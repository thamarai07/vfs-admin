<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST method allowed']);
    exit();
}

require_once('../config/db.php');

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    $customerId = isset($data['customerId']) ? intval($data['customerId']) : null;
    $orderId = isset($data['orderId']) ? intval($data['orderId']) : null;
    
    if (!$customerId) {
        throw new Exception('Customer ID is required');
    }
    
    // 🔥 TRY BOTH: Real session AND hardcoded user_1
    $sessionIds = [
        'user_' . $customerId,  // Try actual user session (user_8)
        'user_1'                // Try hardcoded session (user_1)
    ];
    
    error_log("🔍 Trying sessions: " . implode(', ', $sessionIds));
    
    $conn->beginTransaction();
    
    $totalMarked = 0;
    $usedSessionId = null;
    
    // Try each session ID
    foreach ($sessionIds as $sessionId) {
        // Check if items exist for this session
        $checkStmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM cart 
            WHERE session_id = ? 
            AND status = 'active'
        ");
        $checkStmt->execute([$sessionId]);
        $count = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        error_log("📊 Found $count active items for session: $sessionId");
        
        if ($count > 0) {
            // Found items! Update them
            $updateStmt = $conn->prepare("
                UPDATE cart 
                SET 
                    status = 'ordered',
                    order_id = ?,
                    updated_at = NOW()
                WHERE session_id = ? 
                AND status = 'active'
            ");
            
            $updateStmt->execute([$orderId, $sessionId]);
            $markedCount = $updateStmt->rowCount();
            
            $totalMarked += $markedCount;
            $usedSessionId = $sessionId;
            
            error_log("✅ Marked $markedCount items as 'ordered' for session: $sessionId");
            
            break; // Stop after finding items
        }
    }
    
    $conn->commit();
    
    if ($totalMarked > 0) {
        echo json_encode([
            'success' => true,
            'message' => "Successfully marked $totalMarked items as ordered",
            'data' => [
                'markedItems' => $totalMarked,
                'sessionId' => $usedSessionId,
                'orderId' => $orderId,
                'customerId' => $customerId,
                'note' => $usedSessionId === 'user_1' ? 'Using legacy session (user_1)' : 'Using actual user session'
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'No active cart items found',
            'data' => [
                'markedItems' => 0,
                'triedSessions' => $sessionIds,
                'note' => 'Cart was already empty or items already processed'
            ]
        ]);
    }
    
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("❌ ERROR: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>