<?php
/**
 * Delete Account
 * POST /api/delete_account.php   (Bearer token required)
 * Body: { "password": "<current password>" }
 *
 * Required by the App Store and Google Play: a customer must be able to delete
 * their account from inside the app.
 *
 * What is removed: the customer row, saved addresses, cart, wishlist,
 * remember-me tokens, password-reset tokens and reviews written with their email.
 *
 * What is kept: past orders, because tax law requires invoices to be kept.
 * They are detached from the account (customer_id = NULL), so they no longer
 * belong to any login. Name / phone / address on the order stay as the
 * delivery record of that invoice.
 */

require_once __DIR__ . '/../config/cors.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../config/rate_limit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$authUser   = requireAuth();
$customerId = (int) $authUser['user_id'];
$data       = json_decode(file_get_contents('php://input'), true) ?: [];
$password   = (string) ($data['password'] ?? '');

$rlKey = 'delete:' . $customerId;
if ($wait = rateLimitBlocked($rlKey)) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Too many attempts. Please try again in ' . (int) ceil($wait / 60) . ' minute(s).']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT id, email, password_hash FROM customers WHERE id = ?");
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Account not found']);
        exit;
    }

    // Confirm it is really the owner (a stolen, still-valid token is not enough).
    if ($password === '' || !password_verify($password, $customer['password_hash'])) {
        rateLimitFail($rlKey);
        echo json_encode(['status' => 'error', 'message' => 'That password is not correct']);
        exit;
    }

    $conn->beginTransaction();

    $conn->prepare("DELETE FROM customer_addresses WHERE customer_id = ?")->execute([$customerId]);
    $conn->prepare("DELETE FROM cart WHERE session_id = ?")->execute(['user_' . $customerId]);
    $conn->prepare("DELETE FROM favorites WHERE user_id = ?")->execute([$customerId]);
    $conn->prepare("DELETE FROM remember_me_tokens WHERE customer_id = ?")->execute([$customerId]);
    $conn->prepare("DELETE FROM password_resets WHERE customer_id = ?")->execute([$customerId]);
    if (!empty($customer['email'])) {
        try {
            $conn->prepare("DELETE FROM product_reviews WHERE customer_email = ?")->execute([$customer['email']]);
        } catch (PDOException $e) {
            error_log('delete_account.php reviews skipped: ' . $e->getMessage());
        }
    }
    $conn->prepare("UPDATE orders SET customer_id = NULL WHERE customer_id = ?")->execute([$customerId]);
    $conn->prepare("DELETE FROM customers WHERE id = ?")->execute([$customerId]);

    $conn->commit();
    rateLimitReset($rlKey);

    echo json_encode(['status' => 'success', 'message' => 'Your account has been deleted']);
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('delete_account.php Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'We could not delete your account. Please try again or contact support.']);
}
