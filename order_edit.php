<?php
/**
 * Order Edit - PDO Version
 * File: order_edit.php
 */

session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

require_once('config/db.php');

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = '';
$message_type = '';

if ($order_id === 0) {
    header('Location: orders.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order'])) {
    $new_status = $_POST['status'];
    $new_payment_status = $_POST['payment_status'];
    $delivery_date = $_POST['delivery_date'];
    $notes = $_POST['notes'];
    $admin_notes = $_POST['admin_notes'];
    
    // Get current status
    $current_query = "SELECT status, payment_status FROM orders WHERE id = :order_id";
    $current_stmt = $conn->prepare($current_query);
    $current_stmt->bindValue(':order_id', $order_id, PDO::PARAM_INT);
    $current_stmt->execute();
    $current = $current_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Start transaction
    $conn->beginTransaction();
    
    try {
        // Update order
        $update_query = "
            UPDATE orders 
            SET status = :status, 
                payment_status = :payment_status, 
                delivery_date = :delivery_date, 
                notes = :notes,
                updated_at = NOW()
            WHERE id = :order_id
        ";
        
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bindValue(':status', $new_status);
        $update_stmt->bindValue(':payment_status', $new_payment_status);
        $update_stmt->bindValue(':delivery_date', $delivery_date);
        $update_stmt->bindValue(':notes', $notes);
        $update_stmt->bindValue(':order_id', $order_id, PDO::PARAM_INT);
        $update_stmt->execute();
        
        // Log status change if status changed
        if ($current['status'] !== $new_status) {
            $history_query = "
                INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, notes, created_at)
                VALUES (:order_id, :old_status, :new_status, :changed_by, :notes, NOW())
            ";
            $history_stmt = $conn->prepare($history_query);
            $admin_id = $_SESSION['admin_id'] ?? 0;
            $history_stmt->bindValue(':order_id', $order_id, PDO::PARAM_INT);
            $history_stmt->bindValue(':old_status', $current['status']);
            $history_stmt->bindValue(':new_status', $new_status);
            $history_stmt->bindValue(':changed_by', $admin_id, PDO::PARAM_INT);
            $history_stmt->bindValue(':notes', $admin_notes);
            $history_stmt->execute();
        }
        
        // Commit transaction
        $conn->commit();
        
        $message = 'Order updated successfully!';
        $message_type = 'success';
        
    } catch (Exception $e) {
        $conn->rollBack();
        $message = 'Error updating order: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// Get order details
$query = "SELECT * FROM orders WHERE id = :order_id";
$stmt = $conn->prepare($query);
$stmt->bindValue(':order_id', $order_id, PDO::PARAM_INT);
$stmt->execute();
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: orders.php');
    exit();
}

// Get order items
$items_query = "SELECT * FROM order_items WHERE order_id = :order_id";
$items_stmt = $conn->prepare($items_query);
$items_stmt->bindValue(':order_id', $order_id, PDO::PARAM_INT);
$items_stmt->execute();
$items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Edit Order - " . $order['order_number'];
include('includes/header.php');
?>

