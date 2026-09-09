<?php
/**
 * Create Order API (COD) - WITH WISHLIST STATUS UPDATE
 * File: create_order.php
 */

require_once __DIR__ . '/../config/cors.php';  // Handles CORS + OPTIONS preflight
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Database connection
require_once('../config/db.php');
require_once('../config/jwt.php');

// Authenticate — customerId comes from token, NOT from client body
$authUser   = requireAuth();
$customerId = (int) $authUser['user_id'];

try {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Validate required fields (customerId now comes from JWT — not required in body)
    if (!isset($data['items']) || !isset($data['address']) || !isset($data['total'])) {
        throw new Exception('Missing required fields');
    }

    // Validate items array
    if (!is_array($data['items']) || empty($data['items'])) {
        throw new Exception('Cart is empty or invalid');
    }

    // Extract data (customerId already set from JWT above)
    $items         = $data['items'];
    $address       = $data['address'];
    $notes         = isset($data['notes']) ? trim($data['notes']) : '';
    $paymentMethod = isset($data['paymentMethod']) ? $data['paymentMethod'] : 'cod';

    // Calculate totals
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += floatval($item['subtotal']);
    }

    // Recalculate totals (match frontend calculation)
    $tax = $subtotal * 0.08; // 8% tax
    $shippingCharge = $subtotal > 500 ? 0 : 50;
    $totalAmount = $subtotal + $tax + $shippingCharge;

    // More flexible validation (allow 1 rupee difference for rounding)
    $receivedTotal = floatval($data['total']);
    if (abs($totalAmount - $receivedTotal) > 1.0) {
        error_log("Total mismatch - Calculated: $totalAmount, Received: $receivedTotal");
        throw new Exception("Total amount mismatch. Calculated: ₹" . number_format($totalAmount, 2) . ", Received: ₹" . number_format($receivedTotal, 2));
    }

    // Use the frontend total to avoid rounding issues
    $totalAmount = $receivedTotal;

    // Start transaction
    $conn->beginTransaction();

    // Generate unique order number
    $orderNumber = 'ORD' . date('Ymd') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

    // Check if order number already exists (retry if duplicate)
    $checkStmt = $conn->prepare("SELECT id FROM orders WHERE order_number = ?");
    $checkStmt->execute([$orderNumber]);

    if ($checkStmt->rowCount() > 0) {
        // Generate new order number if duplicate
        $orderNumber = 'ORD' . date('Ymd') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    // Extract address details
    $customerName = $address['name'];
    $customerPhone = $address['phoneNumber'];

    // Build full address string from whatever structured parts were sent.
    // Falls back gracefully to the legacy `fullAddress` line.
    $addressKeys = ['flatNo', 'streetAddress', 'area', 'landmark', 'city', 'state', 'pincode', 'country'];
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
    $customerAddress = implode(', ', $addressParts);

    // Insert order into orders table
    $stmt = $conn->prepare("
        INSERT INTO orders (
            order_number, customer_id, customer_name, customer_phone, customer_address,
            subtotal, tax, shipping_charge, total_amount, notes,
            payment_method, payment_status, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'unpaid', 'pending')
    ");

    $stmt->execute([
        $orderNumber,
        $customerId,
        $customerName,
        $customerPhone,
        $customerAddress,
        $subtotal,
        $tax,
        $shippingCharge,
        $totalAmount,
        $notes,
        $paymentMethod
    ]);

    $orderId = $conn->lastInsertId();

    // Insert order items.
    // `unit` is an additive nullable column — only used when the festival
    // migration has been run; otherwise this behaves exactly as before.
    $hasItemUnit = false;
    try {
        $hasItemUnit = (bool) $conn->query("SHOW COLUMNS FROM order_items LIKE 'unit'")->fetch();
    } catch (Throwable $e) {
        $hasItemUnit = false;
    }

    $itemStmt = $hasItemUnit
        ? $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, unit, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?)")
        : $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?, ?)");

    // Collect product IDs from order items
    $orderedProductIds = [];
    $invoiceItems = []; // for the confirmation email

    foreach ($items as $item) {
        $productId = intval($item['id']);
        $productName = $item['name'];
        $quantity = floatval($item['quantity']);
        $price = floatval($item['price']);
        $itemSubtotal = floatval($item['subtotal']);
        $unit = (isset($item['unit']) && strtolower(trim((string)$item['unit'])) === 'piece') ? 'piece' : null;

        $itemStmt->execute($hasItemUnit
            ? [$orderId, $productId, $productName, $unit, $quantity, $price, $itemSubtotal]
            : [$orderId, $productId, $productName, $quantity, $price, $itemSubtotal]
        );

        // Track product IDs for wishlist update
        $orderedProductIds[] = $productId;

        $invoiceItems[] = [
            'name'     => $productName,
            'unit'     => $unit,
            'quantity' => $quantity,
            'price'    => $price,
            'subtotal' => $itemSubtotal,
        ];
    }

    // Insert status history (if you have this table)
    try {
        $historyStmt = $conn->prepare("
            INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, notes)
            VALUES (?, NULL, 'pending', ?, 'Order created')
        ");
        $historyStmt->execute([$orderId, $customerId]);
    } catch (Exception $e) {
        // If table doesn't exist, just log and continue
        error_log("Status history insert skipped: " . $e->getMessage());
    }

    // 🔥 MARK CART AS 'ORDERED'
    $sessionId = 'user_' . $customerId;
    $updateCartStmt = $conn->prepare("
        UPDATE cart 
        SET 
            status = 'ordered',
            order_id = ?,
            updated_at = NOW()
        WHERE session_id = ? 
        AND status = 'active'
    ");
    $updateCartStmt->execute([$orderId, $sessionId]);
    $markedCartItems = $updateCartStmt->rowCount();

    error_log("✅ Marked $markedCartItems cart items as 'ordered' for order #$orderId");

    // 🔥🔥 NEW: MARK WISHLIST ITEMS AS 'ORDERED' 🔥🔥
    $markedWishlistItems = 0;
    
    if (!empty($orderedProductIds)) {
        // Build placeholders for IN clause
        $placeholders = str_repeat('?,', count($orderedProductIds) - 1) . '?';
        
        $updateWishlistStmt = $conn->prepare("
            UPDATE favorites 
            SET 
                status = 'ordered',
                order_id = ?
            WHERE user_id = ? 
            AND product_id IN ($placeholders)
            AND status = 'active'
        ");
        
        // Merge parameters: orderId, userId, ...productIds
        $params = array_merge([$orderId, $customerId], $orderedProductIds);
        
        $updateWishlistStmt->execute($params);
        $markedWishlistItems = $updateWishlistStmt->rowCount();
        
        error_log("✅ Marked $markedWishlistItems wishlist items as 'ordered' for order #$orderId");
        error_log("   Product IDs: " . implode(', ', $orderedProductIds));
    }

    // Commit transaction
    $conn->commit();

    // ── Email the customer a neat invoice (best-effort, never blocks the order) ──
    $invoiceEmailed = false;
    try {
        $custStmt = $conn->prepare("SELECT email, name FROM customers WHERE id = ?");
        $custStmt->execute([$customerId]);
        $cust = $custStmt->fetch(PDO::FETCH_ASSOC);

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

    // Send success response
    echo json_encode([
        'success' => true,
        'message' => 'Order placed successfully',
        'data' => [
            'orderId' => $orderId,
            'orderNumber' => $orderNumber,
            'totalAmount' => $totalAmount,
            'paymentMethod' => $paymentMethod,
            'orderStatus' => 'pending',
            'cartMarkedAsOrdered' => true,
            'itemsMarked' => $markedCartItems,
            'wishlistItemsMarked' => $markedWishlistItems, // NEW: Track wishlist updates
            'invoiceEmailed' => $invoiceEmailed
        ]
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log("Order creation error: " . $e->getMessage());

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>