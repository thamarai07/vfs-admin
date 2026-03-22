<?php
require_once __DIR__ . '/../config/cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';

// ----------------------------------------------------------------
// reCAPTCHA Enterprise Verification
// ----------------------------------------------------------------
function verifyRecaptcha(string $token, string $expectedAction): array {
    $projectId = env('RECAPTCHA_PROJECT_ID', 'vasugi-fruit-shop');
    $apiKey    = env('RECAPTCHA_API_KEY', '');
    $siteKey   = env('RECAPTCHA_SITE_KEY', '');

    $url = "https://recaptchaenterprise.googleapis.com/v1/projects/$projectId/assessments?key=$apiKey";

    $payload = json_encode([
        "event" => [
            "token"          => $token,
            "siteKey"        => $siteKey,
            "expectedAction" => $expectedAction
        ]
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["Content-Type: application/json"],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$response) {
        return ["success" => false, "message" => "Failed to contact reCAPTCHA server"];
    }

    $data = json_decode($response, true);

    if (isset($data['error'])) {
        return ["success" => false, "message" => "reCAPTCHA API error"];
    }

    if (isset($data["tokenProperties"]["valid"]) && !$data["tokenProperties"]["valid"]) {
        return ["success" => false, "message" => "Invalid token: " . ($data["tokenProperties"]["invalidReason"] ?? "Unknown reason")];
    }

    $actualAction = $data["tokenProperties"]["action"] ?? "";
    if (strcasecmp(trim($actualAction), trim($expectedAction)) !== 0) {
        return ["success" => false, "message" => "Action mismatch"];
    }

    $score = $data["riskAnalysis"]["score"] ?? 0;
    if ($score < 0.5) {
        return ["success" => false, "message" => "Suspicious behavior detected", "score" => $score];
    }

    return ["success" => true, "score" => $score];
}

// ----------------------------------------------------------------
// Read Input
// ----------------------------------------------------------------
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['name'], $data['email'], $data['phone'], $data['password'], $data['captcha_token'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit();
}

// Verify Captcha
$captcha = verifyRecaptcha($data['captcha_token'], "signup");
if (!$captcha["success"]) {
    echo json_encode(["status" => "error", "message" => $captcha["message"]]);
    exit();
}

// Sanitize inputs
$name     = trim($data['name']);
$email    = trim($data['email']);
$phone    = trim($data['phone']);
$password = $data['password'];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid email']);
    exit();
}

if (!preg_match('/^\d{10,15}$/', $phone)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid phone']);
    exit();
}

if (strlen($password) < 8) {
    echo json_encode(['status' => 'error', 'message' => 'Password too short']);
    exit();
}

$password_hash = password_hash($password, PASSWORD_BCRYPT);

try {
    $stmt = $conn->prepare("
        INSERT INTO customers (name, email, phone, password_hash)
        VALUES (:name, :email, :phone, :password_hash)
    ");
    $stmt->bindParam(':name',          $name);
    $stmt->bindParam(':email',         $email);
    $stmt->bindParam(':phone',         $phone);
    $stmt->bindParam(':password_hash', $password_hash);

    if ($stmt->execute()) {
        echo json_encode([
            "status"        => "success",
            "user"          => [
                "id"    => $conn->lastInsertId(),
                "name"  => $name,
                "email" => $email,
                "phone" => $phone
            ],
            "captcha_score" => $captcha["score"]
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Registration failed. Please try again."]);
    }
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        echo json_encode(["status" => "error", "message" => "Email or phone already exists"]);
    } else {
        error_log("Register API DB Error: " . $e->getMessage());
        echo json_encode(["status" => "error", "message" => "An error occurred. Please try again."]);
    }
}

$conn = null;