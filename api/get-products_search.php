<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

require_once "../config/db.php";

try {
    // Get search query from URL parameter
    $searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Base URL for images
    $baseUrl = "http://localhost/vfs_portal/vfs-admin/assets/images/uploads/";
    
    $response = [
        'success' => true,
        'searchResults' => [],
        'topSelling' => [],
        'searchQuery' => $searchQuery,
        'resultCount' => 0
    ];
    
    // Search for products matching the query
    if (!empty($searchQuery)) {
        $searchStmt = $conn->prepare("
            SELECT 
                id,
                name,
                category,
                price,
                price_per_kg,
                stock,
                image,
                description
            FROM products 
            WHERE (name LIKE :search1 
               OR category LIKE :search2 
               OR description LIKE :search3)
            AND stock > 0
            ORDER BY name ASC
            LIMIT 5
        ");
        
        $searchTerm = "%{$searchQuery}%";
        $searchStmt->bindParam(':search1', $searchTerm, PDO::PARAM_STR);
        $searchStmt->bindParam(':search2', $searchTerm, PDO::PARAM_STR);
        $searchStmt->bindParam(':search3', $searchTerm, PDO::PARAM_STR);
        $searchStmt->execute();
        
        $searchResults = $searchStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fix image paths for search results
        foreach ($searchResults as &$product) {
            if (!empty($product['image'])) {
                $images = explode(',', $product['image']);
                $product['image'] = $baseUrl . trim($images[0]);
            } else {
                $product['image'] = $baseUrl . 'default-product.jpg';
            }
        }
        unset($product); // Break reference
        
        $response['searchResults'] = $searchResults;
        $response['resultCount'] = count($searchResults);
    }
    
    // Get top selling products based on order items
    $topSellingStmt = $conn->prepare("
        SELECT 
            p.id,
            p.name,
            p.category,
            p.price,
            p.price_per_kg,
            p.stock,
            p.image,
            p.description,
            COALESCE(SUM(oi.quantity), 0) as total_sold
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        WHERE p.stock > 0
        GROUP BY p.id, p.name, p.category, p.price, p.price_per_kg, p.stock, p.image, p.description
        ORDER BY total_sold DESC, p.created_at DESC
        LIMIT 5
    ");
    
    $topSellingStmt->execute();
    $topSellingProducts = $topSellingStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fix image paths for top selling products
    foreach ($topSellingProducts as &$product) {
        if (!empty($product['image'])) {
            $images = explode(',', $product['image']);
            $product['image'] = $baseUrl . trim($images[0]);
        } else {
            $product['image'] = $baseUrl . 'default-product.jpg';
        }
    }
    unset($product); // Break reference
    
    $response['topSelling'] = $topSellingProducts;
    
    echo json_encode($response);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
?>