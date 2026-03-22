<?php
require_once __DIR__ . '/../config/cors.php';
header("Content-Type: application/json");

require_once __DIR__ . '/../config/db.php';

$method  = $_SERVER['REQUEST_METHOD'];
$baseUrl = env('IMAGE_BASE_URL');

// user_id – kept as-is (no JWT on frontend yet)
// Cart/wishlist currently pass user_id from the client
$user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 1;

try {
    switch ($method) {

        // ✅ GET - Fetch all ACTIVE wishlist items
        case 'GET':
            $stmt = $conn->prepare("
                SELECT
                    f.id as wishlist_id,
                    p.id,
                    p.name,
                    p.price_per_kg as price,
                    p.image,
                    p.category,
                    p.stock,
                    f.status,
                    f.order_id,
                    f.created_at
                FROM favorites f
                INNER JOIN products p ON f.product_id = p.id
                WHERE f.user_id = ? AND f.status = 'active'
                ORDER BY f.created_at DESC
            ");
            $stmt->execute([$user_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($items as &$item) {
                if (!empty($item['image'])) {
                    $images       = explode(',', $item['image']);
                    $item['image'] = $baseUrl . trim($images[0]);
                } else {
                    $item['image'] = "https://placehold.co/200x200?text=No+Image";
                }
                $item['id']    = (int)   $item['id'];
                $item['price'] = (float) $item['price'];
            }
            unset($item);

            echo json_encode([
                "status" => "success",
                "data"   => $items,
                "count"  => count($items)
            ]);
            break;

        // ✅ POST - Add to wishlist
        case 'POST':
            $input      = json_decode(file_get_contents('php://input'), true);
            $product_id = (int) ($input['product_id'] ?? 0);

            // Accept user_id from body if sent
            if (isset($input['user_id'])) {
                $user_id = (int) $input['user_id'];
            }

            if ($product_id <= 0) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "Invalid product ID"]);
                exit;
            }

            // Check product exists
            $productCheck = $conn->prepare("SELECT id FROM products WHERE id = ?");
            $productCheck->execute([$product_id]);

            if ($productCheck->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "Product not found", "product_id" => $product_id]);
                exit;
            }

            // Already in active wishlist?
            $checkStmt = $conn->prepare("
                SELECT id FROM favorites
                WHERE user_id = ? AND product_id = ? AND status = 'active'
            ");
            $checkStmt->execute([$user_id, $product_id]);

            if ($checkStmt->rowCount() > 0) {
                echo json_encode(["status" => "info", "message" => "Already in wishlist"]);
                exit;
            }

            // Was previously ordered – reactivate
            $checkOrdered = $conn->prepare("
                SELECT id FROM favorites
                WHERE user_id = ? AND product_id = ? AND status = 'ordered'
            ");
            $checkOrdered->execute([$user_id, $product_id]);

            if ($checkOrdered->rowCount() > 0) {
                $conn->prepare("
                    UPDATE favorites
                    SET status = 'active', order_id = NULL, created_at = NOW()
                    WHERE user_id = ? AND product_id = ? AND status = 'ordered'
                ")->execute([$user_id, $product_id]);

                echo json_encode(["status" => "success", "message" => "Re-added to wishlist"]);
                exit;
            }

            // Insert new
            $conn->prepare("
                INSERT INTO favorites (user_id, product_id, status, created_at)
                VALUES (?, ?, 'active', NOW())
            ")->execute([$user_id, $product_id]);

            echo json_encode([
                "status"      => "success",
                "message"     => "Added to wishlist",
                "wishlist_id" => $conn->lastInsertId()
            ]);
            break;

        // ✅ DELETE - Remove from wishlist
        case 'DELETE':
            $product_id = (int) ($_GET['product_id'] ?? 0);

            if (isset($_GET['user_id'])) {
                $user_id = (int) $_GET['user_id'];
            }

            if ($product_id <= 0) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "Invalid product ID"]);
                exit;
            }

            $stmt = $conn->prepare("
                DELETE FROM favorites
                WHERE user_id = ? AND product_id = ? AND status = 'active'
            ");
            $stmt->execute([$user_id, $product_id]);

            echo json_encode([
                "status"        => "success",
                "message"       => "Removed from wishlist",
                "affected_rows" => $stmt->rowCount()
            ]);
            break;

        default:
            http_response_code(405);
            echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    }

} catch (Exception $e) {
    error_log("Wishlist API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "An error occurred. Please try again."]);
}