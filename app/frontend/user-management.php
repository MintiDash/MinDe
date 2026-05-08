<?php
/**
 * User Management Frontend
 * File: C:\xampp\htdocs\MinC_Project\app\frontend\user-management.php
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

// Check if user has permission to access user management
// Only Admin (1) and Employee (2) can access
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
$custom_title = 'User Management - MinC Project';

// Update user array to match app.php format
$user = [
    'full_name' => $user_data['name'],
    'user_type' => $user_data['user_type'],
    'is_logged_in' => isset($user_data['id'])
];

// Fetch data for dropdowns
try {
    // Get user levels from user_levels table
    $user_levels_query = "
        SELECT MIN(user_level_id) AS user_level_id, normalized_name AS user_type_name
        FROM (
            SELECT
                user_level_id,
                CASE
                    WHEN user_level_id = 1 THEN 'Admin'
                    WHEN user_level_id = 2 THEN 'Employee'
                    WHEN user_level_id = 3 THEN 'Supplier'
                    WHEN user_level_id = 4 THEN 'Customer'
                    WHEN LOWER(user_type_name) IN ('it personnel', 'admin') THEN 'Admin'
                    WHEN LOWER(user_type_name) IN ('owner', 'manager', 'employee', 'employees') THEN 'Employee'
                    WHEN LOWER(user_type_name) IN ('supplier', 'suppliers') THEN 'Supplier'
                    WHEN LOWER(user_type_name) IN ('consumer', 'customer', 'customers') THEN 'Customer'
                    ELSE user_type_name
                END AS normalized_name
            FROM user_levels
            WHERE user_type_status = 'active'
        ) roles
        GROUP BY normalized_name
        ORDER BY
            CASE normalized_name
                WHEN 'Admin' THEN 1
                WHEN 'Employee' THEN 2
                WHEN 'Supplier' THEN 3
                WHEN 'Customer' THEN 4
                ELSE 5
            END,
            normalized_name
    ";
    $user_levels_result = $pdo->query($user_levels_query);
    $user_levels = $user_levels_result->fetchAll(PDO::FETCH_ASSOC);

    // Enforce canonical role options in UI even if legacy DB role rows are inconsistent.
    $canonical_roles = [
        1 => 'Admin',
        2 => 'Employee',
        3 => 'Supplier',
        4 => 'Customer'
    ];
    $resolved_user_levels = [];
    foreach ($canonical_roles as $canonical_id => $canonical_name) {
        $matched_id = null;
        foreach ($user_levels as $level) {
            $level_id = (int)($level['user_level_id'] ?? 0);
            $level_name = trim((string)($level['user_type_name'] ?? ''));
            if ($level_id === $canonical_id || strcasecmp($level_name, $canonical_name) === 0) {
                $matched_id = $level_id > 0 ? $level_id : $canonical_id;
                break;
            }
        }

        $resolved_user_levels[] = [
            'user_level_id' => $matched_id ?? $canonical_id,
            'user_type_name' => $canonical_name
        ];
    }
    $user_levels = $resolved_user_levels;

    // Get distinct user statuses from users table
    $statuses_query = "SELECT DISTINCT user_status FROM users WHERE user_status IS NOT NULL ORDER BY user_status";
    $statuses_result = $pdo->query($statuses_query);
    $user_statuses = $statuses_result->fetchAll(PDO::FETCH_ASSOC);
    
    // If no statuses found, provide defaults
    if (empty($user_statuses)) {
        $user_statuses = [
            ['user_status' => 'active'],
            ['user_status' => 'inactive']
        ];
    }

    // Get users with their user level information
    $users_query = "
        SELECT 
            u.user_id,
            u.fname,
            u.lname,
            CONCAT(u.fname, ' ', u.lname) as full_name,
            u.email,
            u.username,
            u.contact_num,
            u.user_status,
            u.user_level_id,
            CASE
                WHEN u.user_level_id = 1 THEN 'Admin'
                WHEN u.user_level_id = 2 THEN 'Employee'
                WHEN u.user_level_id = 3 THEN 'Supplier'
                WHEN u.user_level_id = 4 THEN 'Customer'
                WHEN LOWER(ul.user_type_name) IN ('it personnel', 'admin') THEN 'Admin'
                WHEN LOWER(ul.user_type_name) IN ('owner', 'manager', 'employee', 'employees') THEN 'Employee'
                WHEN LOWER(ul.user_type_name) IN ('supplier', 'suppliers') THEN 'Supplier'
                WHEN LOWER(ul.user_type_name) IN ('consumer', 'customer', 'customers') THEN 'Customer'
                ELSE ul.user_type_name
            END AS user_type_name,
            u.created_at
        FROM users u
        LEFT JOIN user_levels ul ON u.user_level_id = ul.user_level_id
        ORDER BY u.created_at DESC
    ";
    $users_result = $pdo->query($users_query);
    $users = $users_result->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error loading data: ' . $e->getMessage();
    $user_levels = $user_statuses = $users = [];
}

// Additional styles for user management specific elements
$additional_styles = '
.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #16a34a, #22c55e);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 16px;
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

/* ADD THESE NEW PASSWORD STYLES */
.password-strength-meter {
    height: 4px;
    background-color: #e5e7eb;
    border-radius: 2px;
    margin-top: 0.5rem;
    overflow: hidden;
}

