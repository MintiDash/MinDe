<?php
/**
 * Stock Management Frontend
 * File: d:\XAMPP\htdocs\pages\MinC_Project\app\frontend\stock-management.php
 */

include_once '../../backend/auth.php';
include_once '../../database/connect_database.php';

// Validate session
$validation = validateSession();
if (!$validation['valid']) {
    header('Location: ../../index.php?error=' . $validation['reason']);
    exit;
}

// Check if user has permission to access stock management
// Only IT Personnel (1) and Owner (2) can access
if (!isITStaff() && !isOwner()) {
    $_SESSION['error_message'] = 'Access denied. Only IT Personnel and Owner can access stock management.';
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
$custom_title = 'Stock Management - MinC Project';

// Fetch stock data
try {
    // Get all products with stock information
    $stock_query = "
        SELECT 
            p.product_id,
            p.product_name,
            p.product_code,
            p.stock_quantity,
            p.min_stock_level,
            p.stock_status,
            pl.product_line_name,
            c.category_name,
            p.price,
            (p.stock_quantity * p.price) as stock_value
        FROM products p
        INNER JOIN product_lines pl ON p.product_line_id = pl.product_line_id
        INNER JOIN categories c ON pl.category_id = c.category_id
        WHERE p.status = 'active'
        ORDER BY p.stock_quantity ASC
    ";
    $stock_result = $pdo->query($stock_query);
    $products = $stock_result->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals
    $total_stock_value = 0;
    $low_stock_count = 0;
    $out_of_stock_count = 0;
    $total_items = 0;

    foreach ($products as $product) {
        $total_stock_value += $product['stock_value'];
        $total_items += $product['stock_quantity'];
        if ($product['stock_status'] === 'low_stock') $low_stock_count++;
        if ($product['stock_status'] === 'out_of_stock') $out_of_stock_count++;
    }

} catch (PDOException $e) {
    $products = [];
    $total_stock_value = 0;
    $low_stock_count = 0;
    $out_of_stock_count = 0;
    $total_items = 0;
    error_log("Stock Management query error: " . $e->getMessage());
}

// Custom styles
$additional_styles = '
<style>
    .stock-status-low {
        background-color: #FEF3C7;
        color: #92400E;
    }

    .stock-status-out {
        background-color: #FEE2E2;
        color: #991B1B;
    }

    .stock-status-ok {
        background-color: #D1FAE5;
        color: #065F46;
    }

    .stock-row:hover {
        background-color: rgba(8, 65, 92, 0.05);
    }

    .update-btn {
        transition: all 0.3s ease;
    }

    .update-btn:hover {
        transform: translateY(-2px);
    }
</style>';

// Stock management content
ob_start();
?>

<!-- Page Header -->
<div class="professional-card rounded-xl p-6 mb-6 animate-fadeIn">
    <div class="flex flex-col md:flex-row md:items-center justify-between">
        <div class="mb-4 md:mb-0">
            <h2 class="text-2xl font-bold text-[#08415c] mb-2 flex items-center">
                <i class="fas fa-warehouse text-teal-600 mr-3"></i>
                Stock Management
            </h2>
            <p class="text-gray-600">
                Monitor and manage product inventory levels
            </p>
        </div>
        <div class="flex items-center space-x-2">
            <button onclick="window.print()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition flex items-center">
                <i class="fas fa-print mr-2"></i>Print Report
            </button>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
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
                <p class="text-sm text-gray-600 mb-1">Stock Value</p>
                <p class="text-2xl font-bold text-[#08415c]">₱<?= number_format($total_stock_value, 2) ?></p>
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
                <p class="text-2xl font-bold text-amber-600"><?= $low_stock_count ?></p>
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
                <p class="text-2xl font-bold text-red-600"><?= $out_of_stock_count ?></p>
            </div>
            <div class="p-3 bg-gradient-to-br from-red-500 to-red-700 text-white rounded-lg">
                <i class="fas fa-ban text-xl"></i>
            </div>
        </div>
    </div>
</div>

<!-- Stock Table -->
<div class="professional-card rounded-xl p-6">
    <h3 class="text-lg font-bold text-[#08415c] mb-4">Product Stock Levels</h3>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Product</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Code</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Category</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Current Stock</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Min Level</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Unit Price</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Stock Value</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (!empty($products)): ?>
                    <?php foreach ($products as $product): ?>
                        <tr class="stock-row hover:bg-gray-50 transition">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div>
                                    <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($product['product_name']) ?></p>
                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($product['product_line_name']) ?></p>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?= htmlspecialchars($product['product_code']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?= htmlspecialchars($product['category_name']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-[#08415c]"><?= number_format($product['stock_quantity']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?= number_format($product['min_stock_level']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">₱<?= number_format($product['price'], 2) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-green-600">₱<?= number_format($product['stock_value'], 2) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1 rounded-full text-xs font-medium
                                    <?php 
                                    if ($product['stock_status'] === 'low_stock') {
                                        echo 'stock-status-low';
                                    } elseif ($product['stock_status'] === 'out_of_stock') {
                                        echo 'stock-status-out';
                                    } else {
                                        echo 'stock-status-ok';
                                    }
                                    ?>">
                                    <?= ucfirst(str_replace('_', ' ', $product['stock_status'])) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                            No products found
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // Print functionality
    function printReport() {
        window.print();
    }
</script>

<?php
$stock_management_content = ob_get_clean();
$content = $stock_management_content;
$current_page = 'stock-management';
include 'app.php';
?>
