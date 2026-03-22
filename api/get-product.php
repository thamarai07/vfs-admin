<?php
/**
 * Products API - Production Grade
 * Fetch active products with pagination, filtering, and sorting
 */

require_once __DIR__ . '/../config/cors.php';
header("Content-Type: application/json; charset=UTF-8");

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// Standardized logging: removed hardcoded ini_set('error_log')

function sendResponse(string $status, ?string $message = null, $data = null, array $meta = []): void {
    $code = $status === 'success' ? 200 : ($status === 'error' ? 500 : 400);
    http_response_code($code);
    echo json_encode([
        "status"  => $status,
        "message" => $message,
        "items"   => $data,
        "meta"    => array_merge([
            "total"     => is_array($data) ? count($data) : 0,
            "timestamp" => date('c'),
            "version"   => "1.1.0"
        ], $meta)
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function generateSlug(string $name): string {
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    return trim($slug, '-');
}

try {
    require_once __DIR__ . '/../config/db.php';

    if (!isset($conn) || !($conn instanceof PDO)) {
        throw new Exception("Database connection failed");
    }

    $baseUrl = env('IMAGE_BASE_URL', 'http://localhost/vfs_portal/vfs-admin/assets/images/uploads/');

    // ---- Request Parameters ----
    $limit    = min(max((int) ($_GET['limit']  ?? 12), 1), 100);
    $offset   = max((int) ($_GET['offset'] ?? 0), 0);
    $category = trim($_GET['category'] ?? '');
    $search   = trim($_GET['search']   ?? '');
    $sort     = trim($_GET['sort']     ?? 'newest');
    $featured = isset($_GET['featured']) ? (bool) $_GET['featured'] : false;

    // ---- Build Query ----
    $query      = "SELECT id, name, slug, category, price, price_per_kg, discount_percent,
                          unit, min_quantity, max_quantity, image, description, stock,
                          is_featured, is_active, created_at, updated_at
                   FROM products WHERE is_active = 1";
    $countQuery = "SELECT COUNT(*) as total FROM products WHERE is_active = 1";
    $params     = [];

    if (!empty($category) && $category !== 'all') {
        $query      .= " AND category = ?";
        $countQuery .= " AND category = ?";
        $params[]    = $category;
    }

    if (!empty($search)) {
        $query      .= " AND (name LIKE ? OR description LIKE ? OR category LIKE ?)";
        $countQuery .= " AND (name LIKE ? OR description LIKE ? OR category LIKE ?)";
        $term        = "%$search%";
        $params[]    = $term;
        $params[]    = $term;
        $params[]    = $term;
    }

    if ($featured) {
        $query      .= " AND is_featured = 1";
        $countQuery .= " AND is_featured = 1";
    }

    switch ($sort) {
        case 'oldest':     $query .= " ORDER BY created_at ASC";    break;
        case 'price_low':  $query .= " ORDER BY price_per_kg ASC";  break;
        case 'price_high': $query .= " ORDER BY price_per_kg DESC"; break;
        case 'name':       $query .= " ORDER BY name ASC";          break;
        case 'popular':    $query .= " ORDER BY stock DESC";        break;
        default:           $query .= " ORDER BY created_at DESC";
    }

    $query .= " LIMIT ? OFFSET ?";

    // ---- Execute ----
    $countStmt = $conn->prepare($countQuery);
    $countStmt->execute($params);
    $totalCount = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $conn->prepare($query);
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ---- Process ----
    foreach ($products as &$product) {
        if (!empty($product['image'])) {
            $images     = explode(',', $product['image']);
            $firstImage = trim($images[0]);
            $product['image'] = preg_match('#^https?://#i', $firstImage)
                ? $firstImage
                : $baseUrl . $firstImage;
        } else {
            $product['image'] = "https://placehold.co/300x300/e5e7eb/6b7280?text=No+Image";
        }

        $product['slug'] = empty($product['slug'])
            ? generateSlug($product['name'])
            : strtolower(trim($product['slug']));

        $product['id']               = (int)   $product['id'];
        $product['price']            = (float) $product['price'];
        $product['price_per_kg']     = (float) $product['price_per_kg'];
        $product['discount_percent'] = (float) ($product['discount_percent'] ?? 0);
        $product['min_quantity']     = (float) ($product['min_quantity']     ?? 0.25);
        $product['max_quantity']     = (float) ($product['max_quantity']     ?? 100);
        $product['stock']            = (int)   $product['stock'];
        $product['is_featured']      = (int)   ($product['is_featured']      ?? 0);
        $product['is_active']        = (int)   ($product['is_active']        ?? 1);

        $originalPrice          = $product['price_per_kg'];
        $discount               = $product['discount_percent'];
        $finalPrice             = $originalPrice * (1 - $discount / 100);
        $product['final_price']       = round($finalPrice, 2);
        $product['savings_per_unit']  = round($originalPrice - $finalPrice, 2);
        $product['has_discount']      = $discount > 0;
        $product['in_stock']          = $product['stock'] > 0;
        $product['low_stock']         = $product['stock'] < 20 && $product['stock'] > 0;
        $product['out_of_stock']      = $product['stock'] <= 0;
        $product['thumbnail']         = $product['image'];
        $product['url']               = "/product/" . urlencode($product['slug']);
        $product['unit']              = $product['unit'] ?? 'kg';

        $product['created_at'] = date('Y-m-d H:i:s', strtotime($product['created_at']));
        if (!empty($product['updated_at'])) {
            $product['updated_at'] = date('Y-m-d H:i:s', strtotime($product['updated_at']));
        }
    }
    unset($product);

    $currentPage = floor($offset / $limit) + 1;
    $totalPages  = (int) ceil($totalCount / $limit);

    $meta = [
        "total"        => $totalCount,
        "count"        => count($products),
        "per_page"     => $limit,
        "current_page" => $currentPage,
        "total_pages"  => $totalPages,
        "has_more"     => ($offset + $limit) < $totalCount,
        "filters"      => [
            "category" => $category ?: null,
            "search"   => $search   ?: null,
            "sort"     => $sort,
            "featured" => $featured
        ]
    ];

    sendResponse(
        "success",
        $totalCount > 0 ? "Products loaded successfully" : "No products found",
        $products,
        $meta
    );

} catch (PDOException $e) {
    error_log("[get-product.php] Database Error: " . $e->getMessage());
    sendResponse("error", "Database error occurred. Please try again later.");
} catch (Exception $e) {
    error_log("[get-product.php] Error: " . $e->getMessage());
    sendResponse("error", "An error occurred while fetching products.");
}