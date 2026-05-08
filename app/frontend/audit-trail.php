<?php
/**
 * Audit Trail Frontend
 * File: C:\xampp\htdocs\MinC_Project\app\frontend\audit-trail.php
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

// Check if user has permission to access audit trail
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
$custom_title = 'Audit Trail - MinC Project';

// Update user array to match app.php format
$user = [
    'full_name' => $user_data['name'],
    'user_type' => $user_data['user_type'],
    'is_logged_in' => isset($user_data['id'])
];

// Fetch audit trail data
try {
    $allowed_action_filters = [
        'create',
        'update',
        'delete',
        'login',
        'logout',
        'registration_started',
        'resend_registration_otp',
        'email_verified',
        'update_profile',
        'upload_profile_picture',
        'delete_profile_picture',
        'change_password',
        'deactivate_own_account',
        'update_order_state'
    ];

    $allowed_entity_filters = [
        'user',
        'order',
        'product',
        'product_line',
        'category',
        'auth'
    ];

    $action_label_map = [
        'create' => 'Create',
        'update' => 'Update',
        'delete' => 'Delete',
        'login' => 'Login',
        'logout' => 'Logout',
        'registration_started' => 'Registration Started',
        'resend_registration_otp' => 'Resend Registration OTP',
        'email_verified' => 'Email Verified',
        'update_profile' => 'Update Profile',
        'upload_profile_picture' => 'Upload Profile Picture',
        'delete_profile_picture' => 'Delete Profile Picture',
        'change_password' => 'Change Password',
        'deactivate_own_account' => 'Deactivate Own Account',
        'update_order_state' => 'Order Status Updated'
    ];

    $entity_label_map = [
        'user' => 'User',
        'order' => 'Order',
        'product' => 'Product',
        'product_line' => 'Product Line',
        'category' => 'Category',
        'auth' => 'Auth'
    ];

    // Get audit trail records with user information
    $audit_query = "
        SELECT 
            at.audit_trail_id,
            at.user_id,
            at.session_username,
            at.action,
            at.entity_type,
            at.entity_id,
            at.old_value,
            at.new_value,
            at.change_reason,
            at.timestamp,
            at.ip_address,
            at.user_agent,
            at.system_id,
            at.transaction_id,
            CONCAT(u.fname, ' ', u.lname) as user_full_name,
            ul.user_type_name
        FROM audit_trail at
        LEFT JOIN users u ON at.user_id = u.user_id
        LEFT JOIN user_levels ul ON u.user_level_id = ul.user_level_id
        ORDER BY at.timestamp DESC
        LIMIT 1000
    ";
    $audit_result = $pdo->query($audit_query);
    $audit_records = $audit_result->fetchAll(PDO::FETCH_ASSOC);

    // Get distinct actions for filter (whitelisted to MinC domain actions)
    $actions_query = "SELECT DISTINCT LOWER(TRIM(action)) AS action FROM audit_trail WHERE action IS NOT NULL AND TRIM(action) != ''";
    $actions_result = $pdo->query($actions_query);
    $raw_actions = $actions_result->fetchAll(PDO::FETCH_COLUMN, 0);
    $raw_actions = array_map(static function ($value) {
        return strtolower(trim((string)$value));
    }, $raw_actions);
    $raw_actions = array_values(array_unique($raw_actions));

    $actions = [];
    foreach ($allowed_action_filters as $allowed_action) {
        if (in_array($allowed_action, $raw_actions, true)) {
            $actions[] = [
                'action' => $allowed_action,
                'label' => $action_label_map[$allowed_action] ?? ucwords(str_replace('_', ' ', $allowed_action))
            ];
        }
    }

    // Get distinct entity types for filter (whitelisted to MinC domain entities)
    $entities_query = "SELECT DISTINCT LOWER(TRIM(entity_type)) AS entity_type FROM audit_trail WHERE entity_type IS NOT NULL AND TRIM(entity_type) != ''";
    $entities_result = $pdo->query($entities_query);
    $raw_entities = $entities_result->fetchAll(PDO::FETCH_COLUMN, 0);
    $raw_entities = array_map(static function ($value) {
        return strtolower(trim((string)$value));
    }, $raw_entities);
    $raw_entities = array_values(array_unique($raw_entities));

    $entity_types = [];
    foreach ($allowed_entity_filters as $allowed_entity) {
        if (in_array($allowed_entity, $raw_entities, true)) {
            $entity_types[] = [
                'entity_type' => $allowed_entity,
                'label' => $entity_label_map[$allowed_entity] ?? ucwords(str_replace('_', ' ', $allowed_entity))
            ];
        }
    }

    // Get distinct users for filter
    $users_query = "
        SELECT DISTINCT 
            u.user_id,
            CONCAT(u.fname, ' ', u.lname) as full_name
        FROM audit_trail at
        INNER JOIN users u ON at.user_id = u.user_id
        ORDER BY full_name
    ";
    $users_result = $pdo->query($users_query);
    $users_list = $users_result->fetchAll(PDO::FETCH_ASSOC);

    foreach ($audit_records as &$record) {
        $normalizedAction = strtolower(trim((string)($record['action'] ?? '')));
        $normalizedEntity = strtolower(trim((string)($record['entity_type'] ?? '')));
        $record['display_action'] = $action_label_map[$normalizedAction] ?? ucwords(str_replace('_', ' ', $normalizedAction));
        $record['display_entity'] = $entity_label_map[$normalizedEntity] ?? ucwords(str_replace('_', ' ', $normalizedEntity));
    }
    unset($record);

} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error loading audit trail data: ' . $e->getMessage();
    $audit_records = $actions = $entity_types = $users_list = [];
}

// Additional styles for audit trail specific elements
$additional_styles = '
.audit-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 500;
    text-transform: uppercase;
    display: inline-block;
}

.action-create {
    background-color: #dcfce7;
    color: #166534;
}

.action-update {
    background-color: #dbeafe;
    color: #1e40af;
}

.action-delete {
    background-color: #fee2e2;
    color: #991b1b;
}

.action-assign, .action-assign_student, .action-assign_parent, .action-bulk_assign_student {
    background-color: #e0e7ff;
    color: #4338ca;
}

.action-transfer, .action-transfer_student {
    background-color: #fef3c7;
    color: #92400e;
}

.action-remove, .action-remove_student {
    background-color: #fed7aa;
    color: #9a3412;
}

.action-update_parent_relationship {
    background-color: #e9d5ff;
    color: #6b21a8;
}

.entity-badge {
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 11px;
    font-weight: 500;
    background-color: #f3f4f6;
    color: #374151;
    display: inline-block;
}

.modal {
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
}

.json-viewer {
    background-color: #1e293b;
    color: #e2e8f0;
    padding: 1rem;
    border-radius: 0.5rem;
    font-family: "Courier New", monospace;
    font-size: 0.875rem;
    overflow-x: auto;
    max-height: 400px;
}

.json-key {
    color: #93c5fd;
}

.json-string {
    color: #86efac;
}

.json-number {
    color: #fbbf24;
}

.json-boolean {
    color: #fb923c;
}

.json-null {
    color: #f87171;
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid #e5e7eb;
}

.info-label {
    font-weight: 600;
    color: #6b7280;
    min-width: 150px;
}

.info-value {
    color: #1f2937;
    word-break: break-all;
}

.table-hover tbody tr:hover {
    background-color: rgba(249, 250, 251, 0.8);
}

/* Fixed table container and table width styles */
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
    min-width: 120px;
}

