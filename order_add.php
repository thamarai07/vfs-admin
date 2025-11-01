<?php
session_start();
require_once "config/db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$error = "";

// Fetch products for dropdown
$productsStmt = $conn->query("SELECT id, name, price_per_kg, stock FROM products ORDER BY name");
$products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch customers for dropdown
$customersStmt = $conn->query("SELECT id, name, phone FROM customers ORDER BY name");
$customers = $customersStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = trim($_POST['customer_name']);
    $customer_phone = trim($_POST['customer_phone']);
    $customer_address = trim($_POST['customer_address']);
    $discount = (float)$_POST['discount'];
    $delivery_charge = (float)$_POST['delivery_charge'];
    $payment_method = $_POST['payment_method'];
    $payment_status = $_POST['payment_status'];
    $status = $_POST['status'];
    $delivery_date = $_POST['delivery_date'] ?: null;
    $notes = trim($_POST['notes']);
    
    $product_ids = $_POST['product_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $prices = $_POST['price'] ?? [];
    
    if (empty($customer_name) || empty($product_ids)) {
        $error = "Customer name and at least one product are required!";
    } else {
        try {
            $conn->beginTransaction();
            
            // Generate order number
            $orderNumber = 'ORD' . date('Ymd') . rand(1000, 9999);
            
            // Calculate subtotal
            $subtotal = 0;
            foreach ($product_ids as $i => $pid) {
                if (!empty($pid) && $quantities[$i] > 0) {
                    $subtotal += $quantities[$i] * $prices[$i];
                }
            }
            
            $total = $subtotal - $discount + $delivery_charge;
            
            // Insert order
            $stmt = $conn->prepare("
                INSERT INTO orders (order_number, customer_name, customer_phone, customer_address, 
                                   total, discount, delivery_charge, status, payment_status, payment_method, 
                                   delivery_date, notes, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $orderNumber, $customer_name, $customer_phone, $customer_address,
                $total, $discount, $delivery_charge, $status, $payment_status, $payment_method,
                $delivery_date, $notes
            ]);
            
            $order_id = $conn->lastInsertId();
            
            // Insert order items
            $itemStmt = $conn->prepare("
                INSERT INTO order_items (order_id, product_id, product_name, quantity, price, subtotal) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($product_ids as $i => $pid) {
                if (!empty($pid) && $quantities[$i] > 0) {
                    // Get product name
                    $pStmt = $conn->prepare("SELECT name FROM products WHERE id = ?");
                    $pStmt->execute([$pid]);
                    $productName = $pStmt->fetchColumn();
                    
                    $itemSubtotal = $quantities[$i] * $prices[$i];
                    $itemStmt->execute([$order_id, $pid, $productName, $quantities[$i], $prices[$i], $itemSubtotal]);
                }
            }
            
            $conn->commit();
            header("Location: orders.php?msg=added");
            exit;
            
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Error creating order: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Order - VFS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen p-8">
        <div class="max-w-5xl mx-auto">
            <div class="bg-white rounded-2xl shadow-2xl p-8">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-3xl font-bold text-gray-800">Create New Order</h2>
                    <a href="orders.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-times text-2xl"></i>
                    </a>
                </div>

                <?php if ($error): ?>
                <div class="bg-red-100 text-red-700 p-4 rounded-lg mb-4">
                    <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>

                <form method="POST" id="orderForm">
                    <!-- Customer Information -->
                    <div class="mb-6">
                        <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                            <i class="fas fa-user text-blue-600"></i>
                            Customer Information
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-gray-700 font-semibold mb-2">Customer Name *</label>
                                <input type="text" name="customer_name" required 
                                       class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-green-500"
                                       placeholder="Enter customer name">
                            </div>
                            <div>
                                <label class="block text-gray-700 font-semibold mb-2">Phone *</label>
                                <input type="text" name="customer_phone" required 
                                       class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-green-500"
                                       placeholder="9876543210">
                            </div>
                            <div>
                                <label class="block text-gray-700 font-semibold mb-2">Address</label>
                                <input type="text" name="customer_address" 
                                       class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-green-500"
                                       placeholder="Delivery address">
                            </div>
                        </div>
                    </div>

                    <!-- Order Items -->
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                                <i class="fas fa-shopping-basket text-green-600"></i>
                                Order Items
                            </h3>
                            <button type="button" onclick="addRow()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition text-sm">
                                <i class="fas fa-plus mr-2"></i>Add Item
                            </button>
                        </div>
                        <div id="itemsContainer">
                            <div class="item-row grid grid-cols-12 gap-2 mb-2">
                                <div class="col-span-5">
                                    <select name="product_id[]" onchange="updatePrice(this)" class="w-full px-3 py-2 border rounded-lg" required>
                                        <option value="">Select Product</option>
                                        <?php foreach ($products as $p): ?>
                                        <option value="<?= $p['id'] ?>" data-price="<?= $p['price_per_kg'] ?>" data-stock="<?= $p['stock'] ?>">
                                            <?= htmlspecialchars($p['name']) ?> (Stock: <?= $p['stock'] ?>kg)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-span-2">
                                    <input type="number" step="0.01" name="quantity[]" placeholder="Qty (kg)" oninput="calculateRow(this)" 
                                           class="w-full px-3 py-2 border rounded-lg" required>
                                </div>
                                <div class="col-span-2">
                                    <input type="number" step="0.01" name="price[]" placeholder="Price" readonly 
                                           class="w-full px-3 py-2 border rounded-lg bg-gray-50">
                                </div>
                                <div class="col-span-2">
                                    <input type="number" step="0.01" name="subtotal[]" placeholder="Subtotal" readonly 
                                           class="w-full px-3 py-2 border rounded-lg bg-gray-50 font-bold">
                                </div>
                                <div class="col-span-1">
                                    <button type="button" onclick="removeRow(this)" class="w-full bg-red-500 text-white py-2 rounded-lg hover:bg-red-600">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Order Summary -->
                    <div class="mb-6 bg-gray-50 p-6 rounded-lg">
                        <h3 class="text-xl font-bold text-gray-800 mb-4">Order Summary</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 font-semibold mb-2">Discount (₹)</label>
                                <input type="number" step="0.01" name="discount" value="0" oninput="calculateTotal()" 
                                       class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-green-500">
                            </div>
                            <div>
                                <label class="block text-gray-700 font-semibold mb-2">Delivery Charge (₹)</label>
                                <input type="number" step="0.01" name="delivery_charge" value="0" oninput="calculateTotal()" 
                                       class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-green-500">
                            </div>
                        </div>
                        <div class="mt-4 p-4 bg-green-50 rounded-lg border-2 border-green-200">
                            <div class="flex justify-between items-center">
                                <span class="text-lg font-bold text-gray-800">Total Amount:</span>
                                <span id="totalDisplay" class="text-2xl font-black text-green-600">₹0.00</span>
                            </div>
                        </div>
                    </div>

                    <!-- Order Details -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">Status</label>
                            <select name="status" class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-green-500">
                                <option value="pending">Pending</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="processing">Processing</option>
                                <option value="delivered">Delivered</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">Payment Status</label>
                            <select name="payment_status" class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-green-500">
                                <option value="unpaid">Unpaid</option>
                                <option value="paid">Paid</option>
                                <option value="partial">Partial</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">Payment Method</label>
                            <select name="payment_method" class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-green-500">
                                <option value="cash">Cash</option>
                                <option value="online">Online</option>
                                <option value="upi">UPI</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2">Delivery Date</label>
                            <input type="date" name="delivery_date" 
                                   class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-green-500">
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="mb-6">
                        <label class="block text-gray-700 font-semibold mb-2">Notes</label>
                        <textarea name="notes" rows="3" 
                                  class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-green-500"
                                  placeholder="Order notes..."></textarea>
                    </div>

                    <!-- Buttons -->
                    <div class="flex gap-4">
                        <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-lg transition">
                            <i class="fas fa-check mr-2"></i>Create Order
                        </button>
                        <a href="orders.php" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-3 rounded-lg transition text-center">
                            <i class="fas fa-times mr-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function addRow() {
            const container = document.getElementById('itemsContainer');
            const firstRow = container.querySelector('.item-row');
            const newRow = firstRow.cloneNode(true);
            newRow.querySelectorAll('input, select').forEach(input => input.value = '');
            container.appendChild(newRow);
        }

        function removeRow(btn) {
            const container = document.getElementById('itemsContainer');
            if (container.querySelectorAll('.item-row').length > 1) {
                btn.closest('.item-row').remove();
                calculateTotal();
            }
        }

        function updatePrice(select) {
            const row = select.closest('.item-row');
            const option = select.options[select.selectedIndex];
            const price = option.getAttribute('data-price') || 0;
            row.querySelector('input[name="price[]"]').value = price;
            calculateRow(row.querySelector('input[name="quantity[]"]'));
        }

        function calculateRow(input) {
            const row = input.closest('.item-row');
            const qty = parseFloat(row.querySelector('input[name="quantity[]"]').value) || 0;
            const price = parseFloat(row.querySelector('input[name="price[]"]').value) || 0;
            const subtotal = qty * price;
            row.querySelector('input[name="subtotal[]"]').value = subtotal.toFixed(2);
            calculateTotal();
        }

        function calculateTotal() {
            let total = 0;
            document.querySelectorAll('input[name="subtotal[]"]').forEach(input => {
                total += parseFloat(input.value) || 0;
            });
            
            const discount = parseFloat(document.querySelector('input[name="discount"]').value) || 0;
            const delivery = parseFloat(document.querySelector('input[name="delivery_charge"]').value) || 0;
            
            total = total - discount + delivery;
            document.getElementById('totalDisplay').textContent = '₹' + total.toFixed(2);
        }
    </script>
</body>
</html>