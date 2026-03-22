<?php
/**
 * Invoice Download - HTML Invoice Generator
 * Fixed: removed raw die() error, fixed $order['total'] -> $order['total_amount'], store info from .env
 */

require_once __DIR__ . '/../config/db.php';

$order_id    = isset($_GET['order_id'])    ? (int) $_GET['order_id']    : null;
$customer_id = isset($_GET['customer_id']) ? (int) $_GET['customer_id'] : null;

if (!$order_id || !$customer_id) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT o.*
        FROM orders o
        WHERE o.id = :order_id AND o.customer_id = :customer_id
    ");
    $stmt->bindParam(':order_id',    $order_id);
    $stmt->bindParam(':customer_id', $customer_id);
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }

    // Fetch order items
    $itemsStmt = $conn->prepare("
        SELECT oi.*, p.name as product_name
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = :order_id
    ");
    $itemsStmt->bindParam(':order_id', $order_id);
    $itemsStmt->execute();
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $html = generateInvoiceHTML($order, $items);

    header('Content-Type: text/html');
    header('Content-Disposition: inline; filename="invoice_' . $order['order_number'] . '.html"');
    echo $html;

} catch (PDOException $e) {
    error_log("download_invoice.php DB Error: " . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}

function generateInvoiceHTML(array $order, array $items): string {
    // Parse customer address from JSON
    $address = json_decode($order['customer_address'], true);
    if (!$address) {
        $address = [
            'name'        => $order['customer_name'] ?? 'Customer',
            'phone'       => $order['customer_phone'] ?? '',
            'fullAddress' => $order['customer_address']
        ];
    }

    // Store information from .env
    $storeInfo = [
        'name'    => env('STORE_NAME',    'VFS Fresh'),
        'address' => env('STORE_ADDRESS', 'Your Store Address, City, State, PIN'),
        'phone'   => env('STORE_PHONE',   '+91 1234567890'),
        'email'   => env('STORE_EMAIL',   'contact@vfsfresh.com'),
        'gst'     => env('STORE_GST',     'GST123456789'),
        'website' => env('STORE_WEBSITE', 'www.vfsfresh.com'),
    ];

    // Use total_amount (fixed from $order['total'] which was wrong column name)
    $orderTotal = $order['total_amount'] ?? $order['total'] ?? 0;

    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Invoice - <?= htmlspecialchars($order['order_number']) ?></title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                padding: 40px;
                color: #333;
                background: #f9fafb;
            }
            .invoice-container {
                max-width: 900px;
                margin: 0 auto;
                background: white;
                padding: 40px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                border-radius: 8px;
            }
            .header {
                text-align: center;
                margin-bottom: 40px;
                border-bottom: 4px solid #16a34a;
                padding-bottom: 20px;
            }
            .header h1 { color: #16a34a; margin-bottom: 10px; font-size: 36px; font-weight: bold; }
            .header p  { margin: 5px 0; color: #666; font-size: 14px; }
            .invoice-title {
                background: #16a34a; color: white; padding: 15px;
                text-align: center; font-size: 24px; font-weight: bold;
                margin: 20px 0; border-radius: 4px;
            }
            .invoice-info { display: flex; justify-content: space-between; margin-bottom: 30px; gap: 30px; }
            .info-box {
                flex: 1; background: #f9fafb; padding: 20px;
                border-radius: 8px; border: 1px solid #e5e7eb;
            }
            .info-box h3 { color: #16a34a; border-bottom: 2px solid #16a34a; padding-bottom: 8px; margin-bottom: 15px; font-size: 16px; }
            .info-box p  { margin: 8px 0; line-height: 1.6; font-size: 14px; }
            .info-box strong { color: #111827; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 30px; background: white; }
            thead { background: #16a34a; }
            th { color: white; padding: 15px 12px; text-align: left; font-weight: 600; text-transform: uppercase; font-size: 13px; letter-spacing: 0.5px; }
            td { padding: 15px 12px; border-bottom: 1px solid #e5e7eb; font-size: 14px; }
            tbody tr:hover { background: #f9fafb; }
            .text-right  { text-align: right; }
            .text-center { text-align: center; }
            .totals { margin-left: auto; width: 350px; background: #f9fafb; padding: 20px; border-radius: 8px; border: 2px solid #e5e7eb; }
            .totals table { margin: 0; background: transparent; }
            .totals table td { padding: 10px 0; border: none; font-size: 15px; }
            .total-row { font-weight: bold; font-size: 20px !important; background: #dcfce7; padding: 15px !important; color: #166534; border-top: 2px solid #16a34a !important; }
            .notes-box { margin-top: 30px; padding: 20px; background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 4px; }
            .notes-box strong { color: #92400e; display: block; margin-bottom: 8px; }
            .footer { text-align: center; margin-top: 50px; padding-top: 30px; border-top: 3px solid #e5e7eb; color: #666; }
            .footer p { margin: 10px 0; }
            .footer .thank-you { font-size: 20px; font-weight: bold; color: #16a34a; margin-bottom: 15px; }
            .print-btn {
                position: fixed; top: 20px; right: 20px;
                background: #16a34a; color: white; border: none;
                padding: 12px 24px; border-radius: 6px; cursor: pointer;
                font-size: 14px; font-weight: 600; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                transition: all 0.3s;
            }
            .print-btn:hover { background: #15803d; transform: translateY(-2px); }
            @media print {
                body { margin: 0; padding: 20px; background: white; }
                .invoice-container { box-shadow: none; padding: 0; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <button onclick="window.print()" class="print-btn no-print">🖨️ Print Invoice</button>

        <div class="invoice-container">
            <!-- Header -->
            <div class="header">
                <h1>🌿 <?= htmlspecialchars($storeInfo['name']) ?></h1>
                <p><?= htmlspecialchars($storeInfo['address']) ?></p>
                <p>📞 <?= htmlspecialchars($storeInfo['phone']) ?> | 📧 <?= htmlspecialchars($storeInfo['email']) ?></p>
                <p>🏢 GST: <?= htmlspecialchars($storeInfo['gst']) ?> | 🌐 <?= htmlspecialchars($storeInfo['website']) ?></p>
            </div>

            <div class="invoice-title">TAX INVOICE</div>

            <!-- Invoice Info -->
            <div class="invoice-info">
                <div class="info-box">
                    <h3>📋 Invoice Details</h3>
                    <p><strong>Invoice Number:</strong> <?= htmlspecialchars($order['order_number']) ?></p>
                    <p><strong>Order Date:</strong> <?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></p>
                    <p><strong>Order Status:</strong> <span style="color: #16a34a; font-weight: 600;"><?= strtoupper($order['status']) ?></span></p>
                    <p><strong>Payment Method:</strong> <?= strtoupper($order['payment_method']) ?></p>
                    <p><strong>Payment Status:</strong>
                        <span style="color: <?= $order['payment_status'] === 'paid' ? '#16a34a' : '#f59e0b' ?>; font-weight: 600;">
                            <?= strtoupper($order['payment_status']) ?>
                        </span>
                    </p>
                </div>

                <div class="info-box">
                    <h3>📦 Billing &amp; Shipping To</h3>
                    <p><strong><?= htmlspecialchars($address['name'] ?? '') ?></strong></p>
                    <p><?= htmlspecialchars($address['fullAddress'] ?? '') ?></p>
                    <?php if (!empty($address['landmark'])): ?>
                        <p>Landmark: <?= htmlspecialchars($address['landmark']) ?></p>
                    <?php endif; ?>
                    <p>📱 Phone: <?= htmlspecialchars($address['phone'] ?? $address['phoneNumber'] ?? '') ?></p>
                    <?php if (!empty($address['email'])): ?>
                        <p>📧 Email: <?= htmlspecialchars($address['email']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Order Items -->
            <table>
                <thead>
                    <tr>
                        <th class="text-center" style="width:50px">#</th>
                        <th>Product Name</th>
                        <th class="text-right" style="width:120px">Unit Price</th>
                        <th class="text-right" style="width:100px">Quantity</th>
                        <th class="text-right" style="width:120px">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $index => $item): ?>
                        <tr>
                            <td class="text-center"><?= $index + 1 ?></td>
                            <td><strong><?= htmlspecialchars($item['product_name'] ?? $item['name'] ?? '') ?></strong></td>
                            <td class="text-right">₹<?= number_format((float)($item['price'] ?? $item['price_per_kg'] ?? 0), 2) ?></td>
                            <td class="text-right"><?= $item['quantity'] ?> kg</td>
                            <td class="text-right"><strong>₹<?= number_format((float)$item['subtotal'], 2) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Totals -->
            <div class="totals">
                <table>
                    <tr>
                        <td>Subtotal:</td>
                        <td class="text-right">₹<?= number_format((float)($order['subtotal'] ?? 0), 2) ?></td>
                    </tr>
                    <tr>
                        <td>Tax (8%):</td>
                        <td class="text-right">₹<?= number_format((float)($order['tax'] ?? 0), 2) ?></td>
                    </tr>
                    <tr>
                        <td>Shipping Charge:</td>
                        <td class="text-right" style="color: <?= ($order['shipping_charge'] ?? 0) > 0 ? '#111' : '#16a34a' ?>;">
                            <?= ($order['shipping_charge'] ?? 0) > 0 ? '₹' . number_format((float)$order['shipping_charge'], 2) : 'FREE' ?>
                        </td>
                    </tr>
                    <?php if (!empty($order['discount']) && $order['discount'] > 0): ?>
                        <tr>
                            <td>Discount:</td>
                            <td class="text-right" style="color:#dc2626;">-₹<?= number_format((float)$order['discount'], 2) ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr class="total-row">
                        <td><strong>TOTAL AMOUNT:</strong></td>
                        <td class="text-right"><strong>₹<?= number_format((float)$orderTotal, 2) ?></strong></td>
                    </tr>
                </table>
            </div>

            <?php if (!empty($order['notes'])): ?>
                <div class="notes-box">
                    <strong>📝 Special Instructions:</strong>
                    <p><?= htmlspecialchars($order['notes']) ?></p>
                </div>
            <?php endif; ?>

            <!-- Terms -->
            <div style="margin-top:40px;padding:20px;background:#f3f4f6;border-radius:8px;border:1px solid #e5e7eb;">
                <h4 style="color:#374151;margin-bottom:10px;">Terms &amp; Conditions:</h4>
                <ul style="padding-left:20px;color:#6b7280;font-size:13px;line-height:1.8;">
                    <li>All products are subject to availability</li>
                    <li>Goods once sold cannot be returned or exchanged</li>
                    <li>Prices are inclusive of all taxes unless specified</li>
                    <li>Delivery charges may vary based on location</li>
                </ul>
            </div>

            <!-- Footer -->
            <div class="footer">
                <p class="thank-you">✨ Thank You for Your Order! ✨</p>
                <p>We appreciate your business and look forward to serving you again.</p>
                <p style="margin-top:15px;">For any queries or support:</p>
                <p>📞 Call us at <strong><?= htmlspecialchars($storeInfo['phone']) ?></strong></p>
                <p>📧 Email us at <strong><?= htmlspecialchars($storeInfo['email']) ?></strong></p>
                <p style="font-size:12px;margin-top:25px;color:#9ca3af;">
                    This is a computer-generated invoice and does not require a physical signature.
                </p>
                <p style="font-size:11px;color:#9ca3af;margin-top:10px;">
                    Generated on <?= date('d M Y, h:i A') ?>
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}