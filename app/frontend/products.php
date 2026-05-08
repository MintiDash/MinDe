<?php
/**
 * Products Management Frontend
 * File: C:\xampp\htdocs\MinC_Project\app\frontend\products.php
 */

// Authentication and user data
include_once '../../backend/auth.php';
include_once '../../database/connect_database.php';

// Validate session
$validation = validateSession();
if (!$validation['valid']) {
    header('Location: ../../index.php?error=' . $validation['reason']);
    exit;
}

// Check if user has permission to access products management
// Only IT Personnel (1), Owner (2), and Manager (3) can access
if (!isManagementLevel()) {
    $_SESSION['error_message'] = 'Access denied. You do not have permission to access this page.';
    header('Location: dashboard.php');
    exit;
}

// Get current user data
$user_data = [
    'id' => $_SESSION['user_id'] ?? null,
    'name' => $_SESSION['full_name'] ?? $_SESSION['fname'] ?? 'Guest User',
    'user_type' => $_SESSION['user_type_name'] ?? 'User',
    'user_level_id' => $_SESSION['user_level_id'] ?? null
];

// Set custom title for this page
$custom_title = 'Products Management - MinC Project';

// Update user array to match app.php format
$user = [
    'full_name' => $user_data['name'],
    'user_type' => $user_data['user_type'],
    'is_logged_in' => isset($user_data['id'])
];

// Fetch products data
try {
    // Get all products with their product line and category details
    $products_query = "
        SELECT 
            p.product_id,
            p.product_line_id,
            p.product_name,
            p.product_code,
            p.product_description,
            p.product_image,
            p.price,
            p.stock_quantity,
            p.stock_status,
            p.min_stock_level,
            p.is_featured,
            p.display_order,
            p.status,
            p.created_at,
            p.updated_at,
            pl.product_line_name,
            pl.category_id,
            c.category_name
        FROM products p
        INNER JOIN product_lines pl ON p.product_line_id = pl.product_line_id
        INNER JOIN categories c ON pl.category_id = c.category_id
        ORDER BY c.display_order ASC, pl.display_order ASC, p.display_order ASC, p.product_name ASC
    ";
    $products_result = $pdo->query($products_query);
    $products = $products_result->fetchAll(PDO::FETCH_ASSOC);

    // Get all categories for the filter
    $categories_query = "
        SELECT 
            category_id,
            category_name,
            status
        FROM categories
        WHERE status = 'active'
        ORDER BY display_order ASC, category_name ASC
    ";
    $categories_result = $pdo->query($categories_query);
    $categories = $categories_result->fetchAll(PDO::FETCH_ASSOC);

    // Get all product lines for the dropdown
    $product_lines_query = "
        SELECT 
            pl.product_line_id,
            pl.product_line_name,
            pl.category_id,
            pl.status,
            c.category_name
        FROM product_lines pl
        INNER JOIN categories c ON pl.category_id = c.category_id
        WHERE pl.status = 'active'
        ORDER BY c.display_order ASC, pl.display_order ASC, pl.product_line_name ASC
    ";
    $product_lines_result = $pdo->query($product_lines_query);
    $product_lines = $product_lines_result->fetchAll(PDO::FETCH_ASSOC);

    // Get distinct statuses for filter
    $statuses_query = "SELECT DISTINCT status FROM products WHERE status IS NOT NULL ORDER BY status";
    $statuses_result = $pdo->query($statuses_query);
    $product_statuses = $statuses_result->fetchAll(PDO::FETCH_ASSOC);
    
    // If no statuses found, provide defaults
    if (empty($product_statuses)) {
        $product_statuses = [
            ['status' => 'active'],
            ['status' => 'inactive'],
            ['status' => 'discontinued']
        ];
    }

    // Get distinct stock statuses for filter
    $stock_statuses = [
        ['stock_status' => 'in_stock'],
        ['stock_status' => 'low_stock'],
        ['stock_status' => 'out_of_stock']
    ];

} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error loading products data: ' . $e->getMessage();
    $products = $categories = $product_lines = $product_statuses = $stock_statuses = [];
}

// Additional styles for products management specific elements
$additional_styles = '
.product-image-preview {
    width: 60px;
    height: 60px;
    border-radius: 8px;
    object-fit: cover;
    border: 2px solid #e5e7eb;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
}

.status-active {
    background-color: #dcfce7;
    color: #166534;
}

.status-inactive {
    background-color: #fef2f2;
    color: #991b1b;
}

.status-discontinued {
    background-color: #f3f4f6;
    color: #4b5563;
}

.stock-status-in_stock {
    background-color: #dcfce7;
    color: #166534;
}

.stock-status-low_stock {
    background-color: #fef3c7;
    color: #92400e;
}

.stock-status-out_of_stock {
    background-color: #fef2f2;
    color: #991b1b;
}

.featured-badge {
    background-color: #fef3c7;
    color: #92400e;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
}

.modal {
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
}

.form-group {
    margin-bottom: 1rem;
}

.form-label {
    display: block;
    font-size: 0.875rem;
    font-weight: 500;
    color: #374151;
    margin-bottom: 0.5rem;
}

.form-input {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    transition: all 0.2s ease;
}

.form-input:focus {
    outline: none;
    border-color: #16a34a;
    box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
}

.form-textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    transition: all 0.2s ease;
    resize: vertical;
    min-height: 100px;
}

.form-textarea:focus {
    outline: none;
    border-color: #16a34a;
    box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
}

