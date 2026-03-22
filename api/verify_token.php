<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

function base64UrlDecode($data) {
    return base64_decode(strtr($data, '-_', '+/'));
}

function verifyJWT($jwt, $secret) {
    $parts = explode('.', $jwt);
    
    if (count($parts) !== 3) {
        return ["valid" => false, "message" => "Invalid token format"];
    }
    
    [$header, $payload, $signature] = $parts;
    
    // Verify signature
    $validSignature = hash_hmac('sha256', $header . "." . $payload, $secret, true);
    $validSignatureEncoded = rtrim(strtr(base64_encode($validSignature), '+/', '-_'), '=');
    
    if ($signature !== $validSignatureEncoded) {
        return ["valid" => false, "message" => "Invalid signature"];
    }
    
    // Decode payload
    $payloadData = json_decode(base64UrlDecode($payload), true);
    
    // Check expiration
    if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
        return ["valid" => false, "message" => "Token expired"];
    }
    
    return ["valid" => true, "payload" => $payloadData];
}

// Get token from Authorization header
$headers = getallheaders();
$token = null;

if (isset($headers['Authorization'])) {
    $authHeader = $headers['Authorization'];
    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = $matches[1];
    }
}

if (!$token) {
    echo json_encode(["status" => "error", "message" => "No token provided"]);
    exit();
}

$jwtSecret = "yyyyfSDSWFRWF34rGRS43##$3453gt";
$result = verifyJWT($token, $jwtSecret);

if ($result["valid"]) {
    echo json_encode([
        "status" => "success",
        "user" => $result["payload"]
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => $result["message"]
    ]);
}
?>