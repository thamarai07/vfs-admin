<?php
session_start();
require_once "config/db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$error = "";

// Fetch product
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header("Location: products.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $category = trim($_POST['category']);
    $price_per_kg = (float)$_POST['price_per_kg'];
    $stock = (int)$_POST['stock'];
    $description = trim($_POST['description']);
    
    $imageName = $product['image'];
    
    // Handle new image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $imageName = 'product_' . time() . '.' . $ext;
            $uploadPath = 'assets/images/uploads/' . $imageName;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                // Delete old image
                if ($product['image'] && file_exists('assets/images/uploads/' . $product['image'])) {
                    unlink('assets/images/uploads/' . $product['image']);
                }
            }
        }
    }
    
    try {
        $stmt = $conn->prepare("
            UPDATE products 
            SET name = ?, category = ?, price = ?, price_per_kg = ?, stock = ?, image = ?, description = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$name, $category, $price_per_kg, $price_per_kg, $stock, $imageName, $description, $id]);
        
        header("Location: products.php?msg=updated");
        exit;
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - VFS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-2xl">
            <div class="bg-white rounded-2xl shadow-2xl p-8">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-3xl font-bold text-gray-800">Edit Product</h2>
                    <a href="products.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-times text-2xl"></i>
                    </a>
                </div>

                <?php if ($error): ?>
                <div class="bg-red-100 text-red-700 p-4 rounded-lg mb-4">
                    <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>

                <!-- Current Image -->
                <?php if ($product['image']): ?>
                <div class="mb-4 text-center">
                    <p class="text-sm text-gray-600 mb-2">Current Image:</p>
                    <img src="assets/images/uploads/<?= htmlspecialchars($product['image']) ?>" 
                         alt="Current" 
                         class="w-32 h-32 object-cover rounded-lg mx-auto border-2 border-gray-200">
                </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Product Name *</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($product['name']) ?>" required 
                               class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-green-500">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">Category</label>
                            <input type="text" name="category" value="<?= htmlspecialchars($product['category']) ?>" 
                                   class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-green-500">
                        </div>

                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">Price per kg *</label>
                            <input type="number" step="0.01" name="price_per_kg" value="<?= $product['price_per_kg'] ?>" required 
                                   class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-green-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Stock (kg) *</label>
                        <input type="number" name="stock" value="<?= $product['stock'] ?>" required 
                               class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-green-500">
                    </div>

                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Description</label>
                        <textarea name="description" rows="3" 
                                  class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-green-500"><?= htmlspecialchars($product['description']) ?></textarea>
                    </div>

                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">New Image (optional)</label>
                        <input type="file" name="image" accept="image/*" 
                               class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-green-500">
                        <p class="text-xs text-gray-500 mt-1">Leave empty to keep current image</p>
                    </div>

                    <div class="flex gap-4 pt-4">
                        <button type="submit" 
                                class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition">
                            <i class="fas fa-save mr-2"></i>Update Product
                        </button>
                        <a href="products.php" 
                           class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-3 rounded-lg transition text-center">
                            <i class="fas fa-times mr-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>