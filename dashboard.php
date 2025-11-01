<?php
session_start();
require_once "config/db.php";

// Check if logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$admin_role = $_SESSION['admin_role'] ?? 'staff';

// Fetch dashboard statistics
try {
    // Total Products
    $productStmt = $conn->query("SELECT COUNT(*) as total FROM products");
    $totalProducts = $productStmt->fetch()['total'];
    
    // Total Orders
    $orderStmt = $conn->query("SELECT COUNT(*) as total FROM orders");
    $totalOrders = $orderStmt->fetch()['total'];
    
    // Total Revenue
    $revenueStmt = $conn->query("SELECT SUM(total) as revenue FROM orders WHERE status = 'delivered'");
    $totalRevenue = $revenueStmt->fetch()['revenue'] ?? 0;
    
    // Total Customers
    $customerStmt = $conn->query("SELECT COUNT(*) as total FROM customers");
    $totalCustomers = $customerStmt->fetch()['total'];
    
    // Low Stock Products
    $lowStockStmt = $conn->query("SELECT COUNT(*) as total FROM products WHERE stock < 20");
    $lowStockCount = $lowStockStmt->fetch()['total'];
    
    // Recent Orders
    $recentOrdersStmt = $conn->query("
        SELECT o.id, o.order_number, o.customer_name, o.total, o.status, o.created_at
        FROM orders o
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $recentOrders = $recentOrdersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top Products
    $topProductsStmt = $conn->query("
        SELECT p.name, SUM(oi.quantity) as total_sold, SUM(oi.subtotal) as revenue
        FROM order_items oi
        INNER JOIN products p ON oi.product_id = p.id
        GROUP BY p.id, p.name
        ORDER BY total_sold DESC
        LIMIT 5
    ");
    $topProducts = $topProductsStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $totalProducts = $totalOrders = $totalRevenue = $totalCustomers = $lowStockCount = 0;
    $recentOrders = $topProducts = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Vasugi Fruit Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sidebar-active { background: linear-gradient(to right, #10b981, #059669); color: white; }
        .stat-card { transition: all 0.3s; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Sidebar -->
    <div class="fixed inset-y-0 left-0 w-64 bg-gradient-to-b from-gray-900 to-gray-800 text-white z-50 shadow-2xl">
        <!-- Logo -->
        <div class="p-6 border-b border-gray-700">
            <div class="flex items-center gap-3">
                <i class="fas fa-apple-alt text-3xl text-green-400"></i>
                <div>
                    <h1 class="text-xl font-bold">Vasugi Fruits</h1>
                    <p class="text-xs text-gray-400">Admin Panel</p>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="p-4 space-y-2">
            <a href="dashboard.php" class="sidebar-active flex items-center gap-3 px-4 py-3 rounded-lg">
                <i class="fas fa-home w-5"></i>
                <span class="font-medium">Dashboard</span>
            </a>
            <a href="products.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-700 transition">
                <i class="fas fa-box w-5"></i>
                <span class="font-medium">Products</span>
            </a>
            <a href="orders.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-700 transition">
                <i class="fas fa-shopping-cart w-5"></i>
                <span class="font-medium">Orders</span>
            </a>
            <a href="customers.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-700 transition">
                <i class="fas fa-users w-5"></i>
                <span class="font-medium">Customers</span>
            </a>
            <a href="reports.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-700 transition">
                <i class="fas fa-chart-line w-5"></i>
                <span class="font-medium">Reports</span>
            </a>
            <a href="settings.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-700 transition">
                <i class="fas fa-cog w-5"></i>
                <span class="font-medium">Settings</span>
            </a>
        </nav>

        <!-- Logout -->
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
                    <h2 class="text-2xl font-bold text-gray-800">Dashboard</h2>
                    <p class="text-sm text-gray-600">Welcome back, <?= htmlspecialchars($admin_name) ?>!</p>
                </div>
                <div class="flex items-center gap-4">
                    <!-- Search Bar -->
                    <div class="relative hidden md:block">
                        <input 
                            type="text" 
                            placeholder="Search products, orders..." 
                            class="pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 w-64"
                        >
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    </div>
                    
                    <!-- Notifications -->
                    <button class="relative p-2 text-gray-600 hover:bg-gray-100 rounded-lg transition">
                        <i class="fas fa-bell text-xl"></i>
                        <?php if ($lowStockCount > 0): ?>
                        <span class="absolute top-0 right-0 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center animate-pulse">
                            <?= $lowStockCount ?>
                        </span>
                        <?php endif; ?>
                    </button>
                    
                    <!-- Profile -->
                    <div class="flex items-center gap-3 bg-gray-100 px-4 py-2 rounded-lg">
                        <i class="fas fa-user-circle text-2xl text-gray-600"></i>
                        <div>
                            <p class="font-semibold text-sm"><?= htmlspecialchars($admin_name) ?></p>
                            <p class="text-xs text-gray-500 capitalize"><?= htmlspecialchars($admin_role) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Dashboard Content -->
        <main class="p-8">
            <!-- Alert for Low Stock -->
            <?php if ($lowStockCount > 0): ?>
            <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mb-6 rounded-lg flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <i class="fas fa-exclamation-triangle text-yellow-500 text-2xl"></i>
                    <div>
                        <p class="font-semibold text-yellow-800">Low Stock Alert!</p>
                        <p class="text-sm text-yellow-700"><?= $lowStockCount ?> products are running low on stock</p>
                    </div>
                </div>
                <a href="products.php?filter=low_stock" class="bg-yellow-500 text-white px-4 py-2 rounded-lg hover:bg-yellow-600 transition text-sm font-medium">
                    View Products
                </a>
            </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Products -->
                <div class="stat-card bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white cursor-pointer" onclick="window.location='products.php'">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm font-medium">Total Products</p>
                            <h3 class="text-4xl font-bold mt-2"><?= number_format($totalProducts) ?></h3>
                            <p class="text-blue-100 text-xs mt-2">
                                <i class="fas fa-arrow-up"></i> Active inventory
                            </p>
                        </div>
                        <i class="fas fa-box text-6xl opacity-20"></i>
                    </div>
                </div>

                <!-- Total Orders -->
                <div class="stat-card bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white cursor-pointer" onclick="window.location='orders.php'">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100 text-sm font-medium">Total Orders</p>
                            <h3 class="text-4xl font-bold mt-2"><?= number_format($totalOrders) ?></h3>
                            <p class="text-green-100 text-xs mt-2">
                                <i class="fas fa-chart-line"></i> All time
                            </p>
                        </div>
                        <i class="fas fa-shopping-cart text-6xl opacity-20"></i>
                    </div>
                </div>

                <!-- Total Revenue -->
                <div class="stat-card bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white cursor-pointer" onclick="window.location='reports.php'">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100 text-sm font-medium">Total Revenue</p>
                            <h3 class="text-4xl font-bold mt-2">₹<?= number_format($totalRevenue, 2) ?></h3>
                            <p class="text-purple-100 text-xs mt-2">
                                <i class="fas fa-calendar"></i> From delivered orders
                            </p>
                        </div>
                        <i class="fas fa-rupee-sign text-6xl opacity-20"></i>
                    </div>
                </div>

                <!-- Total Customers -->
                <div class="stat-card bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-lg p-6 text-white cursor-pointer" onclick="window.location='customers.php'">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-orange-100 text-sm font-medium">Total Customers</p>
                            <h3 class="text-4xl font-bold mt-2"><?= number_format($totalCustomers) ?></h3>
                            <p class="text-orange-100 text-xs mt-2">
                                <i class="fas fa-user-plus"></i> Registered users
                            </p>
                        </div>
                        <i class="fas fa-users text-6xl opacity-20"></i>
                    </div>
                </div>
            </div>

            <!-- Three Column Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <!-- Today's Summary -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-calendar-day text-green-600"></i>
                        Today's Summary
                    </h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center p-3 bg-blue-50 rounded-lg">
                            <span class="text-sm text-gray-600">Orders</span>
                            <span class="font-bold text-blue-600">12</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-green-50 rounded-lg">
                            <span class="text-sm text-gray-600">Revenue</span>
                            <span class="font-bold text-green-600">₹8,450</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-purple-50 rounded-lg">
                            <span class="text-sm text-gray-600">New Customers</span>
                            <span class="font-bold text-purple-600">3</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-bolt text-yellow-600"></i>
                        Quick Actions
                    </h3>
                    <div class="grid grid-cols-2 gap-3">
                        <button onclick="window.location='products.php?action=add'" class="p-4 bg-blue-50 hover:bg-blue-100 rounded-lg transition text-center">
                            <i class="fas fa-plus-circle text-2xl text-blue-600 mb-2"></i>
                            <p class="text-xs font-semibold text-gray-700">Add Product</p>
                        </button>
                        <button onclick="window.location='orders.php?action=add'" class="p-4 bg-green-50 hover:bg-green-100 rounded-lg transition text-center">
                            <i class="fas fa-cart-plus text-2xl text-green-600 mb-2"></i>
                            <p class="text-xs font-semibold text-gray-700">New Order</p>
                        </button>
                        <button onclick="window.location='customers.php?action=add'" class="p-4 bg-purple-50 hover:bg-purple-100 rounded-lg transition text-center">
                            <i class="fas fa-user-plus text-2xl text-purple-600 mb-2"></i>
                            <p class="text-xs font-semibold text-gray-700">Add Customer</p>
                        </button>
                        <button onclick="window.location='reports.php'" class="p-4 bg-orange-50 hover:bg-orange-100 rounded-lg transition text-center">
                            <i class="fas fa-file-invoice text-2xl text-orange-600 mb-2"></i>
                            <p class="text-xs font-semibold text-gray-700">Reports</p>
                        </button>
                    </div>
                </div>

                <!-- Order Status -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-chart-pie text-blue-600"></i>
                        Order Status
                    </h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Pending</span>
                            <div class="flex items-center gap-2">
                                <div class="w-32 h-2 bg-gray-200 rounded-full overflow-hidden">
                                    <div class="h-full bg-yellow-500" style="width: 40%"></div>
                                </div>
                                <span class="font-bold text-yellow-600 text-sm">8</span>
                            </div>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Processing</span>
                            <div class="flex items-center gap-2">
                                <div class="w-32 h-2 bg-gray-200 rounded-full overflow-hidden">
                                    <div class="h-full bg-blue-500" style="width: 60%"></div>
                                </div>
                                <span class="font-bold text-blue-600 text-sm">12</span>
                            </div>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Delivered</span>
                            <div class="flex items-center gap-2">
                                <div class="w-32 h-2 bg-gray-200 rounded-full overflow-hidden">
                                    <div class="h-full bg-green-500" style="width: 85%"></div>
                                </div>
                                <span class="font-bold text-green-600 text-sm">34</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Two Column Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Recent Orders -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xl font-bold text-gray-800">Recent Orders</h3>
                        <a href="orders.php" class="text-blue-600 hover:text-blue-700 text-sm font-medium">View All →</a>
                    </div>
                    <div class="space-y-3">
                        <?php foreach ($recentOrders as $order): ?>
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                            <div class="flex-1">
                                <p class="font-semibold text-gray-800"><?= htmlspecialchars($order['order_number']) ?></p>
                                <p class="text-sm text-gray-600"><?= htmlspecialchars($order['customer_name']) ?></p>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-green-600">₹<?= number_format($order['total'], 2) ?></p>
                                <span class="text-xs px-2 py-1 rounded-full bg-<?= $order['status'] == 'delivered' ? 'green' : 'yellow' ?>-100 text-<?= $order['status'] == 'delivered' ? 'green' : 'yellow' ?>-800">
                                    <?= ucfirst($order['status']) ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Top Products -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xl font-bold text-gray-800">Top Products</h3>
                        <a href="products.php" class="text-blue-600 hover:text-blue-700 text-sm font-medium">View All →</a>
                    </div>
                    <div class="space-y-3">
                        <?php foreach ($topProducts as $index => $product): ?>
                        <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-lg">
                            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center font-bold text-green-600">
                                #<?= $index + 1 ?>
                            </div>
                            <div class="flex-1">
                                <p class="font-semibold text-gray-800"><?= htmlspecialchars($product['name']) ?></p>
                                <p class="text-sm text-gray-600"><?= number_format($product['total_sold']) ?> sold</p>
                            </div>
                            <p class="font-bold text-green-600">₹<?= number_format($product['revenue'], 2) ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>