.desktop-table th:nth-child(4),
.desktop-table td:nth-child(4) {
    min-width: 120px;
}

.desktop-table th:nth-child(5),
.desktop-table td:nth-child(5) {
    min-width: 150px;
}

.desktop-table th:last-child,
.desktop-table td:last-child {
    min-width: 120px;
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

.timestamp-text {
    font-size: 0.875rem;
    color: #6b7280;
}
';

// Audit trail content
ob_start();
?>

<!-- Page Header -->
<div class="professional-card rounded-xl p-6 mb-6 animate-fadeIn">
    <div class="flex flex-col md:flex-row md:items-center justify-between">
        <div class="mb-4 md:mb-0">
            <h2 class="text-2xl font-bold text-gray-800 mb-2 flex items-center">
                <i class="fas fa-history text-green-600 mr-3"></i>
                Audit Trail
            </h2>
            <p class="text-gray-600">
                Track and monitor all system activities and changes made by users.
            </p>
        </div>
        <div class="flex items-center space-x-3">
            <button onclick="exportAuditTrail()" class="px-6 py-3 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 transition-colors duration-200 font-medium flex items-center">
                <i class="fas fa-download mr-2"></i>
                Export CSV
            </button>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="professional-card rounded-xl p-6 hover-lift">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 mb-1">Total Logs</p>
                <p class="text-3xl font-bold text-gray-900"><?php echo count($audit_records); ?></p>
            </div>
            <div class="p-4 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-xl shadow-lg">
                <i class="fas fa-database text-white text-2xl"></i>
            </div>
        </div>
    </div>

    <div class="professional-card rounded-xl p-6 hover-lift">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 mb-1">Create Actions</p>
                <p class="text-3xl font-bold text-green-600">
                    <?php 
                    echo count(array_filter($audit_records, function($r) { 
                        return strtoupper($r['action']) === 'CREATE' || strpos(strtolower($r['action']), 'create') !== false;
                    })); 
                    ?>
                </p>
            </div>
            <div class="p-4 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl shadow-lg">
                <i class="fas fa-plus-circle text-white text-2xl"></i>
            </div>
        </div>
    </div>

    <div class="professional-card rounded-xl p-6 hover-lift">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 mb-1">Update Actions</p>
                <p class="text-3xl font-bold text-blue-600">
                    <?php 
                    echo count(array_filter($audit_records, function($r) { 
                        return strtoupper($r['action']) === 'UPDATE' || strpos(strtolower($r['action']), 'update') !== false;
                    })); 
                    ?>
                </p>
            </div>
            <div class="p-4 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl shadow-lg">
                <i class="fas fa-edit text-white text-2xl"></i>
            </div>
        </div>
    </div>

    <div class="professional-card rounded-xl p-6 hover-lift">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 mb-1">Delete Actions</p>
                <p class="text-3xl font-bold text-red-600">
                    <?php 
                    echo count(array_filter($audit_records, function($r) { 
                        return strtoupper($r['action']) === 'DELETE' || strpos(strtolower($r['action']), 'delete') !== false;
                    })); 
                    ?>
                </p>
            </div>
            <div class="p-4 bg-gradient-to-br from-red-500 to-rose-600 rounded-xl shadow-lg">
                <i class="fas fa-trash-alt text-white text-2xl"></i>
            </div>
        </div>
    </div>
</div>
<!-- Filters and Search -->
<div class="professional-card rounded-xl p-6 mb-6 animate-fadeIn">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
            <label for="search_audit" class="form-label">Search</label>
            <div class="relative">
                <input type="text" id="search_audit" placeholder="Search ID, user, action, entity, reason..." 
                       class="form-input input-with-icon">
                <i class="fas fa-search input-icon absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
            </div>
        </div>
        
        <div>
            <label for="user_filter" class="form-label">User</label>
            <select id="user_filter" class="form-input">
                <option value="">All Users</option>
                <?php foreach ($users_list as $user_item): ?>
                    <option value="<?php echo htmlspecialchars($user_item['full_name']); ?>">
                        <?php echo htmlspecialchars($user_item['full_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div>
            <label for="action_filter" class="form-label">Action</label>
            <select id="action_filter" class="form-input">
                <option value="">All Actions</option>
                <?php foreach ($actions as $action): ?>
                    <option value="<?php echo htmlspecialchars($action['action']); ?>">
                        <?php echo htmlspecialchars($action['label'] ?? strtoupper($action['action'])); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="entity_filter" class="form-label">Entity Type</label>
            <select id="entity_filter" class="form-input">
                <option value="">All Entities</option>
                <?php foreach ($entity_types as $entity): ?>
                    <option value="<?php echo htmlspecialchars($entity['entity_type']); ?>">
                        <?php echo htmlspecialchars($entity['label'] ?? ucwords(str_replace('_', ' ', $entity['entity_type']))); ?>
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

<!-- Audit Trail Table/Cards -->
<div class="professional-card table-container rounded-xl overflow-hidden animate-fadeIn">
    <!-- Desktop Table View -->
    <div class="desktop-table">
        <table class="w-full table-hover">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Entity</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($audit_records)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-history text-4xl mb-4 text-gray-300"></i>
                            <p>No audit records found.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($audit_records as $record): ?>
                        <tr class="audit-row" 
                            data-audit-id="<?php echo (int)$record['audit_trail_id']; ?>"
                            data-user="<?php echo htmlspecialchars($record['user_full_name'] ?? $record['session_username']); ?>"
                            data-action="<?php echo htmlspecialchars($record['action']); ?>"
                            data-entity="<?php echo htmlspecialchars($record['entity_type']); ?>"
                            data-search="<?php echo htmlspecialchars($record['entity_id'] . ' ' . ($record['change_reason'] ?? '')); ?>">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    #<?php echo htmlspecialchars($record['audit_trail_id']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($record['user_full_name'] ?? $record['session_username']); ?>
                                </div>
                                <div class="text-xs text-gray-500">
                                    <?php echo htmlspecialchars($record['user_type_name'] ?? 'Unknown'); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="audit-badge action-<?php echo strtolower(str_replace('_', '-', $record['action'])); ?>">
                                    <?php echo htmlspecialchars($record['display_action'] ?? strtoupper($record['action'])); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="entity-badge">
                                    <?php echo htmlspecialchars($record['display_entity'] ?? ucfirst($record['entity_type'])); ?>
                                </span>
                                <div class="text-xs text-gray-500 mt-1">
                                    ID: <?php echo htmlspecialchars($record['entity_id']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <?php echo date('M d, Y', strtotime($record['timestamp'])); ?>
                                </div>
                                <div class="text-xs text-gray-500">
                                    <?php echo date('h:i A', strtotime($record['timestamp'])); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button onclick='viewDetails(<?php echo json_encode($record); ?>)' 
                                        class="text-blue-600 hover:text-blue-900 p-2 rounded-lg hover:bg-blue-50 transition-colors duration-200" 
                                        title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr id="no-filter-results-row" style="display:none;">
                        <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-search text-4xl mb-4 text-gray-300"></i>
                            <p>No matching audit records found.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Card View -->
    <div class="mobile-card p-4 space-y-4">
        <?php if (empty($audit_records)): ?>
            <div class="text-center py-12 text-gray-500">
                <i class="fas fa-history text-4xl mb-4 text-gray-300"></i>
                <p>No audit records found.</p>
            </div>
        <?php else: ?>
            <?php foreach ($audit_records as $record): ?>
                <div class="bg-white border border-gray-200 rounded-xl p-4 audit-card" 
                     data-audit-id="<?php echo (int)$record['audit_trail_id']; ?>"
                     data-user="<?php echo htmlspecialchars($record['user_full_name'] ?? $record['session_username']); ?>"
                     data-action="<?php echo htmlspecialchars($record['action']); ?>"
                     data-entity="<?php echo htmlspecialchars($record['entity_type']); ?>"
                     data-search="<?php echo htmlspecialchars($record['entity_id'] . ' ' . ($record['change_reason'] ?? '')); ?>">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <h4 class="font-medium text-gray-900">
                                #<?php echo htmlspecialchars($record['audit_trail_id']); ?> - 
                                <?php echo htmlspecialchars($record['user_full_name'] ?? $record['session_username']); ?>
                            </h4>
                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($record['user_type_name'] ?? 'Unknown'); ?></p>
                        </div>
                        <span class="audit-badge action-<?php echo strtolower(str_replace('_', '-', $record['action'])); ?>">
                            <?php echo htmlspecialchars($record['display_action'] ?? strtoupper($record['action'])); ?>
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <p class="text-xs text-gray-500 mb-1">Entity Type</p>
                            <span class="entity-badge">
                                <?php echo htmlspecialchars($record['display_entity'] ?? ucfirst($record['entity_type'])); ?>
                            </span>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-1">Entity ID</p>
                            <p class="text-sm text-gray-900"><?php echo htmlspecialchars($record['entity_id']); ?></p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-xs text-gray-500 mb-1">Timestamp</p>
                            <p class="text-sm text-gray-900">
                                <?php echo date('M d, Y h:i A', strtotime($record['timestamp'])); ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex justify-end">
                        <button onclick='viewDetails(<?php echo json_encode($record); ?>)' 
                                class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors duration-200" 
                                title="View Details">
                            <i class="fas fa-eye mr-1"></i> View Details
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
            <div id="no-filter-results-card" class="hidden text-center py-12 text-gray-500">
                <i class="fas fa-search text-4xl mb-4 text-gray-300"></i>
                <p>No matching audit records found.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Details Modal -->
<div id="detailsModal" class="fixed inset-0 z-50 hidden modal bg-black bg-opacity-50 flex items-center justify-center p-4">
    <div class="professional-card rounded-xl max-w-4xl w-full max-h-screen overflow-y-auto">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-semibold text-gray-800">Audit Trail Details</h3>
                <button type="button" onclick="closeDetailsModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <!-- Basic Information -->
            <div class="mb-6">
                <h4 class="text-lg font-semibold text-gray-700 mb-3">Basic Information</h4>
                <div class="space-y-2">
                    <div class="info-row">
                        <span class="info-label">Audit ID:</span>
                        <span class="info-value" id="detail_audit_id"></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">User:</span>
                        <span class="info-value" id="detail_user"></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">User Type:</span>
                        <span class="info-value" id="detail_user_type"></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Action:</span>
                        <span class="info-value" id="detail_action"></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Entity Type:</span>
                        <span class="info-value" id="detail_entity_type"></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Entity ID:</span>
                        <span class="info-value" id="detail_entity_id"></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Timestamp:</span>
                        <span class="info-value" id="detail_timestamp"></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">IP Address:</span>
                        <span class="info-value" id="detail_ip"></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Change Reason:</span>
                        <span class="info-value" id="detail_reason"></span>
                    </div>
                </div>
            </div>

            <!-- Old Value -->
            <div class="mb-6" id="old_value_section">
                <h4 class="text-lg font-semibold text-gray-700 mb-3">Old Value</h4>
                <div class="json-viewer" id="detail_old_value">
                    No data available
                </div>
            </div>

            <!-- New Value -->
            <div class="mb-6" id="new_value_section">
                <h4 class="text-lg font-semibold text-gray-700 mb-3">New Value</h4>
                <div class="json-viewer" id="detail_new_value">
                    No data available
                </div>
            </div>

            <!-- Additional Information -->
            <div class="mb-6" id="additional_info_section" style="display: none;">
                <h4 class="text-lg font-semibold text-gray-700 mb-3">Additional Information</h4>
                <div class="space-y-2">
                    <div class="info-row">
                        <span class="info-label">System ID:</span>
                        <span class="info-value" id="detail_system_id"></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Transaction ID:</span>
                        <span class="info-value" id="detail_transaction_id"></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">User Agent:</span>
                        <span class="info-value" id="detail_user_agent"></span>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end">
                <button type="button" onclick="closeDetailsModal()" 
                        class="px-6 py-3 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 transition-colors duration-200">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript Data and Functions -->
<script>
// Global variables to store audit data
const auditData = <?php echo json_encode($audit_records); ?>;
let filteredData = [...auditData];
let currentPage = 1;
let recordsPerPage = 10;
let rowIndexMap = new Map();
let cardIndexMap = new Map();

function buildElementIndexes() {
    rowIndexMap = new Map();
    cardIndexMap = new Map();

    document.querySelectorAll(".audit-row[data-audit-id]").forEach(row => {
        rowIndexMap.set(String(row.dataset.auditId), row);
    });
    document.querySelectorAll(".audit-card[data-audit-id]").forEach(card => {
        cardIndexMap.set(String(card.dataset.auditId), card);
    });
}

function debounce(fn, wait = 180) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), wait);
    };
}

