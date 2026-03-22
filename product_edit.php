<?php
session_start();
require_once "config/db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$error = $success = "";

// Fetch product
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header("Location: products.php");
    exit;
}

// Generate slug
function generateSlug($text) {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9-]/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return preg_replace('/^-+|-+$/', '', $text);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $slug = !empty($_POST['slug']) ? generateSlug($_POST['slug']) : generateSlug($name);
    $category = trim($_POST['category']);
    $price_per_kg = (float)$_POST['price_per_kg'];
    $discount_percent = (float)$_POST['discount_percent'];
    $unit = $_POST['unit'];
    $min_qty = (float)$_POST['min_quantity'];
    $max_qty = (float)$_POST['max_quantity'];
    $stock = (float)$_POST['stock'];
    $description = trim($_POST['description']);
    $meta_title = trim($_POST['meta_title']);
    $meta_description = trim($_POST['meta_description']);
    $tags = trim($_POST['tags']);
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $imageName = $product['image'];

    // Image upload
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $imageName = 'product_' . time() . '_' . rand(1000,9999) . '.' . $ext;
            $uploadPath = '../assets/images/uploads/' . $imageName;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                if ($product['image'] && file_exists('../assets/images/uploads/' . $product['image'])) {
                    @unlink('../assets/images/uploads/' . $product['image']);
                }
            } else {
                $error = "Failed to upload image.";
            }
        } else {
            $error = "Invalid image format.";
        }
    }

    if (empty($error)) {
        try {
            $sql = "UPDATE products SET 
                    name = ?, slug = ?, category = ?, price_per_kg = ?, discount_percent = ?,
                    unit = ?, min_quantity = ?, max_quantity = ?, stock = ?, image = ?, 
                    description = ?, meta_title = ?, meta_description = ?, tags = ?,
                    is_featured = ?, is_active = ?, updated_at = NOW()
                    WHERE id = ?";

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $name, $slug, $category, $price_per_kg, $discount_percent,
                $unit, $min_qty, $max_qty, $stock, $imageName,
                $description, $meta_title, $meta_description, $tags,
                $is_featured, $is_active, $id
            ]);

            $success = "Product updated successfully!";
            // Refresh product data
            $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - FreshMart Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-8 px-4">
        <div class="max-w-5xl mx-auto">
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                <!-- Header -->
                <div class="bg-gradient-to-r from-green-600 to-emerald-700 text-white p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-3xl font-bold">Edit Product</h1>
                            <p class="text-green-100 mt-1">ID: #<?= $product['id'] ?> • Last updated: <?= date('d M Y, h:i A', strtotime($product['updated_at'])) ?></p>
                        </div>
                        <a href="products.php" class="bg-white/20 hover:bg-white/30 backdrop-blur px-5 py-3 rounded-xl font-medium transition">
                            ← Back to Products
                        </a>
                    </div>
                </div>

                <div class="p-8">
                    <?php if ($success): ?>
                    <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-800 rounded-xl flex items-center">
                        <i class="fas fa-check-circle text-2xl mr-3"></i>
                        <span class="font-medium"><?= $success ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                    <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-800 rounded-xl flex items-center">
                        <i class="fas fa-exclamation-triangle text-2xl mr-3"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" class="space-y-8">
                        <!-- Basic Info -->
                        <div class="grid lg:grid-cols-2 gap-8">
                            <div class="space-y-6">
                                <h3 class="text-xl font-bold text-gray-800 border-b pb-3">Basic Information</h3>
                                
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Product Name *</label>
                                    <input type="text" name="name" value="<?= htmlspecialchars($product['name']) ?>" required 
                                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">SEO Slug (URL)</label>
                                    <div class="flex">
                                        <span class="inline-flex items-center px-4 py-3 bg-gray-100 border border-r-0 border-gray-300 rounded-l-xl text-gray-500">yoursite.com/</span>
                                        <input type="text" name="slug" value="<?= htmlspecialchars($product['slug'] ?? '') ?>" placeholder="fresh-strawberries"
                                               class="flex-1 px-4 py-3 border border-gray-300 rounded-r-xl focus:ring-2 focus:ring-green-500">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Category</label>
                                    <input type="text" name="category" value="<?= htmlspecialchars($product['category'] ?? '') ?>" 
                                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500">
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                                    <textarea name="description" rows="4" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
                                </div>
                            </div>

                            <!-- Pricing & Stock -->
                            <div class="space-y-6">
                                <h3 class="text-xl font-bold text-gray-800 border-b pb-3">Pricing & Inventory</h3>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Price per kg *</label>
                                        <input type="number" step="0.01" name="price_per_kg" value="<?= $product['price_per_kg'] ?>" required
                                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Discount %</label>
                                        <input type="number" step="0.01" max="90" name="discount_percent" value="<?= $product['discount_percent'] ?? 0 ?>"
                                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500">
                                    </div>
                                </div>

                                <div class="grid grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Unit</label>
                                        <select name="unit" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500">
                                            <option value="kg" <?= ($product['unit'] ?? 'kg') === 'kg' ? 'selected' : '' ?>>Per kg</option>
                                            <option value="piece" <?= ($product['unit'] ?? '') === 'piece' ? 'selected' : '' ?>>Per Piece</option>
                                            <option value="dozen" <?= ($product['unit'] ?? '') === 'dozen' ? 'selected' : '' ?>>Per Dozen</option>
                                            <option value="bunch" <?= ($product['unit'] ?? '') === 'bunch' ? 'selected' : '' ?>>Per Bunch</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Min Qty</label>
                                        <input type="number" step="0.25" name="min_quantity" value="<?= $product['min_quantity'] ?? 0.25 ?>"
                                               class="w-full px-4 py-3 border border-gray-300 rounded-xl">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Stock (kg)</label>
                                        <input type="number" step="0.01" name="stock" value="<?= $product['stock'] ?>" required
                                               class="w-full px-4 py-3 border border-gray-300 rounded-xl <?= $product['stock'] <= 5 ? 'bg-red-50 border-red-300' : '' ?>">
                                        <?php if ($product['stock'] <= 5): ?>
                                        <p class="text-xs text-red-600 mt-1">Low Stock Warning!</p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="flex gap-6">
                                    <label class="flex items-center gap-3 cursor-pointer">
                                        <input type="checkbox" name="is_featured" <?= ($product['is_featured'] ?? 0) ? 'checked' : '' ?> class="w-5 h-5 text-green-600 rounded focus:ring-green-500">
                                        <span class="font-medium">Featured Product</span>
                                    </label>
                                    <label class="flex items-center gap-3 cursor-pointer">
                                        <input type="checkbox" name="is_active" <?= ($product['is_active'] ?? 1) ? 'checked' : '' ?> class="w-5 h-5 text-green-600 rounded focus:ring-green-500">
                                        <span class="font-medium">Active (Visible)</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Image Upload -->
                        <div class="bg-gray-50 rounded-2xl p-8 text-center border-2 border-dashed border-gray-300">
                            <h3 class="text-xl font-bold text-gray-800 mb-4">Product Image</h3>
                            <?php if ($product['image']): ?>
                            <div class="mb-6">
                                <img src="../assets/images/uploads/<?= htmlspecialchars($product['image']) ?>" alt="Current" class="w-64 h-64 object-cover rounded-xl mx-auto shadow-lg">
                                <p class="text-sm text-gray-600 mt-3">Current Image</p>
                            </div>
                            <?php endif; ?>
                            <input type="file" name="image" accept="image/*" class="block w-full text-sm text-gray-500 file:mr-4 file:py-3 file:px-6 file:rounded-xl file:border-0 file:bg-green-600 file:text-white hover:file:bg-green-700">
                            <p class="text-sm text-gray-500 mt-3">Recommended: 800x800px • JPG, PNG, WebP</p>
                        </div>

                        <!-- SEO & Tags -->
                        <div>
                            <h3 class="text-xl font-bold text-gray-800 mb-4">SEO & Tags</h3>
                            <div class="grid md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Meta Title</label>
                                    <input type="text" name="meta_title" value="<?= htmlspecialchars($product['meta_title'] ?? '') ?>" placeholder="Fresh Strawberries - Best Price in Town"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-xl">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Tags (comma separated)</label>
                                    <input type="text" name="tags" value="<?= htmlspecialchars($product['tags'] ?? '') ?>" placeholder="organic, fresh, seasonal, sweet"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-xl">
                                </div>
                            </div>
                            <div class="mt-4">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Meta Description</label>
                                <textarea name="meta_description" rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-xl"><?= htmlspecialchars($product['meta_description'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-4 pt-8 border-t">
                            <button type="submit" class="flex-1 bg-gradient-to-r from-green-600 to-emerald-700 hover:from-green-700 hover:to-emerald-800 text-white font-bold py-4 rounded-xl text-lg transition shadow-lg">
                                Update Product
                            </button>
                            <a href="products.php" class="px-8 py-4 bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold rounded-xl transition">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>