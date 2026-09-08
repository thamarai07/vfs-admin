<?php
require_once __DIR__ . '/../config/cors.php';
header("Content-Type: application/json");

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/jwt.php';

$method = $_SERVER['REQUEST_METHOD'];

// ----------------------------------------------------------------
// Authenticate request — extract user_id from verified JWT token.
// Client-supplied user_id values are IGNORED.
// ----------------------------------------------------------------
$authUser = requireAuth();
$user_id  = (int) $authUser['user_id'];
$baseUrl  = env('IMAGE_BASE_URL');

// ----------------------------------------------------------------
// Feature-detect the additive KG/Piece columns. If the festival
// migration has NOT been run yet, every branch below transparently
// falls back to the exact pre-feature behaviour.
// ----------------------------------------------------------------
$HAS_CART_UNIT = $HAS_PIECE_PRICE = false;
try {
    $HAS_CART_UNIT   = (bool) $conn->query("SHOW COLUMNS FROM cart LIKE 'unit'")->fetch();
    $HAS_PIECE_PRICE = (bool) $conn->query("SHOW COLUMNS FROM products LIKE 'piece_price'")->fetch();
} catch (Throwable $e) {
    $HAS_CART_UNIT = $HAS_PIECE_PRICE = false;
}
$UNIT_ENABLED = $HAS_CART_UNIT && $HAS_PIECE_PRICE;

