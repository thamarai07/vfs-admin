<?php
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$admin_role = $_SESSION['admin_role'] ?? 'staff';

// Get low stock count for notification
try {
    $lowStockStmt = $conn->query("SELECT COUNT(*) as total FROM products WHERE stock < 20");
    $lowStockCount = $lowStockStmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    $lowStockCount = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Admin Panel' ?> - Vasugi Fruit Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sidebar-active { 
            background: linear-gradient(to right, #10b981, #059669); 
            color: white; 
        }
        .stat-card { transition: all 0.3s; }
        .stat-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); 
        }
        .mobile-menu { display: none; }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
            .sidebar.active { transform: translateX(0); }
            .mobile-menu { display: block; }
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Mobile Menu Button -->
    <button id="mobile-menu-btn" class="mobile-menu fixed top-4 left-4 z-50 bg-gray-800 text-white p-3 rounded-lg md:hidden">
        <i class="fas fa-bars text-xl"></i>
    </button>

    <!-- Sidebar -->
    <div id="sidebar" class="sidebar fixed inset-y-0 left-0 w-64 bg-gradient-to-b from-gray-900 to-gray-800 text-white z-50 shadow-2xl">
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
        <nav class="p-4 space-y-2 overflow-y-auto h-[calc(100vh-200px)]">
            <a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'sidebar-active' : '' ?> flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-700 transition">
                <i class="fas fa-home w-5"></i>
                <span class="font-medium">Dashboard</span>
            </a>
            <a href="products.php" class="<?= basename($_SERVER['PHP_SELF']) == 'products.php' ? 'sidebar-active' : '' ?> flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-700 transition">
                <i class="fas fa-box w-5"></i>
                <span class="font-medium">Products</span>
            </a>
            <a href="orders.php" class="<?= basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'sidebar-active' : '' ?> flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-700 transition">
                <i class="fas fa-shopping-cart w-5"></i>
                <span class="font-medium">Orders</span>
            </a>
            <a href="customers.php" class="<?= basename($_SERVER['PHP_SELF']) == 'customers.php' ? 'sidebar-active' : '' ?> flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-700 transition">
                <i class="fas fa-users w-5"></i>
                <span class="font-medium">Customers</span>
            </a>
            <a href="reports.php" class="<?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'sidebar-active' : '' ?> flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-700 transition">
                <i class="fas fa-chart-line w-5"></i>
                <span class="font-medium">Reports</span>
            </a>
            <a href="settings.php" class="<?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'sidebar-active' : '' ?> flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-700 transition">
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
    <div class="md:ml-64">
        <!-- Header -->
        <header class="bg-white shadow-md sticky top-0 z-40">
            <div class="flex items-center justify-between px-4 md:px-8 py-4">
                <div>
                    <h2 class="text-xl md:text-2xl font-bold text-gray-800"><?= $pageTitle ?? 'Dashboard' ?></h2>
                    <p class="text-sm text-gray-600">Welcome back, <?= htmlspecialchars($admin_name) ?>!</p>
                </div>
                <div class="flex items-center gap-2 md:gap-4">
                    <!-- Search Bar -->
                    <div class="relative hidden lg:block">
                        <input 
                            type="text" 
                            id="globalSearch"
                            placeholder="Search..." 
                            class="pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 w-48 xl:w-64"
                        >
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    </div>
                    
                    <!-- Notifications -->
                    <button class="relative p-2 text-gray-600 hover:bg-gray-100 rounded-lg transition">
                        <i class="fas fa-bell text-xl"></i>
                        <?php if ($lowStockCount > 0): ?>
                        <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center animate-pulse">
                            <?= $lowStockCount ?>
                        </span>
                        <?php endif; ?>
                    </button>
                    
                    <!-- Profile -->
                    <div class="hidden md:flex items-center gap-3 bg-gray-100 px-4 py-2 rounded-lg">
                        <i class="fas fa-user-circle text-2xl text-gray-600"></i>
                        <div>
                            <p class="font-semibold text-sm"><?= htmlspecialchars($admin_name) ?></p>
                            <p class="text-xs text-gray-500 capitalize"><?= htmlspecialchars($admin_role) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="p-4 md:p-8">
            <?php if ($lowStockCount > 0 && basename($_SERVER['PHP_SELF']) == 'dashboard.php'): ?>
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

    <script>
        // Mobile menu toggle
        document.getElementById('mobile-menu-btn')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const menuBtn = document.getElementById('mobile-menu-btn');
            if (window.innerWidth < 768 && !sidebar.contains(event.target) && !menuBtn.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        });
    </script>