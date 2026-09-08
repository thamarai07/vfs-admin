<?php
/**
 * Get Single Festival + its products  (NEW — isolated endpoint)
 * ---------------------------------------------------------------------------
 * GET /api/get_festival.php?slug=diwali
 *   -> { status:"success", festival:{...}, items:[ <product> ... ] }
 *
 * GET /api/get_festival.php?slug=diwali&preview=1
 *   -> also resolves inactive festivals (CMS "view" link).
 *
 * Bad / unknown / inactive slug  -> HTTP 404 { status:"error" }
 * Festival exists but has no products -> 200 { status:"success", items:[] }
 *
 * Each product row is SELF-CONTAINED and shaped like get-product.php's items so
 * the storefront's existing card/cart code can consume it directly, PLUS:
 *   - piece_price : number|null   (per-piece price when dual-unit)
 *   - units       : [{ unit, label, price, step, min, max, is_default }]
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

function festival_slugify(string $name): string {
    $s = strtolower(trim($name));
    $s = preg_replace('/[^a-z0-9\s-]/', '', $s);
    $s = preg_replace('/[\s-]+/', '-', $s);
    return trim($s, '-');
}

function festival_unit_label(string $unit, bool $plural = false): string {
    $map = [
        'kg'    => 'KG',    'gram'  => 'g',
        'piece' => $plural ? 'Pieces' : 'Piece',
        'pieces'=> $plural ? 'Pieces' : 'Piece',
        'dozen' => 'Dozen', 'bunch' => 'Bunch', 'pack' => 'Pack',
    ];
    return $map[$unit] ?? ucfirst($unit);
}

try {
    if (!isset($conn) || !($conn instanceof PDO)) {
        throw new RuntimeException('Database connection unavailable');
    }

    $slug    = trim($_GET['slug'] ?? '');
    $preview = isset($_GET['preview']) && $_GET['preview'] == '1';
    $baseUrl = rtrim(env('IMAGE_BASE_URL', ''), '/') . '/';

    if ($slug === '') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing festival slug']);
        exit;
    }

    // ── Festival ────────────────────────────────────────────────────────────
    $fStmt = $conn->prepare("
        SELECT id, name, slug, description, tagline, theme_color,
               banner_image, mobile_image, is_active, start_date, end_date
        FROM festivals
        WHERE slug = ?
        LIMIT 1
    ");
    $fStmt->execute([$slug]);
    $festival = $fStmt->fetch(PDO::FETCH_ASSOC);

    $withinWindow = $festival
        && (empty($festival['start_date']) || $festival['start_date'] <= date('Y-m-d'))
        && (empty($festival['end_date'])   || $festival['end_date']   >= date('Y-m-d'));

    if (!$festival || (!$preview && (!$festival['is_active'] || !$withinWindow))) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Festival not found']);
        exit;
    }

    $imgUrl = static function ($name) use ($baseUrl) {
        if (empty($name)) return null;
        return preg_match('#^https?://#i', $name) ? $name : $baseUrl . ltrim($name, '/');
    };

    $festivalOut = [
        'id'           => (int) $festival['id'],
        'name'         => $festival['name'],
        'slug'         => $festival['slug'],
        'description'  => $festival['description'],
        'tagline'      => $festival['tagline'],
        'theme_color'  => $festival['theme_color'],
        'banner_image' => $imgUrl($festival['banner_image']),
        'mobile_image' => $imgUrl($festival['mobile_image']),
        'is_active'    => (int) $festival['is_active'],
    ];

    // ── Products assigned to this festival ─────────────────────────────────
    $pStmt = $conn->prepare("
        SELECT p.id, p.name, p.slug, p.category,
               p.price, p.price_per_kg, p.piece_price, p.discount_percent,
               p.unit, p.min_quantity, p.max_quantity,
               p.image, p.description, p.stock, p.is_featured, p.is_active,
               fp.display_order AS fp_order
        FROM festival_products fp
        INNER JOIN products p ON p.id = fp.product_id
        WHERE fp.festival_id = ?
          AND fp.is_active = 1
          AND p.is_active = 1
        ORDER BY fp.display_order ASC, p.name ASC
    ");
    $pStmt->execute([$festivalOut['id']]);
    $rawProducts = $pStmt->fetchAll(PDO::FETCH_ASSOC);

    $items = array_map(static function (array $p) use ($imgUrl): array {
        // Image
        if (!empty($p['image'])) {
            $first = trim(explode(',', $p['image'])[0]);
            $p['image'] = preg_match('#^https?://#i', $first) ? $first : $imgUrl($first);
        } else {
            $p['image'] = 'https://placehold.co/300x300/e5e7eb/6b7280?text=No+Image';
        }
        $p['slug'] = empty($p['slug']) ? festival_slugify($p['name']) : strtolower(trim($p['slug']));

        // Numeric casts
        $p['id']               = (int) $p['id'];
        $p['price']            = (float) $p['price'];
        $p['price_per_kg']     = (float) $p['price_per_kg'];
        $p['piece_price']      = ($p['piece_price'] === null || $p['piece_price'] === '')
                                    ? null : (float) $p['piece_price'];
        $p['discount_percent'] = (float) ($p['discount_percent'] ?? 0);
        $p['min_quantity']     = (float) ($p['min_quantity'] ?? 0.25);
        $p['max_quantity']     = (float) ($p['max_quantity'] ?? 0);
        $p['stock']            = (int) $p['stock'];
        $p['is_featured']      = (int) ($p['is_featured'] ?? 0);
        $p['is_active']        = (int) ($p['is_active'] ?? 1);
        $primaryUnit           = $p['unit'] ?: 'kg';
        $p['unit']             = $primaryUnit;

        // Stock helpers (same names get-product.php uses)
        $p['in_stock']     = $p['stock'] > 0;
        $p['low_stock']    = $p['stock'] > 0 && $p['stock'] < 20;
        $p['out_of_stock'] = $p['stock'] <= 0;
        $p['thumbnail']    = $p['image'];
        $p['url']          = '/product/' . rawurlencode($p['slug']);

        // Discounted price for the KG/primary unit
        $applyDisc = static fn($base) => round($base * (1 - $p['discount_percent'] / 100), 2);

        // ── Build the units[] the festival card renders from ──────────────
        $units   = [];
        $isPieceLike = in_array($primaryUnit, ['piece', 'pieces', 'dozen', 'bunch', 'pack'], true);

        // KG row: present when the product's own unit is weight-based
        if (!$isPieceLike) {
            $units[] = [
                'unit'       => 'kg',
                'label'      => 'KG',
                'price'      => $applyDisc($p['price_per_kg']),
                'base_price' => $p['price_per_kg'],
                'step'       => 0.25,
                'min'        => $p['min_quantity'] > 0 ? $p['min_quantity'] : 0.25,
                'max'        => $p['max_quantity'] > 0 ? $p['max_quantity'] : null,
                'is_default' => true,
            ];
        }

        // Piece row:
        //  - product.unit is itself piece-like  -> single piece unit priced from price_per_kg
        //  - OR product.piece_price is set      -> dual unit (kg + piece)
        if ($isPieceLike) {
            $units[] = [
                'unit'       => 'piece',
                'label'      => festival_unit_label($primaryUnit),
                'price'      => $applyDisc($p['piece_price'] ?? $p['price_per_kg']),
                'base_price' => $p['piece_price'] ?? $p['price_per_kg'],
                'step'       => 1,
                'min'        => 1,
                'max'        => $p['stock'] > 0 ? $p['stock'] : null,
                'is_default' => true,
            ];
        } elseif ($p['piece_price'] !== null && $p['piece_price'] > 0) {
            $units[] = [
                'unit'       => 'piece',
                'label'      => 'Piece',
                'price'      => $applyDisc($p['piece_price']),
                'base_price' => $p['piece_price'],
                'step'       => 1,
                'min'        => 1,
                'max'        => $p['stock'] > 0 ? $p['stock'] : null,
                'is_default' => false,
            ];
        }

        $p['units'] = $units;
        // final_price = default unit price (keeps parity with get-product.php)
        $defaultUnit = $units[0] ?? null;
        $p['final_price'] = $defaultUnit ? $defaultUnit['price'] : $applyDisc($p['price_per_kg']);
        $p['has_discount'] = $p['discount_percent'] > 0;

        unset($p['fp_order']);
        return $p;
    }, $rawProducts);

    // Drop products that ended up with no usable unit/price
    $items = array_values(array_filter($items, static fn($p) => !empty($p['units'])));

    echo json_encode([
        'status'   => 'success',
        'festival' => $festivalOut,
        'items'    => $items,
        'meta'     => ['total' => count($items), 'timestamp' => date('c')],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('[get_festival.php] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to load festival']);
}
