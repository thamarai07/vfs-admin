<?php
/**
 * Create Order API (COD) - WITH WISHLIST STATUS UPDATE
 * File: create_order.php
 *
 * SECURITY: every price is taken from the products table, never from the
 * client. The client only says WHICH products, HOW MUCH and in WHICH unit.
 * Name / price / subtotal fields sent by older clients are ignored.
 *
 * Pricing rules (identical to cart.php so the cart and the order agree):
 *   kg line    -> products.price_per_kg  x quantity
 *   piece line -> products.piece_price   x quantity (whole pieces only)
 *   tax 8%, delivery ₹50 unless the subtotal is above ₹500.
 *
 * Stock: kg lines are checked and reduced inside the same transaction
 * (rows locked with FOR UPDATE, so two buyers can't take the last kilo).
 * Piece lines are not taken from the kg stock.
 */

require_once __DIR__ . '/../config/cors.php';  // Handles CORS + OPTIONS preflight
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/jwt.php';

const TAX_RATE              = 0.08;
const FREE_SHIPPING_ABOVE   = 500;
const SHIPPING_FEE          = 50;
const MAX_LINE_QUANTITY     = 100;

// Authenticate — customerId comes from token, NOT from client body.
// Auth is optional: a valid Bearer token maps to a logged-in customer_id;
// no/invalid token means a guest order (customer_id stays NULL).
$authUser   = optionalAuth();
$customerId = $authUser ? (int) $authUser['user_id'] : null;
$isGuest    = $customerId === null;

