<?php
/**
 * Generate Report Frontend
 * File: d:\XAMPP\htdocs\pages\MinC_Project\app\frontend\generate_report.php
 */

include_once '../../backend/auth.php';
include_once '../../database/connect_database.php';

$validation = validateSession();
if (!$validation['valid']) {
    header('Location: ../../index.php?error=' . $validation['reason']);
    exit;
}

if (!isManagementLevel()) {
    $_SESSION['error_message'] = 'Access denied. Only management can generate reports.';
    header('Location: dashboard.php');
    exit;
}

$custom_title = 'Generate Report - MinC Project';
$current_page = 'generate_report';

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$status_filter = $_GET['status'] ?? '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
    $start_date = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    $end_date = date('Y-m-d');
}
if ($end_date < $start_date) {
    $end_date = $start_date;
}

$report_range_label = date('F j, Y', strtotime($start_date)) . ' to ' . date('F j, Y', strtotime($end_date));

$params = [':start_date' => $start_date . ' 00:00:00', ':end_date' => $end_date . ' 23:59:59'];
$status_sql = '';
if ($status_filter !== '') {
    $status_sql = ' AND o.order_status = :status_filter ';
    $params[':status_filter'] = $status_filter;
}

try {
    $summary_stmt = $pdo->prepare("\n        SELECT\n            COUNT(*) AS total_orders,\n            COALESCE(SUM(o.total_amount), 0) AS total_sales,\n            SUM(CASE WHEN o.order_status = 'pending' THEN 1 ELSE 0 END) AS pending_orders,\n            SUM(CASE WHEN o.order_status IN ('confirmed','processing') THEN 1 ELSE 0 END) AS processing_orders,\n            SUM(CASE WHEN o.order_status IN ('shipped','delivered') THEN 1 ELSE 0 END) AS fulfilled_orders,\n            SUM(CASE WHEN o.order_status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_orders\n        FROM orders o\n        WHERE o.created_at BETWEEN :start_date AND :end_date\n        $status_sql\n    ");
    $summary_stmt->execute($params);
    $summary = $summary_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $orders_stmt = $pdo->prepare("\n        SELECT\n            o.order_number,\n            CONCAT(c.first_name, ' ', c.last_name) AS customer_name,\n            o.order_status,\n            o.payment_status,\n            o.payment_method,\n            o.total_amount,\n            o.created_at\n        FROM orders o\n        INNER JOIN customers c ON c.customer_id = o.customer_id\n        WHERE o.created_at BETWEEN :start_date AND :end_date\n        $status_sql\n        ORDER BY o.created_at DESC\n        LIMIT 100\n    ");
    $orders_stmt->execute($params);
    $orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);

    $top_products_stmt = $pdo->prepare("\n        SELECT\n            oi.product_name,\n            SUM(oi.quantity) AS qty_sold,\n            SUM(oi.subtotal) AS revenue\n        FROM order_items oi\n        INNER JOIN orders o ON o.order_id = oi.order_id\n        WHERE o.created_at BETWEEN :start_date AND :end_date\n          AND o.order_status IN ('confirmed','processing','shipped','delivered')\n        GROUP BY oi.product_name\n        ORDER BY revenue DESC\n        LIMIT 10\n    ");
    $top_products_stmt->execute([
        ':start_date' => $start_date . ' 00:00:00',
        ':end_date' => $end_date . ' 23:59:59'
    ]);
    $top_products = $top_products_stmt->fetchAll(PDO::FETCH_ASSOC);

    $daily_sales_stmt = $pdo->prepare("\n        SELECT\n            DATE(o.created_at) AS sale_date,\n            COUNT(*) AS orders_count,\n            COALESCE(SUM(o.total_amount), 0) AS total_sales,\n            SUM(CASE WHEN o.order_status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count,\n            SUM(CASE WHEN o.order_status IN ('confirmed','processing','shipped','delivered') THEN 1 ELSE 0 END) AS active_count\n        FROM orders o\n        WHERE o.created_at BETWEEN :start_date AND :end_date\n        $status_sql\n        GROUP BY DATE(o.created_at)\n        ORDER BY sale_date DESC\n    ");
    $daily_sales_stmt->execute($params);
    $daily_sales_raw = $daily_sales_stmt->fetchAll(PDO::FETCH_ASSOC);

    $daily_sales_index = [];
    foreach ($daily_sales_raw as $row) {
        $daily_sales_index[$row['sale_date']] = $row;
    }

    $daily_sales = [];
    $date_cursor = strtotime($start_date);
    $end_timestamp = strtotime($end_date);
    while ($date_cursor !== false && $date_cursor <= $end_timestamp) {
        $date_key = date('Y-m-d', $date_cursor);
        $row = $daily_sales_index[$date_key] ?? [
            'sale_date' => $date_key,
            'orders_count' => 0,
            'total_sales' => 0,
            'cancelled_count' => 0,
            'active_count' => 0
        ];
        $daily_sales[] = $row;
        $date_cursor = strtotime('+1 day', $date_cursor);
    }
    $daily_sales = array_reverse($daily_sales);

} catch (PDOException $e) {
    $summary = [
        'total_orders' => 0,
        'total_sales' => 0,
        'pending_orders' => 0,
        'processing_orders' => 0,
        'fulfilled_orders' => 0,
        'cancelled_orders' => 0
    ];
    $orders = [];
    $top_products = [];
    $daily_sales = [];
    error_log('Generate Report query error: ' . $e->getMessage());
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=orders_report_' . date('Ymd_His') . '.csv');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Order Number', 'Customer', 'Order Status', 'Payment Status', 'Payment Method', 'Total Amount', 'Created At']);
    foreach ($orders as $order) {
        fputcsv($out, [
            $order['order_number'],
            $order['customer_name'],
            $order['order_status'],
            $order['payment_status'],
            $order['payment_method'],
            $order['total_amount'],
            $order['created_at']
        ]);
    }
    fclose($out);
    exit;
}

