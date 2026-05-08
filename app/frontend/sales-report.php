<?php
/**
 * Sales Report Frontend
 * File: d:\XAMPP\htdocs\pages\MinC_Project\app\frontend\sales-report.php
 */

include_once '../../backend/auth.php';
include_once '../../database/connect_database.php';

// Validate session
$validation = validateSession();
if (!$validation['valid']) {
    header('Location: ../../index.php?error=' . $validation['reason']);
    exit;
}

// Check if user has permission
if (!isManagementLevel()) {
    $_SESSION['error_message'] = 'Access denied. Only management can view sales reports.';
    header('Location: dashboard.php');
    exit;
}

// Get current user data
$user_data = [
    'id' => $_SESSION['user_id'] ?? null,
    'name' => $_SESSION['full_name'] ?? $_SESSION['fname'] ?? 'Guest User',
    'user_type' => $_SESSION['user_type_name'] ?? 'User'
];

// Set custom title
$custom_title = 'Sales Report - MinC Project';

// Get date range from request
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Fetch sales data
try {
    // Daily sales
    $daily_sales_query = "
        SELECT 
            DATE(created_at) as sale_date,
            COUNT(*) as order_count,
            SUM(total_amount) as daily_total
        FROM orders
        WHERE DATE(created_at) BETWEEN :start_date AND :end_date
          AND order_status IN ('delivered', 'shipped')
        GROUP BY DATE(created_at)
        ORDER BY sale_date ASC
    ";
    $daily_sales_stmt = $pdo->prepare($daily_sales_query);
    $daily_sales_stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
    $daily_sales = $daily_sales_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top products
    $top_products_query = "
        SELECT 
            p.product_name,
            SUM(oi.quantity) as total_quantity,
            SUM(oi.quantity * oi.price) as total_revenue
        FROM order_items oi
        JOIN products p ON oi.product_id = p.product_id
        JOIN orders o ON oi.order_id = o.order_id
        WHERE DATE(o.created_at) BETWEEN :start_date AND :end_date
          AND o.order_status IN ('delivered', 'shipped')
        GROUP BY p.product_id, p.product_name
        ORDER BY total_revenue DESC
        LIMIT 10
    ";
    $top_products_stmt = $pdo->prepare($top_products_query);
    $top_products_stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
    $top_products = $top_products_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Category sales
    $category_sales_query = "
        SELECT 
            c.category_name,
            COUNT(DISTINCT o.order_id) as order_count,
            SUM(oi.quantity) as total_items,
            SUM(oi.quantity * oi.price) as total_revenue
        FROM order_items oi
        JOIN products p ON oi.product_id = p.product_id
        JOIN product_lines pl ON p.product_line_id = pl.product_line_id
        JOIN categories c ON pl.category_id = c.category_id
        JOIN orders o ON oi.order_id = o.order_id
        WHERE DATE(o.created_at) BETWEEN :start_date AND :end_date
          AND o.order_status IN ('delivered', 'shipped')
        GROUP BY c.category_id, c.category_name
        ORDER BY total_revenue DESC
    ";
    $category_sales_stmt = $pdo->prepare($category_sales_query);
    $category_sales_stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
    $category_sales = $category_sales_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Summary
    $summary_query = "
        SELECT 
            COUNT(*) as total_orders,
            COUNT(DISTINCT customer_id) as total_customers,
            SUM(total_amount) as total_revenue,
            AVG(total_amount) as avg_order_value
        FROM orders
        WHERE DATE(created_at) BETWEEN :start_date AND :end_date
          AND order_status IN ('delivered', 'shipped')
    ";
    $summary_stmt = $pdo->prepare($summary_query);
    $summary_stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
    $summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $daily_sales = [];
    $top_products = [];
    $category_sales = [];
    $summary = ['total_orders' => 0, 'total_customers' => 0, 'total_revenue' => 0, 'avg_order_value' => 0];
    error_log("Sales Report query error: " . $e->getMessage());
}

