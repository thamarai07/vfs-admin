<?php
/**
 * Pincode Lookup + Delivery Serviceability
 * GET /api/pincode_lookup.php?pincode=560001     (no auth required)
 *
 * 1. Validates the pincode format (6 digits, India).
 * 2. Resolves city / state / area suggestions via the India Post API.
 * 3. Checks delivery serviceability + estimated delivery date.
 *
 * Serviceability policy:
 *   - If the `serviceable_pincodes` table has any active rows, it is treated as
 *     an allow-list (serviceable only when the pincode is listed).
 *   - If that table is empty (fresh install), every valid pincode is treated as
 *     serviceable with the default ETA below.
 */

require_once __DIR__ . '/../config/cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';

const DEFAULT_DELIVERY_DAYS = 4;

$pincode = trim($_GET['pincode'] ?? $_POST['pincode'] ?? '');
if ($pincode === '') {
    // Allow JSON body too
    $raw = file_get_contents('php://input');
    if ($raw) {
        $body    = json_decode($raw, true);
        $pincode = trim($body['pincode'] ?? '');
    }
}

if (!preg_match('/^[1-9][0-9]{5}$/', $pincode)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Enter a valid 6-digit pincode']);
    exit();
}

/**
 * Look up the pincode via the public India Post API.
 * Returns [city, state, areas[]] or null on failure.
 */
function lookupIndiaPost(string $pincode): ?array
{
    $url = "https://api.postalpincode.in/pincode/{$pincode}";

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_USERAGENT      => 'RootoAddressService/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($response === false || $code !== 200) {
            return null;
        }
    } else {
        $response = @file_get_contents($url);
        if ($response === false) {
            return null;
        }
    }

    $json = json_decode($response, true);
    if (!is_array($json) || empty($json[0]) || ($json[0]['Status'] ?? '') !== 'Success') {
        return null;
    }

    $offices = $json[0]['PostOffice'] ?? [];
    if (empty($offices)) {
        return null;
    }

    $areas = [];
    foreach ($offices as $o) {
        if (!empty($o['Name'])) {
            $areas[] = $o['Name'];
        }
    }

    return [
        'city'    => $offices[0]['District'] ?? '',
        'state'   => $offices[0]['State'] ?? '',
        'country' => $offices[0]['Country'] ?? 'India',
        'areas'   => array_values(array_unique($areas)),
    ];
}

try {
    // ---- Serviceability via DB allow-list -------------------------------
    $serviceable    = null;   // null = unknown yet
    $deliveryDays   = DEFAULT_DELIVERY_DAYS;
    $codAvailable   = true;
    $dbCity         = null;
    $dbState        = null;

    try {
        $activeCount = (int) $conn->query("SELECT COUNT(*) FROM serviceable_pincodes WHERE is_active = 1")->fetchColumn();

        if ($activeCount > 0) {
            $stmt = $conn->prepare(
                "SELECT city, state, cod_available, delivery_days
                 FROM serviceable_pincodes WHERE pincode = ? AND is_active = 1 LIMIT 1"
            );
            $stmt->execute([$pincode]);
            $svc = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($svc) {
                $serviceable  = true;
                $deliveryDays = (int) ($svc['delivery_days'] ?: DEFAULT_DELIVERY_DAYS);
                $codAvailable = (bool) (int) $svc['cod_available'];
                $dbCity       = $svc['city'] ?: null;
                $dbState      = $svc['state'] ?: null;
            } else {
                $serviceable = false;   // allow-list exists but pincode not in it
            }
        }
    } catch (PDOException $e) {
        // Table may not exist yet — fall back to "valid pincode = serviceable".
        error_log('pincode_lookup.php (serviceable_pincodes missing?): ' . $e->getMessage());
    }

    // ---- Resolve location names via India Post --------------------------
    $post = lookupIndiaPost($pincode);

    $city    = $dbCity  ?: ($post['city']  ?? '');
    $state   = $dbState ?: ($post['state'] ?? '');
    $country = $post['country'] ?? 'India';
    $areas   = $post['areas'] ?? [];

    // If India Post could not resolve it and it is not in the allow-list, reject.
    if ($post === null && $city === '') {
        echo json_encode([
            'success'     => false,
            'message'     => 'We could not verify this pincode. Please check and try again.',
            'pincode'     => $pincode,
            'serviceable' => false,
        ]);
        exit();
    }

    // No allow-list configured => any resolvable pincode is serviceable.
    if ($serviceable === null) {
        $serviceable = true;
    }

    // ---- Estimated delivery date ----------------------------------------
    $estimatedDate = null;
    $estimatedText = null;
    if ($serviceable) {
        $ts            = strtotime("+{$deliveryDays} days");
        $estimatedDate = date('Y-m-d', $ts);
        $estimatedText = date('D, d M', $ts);
    }

    echo json_encode([
        'success'                 => true,
        'pincode'                 => $pincode,
        'city'                    => $city,
        'state'                   => $state,
        'country'                 => $country,
        'areas'                   => $areas,
        'serviceable'             => $serviceable,
        'cod_available'           => $codAvailable,
        'delivery_days'           => $deliveryDays,
        'estimated_delivery_date' => $estimatedDate,
        'estimated_delivery_text' => $estimatedText,
        'message'                 => $serviceable
            ? "Delivery available • Arrives by {$estimatedText}"
            : 'Sorry, we do not deliver to this pincode yet.',
    ]);

} catch (Exception $e) {
    error_log('pincode_lookup.php Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not check this pincode. Please try again.']);
}
