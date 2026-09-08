<?php
session_start();
require_once "config/db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$msg = $_GET['msg'] ?? '';

// Festivals + assigned product counts
$festivals = $conn->query("
    SELECT f.*,
           (SELECT COUNT(*) FROM festival_products fp
             WHERE fp.festival_id = f.id AND fp.is_active = 1) AS product_count
    FROM festivals f
    ORDER BY f.display_order ASC, f.id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Festivals - Vasugi Fruit Shop Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
  <?php $festivalNavActive = 'festivals.php'; include "includes/festival_nav.php"; ?>

  <div class="ml-64 p-8">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-2xl font-bold text-gray-800"><i class="fas fa-gifts text-green-600 mr-2"></i>Festivals</h2>
      <a href="festival_add.php" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-semibold">
        <i class="fas fa-plus-circle"></i> Add Festival
      </a>
    </div>

    <?php if ($msg): ?>
      <div class="mb-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
        <?= htmlspecialchars(
              ['added'=>'Festival created.','updated'=>'Festival updated.',
               'deleted'=>'Festival deleted.','status'=>'Festival status changed.'][$msg] ?? 'Done.'
            ) ?>
      </div>
    <?php endif; ?>

    <div class="bg-white shadow-lg rounded-xl p-6 overflow-x-auto">
      <table class="min-w-full border-collapse">
        <thead>
          <tr class="bg-green-600 text-white text-left">
            <th class="p-3">#</th>
            <th class="p-3">Banner</th>
            <th class="p-3">Name</th>
            <th class="p-3">Slug / URL</th>
            <th class="p-3">Products</th>
            <th class="p-3">Window</th>
            <th class="p-3">Status</th>
            <th class="p-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($festivals): ?>
            <?php foreach ($festivals as $f): ?>
              <tr class="border-b hover:bg-gray-50">
                <td class="p-3"><?= (int)$f['id'] ?></td>
                <td class="p-3">
                  <?php if (!empty($f['banner_image'])): ?>
                    <img src="assets/images/uploads/<?= htmlspecialchars($f['banner_image']) ?>" class="w-24 h-14 object-cover rounded shadow">
                  <?php else: ?>
                    <span class="text-gray-400 text-sm">—</span>
                  <?php endif; ?>
                </td>
                <td class="p-3 font-semibold text-gray-700"><?= htmlspecialchars($f['name']) ?></td>
                <td class="p-3 text-sm text-gray-600"><code>/festival/<?= htmlspecialchars($f['slug']) ?></code></td>
                <td class="p-3"><?= (int)$f['product_count'] ?></td>
                <td class="p-3 text-xs text-gray-500">
                  <?= $f['start_date'] ? htmlspecialchars($f['start_date']) : '—' ?>
                  &rarr;
                  <?= $f['end_date'] ? htmlspecialchars($f['end_date']) : '—' ?>
                </td>
                <td class="p-3">
                  <?php if ($f['is_active']): ?>
                    <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm font-medium">Active</span>
                  <?php else: ?>
                    <span class="bg-gray-200 text-gray-700 px-3 py-1 rounded-full text-sm font-medium">Inactive</span>
                  <?php endif; ?>
                </td>
                <td class="p-3 space-x-3 whitespace-nowrap">
                  <a href="festival_edit.php?id=<?= (int)$f['id'] ?>" class="text-blue-600 font-semibold hover:underline">Edit</a>
                  <a href="festival_activate.php?id=<?= (int)$f['id'] ?>&to=<?= $f['is_active'] ? 0 : 1 ?>"
                     class="<?= $f['is_active'] ? 'text-gray-600' : 'text-green-600' ?> font-semibold hover:underline">
                     <?= $f['is_active'] ? 'Deactivate' : 'Activate' ?>
                  </a>
                  <a href="festival_delete.php?id=<?= (int)$f['id'] ?>" class="text-red-500 font-semibold hover:underline"
                     onclick="return confirm('Delete this festival and its product assignments?')">Delete</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="8" class="text-center p-6 text-gray-500">No festivals yet. Click <b>Add Festival</b> to create one.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
