<?php
require_once __DIR__ . '/../config/cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$order_id = isset($data['order_id']) ? intval($data['order_id']) : null;
$customer_id = isset($data['customer_id']) ? intval($data['customer_id']) : null;
$reason = isset($data['reason']) ? $data['reason'] : 'Customer requested cancellation';

if (!$order_id || !$customer_id) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Order ID and Customer ID are required'
    ]);
    exit;
}

try {
    // Verify order belongs to customer and can be cancelled
    $checkQuery = "
        SELECT status, payment_status, total 
        FROM orders 
        WHERE id = :order_id AND customer_id = :customer_id
    ";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bindParam(':order_id', $order_id);
    $checkStmt->bindParam(':customer_id', $customer_id);
    $checkStmt->execute();
    $order = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Order not found or does not belong to you'
        ]);
        exit;
    }

    // Check if order can be cancelled (only pending, confirmed, processing)
    $cancellableStatuses = ['pending', 'confirmed', 'processing'];
    if (!in_array($order['status'], $cancellableStatuses)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Order cannot be cancelled at this stage'
        ]);
        exit;
    }

    // 🔥 START TRANSACTION HERE - Before any database modifications
    $conn->beginTransaction();

    $old_status = $order['status'];

    // Update order status to cancelled
    $updateQuery = "
        UPDATE orders 
        SET status = 'cancelled',
            updated_at = CURRENT_TIMESTAMP,
            notes = CONCAT(COALESCE(notes, ''), '\nCancellation Reason: ', :reason)
        WHERE id = :order_id
    ";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bindParam(':order_id', $order_id);
    $updateStmt->bindParam(':reason', $reason);
    $updateStmt->execute();

    // Add to order status history
    $historyQuery = "
        INSERT INTO order_status_history 
        (order_id, old_status, new_status, changed_by, notes)
        VALUES 
        (:order_id, :old_status, 'cancelled', :customer_id, :reason)
    ";
    $historyStmt = $conn->prepare($historyQuery);
    $historyStmt->bindParam(':order_id', $order_id);
    $historyStmt->bindParam(':old_status', $old_status);
    $historyStmt->bindParam(':customer_id', $customer_id);
    $historyStmt->bindParam(':reason', $reason);
    $historyStmt->execute();

    // If payment was made, initiate refund
    if ($order['payment_status'] === 'paid') {
        // Update payment status to refunded
        $refundQuery = "UPDATE orders SET payment_status = 'refunded' WHERE id = :order_id";
        $refundStmt = $conn->prepare($refundQuery);
        $refundStmt->bindParam(':order_id', $order_id);
        $refundStmt->execute();

        // TODO: Integrate with actual payment gateway for refund
        // Example: initiateRefund($order_id, $order['total']);
    }

    // Restore product quantities (if you track inventory)
    // Check if stock column exists first
    $itemsQuery = "SELECT product_id, quantity FROM order_items WHERE order_id = :order_id";
    $itemsStmt = $conn->prepare($itemsQuery);
    $itemsStmt->bindParam(':order_id', $order_id);
    $itemsStmt->execute();
    
    while ($item = $itemsStmt->fetch(PDO::FETCH_ASSOC)) {
        // Check if stock column exists in products table
        $checkStockColumn = "SHOW COLUMNS FROM products LIKE 'stock'";
        $stockCheck = $conn->query($checkStockColumn);
        
        if ($stockCheck && $stockCheck->rowCount() > 0) {
            $restoreQuery = "
                UPDATE products 
                SET stock = stock + :quantity 
                WHERE id = :product_id
            ";
            $restoreStmt = $conn->prepare($restoreQuery);
            $restoreStmt->bindParam(':quantity', $item['quantity']);
            $restoreStmt->bindParam(':product_id', $item['product_id']);
            $restoreStmt->execute();
        }
    }

    // 🔥 COMMIT TRANSACTION - All operations successful
    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Order cancelled successfully',
        'refund_initiated' => $order['payment_status'] === 'paid'
    ]);

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("cancel_order.php DB Error: " . $e->getMessage());
    echo json_encode([
        'status'  => 'error',
        'message' => 'An error occurred while cancelling the order. Please try again.'
    ]);
}
?>