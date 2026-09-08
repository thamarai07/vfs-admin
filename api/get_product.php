<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

header("Content-Type: application/json");

// ─── HTTP Caching ─────────────────────────────────────────────────────────────
// Products don't change every second — cache for 60s at CDN/browser level
header("Cache-Control: public, max-age=60, stale-while-revalidate=120");

// ─── Input validation ─────────────────────────────────────────────────────────
$id   = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$slug = strtolower(urldecode(trim($_GET['slug'] ?? '')));

// Allow lookup by numeric slug too (catalog search results have no slug).
if ($id <= 0 && ctype_digit($slug)) {
    $id = (int) $slug;
    $slug = '';
}

if ($id <= 0 && $slug === '') {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Product id or slug is required"]);
    exit;
}

// ─── Simple file-based cache (60s TTL) ───────────────────────────────────────
// Avoids hitting the DB on every page view for the same product.
// Swap this for Redis/Memcached in production for better performance.
$cacheDir  = sys_get_temp_dir() . '/vfs_product_cache';
$cacheKey  = preg_replace('/[^a-z0-9\-]/', '_', $id > 0 ? "id-$id" : $slug);
$cacheFile = "$cacheDir/$cacheKey.json";
$cacheTTL  = 60; // seconds

if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
    // Cache HIT — return instantly, no DB query
    header("X-Cache: HIT");
    readfile($cacheFile);
    exit;
}

header("X-Cache: MISS");

// ─── DB query ─────────────────────────────────────────────────────────────────
try {
    // Main product — single optimised query
    // Make sure these indexes exist on your table:
    //   CREATE INDEX idx_slug       ON products (slug);
    //   CREATE INDEX idx_active     ON products (is_active);
    //   CREATE INDEX idx_cat_active ON products (category, is_active, stock);
    $productSql = "
        SELECT
            id, name, slug, category, price_per_kg, discount_percent, unit,
            min_quantity, max_quantity, stock, image, description,
            meta_title, meta_description, tags, is_featured, is_active,
            created_at, updated_at
        FROM products
        WHERE %s AND is_active = 1
        LIMIT 1
    ";
    if ($id > 0) {
        $stmt = $conn->prepare(sprintf($productSql, "id = ?"));
        $stmt->execute([$id]);
    } else {
        $stmt = $conn->prepare(sprintf($productSql, "LOWER(slug) = ?"));
        $stmt->execute([$slug]);
    }
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        // Try fuzzy suggestions in one query
        $suggestStmt = $conn->prepare("
            SELECT name, slug
            FROM products
            WHERE LOWER(name) LIKE ? AND is_active = 1
            LIMIT 3
        ");
        $suggestStmt->execute(['%' . str_replace(['-', '_'], ' ', $slug) . '%']);
        $suggestions = $suggestStmt->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(404);
        echo json_encode([
            "status"      => "error",
            "message"     => "Product not found or inactive",
            "suggestions" => $suggestions
        ]);
        exit;
    }

    // ─── Cast types ───────────────────────────────────────────────────────────
    $row['price_per_kg']    = (float)$row['price_per_kg'];
    $row['discount_percent']= (float)$row['discount_percent'];
    $row['min_quantity']    = (float)$row['min_quantity'];
    $row['max_quantity']    = (float)$row['max_quantity'];
    $row['stock']           = (int)$row['stock'];
    $row['is_featured']     = (int)$row['is_featured'];
    $row['is_active']       = (int)$row['is_active'];

    // ─── Pricing ─────────────────────────────────────────────────────────────
    $finalPrice = $row['price_per_kg'] * (1 - $row['discount_percent'] / 100);
    $row['final_price']      = round($finalPrice, 2);
    $row['savings_per_unit'] = round($row['price_per_kg'] - $finalPrice, 2);

    // ─── Related products — single query, no loop ─────────────────────────────
    $relatedStmt = $conn->prepare("
        SELECT id, name, slug, category, price_per_kg, discount_percent,
               unit, image, stock, is_featured
        FROM products
        WHERE category = ? AND id != ? AND is_active = 1 AND stock > 0
        ORDER BY is_featured DESC, created_at DESC
        LIMIT 4
    ");
    $relatedStmt->execute([$row['category'], $row['id']]);
    $related = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);

    // Cast related in one pass
    $related = array_map(function ($p) {
        $p['price_per_kg']     = (float)$p['price_per_kg'];
        $p['discount_percent'] = (float)$p['discount_percent'];
        $p['stock']            = (int)$p['stock'];
        $p['is_featured']      = (int)$p['is_featured'];
        $p['final_price']      = round($p['price_per_kg'] * (1 - $p['discount_percent'] / 100), 2);
        return $p;
    }, $related);

    // ─── Static placeholders (replace with real analytics/reviews table) ──────
    // Using a deterministic hash so the numbers don't flicker on every refresh
    $idHash = crc32($row['id'] . 'vfs_salt');
    $row['view_count']   = 150  + abs($idHash % 2350);
    $row['rating']       = round(4.0 + (abs($idHash % 10) / 10), 1);
    $row['review_count'] = 25   + abs(($idHash >> 4) % 425);

    // ─── Build response and cache it ──────────────────────────────────────────
    $response = json_encode([
        "status"           => "success",
        "product"          => $row,
        "related_products" => $related
    ]);

    // Write to cache (atomic write via temp file)
    $tmpFile = $cacheFile . '.tmp';
    file_put_contents($tmpFile, $response);
    rename($tmpFile, $cacheFile);

    echo $response;

} catch (Exception $e) {
    error_log("get_product.php Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "An error occurred. Please try again."]);
}
?>