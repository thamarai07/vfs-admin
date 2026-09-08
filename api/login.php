<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

function base64UrlEncode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function generateJWT(array $payload, string $secret): string {
    $header  = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $b64Head = base64UrlEncode($header);
    $b64Pay  = base64UrlEncode(json_encode($payload));
    $sig     = hash_hmac('sha256', "$b64Head.$b64Pay", $secret, true);
    return "$b64Head.$b64Pay." . base64UrlEncode($sig);
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['login'], $data['password'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

$login      = trim($data['login']);
$password   = $data['password'];
$rememberMe = !empty($data['remember_me']); // NEW

$query = filter_var($login, FILTER_VALIDATE_EMAIL)
    ? "SELECT id, name, email, phone, password_hash FROM customers WHERE email = :login"
    : "SELECT id, name, email, phone, password_hash FROM customers WHERE phone = :login";

try {
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':login', $login);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email/mobile or password']);
        exit;
    }

    $jwtSecret = $_ENV['JWT_SECRET'] ?? 'change-this-secret-in-env';
    $payload   = [
        'user_id' => $user['id'],
        'email'   => $user['email'],
        'name'    => $user['name'],
        'iat'     => time(),
        'exp'     => time() + (7 * 24 * 60 * 60),
    ];
    $jwt = generateJWT($payload, $jwtSecret);

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

} catch (PDOException $e) {
    error_log("Login API DB Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'An error occurred. Please try again.']);
}

$conn = null;