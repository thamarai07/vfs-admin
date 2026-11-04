<?php
/**
 * Stock helpers shared by every place that can cancel an order
 * (customer cancel API, admin order edit, admin status API).
 *
 * create_order.php reduces the kg stock and sets orders.stock_deducted = 1.
 * restoreOrderStock() gives exactly that stock back once, then clears the flag,
 * so cancelling twice — or cancelling an old order that never reduced stock —
 * can't inflate stock. Piece lines never touch the kg stock.
 *
 * Call it inside the same transaction that changes the order status.
 */

if (!function_exists('restoreOrderStock')) {
    function restoreOrderStock(PDO $conn, int $orderId): bool
    {
        try {
            if (!$conn->query("SHOW COLUMNS FROM orders LIKE 'stock_deducted'")->fetch()) {
                return false; // migration not run yet — nothing was deducted
            }
        } catch (Throwable $e) {
            return false;
        }

        $flag = $conn->prepare("SELECT stock_deducted FROM orders WHERE id = ? FOR UPDATE");
        $flag->execute([$orderId]);
        if ((int) $flag->fetchColumn() !== 1) {
            return false;
        }

        $items = $conn->prepare("SELECT product_id, quantity, unit FROM order_items WHERE order_id = ?");
        $items->execute([$orderId]);
        $restore = $conn->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
        foreach ($items->fetchAll(PDO::FETCH_ASSOC) as $item) {
            if (strtolower((string) ($item['unit'] ?? '')) === 'piece') {
                continue;
            }
            $restore->execute([$item['quantity'], $item['product_id']]);
        }

        $conn->prepare("UPDATE orders SET stock_deducted = 0 WHERE id = ?")->execute([$orderId]);
        return true;
    }
}