// Custom styles
$additional_styles = '
<style>
    .sales-chart {
        background: linear-gradient(135deg, rgba(248,250,252,0.95) 0%, rgba(255,255,255,0.95) 100%);
        border: 1px solid rgba(8, 65, 92, 0.1);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .report-row:hover {
        background-color: rgba(8, 65, 92, 0.05);
    }

    .filter-btn {
        transition: all 0.3s ease;
    }

    .filter-btn:hover {
        transform: translateY(-2px);
    }
</style>';

// Sales report content
ob_start();
?>

<!-- Page Header -->
<div class="professional-card rounded-xl p-6 mb-6 animate-fadeIn">
    <div class="flex flex-col md:flex-row md:items-center justify-between">
        <div class="mb-4 md:mb-0">
            <h2 class="text-2xl font-bold text-[#08415c] mb-2 flex items-center">
                <i class="fas fa-chart-line text-teal-600 mr-3"></i>
                Sales Report
            </h2>
            <p class="text-gray-600">
                View comprehensive sales analytics and performance metrics
            </p>
        </div>
        <button onclick="window.print()"
            class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition flex items-center">
            <i class="fas fa-print mr-2"></i>Print Report
        </button>
    </div>
</div>

<!-- Date Range Filter -->
<div class="professional-card rounded-xl p-6 mb-6">
    <h3 class="text-lg font-bold text-[#08415c] mb-4">Filter by Date Range</h3>
    <form method="GET" class="flex flex-col md:flex-row gap-4 items-end">
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
            <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#08415c] focus:border-transparent">
        </div>
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
            <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#08415c] focus:border-transparent">
        </div>
        <button type="submit"
            class="filter-btn px-6 py-2 bg-gradient-to-r from-[#08415c] to-[#0a5273] text-white rounded-lg hover:shadow-lg transition">
            Generate Report
        </button>
    </form>
</div>

<!-- Summary Statistics -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="professional-card rounded-xl p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">Total Orders</p>
                <p class="text-2xl font-bold text-[#08415c]"><?= number_format($summary['total_orders'] ?? 0) ?></p>
            </div>
            <div class="p-3 bg-gradient-to-br from-[#08415c] to-[#0a5273] text-white rounded-lg">
                <i class="fas fa-shopping-cart text-xl"></i>
            </div>
        </div>
    </div>

    <div class="professional-card rounded-xl p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">Total Revenue</p>
                <p class="text-2xl font-bold text-green-600">₱<?= number_format($summary['total_revenue'] ?? 0, 0) ?>
                </p>
            </div>
            <div class="p-3 bg-gradient-to-br from-green-500 to-green-700 text-white rounded-lg">
                <i class="fas fa-money-bill-wave text-xl"></i>
            </div>
        </div>
    </div>

    <div class="professional-card rounded-xl p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">Avg Order Value</p>
                <p class="text-2xl font-bold text-blue-600">₱<?= number_format($summary['avg_order_value'] ?? 0, 2) ?>
                </p>
            </div>
            <div class="p-3 bg-gradient-to-br from-blue-500 to-blue-700 text-white rounded-lg">
                <i class="fas fa-chart-bar text-xl"></i>
            </div>
        </div>
    </div>

    <div class="professional-card rounded-xl p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">Unique Customers</p>
                <p class="text-2xl font-bold text-purple-600"><?= number_format($summary['total_customers'] ?? 0) ?></p>
            </div>
            <div class="p-3 bg-gradient-to-br from-purple-500 to-purple-700 text-white rounded-lg">
                <i class="fas fa-users text-xl"></i>
            </div>
        </div>
    </div>
</div>

<!-- Top Products -->
<div class="professional-card rounded-xl p-6 mb-6">
    <h3 class="text-lg font-bold text-[#08415c] mb-4">Top 10 Products by Revenue</h3>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Product Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Qty Sold</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Total Revenue</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (!empty($top_products)): ?>
                    <?php foreach ($top_products as $product): ?>
                        <tr class="report-row hover:bg-gray-50 transition">
                            <td class="px-6 py-4"><?= htmlspecialchars($product['product_name']) ?></td>
                            <td class="px-6 py-4 text-sm"><?= number_format($product['total_quantity']) ?></td>
                            <td class="px-6 py-4 text-sm font-semibold text-[#08415c]">
                                ₱<?= number_format($product['total_revenue'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="px-6 py-8 text-center text-gray-500">No sales data available</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Category Sales -->
<div class="professional-card rounded-xl p-6">
    <h3 class="text-lg font-bold text-[#08415c] mb-4">Sales by Category</h3>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Category</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Orders</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Items</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Revenue</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">% of Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php
                $total_revenue = $summary['total_revenue'] ?? 0;
                if (!empty($category_sales)):
                    foreach ($category_sales as $cat):
                        $percentage = $total_revenue > 0 ? ($cat['total_revenue'] / $total_revenue) * 100 : 0;
                        ?>
                        <tr class="report-row hover:bg-gray-50 transition">
                            <td class="px-6 py-4"><?= htmlspecialchars($cat['category_name']) ?></td>
                            <td class="px-6 py-4 text-sm"><?= number_format($cat['order_count']) ?></td>
                            <td class="px-6 py-4 text-sm"><?= number_format($cat['total_items']) ?></td>
                            <td class="px-6 py-4 text-sm font-semibold text-[#08415c]">
                                ₱<?= number_format($cat['total_revenue'], 2) ?></td>
                            <td class="px-6 py-4 text-sm"><?= number_format($percentage, 1) ?>%</td>
                        </tr>
                        <?php
                    endforeach;
                else:
                    ?>
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-gray-500">No category data available</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$sales_report_content = ob_get_clean();
$content = $sales_report_content;
$current_page = 'sales-report';
include 'app.php';
?>