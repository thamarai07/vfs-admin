<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}
require 'db.php';

// Advanced Analytics Calculations
$products = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$orders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$customers = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$categories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$revenue = $pdo->query("SELECT IFNULL(SUM(total),0) FROM orders WHERE status='completed'")->fetchColumn();

// Advanced Metrics
$todayRevenue = $pdo->query("SELECT IFNULL(SUM(total),0) FROM orders WHERE status='completed' AND DATE(created_at) = CURDATE()")->fetchColumn();
$yesterdayRevenue = $pdo->query("SELECT IFNULL(SUM(total),0) FROM orders WHERE status='completed' AND DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)")->fetchColumn();
$revenueGrowth = $yesterdayRevenue > 0 ? (($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100 : 0;

$thisMonthRevenue = $pdo->query("SELECT IFNULL(SUM(total),0) FROM orders WHERE status='completed' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetchColumn();
$lastMonthRevenue = $pdo->query("SELECT IFNULL(SUM(total),0) FROM orders WHERE status='completed' AND MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))")->fetchColumn();
$monthlyGrowth = $lastMonthRevenue > 0 ? (($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100 : 0;

// Customer Lifetime Value (Average)
$avgCLV = $pdo->query("
    SELECT AVG(customer_total) as avg_clv FROM (
        SELECT customer_id, SUM(total) as customer_total 
        FROM orders 
        WHERE status='completed' 
        GROUP BY customer_id
    ) as customer_totals
")->fetchColumn();

// Average Order Value
$avgOrderValue = $pdo->query("SELECT IFNULL(AVG(total),0) FROM orders WHERE status='completed'")->fetchColumn();

// Conversion Rate (Orders / Customers)
$conversionRate = $customers > 0 ? ($orders / $customers) * 100 : 0;

// Pending Orders
$pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();

// Low Stock Count
$lowStockCount = $pdo->query("SELECT COUNT(*) FROM products WHERE stock < 20")->fetchColumn();

// Critical Stock (less than 10)
$criticalStockCount = $pdo->query("SELECT COUNT(*) FROM products WHERE stock < 10")->fetchColumn();

// Top 5 Products by Revenue
$topProducts = $pdo->query("
    SELECT p.name, p.image, SUM(oi.quantity) as total_sold, SUM(oi.quantity * oi.price) as revenue
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.status = 'completed'
    GROUP BY p.id, p.name, p.image
    ORDER BY revenue DESC
    LIMIT 5
")->fetchAll();

// Recent Activity (Last 10 orders)
$recentActivity = $pdo->query("
    SELECT o.id, c.name as customer_name, o.total, o.status, o.created_at 
    FROM orders o 
    LEFT JOIN customers c ON o.customer_id = c.id 
    ORDER BY o.created_at DESC 
    LIMIT 10
")->fetchAll();

// Sales Trend (Last 30 days)
$salesTrend = $pdo->query("
    SELECT DATE(created_at) as date, COUNT(*) as order_count, SUM(total) as daily_revenue
    FROM orders
    WHERE status='completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
")->fetchAll();

// Category Performance
$categoryPerformance = $pdo->query("
    SELECT c.name, COUNT(DISTINCT oi.order_id) as order_count, SUM(oi.quantity * oi.price) as revenue
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed'
    GROUP BY c.id, c.name
    ORDER BY revenue DESC
")->fetchAll();

// Customer Segments
$newCustomers = $pdo->query("SELECT COUNT(*) FROM customers WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
$returningCustomers = $pdo->query("
    SELECT COUNT(DISTINCT customer_id) FROM orders 
    WHERE customer_id IN (
        SELECT customer_id FROM orders GROUP BY customer_id HAVING COUNT(*) > 1
    )
")->fetchColumn();

// Prediction: Next Month Revenue (Simple Linear Projection)
$last3MonthsAvg = $pdo->query("
    SELECT AVG(monthly_revenue) as avg_revenue FROM (
        SELECT SUM(total) as monthly_revenue
        FROM orders
        WHERE status='completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
        GROUP BY YEAR(created_at), MONTH(created_at)
    ) as monthly_data
")->fetchColumn();
$predictedRevenue = $last3MonthsAvg * (1 + ($monthlyGrowth / 100));

// Stock Value
$totalStockValue = $pdo->query("SELECT SUM(price * stock) FROM products")->fetchColumn();

// Order Status Distribution
$orderStats = $pdo->query("
    SELECT status, COUNT(*) as count
    FROM orders
    GROUP BY status
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Dashboard | Fruit CRM Pro</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* CSS Variables for Theming */
        :root {
            --primary: #667eea;
            --primary-dark: #5a67d8;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --neutral: #64748b;
            --background: #f8fafc;
            --card-bg: #ffffff;
            --text-dark: #0f172a;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            --shadow-hover: 0 12px 35px rgba(0, 0, 0, 0.12);
            --border-radius: 16px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--background);
            color: var(--text-dark);
            line-height: 1.5;
        }

        /* Layout */
        .content {
            padding: 24px 32px;
            max-width: 1600px;
            margin: 0 auto;
        }

        /* Header Section */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header-left h1 {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .header-subtitle {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--neutral);
            font-size: 0.875rem;
        }

        .live-indicator {
            width: 8px;
            height: 8px;
            background: var(--success);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .quick-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 12px 24px;
            border-radius: var(--border-radius);
            border: none;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            box-shadow: var(--shadow);
        }

        .action-btn-primary {
            background: linear-gradient(135deg, var(--primary), #764ba2);
            color: white;
        }

        .action-btn-success {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
        }

        .action-btn-warning {
            background: linear-gradient(135deg, var(--warning), #d97706);
            color: white;
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }

        /* AI Insights Banner */
        .ai-insights-banner {
            background: linear-gradient(135deg, #1e293b, #334155);
            border-radius: var(--border-radius);
            padding: 28px;
            margin-bottom: 32px;
            color: white;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }

        .ai-insights-banner::before {
            content: "";
            position: absolute;
            top: -50%;
            right: -20%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.2) 0%, transparent 70%);
            border-radius: 50%;
        }

        .ai-banner-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .ai-icon {
            width: 48px;
            height: 48px;
            background: rgba(102, 126, 234, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .ai-insights-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .ai-insight-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .insight-label {
            font-size: 0.75rem;
            opacity: 0.8;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .insight-value {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 8 colo
        }

        .insight-description {
            font-size: 0.8125rem;
            opacity: 0.7;
            line-height: 1.5;
        }

        /* KPI Cards Grid */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .kpi-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 24px;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border-left: 4px solid var(--accent);
        }

        .kpi-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }

        .kpi-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .kpi-label {
            font-size: 0.75rem;
            color: var(--neutral);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .kpi-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: var(--accent);
            opacity: 0.15;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .kpi-value {
            font-size: 2.25rem;
            font-weight: 800;
            color: var(--accent);
            margin-bottom: 12px;
            line-height: 1;
        }

        .kpi-footer {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.8125rem;
        }

        .trend-badge {
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .trend-up {
            background: #d1fae5;
            color: #065f46;
        }

        .trend-down {
            background: #fee2e2;
            color: #991b1b;
        }

        .trend-neutral {
            background: #e0e7ff;
            color: #3730a3;
        }

        /* Main Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            margin-bottom: 32px;
        }

        @media (max-width: 1400px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        .section-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 28px;
            box-shadow: var(--shadow);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f1f5f9;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .view-all-link {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .view-all-link:hover {
            color: var(--primary-dark);
            gap: 8px;
        }

        /* Chart Container */
        .chart-wrapper {
            position: relative;
            height: 320px;
            margin-top: 20px;
        }

        /* Activity Feed */
        .activity-feed {
            max-height: 500px;
            overflow-y: auto;
        }

        .activity-feed::-webkit-scrollbar {
            width: 6px;
        }

        .activity-feed::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }

        .activity-feed::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 12px;
            transition: all 0.3s;
            border: 1px solid #f1f5f9;
        }

        .activity-item:hover {
            background: #f8fafc;
            border-color: #e2e8f0;
        }

        .activity-icon-wrapper {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-size: 0.9375rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 4px;
        }

        .activity-meta {
            font-size: 0.8125rem;
            color: var(--neutral);
        }

        .activity-value {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--primary);
        }

        /* Top Products List */
        .product-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .product-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            border-radius: 14px;
            background: #f8fafc;
            transition: all 0.3s;
        }

        .product-item:hover {
            background: #f1f5f9;
            transform: translateX(5px);
        }

        .product-rank {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary), #764ba2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .product-info {
            flex: 1;
        }

        .product-name {
            font-size: 0.9375rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 4px;
        }

        .product-stats {
            font-size: 0.8125rem;
            color: var(--neutral);
        }

        .product-revenue {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--success);
        }

        /* Alert Box */
        .alert-box {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border-left: 4px solid var(--warning);
            border-radius: var(--border-radius);
            padding: 20px 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.2);
        }

        .alert-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .alert-icon {
            font-size: 1.5rem;
        }

        .alert-title {
            font-size: 1rem;
            font-weight: 700;
            color: #92400e;
        }

        .alert-description {
            color: #78350f;
            font-size: 0.875rem;
            line-height: 1.6;
            margin-bottom: 12px;
        }

        .alert-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .alert-badge {
            padding: 6px 14px;
            background: white;
            border-radius: 8px;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #92400e;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--neutral);
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-text {
            font-size: 0.9375rem;
            font-weight: 500;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .content {
                padding: 16px;
            }

            .kpi-grid {
                grid-template-columns: 1fr;
            }

            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .quick-actions {
                width: 100%;
                flex-direction: column;
            }

            .action-btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Loading Animation */
        @keyframes shimmer {
            0% { background-position: -468px 0; }
            100% { background-position: 468px 0; }
        }

        .skeleton {
            animation: shimmer 1.2s ease-in-out infinite;
            background: linear-gradient(to right, #f6f7f8 0%, #edeef1 20%, #f6f7f8 40%, #f6f7f8 100%);
            background-size: 800px 104px;
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include 'includes/header.php'; ?>

        <div class="content">
            <!-- Header -->
            <div class="dashboard-header">
                <div class="header-left">
                    <h1>🚀 Smart Dashboard</h1>
                    <div class="header-subtitle">
                        <span class="live-indicator"></span>
                        <span>Real-time analytics • Last updated: just now</span>
                    </div>
                </div>
                <div class="quick-actions">
                    <a href="products.php?action=add" class="action-btn action-btn-primary">➕ New Product</a>
                    <a href="customers.php?action=add" class="action-btn action-btn-success">👤 New Customer</a>
                    <a href="orders.php?status=pending" class="action-btn action-btn-warning">📋 Pending (<?= $pendingOrders ?>)</a>
                </div>
            </div>

            <!-- AI Insights Banner -->
            <div class="ai-insights-banner">
                <div class="ai-banner-header">
                    <div class="ai-icon">🤖</div>
                    <div>
                        <h2>AI-Powered Insights</h2>
                        <p style="opacity:0.8; font-size:0.875rem; margin-top:4px;">Smart predictions based on your data</p>
                    </div>
                </div>
                <div class="ai-insights-grid">
                    <div class="ai-insight-card">
                        <div class="insight-label">📈 Revenue Prediction</div>
                        <div class="insight-value">₹<?= number_format($predictedRevenue) ?></div>
                        <div class="insight-description">Projected revenue for next month based on trend analysis</div>
                    </div>
                    <div class="ai-insight-card">
                        <div class="insight-label">👥 Customer Retention</div>
                        <div class="insight-value"><?= $customers > 0 ? round(($returningCustomers / $customers) * 100) : 0 ?>%</div>
                        <div class="insight-description"><?= $returningCustomers ?> returning customers out of <?= $customers ?> total</div>
                    </div>
                    <div class="ai-insight-card">
                        <div class="insight-label">💎 Avg Customer Value</div>
                        <div class="insight-value">₹<?= number_format($avgCLV) ?></div>
                        <div class="insight-description">Average lifetime value per customer</div>
                    </div>
                    <div class="ai-insight-card">
                        <div class="insight-label">📦 Stock Value</div>
                        <div class="insight-value">₹<?= number_format($totalStockValue) ?></div>
                        <div class="insight-description">Total inventory value at current prices</div>
                    </div>
                </div>
            </div>

            <!-- Critical Alerts -->
            <?php if ($criticalStockCount > 0): ?>
                <div class="alert-box">
                    <div class="alert-header">
                        <span class="alert-icon">⚠️</span>
                        <span class="alert-title">Critical Stock Alert!</span>
                    </div>
                    <p class="alert-description">
                        <strong><?= $criticalStockCount ?></strong> products are critically low (less than 10 units). 
                        <strong><?= $lowStockCount ?></strong> products need restocking soon.
                    </p>
                    <div class="alert-list">
                        <?php
                        $criticalProducts = $pdo->query("SELECT name, stock FROM products WHERE stock < 10 ORDER BY stock ASC LIMIT 5")->fetchAll();
                        foreach ($criticalProducts as $product):
                        ?>
                            <span class="alert-badge"><?= htmlspecialchars($product['name']) ?>: <?= $product['stock'] ?> left</span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- KPI Cards -->
            <div class="kpi-grid">
                <div class="kpi-card" style="--accent:#667eea;">
                    <div class="kpi-header">
                        <div>
                            <div class="kpi-label">Total Revenue</div>
                        </div>
                        <div class="kpi-icon">💰</div>
                    </div>
                    <div class="kpi-value">₹<?= number_format($revenue) ?></div>
                    <div class="kpi-footer">
                        <span class="trend-badge trend-up">+<?= $newCustomers ?> new</span>
                        <span style="color:#64748b;">this month</span>
                    </div>
                </div>
                <div class="kpi-card" style="--accent:#10b981;">
                    <div class="kpi-header">
                        <div>
                            <div class="kpi-label">Total Orders</div>
                        </div>
                        <div class="kpi-icon">📦</div>
                    </div>
                    <div class="kpi-value"><?= $orders ?></div>
                    <div class="kpi-footer">
                        <span class="trend-badge trend-warning"><?= $pendingOrders ?> pending</span>
                        <span style="color:#64748b;">awaiting action</span>
                    </div>
                </div>
                <div class="kpi-card" style="--accent:#f59e0b;">
                    <div class="kpi-header">
                        <div>
                            <div class="kpi-label">Avg Order Value</div>
                        </div>
                        <div class="kpi-icon">💳</div>
                    </div>
                    <div class="kpi-value">₹<?= number_format($avgOrderValue) ?></div>
                    <div class="kpi-footer">
                        <span class="trend-badge trend-neutral">Per order</span>
                        <span style="color:#64748b;">average spend</span>
                    </div>
                </div>
                <div class="kpi-card" style="--accent:#3b82f6;">
                    <div class="kpi-header">
                        <div>
                            <div class="kpi-label">Total Customers</div>
                        </div>
                        <div class="kpi-icon">👥</div>
                    </div>
                    <div class="kpi-value"><?= $customers ?></div>
                    <div class="kpi-footer">
                        <span class="trend-badge <?= $monthlyGrowth >= 0 ? 'trend-up' : 'trend-down' ?>">
                            <?= $monthlyGrowth >= 0 ? '↗' : '↘' ?> <?= abs(round($monthlyGrowth, 1)) ?>%
                        </span>
                        <span style="color:#64748b;">vs last month</span>
                    </div>
                </div>
                <div class="kpi-card" style="--accent:#8b5cf6;">
                    <div class="kpi-header">
                        <div>
                            <div class="kpi-label">Conversion Rate</div>
                        </div>
                        <div class="kpi-icon">📊</div>
                    </div>
                    <div class="kpi-value"><?= round($conversionRate, 1) ?>%</div>
                    <div class="kpi-footer">
                        <span class="trend-badge trend-neutral">Orders/Customer</span>
                        <span style="color:#64748b;">ratio</span>
                    </div>
                </div>
                <div class="kpi-card" style="--accent:#ec4899;">
                    <div class="kpi-header">
                        <div>
                            <div class="kpi-label">Today's Revenue</div>
                        </div>
                        <div class="kpi-icon">⚡</div>
                    </div>
                    <div class="kpi-value">₹<?= number_format($todayRevenue) ?></div>
                    <div class="kpi-footer">
                        <span class="trend-badge <?= $revenueGrowth >= 0 ? 'trend-up' : 'trend-down' ?>">
                            <?= $revenueGrowth >= 0 ? '↗' : '↘' ?> <?= abs(round($revenueGrowth, 1)) ?>%
                        </span>
                        <span style="color:#64748b;">vs yesterday</span>
                    </div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="content-grid">
                <!-- Sales Trend Chart -->
                <div class="section-card">
                    <div class="section-header">
                        <h3 class="section-title">📈 Sales Trend (Last 30 Days)</h3>
                        <a href="reports.php" class="view-all-link">View Reports →</a>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="salesTrendChart"></canvas>
                    </div>
                </div>

                <!-- Top Products -->
                <div class="section-card">
                    <div class="section-header">
                        <h3 class="section-title">🏆 Top Products</h3>
                        <a href="products.php" class="view-all-link">View All →</a>
                    </div>
                    <?php if (count($topProducts) > 0): ?>
                        <div class="product-list">
                            <?php foreach ($topProducts as $index => $product): ?>
                                <div class="product-item">
                                    <div class="product-rank">#<?= $index + 1 ?></div>
                                    <div class="product-info">
                                        <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
                                        <div class="product-stats"><?= $product['total_sold'] ?> units sold</div>
                                    </div>
                                    <div class="product-revenue">₹<?= number_format($product['revenue']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">📊</div>
                            <p class="empty-text">No product data available yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Second Grid -->
            <div class="content-grid">
                <!-- Recent Activity -->
                <div class="section-card">
                    <div class="section-header">
                        <h3 class="section-title">⚡ Recent Activity</h3>
                        <a href="orders.php" class="view-all-link">View All Orders →</a>
                    </div>
                    <?php if (count($recentActivity) > 0): ?>
                        <div class="activity-feed">
                            <?php foreach ($recentActivity as $activity): ?>
                                <?php
                                $iconBg = '';
                                $icon = '';
                                switch ($activity['status']) {
                                    case 'completed':
                                        $iconBg = 'background: linear-gradient(135deg, #10b981, #059669);';
                                        $icon = '✓';
                                        break;
                                    case 'pending':
                                        $iconBg = 'background: linear-gradient(135deg, #f59e0b, #d97706);';
                                        $icon = '⏳';
                                        break;
                                    case 'cancelled':
                                        $iconBg = 'background: linear-gradient(135deg, #ef4444, #dc2626);';
                                        $icon = '✕';
                                        break;
                                }
                                ?>
                                <div class="activity-item">
                                    <div class="activity-icon-wrapper" style="<?= $iconBg ?> color: white;">
                                        <?= $icon ?>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title">
                                            Order #<?= $activity['id'] ?> by <?= htmlspecialchars($activity['customer_name']) ?>
                                        </div>
                                        <div class="activity-meta">
                                            <?= ucfirst($activity['status']) ?> • 
                                            <?php
                                            $time = strtotime($activity['created_at']);
                                            $diff = time() - $time;
                                            if ($diff < 60) echo 'Just now';
                                            elseif ($diff < 3600) echo round($diff/60) . ' mins ago';
                                            elseif ($diff < 86400) echo round($diff/3600) . ' hours ago';
                                            else echo date('d M, Y', $time);
                                            ?>
                                        </div>
                                    </div>
                                    <div class="activity-value">₹<?= number_format($activity['total']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">📭</div>
                            <p class="empty-text">No recent activity</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Category Performance -->
                <div class="section-card">
                    <div class="section-header">
                        <h3 class="section-title">📊 Category Performance</h3>
                        <a href="categories.php" class="view-all-link">Manage →</a>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Quick Stats Row -->
            <div class="section-card">
                <div class="section-header">
                    <h3 class="section-title">📋 Quick Stats</h3>
                </div>
                <div class="kpi-grid">
                    <div class="kpi-card" style="--accent:#14b8a6;">
                        <div class="kpi-header">
                            <div class="kpi-label">New Customers</div>
                            <div class="kpi-icon">🆕</div>
                        </div>
                        <div class="kpi-value"><?= $newCustomers ?></div>
                        <div class="kpi-footer">
                            <span style="color:#64748b;">Last 30 days</span>
                        </div>
                    </div>
                    <div class="kpi-card" style="--accent:#6366f1;">
                        <div class="kpi-header">
                            <div class="kpi-label">Returning Customers</div>
                            <div class="kpi-icon">🔄</div>
                        </div>
                        <div class="kpi-value"><?= $returningCustomers ?></div>
                        <div class="kpi-footer">
                            <span style="color:#64748b;">Made 2+ orders</span>
                        </div>
                    </div>
                    <div class="kpi-card" style="--accent:#f43f5e;">
                        <div class="kpi-header">
                            <div class="kpi-label">Products in Stock</div>
                            <div class="kpi-icon">📦</div>
                        </div>
                        <div class="kpi-value"><?= $products ?></div>
                        <div class="kpi-footer">
                            <span class="trend-badge" style="background:#fee2e2; color:#991b1b;">
                                <?= $lowStockCount ?> low stock
                            </span>
                        </div>
                    </div>
                    <div class="kpi-card" style="--accent:#0ea5e9;">
                        <div class="kpi-header">
                            <div class="kpi-label">Active Categories</div>
                            <div class="kpi-icon">📂</div>
                        </div>
                        <div class="kpi-value"><?= $categories ?></div>
                        <div class="kpi-footer">
                            <span style="color:#64748b;">Product categories</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Sales Trend Chart
        const initSalesTrendChart = () => {
            const salesCtx = document.getElementById('salesTrendChart').getContext('2d');
            const salesData = <?= json_encode($salesTrend) ?>;
            
            const salesDates = salesData.map(item => {
                const date = new Date(item.date);
                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            });
            const salesRevenue = salesData.map(item => parseFloat(item.daily_revenue));
            const salesOrders = salesData.map(item => parseInt(item.order_count));

            new Chart(salesCtx, {
                type: 'line',
                data: {
                    labels: salesDates,
                    datasets: [
                        {
                            label: 'Revenue (₹)',
                            data: salesRevenue,
                            borderColor: '#667eea',
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            borderWidth: 3,
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: '#667eea',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Orders',
                            data: salesOrders,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            borderWidth: 3,
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: '#10b981',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 20,
                                font: { size: 13, weight: '600' }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.95)',
                            padding: 12,
                            borderColor: '#334155',
                            borderWidth: 1,
                            titleFont: { size: 14, weight: '700' },
                            bodyFont: { size: 13 },
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) label += ': ';
                                    if (context.parsed.y !== null) {
                                        label += context.datasetIndex === 0 ? '₹' + context.parsed.y.toLocaleString() : context.parsed.y;
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            beginAtZero: true,
                            grid: { color: '#f1f5f9' },
                            ticks: {
                                callback: function(value) { return '₹' + value.toLocaleString(); },
                                font: { size: 12 }
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            beginAtZero: true,
                            grid: { drawOnChartArea: false },
                            ticks: { font: { size: 12 } }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 12 } }
                        }
                    }
                }
            });
        };

        // Category Performance Chart
        const initCategoryChart = () => {
            const categoryCtx = document.getElementById('categoryChart').getContext('2d');
            const categoryData = <?= json_encode($categoryPerformance) ?>;
            
            const categoryLabels = categoryData.map(item => item.name);
            const categoryRevenue = categoryData.map(item => parseFloat(item.revenue));

            const categoryColors = ['#667eea', '#10b981', '#f59e0b', '#3b82f6', '#8b5cf6', '#ec4899'];

            new Chart(categoryCtx, {
                type: 'doughnut',
                data: {
                    labels: categoryLabels,
                    datasets: [{
                        data: categoryRevenue,
                        backgroundColor: categoryColors,
                        borderWidth: 4,
                        borderColor: '#fff',
                        hoverOffset: 10
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                font: { size: 13, weight: '600' },
                                usePointStyle: true,
                                generateLabels: function(chart) {
                                    const data = chart.data;
                                    if (data.labels.length && data.datasets.length) {
                                        return data.labels.map((label, i) => {
                                            const value = data.datasets[0].data[i];
                                            return {
                                                text: label + ' (₹' + value.toLocaleString() + ')',
                                                fillStyle: data.datasets[0].backgroundColor[i],
                                                hidden: false,
                                                index: i
                                            };
                                        });
                                    }
                                    return [];
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.95)',
                            padding: 12,
                            borderColor: '#334155',
                            borderWidth: 1,
                            titleFont: { size: 14, weight: '700' },
                            bodyFont: { size: 13 },
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return label + ': ₹' + value.toLocaleString() + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        };

        // Initialize Charts
        window.onload = () => {
            initSalesTrendChart();
            initCategoryChart();
            if (typeof showDialog === 'function') {
                showDialog("Welcome to your AI-Powered Dashboard! 🚀", "success");
            }
        };

        // Auto-refresh stats every 30 seconds
        setInterval(() => {
            const indicator = document.querySelector('.live-indicator');
            indicator.style.opacity = '0.3';
            setTimeout(() => {
                indicator.style.opacity = '1';
                console.log('Stats refreshed');
            }, 300);
        }, 30000);
    </script>
</body>
</html>