// Initialize filters on page load
document.addEventListener("DOMContentLoaded", function() {
    console.log("DOM loaded, initializing filters and pagination");
    buildElementIndexes();
    initializeFilters();
    initializePagination();
    updateDisplay();
});

function initializeFilters() {
    const searchInput = document.getElementById("search_audit");
    const userFilter = document.getElementById("user_filter");
    const actionFilter = document.getElementById("action_filter");
    const entityFilter = document.getElementById("entity_filter");

    function applyFilters() {
        const searchTerm = searchInput ? searchInput.value.toLowerCase() : "";
        const selectedUser = userFilter ? userFilter.value : "";
        const selectedAction = actionFilter ? actionFilter.value : "";
        const selectedEntity = entityFilter ? entityFilter.value : "";
        
        filteredData = auditData.filter(record => {
            const user = (record.user_full_name || record.session_username || "");
            const action = (record.action || "");
            const entity = (record.entity_type || "");
            const searchData = [
                record.audit_trail_id,
                user,
                record.user_type_name || "",
                action,
                entity,
                record.entity_id || "",
                record.change_reason || "",
                record.timestamp || "",
                record.ip_address || "",
                record.system_id || "",
                record.transaction_id || ""
            ].join(" ").toLowerCase();

            const matchesSearch = searchTerm === "" || searchData.includes(searchTerm);
            const matchesUser = selectedUser === "" || user === selectedUser;
            const matchesAction = selectedAction === "" || action.toLowerCase() === selectedAction.toLowerCase();
            const matchesEntity = selectedEntity === "" || entity.toLowerCase() === selectedEntity.toLowerCase();

            return matchesSearch && matchesUser && matchesAction && matchesEntity;
        });

        currentPage = 1; // Reset to first page when filters change
        updateDisplay();
    }

    const debouncedApplyFilters = debounce(applyFilters, 150);
    if (searchInput) searchInput.addEventListener("input", debouncedApplyFilters);
    if (userFilter) userFilter.addEventListener("change", applyFilters);
    if (actionFilter) actionFilter.addEventListener("change", applyFilters);
    if (entityFilter) entityFilter.addEventListener("change", applyFilters);
}

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

