<?php
/**
 * Get Festivals API  (NEW — isolated, does not touch any existing endpoint)
 * ---------------------------------------------------------------------------
 * GET /api/get_festivals.php
 *   -> list of currently-active festivals, ordered by display_order.
 *
 * GET /api/get_festivals.php?all=1          (optional, for CMS preview)
 *   -> include inactive / out-of-window festivals too.
 *
 * Response:
 *   { "status":"success", "items":[ { id, name, slug, tagline, theme_color,
 *                                     banner_image, mobile_image } ], "meta":{...} }
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    if (!isset($conn) || !($conn instanceof PDO)) {
        throw new RuntimeException('Database connection unavailable');
    }

    $baseUrl      = rtrim(env('IMAGE_BASE_URL', ''), '/') . '/';
    $includeAll   = isset($_GET['all']) && $_GET['all'] == '1';

    $where = [];
    if (!$includeAll) {
        $where[] = 'f.is_active = 1';
        $where[] = '(f.start_date IS NULL OR f.start_date <= CURDATE())';
        $where[] = '(f.end_date   IS NULL OR f.end_date   >= CURDATE())';
    }
    $whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $stmt = $conn->query("
        SELECT f.id, f.name, f.slug, f.tagline, f.description, f.theme_color,
               f.banner_image, f.mobile_image, f.display_order, f.is_active,
               f.start_date, f.end_date,
               (SELECT COUNT(*) FROM festival_products fp
                 WHERE fp.festival_id = f.id AND fp.is_active = 1) AS product_count
        FROM festivals f
        $whereSQL
        ORDER BY f.display_order ASC, f.name ASC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $items = array_map(static function (array $f) use ($baseUrl): array {
        $img = static function ($name) use ($baseUrl) {
            if (empty($name)) return null;
            return preg_match('#^https?://#i', $name) ? $name : $baseUrl . ltrim($name, '/');
        };
        return [
            'id'            => (int) $f['id'],
            'name'          => $f['name'],
            'slug'          => $f['slug'],
            'tagline'       => $f['tagline'],
            'description'   => $f['description'],
            'theme_color'   => $f['theme_color'],
            'banner_image'  => $img($f['banner_image']),
            'mobile_image'  => $img($f['mobile_image']),
            'display_order' => (int) $f['display_order'],
            'is_active'     => (int) $f['is_active'],
            'product_count' => (int) $f['product_count'],
            'url'           => '/festival/' . $f['slug'],
        ];
    }, $rows);

    echo json_encode([
        'status' => 'success',
        'items'  => $items,
        'meta'   => ['total' => count($items), 'timestamp' => date('c')],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('[get_festivals.php] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to load festivals', 'items' => []]);
}