.password-strength-bar {
    height: 100%;
    width: 0%;
    transition: all 0.3s ease;
    border-radius: 2px;
}

.strength-weak {
    background-color: #ef4444;
    width: 33.33%;
}

.strength-medium {
    background-color: #f59e0b;
    width: 66.66%;
}

.strength-strong {
    background-color: #22c55e;
    width: 100%;
}

.password-requirements {
    margin-top: 0.5rem;
    font-size: 0.75rem;
}

.requirement-item {
    display: flex;
    align-items: center;
    color: #6b7280;
    margin-bottom: 0.25rem;
}

.requirement-item.met {
    color: #22c55e;
}

.requirement-item i {
    margin-right: 0.5rem;
    font-size: 0.625rem;
}

.password-match-indicator {
    display: flex;
    align-items: center;
    margin-top: 0.5rem;
    font-size: 0.75rem;
}

.password-match-indicator.match {
    color: #22c55e;
}

.password-match-indicator.no-match {
    color: #ef4444;
}

.password-match-indicator i {
    margin-right: 0.5rem;
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
    min-width: 120px;
}

.desktop-table th:first-child,
.desktop-table td:first-child {
    min-width: 200px;
}

.desktop-table th:nth-child(2),
.desktop-table td:nth-child(2) {
    min-width: 180px;
}

.desktop-table th:nth-child(3),
.desktop-table td:nth-child(3) {
    min-width: 160px;
}