/** Error the customer should see (safe to show). */
class OrderError extends Exception {}

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!is_array($data) || !isset($data['items'], $data['address'], $data['total'])) {
        throw new OrderError('Missing required fields');
    }
    if (!is_array($data['items']) || empty($data['items'])) {
        throw new OrderError('Cart is empty or invalid');
    }
    if (count($data['items']) > 100) {
        throw new OrderError('Too many items in one order');
    }

    $items   = $data['items'];
    $address = is_array($data['address']) ? $data['address'] : [];
    $notes   = mb_substr(trim((string) ($data['notes'] ?? '')), 0, 1000);

    // orders.payment_method is ENUM('cash','online','upi'). 'cod' used to be
    // written as-is, which MySQL stored as '' — so map it.
    $pmRaw         = strtolower(trim((string) ($data['paymentMethod'] ?? 'cod')));
    $paymentMethod = in_array($pmRaw, ['online', 'upi'], true) ? $pmRaw : 'cash';

    // ── Delivery details ─────────────────────────────────────────────
    $customerName  = mb_substr(trim((string) ($address['name'] ?? '')), 0, 100);
    $customerPhone = substr(preg_replace('/\D/', '', (string) ($address['phoneNumber'] ?? $address['phone'] ?? '')), -10);
    if ($customerName === '') {
        throw new OrderError('Please add the receiver’s name to the delivery address');
    }
    if (!preg_match('/^\d{10}$/', $customerPhone)) {
        throw new OrderError('Please add a valid 10-digit mobile number to the delivery address');
    }

    // Build full address string from whatever structured parts were sent.
    // Falls back gracefully to the legacy `fullAddress` line.
    $addressKeys  = ['flatNo', 'streetAddress', 'area', 'landmark', 'city', 'state', 'pincode', 'country'];
    $addressParts = [];
    foreach ($addressKeys as $key) {
        if (isset($address[$key]) && trim((string) $address[$key]) !== '') {
            $part = trim((string) $address[$key]);
            $addressParts[] = ($key === 'landmark') ? ('Near ' . $part) : $part;
        }
    }
    if (empty($addressParts) && !empty($address['fullAddress'])) {
        $addressParts[] = $address['fullAddress'];
    } elseif (!empty($address['fullAddress']) && empty($address['streetAddress'])) {
        // legacy callers only send fullAddress (+ flatNo/landmark)
        $addressParts[] = $address['fullAddress'];
    }
    $customerAddress = mb_substr(implode(', ', $addressParts), 0, 2000);
    if ($customerAddress === '') {
        throw new OrderError('Please add a delivery address');
    }

    // ── Optional columns (feature-detected, like the rest of the API) ──
    $hasColumn = function (string $table, string $col) use ($conn): bool {
        try {
            return (bool) $conn->query("SHOW COLUMNS FROM `$table` LIKE " . $conn->quote($col))->fetch();
        } catch (Throwable $e) {
            return false;
        }
    };
    $hasItemUnit     = $hasColumn('order_items', 'unit');
    $hasPiecePrice   = $hasColumn('products', 'piece_price');
    $hasStockFlag    = $hasColumn('orders', 'stock_deducted');

    // ── Normalise the requested lines (product, unit, quantity) ───────
    // The unit an older client didn't send is read from the buyer's active cart row.
    $cartUnitStmt = (!$isGuest && $hasItemUnit)
        ? $conn->prepare("SELECT unit FROM cart WHERE session_id = ? AND product_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1")
        : null;

    $lines = [];
    foreach ($items as $item) {
        $productId = (int) ($item['id'] ?? $item['product_id'] ?? 0);
        $quantity  = round((float) ($item['quantity'] ?? 0), 2);
        if ($productId <= 0 || $quantity <= 0 || $quantity > MAX_LINE_QUANTITY) {
            throw new OrderError('One of the items in your cart has an invalid quantity');
        }

        $sentUnit = isset($item['unit']) ? strtolower(trim((string) $item['unit'])) : '';
        $unit     = $sentUnit === 'piece' ? 'piece' : 'kg';
        if ($sentUnit === '' && $cartUnitStmt) {
            $cartUnitStmt->execute(['user_' . $customerId, $productId]);
            $cu = $cartUnitStmt->fetchColumn();
            if ($cu !== false && strtolower(trim((string) $cu)) === 'piece') {
                $unit = 'piece';
            }
        }
        if ($unit === 'piece' && floor($quantity) != $quantity) {
            throw new OrderError('Pieces can only be ordered in whole numbers');
        }

        $key = $productId . '|' . $unit;   // same product + unit twice -> one line
        if (isset($lines[$key])) {
            $lines[$key]['quantity'] = round($lines[$key]['quantity'] + $quantity, 2);
        } else {
            $lines[$key] = ['product_id' => $productId, 'unit' => $unit, 'quantity' => $quantity];
        }
    }

    // ── Price everything from the database, inside a transaction ──────
    $conn->beginTransaction();

    $productIds   = array_values(array_unique(array_column($lines, 'product_id')));
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $pieceCol     = $hasPiecePrice ? 'piece_price' : 'NULL AS piece_price';
    $pStmt = $conn->prepare("
        SELECT id, name, price_per_kg, $pieceCol, stock, is_active, max_quantity
        FROM products
        WHERE id IN ($placeholders)
        FOR UPDATE
    ");
    $pStmt->execute($productIds);
    $products = [];
    foreach ($pStmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $products[(int) $p['id']] = $p;
    }

    $subtotal  = 0.0;
    $kgByProduct = [];
    foreach ($lines as &$line) {
        $p = $products[$line['product_id']] ?? null;
        if (!$p || (int) $p['is_active'] !== 1) {
            throw new OrderError('Some items in your cart are no longer available. Please review your cart.');
        }

        if ($line['unit'] === 'piece') {
            $price = (float) ($p['piece_price'] ?? 0);
            if ($price <= 0) {
                throw new OrderError("{$p['name']} is not sold by the piece any more. Please review your cart.");
            }
        } else {
            $price = (float) $p['price_per_kg'];
            $kgByProduct[$line['product_id']] = ($kgByProduct[$line['product_id']] ?? 0) + $line['quantity'];
            $max = (float) ($p['max_quantity'] ?? 0);
            if ($max > 0 && $kgByProduct[$line['product_id']] > $max) {
                throw new OrderError("You can order up to {$max} kg of {$p['name']}");
            }
        }
        if ($price <= 0) {
            throw new OrderError("{$p['name']} can’t be ordered right now. Please try again later.");
        }

        $line['name']     = $p['name'];
        $line['price']    = round($price, 2);
        $line['subtotal'] = round($price * $line['quantity'], 2);
        $subtotal        += $line['subtotal'];
    }
    unset($line);

    // Stock check for kg lines (rows are locked above).
    foreach ($kgByProduct as $pid => $qty) {
        $stock = (float) $products[$pid]['stock'];
        if ($qty > $stock) {
            $left = rtrim(rtrim(number_format(max(0, $stock), 2, '.', ''), '0'), '.');
            throw new OrderError($stock > 0
                ? "Only {$left} kg of {$products[$pid]['name']} is left. Please reduce the quantity."
                : "{$products[$pid]['name']} is out of stock. Please remove it from your cart.");
        }
    }

    $subtotal       = round($subtotal, 2);
    $tax            = round($subtotal * TAX_RATE, 2);
    $shippingCharge = $subtotal > FREE_SHIPPING_ABOVE ? 0 : SHIPPING_FEE;
    $totalAmount    = round($subtotal + $tax + $shippingCharge, 2);

    // The customer must have seen the same total (₹1 allowed for rounding).
    // If a price changed since they loaded the cart, stop and let them review.
    $receivedTotal = (float) $data['total'];
    if (abs($totalAmount - $receivedTotal) > 1.0) {
        error_log("Total mismatch - Calculated: $totalAmount, Received: $receivedTotal");
        throw new OrderError('Prices in your cart have changed. Your new total is ₹' . number_format($totalAmount, 2) . '. Please review your cart and try again.');
    }

    // ── Unique order number ─────────────────────────────────────────
    $checkStmt   = $conn->prepare("SELECT id FROM orders WHERE order_number = ?");
    $orderNumber = null;
    for ($try = 0; $try < 5; $try++) {
        $candidate = 'ORD' . date('Ymd') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $checkStmt->execute([$candidate]);
        if (!$checkStmt->fetch()) {
            $orderNumber = $candidate;
            break;
        }
    }
    if ($orderNumber === null) {
        $orderNumber = 'ORD' . date('YmdHis') . random_int(10, 99);
    }

    // ── Save the order ──────────────────────────────────────────────
    $conn->prepare("
        INSERT INTO orders (
            order_number, customer_id, customer_name, customer_phone, customer_address,
            subtotal, tax, shipping_charge, total_amount, notes,
            payment_method, payment_status, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'unpaid', 'pending')
    ")->execute([
        $orderNumber, $customerId, $customerName, $customerPhone, $customerAddress,
        $subtotal, $tax, $shippingCharge, $totalAmount, $notes, $paymentMethod,
    ]);
    $orderId = (int) $conn->lastInsertId();

    $itemStmt = $hasItemUnit
        ? $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, unit, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?)")
        : $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?, ?)");

    $orderedProductIds = [];
    $invoiceItems      = []; // for the confirmation email
    foreach ($lines as $line) {
        $dbUnit = $line['unit'] === 'piece' ? 'piece' : null;   // NULL = kg (legacy convention)
        $itemStmt->execute($hasItemUnit
            ? [$orderId, $line['product_id'], $line['name'], $dbUnit, $line['quantity'], $line['price'], $line['subtotal']]
            : [$orderId, $line['product_id'], $line['name'], $line['quantity'], $line['price'], $line['subtotal']]
        );
        $orderedProductIds[] = $line['product_id'];
        $invoiceItems[] = [
            'name'     => $line['name'],
            'unit'     => $dbUnit,
            'quantity' => $line['quantity'],
            'price'    => $line['price'],
            'subtotal' => $line['subtotal'],
        ];
    }

    // Take the kg stock.
    if ($kgByProduct) {
        $dec = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        foreach ($kgByProduct as $pid => $qty) {
            $dec->execute([$qty, $pid]);
        }
        if ($hasStockFlag) {
            $conn->prepare("UPDATE orders SET stock_deducted = 1 WHERE id = ?")->execute([$orderId]);
        }
    }

    // Insert status history (if you have this table)
    try {
        $conn->prepare("
            INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, notes)
            VALUES (?, NULL, 'pending', ?, 'Order created')
        ")->execute([$orderId, $customerId]);
    } catch (Exception $e) {
        error_log("Status history insert skipped: " . $e->getMessage());
    }

    $markedCartItems     = 0;
    $markedWishlistItems = 0;
    if (!$isGuest) {
        // Mark the customer's cart as ordered.
        $updateCartStmt = $conn->prepare("
            UPDATE cart SET status = 'ordered', order_id = ?, updated_at = NOW()
            WHERE session_id = ? AND status = 'active'
        ");
        $updateCartStmt->execute([$orderId, 'user_' . $customerId]);
        $markedCartItems = $updateCartStmt->rowCount();

        // Mark matching wishlist items as ordered.
        $ph = implode(',', array_fill(0, count($orderedProductIds), '?'));
        $updateWishlistStmt = $conn->prepare("
            UPDATE favorites SET status = 'ordered', order_id = ?
            WHERE user_id = ? AND product_id IN ($ph) AND status = 'active'
        ");
        $updateWishlistStmt->execute(array_merge([$orderId, $customerId], $orderedProductIds));
        $markedWishlistItems = $updateWishlistStmt->rowCount();
    }

    $conn->commit();

    // ── Email the customer a neat invoice (best-effort, never blocks the order) ──
    $invoiceEmailed = false;
    try {
        if ($isGuest) {
            // Guest order — no customers row to look up; use the email they
            // typed in the delivery address form, if any.
            $guestEmail = trim((string) ($address['email'] ?? ''));
            $cust = ($guestEmail !== '' && filter_var($guestEmail, FILTER_VALIDATE_EMAIL))
                ? ['email' => $guestEmail, 'name' => $customerName]
                : null;
        } else {
            $custStmt = $conn->prepare("SELECT email, name FROM customers WHERE id = ?");
            $custStmt->execute([$customerId]);
            $cust = $custStmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($cust && !empty($cust['email'])) {
            require_once __DIR__ . '/../includes/invoice_email.php';

            // Optional admin copy — set ADMIN_EMAIL in .env to your real inbox.
            $adminEmail = $_ENV['ADMIN_EMAIL'] ?? getenv('ADMIN_EMAIL') ?: '';
            $bcc = ($adminEmail !== '' && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) ? [$adminEmail] : [];

            $invoiceEmailed = sendOrderInvoiceEmail(
                $cust['email'],
                $cust['name'] ?? $customerName,
                [
                    'order_number'     => $orderNumber,
                    'created_at'       => date('Y-m-d H:i:s'),
                    'customer_name'    => $customerName,
                    'customer_phone'   => $customerPhone,
                    'customer_address' => $customerAddress,
                    'subtotal'         => $subtotal,
                    'tax'              => $tax,
                    'shipping_charge'  => $shippingCharge,
                    'total_amount'     => $totalAmount,
                    'payment_method'   => $paymentMethod,
                    'notes'            => $notes,
                ],
                $invoiceItems,
                $bcc
            );

            // Record the send so the admin CMS can show it (nullable column;
            // silently skipped if the migration hasn't run yet).
            if ($invoiceEmailed) {
                try {
                    $conn->prepare("UPDATE orders SET invoice_emailed_at = NOW() WHERE id = ?")
                         ->execute([$orderId]);
                } catch (\Throwable $e) {
                    error_log("[create_order] invoice_emailed_at update skipped: " . $e->getMessage());
                }
            }
        } else {
            error_log("[create_order] no customer email for customer_id={$customerId} — invoice email skipped");
        }
    } catch (\Throwable $e) {
        error_log("[create_order] invoice email skipped: " . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Order placed successfully',
        'data' => [
            'orderId'             => $orderId,
            'orderNumber'         => $orderNumber,
            'subtotal'            => $subtotal,
            'tax'                 => $tax,
            'shippingCharge'      => $shippingCharge,
            'totalAmount'         => $totalAmount,
            'paymentMethod'       => $paymentMethod,
            'orderStatus'         => 'pending',
            'cartMarkedAsOrdered' => !$isGuest,
            'itemsMarked'         => $markedCartItems,
            'wishlistItemsMarked' => $markedWishlistItems,
            'invoiceEmailed'      => $invoiceEmailed,
        ]
    ]);

} catch (OrderError $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    // Database / unexpected errors: log the detail, show a safe message.
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Order creation error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'We couldn’t place your order. Please try again.']);
}
