<?php
/**
 * Inventory Report Frontend
 * File: d:\XAMPP\htdocs\pages\MinC_Project\app\frontend\inventory-report.php
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
    $_SESSION['error_message'] = 'Access denied. Only management can view inventory reports.';
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
$custom_title = 'Inventory Report - MinC Project';

// Fetch inventory data
try {
    // All products with stock info
    $inventory_query = "
        SELECT 
            p.product_id,
            p.product_name,
            p.product_code,
            p.stock_quantity,
            p.min_stock_level,
            p.stock_status,
            p.price,
            pl.product_line_name,
            c.category_name,
            p.status,
            p.created_at,
            (p.stock_quantity * p.price) as inventory_value
        FROM products p
        INNER JOIN product_lines pl ON p.product_line_id = pl.product_line_id
        INNER JOIN categories c ON pl.category_id = c.category_id
        WHERE p.status = 'active'
        ORDER BY c.category_name, pl.product_line_name, p.product_name
    ";
    $inventory_result = $pdo->query($inventory_query);
    $products = $inventory_result->fetchAll(PDO::FETCH_ASSOC);

    // Calculate summary
    $total_items = 0;
    $total_value = 0;
    $low_stock_items = 0;
    $out_of_stock_items = 0;
    $discontinued_items = 0;

    foreach ($products as $product) {
        $total_items += $product['stock_quantity'];
        $total_value += $product['inventory_value'];
        if ($product['stock_status'] === 'low_stock') $low_stock_items++;
        if ($product['stock_status'] === 'out_of_stock') $out_of_stock_items++;
        if ($product['status'] === 'discontinued') $discontinued_items++;
    }

    // Category inventory summary
    $category_summary_query = "
        SELECT 
            c.category_name,
            COUNT(p.product_id) as product_count,
            SUM(p.stock_quantity) as total_quantity,
            SUM(p.stock_quantity * p.price) as total_value,
            SUM(CASE WHEN p.stock_status = 'low_stock' THEN 1 ELSE 0 END) as low_stock_count
        FROM products p
        INNER JOIN product_lines pl ON p.product_line_id = pl.product_line_id
        INNER JOIN categories c ON pl.category_id = c.category_id
        WHERE p.status = 'active'
        GROUP BY c.category_id, c.category_name
        ORDER BY c.category_name
    ";
    $category_summary_result = $pdo->query($category_summary_query);
    $category_summaries = $category_summary_result->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $products = [];
    $category_summaries = [];
    $total_items = $total_value = $low_stock_items = $out_of_stock_items = $discontinued_items = 0;
    error_log("Inventory Report query error: " . $e->getMessage());
}

// Custom styles
$additional_styles = '
<style>
    .inventory-status-ok {
        background-color: #D1FAE5;
        color: #065F46;
    }

    .inventory-status-low {
        background-color: #FEF3C7;
        color: #92400E;
    }

    .inventory-status-out {
        background-color: #FEE2E2;
        color: #991B1B;
    }

    .category-header {
        background-color: rgba(8, 65, 92, 0.05);
        font-weight: 600;
        color: #08415c;
    }

    .inventory-row:hover {
        background-color: rgba(8, 65, 92, 0.03);
    }
</style>';

// Inventory report content
ob_start();
?>

<!-- Page Header -->
<div class="professional-card rounded-xl p-6 mb-6 animate-fadeIn">
    <div class="flex flex-col md:flex-row md:items-center justify-between">
        <div class="mb-4 md:mb-0">
            <h2 class="text-2xl font-bold text-[#08415c] mb-2 flex items-center">
                <i class="fas fa-clipboard-list text-teal-600 mr-3"></i>
                Inventory Report
            </h2>
            <p class="text-gray-600">
                Complete inventory status and valuation report
            </p>
        </div>
        <button onclick="window.print()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition flex items-center">
            <i class="fas fa-print mr-2"></i>Print Report
        </button>
    </div>
</div>

<!-- Summary Statistics -->
<div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
    <div class="professional-card rounded-xl p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">Total Items</p>
                <p class="text-2xl font-bold text-[#08415c]"><?= number_format($total_items) ?></p>
            </div>
            <div class="p-3 bg-gradient-to-br from-[#08415c] to-[#0a5273] text-white rounded-lg">
                <i class="fas fa-boxes text-xl"></i>
            </div>
        </div>
    </div>

    <div class="professional-card rounded-xl p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">Inventory Value</p>
                <p class="text-2xl font-bold text-green-600">₱<?= number_format($total_value, 0) ?></p>
            </div>
            <div class="p-3 bg-gradient-to-br from-green-500 to-green-700 text-white rounded-lg">
                <i class="fas fa-money-bill-wave text-xl"></i>
            </div>
        </div>
    </div>

    <div class="professional-card rounded-xl p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">Low Stock</p>
                <p class="text-2xl font-bold text-amber-600"><?= $low_stock_items ?></p>
            </div>
            <div class="p-3 bg-gradient-to-br from-amber-500 to-amber-700 text-white rounded-lg">
                <i class="fas fa-exclamation-circle text-xl"></i>
            </div>
        </div>
    </div>

    <div class="professional-card rounded-xl p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">Out of Stock</p>
                <p class="text-2xl font-bold text-red-600"><?= $out_of_stock_items ?></p>
            </div>
            <div class="p-3 bg-gradient-to-br from-red-500 to-red-700 text-white rounded-lg">
                <i class="fas fa-ban text-xl"></i>
            </div>
        </div>
    </div>

    <div class="professional-card rounded-xl p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">Total SKUs</p>
                <p class="text-2xl font-bold text-blue-600"><?= count($products) ?></p>
            </div>
            <div class="p-3 bg-gradient-to-br from-blue-500 to-blue-700 text-white rounded-lg">
                <i class="fas fa-cubes text-xl"></i>
            </div>
        </div>
    </div>
</div>

<!-- Category Summary -->
<div class="professional-card rounded-xl p-6 mb-6">
    <h3 class="text-lg font-bold text-[#08415c] mb-4">Category Summary</h3>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Category</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">SKUs</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Total Qty</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Inventory Value</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Low Stock</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (!empty($category_summaries)): ?>
                    <?php foreach ($category_summaries as $cat): ?>
                        <tr class="category-header">
                            <td class="px-6 py-3"><?= htmlspecialchars($cat['category_name']) ?></td>
                            <td class="px-6 py-3"><?= $cat['product_count'] ?></td>
                            <td class="px-6 py-3"><?= number_format($cat['total_quantity']) ?></td>
                            <td class="px-6 py-3">₱<?= number_format($cat['total_value'], 2) ?></td>
                            <td class="px-6 py-3">
                                <span class="<?= $cat['low_stock_count'] > 0 ? 'text-amber-600 font-semibold' : 'text-green-600' ?>">
                                    <?= $cat['low_stock_count'] ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-gray-500">No category data available</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Detailed Inventory -->
<div class="professional-card rounded-xl p-6">
    <h3 class="text-lg font-bold text-[#08415c] mb-4">Detailed Inventory by Product</h3>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Product</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Code</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Category</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 uppercase">Qty</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 uppercase">Min Level</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 uppercase">Price</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 uppercase">Value</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (!empty($products)): ?>
                    <?php foreach ($products as $product): ?>
                        <tr class="inventory-row hover:bg-gray-50 transition">
                            <td class="px-4 py-3 font-medium text-gray-900"><?= htmlspecialchars($product['product_name']) ?></td>
                            <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($product['product_code']) ?></td>
                            <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($product['category_name']) ?></td>
                            <td class="px-4 py-3 text-right font-semibold text-[#08415c]"><?= number_format($product['stock_quantity']) ?></td>
                            <td class="px-4 py-3 text-right text-gray-600"><?= number_format($product['min_stock_level']) ?></td>
                            <td class="px-4 py-3 text-right text-gray-600">₱<?= number_format($product['price'], 2) ?></td>
                            <td class="px-4 py-3 text-right font-semibold text-green-600">₱<?= number_format($product['inventory_value'], 2) ?></td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 rounded text-xs font-medium whitespace-nowrap
                                    <?php 
                                    if ($product['stock_status'] === 'low_stock') {
                                        echo 'inventory-status-low';
                                    } elseif ($product['stock_status'] === 'out_of_stock') {
                                        echo 'inventory-status-out';
                                    } else {
                                        echo 'inventory-status-ok';
                                    }
                                    ?>">
                                    <?= ucfirst(str_replace('_', ' ', $product['stock_status'])) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-gray-500">No inventory data available</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$inventory_report_content = ob_get_clean();
$content = $inventory_report_content;
$current_page = 'inventory-report';
include 'app.php';
?>
