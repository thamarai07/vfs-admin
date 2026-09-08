<?php
/**
 * JWT Verification Helper
 * ─────────────────────────────────────────────────────────────────────────
 * Include this in any API that requires an authenticated user.
 * Usage:
 *   require_once __DIR__ . '/../config/jwt.php';
 *   $user = requireAuth();   // dies with 401 JSON if token is invalid/missing
 *   $userId = $user['user_id'];
 */

require_once __DIR__ . '/env.php';

function base64UrlDecode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}

/**
 * Verify a JWT and return its payload array.
 * Returns false on failure.
 */
function verifyJWT(string $token): array|false {
    $secret = env('JWT_SECRET', '');
    if (empty($secret)) {
        error_log('[jwt.php] JWT_SECRET is not set in environment');
        return false;
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return false;
    }

    [$b64Head, $b64Pay, $b64Sig] = $parts;

    // Verify signature
    $expectedSig = hash_hmac('sha256', "$b64Head.$b64Pay", $secret, true);
    $expectedB64 = rtrim(strtr(base64_encode($expectedSig), '+/', '-_'), '=');

    if (!hash_equals($expectedB64, $b64Sig)) {
        return false;
    }

    // Decode payload
    $payload = json_decode(base64UrlDecode($b64Pay), true);
    if (!is_array($payload)) {
        return false;
    }

    // Check expiry
    if (isset($payload['exp']) && time() > $payload['exp']) {
        return false;
    }

    return $payload;
}

/**
 * Extract the Bearer token from the Authorization header.
 */
function getBearerToken(): ?string {
    $headers = null;

    if (isset($_SERVER['Authorization'])) {
        $headers = $_SERVER['Authorization'];
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        if (isset($requestHeaders['Authorization'])) {
            $headers = $requestHeaders['Authorization'];
        }
    }

    if ($headers && preg_match('/Bearer\s+(.+)$/i', $headers, $m)) {
        return trim($m[1]);
    }

    if (isset($_GET['token'])) {
        return $_GET['token'];
    }

    return null;
}

/**
 * Require a valid JWT. Sends a 401 JSON response and exits on failure.
 * Returns the decoded payload (including user_id, email, name) on success.
 */
function requireAuth(): array {
    $token = getBearerToken();

    if (!$token) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Authentication required. Please log in.']);
        exit;
    }

    $payload = verifyJWT($token);

    if (!$payload) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired token. Please log in again.']);
        exit;
    }

    return $payload;
}
