<?php
session_start();
require_once "config/db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Fetch logos
$stmt = $conn->query("SELECT * FROM logo_master ORDER BY id DESC");
$logos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Logo Master - Vasugi Fruit Shop Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
  <div class="fixed inset-y-0 left-0 w-64 bg-gradient-to-b from-gray-900 to-gray-800 text-white shadow-lg">
    <div class="p-6 border-b border-gray-700 flex items-center gap-3">
      <i class="fas fa-apple-alt text-3xl text-green-400"></i>
      <div>
        <h1 class="text-xl font-bold">Vasugi Fruits</h1>
        <p class="text-xs text-gray-400">Admin Panel</p>
      </div>
    </div>

    <nav class="p-4 space-y-2">
      <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-700 transition">
        <i class="fas fa-home w-5"></i><span>Dashboard</span>
      </a>
      <a href="products.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-700 transition">
        <i class="fas fa-box w-5"></i><span>Products</span>
      </a>
      <a href="orders.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-700 transition">
        <i class="fas fa-shopping-cart w-5"></i><span>Orders</span>
      </a>
      <a href="logo_master.php" class="sidebar-active flex items-center gap-3 px-4 py-3 rounded-lg bg-green-600">
        <i class="fas fa-image w-5"></i><span>Logo Master</span>
      </a>
    </nav>

    <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-gray-700">
      <a href="logout.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-red-600 transition">
        <i class="fas fa-sign-out-alt w-5"></i><span>Logout</span>
      </a>
    </div>
  </div>

  <div class="ml-64 p-8">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-2xl font-bold text-gray-800">Logo Master</h2>
      <a href="logo_add.php" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-semibold">
        <i class="fas fa-plus-circle"></i> Add Logo
      </a>
    </div>

    <div class="bg-white shadow-lg rounded-xl p-6">
      <table class="min-w-full border-collapse">
        <thead>
          <tr class="bg-green-600 text-white text-left">
            <th class="p-3">#</th>
            <th class="p-3">Preview</th>
            <th class="p-3">Logo Name</th>
            <th class="p-3">Status</th>
            <th class="p-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($logos) > 0): ?>
            <?php foreach ($logos as $logo): ?>
              <tr class="border-b hover:bg-gray-50">
                <td class="p-3"><?= $logo['id'] ?></td>
                <td class="p-3"><img src="<?= $logo['logo_path'] ?>" class="w-20 h-auto rounded shadow"></td>
                <td class="p-3 font-semibold text-gray-700"><?= htmlspecialchars($logo['logo_name']) ?></td>
                <td class="p-3">
                  <?php if ($logo['is_active']): ?>
                    <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm font-medium">Active</span>
                  <?php else: ?>
                    <span class="bg-gray-200 text-gray-700 px-3 py-1 rounded-full text-sm font-medium">Inactive</span>
                  <?php endif; ?>
                </td>
                <td class="p-3 space-x-3">
                  <?php if (!$logo['is_active']): ?>
                    <a href="logo_activate.php?id=<?= $logo['id'] ?>" class="text-green-600 font-semibold hover:underline">Activate</a>
                  <?php endif; ?>
                  <a href="logo_delete.php?id=<?= $logo['id'] ?>" class="text-red-500 font-semibold hover:underline" onclick="return confirm('Delete this logo?')">Delete</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="5" class="text-center p-4 text-gray-500">No logos uploaded yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
