<?php
/**
 * Update Order Status API - PDO Version
 * File: api/update_order_status.php
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!isset($data['order_id']) || !isset($data['field']) || !isset($data['value'])) {
        throw new Exception('Missing required fields');
    }
    
    $order_id = intval($data['order_id']);
    $field = $data['field'];
    $value = $data['value'];
    $notes = isset($data['notes']) ? trim($data['notes']) : '';
    
    // Validate field
    $allowed_fields = ['status', 'payment_status'];
    if (!in_array($field, $allowed_fields)) {
        throw new Exception('Invalid field');
    }
    
    // Validate values
    $valid_status = ['pending', 'confirmed', 'processing', 'delivered'];
    $valid_payment = ['unpaid', 'paid', 'partial'];
    
    if ($field === 'status' && !in_array($value, $valid_status)) {
        throw new Exception('Invalid status value');
    }
    
    if ($field === 'payment_status' && !in_array($value, $valid_payment)) {
        throw new Exception('Invalid payment status value');
    }
    
    // Get current value
    $current_query = "SELECT $field FROM orders WHERE id = :order_id";
    $current_stmt = $conn->prepare($current_query);
    $current_stmt->bindValue(':order_id', $order_id, PDO::PARAM_INT);
    $current_stmt->execute();
    $result = $current_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        throw new Exception('Order not found');
    }
    
    $old_value = $result[$field];
    
    // Start transaction
    $conn->beginTransaction();
    
    // Update order
    $update_query = "UPDATE orders SET $field = :value, updated_at = NOW() WHERE id = :order_id";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bindValue(':value', $value);
    $update_stmt->bindValue(':order_id', $order_id, PDO::PARAM_INT);
    $update_stmt->execute();
    
    // Log status change if status field
    if ($field === 'status') {
        $history_query = "
            INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, notes, created_at)
            VALUES (:order_id, :old_status, :new_status, :changed_by, :notes, NOW())
        ";
        $history_stmt = $conn->prepare($history_query);
        $admin_id = $_SESSION['admin_id'] ?? 0;
        $history_stmt->bindValue(':order_id', $order_id, PDO::PARAM_INT);
        $history_stmt->bindValue(':old_status', $old_value);
        $history_stmt->bindValue(':new_status', $value);
        $history_stmt->bindValue(':changed_by', $admin_id, PDO::PARAM_INT);
        $history_stmt->bindValue(':notes', $notes);
        $history_stmt->execute();
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => ucfirst($field) . ' updated successfully',
        'data' => [
            'order_id' => $order_id,
            'field' => $field,
            'old_value' => $old_value,
            'new_value' => $value
        ]
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>