function updateDisplay() {
    const totalRecords = filteredData.length;
    const totalPages = Math.ceil(totalRecords / recordsPerPage);
    const startIndex = (currentPage - 1) * recordsPerPage;
    const endIndex = Math.min(startIndex + recordsPerPage, totalRecords);

    // Update statistics
    const totalRecordsElement = document.getElementById("total_records");
    if (totalRecordsElement) {
        totalRecordsElement.textContent = auditData.length;
    }
    
    const showingRecordsElement = document.getElementById("showing_records");
    if (showingRecordsElement) {
        showingRecordsElement.textContent = totalRecords;
    }

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

function displayRecords(startIndex, endIndex) {
    const visibleIds = new Set(filteredData.slice(startIndex, endIndex).map(record => String(record.audit_trail_id)));

    rowIndexMap.forEach((row, id) => {
        row.style.display = visibleIds.has(id) ? "" : "none";
    });
    cardIndexMap.forEach((card, id) => {
        card.style.display = visibleIds.has(id) ? "" : "none";
    });

    const noResultsRow = document.getElementById("no-filter-results-row");
    const noResultsCard = document.getElementById("no-filter-results-card");
    const hasVisible = visibleIds.size > 0;

    if (noResultsRow) {
        noResultsRow.style.display = hasVisible ? "none" : "";
    }
    if (noResultsCard) {
        noResultsCard.classList.toggle("hidden", hasVisible);
    }
}

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

// Format JSON for display
function formatJSON(jsonString) {
    if (!jsonString || jsonString === 'null') {
        return '<span class="json-null">No data available</span>';
    }
    try {
        const obj = typeof jsonString === 'string' ? JSON.parse(jsonString) : jsonString;
        return syntaxHighlight(JSON.stringify(obj, null, 2));
    } catch (e) {
        return '<span class="json-string">' + escapeHtml(jsonString) + '</span>';
    }
}

// Syntax highlighting for JSON
function syntaxHighlight(json) {
    json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
        let cls = 'json-number';
        if (/^"/.test(match)) {
            if (/:$/.test(match)) {
                cls = 'json-key';
            } else {
                cls = 'json-string';
            }
        } else if (/true|false/.test(match)) {
            cls = 'json-boolean';
        } else if (/null/.test(match)) {
            cls = 'json-null';
        }
        return '<span class="' + cls + '">' + match + '</span>';
    });
}

