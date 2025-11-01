<?php
session_start();
require_once "config/db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: orders.php?msg=deleted");
        exit;
    } catch (Exception $e) {
        $error = "Error deleting order";
    }
}

// Search & Filter
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$payment_status = $_GET['payment_status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$query = "SELECT * FROM orders WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (order_number LIKE ? OR customer_name LIKE ? OR customer_phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status) {
    $query .= " AND status = ?";
    $params[] = $status;
}

if ($payment_status) {
    $query .= " AND payment_status = ?";
    $params[] = $payment_status;
}

if ($date_from) {
    $query .= " AND DATE(created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $query .= " AND DATE(created_at) <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY id DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistics
$statsStmt = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
        SUM(CASE WHEN payment_status = 'unpaid' THEN 1 ELSE 0 END) as unpaid,
        SUM(total) as total_revenue
    FROM orders
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Status badge colors
function getStatusBadge($status) {
    $colors = [
        'pending' => 'bg-yellow-100 text-yellow-800',
        'confirmed' => 'bg-blue-100 text-blue-800',
        'processing' => 'bg-purple-100 text-purple-800',
        'delivered' => 'bg-green-100 text-green-800',
        'cancelled' => 'bg-red-100 text-red-800'
    ];
    return $colors[$status] ?? 'bg-gray-100 text-gray-800';
}

function getPaymentBadge($payment_status) {
    $colors = [
        'unpaid' => 'bg-red-100 text-red-800',
        'paid' => 'bg-green-100 text-green-800',
        'partial' => 'bg-orange-100 text-orange-800'
    ];
    return $colors[$payment_status] ?? 'bg-gray-100 text-gray-800';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - VFS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sidebar-active { background: linear-gradient(to right, #10b981, #059669); color: white; }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Sidebar -->
    <div class="fixed inset-y-0 left-0 w-64 bg-gradient-to-b from-gray-900 to-gray-800 text-white z-50 shadow-2xl">
        <div class="p-6 border-b border-gray-700">
            <div class="flex items-center gap-3">
                <i class="fas fa-apple-alt text-3xl text-green-400"></i>
                <div>
                    <h1 class="text-xl font-bold">VFS Portal</h1>
                    <p class="text-xs text-gray-400">Admin Panel</p>
                </div>
            </div>
        </div>

        <nav class="p-4 space-y-2">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-700 transition">
                <i class="fas fa-home w-5"></i>
                <span class="font-medium">Dashboard</span>
            </a>
            <a href="products.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-700 transition">
                <i class="fas fa-box w-5"></i>
                <span class="font-medium">Products</span>
            </a>
            <a href="orders.php" class="sidebar-active flex items-center gap-3 px-4 py-3 rounded-lg">
                <i class="fas fa-shopping-cart w-5"></i>
                <span class="font-medium">Orders</span>
            </a>
            <a href="customers.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-700 transition">
                <i class="fas fa-users w-5"></i>
                <span class="font-medium">Customers</span>
            </a>
            <a href="settings.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-700 transition">
                <i class="fas fa-cog w-5"></i>
                <span class="font-medium">Settings</span>
            </a>
        </nav>

        <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-gray-700">
            <a href="logout.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-red-600 transition">
                <i class="fas fa-sign-out-alt w-5"></i>
                <span class="font-medium">Logout</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="ml-64">
        <!-- Header -->
        <header class="bg-white shadow-md sticky top-0 z-40">
            <div class="flex items-center justify-between px-8 py-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Order Management</h2>
                    <p class="text-sm text-gray-600"><?= $stats['total'] ?> Total Orders</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="order_add.php" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg font-semibold transition flex items-center gap-2">
                        <i class="fas fa-plus"></i> New Order
                    </a>
                    <div class="flex items-center gap-3 bg-gray-100 px-4 py-2 rounded-lg">
                        <i class="fas fa-user-circle text-2xl text-gray-600"></i>
                        <span class="font-semibold text-sm"><?= htmlspecialchars($admin_name) ?></span>
                    </div>
                </div>
            </div>
        </header>

        <main class="p-8">
            <!-- Success/Error Messages -->
            <?php if (isset($_GET['msg'])): ?>
            <div class="mb-6 p-4 rounded-lg <?= $_GET['msg'] === 'deleted' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' ?>">
                <?php
                    if ($_GET['msg'] === 'added') echo '✅ Order created successfully!';
                    elseif ($_GET['msg'] === 'updated') echo '✅ Order updated successfully!';
                    elseif ($_GET['msg'] === 'deleted') echo '🗑️ Order deleted successfully!';
                ?>
            </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-8">
                <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white">
                    <p class="text-blue-100 text-sm">Total Orders</p>
                    <h3 class="text-3xl font-bold mt-2"><?= $stats['total'] ?></h3>
                </div>

                <div class="bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-xl shadow-lg p-6 text-white">
                    <p class="text-yellow-100 text-sm">Pending</p>
                    <h3 class="text-3xl font-bold mt-2"><?= $stats['pending'] ?></h3>
                </div>

                <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white">
                    <p class="text-green-100 text-sm">Delivered</p>
                    <h3 class="text-3xl font-bold mt-2"><?= $stats['delivered'] ?></h3>
                </div>

                <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-xl shadow-lg p-6 text-white">
                    <p class="text-red-100 text-sm">Unpaid</p>
                    <h3 class="text-3xl font-bold mt-2"><?= $stats['unpaid'] ?></h3>
                </div>

                <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white">
                    <p class="text-purple-100 text-sm">Revenue</p>
                    <h3 class="text-2xl font-bold mt-2">₹<?= number_format($stats['total_revenue'], 2) ?></h3>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                    <!-- Search -->
                    <div class="md:col-span-2">
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                               placeholder="🔍 Search order/customer..." 
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                    </div>

                    <!-- Status -->
                    <div>
                        <select name="status" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                            <option value="">All Status</option>
                            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="confirmed" <?= $status === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                            <option value="processing" <?= $status === 'processing' ? 'selected' : '' ?>>Processing</option>
                            <option value="delivered" <?= $status === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                            <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>

                    <!-- Payment Status -->
                    <div>
                        <select name="payment_status" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                            <option value="">Payment Status</option>
                            <option value="unpaid" <?= $payment_status === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                            <option value="paid" <?= $payment_status === 'paid' ? 'selected' : '' ?>>Paid</option>
                            <option value="partial" <?= $payment_status === 'partial' ? 'selected' : '' ?>>Partial</option>
                        </select>
                    </div>

                    <!-- Date Range -->
                    <div class="flex gap-2">
                        <input type="date" name="date_from" value="<?= $date_from ?>" 
                               class="flex-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                    </div>

                    <!-- Buttons -->
                    <div class="flex gap-2">
                        <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition">
                            <i class="fas fa-filter"></i>
                        </button>
                        <a href="orders.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition flex items-center">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Orders Table -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Order #</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Customer</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Total</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Payment</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Date</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-shopping-cart text-4xl mb-2"></i>
                                    <p>No orders found</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4">
                                    <p class="font-bold text-blue-600"><?= htmlspecialchars($order['order_number']) ?></p>
                                    <p class="text-xs text-gray-500">#<?= $order['id'] ?></p>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-gray-900"><?= htmlspecialchars($order['customer_name']) ?></p>
                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($order['customer_phone']) ?></p>
                                </td>
                                <td class="px-6 py-4 font-bold text-green-600">₹<?= number_format($order['total'], 2) ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full <?= getStatusBadge($order['status']) ?>">
                                        <?= ucfirst($order['status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full <?= getPaymentBadge($order['payment_status']) ?>">
                                        <?= ucfirst($order['payment_status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <?= date('d M Y', strtotime($order['created_at'])) ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <a href="order_view.php?id=<?= $order['id'] ?>" 
                                           class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm transition">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="order_edit.php?id=<?= $order['id'] ?>" 
                                           class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm transition">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="orders.php?delete=<?= $order['id'] ?>" 
                                           onclick="return confirm('Delete this order?')"
                                           class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm transition">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>