<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../config/rate_limit.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['login'], $data['password'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

$login      = trim($data['login']);
$password   = $data['password'];
$rememberMe = !empty($data['remember_me']); // NEW

// Brute-force protection: 5 wrong passwords per account+IP, and 30 per IP, in 15 minutes.
$rlAccount = 'customer:' . clientIp() . ':' . strtolower($login);
$rlIp      = 'customer-ip:' . clientIp();
$wait = max(rateLimitBlocked($rlAccount), rateLimitBlocked($rlIp, 30));
if ($wait > 0) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Too many attempts. Please try again in ' . (int) ceil($wait / 60) . ' minute(s).']);
    exit;
}

$query = filter_var($login, FILTER_VALIDATE_EMAIL)
    ? "SELECT id, name, email, phone, password_hash FROM customers WHERE email = :login"
    : "SELECT id, name, email, phone, password_hash FROM customers WHERE phone = :login";

try {
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':login', $login);
    $stmt->execute();
    // Older data has a few duplicate phone numbers, so check every match.
    $user = null;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $candidate) {
        if (password_verify($password, $candidate['password_hash'])) {
            $user = $candidate;
            break;
        }
    }

    if (!$user) {
        rateLimitFail($rlAccount);
        rateLimitFail($rlIp);
        echo json_encode(['status' => 'error', 'message' => 'Invalid email/mobile or password']);
        exit;
    }

    rateLimitReset($rlAccount);
    $payload   = [
        'user_id' => $user['id'],
        'email'   => $user['email'],
        'name'    => $user['name'],
        'iat'     => time(),
        'exp'     => time() + (7 * 24 * 60 * 60),
    ];
    $jwt = jwtSign($payload);

    $response = [
        'status' => 'success',
        'token'  => $jwt,
        'user'   => [
            'id'    => (int)$user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
        ],
        'captcha_score' => null,
    ];

    // ── Remember Me ──────────────────────────────────────
    if ($rememberMe) {
        $remToken  = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

        // Remove old tokens for this user (one active token per user)
        $del = $conn->prepare("DELETE FROM remember_me_tokens WHERE customer_id = :id");
        $del->execute([':id' => $user['id']]);

        $ins = $conn->prepare("
            INSERT INTO remember_me_tokens (customer_id, token, expires_at)
            VALUES (:customer_id, :token, :expires_at)
        ");
        $ins->execute([
            ':customer_id' => $user['id'],
            ':token'       => $remToken,
            ':expires_at'  => $expiresAt,
        ]);

        // Return token in response (frontend stores in localStorage/cookie)
        $response['remember_me_token'] = $remToken;
    }

    echo json_encode($response);

} catch (Throwable $e) {
    error_log("Login API DB Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'An error occurred. Please try again.']);
}

$conn = null;