// Escape HTML
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// View details modal
function viewDetails(record) {
    console.log("Opening details for record:", record);
    const modal = document.getElementById("detailsModal");
    if (!modal) {
        console.error("Details modal not found");
        return;
    }

    // Populate basic information
    document.getElementById("detail_audit_id").textContent = record.audit_trail_id || 'N/A';
    document.getElementById("detail_user").textContent = record.user_full_name || record.session_username || 'N/A';
    document.getElementById("detail_user_type").textContent = record.user_type_name || 'N/A';
    const actionLabel = record.display_action || (record.action ? record.action.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) : 'N/A');
    const entityLabel = record.display_entity || (record.entity_type ? record.entity_type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) : 'N/A');
    document.getElementById("detail_action").innerHTML = '<span class="audit-badge action-' + record.action.toLowerCase().replace(/_/g, '-') + '">' + actionLabel + '</span>';
    document.getElementById("detail_entity_type").textContent = entityLabel;
    document.getElementById("detail_entity_id").textContent = record.entity_id || 'N/A';
    document.getElementById("detail_timestamp").textContent = record.timestamp ? new Date(record.timestamp).toLocaleString() : 'N/A';
    document.getElementById("detail_ip").textContent = record.ip_address || 'N/A';
    document.getElementById("detail_reason").textContent = record.change_reason || 'N/A';

    // Populate old value
    const oldValueSection = document.getElementById("old_value_section");
    const oldValueElement = document.getElementById("detail_old_value");
    if (record.old_value) {
        oldValueSection.style.display = 'block';
        oldValueElement.innerHTML = formatJSON(record.old_value);
    } else {
        oldValueSection.style.display = 'none';
    }

    // Populate new value
    const newValueSection = document.getElementById("new_value_section");
    const newValueElement = document.getElementById("detail_new_value");
    if (record.new_value) {
        newValueSection.style.display = 'block';
        newValueElement.innerHTML = formatJSON(record.new_value);
    } else {
        newValueSection.style.display = 'none';
    }

    // Populate additional information
    const additionalSection = document.getElementById("additional_info_section");
    if (record.system_id || record.transaction_id || record.user_agent) {
        additionalSection.style.display = 'block';
        document.getElementById("detail_system_id").textContent = record.system_id || 'N/A';
        document.getElementById("detail_transaction_id").textContent = record.transaction_id || 'N/A';
        document.getElementById("detail_user_agent").textContent = record.user_agent || 'N/A';
    } else {
        additionalSection.style.display = 'none';
    }

    modal.classList.remove("hidden");
    console.log("Details modal opened");
}

