<?php
session_start();
require_once "config/db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

function festival_slugify(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}
function festival_upload(string $field): ?string {
    if (empty($_FILES[$field]['name']) || $_FILES[$field]['error'] !== 0) return null;
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) return null;
    $dir = 'assets/images/uploads/';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $name = 'festival_' . time() . '_' . random_int(1000, 9999) . '.' . $ext;
    return move_uploaded_file($_FILES[$field]['tmp_name'], $dir . $name) ? $name : null;
}

$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM festivals WHERE id = ?");
$stmt->execute([$id]);
$festival = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$festival) {
    header("Location: festivals.php");
    exit;
}

$error = "";
$notice = $_GET['msg'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_festival') {
        $name        = trim($_POST['name'] ?? '');
        $slug        = festival_slugify($_POST['slug'] ?? '') ?: festival_slugify($name);
        $tagline     = trim($_POST['tagline'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $theme_color = trim($_POST['theme_color'] ?? '');
        $display_ord = (int)($_POST['display_order'] ?? 0);
        $is_active   = isset($_POST['is_active']) ? 1 : 0;
        $start_date  = $_POST['start_date'] ?: null;
        $end_date    = $_POST['end_date'] ?: null;

        $dupe = $conn->prepare("SELECT id FROM festivals WHERE slug = ? AND id <> ?");
        $dupe->execute([$slug, $id]);
        if ($name === '' || $slug === '') {
            $error = "Festival name is required.";
        } elseif ($dupe->fetch()) {
            $error = "Slug \"$slug\" is used by another festival.";
        } else {
            $banner = festival_upload('banner_image') ?? $festival['banner_image'];
            $mobile = festival_upload('mobile_image') ?? $festival['mobile_image'];
            $conn->prepare("
                UPDATE festivals SET
                    name = ?, slug = ?, tagline = ?, description = ?, theme_color = ?,
                    banner_image = ?, mobile_image = ?, display_order = ?, is_active = ?,
                    start_date = ?, end_date = ?, updated_at = NOW()
                WHERE id = ?
            ")->execute([
                $name, $slug, $tagline, $description, $theme_color,
                $banner, $mobile, $display_ord, $is_active, $start_date, $end_date, $id
            ]);
            header("Location: festival_edit.php?id=$id&msg=updated");
            exit;
        }
    }

    if ($action === 'add_products') {
        $productIds = array_filter(array_map('intval', $_POST['product_ids'] ?? []));
        if ($productIds) {
            $ins = $conn->prepare("
                INSERT INTO festival_products (festival_id, product_id, display_order, is_active, created_at)
                VALUES (?, ?, 0, 1, NOW())
                ON DUPLICATE KEY UPDATE is_active = 1
            ");
            foreach ($productIds as $pid) {
                $ins->execute([$id, $pid]);
            }
        }
        header("Location: festival_edit.php?id=$id&msg=products_added");
        exit;
    }

    if ($action === 'save_mappings') {
        $orders  = $_POST['map_order']  ?? [];
        $actives = $_POST['map_active'] ?? [];
        $upd = $conn->prepare("UPDATE festival_products SET display_order = ?, is_active = ? WHERE id = ? AND festival_id = ?");
        foreach ($orders as $mapId => $ord) {
            $mapId = (int)$mapId;
            $upd->execute([(int)$ord, isset($actives[$mapId]) ? 1 : 0, $mapId, $id]);
        }
        header("Location: festival_edit.php?id=$id&msg=mappings_saved");
        exit;
    }

    if ($action === 'remove_product') {
        $mapId = (int)($_POST['map_id'] ?? 0);
        $conn->prepare("DELETE FROM festival_products WHERE id = ? AND festival_id = ?")->execute([$mapId, $id]);
        header("Location: festival_edit.php?id=$id&msg=product_removed");
        exit;
    }
}

// Assigned products
$assigned = $conn->prepare("
    SELECT fp.id AS map_id, fp.display_order, fp.is_active,
           p.id, p.name, p.category, p.price_per_kg, p.piece_price, p.unit, p.stock, p.image
    FROM festival_products fp
    INNER JOIN products p ON p.id = fp.product_id
    WHERE fp.festival_id = ?
    ORDER BY fp.display_order ASC, p.name ASC
");
$assigned->execute([$id]);
$assigned = $assigned->fetchAll(PDO::FETCH_ASSOC);
$assignedIds = array_column($assigned, 'id');

// Products available to add (active, not already assigned)
$available = $conn->query("SELECT id, name, category, unit, price_per_kg, piece_price, stock
                           FROM products WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$available = array_values(array_filter($available, fn($p) => !in_array($p['id'], $assignedIds)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Festival - VFS Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
  <?php $festivalNavActive = 'festival_edit.php'; include "includes/festival_nav.php"; ?>

  <div class="ml-64 p-8 max-w-5xl">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-2xl font-bold text-gray-800">Edit Festival — <?= htmlspecialchars($festival['name']) ?></h2>
      <div class="flex gap-4 items-center">
        <a href="/festival/<?= htmlspecialchars($festival['slug']) ?>" target="_blank" class="text-blue-600 hover:underline text-sm">
          <i class="fas fa-external-link-alt mr-1"></i>View page
        </a>
        <a href="festivals.php" class="text-gray-600 hover:underline"><i class="fas fa-arrow-left mr-1"></i>Back</a>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($notice): ?>
      <div class="mb-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">Saved.</div>
    <?php endif; ?>

    <!-- ── Festival details ── -->
    <form method="POST" enctype="multipart/form-data" class="bg-white shadow-lg rounded-xl p-6 space-y-5 mb-8">
      <input type="hidden" name="action" value="save_festival">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Festival Name *</label>
          <input type="text" name="name" required value="<?= htmlspecialchars($festival['name']) ?>"
                 class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Slug (URL)</label>
          <input type="text" name="slug" value="<?= htmlspecialchars($festival['slug']) ?>"
                 class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500">
        </div>
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Tagline</label>
        <input type="text" name="tagline" value="<?= htmlspecialchars($festival['tagline'] ?? '') ?>"
               class="w-full px-4 py-3 border border-gray-300 rounded-xl">
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
        <textarea name="description" rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-xl"><?= htmlspecialchars($festival['description'] ?? '') ?></textarea>
      </div>
      <div class="grid grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Theme Color</label>
          <input type="text" name="theme_color" value="<?= htmlspecialchars($festival['theme_color'] ?? '') ?>"
                 class="w-full px-4 py-3 border border-gray-300 rounded-xl" placeholder="#c2410c">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Display Order</label>
          <input type="number" name="display_order" value="<?= (int)$festival['display_order'] ?>"
                 class="w-full px-4 py-3 border border-gray-300 rounded-xl">
        </div>
        <div class="flex items-end">
          <label class="flex items-center gap-3 cursor-pointer pb-3">
            <input type="checkbox" name="is_active" <?= $festival['is_active'] ? 'checked' : '' ?> class="w-5 h-5 text-green-600 rounded">
            <span class="font-medium">Active</span>
          </label>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Start Date</label>
          <input type="date" name="start_date" value="<?= htmlspecialchars($festival['start_date'] ?? '') ?>"
                 class="w-full px-4 py-3 border border-gray-300 rounded-xl">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">End Date</label>
          <input type="date" name="end_date" value="<?= htmlspecialchars($festival['end_date'] ?? '') ?>"
                 class="w-full px-4 py-3 border border-gray-300 rounded-xl">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Banner Image (desktop)</label>
          <?php if (!empty($festival['banner_image'])): ?>
            <img src="assets/images/uploads/<?= htmlspecialchars($festival['banner_image']) ?>" class="w-40 h-20 object-cover rounded mb-2 shadow">
          <?php endif; ?>
          <input type="file" name="banner_image" accept="image/*" class="w-full text-sm">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Banner Image (mobile)</label>
          <?php if (!empty($festival['mobile_image'])): ?>
            <img src="assets/images/uploads/<?= htmlspecialchars($festival['mobile_image']) ?>" class="w-24 h-32 object-cover rounded mb-2 shadow">
          <?php endif; ?>
          <input type="file" name="mobile_image" accept="image/*" class="w-full text-sm">
        </div>
      </div>
      <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg">
        <i class="fas fa-save mr-2"></i>Save Festival
      </button>
    </form>

    <!-- ── Add products ── -->
    <div class="bg-white shadow-lg rounded-xl p-6 mb-8">
      <h3 class="text-lg font-bold text-gray-800 mb-4">Add products to this festival</h3>
      <?php if ($available): ?>
      <form method="POST">
        <input type="hidden" name="action" value="add_products">
        <input type="text" id="prodSearch" placeholder="Filter products…"
               class="w-full px-4 py-2 border border-gray-300 rounded-lg mb-3">
        <div class="max-h-64 overflow-y-auto border border-gray-200 rounded-lg divide-y" id="prodList">
          <?php foreach ($available as $p): ?>
            <label class="flex items-center gap-3 px-3 py-2 hover:bg-gray-50 cursor-pointer prod-row"
                   data-name="<?= htmlspecialchars(strtolower($p['name'] . ' ' . $p['category'])) ?>">
              <input type="checkbox" name="product_ids[]" value="<?= (int)$p['id'] ?>" class="w-4 h-4 text-green-600 rounded">
              <span class="flex-1 text-sm text-gray-700"><?= htmlspecialchars($p['name']) ?>
                <span class="text-gray-400">— <?= htmlspecialchars($p['category'] ?: 'Uncategorised') ?></span>
              </span>
              <span class="text-xs text-gray-500">
                ₹<?= number_format((float)$p['price_per_kg'], 0) ?>/<?= htmlspecialchars($p['unit'] ?: 'kg') ?>
                <?= $p['piece_price'] !== null ? ' · ₹' . number_format((float)$p['piece_price'], 0) . '/piece' : '' ?>
                · stock <?= (int)$p['stock'] ?>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
        <button type="submit" class="mt-4 bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-5 rounded-lg">
          <i class="fas fa-plus mr-1"></i>Add selected
        </button>
      </form>
      <?php else: ?>
        <p class="text-gray-500 text-sm">All active products are already assigned.</p>
      <?php endif; ?>
    </div>

    <!-- ── Assigned products ── -->
    <div class="bg-white shadow-lg rounded-xl p-6">
      <h3 class="text-lg font-bold text-gray-800 mb-4">Assigned products (<?= count($assigned) ?>)</h3>
      <?php if ($assigned): ?>
      <form method="POST">
        <input type="hidden" name="action" value="save_mappings">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-gray-500 border-b">
              <th class="py-2">Product</th>
              <th class="py-2">Pricing</th>
              <th class="py-2">Stock</th>
              <th class="py-2 w-24">Order</th>
              <th class="py-2 w-20">Shown</th>
              <th class="py-2 w-20"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($assigned as $a): ?>
              <tr class="border-b">
                <td class="py-2 font-medium text-gray-800"><?= htmlspecialchars($a['name']) ?>
                  <span class="text-gray-400 font-normal">— <?= htmlspecialchars($a['category'] ?: '—') ?></span>
                </td>
                <td class="py-2 text-gray-600">
                  ₹<?= number_format((float)$a['price_per_kg'], 0) ?>/<?= htmlspecialchars($a['unit'] ?: 'kg') ?>
                  <?php if ($a['piece_price'] !== null): ?>
                    <span class="text-green-700 font-semibold">· ₹<?= number_format((float)$a['piece_price'], 0) ?>/piece</span>
                  <?php endif; ?>
                </td>
                <td class="py-2 text-gray-600"><?= (int)$a['stock'] ?></td>
                <td class="py-2">
                  <input type="number" name="map_order[<?= (int)$a['map_id'] ?>]" value="<?= (int)$a['display_order'] ?>"
                         class="w-20 px-2 py-1 border border-gray-300 rounded">
                </td>
                <td class="py-2 text-center">
                  <input type="checkbox" name="map_active[<?= (int)$a['map_id'] ?>]" <?= $a['is_active'] ? 'checked' : '' ?>
                         class="w-4 h-4 text-green-600 rounded">
                </td>
                <td class="py-2">
                  <button type="submit" form="rm_<?= (int)$a['map_id'] ?>" class="text-red-500 hover:underline"
                          onclick="return confirm('Remove this product from the festival?')">Remove</button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <button type="submit" class="mt-4 bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-5 rounded-lg">
          <i class="fas fa-save mr-1"></i>Save order &amp; visibility
        </button>
      </form>
      <?php foreach ($assigned as $a): ?>
        <form id="rm_<?= (int)$a['map_id'] ?>" method="POST" class="hidden">
          <input type="hidden" name="action" value="remove_product">
          <input type="hidden" name="map_id" value="<?= (int)$a['map_id'] ?>">
        </form>
      <?php endforeach; ?>
      <?php else: ?>
        <p class="text-gray-500 text-sm">No products assigned yet — add some above.</p>
      <?php endif; ?>
    </div>
  </div>

  <script>
    const s = document.getElementById('prodSearch');
    if (s) s.addEventListener('input', () => {
      const q = s.value.toLowerCase();
      document.querySelectorAll('#prodList .prod-row').forEach(r => {
        r.style.display = r.dataset.name.includes(q) ? '' : 'none';
      });
    });
  </script>
</body>
</html>
