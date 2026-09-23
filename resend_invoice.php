<?php
/**
 * Manually (re)send the order-confirmation invoice email from the admin CMS.
 * Called via fetch() from order_view.php's "Resend Invoice Email" button.
 *
 * POST order_id  →  JSON { status: success|error, message }
 *
 * On success, stamps orders.invoice_emailed_at = NOW() — same column the
 * automatic send (api/create_order.php) writes, so the badge/timestamp shown
 * on order_view.php / orders.php just reflects whichever attempt last worked.
 */
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/invoice_email.php';

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$orderId = (int) ($_POST['order_id'] ?? 0);
if ($orderId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid order id']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['status' => 'error', 'message' => 'Order not found']);
        exit;
    }

    if (empty($order['customer_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'This order has no linked customer account']);
        exit;
    }

    $custStmt = $conn->prepare("SELECT email, name FROM customers WHERE id = ?");
    $custStmt->execute([$order['customer_id']]);
    $cust = $custStmt->fetch(PDO::FETCH_ASSOC);

    if (!$cust || empty($cust['email'])) {
        echo json_encode(['status' => 'error', 'message' => 'No email address on file for this customer']);
        exit;
    }

    $itemsStmt = $conn->prepare("SELECT product_name, unit, quantity, price, subtotal FROM order_items WHERE order_id = ?");
    $itemsStmt->execute([$orderId]);
    $rows = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        echo json_encode(['status' => 'error', 'message' => 'This order has no items to invoice']);
        exit;
    }

    $invoiceItems = array_map(static function (array $r): array {
        return [
            'name'     => $r['product_name'],
            'unit'     => $r['unit'] ?? null,
            'quantity' => (float) $r['quantity'],
            'price'    => (float) $r['price'],
            'subtotal' => (float) $r['subtotal'],
        ];
    }, $rows);

    $adminEmail = $_ENV['ADMIN_EMAIL'] ?? getenv('ADMIN_EMAIL') ?: '';
    $bcc = ($adminEmail !== '' && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) ? [$adminEmail] : [];

    $sent = sendOrderInvoiceEmail(
        $cust['email'],
        $cust['name'] ?? ($order['customer_name'] ?? 'Customer'),
        [
            'order_number'     => $order['order_number'],
            'created_at'       => $order['created_at'],
            'customer_name'    => $order['customer_name'],
            'customer_phone'   => $order['customer_phone'],
            'customer_address' => $order['customer_address'],
            'subtotal'         => (float) $order['subtotal'],
            'tax'              => (float) $order['tax'],
            'shipping_charge'  => (float) $order['shipping_charge'],
            'total_amount'     => (float) ($order['total_amount'] ?: $order['total']),
            'payment_method'   => $order['payment_method'],
            'notes'            => $order['notes'],
        ],
        $invoiceItems,
        $bcc
    );

    if (!$sent) {
        echo json_encode(['status' => 'error', 'message' => 'Mail server rejected the email — check the mail settings/logs']);
        exit;
    }

    // Additive, nullable column — silently skipped if the migration hasn't run.
    try {
        $conn->prepare("UPDATE orders SET invoice_emailed_at = NOW() WHERE id = ?")->execute([$orderId]);
    } catch (\Throwable $e) {
        error_log('[resend_invoice] invoice_emailed_at update skipped: ' . $e->getMessage());
    }

    echo json_encode(['status' => 'success', 'message' => 'Invoice email sent to ' . $cust['email']]);

} catch (\Throwable $e) {
    error_log('[resend_invoice] ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Server error while sending the email']);
}