$additional_styles = '
<style>
    .report-table th {
        font-size: 12px;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #6b7280;
        background: #f8fafc;
    }
    .report-table td, .report-table th {
        padding: 0.85rem 1rem;
        border-bottom: 1px solid #e5e7eb;
    }
    @media print {
        .no-print {
            display: none !important;
        }
        body {
            background: #fff !important;
        }
        .professional-card {
            box-shadow: none !important;
            border: 1px solid #d1d5db !important;
        }
    }
</style>';

ob_start();
?>

<div class="professional-card rounded-xl p-6 mb-6 animate-fadeIn">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-[#08415c] mb-2 flex items-center">
                <i class="fas fa-file-alt mr-3"></i>Sales Report
            </h2>
            <p class="text-gray-600">Unified report for orders, revenue, and product performance.</p>
            <p class="text-sm text-gray-500 mt-2">Reporting range: <strong><?= htmlspecialchars($report_range_label) ?></strong></p>
        </div>
        <div class="flex gap-2 no-print">
            <button onclick="window.print()" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">
                <i class="fas fa-print mr-2"></i>Print / Save PDF
            </button>
            <a href="?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&status=<?= urlencode($status_filter) ?>&export=csv"
               class="px-4 py-2 bg-[#08415c] text-white rounded-lg hover:bg-[#0a5273]">
                <i class="fas fa-file-csv mr-2"></i>Export CSV
            </a>
        </div>
    </div>
</div>

<div class="professional-card rounded-xl p-6 mb-6 no-print">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
            <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
            <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Order Status</label>
            <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                <option value="" <?= $status_filter === '' ? 'selected' : '' ?>>All</option>
                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                <option value="processing" <?= $status_filter === 'processing' ? 'selected' : '' ?>>Processing</option>
                <option value="shipped" <?= $status_filter === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                <option value="delivered" <?= $status_filter === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-[#08415c] text-white rounded-lg hover:bg-[#0a5273]">Apply Filters</button>
    </form>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
    <div class="professional-card rounded-xl p-4"><p class="text-xs text-gray-500">Orders</p><p class="text-2xl font-bold text-[#08415c]"><?= (int)($summary['total_orders'] ?? 0) ?></p></div>
    <div class="professional-card rounded-xl p-4"><p class="text-xs text-gray-500">Sales</p><p class="text-2xl font-bold text-green-600">PHP <?= number_format((float)($summary['total_sales'] ?? 0), 2) ?></p></div>
    <div class="professional-card rounded-xl p-4"><p class="text-xs text-gray-500">Pending</p><p class="text-2xl font-bold text-amber-600"><?= (int)($summary['pending_orders'] ?? 0) ?></p></div>
    <div class="professional-card rounded-xl p-4"><p class="text-xs text-gray-500">Processing</p><p class="text-2xl font-bold text-blue-600"><?= (int)($summary['processing_orders'] ?? 0) ?></p></div>
    <div class="professional-card rounded-xl p-4"><p class="text-xs text-gray-500">Fulfilled</p><p class="text-2xl font-bold text-emerald-600"><?= (int)($summary['fulfilled_orders'] ?? 0) ?></p></div>
    <div class="professional-card rounded-xl p-4"><p class="text-xs text-gray-500">Cancelled</p><p class="text-2xl font-bold text-red-600"><?= (int)($summary['cancelled_orders'] ?? 0) ?></p></div>
</div>

<div class="professional-card rounded-xl p-6 mb-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-4">
        <div>
            <h3 class="text-lg font-bold text-[#08415c]">Daily Sales Table</h3>
            <p class="text-sm text-gray-500">Daily breakdown keeps reporting consistent and ready for PDF export.</p>
        </div>
        <p class="text-sm text-gray-500">Days without sales remain visible as zero when no orders are recorded.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full report-table text-sm">
            <thead>
                <tr>
                    <th class="text-left">Date</th>
                    <th class="text-right">Orders</th>
                    <th class="text-right">Active</th>
                    <th class="text-right">Cancelled</th>
                    <th class="text-right">Sales</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($daily_sales)): ?>
                <tr><td colspan="5" class="text-center text-gray-500">No daily sales rows in the selected range.</td></tr>
            <?php else: ?>
                <?php foreach ($daily_sales as $day): ?>
                    <tr>
                        <td><?= htmlspecialchars(date('F j, Y', strtotime($day['sale_date']))) ?></td>
                        <td class="text-right"><?= (int)$day['orders_count'] ?></td>
                        <td class="text-right"><?= (int)$day['active_count'] ?></td>
                        <td class="text-right"><?= (int)$day['cancelled_count'] ?></td>
                        <td class="text-right">PHP <?= number_format((float)$day['total_sales'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="professional-card rounded-xl p-6 mb-6">
    <h3 class="text-lg font-bold text-[#08415c] mb-4">Orders Table</h3>
    <div class="overflow-x-auto">
        <table class="min-w-full report-table text-sm">
            <thead>
                <tr>
                    <th class="text-left">Order #</th>
                    <th class="text-left">Customer</th>
                    <th class="text-left">Order Status</th>
                    <th class="text-left">Payment Status</th>
                    <th class="text-left">Payment Method</th>
                    <th class="text-right">Total</th>
                    <th class="text-left">Created</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($orders)): ?>
                <tr><td colspan="7" class="text-center text-gray-500">No orders in selected range.</td></tr>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?= htmlspecialchars($order['order_number']) ?></td>
                        <td><?= htmlspecialchars($order['customer_name']) ?></td>
                        <td><?= htmlspecialchars(ucfirst($order['order_status'])) ?></td>
                        <td><?= htmlspecialchars(ucfirst($order['payment_status'])) ?></td>
                        <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $order['payment_method']))) ?></td>
                        <td class="text-right">PHP <?= number_format((float)$order['total_amount'], 2) ?></td>
                        <td><?= htmlspecialchars(date('M j, Y g:i A', strtotime($order['created_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="professional-card rounded-xl p-6">
    <h3 class="text-lg font-bold text-[#08415c] mb-4">Top Products Table</h3>
    <div class="overflow-x-auto">
        <table class="min-w-full report-table text-sm">
            <thead>
                <tr>
                    <th class="text-left">Product</th>
                    <th class="text-right">Qty Sold</th>
                    <th class="text-right">Revenue</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($top_products)): ?>
                <tr><td colspan="3" class="text-center text-gray-500">No product sales data.</td></tr>
            <?php else: ?>
                <?php foreach ($top_products as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['product_name']) ?></td>
                        <td class="text-right"><?= (int)$item['qty_sold'] ?></td>
                        <td class="text-right">PHP <?= number_format((float)$item['revenue'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
$report_content = ob_get_clean();
$content = $report_content;
include 'app.php';
?>

