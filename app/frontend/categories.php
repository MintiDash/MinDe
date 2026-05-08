<?php
/**
 * Categories Management Frontend
 * File: C:\xampp\htdocs\MinC_Project\app\frontend\categories.php
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

// Check if user has permission to access categories management
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
$custom_title = 'Categories Management - MinC Project';

// Update user array to match app.php format
$user = [
    'full_name' => $user_data['name'],
    'user_type' => $user_data['user_type'],
    'is_logged_in' => isset($user_data['id'])
];

// Fetch categories data
try {
    // Get all categories with their details
    $categories_query = "
        SELECT 
            category_id,
            category_name,
            category_description,
            category_image,
            display_order,
            status,
            created_at,
            updated_at
        FROM categories
        ORDER BY display_order ASC, category_name ASC
    ";
    $categories_result = $pdo->query($categories_query);
    $categories = $categories_result->fetchAll(PDO::FETCH_ASSOC);

    // Get distinct statuses for filter
    $statuses_query = "SELECT DISTINCT status FROM categories WHERE status IS NOT NULL ORDER BY status";
    $statuses_result = $pdo->query($statuses_query);
    $category_statuses = $statuses_result->fetchAll(PDO::FETCH_ASSOC);
    
    // If no statuses found, provide defaults
    if (empty($category_statuses)) {
        $category_statuses = [
            ['status' => 'active'],
            ['status' => 'inactive']
        ];
    }

} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error loading categories data: ' . $e->getMessage();
    $categories = $category_statuses = [];
}

// Additional styles for categories management specific elements
$additional_styles = '
.category-image-preview {
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
    min-width: 200px;
}

.desktop-table th:nth-child(3),
.desktop-table td:nth-child(3) {
    min-width: 150px;
}

.desktop-table th:nth-child(4),
.desktop-table td:nth-child(4) {
    min-width: 100px;
}

.desktop-table th:nth-child(5),
.desktop-table td:nth-child(5) {
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
';

// Categories management content
ob_start();
?>

<!-- Page Header -->
<div class="professional-card rounded-xl p-6 mb-6 animate-fadeIn">
    <div class="flex flex-col md:flex-row md:items-center justify-between">
        <div class="mb-4 md:mb-0">
            <h2 class="text-2xl font-bold text-gray-800 mb-2 flex items-center">
                <i class="fas fa-layer-group text-green-600 mr-3"></i>
                Categories Management
            </h2>
            <p class="text-gray-600">
                Manage product categories for your auto parts store.
            </p>
        </div>
        <div class="flex items-center space-x-3">
            <button onclick="openAddModal()" class="btn-primary px-6 py-3 rounded-xl font-medium flex items-center">
                <i class="fas fa-plus mr-2"></i>
                Add New Category
            </button>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <div class="professional-card rounded-xl p-6 hover-lift">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 mb-1">Total Categories</p>
                <p class="text-3xl font-bold text-gray-900"><?php echo count($categories); ?></p>
            </div>
            <div class="p-4 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-xl shadow-lg">
                <i class="fas fa-layer-group text-white text-2xl"></i>
            </div>
        </div>
    </div>

    <div class="professional-card rounded-xl p-6 hover-lift">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 mb-1">Active Categories</p>
                <p class="text-3xl font-bold text-green-600">
                    <?php echo count(array_filter($categories, function($c) { return $c['status'] === 'active'; })); ?>
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
                <p class="text-sm font-medium text-gray-600 mb-1">Inactive Categories</p>
                <p class="text-3xl font-bold text-red-600">
                    <?php echo count(array_filter($categories, function($c) { return $c['status'] === 'inactive'; })); ?>
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
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label for="search_categories" class="form-label">Search Categories</label>
            <div class="relative">
                <input type="text" id="search_categories" placeholder="Search by name or description..." 
                       class="form-input pl-10">
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
            </div>
        </div>
        
        <div>
            <label for="status_filter" class="form-label">Status</label>
            <select id="status_filter" class="form-input">
                <option value="">All Status</option>
                <?php foreach ($category_statuses as $status): ?>
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

<!-- Categories Table/Cards -->
<div class="professional-card table-container rounded-xl overflow-hidden animate-fadeIn">
    <!-- Desktop Table View -->
    <div class="desktop-table">
        <table class="w-full table-hover">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Image</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($categories)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-layer-group text-4xl mb-4 text-gray-300"></i>
                            <p>No categories found.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($categories as $category): ?>
                        <tr class="category-row" 
                            data-name="<?php echo strtolower($category['category_name']); ?>" 
                            data-description="<?php echo strtolower($category['category_description'] ?? ''); ?>"
                            data-status="<?php echo $category['status']; ?>"
                            data-category-id="<?php echo $category['category_id']; ?>">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <i class="fas fa-grip-vertical drag-handle mr-2"></i>
                                    <span class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($category['display_order']); ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($category['category_name']); ?>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <?php echo htmlspecialchars(substr($category['category_description'] ?? 'No description', 0, 50)) . (strlen($category['category_description'] ?? '') > 50 ? '...' : ''); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($category['category_image']): ?>
                                    <img src="../../Assets/images/categories/<?php echo htmlspecialchars($category['category_image']); ?>" 
                                         alt="<?php echo htmlspecialchars($category['category_name']); ?>" 
                                         class="category-image-preview">
                                <?php else: ?>
                                    <div class="category-image-preview bg-gray-200 flex items-center justify-center">
                                        <i class="fas fa-image text-gray-400"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="status-badge status-<?php echo $category['status']; ?>">
                                    <?php echo ucfirst($category['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center space-x-3">
                                    <button onclick="openEditModal(<?php echo $category['category_id']; ?>)" 
                                            class="text-blue-600 hover:text-blue-900 p-2 rounded-lg hover:bg-blue-50 transition-colors duration-200" 
                                            title="Edit Category">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="toggleCategoryStatus(<?php echo $category['category_id']; ?>, '<?php echo $category['status']; ?>')" 
                                            class="<?php echo $category['status'] === 'active' ? 'text-orange-600 hover:text-orange-900 hover:bg-orange-50' : 'text-green-600 hover:text-green-900 hover:bg-green-50'; ?> p-2 rounded-lg transition-colors duration-200" 
                                            title="<?php echo $category['status'] === 'active' ? 'Deactivate' : 'Activate'; ?> Category">
                                        <i class="fas fa-<?php echo $category['status'] === 'active' ? 'ban' : 'check-circle'; ?>"></i>
                                    </button>
                                    <button onclick="deleteCategory(<?php echo $category['category_id']; ?>)" 
                                            class="text-red-600 hover:text-red-900 p-2 rounded-lg hover:bg-red-50 transition-colors duration-200" 
                                            title="Delete Category">
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
        <?php if (empty($categories)): ?>
            <div class="text-center py-12 text-gray-500">
                <i class="fas fa-layer-group text-4xl mb-4 text-gray-300"></i>
                <p>No categories found.</p>
            </div>
        <?php else: ?>
            <?php foreach ($categories as $category): ?>
                <div class="bg-white border border-gray-200 rounded-xl p-4 category-card" 
                     data-name="<?php echo strtolower($category['category_name']); ?>" 
                     data-description="<?php echo strtolower($category['category_description'] ?? ''); ?>"
                     data-status="<?php echo $category['status']; ?>"
                     data-category-id="<?php echo $category['category_id']; ?>">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center">
                            <?php if ($category['category_image']): ?>
                                <img src="../../Assets/images/categories/<?php echo htmlspecialchars($category['category_image']); ?>" 
                                     alt="<?php echo htmlspecialchars($category['category_name']); ?>" 
                                     class="category-image-preview mr-3">
                            <?php else: ?>
                                <div class="category-image-preview bg-gray-200 flex items-center justify-center mr-3">
                                    <i class="fas fa-image text-gray-400"></i>
                                </div>
                            <?php endif; ?>
                            <div>
                                <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($category['category_name']); ?></h4>
                            </div>
                        </div>
                        <span class="status-badge status-<?php echo $category['status']; ?>">
                            <?php echo ucfirst($category['status']); ?>
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <p class="text-xs text-gray-500 mb-1">Display Order</p>
                            <p class="text-sm text-gray-900"><?php echo htmlspecialchars($category['display_order']); ?></p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-xs text-gray-500 mb-1">Description</p>
                            <p class="text-sm text-gray-900">
                                <?php echo htmlspecialchars(substr($category['category_description'] ?? 'No description', 0, 100)) . (strlen($category['category_description'] ?? '') > 100 ? '...' : ''); ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-2">
                        <button onclick="openEditModal(<?php echo $category['category_id']; ?>)" 
                                class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors duration-200" 
                                title="Edit Category">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="toggleCategoryStatus(<?php echo $category['category_id']; ?>, '<?php echo $category['status']; ?>')" 
                                class="p-2 <?php echo $category['status'] === 'active' ? 'text-orange-600 hover:bg-orange-50' : 'text-green-600 hover:bg-green-50'; ?> rounded-lg transition-colors duration-200" 
                                title="<?php echo $category['status'] === 'active' ? 'Deactivate' : 'Activate'; ?> Category">
                            <i class="fas fa-<?php echo $category['status'] === 'active' ? 'ban' : 'check-circle'; ?>"></i>
                        </button>
                        <button onclick="deleteCategory(<?php echo $category['category_id']; ?>)" 
                                class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors duration-200" 
                                title="Delete Category">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add Category Modal -->
<div id="addCategoryModal" class="fixed inset-0 z-50 hidden modal bg-black bg-opacity-50 flex items-center justify-center p-4">
    <div class="professional-card rounded-xl max-w-2xl w-full max-h-screen overflow-y-auto">
        <form id="addCategoryForm" action="../../backend/categories/add_category.php" method="POST" enctype="multipart/form-data" class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-semibold text-gray-800">Add New Category</h3>
                <button type="button" onclick="closeAddModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="space-y-4">
                <div class="form-group">
                    <label for="add_category_name" class="form-label">Category Name *</label>
                    <input type="text" id="add_category_name" name="category_name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label for="add_category_description" class="form-label">Description</label>
                    <textarea id="add_category_description" name="category_description" class="form-textarea"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="add_display_order" class="form-label">Display Order *</label>
                    <input type="number" id="add_display_order" name="display_order" class="form-input" min="0" value="0" required>
                    <p class="text-xs text-gray-500 mt-1">Lower numbers appear first</p>
                </div>
                
                <div class="form-group">
                    <label for="add_category_image" class="form-label">Category Image</label>
                    <div class="image-upload-container" id="add_image_upload_container" onclick="document.getElementById('add_category_image').click()">
                        <input type="file" id="add_category_image" name="category_image" accept="image/*" class="hidden" onchange="previewImage(this, 'add_image_preview')">
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
                    Add Category
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Category Modal -->
<div id="editCategoryModal" class="fixed inset-0 z-50 hidden modal bg-black bg-opacity-50 flex items-center justify-center p-4">
    <div class="professional-card rounded-xl max-w-2xl w-full max-h-screen overflow-y-auto">
        <form id="editCategoryForm" action="../../backend/categories/edit_category.php" method="POST" enctype="multipart/form-data" class="p-6">
            <input type="hidden" id="edit_category_id" name="category_id">
            
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-semibold text-gray-800">Edit Category</h3>
                <button type="button" onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="space-y-4">
                <div class="form-group">
                    <label for="edit_category_name" class="form-label">Category Name *</label>
                    <input type="text" id="edit_category_name" name="category_name" class="form-input" required>
                </div>
            <div class="form-group">
                <label for="edit_category_description" class="form-label">Description</label>
                <textarea id="edit_category_description" name="category_description" class="form-textarea"></textarea>
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
                <label for="edit_category_image" class="form-label">New Category Image</label>
                <div class="image-upload-container" id="edit_image_upload_container" onclick="document.getElementById('edit_category_image').click()">
                    <input type="file" id="edit_category_image" name="category_image" accept="image/*" class="hidden" onchange="previewImage(this, 'edit_image_preview')">
                    <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-2"></i>
                    <p class="text-sm text-gray-600">Click to upload new image</p>
                    <p class="text-xs text-gray-500 mt-1">PNG, JPG, WEBP up to 2MB</p>
                    <img id="edit_image_preview" class="image-preview-large" src="" alt="Preview">
                </div>
            </div>
            <div class="form-group">
                <label for="edit_status" class="form-label">Status *</label>
                <select id="edit_status" name="status" class="form-input" required>
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
                Update Category
            </button>
        </div>
    </form>
</div>
</div>
<!-- JavaScript Data and Functions -->
<script>
// Global variables
const categoriesData = <?php echo json_encode($categories); ?>;
let filteredData = [...categoriesData];
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
    const searchInput = document.getElementById("search_categories");
    const statusFilter = document.getElementById("status_filter");

    function applyFilters() {
        const searchTerm = searchInput ? searchInput.value.toLowerCase() : "";
        const selectedStatus = statusFilter ? statusFilter.value : "";
        
        filteredData = categoriesData.filter(category => {
            const name = category.category_name.toLowerCase();
            const description = (category.category_description || "").toLowerCase();
            const status = category.status;

            const matchesSearch = searchTerm === "" || 
                                name.includes(searchTerm) || 
                                description.includes(searchTerm);
            
            const matchesStatus = selectedStatus === "" || status === selectedStatus;

            return matchesSearch && matchesStatus;
        });

        currentPage = 1;
        updateDisplay();
    }

    if (searchInput) searchInput.addEventListener("input", applyFilters);
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
    const categoryRows = document.querySelectorAll(".category-row");
    const categoryCards = document.querySelectorAll(".category-card");

    // Hide all rows and cards first
    categoryRows.forEach(row => row.style.display = "none");
    categoryCards.forEach(card => card.style.display = "none");

    // Show only records for current page
    filteredData.slice(startIndex, endIndex).forEach(category => {
        // Find matching row and card by category_id
        categoryRows.forEach(row => {
            if (row.getAttribute('data-category-id') == category.category_id) {
                row.style.display = "";
            }
        });

        categoryCards.forEach(card => {
            if (card.getAttribute('data-category-id') == category.category_id) {
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
    const modal = document.getElementById("addCategoryModal");
    if (modal) {
        modal.classList.remove("hidden");
        document.getElementById("addCategoryForm").reset();
        
        // Reset image preview
        const preview = document.getElementById("add_image_preview");
        const container = document.getElementById("add_image_upload_container");
        if (preview) {
            preview.classList.remove('show');
            preview.src = '';
        }
        if (container) container.classList.remove('has-image');
        
        console.log("Add modal opened");
    } else {
        console.error("Add modal not found");
    }
}

function closeAddModal() {
    console.log("Closing add modal");
    const modal = document.getElementById("addCategoryModal");
    if (modal) {
        modal.classList.add("hidden");
        console.log("Add modal closed");
    }
}

function openEditModal(categoryId) {
    console.log("Opening edit modal for category ID:", categoryId);
    const modal = document.getElementById("editCategoryModal");
    
    if (!modal) {
        console.error("Edit modal not found");
        return;
    }
    
    modal.classList.remove("hidden");
    console.log("Edit modal opened, fetching category data");
    
    // Show loading state
    const form = document.getElementById("editCategoryForm");
    if (form) {
        form.style.opacity = "0.5";
        form.style.pointerEvents = "none";
    }
    
    fetch("../../backend/categories/get_category.php?id=" + categoryId)
        .then(response => {
            console.log("Response received:", response);
            return response.json();
        })
        .then(data => {
            console.log("Category data received:", data);
            
            // Remove loading state
            if (form) {
                form.style.opacity = "1";
                form.style.pointerEvents = "auto";
            }
            
            if (data.success) {
                const category = data.category;
                
                document.getElementById("edit_category_id").value = category.category_id || "";
                document.getElementById("edit_category_name").value = category.category_name || "";
                document.getElementById("edit_category_description").value = category.category_description || "";
                document.getElementById("edit_display_order").value = category.display_order || "0";
                document.getElementById("edit_status").value = category.status || "active";
                
                // Display current image
                const currentImageContainer = document.getElementById("edit_current_image_container");
                if (category.category_image) {
                    currentImageContainer.innerHTML = `
                        <img src="../../Assets/images/categories/${category.category_image}" 
                             alt="${category.category_name}" 
                             class="category-image-preview">
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
                showAlertModal("Error: " + (data.message || "Failed to load category data"), 'error', 'Load Error');
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
            
            showAlertModal("Error loading category data: " + error.message, 'error', 'Load Error');
            closeEditModal();
        });
}

function closeEditModal() {
    console.log("Closing edit modal");
    const modal = document.getElementById("editCategoryModal");
    if (modal) {
        modal.classList.add("hidden");
        console.log("Edit modal closed");
    }
}

async function toggleCategoryStatus(categoryId, currentStatus) {
    console.log("Toggle status for category:", categoryId, "Current status:", currentStatus);
    const newStatus = currentStatus === "active" ? "inactive" : "active";
    const action = newStatus === "active" ? "activate" : "deactivate";
    
    if (await showConfirmModal("Are you sure you want to " + action + " this category?", "Confirm Action")) {
        window.location.href = "../../backend/categories/toggle_category_status.php?id=" + categoryId + "&status=" + newStatus;
    }
}

async function deleteCategory(categoryId) {
    console.log("Delete category:", categoryId);
    
    if (await showConfirmModal("Are you sure you want to delete this category? This action cannot be undone.", "Delete Category")) {
        window.location.href = "../../backend/categories/delete_category.php?id=" + categoryId;
    }
}

// Close modals when clicking outside
document.addEventListener("click", function(event) {
    const addModal = document.getElementById("addCategoryModal");
    const editModal = document.getElementById("editCategoryModal");
    
    if (event.target === addModal) {
        closeAddModal();
    }
    if (event.target === editModal) {
        closeEditModal();
    }
});

// Debug: Check if elements exist
console.log("Checking for elements...");
console.log("Add Modal exists:", document.getElementById("addCategoryModal") !== null);
console.log("Edit Modal exists:", document.getElementById("editCategoryModal") !== null);
console.log("Categories count:", categoriesData.length);
</script>
<?php
$categories_content = ob_get_clean();

// Set the content for app.php
$content = $categories_content;

// Include the app.php layout
include 'app.php';
?>