.form-select {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    transition: all 0.2s ease;
    background-color: white;
}

.form-select:focus {
    outline: none;
    border-color: #16a34a;
    box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
}

.form-checkbox {
    width: 1.25rem;
    height: 1.25rem;
    border-radius: 0.25rem;
    border: 1px solid #d1d5db;
    cursor: pointer;
}

.form-checkbox:checked {
    background-color: #16a34a;
    border-color: #16a34a;
}

.image-upload-container {
    position: relative;
    width: 100%;
    padding: 2rem;
    border: 2px dashed #d1d5db;
    border-radius: 0.5rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.image-upload-container:hover {
    border-color: #16a34a;
    background-color: #f9fafb;
}

.image-upload-container.has-image {
    border-style: solid;
    border-color: #16a34a;
}

.image-preview-large {
    max-width: 200px;
    max-height: 200px;
    margin: 1rem auto;
    border-radius: 8px;
    display: none;
}

.image-preview-large.show {
    display: block;
}

.table-hover tbody tr:hover {
    background-color: rgba(249, 250, 251, 0.8);
}

.desktop-table {
    width: 100%;
    overflow-x: auto;
}

.desktop-table table {
    width: 100%;
    min-width: 100%;
    table-layout: auto;
}

.desktop-table th,
.desktop-table td {
    min-width: 100px;
}

.desktop-table th:first-child,
.desktop-table td:first-child {
    min-width: 80px;
}

.desktop-table th:nth-child(2),
.desktop-table td:nth-child(2) {
    min-width: 150px;
}

.desktop-table th:nth-child(3),
.desktop-table td:nth-child(3) {
    min-width: 200px;
}

@media (max-width: 768px) {
    .mobile-card {
        display: block !important;
    }
    
    .desktop-table {
        display: none !important;
    }
}

@media (min-width: 769px) {
    .mobile-card {
        display: none !important;
    }
    
    .desktop-table {
        display: block !important;
        width: 100%;
    }
}

.professional-card.table-container {
    padding: 0;
    overflow: hidden;
}

.professional-card.table-container .desktop-table {
    margin: 0;
}

.drag-handle {
    cursor: move;
    color: #9ca3af;
    transition: color 0.2s ease;
}

.drag-handle:hover {
    color: #16a34a;
}

.dragging {
    opacity: 0.5;
}

.category-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 500;
    background-color: #f3f4f6;
    color: #4b5563;
}

.product-line-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 500;
    background-color: #e0f2fe;
    color: #075985;
}

.price-tag {
    font-size: 1.125rem;
    font-weight: 700;
    color: #16a34a;
}