try {
    switch ($method) {

        // ✅ GET - Fetch cart items
        case 'GET':
            // Effective per-unit price: piece_price only when the buyer explicitly
            // chose 'piece' AND the product has a piece price. Otherwise it is the
            // exact legacy expression (p.price_per_kg) — unchanged for every row
            // written before this feature (c.unit IS NULL).
            $priceExpr = $UNIT_ENABLED
                ? "CASE WHEN c.unit = 'piece' AND p.piece_price IS NOT NULL
                        THEN p.piece_price ELSE p.price_per_kg END"
                : "p.price_per_kg";
            $unitCols = $HAS_CART_UNIT ? "c.unit, p.unit as product_unit," : "";
            $stmt = $conn->prepare("
                SELECT
                    c.id as cart_id,
                    p.id,
                    p.name,
                    p.slug,
                    $priceExpr as price,
                    p.image,
                    p.category,
                    p.stock,
                    c.quantity,
                    $unitCols
                    c.status,
                    c.last_added_at,
                    (c.quantity * $priceExpr) as subtotal
                FROM cart c
                INNER JOIN products p ON c.product_id = p.id
                WHERE c.session_id = ?
                AND c.status = 'active'
                ORDER BY c.created_at DESC, c.id DESC
            ");
            $stmt->execute(['user_' . $user_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $total = 0;
            foreach ($items as &$item) {
                if (!empty($item['image'])) {
                    $images = explode(',', $item['image']);
                    $item['image'] = $baseUrl . trim($images[0]);
                } else {
                    $item['image'] = "https://placehold.co/200x200?text=No+Image";
                }
                $item['id']       = (int)   $item['id'];
                $item['cart_id']  = (int)   $item['cart_id'];
                $item['price']    = (float) $item['price'];
                $item['quantity'] = (float) $item['quantity'];
                $item['subtotal'] = (float) $item['subtotal'];
                $item['stock']    = (int)   $item['stock'];

                // Effective unit + human label (additive fields; existing rows -> 'kg')
                $effUnit = ($item['unit'] ?? null) ?: (($item['product_unit'] ?? null) ?: 'kg');
                $item['unit']       = $effUnit;
                $item['unit_label'] = in_array($effUnit, ['piece', 'pieces'], true)
                    ? 'Piece'
                    : (in_array($effUnit, ['dozen', 'bunch', 'pack'], true) ? ucfirst($effUnit) : 'kg');
                unset($item['product_unit']);

                $total += $item['subtotal'];
            }
            unset($item);

            echo json_encode([
                "status"  => "success",
                "data"    => $items,
                "count"   => count($items),
                "total"   => round($total, 2),
                "user_id" => $user_id
            ]);
            break;

        // ✅ POST - Add to cart
        case 'POST':
            $input      = json_decode(file_get_contents('php://input'), true);
            $product_id = (int)   ($input['product_id']   ?? 0);
            $quantity   = (float) ($input['quantity']      ?? 0.25);
            $last_added = $input['last_added_at'] ?? date('Y-m-d H:i:s');
            // Optional selling unit — only 'piece' is meaningful; anything else
            // (including absent) stays NULL => legacy price_per_kg behaviour.
            $unit       = (isset($input['unit']) && strtolower(trim((string)$input['unit'])) === 'piece')
                            ? 'piece' : null;
            // user_id comes from JWT — ignore any client-supplied value

            if ($product_id <= 0 || $quantity <= 0) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "Invalid data"]);
                exit;
            }

            // Validate stock
            $stockCheck = $conn->prepare("SELECT stock, name FROM products WHERE id = ?");
            $stockCheck->execute([$product_id]);
            $product = $stockCheck->fetch();

            if (!$product) {
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "Product not found"]);
                exit;
            }

            if ($product['stock'] < $quantity) {
                http_response_code(400);
                echo json_encode([
                    "status"  => "error",
                    "message" => "Insufficient stock. Only {$product['stock']} kg available"
                ]);
                exit;
            }

            $session_id = 'user_' . $user_id;

            // Check if already in cart
            $checkStmt = $conn->prepare("
                SELECT id, quantity
                FROM cart
                WHERE session_id = ? AND product_id = ? AND status = 'active'
            ");
            $checkStmt->execute([$session_id, $product_id]);
            $existing = $checkStmt->fetch();

            if ($existing) {
                $newQty = $existing['quantity'] + $quantity;

                if ($newQty > $product['stock']) {
                    http_response_code(400);
                    echo json_encode([
                        "status"  => "error",
                        "message" => "Cannot add more. Total would exceed available stock"
                    ]);
                    exit;
                }

                if ($HAS_CART_UNIT) {
                    $conn->prepare("
                        UPDATE cart SET quantity = ?, unit = ?, last_added_at = ?, updated_at = NOW()
                        WHERE id = ?
                    ")->execute([$newQty, $unit, $last_added, $existing['id']]);
                } else {
                    $conn->prepare("
                        UPDATE cart SET quantity = ?, last_added_at = ?, updated_at = NOW()
                        WHERE id = ?
                    ")->execute([$newQty, $last_added, $existing['id']]);
                }

                echo json_encode([
                    "status"   => "success",
                    "message"  => "Cart updated successfully",
                    "action"   => "updated",
                    "quantity" => $newQty,
                    "user_id"  => $user_id
                ]);
            } else {
                if ($HAS_CART_UNIT) {
                    $conn->prepare("
                        INSERT INTO cart (session_id, product_id, unit, quantity, status, last_added_at, created_at, updated_at)
                        VALUES (?, ?, ?, ?, 'active', ?, NOW(), NOW())
                    ")->execute([$session_id, $product_id, $unit, $quantity, $last_added]);
                } else {
                    $conn->prepare("
                        INSERT INTO cart (session_id, product_id, quantity, status, last_added_at, created_at, updated_at)
                        VALUES (?, ?, ?, 'active', ?, NOW(), NOW())
                    ")->execute([$session_id, $product_id, $quantity, $last_added]);
                }

                echo json_encode([
                    "status"   => "success",
                    "message"  => "Added to cart successfully",
                    "action"   => "added",
                    "cart_id"  => $conn->lastInsertId(),
                    "user_id"  => $user_id
                ]);
            }
            break;

        // ✅ PUT - Update cart quantity
        case 'PUT':
            $input      = json_decode(file_get_contents('php://input'), true);
            $product_id = (int)   ($input['product_id']   ?? 0);
            $quantity   = (float) ($input['quantity']      ?? 0);
            $last_added = $input['last_added_at'] ?? date('Y-m-d H:i:s');
            $hasUnit    = array_key_exists('unit', $input);
            $unit       = ($hasUnit && strtolower(trim((string)$input['unit'])) === 'piece') ? 'piece' : null;
            // user_id comes from JWT — ignore any client-supplied value

            if ($quantity <= 0) {
                $conn->prepare("
                    DELETE FROM cart
                    WHERE session_id = ? AND product_id = ? AND status = 'active'
                ")->execute(['user_' . $user_id, $product_id]);

                echo json_encode(["status" => "success", "message" => "Item removed"]);
                exit;
            }

            // Validate stock
            $stockCheck = $conn->prepare("SELECT stock FROM products WHERE id = ?");
            $stockCheck->execute([$product_id]);
            $product = $stockCheck->fetch();

            if ($quantity > $product['stock']) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "Quantity exceeds available stock"]);
                exit;
            }

            // Only overwrite `unit` when the client actually sent one, so a plain
            // quantity bump from an existing screen never clobbers the stored unit.
            if ($HAS_CART_UNIT && $hasUnit) {
                $conn->prepare("
                    UPDATE cart
                    SET quantity = ?, unit = ?, last_added_at = ?, updated_at = NOW()
                    WHERE session_id = ? AND product_id = ? AND status = 'active'
                ")->execute([$quantity, $unit, $last_added, 'user_' . $user_id, $product_id]);
            } else {
                $conn->prepare("
                    UPDATE cart
                    SET quantity = ?, last_added_at = ?, updated_at = NOW()
                    WHERE session_id = ? AND product_id = ? AND status = 'active'
                ")->execute([$quantity, $last_added, 'user_' . $user_id, $product_id]);
            }

            echo json_encode(["status" => "success", "message" => "Quantity updated"]);
            break;

        // ✅ DELETE - Remove from cart
        case 'DELETE':
            $product_id = (int) ($_GET['product_id'] ?? 0);
            // user_id comes from JWT — ignore any client-supplied value

            $conn->prepare("
                DELETE FROM cart
                WHERE session_id = ? AND product_id = ? AND status = 'active'
            ")->execute(['user_' . $user_id, $product_id]);

            echo json_encode(["status" => "success", "message" => "Removed from cart"]);
            break;

        default:
            http_response_code(405);
            echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    }

} catch (Exception $e) {
    error_log("Cart API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "An error occurred. Please try again."]);
}