<style>
    /* Custom CSS - Same style as orders.php */
    .container-fluid { width: 100%; padding: 20px; margin: 0 auto; }
    .row { display: flex; flex-wrap: wrap; margin: -15px; }
    .col-12 { flex: 0 0 100%; max-width: 100%; padding: 15px; }
    .col-lg-8 { flex: 0 0 66.666%; max-width: 66.666%; padding: 15px; }
    .col-lg-4 { flex: 0 0 33.333%; max-width: 33.333%; padding: 15px; }
    .col-md-6 { flex: 0 0 50%; max-width: 50%; padding: 15px; }

    .mb-3 { margin-bottom: 20px; }
    .mb-4 { margin-bottom: 30px; }
    .mt-4 { margin-top: 30px; }
    .me-2 { margin-right: 8px; }
    .my-2 { margin-top: 10px; margin-bottom: 10px; }

    .card {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    .card-header { padding: 15px 20px; border-bottom: 1px solid #eee; background: #f8f9fa; }
    .card-body { padding: 20px; }

    .form-label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; }
    .form-control, .form-select {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 14px;
    }
    .form-control:disabled { background: #f0f0f0; }

    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        display: inline-block;
        text-decoration: none;
        text-align: center;
    }
    .btn-primary { background: #007bff; color: #fff; }
    .btn-secondary { background: #6c757d; color: #fff; }
    .btn-outline-secondary { background: transparent; border: 1px solid #6c757d; color: #6c757d; }
    .btn-sm { padding: 6px 12px; font-size: 13px; }
    .w-100 { width: 100%; }

    .text-sm { font-size: 14px; }
    .text-secondary { color: #6c757d; }
    .text-danger { color: #dc3545; }
    .font-weight-bold { font-weight: 700; }

    .table { width: 100%; border-collapse: collapse; }
    .table th, .table td {
        padding: 10px;
        border-bottom: 1px solid #eee;
        text-align: left;
    }
    .table thead th { background: #f8f9fa; font-weight: 600; }
    .table-sm th, .table-sm td { padding: 8px; }
    .text-center { text-align: center; }
    .text-end { text-align: right; }

    .d-flex { display: flex; }
    .justify-content-between { justify-content: space-between; }

    .alert {
        padding: 15px;
        border-radius: 6px;
        margin-bottom: 20px;
        position: relative;
    }
    .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .btn-close {
        position: absolute;
        top: 10px;
        right: 15px;
        background: none;
        border: none;
        font-size: 20px;
        cursor: pointer;
    }

    hr { border: 0; border-top: 1px solid #eee; }

    small.text-muted { color: #6c757d; font-size: 12px; }

    @media (max-width: 992px) {
        .col-lg-8, .col-lg-4 { flex: 0 0 100%; max-width: 100%; }
    }
</style>

<div class="container-fluid">
    <!-- Back Button -->
    <div class="row mb-3">
        <div class="col-12">
            <a href="order_view.php?id=<?= $order_id ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-2"></i> Back to Order Details
            </a>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="row mb-3">
        <div class="col-12">
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">×</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <form method="POST" action="order_edit.php?id=<?= $order_id ?>">
        <div class="row">
            <!-- Left Column -->
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h6>Edit Order #<?= htmlspecialchars($order['order_number']) ?></h6>
                    </div>
                    <div class="card-body">
                        <!-- Order Status -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Order Status *</label>
                                <select class="form-select" name="status" required>
                                    <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="confirmed" <?= $order['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                    <option value="processing" <?= $order['status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
                                    <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Payment Status *</label>
                                <select class="form-select" name="payment_status" required>
                                    <option value="unpaid" <?= $order['payment_status'] === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                                    <option value="paid" <?= $order['payment_status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                                    <option value="partial" <?= $order['payment_status'] === 'partial' ? 'selected' : '' ?>>Partial</option>
                                </select>
                            </div>
                        </div>

                        <!-- Delivery Date -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Expected Delivery Date</label>
                                <input type="date" class="form-control" name="delivery_date" value="<?= $order['delivery_date'] ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Payment Method</label>
                                <input type="text" class="form-control" value="<?= ucfirst($order['payment_method']) ?>" disabled>
                            </div>
                        </div>

                        <!-- Customer Notes -->
                        <div class="mb-3">
                            <label class="form-label">Customer Notes</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="Add customer notes..."><?= htmlspecialchars($order['notes']) ?></textarea>
                        </div>

                        <!-- Admin Notes for Status Change -->
                        <div class="mb-3">
                            <label class="form-label">Admin Notes (Status Change)</label>
                            <textarea class="form-control" name="admin_notes" rows="2" placeholder="Add notes for this status change..."></textarea>
                            <small class="text-muted">This will be recorded in order history</small>
                        </div>

                        <!-- Order Items (Read-only) -->
                        <div class="mt-4">
                            <h6 class="text-sm font-weight-bold mb-3">Order Items</h6>
                            <div style="overflow-x: auto;">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th class="text-center">Quantity</th>
                                            <th class="text-center">Price</th>
                                            <th class="text-end">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['product_name']) ?></td>
                                            <td class="text-center"><?= $item['quantity'] ?> kg</td>
                                            <td class="text-center">₹<?= number_format($item['price'], 2) ?></td>
                                            <td class="text-end">₹<?= number_format($item['subtotal'], 2) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                            <td class="text-end"><strong>₹<?= number_format($order['total_amount'], 2) ?></strong></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-lg-4">
                <!-- Customer Info -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6>Customer Information</h6>
                    </div>
                    <div class="card-body">
                        <p class="text-sm mb-2"><strong>Name:</strong> <?= htmlspecialchars($order['customer_name'] ?? 'N/A') ?></p>
                        <p class="text-sm mb-2"><strong>Phone:</strong> <?= htmlspecialchars($order['customer_phone'] ?? 'N/A') ?></p>
                        <p class="text-sm mb-2"><strong>Address:</strong></p>
                        <p class="text-sm text-secondary"><?= nl2br(htmlspecialchars($order['customer_address'] ?? 'N/A')) ?></p>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6>Order Summary</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-sm">Subtotal:</span>
                            <span class="text-sm">₹<?= number_format($order['subtotal'], 2) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-sm">Tax:</span>
                            <span class="text-sm">₹<?= number_format($order['tax'], 2) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-sm">Shipping:</span>
                            <span class="text-sm">₹<?= number_format($order['shipping_charge'], 2) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-sm">Discount:</span>
                            <span class="text-sm text-danger">-₹<?= number_format($order['discount'] ?? 0, 2) ?></span>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between">
                            <strong>Total:</strong>
                            <strong>₹<?= number_format($order['total_amount'], 2) ?></strong>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="card">
                    <div class="card-body">
                        <button type="submit" name="update_order" class="btn btn-primary w-100 mb-2">
                            <i class="fas fa-save me-2"></i> Update Order
                        </button>
                        <a href="order_view.php?id=<?= $order_id ?>" class="btn btn-secondary w-100">
                            <i class="fas fa-times me-2"></i> Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Confirmation Dialog -->
<script>
document.querySelector('form').addEventListener('submit', function(e) {
    const confirmed = confirm('Are you sure you want to update this order?');
    if (!confirmed) {
        e.preventDefault();
    }
});
</script>

<?php include('includes/footer.php'); ?>