.stock-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.grid-2-cols {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

@media (max-width: 640px) {
    .grid-2-cols {
        grid-template-columns: 1fr;
    }
}
';

// Products management content
ob_start();
?>

<!-- Page Header -->
<div class="professional-card rounded-xl p-6 mb-6 animate-fadeIn">
    <div class="flex flex-col md:flex-row md:items-center justify-between">
        <div class="mb-4 md:mb-0">
            <h2 class="text-2xl font-bold text-gray-800 mb-2 flex items-center">
                <i class="fas fa-box-open text-green-600 mr-3"></i>
                Products Management
            </h2>
            <p class="text-gray-600">
                Manage your auto parts inventory, pricing, and stock levels.
            </p>
        </div>
        <div class="w-full md:w-auto flex justify-start md:justify-end">
            <button onclick="openAddModal()" class="btn-primary px-6 py-3 rounded-xl font-medium flex items-center">
                <i class="fas fa-plus mr-2"></i>
                Add New Product
            </button>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <div class="professional-card rounded-xl p-6 hover-lift">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 mb-1">Total Products</p>
                <p class="text-3xl font-bold text-gray-900"><?php echo count($products); ?></p>
            </div>
            <div class="p-4 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-xl shadow-lg">
                <i class="fas fa-box-open text-white text-2xl"></i>
            </div>
        </div>
    </div>

    <div class="professional-card rounded-xl p-6 hover-lift">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 mb-1">In Stock</p>
                <p class="text-3xl font-bold text-green-600">
                    <?php echo count(array_filter($products, function($p) { return $p['stock_status'] === 'in_stock'; })); ?>
                </p>
            </div>
            <div class="p-4 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl shadow-lg">
                <i class="fas fa-check-circle text-white text-2xl"></i>
            </div>
        </div>
    </div>

    <div class="professional-card rounded-xl p-6 hover-lift">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 mb-1">Low Stock</p>
                <p class="text-3xl font-bold text-yellow-600">
                    <?php echo count(array_filter($products, function($p) { return $p['stock_status'] === 'low_stock'; })); ?>
                </p>
            </div>
            <div class="p-4 bg-gradient-to-br from-yellow-500 to-amber-600 rounded-xl shadow-lg">
                <i class="fas fa-exclamation-triangle text-white text-2xl"></i>
            </div>
        </div>
    </div>

    <div class="professional-card rounded-xl p-6 hover-lift">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 mb-1">Out of Stock</p>
                <p class="text-3xl font-bold text-red-600">
                    <?php echo count(array_filter($products, function($p) { return $p['stock_status'] === 'out_of_stock'; })); ?>
                </p>
            </div>
            <div class="p-4 bg-gradient-to-br from-red-500 to-rose-600 rounded-xl shadow-lg">
                <i class="fas fa-times-circle text-white text-2xl"></i>
            </div>
        </div>
    </div>
</div>

<!-- Filters and Search -->
<div class="professional-card rounded-xl p-6 mb-6 animate-fadeIn">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div>
            <label for="search_products" class="form-label">Search Products</label>
            <div class="relative">
                <input type="text" id="search_products" placeholder="Search by name, code..." 
                       class="form-input pl-10">
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
            </div>
        </div>
        
        <div>
            <label for="category_filter" class="form-label">Category</label>
            <select id="category_filter" class="form-select">
                <option value="">All Categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo htmlspecialchars($category['category_id']); ?>">
                        <?php echo htmlspecialchars($category['category_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div>
            <label for="stock_status_filter" class="form-label">Stock Status</label>
            <select id="stock_status_filter" class="form-select">
                <option value="">All Stock Status</option>
                <?php foreach ($stock_statuses as $stock_status): ?>
                    <option value="<?php echo htmlspecialchars($stock_status['stock_status']); ?>">
                        <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($stock_status['stock_status']))); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div>
            <label for="status_filter" class="form-label">Status</label>
            <select id="status_filter" class="form-select">
                <option value="">All Status</option>
                <?php foreach ($product_statuses as $status): ?>
                    <option value="<?php echo htmlspecialchars($status['status']); ?>">
                        <?php echo ucfirst(htmlspecialchars($status['status'])); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>

<!-- Pagination Controls -->
<div class="professional-card rounded-xl p-4 mb-6 animate-fadeIn">
    <div class="flex flex-col md:flex-row justify-between items-center gap-4">
        <div class="flex items-center gap-2">
            <label for="records_per_page" class="text-sm font-medium text-gray-700">Records per page:</label>
            <select id="records_per_page" class="form-input w-20">
                <option value="10" selected>10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </div>
        
        <div class="flex items-center gap-2">
            <span class="text-sm text-gray-600" id="pagination_info">Showing 0 to 0 of 0 records</span>
        </div>
        
        <div class="flex items-center gap-2" id="pagination_controls">
            <button onclick="goToFirstPage()" id="btn_first" class="px-3 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200" title="First Page">
                <i class="fas fa-angle-double-left"></i>
            </button>
            <button onclick="goToPreviousPage()" id="btn_prev" class="px-3 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200" title="Previous Page">
                <i class="fas fa-angle-left"></i>
            </button>
            
            <div class="flex gap-1" id="page_numbers">
                <!-- Page numbers will be inserted here by JavaScript -->
            </div>
            
            <button onclick="goToNextPage()" id="btn_next" class="px-3 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200" title="Next Page">
                <i class="fas fa-angle-right"></i>
            </button>
            <button onclick="goToLastPage()" id="btn_last" class="px-3 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200" title="Last Page">
                <i class="fas fa-angle-double-right"></i>
            </button>
        </div>
    </div>
</div>

<!-- Products Table/Cards -->
<div class="professional-card table-container rounded-xl overflow-hidden animate-fadeIn">
    <!-- Desktop Table View -->
    <div class="desktop-table">
        <table class="w-full table-hover">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Image</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product Details</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category/Line</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-box-open text-4xl mb-4 text-gray-300"></i>
                            <p>No products found.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <tr class="product-row" 
                            data-name="<?php echo strtolower($product['product_name']); ?>" 
                            data-code="<?php echo strtolower($product['product_code'] ?? ''); ?>"
                            data-description="<?php echo strtolower($product['product_description'] ?? ''); ?>"
                            data-category-id="<?php echo $product['category_id']; ?>"
                            data-stock-status="<?php echo $product['stock_status']; ?>"
                            data-status="<?php echo $product['status']; ?>"
                            data-product-id="<?php echo $product['product_id']; ?>">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <i class="fas fa-grip-vertical drag-handle mr-2"></i>
                                    <span class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($product['display_order']); ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($product['product_image']): ?>
                                    <img src="../../Assets/images/products/<?php echo htmlspecialchars($product['product_image']); ?>" 
                                         alt="<?php echo htmlspecialchars($product['product_name']); ?>" 
                                         class="product-image-preview">
                                <?php else: ?>
                                    <div class="product-image-preview bg-gray-200 flex items-center justify-center">
                                        <i class="fas fa-image text-gray-400"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($product['product_name']); ?>
                                    <?php if ($product['is_featured']): ?>
                                        <span class="featured-badge ml-2">Featured</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs text-gray-500 mb-1">
                                    Code: <?php echo htmlspecialchars($product['product_code']); ?>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <?php echo htmlspecialchars(substr($product['product_description'] ?? 'No description', 0, 60)) . (strlen($product['product_description'] ?? '') > 60 ? '...' : ''); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="category-badge mb-1">
                                    <?php echo htmlspecialchars($product['category_name']); ?>
                                </div>
                                <div class="product-line-badge">
                                    <?php echo htmlspecialchars($product['product_line_name']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="price-tag">
                                    ₱<?php echo number_format($product['price'], 2); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="stock-info">
                                    <span class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($product['stock_quantity']); ?>
                                    </span>
                                    <span class="status-badge stock-status-<?php echo $product['stock_status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $product['stock_status'])); ?>
                                    </span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    Min: <?php echo htmlspecialchars($product['min_stock_level']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="status-badge status-<?php echo $product['status']; ?>">
                                    <?php echo ucfirst($product['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center space-x-3">
                                    <button onclick="openEditModal(<?php echo $product['product_id']; ?>)" 
                                            class="text-blue-600 hover:text-blue-900 p-2 rounded-lg hover:bg-blue-50 transition-colors duration-200" 
                                            title="Edit Product">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="toggleProductStatus(<?php echo $product['product_id']; ?>, '<?php echo $product['status']; ?>')" 
                                            class="<?php echo $product['status'] === 'active' ? 'text-orange-600 hover:text-orange-900 hover:bg-orange-50' : 'text-green-600 hover:text-green-900 hover:bg-green-50'; ?> p-2 rounded-lg transition-colors duration-200" 
                                            title="<?php echo $product['status'] === 'active' ? 'Deactivate' : 'Activate'; ?> Product">
                                        <i class="fas fa-<?php echo $product['status'] === 'active' ? 'ban' : 'check-circle'; ?>"></i>
                                    </button>
                                    <button onclick="deleteProduct(<?php echo $product['product_id']; ?>)" 
                                            class="text-red-600 hover:text-red-900 p-2 rounded-lg hover:bg-red-50 transition-colors duration-200" 
                                            title="Delete Product">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Card View -->
    <div class="mobile-card p-4 space-y-4">
        <?php if (empty($products)): ?>
            <div class="text-center py-12 text-gray-500">
                <i class="fas fa-box-open text-4xl mb-4 text-gray-300"></i>
                <p>No products found.</p>
            </div>
        <?php else: ?>
            <?php foreach ($products as $product): ?>
<div class="bg-white border border-gray-200 rounded-xl p-4 product-card" 
     data-name="<?php echo strtolower($product['product_name']); ?>" 
     data-code="<?php echo strtolower($product['product_code'] ?? ''); ?>"
     data-description="<?php echo strtolower($product['product_description'] ?? ''); ?>"
data-category-id="<?php echo $product['category_id']; ?>"
data-stock-status="<?php echo $product['stock_status']; ?>"
data-status="<?php echo $product['status']; ?>"
data-product-id="<?php echo $product['product_id']; ?>">
<div class="flex items-start justify-between mb-3">
<div class="flex items-center">
<?php if ($product['product_image']): ?>
<img src="../../Assets/images/products/<?php echo htmlspecialchars($product['product_image']); ?>" 
                                  alt="<?php echo htmlspecialchars($product['product_name']); ?>" 
                                  class="product-image-preview mr-3">
<?php else: ?>
<div class="product-image-preview bg-gray-200 flex items-center justify-center mr-3">
<i class="fas fa-image text-gray-400"></i>
</div>
<?php endif; ?>
<div>
<h4 class="font-medium text-gray-900">
<?php echo htmlspecialchars($product['product_name']); ?>
<?php if ($product['is_featured']): ?>
<span class="featured-badge ml-1">Featured</span>
<?php endif; ?>
</h4>
<p class="text-xs text-gray-500">Code: <?php echo htmlspecialchars($product['product_code']); ?></p>
</div>
</div>
<span class="status-badge status-<?php echo $product['status']; ?>">
<?php echo ucfirst($product['status']); ?>
</span>
</div>
<div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Category</p>
                        <span class="category-badge"><?php echo htmlspecialchars($product['category_name']); ?></span>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Product Line</p>
                        <span class="product-line-badge"><?php echo htmlspecialchars($product['product_line_name']); ?></span>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Price</p>
                        <p class="price-tag">₱<?php echo number_format($product['price'], 2); ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Stock</p>
                        <div class="stock-info">
                            <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($product['stock_quantity']); ?></span>
                            <span class="status-badge stock-status-<?php echo $product['stock_status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $product['stock_status'])); ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-span-2">
                        <p class="text-xs text-gray-500 mb-1">Description</p>
                        <p class="text-sm text-gray-900">
                            <?php echo htmlspecialchars(substr($product['product_description'] ?? 'No description', 0, 100)) . (strlen($product['product_description'] ?? '') > 100 ? '...' : ''); ?>
                        </p>
                    </div>
                </div>
                <div class="flex justify-end space-x-2">
                    <button onclick="openEditModal(<?php echo $product['product_id']; ?>)" 
                            class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors duration-200" 
                            title="Edit Product">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="toggleProductStatus(<?php echo $product['product_id']; ?>, '<?php echo $product['status']; ?>')" 
                            class="p-2 <?php echo $product['status'] === 'active' ? 'text-orange-600 hover:bg-orange-50' : 'text-green-600 hover:bg-green-50'; ?> rounded-lg transition-colors duration-200" 
                            title="<?php echo $product['status'] === 'active' ? 'Deactivate' : 'Activate'; ?> Product">
                        <i class="fas fa-<?php echo $product['status'] === 'active' ? 'ban' : 'check-circle'; ?>"></i>
                    </button>
                    <button onclick="deleteProduct(<?php echo $product['product_id']; ?>)" 
                            class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors duration-200" 
                            title="Delete Product">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</div><!-- Add Product Modal -->
<div id="addProductModal" class="fixed inset-0 z-50 hidden modal bg-black bg-opacity-50 flex items-center justify-center p-4">
    <div class="professional-card rounded-xl max-w-4xl w-full max-h-screen overflow-y-auto">
        <form id="addProductForm" action="../../backend/products/add_product.php" method="POST" enctype="multipart/form-data" class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-semibold text-gray-800">Add New Product</h3>
                <button type="button" onclick="closeAddModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Basic Information -->
            <div class="md:col-span-2">
                <h4 class="text-md font-semibold text-gray-700 mb-3 pb-2 border-b">Basic Information</h4>
            </div>
            <div class="form-group">
                <label for="add_category_id" class="form-label">Category *</label>
                <select id="add_category_id" name="category_id" class="form-select" required onchange="loadProductLines('add')">
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category['category_id']); ?>">
                            <?php echo htmlspecialchars($category['category_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="add_product_line_id" class="form-label">Product Line *</label>
                <select id="add_product_line_id" name="product_line_id" class="form-select" required>
                    <option value="">Select Product Line</option>
                </select>
            </div>
            <div class="form-group">
                <label for="add_product_name" class="form-label">Product Name *</label>
                <input type="text" id="add_product_name" name="product_name" class="form-input" required>
            </div>
            <div class="form-group">
                <label for="add_product_code" class="form-label">Product Code *</label>
                <input type="text" id="add_product_code" name="product_code" class="form-input" required>
                <p class="text-xs text-gray-500 mt-1">Unique identifier (e.g., WA-001)</p>
            </div>
            <div class="form-group">
                <label for="add_display_order" class="form-label">Display Order *</label>
                <input type="number" id="add_display_order" name="display_order" class="form-input" min="0" value="0" required>
                <p class="text-xs text-gray-500 mt-1">Lower numbers appear first</p>
            </div>
            <div class="form-group md:col-span-2">
                <label for="add_product_description" class="form-label">Description</label>
                <textarea id="add_product_description" name="product_description" class="form-textarea"></textarea>
            </div>
            <!-- Pricing & Stock -->
            <div class="md:col-span-2">
                <h4 class="text-md font-semibold text-gray-700 mb-3 pb-2 border-b mt-4">Pricing & Stock</h4>
            </div>
            
            <div class="form-group">
                <label for="add_price" class="form-label">Price (₱) *</label>
                <input type="number" id="add_price" name="price" class="form-input" min="0" step="0.01" value="0.00" required>
            </div>
            
            <div class="form-group">
                <label for="add_stock_quantity" class="form-label">Stock Quantity *</label>
                <input type="number" id="add_stock_quantity" name="stock_quantity" class="form-input" min="0" value="0" required>
            </div>
            
            <div class="form-group">
                <label for="add_min_stock_level" class="form-label">Minimum Stock Level *</label>
                <input type="number" id="add_min_stock_level" name="min_stock_level" class="form-input" min="0" value="10" required>
                <p class="text-xs text-gray-500 mt-1">Alert when stock falls below this level</p>
            </div>
            
            <div class="form-group">
                <label for="add_status" class="form-label">Status *</label>
                <select id="add_status" name="status" class="form-select" required>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="discontinued">Discontinued</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label flex items-center">
                    <input type="checkbox" id="add_is_featured" name="is_featured" class="form-checkbox mr-2" value="1">
                    <span>Featured Product</span>
                </label>
                <p class="text-xs text-gray-500 mt-1">Display in featured products section</p>
            </div>
            
            <!-- Product Image -->
            <div class="md:col-span-2">
                <h4 class="text-md font-semibold text-gray-700 mb-3 pb-2 border-b mt-4">Product Image</h4>
            </div>
            
            <div class="form-group md:col-span-2">
                <label for="add_product_image" class="form-label">Product Image</label>
                <div class="image-upload-container" id="add_image_upload_container" onclick="document.getElementById('add_product_image').click()">
                    <input type="file" id="add_product_image" name="product_image" accept="image/*" class="hidden" onchange="previewImage(this, 'add_image_preview')">
                    <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-2"></i>
                    <p class="text-sm text-gray-600">Click to upload image</p>
                    <p class="text-xs text-gray-500 mt-1">PNG, JPG, WEBP up to 2MB</p>
                    <img id="add_image_preview" class="image-preview-large" src="" alt="Preview">
                </div>
            </div>
        </div>
        
        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <button type="button" onclick="closeAddModal()" 
                    class="px-6 py-3 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 transition-colors duration-200">
                Cancel
            </button>
            <button type="submit" 
                    class="btn-primary px-6 py-3 rounded-xl font-medium">
                <i class="fas fa-plus mr-2"></i>
                Add Product
            </button>
        </div>
    </form>
</div>
</div>
<!-- Edit Product Modal -->
<div id="editProductModal" class="fixed inset-0 z-50 hidden modal bg-black bg-opacity-50 flex items-center justify-center p-4">
    <div class="professional-card rounded-xl max-w-4xl w-full max-h-screen overflow-y-auto">
        <form id="editProductForm" action="../../backend/products/edit_product.php" method="POST" enctype="multipart/form-data" class="p-6">
            <input type="hidden" id="edit_product_id" name="product_id">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-semibold text-gray-800">Edit Product</h3>
                <button type="button" onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Basic Information -->
            <div class="md:col-span-2">
                <h4 class="text-md font-semibold text-gray-700 mb-3 pb-2 border-b">Basic Information</h4>
            </div>
            
            <div class="form-group">
                <label for="edit_category_id" class="form-label">Category *</label>
                <select id="edit_category_id" name="category_id" class="form-select" required onchange="loadProductLines('edit')">
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category['category_id']); ?>">
                            <?php echo htmlspecialchars($category['category_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="edit_product_line_id" class="form-label">Product Line *</label>
                <select id="edit_product_line_id" name="product_line_id" class="form-select" required>
                    <option value="">Select Product Line</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="edit_product_name" class="form-label">Product Name *</label>
                <input type="text" id="edit_product_name" name="product_name" class="form-input" required>
            </div>
            
            <div class="form-group">
                <label for="edit_product_code" class="form-label">Product Code *</label>
                <input type="text" id="edit_product_code" name="product_code" class="form-input" required>
            </div>
            
            <div class="form-group">
                <label for="edit_display_order" class="form-label">Display Order *</label>
                <input type="number" id="edit_display_order" name="display_order" class="form-input" min="0" required>
            </div>
            
            <div class="form-group md:col-span-2">
                <label for="edit_product_description" class="form-label">Description</label>
                <textarea id="edit_product_description" name="product_description" class="form-textarea"></textarea>
            </div>
            
            <!-- Pricing & Stock -->
            <div class="md:col-span-2">
                <h4 class="text-md font-semibold text-gray-700 mb-3 pb-2 border-b mt-4">Pricing & Stock</h4>
            </div>
            
            <div class="form-group">
                <label for="edit_price" class="form-label">Price (₱) *</label>
                <input type="number" id="edit_price" name="price" class="form-input" min="0" step="0.01" required>
            </div>
            
            <div class="form-group">
                <label for="edit_stock_quantity" class="form-label">Stock Quantity *</label>
                <input type="number" id="edit_stock_quantity" name="stock_quantity" class="form-input" min="0" required>
            </div>
            
            <div class="form-group">
                <label for="edit_min_stock_level" class="form-label">Minimum Stock Level *</label>
                <input type="number" id="edit_min_stock_level" name="min_stock_level" class="form-input" min="0" required>
            </div>
            
            <div class="form-group">
                <label for="edit_status" class="form-label">Status *</label>
                <select id="edit_status" name="status" class="form-select" required>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="discontinued">Discontinued</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label flex items-center">
                    <input type="checkbox" id="edit_is_featured" name="is_featured" class="form-checkbox mr-2" value="1">
                    <span>Featured Product</span>
                </label>
            </div>
            
            <!-- Product Image -->
            <div class="md:col-span-2">
                <h4 class="text-md font-semibold text-gray-700 mb-3 pb-2 border-b mt-4">Product Image</h4>
            </div>
            
            <div class="form-group md:col-span-2">
                <label class="form-label">Current Image</label>
                <div id="edit_current_image_container" class="mb-2">
                    <!-- Current image will be displayed here -->
                </div>
            </div>
            
            <div class="form-group md:col-span-2">
                <label for="edit_product_image" class="form-label">New Product Image</label>
                <div class="image-upload-container" id="edit_image_upload_container" onclick="document.getElementById('edit_product_image').click()">
                    <input type="file" id="edit_product_image" name="product_image" accept="image/*" class="hidden" onchange="previewImage(this, 'edit_image_preview')">
                    <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-2"></i>
                    <p class="text-sm text-gray-600">Click to upload new image</p>
                    <p class="text-xs text-gray-500 mt-1">PNG, JPG, WEBP up to 2MB</p>
                    <img id="edit_image_preview" class="image-preview-large" src="" alt="Preview">
                </div>
            </div>
        </div>
        
        <div class="flex justify-end space-x-3 mt-6">
            <button type="button" onclick="closeEditModal()" 
                    class="px-6 py-3 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 transition-colors duration-200">
                Cancel
            </button>
            <button type="submit" 
                    class="btn-primary px-6 py-3 rounded-xl font-medium">
                <i class="fas fa-save mr-2"></i>
                Update Product
            </button>
        </div>
    </form>
</div>
</div>
<!-- JavaScript Data and Functions -->
<script>
// Global variables
const productsData = <?php echo json_encode($products); ?>;
const productLinesData = <?php echo json_encode($product_lines); ?>;
let filteredData = [...productsData];
let currentPage = 1;
let recordsPerPage = 10;

// Initialize on page load
document.addEventListener("DOMContentLoaded", function() {
    console.log("DOM loaded, initializing filters and pagination");
    initializeFilters();
    initializePagination();
    updateDisplay();
});

// Filter initialization
function initializeFilters() {
    const searchInput = document.getElementById("search_products");
    const categoryFilter = document.getElementById("category_filter");
    const stockStatusFilter = document.getElementById("stock_status_filter");
    const statusFilter = document.getElementById("status_filter");

    function applyFilters() {
        const searchTerm = searchInput ? searchInput.value.toLowerCase() : "";
        const selectedCategory = categoryFilter ? categoryFilter.value : "";
        const selectedStockStatus = stockStatusFilter ? stockStatusFilter.value : "";
        const selectedStatus = statusFilter ? statusFilter.value : "";
        
        filteredData = productsData.filter(product => {
            const name = product.product_name.toLowerCase();
            const code = (product.product_code || "").toLowerCase();
            const description = (product.product_description || "").toLowerCase();
            const categoryId = product.category_id.toString();
            const stockStatus = product.stock_status;
            const status = product.status;

            const matchesSearch = searchTerm === "" || 
                                name.includes(searchTerm) || 
                                code.includes(searchTerm) ||
                                description.includes(searchTerm);
            
            const matchesCategory = selectedCategory === "" || categoryId === selectedCategory;
            const matchesStockStatus = selectedStockStatus === "" || stockStatus === selectedStockStatus;
            const matchesStatus = selectedStatus === "" || status === selectedStatus;

            return matchesSearch && matchesCategory && matchesStockStatus && matchesStatus;
        });

        currentPage = 1;
        updateDisplay();
    }

    if (searchInput) searchInput.addEventListener("input", applyFilters);
    if (categoryFilter) categoryFilter.addEventListener("change", applyFilters);
    if (stockStatusFilter) stockStatusFilter.addEventListener("change", applyFilters);
    if (statusFilter) statusFilter.addEventListener("change", applyFilters);
}

// Pagination initialization
function initializePagination() {
    const recordsPerPageSelect = document.getElementById("records_per_page");
    if (recordsPerPageSelect) {
        recordsPerPageSelect.addEventListener("change", function() {
            recordsPerPage = parseInt(this.value);
            currentPage = 1;
            updateDisplay();
        });
    }
}

// Load product lines based on selected category
function loadProductLines(modalType) {
    const categorySelect = document.getElementById(`${modalType}_category_id`);
    const productLineSelect = document.getElementById(`${modalType}_product_line_id`);
    
    if (!categorySelect || !productLineSelect) return;
    
    const selectedCategory = categorySelect.value;
    
    // Clear existing options except first one
    productLineSelect.innerHTML = '<option value="">Select Product Line</option>';
    
    if (selectedCategory) {
        // Filter product lines by category
        const filteredLines = productLinesData.filter(pl => pl.category_id == selectedCategory);
        
        filteredLines.forEach(line => {
            const option = document.createElement('option');
            option.value = line.product_line_id;
            option.textContent = line.product_line_name;
            productLineSelect.appendChild(option);
        });
    }
}

// Update display
function updateDisplay() {
    const totalRecords = filteredData.length;
    const totalPages = Math.ceil(totalRecords / recordsPerPage);
    const startIndex = (currentPage - 1) * recordsPerPage;
    const endIndex = Math.min(startIndex + recordsPerPage, totalRecords);

    // Update pagination info
    const paginationInfo = document.getElementById("pagination_info");
    if (paginationInfo) {
        if (totalRecords === 0) {
            paginationInfo.textContent = "Showing 0 to 0 of 0 records";
        } else {
            paginationInfo.textContent = `Showing ${startIndex + 1} to ${endIndex} of ${totalRecords} records`;
        }
    }

    // Display records for current page
    displayRecords(startIndex, endIndex);

    // Update pagination controls
    updatePaginationControls(totalPages);
}

// Display records
function displayRecords(startIndex, endIndex) {
    const productRows = document.querySelectorAll(".product-row");
    const productCards = document.querySelectorAll(".product-card");

    // Hide all rows and cards first
    productRows.forEach(row => row.style.display = "none");
    productCards.forEach(card => card.style.display = "none");

    // Show only records for current page
    filteredData.slice(startIndex, endIndex).forEach(product => {
        // Find matching row and card by product_id
        productRows.forEach(row => {
            if (row.getAttribute('data-product-id') == product.product_id) {
                row.style.display = "";
            }
        });

        productCards.forEach(card => {
            if (card.getAttribute('data-product-id') == product.product_id) {
                card.style.display = "";
            }
        });
    });
}

// Update pagination controls
function updatePaginationControls(totalPages) {
    const btnFirst = document.getElementById("btn_first");
    const btnPrev = document.getElementById("btn_prev");
    const btnNext = document.getElementById("btn_next");
    const btnLast = document.getElementById("btn_last");
    const pageNumbers = document.getElementById("page_numbers");

    // Enable/disable navigation buttons
    if (btnFirst) btnFirst.disabled = currentPage === 1;
    if (btnPrev) btnPrev.disabled = currentPage === 1;
    if (btnNext) btnNext.disabled = currentPage === totalPages || totalPages === 0;
    if (btnLast) btnLast.disabled = currentPage === totalPages || totalPages === 0;

    // Generate page numbers
    if (pageNumbers) {
        pageNumbers.innerHTML = '';
        
        if (totalPages === 0) {
            return;
        }

        // Show max 5 page numbers at a time
        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, startPage + 4);
        
        // Adjust if we're near the end
        if (endPage - startPage < 4) {
            startPage = Math.max(1, endPage - 4);
        }

        for (let i = startPage; i <= endPage; i++) {
            const pageBtn = document.createElement('button');
            pageBtn.textContent = i;
            pageBtn.className = `px-3 py-2 border rounded-lg transition-colors duration-200 ${
                i === currentPage 
                    ? 'bg-green-600 text-white border-green-600' 
                    : 'border-gray-300 text-gray-700 hover:bg-gray-50'
            }`;
            pageBtn.onclick = () => goToPage(i);
            pageNumbers.appendChild(pageBtn);
        }
    }
}

// Pagination functions
function goToPage(page) {
    const totalPages = Math.ceil(filteredData.length / recordsPerPage);
    if (page >= 1 && page <= totalPages) {
        currentPage = page;
        updateDisplay();
    }
}

function goToFirstPage() {
    goToPage(1);
}

function goToPreviousPage() {
    goToPage(currentPage - 1);
}

function goToNextPage() {
    goToPage(currentPage + 1);
}

function goToLastPage() {
    const totalPages = Math.ceil(filteredData.length / recordsPerPage);
    goToPage(totalPages);
}

// Image preview function
function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    const container = input.parentElement;
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.classList.add('show');
            container.classList.add('has-image');
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Modal functions
function openAddModal() {
    console.log("Opening add modal");
    const modal = document.getElementById("addProductModal");
    if (modal) {
        modal.classList.remove("hidden");
        document.getElementById("addProductForm").reset();
        
        // Reset image preview
        const preview = document.getElementById("add_image_preview");
        const container = document.getElementById("add_image_upload_container");
        if (preview) {
            preview.classList.remove('show');
            preview.src = '';
        }
        if (container) container.classList.remove('has-image');
        
        // Reset product lines dropdown
        document.getElementById("add_product_line_id").innerHTML = '<option value="">Select Product Line</option>';
        
        console.log("Add modal opened");
    } else {
        console.error("Add modal not found");
    }
}

function closeAddModal() {
    console.log("Closing add modal");
    const modal = document.getElementById("addProductModal");
    if (modal) {
        modal.classList.add("hidden");
        console.log("Add modal closed");
    }
}

function openEditModal(productId) {
    console.log("Opening edit modal for product ID:", productId);
    const modal = document.getElementById("editProductModal");
    
    if (!modal) {
        console.error("Edit modal not found");
        return;
    }
    
    modal.classList.remove("hidden");
    console.log("Edit modal opened, fetching product data");
    
    // Show loading state
    const form = document.getElementById("editProductForm");
    if (form) {
        form.style.opacity = "0.5";
        form.style.pointerEvents = "none";
    }
    
    fetch("../../backend/products/get_product.php?id=" + productId)
        .then(response => {
            console.log("Response received:", response);
            return response.json();
        })
        .then(data => {
            console.log("Product data received:", data);
            
            // Remove loading state
            if (form) {
                form.style.opacity = "1";
                form.style.pointerEvents = "auto";
            }
            
            if (data.success) {
                const product = data.product;
                
                document.getElementById("edit_product_id").value = product.product_id || "";
                document.getElementById("edit_category_id").value = product.category_id || "";
                
                // Load product lines for the selected category
                loadProductLines('edit');
                
                // Set product line after a brief delay to ensure options are loaded
                setTimeout(() => {
                    document.getElementById("edit_product_line_id").value = product.product_line_id || "";
                }, 100);
                
                document.getElementById("edit_product_name").value = product.product_name || "";
                document.getElementById("edit_product_code").value = product.product_code || "";
                document.getElementById("edit_product_description").value = product.product_description || "";
                document.getElementById("edit_price").value = product.price || "0.00";
                document.getElementById("edit_stock_quantity").value = product.stock_quantity || "0";
                document.getElementById("edit_min_stock_level").value = product.min_stock_level || "10";
                document.getElementById("edit_display_order").value = product.display_order || "0";
                document.getElementById("edit_status").value = product.status || "active";
                document.getElementById("edit_is_featured").checked = product.is_featured == 1;
                
                // Display current image
                const currentImageContainer = document.getElementById("edit_current_image_container");
                if (product.product_image) {
                    currentImageContainer.innerHTML = `
                        <img src="../../Assets/images/products/${product.product_image}" 
                             alt="${product.product_name}" 
                             class="product-image-preview">
                    `;
                } else {
                    currentImageContainer.innerHTML = '<p class="text-sm text-gray-500">No current image</p>';
                }
                
                // Reset new image preview
                const preview = document.getElementById("edit_image_preview");
                const container = document.getElementById("edit_image_upload_container");
                if (preview) {
                    preview.classList.remove('show');
                    preview.src = '';
                }
                if (container) container.classList.remove('has-image');
                
                console.log("Form populated successfully");
            } else {
                showAlertModal("Error: " + (data.message || "Failed to load product data"), 'error', 'Load Error');
                closeEditModal();
            }
        })
        .catch(error => {
            console.error("Error:", error);
            
            // Remove loading state
            if (form) {
                form.style.opacity = "1";
                form.style.pointerEvents = "auto";
            }
            
            showAlertModal("Error loading product data: " + error.message, 'error', 'Load Error');
            closeEditModal();
        });
}

function closeEditModal() {
    console.log("Closing edit modal");
    const modal = document.getElementById("editProductModal");
    if (modal) {
        modal.classList.add("hidden");
        console.log("Edit modal closed");
    }
}

async function toggleProductStatus(productId, currentStatus) {
    console.log("Toggle status for product:", productId, "Current status:", currentStatus);
    const newStatus = currentStatus === "active" ? "inactive" : "active";
    const action = newStatus === "active" ? "activate" : "deactivate";
    
    if (await showConfirmModal("Are you sure you want to " + action + " this product?", "Confirm Action")) {
        window.location.href = "../../backend/products/toggle_product_status.php?id=" + productId + "&status=" + newStatus;
    }
}

async function deleteProduct(productId) {
    console.log("Delete product:", productId);
    
    if (await showConfirmModal("Are you sure you want to delete this product? This action cannot be undone.", "Delete Product")) {
        window.location.href = "../../backend/products/delete_product.php?id=" + productId;
    }
}

// Close modals when clicking outside
document.addEventListener("click", function(event) {
    const addModal = document.getElementById("addProductModal");
    const editModal = document.getElementById("editProductModal");
    
    if (event.target === addModal) {
        closeAddModal();
    }
    if (event.target === editModal) {
        closeEditModal();
    }
});

// Debug: Check if elements exist
console.log("Checking for elements...");
console.log("Add Modal exists:", document.getElementById("addProductModal") !== null);
console.log("Edit Modal exists:", document.getElementById("editProductModal") !== null);
console.log("Products count:", productsData.length);
console.log("Product Lines count:", productLinesData.length);
</script>

<?php
$products_content = ob_get_clean();

// Set the content for app.php
$content = $products_content;

// Include the app.php layout
include 'app.php';
?>
