<?php
session_start();
require_once "config/db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$id = (int)($_GET['id'] ?? 0);

// Fetch order details
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: orders.php");
    exit;
}

// Fetch order items
$itemsStmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?= htmlspecialchars($order['order_number']) ?> - VFS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen p-8">
        <div class="max-w-5xl mx-auto">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Order Details</h1>
                    <p class="text-gray-600">Order #<?= htmlspecialchars($order['order_number']) ?></p>
                </div>
                <div class="flex gap-3">
                    <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                        <i class="fas fa-print mr-2"></i>Print
                    </button>
                    <a href="order_edit.php?id=<?= $id ?>" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                        <i class="fas fa-edit mr-2"></i>Edit
                    </a>
                    <a href="orders.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">
                        <i class="fas fa-arrow-left mr-2"></i>Back
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Customer Info -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Customer Details -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                            <i class="fas fa-user text-blue-600"></i>
                            Customer Information
                        </h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-500">Name</p>
                                <p class="font-semibold text-gray-900"><?= htmlspecialchars($order['customer_name']) ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Phone</p>
                                <p class="font-semibold text-gray-900"><?= htmlspecialchars($order['customer_phone']) ?></p>
                            </div>
                            <div class="col-span-2">
                                <p class="text-sm text-gray-500">Address</p>
                                <p class="font-semibold text-gray-900"><?= htmlspecialchars($order['customer_address'] ?? 'N/A') ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Order Items -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                            <i class="fas fa-shopping-basket text-green-600"></i>
                            Order Items
                        </h3>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50 border-b">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Product</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Qty</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Price</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900"><?= htmlspecialchars($item['product_name']) ?></td>
                                        <td class="px-4 py-3 text-right"><?= number_format($item['quantity'], 2) ?> kg</td>
                                        <td class="px-4 py-3 text-right">₹<?= number_format($item['price'], 2) ?></td>
                                        <td class="px-4 py-3 text-right font-semibold">₹<?= number_format($item['subtotal'], 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="bg-gray-50 border-t-2">
                                    <tr>
                                        <td colspan="3" class="px-4 py-3 text-right font-semibold">Subtotal:</td>
                                        <td class="px-4 py-3 text-right font-semibold">₹<?= number_format($order['total'] - $order['delivery_charge'] + $order['discount'], 2) ?></td>
                                    </tr>
                                    <?php if ($order['discount'] > 0): ?>
                                    <tr>
                                        <td colspan="3" class="px-4 py-2 text-right text-green-600">Discount:</td>
                                        <td class="px-4 py-2 text-right text-green-600">- ₹<?= number_format($order['discount'], 2) ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td colspan="3" class="px-4 py-2 text-right">Delivery Charge:</td>
                                        <td class="px-4 py-2 text-right">₹<?= number_format($order['delivery_charge'], 2) ?></td>
                                    </tr>
                                    <tr class="bg-green-50">
                                        <td colspan="3" class="px-4 py-3 text-right text-lg font-bold">Total:</td>
                                        <td class="px-4 py-3 text-right text-lg font-bold text-green-600">₹<?= number_format($order['total'], 2) ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Notes -->
                    <?php if ($order['notes']): ?>
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                            <i class="fas fa-sticky-note text-yellow-600"></i>
                            Notes
                        </h3>
                        <p class="text-gray-700"><?= nl2br(htmlspecialchars($order['notes'])) ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Status Card -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">Order Status</h3>
                        <div class="space-y-3">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Order Status</p>
                                <span class="px-3 py-1 rounded-full text-sm font-semibold <?php
                                    $colors = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'confirmed' => 'bg-blue-100 text-blue-800',
                                        'processing' => 'bg-purple-100 text-purple-800',
                                        'delivered' => 'bg-green-100 text-green-800',
                                        'cancelled' => 'bg-red-100 text-red-800'
                                    ];
                                    echo $colors[$order['status']] ?? 'bg-gray-100 text-gray-800';
                                ?>">
                                    <?= ucfirst($order['status']) ?>
                                </span>
                            </div>

                            <div>
                                <p class="text-sm text-gray-500 mb-1">Payment Status</p>
                                <span class="px-3 py-1 rounded-full text-sm font-semibold <?php
                                    $payColors = [
                                        'unpaid' => 'bg-red-100 text-red-800',
                                        'paid' => 'bg-green-100 text-green-800',
                                        'partial' => 'bg-orange-100 text-orange-800'
                                    ];
                                    echo $payColors[$order['payment_status']] ?? 'bg-gray-100 text-gray-800';
                                ?>">
                                    <?= ucfirst($order['payment_status']) ?>
                                </span>
                            </div>

                            <div>
                                <p class="text-sm text-gray-500 mb-1">Payment Method</p>
                                <p class="font-semibold capitalize"><?= htmlspecialchars($order['payment_method']) ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Timeline -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">Timeline</h3>
                        <div class="space-y-3">
                            <div class="flex items-start gap-3">
                                <i class="fas fa-calendar-plus text-blue-600 mt-1"></i>
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">Order Created</p>
                                    <p class="text-xs text-gray-500"><?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></p>
                                </div>
                            </div>

                            <?php if ($order['delivery_date']): ?>
                            <div class="flex items-start gap-3">
                                <i class="fas fa-truck text-green-600 mt-1"></i>
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">Delivery Date</p>
                                    <p class="text-xs text-gray-500"><?= date('d M Y', strtotime($order['delivery_date'])) ?></p>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="flex items-start gap-3">
                                <i class="fas fa-sync text-purple-600 mt-1"></i>
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">Last Updated</p>
                                    <p class="text-xs text-gray-500"><?= date('d M Y, h:i A', strtotime($order['updated_at'])) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</body>
</html>