function closeDetailsModal() {
    const modal = document.getElementById("detailsModal");
    if (modal) {
        modal.classList.add("hidden");
    }
}

// Export audit trail to CSV
function exportAuditTrail() {
    let csv = 'Audit ID,User,User Type,Action,Entity Type,Entity ID,Timestamp,IP Address,Change Reason\n';

    filteredData.forEach(record => {
        csv += [
            record.audit_trail_id,
            '"' + (record.user_full_name || record.session_username || '').replace(/"/g, '""') + '"',
            '"' + (record.user_type_name || '').replace(/"/g, '""') + '"',
            record.action,
            record.entity_type,
            record.entity_id,
            record.timestamp,
            record.ip_address || '',
            '"' + (record.change_reason || '').replace(/"/g, '""') + '"'
        ].join(',') + '\n';
    });

    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'audit_trail_' + new Date().toISOString().slice(0, 10) + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

// Close modal when clicking outside
document.addEventListener("click", function(event) {
    const modal = document.getElementById("detailsModal");
    if (event.target === modal) {
        closeDetailsModal();
    }
});

// Debug: Check if elements exist
console.log("Checking for elements...");
console.log("Details Modal exists:", document.getElementById("detailsModal") !== null);
console.log("Audit records count:", auditData.length);
</script>
<style>
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

.form-label {
    display: block;
    font-size: 0.875rem;
    font-weight: 500;
    color: #374151;
    margin-bottom: 0.5rem;
}

.input-with-icon {
    padding-left: 2.5rem !important;
}

.input-icon {
    pointer-events: none;
}
</style>
<?php
$audit_trail_content = ob_get_clean();

// Set the content for app.php
$content = $audit_trail_content;

// Include the app.php layout
include 'app.php';
?>
