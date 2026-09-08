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

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $slug        = festival_slugify($_POST['slug'] ?? '') ?: festival_slugify($name);
    $tagline     = trim($_POST['tagline'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $theme_color = trim($_POST['theme_color'] ?? '');
    $display_ord = (int)($_POST['display_order'] ?? 0);
    $is_active   = isset($_POST['is_active']) ? 1 : 0;
    $start_date  = $_POST['start_date'] ?: null;
    $end_date    = $_POST['end_date'] ?: null;

    if ($name === '' || $slug === '') {
        $error = "Festival name is required.";
    } else {
        $dupe = $conn->prepare("SELECT id FROM festivals WHERE slug = ?");
        $dupe->execute([$slug]);
        if ($dupe->fetch()) {
            $error = "A festival with the slug \"$slug\" already exists. Pick another name/slug.";
        }
    }

    if ($error === "") {
        $banner = festival_upload('banner_image');
        $mobile = festival_upload('mobile_image');

        $stmt = $conn->prepare("
            INSERT INTO festivals
                (name, slug, tagline, description, theme_color, banner_image, mobile_image,
                 display_order, is_active, start_date, end_date, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $name, $slug, $tagline, $description, $theme_color, $banner, $mobile,
            $display_ord, $is_active, $start_date, $end_date
        ]);
        $newId = (int)$conn->lastInsertId();
        header("Location: festival_edit.php?id=$newId&msg=added");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Festival - VFS Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
  <?php $festivalNavActive = 'festival_add.php'; include "includes/festival_nav.php"; ?>

  <div class="ml-64 p-8 max-w-3xl">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-2xl font-bold text-gray-800">Add Festival</h2>
      <a href="festivals.php" class="text-gray-600 hover:underline"><i class="fas fa-arrow-left mr-1"></i>Back</a>
    </div>

    <?php if ($error): ?>
      <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="bg-white shadow-lg rounded-xl p-6 space-y-5">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Festival Name *</label>
          <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                 class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500"
                 placeholder="e.g., Diwali">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Slug (URL)</label>
          <input type="text" name="slug" value="<?= htmlspecialchars($_POST['slug'] ?? '') ?>"
                 class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500"
                 placeholder="auto from name — e.g. diwali">
          <p class="text-xs text-gray-500 mt-1">Page URL will be <code>/festival/&lt;slug&gt;</code></p>
        </div>
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Tagline</label>
        <input type="text" name="tagline" value="<?= htmlspecialchars($_POST['tagline'] ?? '') ?>"
               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500"
               placeholder="Short line shown under the festival title">
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
        <textarea name="description" rows="3"
                  class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
      </div>

      <div class="grid grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Theme Color</label>
          <input type="text" name="theme_color" value="<?= htmlspecialchars($_POST['theme_color'] ?? '') ?>"
                 class="w-full px-4 py-3 border border-gray-300 rounded-xl" placeholder="#c2410c">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Display Order</label>
          <input type="number" name="display_order" value="<?= htmlspecialchars($_POST['display_order'] ?? '0') ?>"
                 class="w-full px-4 py-3 border border-gray-300 rounded-xl">
        </div>
        <div class="flex items-end">
          <label class="flex items-center gap-3 cursor-pointer pb-3">
            <input type="checkbox" name="is_active" checked class="w-5 h-5 text-green-600 rounded">
            <span class="font-medium">Active</span>
          </label>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Start Date</label>
          <input type="date" name="start_date" value="<?= htmlspecialchars($_POST['start_date'] ?? '') ?>"
                 class="w-full px-4 py-3 border border-gray-300 rounded-xl">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">End Date</label>
          <input type="date" name="end_date" value="<?= htmlspecialchars($_POST['end_date'] ?? '') ?>"
                 class="w-full px-4 py-3 border border-gray-300 rounded-xl">
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Banner Image (desktop)</label>
          <input type="file" name="banner_image" accept="image/*" class="w-full text-sm">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Banner Image (mobile)</label>
          <input type="file" name="mobile_image" accept="image/*" class="w-full text-sm">
        </div>
      </div>

      <div class="flex gap-4 pt-2">
        <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-lg">
          <i class="fas fa-check mr-2"></i>Create &amp; add products
        </button>
        <a href="festivals.php" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-3 rounded-lg text-center">Cancel</a>
      </div>
    </form>
  </div>
</body>
</html>