.desktop-table th:nth-child(4),
.desktop-table td:nth-child(4) {
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
';

// User management content
ob_start();
?>

<!-- Page Header -->
<div class="professional-card rounded-xl p-6 mb-6 animate-fadeIn">
    <div class="flex flex-col md:flex-row md:items-center justify-between">
        <div class="mb-4 md:mb-0">
            <h2 class="text-2xl font-bold text-gray-800 mb-2 flex items-center">
                <i class="fas fa-users text-green-600 mr-3"></i>
                User Management
            </h2>
            <p class="text-gray-600">
                Manage system users, roles, and permissions for the MinC Project.
            </p>
        </div>
        <div class="flex items-center space-x-3">
            <button onclick="openAddModal()" class="btn-primary px-6 py-3 rounded-xl font-medium flex items-center">
                <i class="fas fa-plus mr-2"></i>
                Add New User
            </button>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <div class="professional-card rounded-xl p-6 hover-lift">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 mb-1">Total Users</p>
                <p class="text-3xl font-bold text-gray-900"><?php echo count($users); ?></p>
            </div>
            <div class="p-4 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-xl shadow-lg">
                <i class="fas fa-users text-white text-2xl"></i>
            </div>
        </div>
    </div>

    <div class="professional-card rounded-xl p-6 hover-lift">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 mb-1">Active Users</p>
                <p class="text-3xl font-bold text-green-600">
                    <?php echo count(array_filter($users, function($u) { return $u['user_status'] === 'active'; })); ?>
                </p>
            </div>
            <div class="p-4 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl shadow-lg">
                <i class="fas fa-user-check text-white text-2xl"></i>
            </div>
        </div>
    </div>

    <div class="professional-card rounded-xl p-6 hover-lift">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 mb-1">User Types</p>
                <p class="text-3xl font-bold text-purple-600"><?php echo count($user_levels); ?></p>
            </div>
            <div class="p-4 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-xl shadow-lg">
                <i class="fas fa-user-tag text-white text-2xl"></i>
            </div>
        </div>
    </div>
</div>

<!-- Filters and Search -->
<div class="professional-card rounded-xl p-6 mb-6 animate-fadeIn">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label for="search_users" class="form-label">Search Users</label>
            <div class="relative">
                <input type="text" id="search_users" placeholder="Search by name or email..." 
                       class="form-input pl-10">
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
            </div>
        </div>
        
        <div>
            <label for="user_type_filter" class="form-label">User Type</label>
            <select id="user_type_filter" class="form-input">
                <option value="">All Types</option>
                <?php foreach ($user_levels as $level): ?>
                    <option value="<?php echo htmlspecialchars($level['user_type_name']); ?>">
                        <?php echo htmlspecialchars($level['user_type_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div>
            <label for="status_filter" class="form-label">Status</label>
            <select id="status_filter" class="form-input">
                <option value="">All Status</option>
                <?php foreach ($user_statuses as $status): ?>
                    <option value="<?php echo htmlspecialchars($status['user_status']); ?>">
                        <?php echo ucfirst(htmlspecialchars($status['user_status'])); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>

<!-- Users Table/Cards -->
<div class="professional-card table-container rounded-xl overflow-hidden animate-fadeIn">
    <!-- Desktop Table View -->
    <div class="desktop-table">
        <table class="w-full table-hover">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-users text-4xl mb-4 text-gray-300"></i>
                            <p>No users found.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $user_row): ?>
                        <tr class="user-row" 
                            data-name="<?php echo strtolower($user_row['full_name']); ?>" 
                            data-email="<?php echo strtolower($user_row['email']); ?>"
                            data-user-type="<?php echo htmlspecialchars($user_row['user_type_name']); ?>"
                            data-status="<?php echo $user_row['user_status']; ?>">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="user-avatar mr-4">
                                        <?php echo strtoupper(substr($user_row['fname'], 0, 1) . substr($user_row['lname'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($user_row['full_name']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo htmlspecialchars($user_row['username'] ?? 'No username'); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($user_row['email']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($user_row['contact_num'] ?? 'No contact'); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    <?php echo htmlspecialchars($user_row['user_type_name'] ?? 'No role'); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="status-badge status-<?php echo $user_row['user_status']; ?>">
                                    <?php echo ucfirst($user_row['user_status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center space-x-3">
                                    <button onclick="openEditModal(<?php echo $user_row['user_id']; ?>)" 
                                            class="text-blue-600 hover:text-blue-900 p-2 rounded-lg hover:bg-blue-50 transition-colors duration-200" 
                                            title="Edit User">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="toggleUserStatus(<?php echo $user_row['user_id']; ?>, '<?php echo $user_row['user_status']; ?>')" 
                                            class="<?php echo $user_row['user_status'] === 'active' ? 'text-orange-600 hover:text-orange-900 hover:bg-orange-50' : 'text-green-600 hover:text-green-900 hover:bg-green-50'; ?> p-2 rounded-lg transition-colors duration-200" 
                                            title="<?php echo $user_row['user_status'] === 'active' ? 'Deactivate' : 'Activate'; ?> User">
                                        <i class="fas fa-<?php echo $user_row['user_status'] === 'active' ? 'ban' : 'user-check'; ?>"></i>
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
        <?php if (empty($users)): ?>
            <div class="text-center py-12 text-gray-500">
                <i class="fas fa-users text-4xl mb-4 text-gray-300"></i>
                <p>No users found.</p>
            </div>
        <?php else: ?>
            <?php foreach ($users as $user_row): ?>
                <div class="bg-white border border-gray-200 rounded-xl p-4 user-card" 
                     data-name="<?php echo strtolower($user_row['full_name']); ?>" 
                     data-email="<?php echo strtolower($user_row['email']); ?>"
                     data-user-type="<?php echo htmlspecialchars($user_row['user_type_name']); ?>"
                     data-status="<?php echo $user_row['user_status']; ?>">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center">
                            <div class="user-avatar mr-3">
                                <?php echo strtoupper(substr($user_row['fname'], 0, 1) . substr($user_row['lname'], 0, 1)); ?>
                            </div>
                            <div>
                                <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($user_row['full_name']); ?></h4>
                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($user_row['username'] ?? 'No username'); ?></p>
                            </div>
                        </div>
                        <span class="status-badge status-<?php echo $user_row['user_status']; ?>">
                            <?php echo ucfirst($user_row['user_status']); ?>
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <p class="text-xs text-gray-500 mb-1">Email</p>
                            <p class="text-sm text-gray-900"><?php echo htmlspecialchars($user_row['email']); ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-1">Contact</p>
                            <p class="text-sm text-gray-900"><?php echo htmlspecialchars($user_row['contact_num'] ?? 'No contact'); ?></p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-xs text-gray-500 mb-1">Role</p>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                <?php echo htmlspecialchars($user_row['user_type_name'] ?? 'No role'); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-2">
                        <button onclick="openEditModal(<?php echo $user_row['user_id']; ?>)" 
                                class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors duration-200" 
                                title="Edit User">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="toggleUserStatus(<?php echo $user_row['user_id']; ?>, '<?php echo $user_row['user_status']; ?>')" 
                                class="p-2 <?php echo $user_row['user_status'] === 'active' ? 'text-orange-600 hover:bg-orange-50' : 'text-green-600 hover:bg-green-50'; ?> rounded-lg transition-colors duration-200" 
                                title="<?php echo $user_row['user_status'] === 'active' ? 'Deactivate' : 'Activate'; ?> User">
                            <i class="fas fa-<?php echo $user_row['user_status'] === 'active' ? 'ban' : 'user-check'; ?>"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add User Modal -->
<div id="addUserModal" class="fixed inset-0 z-50 hidden modal bg-black bg-opacity-50 flex items-center justify-center p-4">
    <div class="professional-card rounded-xl max-w-2xl w-full max-h-screen overflow-y-auto">
        <form id="addUserForm" action="../../backend/user-management/add_user.php" method="POST" class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-semibold text-gray-800">Add New User</h3>
                <button type="button" onclick="closeAddModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="form-group">
                    <label for="add_fname" class="form-label">First Name *</label>
                    <input type="text" id="add_fname" name="fname" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label for="add_lname" class="form-label">Last Name *</label>
                    <input type="text" id="add_lname" name="lname" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label for="add_email" class="form-label">Email *</label>
                    <input type="email" id="add_email" name="email" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label for="add_username" class="form-label">Username</label>
                    <input type="text" id="add_username" name="username" class="form-input">
                </div>
                
                <div class="form-group">
                    <label for="add_contact" class="form-label">Contact Number</label>
                    <input type="text" id="add_contact" name="contact_num" class="form-input">
                </div>
                
                <div class="form-group md:col-span-2">
                    <label for="add_password" class="form-label">Password *</label>
                    <input type="password" id="add_password" name="password" class="form-input" required>
                    <div class="password-strength-meter">
                        <div id="add_password_strength_bar" class="password-strength-bar"></div>
                    </div>
                    <div id="add_password_requirements" class="password-requirements">
                        <div class="requirement-item" data-requirement="length">
                            <i class="fas fa-circle"></i>
                            <span>At least 8 characters</span>
                        </div>
                        <div class="requirement-item" data-requirement="letter">
                            <i class="fas fa-circle"></i>
                            <span>At least 1 uppercase or lowercase letter</span>
                        </div>
                        <div class="requirement-item" data-requirement="number">
                            <i class="fas fa-circle"></i>
                            <span>At least 1 number</span>
                        </div>
                        <div class="requirement-item" data-requirement="special">
                            <i class="fas fa-circle"></i>
                            <span>At least 1 special character</span>
                        </div>
                    </div>
                </div>

                <div class="form-group md:col-span-2">
                    <label for="add_confirm_password" class="form-label">Confirm Password *</label>
                    <input type="password" id="add_confirm_password" name="confirm_password" class="form-input" required>
                    <div id="add_password_match" class="password-match-indicator" style="display: none;">
                        <i class="fas fa-circle"></i>
                        <span></span>
                    </div>
                </div>
                
                <div class="form-group md:col-span-2">
                    <label for="add_user_type" class="form-label">User Type *</label>
                    <select id="add_user_type" name="user_level_id" class="form-input" required>
                        <option value="">Select User Type</option>
                        <?php foreach ($user_levels as $level): ?>
                            <option value="<?php echo $level['user_level_id']; ?>">
                                <?php echo htmlspecialchars($level['user_type_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
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
                    Add User
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="fixed inset-0 z-50 hidden modal bg-black bg-opacity-50 flex items-center justify-center p-4">
    <div class="professional-card rounded-xl max-w-2xl w-full max-h-screen overflow-y-auto">
        <form id="editUserForm" action="../../backend/user-management/edit_user.php" method="POST" class="p-6">
            <input type="hidden" id="edit_user_id" name="user_id">
            
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-semibold text-gray-800">Edit User</h3>
                <button type="button" onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="form-group">
                    <label for="edit_fname" class="form-label">First Name *</label>
                    <input type="text" id="edit_fname" name="fname" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_lname" class="form-label">Last Name *</label>
                    <input type="text" id="edit_lname" name="lname" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_email" class="form-label">Email *</label>
                    <input type="email" id="edit_email" name="email" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_username" class="form-label">Username</label>
                    <input type="text" id="edit_username" name="username" class="form-input">
                </div>
                
                <div class="form-group">
                    <label for="edit_contact" class="form-label">Contact Number</label>
                    <input type="text" id="edit_contact" name="contact_num" class="form-input">
                </div>
                
                <div class="form-group md:col-span-2">
                    <label for="edit_password" class="form-label">New Password (leave blank to keep current)</label>
                    <input type="password" id="edit_password" name="password" class="form-input">
                    <div class="password-strength-meter">
                        <div id="edit_password_strength_bar" class="password-strength-bar"></div>
                    </div>
                    <div id="edit_password_requirements" class="password-requirements" style="display: none;">
                        <div class="requirement-item" data-requirement="length">
                            <i class="fas fa-circle"></i>
                            <span>At least 8 characters</span>
                        </div>
                        <div class="requirement-item" data-requirement="letter">
                            <i class="fas fa-circle"></i>
                            <span>At least 1 uppercase or lowercase letter</span>
                        </div>
                        <div class="requirement-item" data-requirement="number">
                            <i class="fas fa-circle"></i>
                            <span>At least 1 number</span>
                        </div>
                        <div class="requirement-item" data-requirement="special">
                            <i class="fas fa-circle"></i>
                            <span>At least 1 special character</span>
                        </div>
                    </div>
                </div>

                <div class="form-group md:col-span-2">
                    <label for="edit_confirm_password" class="form-label">Confirm New Password</label>
                    <input type="password" id="edit_confirm_password" name="confirm_password" class="form-input">
                    <div id="edit_password_match" class="password-match-indicator" style="display: none;">
                        <i class="fas fa-circle"></i>
                        <span></span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_user_type" class="form-label">User Type *</label>
                    <select id="edit_user_type" name="user_level_id" class="form-input" required>
                        <option value="">Select User Type</option>
                        <?php foreach ($user_levels as $level): ?>
                            <option value="<?php echo $level['user_level_id']; ?>">
                                <?php echo htmlspecialchars($level['user_type_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_status" class="form-label">Status *</label>
                    <select id="edit_status" name="user_status" class="form-input" required>
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
                    Update User
                </button>
            </div>
        </form>
    </div>
</div>

<!-- JavaScript Data and Functions -->
<script>
// Global variables to store user data
const usersData = <?php echo json_encode($users); ?>;

// Initialize everything on page load
document.addEventListener("DOMContentLoaded", function() {
    console.log("DOM loaded, initializing filters and password validation");
    initializeFilters();
    initializePasswordValidation();
});

// Password validation initialization
function initializePasswordValidation() {
    // Password validation for Add User Modal
    const addPassword = document.getElementById("add_password");
    const addConfirmPassword = document.getElementById("add_confirm_password");
    const addStrengthBar = document.getElementById("add_password_strength_bar");
    const addRequirements = document.getElementById("add_password_requirements");
    const addMatchIndicator = document.getElementById("add_password_match");
    
    // Password validation for Edit User Modal
    const editPassword = document.getElementById("edit_password");
    const editConfirmPassword = document.getElementById("edit_confirm_password");
    const editStrengthBar = document.getElementById("edit_password_strength_bar");
    const editRequirements = document.getElementById("edit_password_requirements");
    const editMatchIndicator = document.getElementById("edit_password_match");
    
    // Password strength checker
    function checkPasswordStrength(password) {
        const requirements = {
            length: password.length >= 8,
            letter: /[a-zA-Z]/.test(password),
            number: /\d/.test(password),
            special: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)
        };
        
        const metCount = Object.values(requirements).filter(Boolean).length;
        let strength = 'weak';
        
        if (metCount === 4) {
            strength = 'strong';
        } else if (metCount >= 2) {
            strength = 'medium';
        }
        
        return { strength, requirements };
    }
    
    // Update strength meter and requirements
    function updatePasswordStrength(password, strengthBar, requirementsContainer) {
        if (!password) {
            strengthBar.className = 'password-strength-bar';
            const items = requirementsContainer.querySelectorAll('.requirement-item');
            items.forEach(item => {
                item.classList.remove('met');
                item.querySelector('i').className = 'fas fa-circle';
            });
            return;
        }
        
        const { strength, requirements } = checkPasswordStrength(password);
        
        // Update strength bar
        strengthBar.className = 'password-strength-bar strength-' + strength;
        
        // Update requirement items
        Object.keys(requirements).forEach(req => {
            const item = requirementsContainer.querySelector(`[data-requirement="${req}"]`);
            if (item) {
                if (requirements[req]) {
                    item.classList.add('met');
                    item.querySelector('i').className = 'fas fa-check-circle';
                } else {
                    item.classList.remove('met');
                    item.querySelector('i').className = 'fas fa-circle';
                }
            }
        });
    }
    
    // Check if passwords match
    function checkPasswordMatch(password, confirmPassword, matchIndicator) {
        if (!confirmPassword) {
            matchIndicator.style.display = 'none';
            return true;
        }
        
        matchIndicator.style.display = 'flex';
        
        if (password === confirmPassword) {
            matchIndicator.className = 'password-match-indicator match';
            matchIndicator.querySelector('i').className = 'fas fa-check-circle';
            matchIndicator.querySelector('span').textContent = 'Passwords match';
            return true;
        } else {
            matchIndicator.className = 'password-match-indicator no-match';
            matchIndicator.querySelector('i').className = 'fas fa-times-circle';
            matchIndicator.querySelector('span').textContent = 'Passwords do not match';
            return false;
        }
    }
    
    // Validate password meets all requirements
    function validatePassword(password) {
        if (!password) return false;
        
        const { requirements } = checkPasswordStrength(password);
        return Object.values(requirements).every(Boolean);
    }
    
    // Add User Modal - Password field
    if (addPassword) {
        addPassword.addEventListener('input', function() {
            updatePasswordStrength(this.value, addStrengthBar, addRequirements);
            if (addConfirmPassword.value) {
                checkPasswordMatch(this.value, addConfirmPassword.value, addMatchIndicator);
            }
        });
    }
    
    // Add User Modal - Confirm password field
    if (addConfirmPassword) {
        addConfirmPassword.addEventListener('input', function() {
            checkPasswordMatch(addPassword.value, this.value, addMatchIndicator);
        });
    }
    
    // Edit User Modal - Password field
    if (editPassword) {
        editPassword.addEventListener('input', function() {
            if (this.value) {
                editRequirements.style.display = 'block';
                updatePasswordStrength(this.value, editStrengthBar, editRequirements);
            } else {
                editRequirements.style.display = 'none';
                editStrengthBar.className = 'password-strength-bar';
            }
            
            if (editConfirmPassword.value) {
                checkPasswordMatch(this.value, editConfirmPassword.value, editMatchIndicator);
            }
        });
    }
    
    // Edit User Modal - Confirm password field
    if (editConfirmPassword) {
        editConfirmPassword.addEventListener('input', function() {
            if (editPassword.value || this.value) {
                checkPasswordMatch(editPassword.value, this.value, editMatchIndicator);
            } else {
                editMatchIndicator.style.display = 'none';
            }
        });
    }
    
    // Form submission validation - Add User
    const addForm = document.getElementById("addUserForm");
    if (addForm) {
        addForm.addEventListener("submit", function(e) {
            const password = addPassword.value;
            const confirmPassword = addConfirmPassword.value;
            
            if (!validatePassword(password)) {
                e.preventDefault();
                showAlertModal("Please ensure your password meets all requirements.", 'warning', 'Weak Password');
                return false;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                showAlertModal("Passwords do not match.", 'warning', 'Validation Error');
                return false;
            }
            
            console.log("Add form submitting with valid password");
        });
    }
    
    // Form submission validation - Edit User
    const editForm = document.getElementById("editUserForm");
    if (editForm) {
        editForm.addEventListener("submit", function(e) {
            const password = editPassword.value;
            const confirmPassword = editConfirmPassword.value;
            
            // Only validate if password is being changed
            if (password) {
                if (!validatePassword(password)) {
                    e.preventDefault();
                    showAlertModal("Please ensure your password meets all requirements.", 'warning', 'Weak Password');
                    return false;
                }
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    showAlertModal("Passwords do not match.", 'warning', 'Validation Error');
                    return false;
                }
            }
            
            console.log("Edit form submitting");
        });
    }
}

// Filter initialization
function initializeFilters() {
    const searchInput = document.getElementById("search_users");
    const userTypeFilter = document.getElementById("user_type_filter");
    const statusFilter = document.getElementById("status_filter");

    function applyFilters() {
        const searchTerm = searchInput ? searchInput.value.toLowerCase() : "";
        const selectedUserType = userTypeFilter ? userTypeFilter.value : "";
        const selectedStatus = statusFilter ? statusFilter.value : "";
        
        const userRows = document.querySelectorAll(".user-row");
        const userCards = document.querySelectorAll(".user-card");

        function filterElement(element) {
            const name = element.getAttribute("data-name") || "";
            const email = element.getAttribute("data-email") || "";
            const userType = element.getAttribute("data-user-type") || "";
            const status = element.getAttribute("data-status") || "";

            const matchesSearch = searchTerm === "" || 
                                name.includes(searchTerm) || 
                                email.includes(searchTerm);
            
            const matchesUserType = selectedUserType === "" || userType === selectedUserType;
            const matchesStatus = selectedStatus === "" || status === selectedStatus;

            const isVisible = matchesSearch && matchesUserType && matchesStatus;
            element.style.display = isVisible ? "" : "none";
        }

        userRows.forEach(filterElement);
        userCards.forEach(filterElement);
    }

    if (searchInput) searchInput.addEventListener("input", applyFilters);
    if (userTypeFilter) userTypeFilter.addEventListener("change", applyFilters);
    if (statusFilter) statusFilter.addEventListener("change", applyFilters);
}

// Modal Functions
function openAddModal() {
    console.log("Opening add modal");
    const modal = document.getElementById("addUserModal");
    if (modal) {
        modal.classList.remove("hidden");
        document.getElementById("addUserForm").reset();
        
        // Reset password strength indicators
        const strengthBar = document.getElementById("add_password_strength_bar");
        const requirements = document.getElementById("add_password_requirements");
        const matchIndicator = document.getElementById("add_password_match");
        
        if (strengthBar) strengthBar.className = 'password-strength-bar';
        if (matchIndicator) matchIndicator.style.display = 'none';
        
        if (requirements) {
            const items = requirements.querySelectorAll('.requirement-item');
            items.forEach(item => {
                item.classList.remove('met');
                item.querySelector('i').className = 'fas fa-circle';
            });
        }
        
        console.log("Add modal opened");
    } else {
        console.error("Add modal not found");
    }
}

function closeAddModal() {
    console.log("Closing add modal");
    const modal = document.getElementById("addUserModal");
    if (modal) {
        modal.classList.add("hidden");
        console.log("Add modal closed");
    }
}

function openEditModal(userId) {
    console.log("Opening edit modal for user ID:", userId);
    const modal = document.getElementById("editUserModal");
    
    if (!modal) {
        console.error("Edit modal not found");
        return;
    }
    
    modal.classList.remove("hidden");
    console.log("Edit modal opened, fetching user data");
    
    // Reset password fields and indicators
    const editPassword = document.getElementById("edit_password");
    const editConfirmPassword = document.getElementById("edit_confirm_password");
    const strengthBar = document.getElementById("edit_password_strength_bar");
    const requirements = document.getElementById("edit_password_requirements");
    const matchIndicator = document.getElementById("edit_password_match");
    
    if (editPassword) editPassword.value = '';
    if (editConfirmPassword) editConfirmPassword.value = '';
    if (strengthBar) strengthBar.className = 'password-strength-bar';
    if (requirements) requirements.style.display = 'none';
    if (matchIndicator) matchIndicator.style.display = 'none';
    
    // Show loading state
    const form = document.getElementById("editUserForm");
    if (form) {
        form.style.opacity = "0.5";
        form.style.pointerEvents = "none";
    }
    
    fetch("../../backend/user-management/get_user.php?id=" + userId)
        .then(response => {
            console.log("Response received:", response);
            return response.json();
        })
        .then(data => {
            console.log("User data received:", data);
            
            // Remove loading state
            if (form) {
                form.style.opacity = "1";
                form.style.pointerEvents = "auto";
            }
            
            if (data.success) {
                const user = data.user;
                
                document.getElementById("edit_user_id").value = user.user_id || "";
                document.getElementById("edit_fname").value = user.fname || "";
                document.getElementById("edit_lname").value = user.lname || "";
                document.getElementById("edit_email").value = user.email || "";
                document.getElementById("edit_username").value = user.username || "";
                document.getElementById("edit_contact").value = user.contact_num || "";
                document.getElementById("edit_user_type").value = user.user_level_id || "";
                document.getElementById("edit_status").value = user.user_status || "active";
                
                console.log("Form populated successfully");
            } else {
                showAlertModal("Error: " + (data.message || "Failed to load user data"), 'error', 'Load Error');
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
            
            showAlertModal("Error loading user data: " + error.message, 'error', 'Load Error');
            closeEditModal();
        });
}

function closeEditModal() {
    console.log("Closing edit modal");
    const modal = document.getElementById("editUserModal");
    if (modal) {
        modal.classList.add("hidden");
        console.log("Edit modal closed");
    }
}

async function toggleUserStatus(userId, currentStatus) {
    console.log("Toggle status for user:", userId, "Current status:", currentStatus);
    const newStatus = currentStatus === "active" ? "inactive" : "active";
    const action = newStatus === "active" ? "activate" : "deactivate";
    
    if (await showConfirmModal("Are you sure you want to " + action + " this user?", "Confirm Action")) {
        window.location.href = "../../backend/user-management/toggle_user_status.php?id=" + userId + "&status=" + newStatus;
    }
}

// Close modals when clicking outside
document.addEventListener("click", function(event) {
    const addModal = document.getElementById("addUserModal");
    const editModal = document.getElementById("editUserModal");
    
    if (event.target === addModal) {
        closeAddModal();
    }
    if (event.target === editModal) {
        closeEditModal();
    }
});

// Debug: Test if modals exist on page load
console.log("Checking for modals...");
console.log("Add Modal exists:", document.getElementById("addUserModal") !== null);
console.log("Edit Modal exists:", document.getElementById("editUserModal") !== null);
console.log("Add User button exists:", document.querySelector('button[onclick="openAddModal()"]') !== null);
</script>
<?php
$user_management_content = ob_get_clean();

// Set the content for app.php
$content = $user_management_content;

// Include the app.php layout
include 'app.php';
?>
