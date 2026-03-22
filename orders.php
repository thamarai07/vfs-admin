<?php
/**
 * Orders Management - Main Listing Page (PDO Version)
 * File: orders.php
 */

session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

require_once('config/db.php');

$page_title = "Order Management";

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$payment_filter = isset($_GET['payment']) ? $_GET['payment'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "o.status = :status";
    $params[':status'] = $status_filter;
}

if ($payment_filter) {
    $where_conditions[] = "o.payment_status = :payment_status";
    $params[':payment_status'] = $payment_filter;
}

if ($search) {
    $where_conditions[] = "(o.order_number LIKE :search1 OR o.customer_name LIKE :search2 OR o.customer_phone LIKE :search3)";
    $search_term = "%$search%";
    $params[':search1'] = $search_term;
    $params[':search2'] = $search_term;
    $params[':search3'] = $search_term;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_query = "SELECT COUNT(*) as total FROM orders o $where_clause";
$count_stmt = $conn->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_orders = $count_stmt->fetchColumn();
$total_pages = ceil($total_orders / $limit);

// Get orders
$query = "
    SELECT 
        o.id,
        o.order_number,
        o.customer_id,
        o.customer_name,
        o.customer_phone,
        o.customer_address,
        o.total_amount,
        o.subtotal,
        o.tax,
        o.shipping_charge,
        o.status,
        o.payment_status,
        o.payment_method,
        o.delivery_date,
        o.notes,
        o.created_at,
        COUNT(oi.id) as item_count
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    $where_clause
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_orders,
        SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_orders,
        SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
        SUM(CASE WHEN payment_status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_orders,
        SUM(total_amount) as total_revenue
    FROM orders
";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch(PDO::FETCH_ASSOC);

include('includes/header.php');
?>

<style>
    /* Custom CSS to replace Tailwind */
    .container-fluid { width: 100%; padding: 20px; margin: 0 auto; }
    .row { display: flex; flex-wrap: wrap; margin: -15px; }
    .col-12 { flex: 0 0 100%; max-width: 100%; padding: 15px; }
    .col-md-2 { flex: 0 0 16.666%; max-width: 16.666%; padding: 15px; }
    .col-md-3 { flex: 0 0 25%; max-width: 25%; padding: 15px; }
    .col-xl-3 { flex: 0 0 25%; max-width: 25%; padding: 15px; }
    .col-sm-6 { flex: 0 0 50%; max-width: 50%; padding: 15px; }

    .mb-4 { margin-bottom: 30px; }
    .mt-4 { margin-top: 30px; }
    .py-4 { padding-top: 30px; padding-bottom: 30px; }
    .px-0 { padding-left: 0; padding-right: 0; }
    .pt-0 { padding-top: 0; }
    .pb-0 { padding-bottom: 0; }
    .pb-2 { padding-bottom: 15px; }

    .card {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    .card-header { padding: 15px 20px; border-bottom: 1px solid #eee; }
    .card-body { padding: 20px; }

    .form-control, .form-select {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 14px;
    }
    .form-label { display: block; margin-bottom: 8px; font-weight: 600; }

    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        display: inline-block;
        text-align: center;
    }
    .btn-primary { background: #007bff; color: #fff; }
    .btn-secondary { background: #6c757d; color: #fff; }
    .btn-link { background: none; padding: 5px; }

    .w-100 { width: 100%; }

    .text-center { text-align: center; }
    .text-end { text-align: right; }
    .text-sm { font-size: 14px; }
    .text-xs { font-size: 12px; }
    .text-uppercase { text-transform: uppercase; }
    .font-weight-bold { font-weight: 700; }
    .font-weight-bolder { font-weight: 800; }

    .text-secondary { color: #6c757d; }
    .text-primary { color: #007bff; }
    .text-success { color: #28a745; }
    .text-warning { color: #ffc107; }

    .badge {
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    .bg-gradient-primary { background: linear-gradient(87deg, #5e72e4, #825ee4); color: #fff; }
    .bg-gradient-success { background: linear-gradient(87deg, #11cdef, #1171ef); color: #fff; }
    .bg-gradient-warning { background: linear-gradient(87deg, #fb6340, #f5365c); color: #fff; }
    .bg-gradient-danger { background: linear-gradient(87deg, #f5365c, #f56036); color: #fff; }
    .bg-gradient-info { background: linear-gradient(87deg, #1171ef, #11cdef); color: #fff; }

    .table {
        width: 100%;
        border-collapse: collapse;
        margin: 0;
    }
    .table th, .table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #eee;
    }
    .table thead th {
        background: #f8f9fa;
        font-size: 12px;
        color: #8898aa;
        letter-spacing: 1px;
    }

    .pagination { display: flex; justify-content: center; list-style: none; padding: 0; }
    .pagination li {
        margin: 0 5px;
    }
    .pagination a {
        padding: 8px 16px;
        border: 1px solid #ddd;
        border-radius: 6px;
        text-decoration: none;
        color: #007bff;
    }
    .pagination .active a {
        background: #007bff;
        color: #fff;
        border-color: #007bff;
    }

    .icon-shape {
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
    }
    .shadow-primary { box-shadow: 0 4px 15px rgba(94, 114, 228, 0.3); }
    .shadow-success { box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3); }
    .shadow-warning { box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3); }
    .shadow-danger { box-shadow: 0 4px 15px rgba(245, 54, 92, 0.3); }

    .g-3 > * { margin-bottom: 15px; }
</style>

<div class="container-fluid">
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-sm-6">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-8">
                            <div>
                                <p class="text-sm mb-0 text-uppercase font-weight-bold">Total Orders</p>
                                <h5 class="font-weight-bolder mb-0"><?= number_format($stats['total_orders']) ?></h5>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon-shape bg-gradient-primary shadow-primary">
                                <i class="ni ni-cart" style="font-size: 20px; color: white;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-8">
                            <div>
                                <p class="text-sm mb-0 text-uppercase font-weight-bold">Pending</p>
                                <h5 class="font-weight-bolder mb-0 text-warning"><?= number_format($stats['pending_orders']) ?></h5>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon-shape bg-gradient-warning shadow-warning">
                                <i class="ni ni-time-alarm" style="font-size: 20px; color: white;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-8">
                            <div>
                                <p class="text-sm mb-0 text-uppercase font-weight-bold">Delivered</p>
                                <h5 class="font-weight-bolder mb-0 text-success"><?= number_format($stats['delivered_orders']) ?></h5>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon-shape bg-gradient-success shadow-success">
                                <i class="ni ni-check-bold" style="font-size: 20px; color: white;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-8">
                            <div>
                                <p class="text-sm mb-0 text-uppercase font-weight-bold">Revenue</p>
                                <h5 class="font-weight-bolder mb-0">₹<?= number_format($stats['total_revenue'], 2) ?></h5>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon-shape bg-gradient-danger shadow-danger">
                                <i class="ni ni-money-coins" style="font-size: 20px; color: white;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="orders.php" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" placeholder="Order #, Customer Name, Phone" value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Order Status</label>
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                <option value="processing" <?= $status_filter === 'processing' ? 'selected' : '' ?>>Processing</option>
                                <option value="delivered" <?= $status_filter === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Payment Status</label>
                            <select class="form-select" name="payment">
                                <option value="">All Payments</option>
                                <option value="unpaid" <?= $payment_filter === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                                <option value="paid" <?= $payment_filter === 'paid' ? 'selected' : '' ?>>Paid</option>
                                <option value="partial" <?= $payment_filter === 'partial' ? 'selected' : '' ?>>Partial</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <a href="orders.php" class="btn btn-secondary w-100">Reset</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header pb-0">
                    <h6>All Orders (<?= number_format($total_orders) ?>)</h6>
                </div>
                <div class="card-body px-0 pt-0 pb-2">
                    <div style="overflow-x: auto;">
                        <table class="table align-items-center mb-0">
                            <thead>
                                <tr>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Order #</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Customer</th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Items</th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Amount</th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Payment</th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Status</th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Date</th>
                                    <th class="text-secondary opacity-7">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>
                                        <div style="padding: 10px;">
                                            <h6 style="margin: 0; font-size: 15px;"><?= htmlspecialchars($order['order_number']) ?></h6>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <h6 style="margin: 0; font-size: 15px;"><?= htmlspecialchars($order['customer_name'] ?? 'N/A') ?></h6>
                                            <p style="margin: 5px 0 0; font-size: 12px; color: #6c757d;"><?= htmlspecialchars($order['customer_phone'] ?? 'N/A') ?></p>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-gradient-info"><?= $order['item_count'] ?> items</span>
                                    </td>
                                    <td class="text-center">
                                        <span style="font-weight: bold; color: #6c757d;">₹<?= number_format($order['total_amount'], 2) ?></span>
                                        <p style="margin: 5px 0 0; font-size: 12px; color: #6c757d;"><?= ucfirst($order['payment_method']) ?></p>
                                    </td>
                                    <td class="text-center">
                                        <?php 
                                        $payment_badge_class = [
                                            'unpaid' => 'bg-gradient-warning',
                                            'paid' => 'bg-gradient-success',
                                            'partial' => 'bg-gradient-info'
                                        ];
                                        $badge_class = $payment_badge_class[$order['payment_status']] ?? 'bg-gradient-secondary';
                                        ?>
                                        <span class="badge <?= $badge_class ?>"><?= ucfirst($order['payment_status']) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php 
                                        $status_badge_class = [
                                            'pending' => 'bg-gradient-warning',
                                            'confirmed' => 'bg-gradient-info',
                                            'processing' => 'bg-gradient-primary',
                                            'delivered' => 'bg-gradient-success'
                                        ];
                                        $badge_class = $status_badge_class[$order['status']] ?? 'bg-gradient-secondary';
                                        ?>
                                        <span class="badge <?= $badge_class ?>"><?= ucfirst($order['status']) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span style="font-weight: bold; color: #6c757d;"><?= date('d M, Y', strtotime($order['created_at'])) ?></span>
                                        <p style="margin: 5px 0 0; font-size: 12px; color: #6c757d;"><?= date('h:i A', strtotime($order['created_at'])) ?></p>
                                    </td>
                                    <td>
                                        <a href="order_view.php?id=<?= $order['id'] ?>" class="btn btn-link text-primary" title="View">
                                            <i class="ni ni-zoom-split-in" style="font-size: 18px;"></i>
                                        </a>
                                        <a href="order_edit.php?id=<?= $order['id'] ?>" class="btn btn-link text-dark" title="Edit">
                                            <i class="fas fa-pencil-alt" style="font-size: 18px;"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="8" class="text-center" style="padding: 50px;">
                                        <p style="color: #6c757d;">No orders found</p>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="row mt-4">
        <div class="col-12">
            <nav aria-label="Page navigation">
                <ul class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="<?= $i === $page ? 'active' : '' ?>">
                        <a href="?page=<?= $i ?>&status=<?= $status_filter ?>&payment=<?= $payment_filter ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include('includes/footer.php'); ?>