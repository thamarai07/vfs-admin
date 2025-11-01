<?php
session_start();
require_once "config/db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $category = trim($_POST['category']);
    $price_per_kg = (float)$_POST['price_per_kg'];
    $stock = (int)$_POST['stock'];
    $description = trim($_POST['description']);
    
    // Handle image upload
    $imageName = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $imageName = 'product_' . time() . '.' . $ext;
            $uploadPath = 'assets/images/uploads/' . $imageName;
            
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                $error = "Failed to upload image";
            }
        } else {
            $error = "Invalid image format";
        }
    }
    
    if (empty($error)) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO products (name, category, price, price_per_kg, stock, image, description, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$name, $category, $price_per_kg, $price_per_kg, $stock, $imageName, $description]);
            
            header("Location: products.php?msg=added");
            exit;
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
    <title>Add Product - VFS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-2xl">
            <div class="bg-white rounded-2xl shadow-2xl p-8">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-3xl font-bold text-gray-800">Add New Product</h2>
                    <a href="products.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-times text-2xl"></i>
                    </a>
                </div>

                <?php if ($error): ?>
                <div class="bg-red-100 text-red-700 p-4 rounded-lg mb-4">
                    <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <!-- Product Name -->
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">
                            <i class="fas fa-box mr-2 text-green-600"></i>Product Name *
                        </label>
                        <input type="text" name="name" required 
                               class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-green-500"
                               placeholder="e.g., Fresh Mango">
                    </div>

                    <!-- Category & Price -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">
                                <i class="fas fa-tag mr-2 text-blue-600"></i>Category
                            </label>
                            <input type="text" name="category" 
                                   class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-green-500"
                                   placeholder="e.g., Tropical Fruits">
                        </div>

                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">
                                <i class="fas fa-rupee-sign mr-2 text-green-600"></i>Price per kg *
                            </label>
                            <input type="number" step="0.01" name="price_per_kg" required 
                                   class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-green-500"
                                   placeholder="120.00">
                        </div>
                    </div>

                    <!-- Stock -->
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">
                            <i class="fas fa-layer-group mr-2 text-purple-600"></i>Stock (kg) *
                        </label>
                        <input type="number" name="stock" required 
                               class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-green-500"
                               placeholder="50">
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">
                            <i class="fas fa-align-left mr-2 text-gray-600"></i>Description
                        </label>
                        <textarea name="description" rows="3" 
                                  class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-green-500"
                                  placeholder="Product description..."></textarea>
                    </div>

                    <!-- Image Upload -->
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">
                            <i class="fas fa-image mr-2 text-orange-600"></i>Product Image
                        </label>
                        <input type="file" name="image" accept="image/*" 
                               class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-green-500">
                        <p class="text-xs text-gray-500 mt-1">Supported: JPG, PNG, GIF, WEBP</p>
                    </div>

                    <!-- Buttons -->
                    <div class="flex gap-4 pt-4">
                        <button type="submit" 
                                class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-lg transition">
                            <i class="fas fa-check mr-2"></i>Add Product
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