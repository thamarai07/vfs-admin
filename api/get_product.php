<?php
require_once __DIR__ . '/../config/cors.php';
header("Content-Type: application/json");

require_once __DIR__ . '/../config/db.php';

// Get and decode slug properly
$slug = trim($_GET['slug'] ?? '');
$slug = urldecode($slug); // Decode once to handle double-encoding
$slug = strtolower($slug); // Normalize to lowercase

if (empty($slug)) {
    echo json_encode(["status" => "error", "message" => "Slug is required"]);
    exit;
}

try {
    // Main product query
    $sql = "
        SELECT 
            id, name, slug, category, price_per_kg, discount_percent, unit,
            min_quantity, max_quantity, stock, image, description,
            meta_title, meta_description, tags, is_featured, is_active,
            created_at, updated_at
        FROM products 
        WHERE LOWER(slug) = ? AND is_active = 1
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        // Clean numeric fields
        $row['price_per_kg'] = (float)$row['price_per_kg'];
        $row['discount_percent'] = (float)$row['discount_percent'];
        $row['min_quantity'] = (float)$row['min_quantity'];
        $row['max_quantity'] = (float)$row['max_quantity'];
        $row['stock'] = (int)$row['stock'];
        $row['is_featured'] = (int)$row['is_featured'];
        $row['is_active'] = (int)$row['is_active'];

        // Calculate pricing
        $original_price = $row['price_per_kg'];
        $discount = $row['discount_percent'];
        $final_price = $original_price * (1 - $discount / 100);
        $savings = $original_price - $final_price;

        $row['final_price'] = round($final_price, 2);
        $row['savings_per_unit'] = round($savings, 2);

        // Get related products (same category, exclude current)
        $relatedSql = "
            SELECT id, name, slug, category, price_per_kg, discount_percent, 
                   unit, image, stock, is_featured
            FROM products 
            WHERE category = ? AND id != ? AND is_active = 1 AND stock > 0
            ORDER BY is_featured DESC, created_at DESC
            LIMIT 4
        ";
        $relatedStmt = $conn->prepare($relatedSql);
        $relatedStmt->execute([$row['category'], $row['id']]);
        $related = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);

        // Clean related products
        foreach ($related as &$prod) {
            $prod['price_per_kg'] = (float)$prod['price_per_kg'];
            $prod['discount_percent'] = (float)$prod['discount_percent'];
            $prod['stock'] = (int)$prod['stock'];
            $prod['is_featured'] = (int)$prod['is_featured'];
            $prod['final_price'] = round(
                $prod['price_per_kg'] * (1 - $prod['discount_percent'] / 100), 
                2
            );
        }

        // Get view count and popularity score
        $row['view_count'] = rand(150, 2500); // Replace with real analytics later
        $row['rating'] = rand(40, 50) / 10; // Replace with real reviews later
        $row['review_count'] = rand(25, 450);

        echo json_encode([
            "status" => "success",
            "product" => $row,
            "related_products" => $related
        ]);
    } else {
        // Try to find similar products for suggestions
        $suggestSql = "
            SELECT name, slug 
            FROM products 
            WHERE LOWER(name) LIKE ? AND is_active = 1
            LIMIT 3
        ";
        $suggestStmt = $conn->prepare($suggestSql);
        $searchTerm = '%' . str_replace(['-', '_'], ' ', $slug) . '%';
        $suggestStmt->execute([$searchTerm]);
        $suggestions = $suggestStmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            "status" => "error",
            "message" => "Product not found or inactive",
            "suggestions" => $suggestions
        ]);
    }
} catch (Exception $e) {
    error_log("get_product.php Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "status"  => "error",
        "message" => "An error occurred. Please try again."
    ]);
}
?>