<?php
session_start();
require_once "config/db.php";

// Check if logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: products.php?msg=deleted");
        exit;
    } catch (Exception $e) {
        $error = "Error deleting product: " . $e->getMessage();
    }
}

// Search & Filter
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$filter = $_GET['filter'] ?? '';

$query = "SELECT * FROM products WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND name LIKE ?";
    $params[] = "%$search%";
}

if ($category) {
    $query .= " AND category = ?";
    $params[] = $category;
}

if ($filter === 'low_stock') {
    $query .= " AND stock < 20";
}

$query .= " ORDER BY id DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$catStmt = $conn->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL ORDER BY category");
$categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);

// Statistics
$statsStmt = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(stock) as total_stock,
        SUM(CASE WHEN stock < 20 THEN 1 ELSE 0 END) as low_stock
    FROM products
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - VFS Admin</title>
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
            <a href="products.php" class="sidebar-active flex items-center gap-3 px-4 py-3 rounded-lg">
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
                    <h2 class="text-2xl font-bold text-gray-800">Product Management</h2>
                    <p class="text-sm text-gray-600"><?= $stats['total'] ?> Total Products</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="product_add.php" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg font-semibold transition flex items-center gap-2">
                        <i class="fas fa-plus"></i> Add Product
                    </a>
                    <div class="flex items-center gap-3 bg-gray-100 px-4 py-2 rounded-lg">
                        <i class="fas fa-user-circle text-2xl text-gray-600"></i>
                        <span class="font-semibold text-sm"><?= htmlspecialchars($admin_name) ?></span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="p-8">
            <!-- Success/Error Messages -->
            <?php if (isset($_GET['msg'])): ?>
            <div class="mb-6 p-4 rounded-lg <?= $_GET['msg'] === 'deleted' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' ?>">
                <?php
                    if ($_GET['msg'] === 'added') echo '✅ Product added successfully!';
                    elseif ($_GET['msg'] === 'updated') echo '✅ Product updated successfully!';
                    elseif ($_GET['msg'] === 'deleted') echo '🗑️ Product deleted successfully!';
                ?>
            </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm">Total Products</p>
                            <h3 class="text-4xl font-bold mt-2"><?= $stats['total'] ?></h3>
                        </div>
                        <i class="fas fa-box text-6xl opacity-20"></i>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100 text-sm">Total Stock</p>
                            <h3 class="text-4xl font-bold mt-2"><?= number_format($stats['total_stock']) ?></h3>
                        </div>
                        <i class="fas fa-layer-group text-6xl opacity-20"></i>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-orange-100 text-sm">Low Stock Alert</p>
                            <h3 class="text-4xl font-bold mt-2"><?= $stats['low_stock'] ?></h3>
                        </div>
                        <i class="fas fa-exclamation-triangle text-6xl opacity-20"></i>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Search -->
                    <div class="md:col-span-2">
                        <input 
                            type="text" 
                            name="search" 
                            value="<?= htmlspecialchars($search) ?>"
                            placeholder="🔍 Search products..." 
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500"
                        >
                    </div>

                    <!-- Category Filter -->
                    <div>
                        <select name="category" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Filter -->
                    <div class="flex gap-2">
                        <select name="filter" class="flex-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                            <option value="">All Products</option>
                            <option value="low_stock" <?= $filter === 'low_stock' ? 'selected' : '' ?>>Low Stock</option>
                        </select>
                        <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition">
                            <i class="fas fa-filter"></i>
                        </button>
                        <a href="products.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition flex items-center">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Products Table -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">ID</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Image</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Product Name</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Category</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Price/kg</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Stock</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-inbox text-4xl mb-2"></i>
                                    <p>No products found</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($products as $product): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">#<?= $product['id'] ?></td>
                                <td class="px-6 py-4">
                                    <?php if ($product['image']): ?>
                                    <img src="assets/images/uploads/<?= htmlspecialchars(explode(',', $product['image'])[0]) ?>" 
                                         alt="Product" 
                                         class="w-12 h-12 rounded-lg object-cover"
                                        >
                                    <?php else: ?>
                                    <div class="w-12 h-12 bg-gray-200 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-image text-gray-400"></i>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-gray-900"><?= htmlspecialchars($product['name']) ?></p>
                                    <p class="text-xs text-gray-500 mt-1 line-clamp-1"><?= htmlspecialchars($product['description'] ?? '') ?></p>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        <?= htmlspecialchars($product['category'] ?? 'General') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 font-bold text-green-600">₹<?= number_format($product['price_per_kg'], 2) ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1 text-sm font-semibold rounded-full <?= $product['stock'] < 20 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' ?>">
                                        <?= $product['stock'] ?> kg
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <a href="product_edit.php?id=<?= $product['id'] ?>" 
                                           class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm transition">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="products.php?delete=<?= $product['id'] ?>" 
                                           onclick="return confirm('Delete this product?')"
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