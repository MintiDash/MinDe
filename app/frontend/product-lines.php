<?php
/**
 * Product Lines Management Frontend
 * File: C:\xampp\htdocs\MinC_Project\app\frontend\product-lines.php
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

// Check if user has permission to access product lines management
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
$custom_title = 'Product Lines Management - MinC Project';

// Update user array to match app.php format
$user = [
    'full_name' => $user_data['name'],
    'user_type' => $user_data['user_type'],
    'is_logged_in' => isset($user_data['id'])
];

// Fetch product lines data
try {
    // Get all product lines with their category details
    $product_lines_query = "
        SELECT 
            pl.product_line_id,
            pl.category_id,
            pl.product_line_name,
            pl.product_line_description,
            pl.product_line_image,
            pl.display_order,
            pl.status,
            pl.created_at,
            pl.updated_at,
            c.category_name,
            c.category_slug
        FROM product_lines pl
        INNER JOIN categories c ON pl.category_id = c.category_id
        ORDER BY c.display_order ASC, pl.display_order ASC, pl.product_line_name ASC
    ";
    $product_lines_result = $pdo->query($product_lines_query);
    $product_lines = $product_lines_result->fetchAll(PDO::FETCH_ASSOC);

    // Get all categories for the dropdown
    $categories_query = "
        SELECT 
            c.category_id,
            c.category_name,
            c.category_slug,
            c.status
        FROM categories c
        WHERE c.status = 'active'
           OR c.category_id IN (SELECT DISTINCT category_id FROM product_lines)
        ORDER BY
            CASE WHEN c.status = 'active' THEN 0 ELSE 1 END,
            c.display_order ASC,
            c.category_name ASC
    ";
    $categories_result = $pdo->query($categories_query);
    $categories = $categories_result->fetchAll(PDO::FETCH_ASSOC);

    // Get distinct statuses for filter
    $statuses_query = "SELECT DISTINCT status FROM product_lines WHERE status IS NOT NULL ORDER BY status";
    $statuses_result = $pdo->query($statuses_query);
    $product_line_statuses = $statuses_result->fetchAll(PDO::FETCH_ASSOC);
    
    // If no statuses found, provide defaults
    if (empty($product_line_statuses)) {
        $product_line_statuses = [
            ['status' => 'active'],
            ['status' => 'inactive']
        ];
    }

} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error loading product lines data: ' . $e->getMessage();
    $product_lines = $categories = $product_line_statuses = [];
}

$default_subcategory_presets = [
    'car parts' => ['Back & Head Lights'],
    'external parts' => ['Side Mirrors', 'Wiper Blades', 'Door Handles & Locks', 'Truck Accessories', 'Window Visors'],
    'internal parts' => ['Horns & Components', 'Mobile Electronics', 'Switches & Relays'],
    'wheels & tyres' => ['Nuts', 'Tires', 'Wheel Accessories'],
];

$normalize_category_key = static function ($value) {
    $value = strtolower(trim((string)$value));
    $value = str_replace(['-', '_'], ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value);
    return $value;
};

$resolve_default_subcategories = static function ($categoryName, $categorySlug = '') use ($default_subcategory_presets, $normalize_category_key) {
    $nameKey = $normalize_category_key($categoryName);
    $slugKey = $normalize_category_key($categorySlug);

    if (isset($default_subcategory_presets[$nameKey])) {
        return $default_subcategory_presets[$nameKey];
    }

    if (isset($default_subcategory_presets[$slugKey])) {
        return $default_subcategory_presets[$slugKey];
    }

    if (strpos($nameKey, 'external') !== false) {
        return $default_subcategory_presets['external parts'];
    }
    if (strpos($nameKey, 'internal') !== false) {
        return $default_subcategory_presets['internal parts'];
    }
    if (strpos($nameKey, 'wheel') !== false) {
        return $default_subcategory_presets['wheels & tyres'];
    }
    if (strpos($nameKey, 'car') !== false) {
        return $default_subcategory_presets['car parts'];
    }

    return [];
};

// Build subcategory suggestion map by category.
// Merge order: DB presets -> existing product lines -> static fallback defaults.
$subcategory_presets_by_category = [];
$append_subcategory_options = static function (&$targetMap, $categoryId, array $options) {
    $categoryKey = (string)$categoryId;
    if ($categoryKey === '') {
        return;
    }

    if (!isset($targetMap[$categoryKey]) || !is_array($targetMap[$categoryKey])) {
        $targetMap[$categoryKey] = [];
    }

    $normalized_existing = array_map(static function ($value) {
        return strtolower(trim((string)$value));
    }, $targetMap[$categoryKey]);

    foreach ($options as $option) {
        $label = trim((string)$option);
        if ($label === '') {
            continue;
        }

        $normalized_label = strtolower($label);
        if (in_array($normalized_label, $normalized_existing, true)) {
            continue;
        }

        $targetMap[$categoryKey][] = $label;
        $normalized_existing[] = $normalized_label;
    }
};

foreach ($categories as $category) {
    $category_key = (string)($category['category_id'] ?? '');
    if ($category_key !== '' && !isset($subcategory_presets_by_category[$category_key])) {
        $subcategory_presets_by_category[$category_key] = [];
    }
}

// Load DB-defined presets if the table exists.
try {
    $table_check = $pdo->query("SHOW TABLES LIKE 'product_line_presets'");
    $has_preset_table = $table_check && (bool)$table_check->fetchColumn();

    if ($has_preset_table) {
        $preset_query = "
            SELECT p.category_id, p.preset_name
            FROM product_line_presets p
            INNER JOIN categories c ON p.category_id = c.category_id
            WHERE p.status = 'active' AND c.status = 'active'
            ORDER BY p.category_id ASC, p.display_order ASC, p.preset_name ASC
        ";
        $preset_result = $pdo->query($preset_query);
        $preset_rows = $preset_result->fetchAll(PDO::FETCH_ASSOC);

        foreach ($preset_rows as $row) {
            $append_subcategory_options(
                $subcategory_presets_by_category,
                $row['category_id'] ?? '',
                [$row['preset_name'] ?? '']
            );
        }
    }
} catch (Exception $e) {
    error_log('Subcategory preset table load failed in product-lines.php: ' . $e->getMessage());
}

// Include existing product line names as suggestions (keeps edit flow resilient).
try {
    $existing_names_query = "
        SELECT category_id, product_line_name
        FROM product_lines
        WHERE product_line_name IS NOT NULL
          AND TRIM(product_line_name) != ''
        ORDER BY category_id ASC, display_order ASC, product_line_name ASC
    ";
    $existing_names_result = $pdo->query($existing_names_query);
    $existing_name_rows = $existing_names_result->fetchAll(PDO::FETCH_ASSOC);

    foreach ($existing_name_rows as $row) {
        $append_subcategory_options(
            $subcategory_presets_by_category,
            $row['category_id'] ?? '',
            [$row['product_line_name'] ?? '']
        );
    }
} catch (Exception $e) {
    error_log('Product line name suggestion load failed in product-lines.php: ' . $e->getMessage());
}

// Fill remaining empty categories with static fallback defaults.
foreach ($categories as $category) {
    $category_key = (string)($category['category_id'] ?? '');
    if ($category_key === '') {
        continue;
    }

    if (!empty($subcategory_presets_by_category[$category_key])) {
        continue;
    }

    $defaults = $resolve_default_subcategories($category['category_name'] ?? '', $category['category_slug'] ?? '');
    $append_subcategory_options($subcategory_presets_by_category, $category_key, $defaults);
}

// Additional styles for product lines management specific elements
$additional_styles = '
.product-line-image-preview {
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
    min-width: 120px;
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

.desktop-table th:nth-child(4),
.desktop-table td:nth-child(4) {
    min-width: 150px;
}

.desktop-table th:nth-child(5),
.desktop-table td:nth-child(5) {
    min-width: 100px;
}

.desktop-table th:nth-child(6),
.desktop-table td:nth-child(6) {
    min-width: 100px;
}

.desktop-table th:last-child,
.desktop-table td:last-child {
    min-width: 140px;
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
';

// Product lines management content
ob_start();
?>

<!-- Page Header -->
<div class="professional-card rounded-xl p-6 mb-6 animate-fadeIn">
    <div class="flex flex-col md:flex-row md:items-center justify-between">
        <div class="mb-4 md:mb-0">
            <h2 class="text-2xl font-bold text-gray-800 mb-2 flex items-center">
                <i class="fas fa-boxes text-green-600 mr-3"></i>
                Product Lines Management
            </h2>
            <p class="text-gray-600">
                Manage product lines (subcategories) for your auto parts categories.
            </p>
        </div>
        <div class="flex items-center space-x-3">
            <button onclick="openAddModal()" class="btn-primary px-6 py-3 rounded-xl font-medium flex items-center">
                <i class="fas fa-plus mr-2"></i>
                Add New Product Line
            </button>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <div class="professional-card rounded-xl p-6 hover-lift">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 mb-1">Total Product Lines</p>
                <p class="text-3xl font-bold text-gray-900"><?php echo count($product_lines); ?></p>
            </div>
            <div class="p-4 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-xl shadow-lg">
                <i class="fas fa-boxes text-white text-2xl"></i>
            </div>
        </div>
    </div>

    <div class="professional-card rounded-xl p-6 hover-lift">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 mb-1">Active Product Lines</p>
                <p class="text-3xl font-bold text-green-600">
                    <?php echo count(array_filter($product_lines, function($pl) { return $pl['status'] === 'active'; })); ?>
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
                <p class="text-sm font-medium text-gray-600 mb-1">Inactive Product Lines</p>
                <p class="text-3xl font-bold text-red-600">
                    <?php echo count(array_filter($product_lines, function($pl) { return $pl['status'] === 'inactive'; })); ?>
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
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label for="search_product_lines" class="form-label">Search Product Lines</label>
            <div class="relative">
                <input type="text" id="search_product_lines" placeholder="Search by name or description..." 
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
                        <?php echo htmlspecialchars($category['category_name'] . (($category['status'] ?? 'active') === 'active' ? '' : ' (Inactive)')); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div>
            <label for="status_filter" class="form-label">Status</label>
            <select id="status_filter" class="form-select">
                <option value="">All Status</option>
                <?php foreach ($product_line_statuses as $status): ?>
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

<!-- Product Lines Table/Cards -->
<div class="professional-card table-container rounded-xl overflow-hidden animate-fadeIn">
    <!-- Desktop Table View -->
    <div class="desktop-table">
        <table class="w-full table-hover">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product Line</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Image</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($product_lines)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-boxes text-4xl mb-4 text-gray-300"></i>
                            <p>No product lines found.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($product_lines as $product_line): ?>
                        <tr class="product-line-row" 
                            data-name="<?php echo strtolower($product_line['product_line_name']); ?>" 
                            data-description="<?php echo strtolower($product_line['product_line_description'] ?? ''); ?>"
                            data-category-id="<?php echo $product_line['category_id']; ?>"
                            data-status="<?php echo $product_line['status']; ?>"
                            data-product-line-id="<?php echo $product_line['product_line_id']; ?>">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <i class="fas fa-grip-vertical drag-handle mr-2"></i>
                                    <span class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($product_line['display_order']); ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="category-badge">
                                    <?php echo htmlspecialchars($product_line['category_name']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($product_line['product_line_name']); ?>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <?php echo htmlspecialchars(substr($product_line['product_line_description'] ?? 'No description', 0, 50)) . (strlen($product_line['product_line_description'] ?? '') > 50 ? '...' : ''); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($product_line['product_line_image']): ?>
                                    <img src="../../Assets/images/product-lines/<?php echo htmlspecialchars($product_line['product_line_image']); ?>" 
                                         alt="<?php echo htmlspecialchars($product_line['product_line_name']); ?>" 
                                         class="product-line-image-preview">
                                <?php else: ?>
                                    <div class="product-line-image-preview bg-gray-200 flex items-center justify-center">
                                        <i class="fas fa-image text-gray-400"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="status-badge status-<?php echo $product_line['status']; ?>">
                                    <?php echo ucfirst($product_line['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center space-x-3">
                                    <button onclick="openEditModal(<?php echo $product_line['product_line_id']; ?>)" 
                                            class="text-blue-600 hover:text-blue-900 p-2 rounded-lg hover:bg-blue-50 transition-colors duration-200" 
                                            title="Edit Product Line">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="toggleProductLineStatus(<?php echo $product_line['product_line_id']; ?>, '<?php echo $product_line['status']; ?>')" 
                                            class="<?php echo $product_line['status'] === 'active' ? 'text-orange-600 hover:text-orange-900 hover:bg-orange-50' : 'text-green-600 hover:text-green-900 hover:bg-green-50'; ?> p-2 rounded-lg transition-colors duration-200" 
                                            title="<?php echo $product_line['status'] === 'active' ? 'Deactivate' : 'Activate'; ?> Product Line">
                                        <i class="fas fa-<?php echo $product_line['status'] === 'active' ? 'ban' : 'check-circle'; ?>"></i>
                                    </button>
                                    <button onclick="deleteProductLine(<?php echo $product_line['product_line_id']; ?>)" 
                                            class="text-red-600 hover:text-red-900 p-2 rounded-lg hover:bg-red-50 transition-colors duration-200" 
                                            title="Delete Product Line">
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
        <?php if (empty($product_lines)): ?>
            <div class="text-center py-12 text-gray-500">
                <i class="fas fa-boxes text-4xl mb-4 text-gray-300"></i>
                <p>No product lines found.</p>
            </div>
        <?php else: ?>
            <?php foreach ($product_lines as $product_line): ?>
                <div class="bg-white border border-gray-200 rounded-xl p-4 product-line-card" 
                     data-name="<?php echo strtolower($product_line['product_line_name']); ?>" 
                     data-description="<?php echo strtolower($product_line['product_line_description'] ?? ''); ?>"
                     data-category-id="<?php echo $product_line['category_id']; ?>"
                     data-status="<?php echo $product_line['status']; ?>"
                     data-product-line-id="<?php echo $product_line['product_line_id']; ?>">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center">
                            <?php if ($product_line['product_line_image']): ?>
                                <img src="../../Assets/images/product-lines/<?php echo htmlspecialchars($product_line['product_line_image']); ?>" 
                                     alt="<?php echo htmlspecialchars($product_line['product_line_name']); ?>" 
                                     class="product-line-image-preview mr-3">
                            <?php else: ?>
                                <div class="product-line-image-preview bg-gray-200 flex items-center justify-center mr-3">
                                    <i class="fas fa-image text-gray-400"></i>
                                </div>
                            <?php endif; ?>
                            <div>
                                <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($product_line['product_line_name']); ?></h4>
                            </div>
                        </div>
                        <span class="status-badge status-<?php echo $product_line['status']; ?>">
                            <?php echo ucfirst($product_line['status']); ?>
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <p class="text-xs text-gray-500 mb-1">Category</p>
                            <span class="category-badge"><?php echo htmlspecialchars($product_line['category_name']); ?></span>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-1">Display Order</p>
                            <p class="text-sm text-gray-900"><?php echo htmlspecialchars($product_line['display_order']); ?></p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-xs text-gray-500 mb-1">Description</p>
                            <p class="text-sm text-gray-900">
                                <?php echo htmlspecialchars(substr($product_line['product_line_description'] ?? 'No description', 0, 100)) . (strlen($product_line['product_line_description'] ?? '') > 100 ? '...' : ''); ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-2">
                        <button onclick="openEditModal(<?php echo $product_line['product_line_id']; ?>)" 
                                class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors duration-200" 
                                title="Edit Product Line">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="toggleProductLineStatus(<?php echo $product_line['product_line_id']; ?>, '<?php echo $product_line['status']; ?>')" 
                                class="p-2 <?php echo $product_line['status'] === 'active' ? 'text-orange-600 hover:bg-orange-50' : 'text-green-600 hover:bg-green-50'; ?> rounded-lg transition-colors duration-200" 
                                title="<?php echo $product_line['status'] === 'active' ? 'Deactivate' : 'Activate'; ?> Product Line">
                            <i class="fas fa-<?php echo $product_line['status'] === 'active' ? 'ban' : 'check-circle'; ?>"></i>
                        </button>
                        <button onclick="deleteProductLine(<?php echo $product_line['product_line_id']; ?>)" 
                                class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors duration-200" 
                                title="Delete Product Line">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add Product Line Modal -->
<div id="addProductLineModal" class="fixed inset-0 z-50 hidden modal bg-black bg-opacity-50 flex items-center justify-center p-4">
    <div class="professional-card rounded-xl max-w-2xl w-full max-h-screen overflow-y-auto">
        <form id="addProductLineForm" action="../../backend/product-lines/add_product_line.php" method="POST" enctype="multipart/form-data" class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-semibold text-gray-800">Add New Product Line</h3>
                <button type="button" onclick="closeAddModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
                            </div>
                            <div class="space-y-4">
            <div class="form-group">
                <label for="add_category_id" class="form-label">Category *</label>
                <select id="add_category_id" name="category_id" class="form-select" required>
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category['category_id']); ?>">
                            <?php echo htmlspecialchars($category['category_name'] . (($category['status'] ?? 'active') === 'active' ? '' : ' (Inactive)')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="add_product_line_name" class="form-label">Subcategory *</label>
                <input type="text"
                       id="add_product_line_name"
                       name="product_line_name"
                       class="form-input"
                       list="add_product_line_name_suggestions"
                       placeholder="Select or type a subcategory"
                       maxlength="255"
                       autocomplete="off"
                       required>
                <datalist id="add_product_line_name_suggestions"></datalist>
                <p class="text-xs text-gray-500 mt-1">Suggestions update by category. You can type a new subcategory if needed.</p>
            </div>
            <div class="form-group">
                <label for="add_product_line_description" class="form-label">Description</label>
                <textarea id="add_product_line_description" name="product_line_description" class="form-textarea"></textarea>
            </div>
            <div class="form-group">
                <label for="add_display_order" class="form-label">Display Order *</label>
                <input type="number" id="add_display_order" name="display_order" class="form-input" min="0" value="0" required>
                <p class="text-xs text-gray-500 mt-1">Lower numbers appear first</p>
            </div>
            <div class="form-group">
                <label for="add_product_line_image" class="form-label">Product Line Image</label>
                <div class="image-upload-container" id="add_image_upload_container" onclick="document.getElementById('add_product_line_image').click()">
                    <input type="file" id="add_product_line_image" name="product_line_image" accept="image/*" class="hidden" onchange="previewImage(this, 'add_image_preview')">
                    <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-2"></i>
                    <p class="text-sm text-gray-600">Click to upload image</p>
                    <p class="text-xs text-gray-500 mt-1">PNG, JPG, WEBP up to 2MB</p>
                    <img id="add_image_preview" class="image-preview-large" src="" alt="Preview">
                </div>
            </div>
        </div>
        <div class="flex justify-end space-x-3 mt-6">
            <button type="button" onclick="closeAddModal()" 
                    class="px-6 py-3 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 transition-colors duration-200">
                Cancel
            </button>
            <button type="submit" 
                    class="btn-primary px-6 py-3 rounded-xl font-medium">
                <i class="fas fa-plus mr-2"></i>
                Add Product Line
            </button>
        </div>
    </form>
</div>
</div>


<!-- Edit Product Line Modal -->
<div id="editProductLineModal" class="fixed inset-0 z-50 hidden modal bg-black bg-opacity-50 flex items-center justify-center p-4">
    <div class="professional-card rounded-xl max-w-2xl w-full max-h-screen overflow-y-auto">
        <form id="editProductLineForm" action="../../backend/product-lines/edit_product_line.php" method="POST" enctype="multipart/form-data" class="p-6">
            <input type="hidden" id="edit_product_line_id" name="product_line_id">
            <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-semibold text-gray-800">Edit Product Line</h3>
            <button type="button" onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <div class="space-y-4">
            <div class="form-group">
                <label for="edit_category_id" class="form-label">Category *</label>
                <select id="edit_category_id" name="category_id" class="form-select" required>
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category['category_id']); ?>">
                            <?php echo htmlspecialchars($category['category_name'] . (($category['status'] ?? 'active') === 'active' ? '' : ' (Inactive)')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="edit_product_line_name" class="form-label">Subcategory *</label>
                <input type="text"
                       id="edit_product_line_name"
                       name="product_line_name"
                       class="form-input"
                       list="edit_product_line_name_suggestions"
                       placeholder="Select or type a subcategory"
                       maxlength="255"
                       autocomplete="off"
                       required>
                <datalist id="edit_product_line_name_suggestions"></datalist>
                <p class="text-xs text-gray-500 mt-1">Suggestions update by category. You can type a new subcategory if needed.</p>
            </div>
            
            <div class="form-group">
                <label for="edit_product_line_description" class="form-label">Description</label>
                <textarea id="edit_product_line_description" name="product_line_description" class="form-textarea"></textarea>
            </div>
            
            <div class="form-group">
                <label for="edit_display_order" class="form-label">Display Order *</label>
                <input type="number" id="edit_display_order" name="display_order" class="form-input" min="0" required>
                <p class="text-xs text-gray-500 mt-1">Lower numbers appear first</p>
            </div>
            
            <div class="form-group">
                <label class="form-label">Current Image</label>
                <div id="edit_current_image_container" class="mb-2">
                    <!-- Current image will be displayed here -->
                </div>
            </div>
            
            <div class="form-group">
                <label for="edit_product_line_image" class="form-label">New Product Line Image</label>
                <div class="image-upload-container" id="edit_image_upload_container" onclick="document.getElementById('edit_product_line_image').click()">
                    <input type="file" id="edit_product_line_image" name="product_line_image" accept="image/*" class="hidden" onchange="previewImage(this, 'edit_image_preview')">
                    <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-2"></i>
                    <p class="text-sm text-gray-600">Click to upload new image</p>
                    <p class="text-xs text-gray-500 mt-1">PNG, JPG, WEBP up to 2MB</p>
                    <img id="edit_image_preview" class="image-preview-large" src="" alt="Preview">
                </div>
            </div>
            
            <div class="form-group">
                <label for="edit_status" class="form-label">Status *</label>
                <select id="edit_status" name="status" class="form-select" required>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
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
                Update Product Line
            </button>
        </div>
    </form>
</div>
</div>
<!-- JavaScript Data and Functions -->
<script>
// Global variables
const productLinesData = <?php echo json_encode($product_lines); ?>;
const subcategoryPresetsByCategory = <?php echo json_encode($subcategory_presets_by_category); ?>;
let filteredData = [...productLinesData];
let currentPage = 1;
let recordsPerPage = 10;

function getSubcategoryOptionsByCategoryId(categoryId) {
    const key = String(categoryId || '').trim();
    if (!key) return [];
    const options = subcategoryPresetsByCategory[key];
    return Array.isArray(options) ? options : [];
}

function renderSubcategorySuggestions(datalistElement, categoryId) {
    if (!datalistElement) return;

    datalistElement.innerHTML = '';
    const options = getSubcategoryOptionsByCategoryId(categoryId);

    options.forEach(optionName => {
        const option = document.createElement('option');
        option.value = optionName;
        datalistElement.appendChild(option);
    });
}

// Initialize on page load
document.addEventListener("DOMContentLoaded", function() {
    console.log("DOM loaded, initializing filters and pagination");
    initializeFilters();
    initializePagination();
    initializeSubcategorySelectors();
    updateDisplay();
});

function initializeSubcategorySelectors() {
    const addCategorySelect = document.getElementById("add_category_id");
    const addSubcategoryInput = document.getElementById("add_product_line_name");
    const addSubcategoryDatalist = document.getElementById("add_product_line_name_suggestions");
    const editCategorySelect = document.getElementById("edit_category_id");
    const editSubcategoryInput = document.getElementById("edit_product_line_name");
    const editSubcategoryDatalist = document.getElementById("edit_product_line_name_suggestions");

    if (addCategorySelect && addSubcategoryDatalist) {
        addCategorySelect.addEventListener("change", function() {
            renderSubcategorySuggestions(addSubcategoryDatalist, this.value);
            if (addSubcategoryInput) {
                addSubcategoryInput.value = '';
            }
        });
        renderSubcategorySuggestions(addSubcategoryDatalist, addCategorySelect.value);
    }

    if (editCategorySelect && editSubcategoryDatalist) {
        editCategorySelect.addEventListener("change", function() {
            renderSubcategorySuggestions(editSubcategoryDatalist, this.value);
            if (editSubcategoryInput) {
                editSubcategoryInput.value = '';
            }
        });
        renderSubcategorySuggestions(editSubcategoryDatalist, editCategorySelect.value);
    }
}

// Filter initialization
function initializeFilters() {
    const searchInput = document.getElementById("search_product_lines");
    const categoryFilter = document.getElementById("category_filter");
    const statusFilter = document.getElementById("status_filter");

    function applyFilters() {
        const searchTerm = searchInput ? searchInput.value.toLowerCase() : "";
        const selectedCategory = categoryFilter ? categoryFilter.value : "";
        const selectedStatus = statusFilter ? statusFilter.value : "";
        
        filteredData = productLinesData.filter(productLine => {
            const name = productLine.product_line_name.toLowerCase();
            const description = (productLine.product_line_description || "").toLowerCase();
            const categoryId = productLine.category_id.toString();
            const status = productLine.status;

            const matchesSearch = searchTerm === "" || 
                                name.includes(searchTerm) || 
                                description.includes(searchTerm);
            
            const matchesCategory = selectedCategory === "" || categoryId === selectedCategory;
            const matchesStatus = selectedStatus === "" || status === selectedStatus;

            return matchesSearch && matchesCategory && matchesStatus;
        });

        currentPage = 1;
        updateDisplay();
    }

    if (searchInput) searchInput.addEventListener("input", applyFilters);
    if (categoryFilter) categoryFilter.addEventListener("change", applyFilters);
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
    const productLineRows = document.querySelectorAll(".product-line-row");
    const productLineCards = document.querySelectorAll(".product-line-card");

    // Hide all rows and cards first
    productLineRows.forEach(row => row.style.display = "none");
    productLineCards.forEach(card => card.style.display = "none");

    // Show only records for current page
    filteredData.slice(startIndex, endIndex).forEach(productLine => {
        // Find matching row and card by product_line_id
        productLineRows.forEach(row => {
            if (row.getAttribute('data-product-line-id') == productLine.product_line_id) {
                row.style.display = "";
            }
        });

        productLineCards.forEach(card => {
            if (card.getAttribute('data-product-line-id') == productLine.product_line_id) {
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
    const modal = document.getElementById("addProductLineModal");
    if (modal) {
        modal.classList.remove("hidden");
        document.getElementById("addProductLineForm").reset();
        
        // Reset image preview
        const preview = document.getElementById("add_image_preview");
        const container = document.getElementById("add_image_upload_container");
        if (preview) {
            preview.classList.remove('show');
            preview.src = '';
        }
        if (container) container.classList.remove('has-image');

        const addCategorySelect = document.getElementById("add_category_id");
        const addSubcategoryInput = document.getElementById("add_product_line_name");
        const addSubcategoryDatalist = document.getElementById("add_product_line_name_suggestions");
        if (addCategorySelect && addSubcategoryDatalist) {
            renderSubcategorySuggestions(addSubcategoryDatalist, addCategorySelect.value);
        }
        if (addSubcategoryInput) {
            addSubcategoryInput.value = '';
        }
        
        console.log("Add modal opened");
    } else {
        console.error("Add modal not found");
    }
}

function closeAddModal() {
    console.log("Closing add modal");
    const modal = document.getElementById("addProductLineModal");
    if (modal) {
        modal.classList.add("hidden");
        console.log("Add modal closed");
    }
}

function openEditModal(productLineId) {
    console.log("Opening edit modal for product line ID:", productLineId);
    const modal = document.getElementById("editProductLineModal");
    
    if (!modal) {
        console.error("Edit modal not found");
        return;
    }
    
    modal.classList.remove("hidden");
    console.log("Edit modal opened, fetching product line data");
    
    // Show loading state
    const form = document.getElementById("editProductLineForm");
    if (form) {
        form.style.opacity = "0.5";
        form.style.pointerEvents = "none";
    }
    
    fetch("../../backend/product-lines/get_product_line.php?id=" + productLineId)
        .then(response => {
            console.log("Response received:", response);
            return response.json();
        })
        .then(data => {
            console.log("Product line data received:", data);
            
            // Remove loading state
            if (form) {
                form.style.opacity = "1";
                form.style.pointerEvents = "auto";
            }
            
            if (data.success) {
                const productLine = data.product_line;
                
                document.getElementById("edit_product_line_id").value = productLine.product_line_id || "";
                document.getElementById("edit_category_id").value = productLine.category_id || "";
                renderSubcategorySuggestions(
                    document.getElementById("edit_product_line_name_suggestions"),
                    productLine.category_id || ""
                );
                document.getElementById("edit_product_line_name").value = productLine.product_line_name || "";
                document.getElementById("edit_product_line_description").value = productLine.product_line_description || "";
                document.getElementById("edit_display_order").value = productLine.display_order || "0";
                document.getElementById("edit_status").value = productLine.status || "active";
                
                // Display current image
                const currentImageContainer = document.getElementById("edit_current_image_container");
                if (productLine.product_line_image) {
                    currentImageContainer.innerHTML = `
                        <img src="../../Assets/images/product-lines/${productLine.product_line_image}" 
                             alt="${productLine.product_line_name}" 
                             class="product-line-image-preview">
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
                showAlertModal("Error: " + (data.message || "Failed to load product line data"), 'error', 'Load Error');
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
            
            showAlertModal("Error loading product line data: " + error.message, 'error', 'Load Error');
            closeEditModal();
        });
}

function closeEditModal() {
    console.log("Closing edit modal");
    const modal = document.getElementById("editProductLineModal");
    if (modal) {
        modal.classList.add("hidden");
        console.log("Edit modal closed");
    }
}

async function toggleProductLineStatus(productLineId, currentStatus) {
    console.log("Toggle status for product line:", productLineId, "Current status:", currentStatus);
    const newStatus = currentStatus === "active" ? "inactive" : "active";
    const action = newStatus === "active" ? "activate" : "deactivate";
    
    if (await showConfirmModal("Are you sure you want to " + action + " this product line?", "Confirm Action")) {
        window.location.href = "../../backend/product-lines/toggle_product_line_status.php?id=" + productLineId + "&status=" + newStatus;
    }
}

async function deleteProductLine(productLineId) {
    console.log("Delete product line:", productLineId);
    
    if (await showConfirmModal("Are you sure you want to delete this product line? This action cannot be undone.", "Delete Product Line")) {
        window.location.href = "../../backend/product-lines/delete_product_line.php?id=" + productLineId;
    }
}

// Close modals when clicking outside
document.addEventListener("click", function(event) {
    const addModal = document.getElementById("addProductLineModal");
    const editModal = document.getElementById("editProductLineModal");
    
    if (event.target === addModal) {
        closeAddModal();
    }
    if (event.target === editModal) {
        closeEditModal();
    }
});

// Debug: Check if elements exist
console.log("Checking for elements...");
console.log("Add Modal exists:", document.getElementById("addProductLineModal") !== null);
console.log("Edit Modal exists:", document.getElementById("editProductLineModal") !== null);
console.log("Product lines count:", productLinesData.length);
</script>
<?php
$product_lines_content = ob_get_clean();

// Set the content for app.php
$content = $product_lines_content;

// Include the app.php layout
include 'app.php';
?>
