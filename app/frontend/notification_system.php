<?php
/**
 * Enhanced Notification System Frontend
 * File: C:\xampp\htdocs\dmmmsu-extension\app\frontend\notification_system.php
 */

// Authentication and user data
include_once '../backend/auth.php';
requireAuth('../../index.php');

// Include database connection
include_once '../../database/connect_database.php';

$user_data = getCurrentUser();

// Set custom title for this page
$custom_title = 'Notification System - DMMMSU Extension System';

$user = [
    'full_name' => $user_data['name'] ?? $user_data['full_name'] ?? 'Guest User',
    'user_type' => $user_data['user_type'] ?? 'User',
    'department' => $user_data['department'] ?? 'General',
    'is_logged_in' => isset($current_user_id) && !empty($current_user_id)
];

// Handle different possible ID field names from getCurrentUser()
$current_user_id = $user_data['id'] ?? $user_data['employee_id'] ?? $user_data['user_id'] ?? null;
$current_user_type = $user_data['user_type'] ?? 'User';

// If no user ID found, redirect to login
if (!$current_user_id) {
    header('Location: dashboard.php');
    exit;
}

// Handle AJAX requests for notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'mark_read':
                $notification_id = (int)$_POST['notification_id'];
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, updated_at = NOW() WHERE notification_id = ? AND recipient_id = ?");
                $stmt->execute([$notification_id, $current_user_id]);
                
                // Log the activity
                $log_stmt = $pdo->prepare("INSERT INTO transaction_logs (date_time, activity, employee_id, created_at) VALUES (NOW(), ?, ?, NOW())");
                $log_stmt->execute([
                    "Marked notification #{$notification_id} as read",
                    $current_user_id
                ]);
                
                echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
                break;
                
            case 'mark_unread':
                $notification_id = (int)$_POST['notification_id'];
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 0, updated_at = NOW() WHERE notification_id = ? AND recipient_id = ?");
                $stmt->execute([$notification_id, $current_user_id]);
                
                // Log the activity
                $log_stmt = $pdo->prepare("INSERT INTO transaction_logs (date_time, activity, employee_id, created_at) VALUES (NOW(), ?, ?, NOW())");
                $log_stmt->execute([
                    "Marked notification #{$notification_id} as unread",
                    $current_user_id
                ]);
                
                echo json_encode(['success' => true, 'message' => 'Notification marked as unread']);
                break;
                
            case 'delete':
                $notification_id = (int)$_POST['notification_id'];
                $stmt = $pdo->prepare("DELETE FROM notifications WHERE notification_id = ? AND recipient_id = ?");
                $stmt->execute([$notification_id, $current_user_id]);
                
                // Log the activity
                $log_stmt = $pdo->prepare("INSERT INTO transaction_logs (date_time, activity, employee_id, created_at) VALUES (NOW(), ?, ?, NOW())");
                $log_stmt->execute([
                    "Deleted notification #{$notification_id}",
                    $current_user_id
                ]);
                
                echo json_encode(['success' => true, 'message' => 'Notification deleted']);
                break;
                
            case 'mark_all_read':
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, updated_at = NOW() WHERE recipient_id = ? AND is_read = 0");
                $affected = $stmt->execute([$current_user_id]);
                
                // Log the activity
                $log_stmt = $pdo->prepare("INSERT INTO transaction_logs (date_time, activity, employee_id, created_at) VALUES (NOW(), ?, ?, NOW())");
                $log_stmt->execute([
                    "Marked all notifications as read",
                    $current_user_id
                ]);
                
                echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
                break;
                
            case 'clear_all':
                // Only allow admin/staff to clear all notifications
                if (isAdmin() || isStaff() || isITStaff()) {
                    $stmt = $pdo->prepare("DELETE FROM notifications WHERE recipient_id = ?");
                    $stmt->execute([$current_user_id]);
                    
                    // Log the activity
                    $log_stmt = $pdo->prepare("INSERT INTO transaction_logs (date_time, activity, employee_id, created_at) VALUES (NOW(), ?, ?, NOW())");
                    $log_stmt->execute([
                        "Cleared all notifications",
                        $current_user_id
                    ]);
                    
                    echo json_encode(['success' => true, 'message' => 'All notifications cleared']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
                }
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Get filter parameters
$filter_type = $_GET['type'] ?? 'all';
$filter_status = $_GET['status'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Initialize variables with default values
$notifications = [];
$total_notifications = 0;
$total_pages = 1;
$stats = [
    'total' => 0,
    'unread' => 0,
    'terminal_reports' => 0,
    'extension_requests' => 0,
    'document_changes' => 0,
    'general_notifications' => 0
];

// Build query based on user permissions and filters
try {
    // DEBUG: Check user's role and related extensions
    $debug_query = "
        SELECT 
            u.employee_id,
            u.fname,
            u.lname,
            ul.user_type_name,
            d.department_name,
            GROUP_CONCAT(DISTINCT ewa.extension_id) as assigned_extensions
        FROM users u
        INNER JOIN user_levels ul ON u.user_level_id = ul.user_level_id
        LEFT JOIN departments d ON u.dept_id = d.department_id
        LEFT JOIN extension_worker_assignments ewa ON u.employee_id = ewa.employee_id
        WHERE u.employee_id = :user_id
        GROUP BY u.employee_id
    ";

    $debug_stmt = $pdo->prepare($debug_query);
    $debug_stmt->execute(['user_id' => $current_user_id]);
    $user_debug_info = $debug_stmt->fetch(PDO::FETCH_ASSOC);

    // Log this information
    error_log("User Debug Info for {$current_user_id}: " . json_encode($user_debug_info));

    // Base condition - notifications for this user
    $where_conditions = ["n.recipient_id = :user_id"];
    $params = ['user_id' => $current_user_id];
    
    // Get user's role and department info
    $user_info_query = "
        SELECT 
            u.employee_id,
            u.dept_id,
            ul.user_type_name,
            ul.user_level_id
        FROM users u
        INNER JOIN user_levels ul ON u.user_level_id = ul.user_level_id
        WHERE u.employee_id = :user_id
    ";
    $user_info_stmt = $pdo->prepare($user_info_query);
    $user_info_stmt->execute(['user_id' => $current_user_id]);
    $user_info = $user_info_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if user has any extension assignments
    $assignment_check = "
        SELECT COUNT(*) as assignment_count 
        FROM extension_worker_assignments 
        WHERE employee_id = :user_id
    ";
    $assign_stmt = $pdo->prepare($assignment_check);
    $assign_stmt->execute(['user_id' => $current_user_id]);
    $assignment_info = $assign_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Log for debugging
    error_log("User {$current_user_id} Info: " . json_encode($user_info));
    error_log("User {$current_user_id} Assignments: " . json_encode($assignment_info));
    
    // Filter by type
    if ($filter_type !== 'all') {
        $where_conditions[] = "n.type = :type";
        $params['type'] = $filter_type;
    }
    
    // Filter by read status
    if ($filter_status !== 'all') {
        $is_read = $filter_status === 'read' ? 1 : 0;
        $where_conditions[] = "n.is_read = :is_read";
        $params['is_read'] = $is_read;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Enhanced query with better joins
    $notifications_query = "
        SELECT 
            n.*,
            e.extension_name,
            e.department_id as extension_dept_id,
            CONCAT(u.fname, ' ', u.lname) as sender_name,
            u.employee_id as sender_id,
            d.department_name,
            ewa.assignment_id,
            CASE 
                WHEN ewa.assignment_id IS NOT NULL THEN 'assigned_worker'
                WHEN recipient.user_level_id = 5 THEN 'facilitator'
                WHEN recipient.user_level_id = 4 THEN 'user'
                WHEN recipient.user_level_id IN (2, 3) THEN 'staff_admin'
                ELSE 'other'
            END as recipient_role_context
        FROM notifications n
        LEFT JOIN extensions e ON n.related_extension_id = e.extension_id
        LEFT JOIN extension_worker_assignments ewa ON (
            e.extension_id = ewa.extension_id 
            AND ewa.employee_id = n.recipient_id
        )
        LEFT JOIN users u ON (
            SELECT uploaded_by FROM documents WHERE extension_id = n.related_extension_id LIMIT 1
        ) = u.employee_id
        LEFT JOIN departments d ON e.department_id = d.department_id
        LEFT JOIN users recipient ON n.recipient_id = recipient.employee_id
        WHERE {$where_clause}
        ORDER BY n.created_at DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $params['limit'] = $per_page;
    $params['offset'] = $offset;
    
    $stmt = $pdo->prepare($notifications_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Additional debug: Log what we found
    error_log("Notifications found for user {$current_user_id}: " . count($notifications));
    
    // Get total count for pagination
    $count_query = "SELECT COUNT(*) FROM notifications n WHERE {$where_clause}";
    $count_params = array_diff_key($params, ['limit' => '', 'offset' => '']);
    $count_stmt = $pdo->prepare($count_query);
    foreach ($count_params as $key => $value) {
        $count_stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $count_stmt->execute();
    $total_notifications = (int)$count_stmt->fetchColumn();
    
    // Get notification statistics
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread,
        SUM(CASE WHEN type = 'terminal_report_due' THEN 1 ELSE 0 END) as terminal_reports,
        SUM(CASE WHEN type = 'extension_request' THEN 1 ELSE 0 END) as extension_requests,
        SUM(CASE WHEN type = 'document_status_change' THEN 1 ELSE 0 END) as document_changes,
        SUM(CASE WHEN type = 'user_registration' THEN 1 ELSE 0 END) as user_registrations,
        SUM(CASE WHEN type = 'general' THEN 1 ELSE 0 END) as general_notifications
    FROM notifications 
    WHERE recipient_id = :user_id
";
    
    $stats_stmt = $pdo->prepare($stats_query);
    $stats_stmt->execute(['user_id' => $current_user_id]);
    $stats_result = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ensure stats has values
if ($stats_result) {
    $stats = [
        'total' => (int)($stats_result['total'] ?? 0),
        'unread' => (int)($stats_result['unread'] ?? 0),
        'terminal_reports' => (int)($stats_result['terminal_reports'] ?? 0),
        'extension_requests' => (int)($stats_result['extension_requests'] ?? 0),
        'document_changes' => (int)($stats_result['document_changes'] ?? 0),
        'user_registrations' => (int)($stats_result['user_registrations'] ?? 0),
        'general_notifications' => (int)($stats_result['general_notifications'] ?? 0)
    ];
}

    // Calculate pagination
    $total_pages = max(1, ceil($total_notifications / $per_page));
    
} catch (PDOException $e) {
    error_log("Notification query error: " . $e->getMessage());
    // Keep default initialized values
}

// Additional styles for notification system
$additional_styles = '
<style>
.notification-item {
    transition: all 0.3s ease;
    border-left: 4px solid transparent;
}

.notification-item.unread {
    border-left-color: #3B82F6;
    background-color: rgba(59, 130, 246, 0.05);
}

.notification-item.read {
    border-left-color: #E5E7EB;
    opacity: 0.8;
}

.notification-item:hover {
    transform: translateX(2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.notification-type-badge {
    transition: all 0.2s ease;
}

.notification-type-badge:hover {
    transform: scale(1.05);
}

.action-button {
    transition: all 0.2s ease;
}

.action-button:hover {
    transform: scale(1.1);
}

.filter-button {
    transition: all 0.3s ease;
}

.filter-button.active {
    background: linear-gradient(135deg, #3B82F6, #1D4ED8);
    color: white;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.stats-card {
    transition: all 0.3s ease;
}

.stats-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.notification-actions {
    opacity: 0;
    transition: opacity 0.3s ease;
}

.notification-item:hover .notification-actions {
    opacity: 1;
}

.pulse-notification {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: .5;
    }
}

.empty-state {
    background: linear-gradient(135deg, #F8FAFC, #E2E8F0);
    border: 2px dashed #CBD5E0;
}
</style>
';

// Start output buffering for main content
ob_start();
?>

<!-- Page Header -->
<div class="professional-card rounded-xl p-6 mb-6 animate-fadeIn">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 mb-2">
                Notification Center 🔔
            </h2>
            <p class="text-gray-600">
                Manage your notifications and stay updated with extension activities.
            </p>
        </div>
        <div class="hidden md:block">
            <div class="w-16 h-16 bg-gradient-to-br from-blue-400 to-indigo-600 rounded-xl flex items-center justify-center animate-float">
                <i class="fas fa-bell text-white text-2xl"></i>
            </div>
        </div>
    </div>
</div>

<!-- Notification Statistics -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <div class="stats-card professional-card rounded-xl p-6 hover-lift">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 mb-1">Total Notifications</p>
                <p class="text-3xl font-bold text-gray-900"><?php echo $stats['total']; ?></p>
            </div>
            <div class="p-4 bg-gradient-to-br from-blue-400 to-blue-600 rounded-xl shadow-lg">
                <i class="fas fa-inbox text-white text-2xl"></i>
            </div>
        </div>
    </div>

    <div class="stats-card professional-card rounded-xl p-6 hover-lift">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 mb-1">Unread</p>
                <p class="text-3xl font-bold text-gray-900"><?php echo $stats['unread']; ?></p>
                <?php if ($stats['unread'] > 0): ?>
                <div class="flex items-center mt-2">
                    <span class="status-dot status-active mr-2 pulse-notification"></span>
                    <span class="text-xs text-red-600 font-medium">Requires attention</span>
                </div>
                <?php endif; ?>
            </div>
            <div class="p-4 bg-gradient-to-br from-red-400 to-red-600 rounded-xl shadow-lg">
                <i class="fas fa-envelope text-white text-2xl"></i>
            </div>
        </div>
    </div>

    <div class="stats-card professional-card rounded-xl p-6 hover-lift">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 mb-1">Terminal Reports</p>
                <p class="text-3xl font-bold text-gray-900"><?php echo $stats['terminal_reports']; ?></p>
            </div>
            <div class="p-4 bg-gradient-to-br from-green-400 to-green-600 rounded-xl shadow-lg">
                <i class="fas fa-file-alt text-white text-2xl"></i>
            </div>
        </div>
    </div>

    <div class="stats-card professional-card rounded-xl p-6 hover-lift">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 mb-1">Extension Requests</p>
                <p class="text-3xl font-bold text-gray-900"><?php echo $stats['extension_requests']; ?></p>
            </div>
            <div class="p-4 bg-gradient-to-br from-purple-400 to-purple-600 rounded-xl shadow-lg">
                <i class="fas fa-clock text-white text-2xl"></i>
            </div>
        </div>
    </div>

    
</div>

<!-- Diagnostic Information (Debug Mode) -->
<?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
<div class="professional-card rounded-xl p-6 mb-6 bg-yellow-50 border-2 border-yellow-300">
    <h3 class="text-lg font-semibold text-yellow-800 mb-4">
        🔍 Diagnostic Information (Debug Mode)
    </h3>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
        <div class="bg-white p-4 rounded-lg">
            <h4 class="font-semibold text-gray-700 mb-2">Your Account Info:</h4>
            <ul class="space-y-1 text-gray-600">
                <li><strong>User ID:</strong> <?php echo $current_user_id; ?></li>
                <li><strong>Role:</strong> <?php echo $current_user_type; ?></li>
                <li><strong>Department:</strong> <?php echo $user['department']; ?></li>
            </ul>
        </div>
        
        <div class="bg-white p-4 rounded-lg">
            <h4 class="font-semibold text-gray-700 mb-2">Extension Assignments:</h4>
            <?php
            $check_assignments = $pdo->prepare("
                SELECT e.extension_name, ewa.assignment_id
                FROM extension_worker_assignments ewa
                INNER JOIN extensions e ON ewa.extension_id = e.extension_id
                WHERE ewa.employee_id = ?
            ");
            $check_assignments->execute([$current_user_id]);
            $assignments = $check_assignments->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <?php if (empty($assignments)): ?>
                <p class="text-red-600">❌ No extension assignments found</p>
                <p class="text-xs text-gray-500 mt-2">
                    This might explain why you're not seeing notifications. 
                    You need to be assigned to an extension project to receive its notifications.
                </p>
            <?php else: ?>
                <ul class="space-y-1 text-gray-600">
                    <?php foreach ($assignments as $assign): ?>
                        <li>✅ <?php echo htmlspecialchars($assign['extension_name']); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        
        <div class="bg-white p-4 rounded-lg">
            <h4 class="font-semibold text-gray-700 mb-2">Recent Notification Events:</h4>
            <?php
            $recent_events = $pdo->prepare("
                SELECT n.notification_id, n.title, n.type, n.created_at,
                       CONCAT(u.fname, ' ', u.lname) as recipient_name
                FROM notifications n
                INNER JOIN users u ON n.recipient_id = u.employee_id
                WHERE n.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY n.created_at DESC
                LIMIT 5
            ");
            $recent_events->execute();
            $events = $recent_events->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <?php if (empty($events)): ?>
                <p class="text-gray-500">No recent notification events in the system</p>
            <?php else: ?>
                <ul class="space-y-1 text-xs text-gray-600">
                    <?php foreach ($events as $event): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($event['recipient_name']); ?>:</strong>
                            <?php echo htmlspecialchars(substr($event['title'], 0, 40)); ?>...
                            <br>
                            <span class="text-gray-400">
                                <?php echo date('M j, g:i A', strtotime($event['created_at'])); ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        
        <div class="bg-white p-4 rounded-lg">
            <h4 class="font-semibold text-gray-700 mb-2">Query Results:</h4>
            <ul class="space-y-1 text-gray-600">
                <li><strong>Filter Type:</strong> <?php echo $filter_type; ?></li>
                <li><strong>Filter Status:</strong> <?php echo $filter_status; ?></li>
                <li><strong>Current Page:</strong> <?php echo $page; ?></li>
                <li><strong>Notifications Found:</strong> <?php echo count($notifications); ?></li>
                <li><strong>Total in DB:</strong> <?php echo $total_notifications; ?></li>
            </ul>
        </div>
    </div>
    
    <div class="mt-4 p-3 bg-blue-50 rounded-lg">
        <p class="text-sm text-blue-800">
            <strong>💡 To test notifications:</strong> 
            <?php if ($current_user_type === 'facilitator' || $current_user_type === 'user'): ?>
                Ask an admin to assign you to an extension project, then have someone submit a document or terminal report for that project.
            <?php else: ?>
                Create a test extension, assign users to it, then submit a document to trigger notifications.
            <?php endif; ?>
        </p>
    </div>
</div>
<?php endif; ?>

<!-- Filters and Actions -->
<div class="professional-card rounded-xl p-6 mb-6 animate-fadeIn">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <!-- Filter Buttons -->
        <div class="flex flex-wrap gap-2">
            <h3 class="text-lg font-semibold text-gray-800 mr-4">Filter by:</h3>
            
            <!-- Type Filters -->
            <div class="flex flex-wrap gap-2">
                <a href="?type=all&status=<?php echo $filter_status; ?>&page=1" 
                   class="filter-button px-4 py-2 rounded-lg text-sm font-medium border border-gray-200 <?php echo $filter_type === 'all' ? 'active' : 'bg-white text-gray-700 hover:bg-gray-50'; ?>">
                    All Types
                </a>
                <a href="?type=terminal_report_due&status=<?php echo $filter_status; ?>&page=1" 
                   class="filter-button px-4 py-2 rounded-lg text-sm font-medium border border-gray-200 <?php echo $filter_type === 'terminal_report_due' ? 'active' : 'bg-white text-gray-700 hover:bg-gray-50'; ?>">
                    Terminal Reports
                </a>
                <a href="?type=extension_request&status=<?php echo $filter_status; ?>&page=1" 
                   class="filter-button px-4 py-2 rounded-lg text-sm font-medium border border-gray-200 <?php echo $filter_type === 'extension_request' ? 'active' : 'bg-white text-gray-700 hover:bg-gray-50'; ?>">
                    Extension Requests
                </a>
                <a href="?type=document_status_change&status=<?php echo $filter_status; ?>&page=1" 
                   class="filter-button px-4 py-2 rounded-lg text-sm font-medium border border-gray-200 <?php echo $filter_type === 'document_status_change' ? 'active' : 'bg-white text-gray-700 hover:bg-gray-50'; ?>">
                    Document Changes
                </a>
                <a href="?type=user_registration&status=<?php echo $filter_status; ?>&page=1" 
   class="filter-button px-4 py-2 rounded-lg text-sm font-medium border border-gray-200 <?php echo $filter_type === 'user_registration' ? 'active' : 'bg-white text-gray-700 hover:bg-gray-50'; ?>">
    User Registrations
</a>
            </div>
        </div>

        <!-- Status Filters and Actions -->
        <div class="flex flex-wrap gap-2">
            <a href="?type=<?php echo $filter_type; ?>&status=all&page=1" 
               class="filter-button px-4 py-2 rounded-lg text-sm font-medium border border-gray-200 <?php echo $filter_status === 'all' ? 'active' : 'bg-white text-gray-700 hover:bg-gray-50'; ?>">
                All
            </a>
            <a href="?type=<?php echo $filter_type; ?>&status=unread&page=1" 
               class="filter-button px-4 py-2 rounded-lg text-sm font-medium border border-gray-200 <?php echo $filter_status === 'unread' ? 'active' : 'bg-white text-gray-700 hover:bg-gray-50'; ?>">
                Unread
            </a>
            <a href="?type=<?php echo $filter_type; ?>&status=read&page=1" 
               class="filter-button px-4 py-2 rounded-lg text-sm font-medium border border-gray-200 <?php echo $filter_status === 'read' ? 'active' : 'bg-white text-gray-700 hover:bg-gray-50'; ?>">
                Read
            </a>
            
            <!-- Bulk Actions (only show if there are notifications) -->
            <?php if ($stats['total'] > 0): ?>
            <div class="flex gap-2 ml-4">
                <?php if ($stats['unread'] > 0): ?>
                <button onclick="markAllAsRead()" class="quick-action-btn bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                    <i class="fas fa-check-double mr-2"></i>Mark All Read
                </button>
                <?php endif; ?>
                
                <?php if (isAdmin() || isStaff() || isITStaff()): ?>
                <button onclick="clearAllNotifications()" class="quick-action-btn bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                    <i class="fas fa-trash mr-2"></i>Clear All
                </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Notifications List -->
<div class="professional-card rounded-xl p-6 mb-6 animate-fadeIn">
    <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-semibold text-gray-800">
            Your Notifications 
            <span class="text-sm font-normal text-gray-500">
                (<?php echo $total_notifications; ?> total<?php echo $total_notifications !== count($notifications) ? ', showing ' . count($notifications) : ''; ?>)
            </span>
        </h3>
    </div>

    <!-- Diagnostic Information (Remove in production) -->
<?php if (isset($_GET['debug']) && ($_GET['debug'] === '1')): ?>
<div class="professional-card rounded-xl p-6 mb-6 bg-yellow-50 border-2 border-yellow-300">
    <h3 class="text-lg font-semibold text-yellow-800 mb-4">
        🔍 Diagnostic Information (Debug Mode)
    </h3>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
        <div class="bg-white p-4 rounded-lg">
            <h4 class="font-semibold text-gray-700 mb-2">Your Account Info:</h4>
            <ul class="space-y-1 text-gray-600">
                <li><strong>User ID:</strong> <?php echo $current_user_id; ?></li>
                <li><strong>Role:</strong> <?php echo $current_user_type; ?></li>
                <li><strong>Department:</strong> <?php echo $user['department']; ?></li>
            </ul>
        </div>
        
        <div class="bg-white p-4 rounded-lg">
            <h4 class="font-semibold text-gray-700 mb-2">Extension Assignments:</h4>
            <?php
            $check_assignments = $pdo->prepare("
                SELECT e.extension_name, ewa.assignment_id
                FROM extension_worker_assignments ewa
                INNER JOIN extensions e ON ewa.extension_id = e.extension_id
                WHERE ewa.employee_id = ?
            ");
            $check_assignments->execute([$current_user_id]);
            $assignments = $check_assignments->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <?php if (empty($assignments)): ?>
                <p class="text-red-600">❌ No extension assignments found</p>
                <p class="text-xs text-gray-500 mt-2">
                    This might explain why you're not seeing notifications. 
                    You need to be assigned to an extension project to receive its notifications.
                </p>
            <?php else: ?>
                <ul class="space-y-1 text-gray-600">
                    <?php foreach ($assignments as $assign): ?>
                        <li>✅ <?php echo htmlspecialchars($assign['extension_name']); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        
        <div class="bg-white p-4 rounded-lg">
            <h4 class="font-semibold text-gray-700 mb-2">Recent Notification Events:</h4>
            <?php
            $recent_events = $pdo->prepare("
                SELECT n.notification_id, n.title, n.type, n.created_at,
                       CONCAT(u.fname, ' ', u.lname) as recipient_name
                FROM notifications n
                INNER JOIN users u ON n.recipient_id = u.employee_id
                WHERE n.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY n.created_at DESC
                LIMIT 5
            ");
            $recent_events->execute();
            $events = $recent_events->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <?php if (empty($events)): ?>
                <p class="text-gray-500">No recent notification events in the system</p>
            <?php else: ?>
                <ul class="space-y-1 text-xs text-gray-600">
                    <?php foreach ($events as $event): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($event['recipient_name']); ?>:</strong>
                            <?php echo htmlspecialchars(substr($event['title'], 0, 40)); ?>...
                            <br>
                            <span class="text-gray-400">
                                <?php echo date('M j, g:i A', strtotime($event['created_at'])); ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        
        <div class="bg-white p-4 rounded-lg">
            <h4 class="font-semibold text-gray-700 mb-2">Query Results:</h4>
            <ul class="space-y-1 text-gray-600">
                <li><strong>Filter Type:</strong> <?php echo $filter_type; ?></li>
                <li><strong>Filter Status:</strong> <?php echo $filter_status; ?></li>
                <li><strong>Current Page:</strong> <?php echo $page; ?></li>
                <li><strong>Notifications Found:</strong> <?php echo count($notifications); ?></li>
                <li><strong>Total in DB:</strong> <?php echo $total_notifications; ?></li>
            </ul>
        </div>
    </div>
    
    <div class="mt-4 p-3 bg-blue-50 rounded-lg">
        <p class="text-sm text-blue-800">
            <strong>💡 To test notifications:</strong> 
            <?php if ($current_user_type === 'facilitator' || $current_user_type === 'user'): ?>
                Ask an admin to assign you to an extension project, then have someone submit a document or terminal report for that project.
            <?php else: ?>
                Create a test extension, assign users to it, then submit a document to trigger notifications.
            <?php endif; ?>
        </p>
    </div>
</div>
<?php endif; ?>

    <?php if (empty($notifications)): ?>
        <!-- Empty State -->
        <div class="empty-state rounded-xl p-12 text-center">
            <div class="w-24 h-24 bg-gradient-to-br from-gray-100 to-gray-200 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-bell-slash text-gray-400 text-3xl"></i>
            </div>
            <h4 class="text-xl font-semibold text-gray-600 mb-2">No notifications found</h4>
            <p class="text-gray-500 mb-6">
                <?php if ($filter_type !== 'all' || $filter_status !== 'all'): ?>
                    No notifications match your current filters. Try adjusting your search criteria.
                <?php else: ?>
                    You're all caught up! New notifications will appear here when available.
                <?php endif; ?>
            </p>
            <?php if ($filter_type !== 'all' || $filter_status !== 'all'): ?>
                <a href="notification_system.php" class="quick-action-btn bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium">
                    <i class="fas fa-filter mr-2"></i>Clear Filters
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Notifications Grid -->
        <div class="space-y-4">
            <?php foreach ($notifications as $notification): ?>
                <div class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?> rounded-xl p-6 border border-gray-200 bg-white hover:bg-gray-50" data-notification-id="<?php echo $notification['notification_id']; ?>">
                    <div class="flex items-start justify-between">
                        <div class="flex items-start space-x-4 flex-1">
                            <!-- Notification Icon -->
<!-- Notification Icon -->
<div class="flex-shrink-0">
<?php
$icon_class = '';
$icon_color = '';
switch ($notification['type']) {
    case 'terminal_report_due':
        $icon_class = 'fas fa-file-alt';
        $icon_color = 'text-green-600';
        break;
    case 'extension_request':
        $icon_class = 'fas fa-clock';
        $icon_color = 'text-blue-600';
        break;
    case 'document_status_change':
        $icon_class = 'fas fa-exchange-alt';
        $icon_color = 'text-purple-600';
        break;
    case 'user_registration':
        $icon_class = 'fas fa-user-plus';
        $icon_color = 'text-orange-600';
        break;
    case 'general':
    default:
        $icon_class = 'fas fa-info-circle';
        $icon_color = 'text-gray-600';
        break;
}
?>
                                <i class="<?php echo $icon_class . ' ' . $icon_color; ?> text-2xl"></i>
                            </div>
                            <!-- Notification Content -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-3 mb-2">
                                    <h4 class="text-lg font-semibold text-gray-800 truncate">
                                        <?php echo htmlspecialchars($notification['title']); ?>
                                    </h4>
                                    <span class="notification-type-badge px-3 py-1 rounded-full text-xs font-medium <?php echo $badge_color; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $notification['type'])); ?>
                                    </span>
                                    <?php if (!$notification['is_read']): ?>
                                        <span class="w-2 h-2 bg-blue-500 rounded-full pulse-notification"></span>
                                    <?php endif; ?>
                                </div>

                                <p class="text-gray-600 mb-3">
                                    <?php echo htmlspecialchars($notification['message']); ?>
                                </p>

                                <!-- Additional Details -->
                                <div class="flex items-center gap-6 text-sm text-gray-500">
                                    <span class="flex items-center">
                                        <i class="fas fa-calendar mr-2"></i>
                                        <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                    </span>
                                    
                                    <?php if ($notification['extension_name']): ?>
                                        <span class="flex items-center">
                                            <i class="fas fa-project-diagram mr-2"></i>
                                            <?php echo htmlspecialchars($notification['extension_name']); ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($notification['department_name']): ?>
                                        <span class="flex items-center">
                                            <i class="fas fa-building mr-2"></i>
                                            <?php echo htmlspecialchars($notification['department_name']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Notification Actions -->
                        <div class="notification-actions flex items-center gap-2 ml-4">
                            <?php if ($notification['is_read']): ?>
                                <button onclick="markAsUnread(<?php echo $notification['notification_id']; ?>)" 
                                        class="action-button p-2 text-gray-400 hover:text-yellow-600 hover:bg-yellow-50 rounded-lg" 
                                        title="Mark as unread">
                                    <i class="fas fa-envelope-open"></i>
                                </button>
                            <?php else: ?>
                                <button onclick="markAsRead(<?php echo $notification['notification_id']; ?>)" 
                                        class="action-button p-2 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg" 
                                        title="Mark as read">
                                    <i class="fas fa-check"></i>
                                </button>
                            <?php endif; ?>
                            
                            <button onclick="deleteNotification(<?php echo $notification['notification_id']; ?>)" 
                                    class="action-button p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg" 
                                    title="Delete notification">
                                <i class="fas fa-trash"></i>
                            </button>
                            
<?php if ($notification['related_extension_id']): ?>
    <a href="extension-projects.php?id=<?php echo $notification['related_extension_id']; ?>" 
       class="action-button p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg" 
       title="View related extension">
        <i class="fas fa-external-link-alt"></i>
    </a>
<?php endif; ?>

<?php if ($notification['type'] === 'user_registration' && (isAdmin() || isFacilitator())): ?>
    <a href="extension-workers.php?filter=inactive" 
       class="action-button p-2 text-gray-400 hover:text-orange-600 hover:bg-orange-50 rounded-lg" 
       title="Review registration">
        <i class="fas fa-user-check"></i>
    </a>
<?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="mt-8 flex items-center justify-between">
                <div class="text-sm text-gray-700">
                    Showing <?php echo (($page - 1) * $per_page) + 1; ?> to <?php echo min($page * $per_page, $total_notifications); ?> of <?php echo $total_notifications; ?> notifications
                </div>
                
                <div class="flex items-center space-x-2">
                    <?php if ($page > 1): ?>
                        <a href="?type=<?php echo $filter_type; ?>&status=<?php echo $filter_status; ?>&page=<?php echo $page - 1; ?>" 
                           class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                            Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="?type=<?php echo $filter_type; ?>&status=<?php echo $filter_status; ?>&page=<?php echo $i; ?>" 
                           class="px-3 py-2 text-sm font-medium <?php echo $i === $page ? 'text-blue-600 bg-blue-50 border-blue-500' : 'text-gray-700 bg-white hover:bg-gray-50'; ?> border border-gray-300 rounded-lg">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?type=<?php echo $filter_type; ?>&status=<?php echo $filter_status; ?>&page=<?php echo $page + 1; ?>" 
                           class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                            Next
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-xl p-6 flex items-center space-x-4">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <span class="text-gray-700 font-medium">Processing...</span>
    </div>
</div>

<!-- JavaScript for Notification Management -->
<script>
// Show loading overlay
function showLoading() {
    document.getElementById('loadingOverlay').classList.remove('hidden');
}

// Hide loading overlay
function hideLoading() {
    document.getElementById('loadingOverlay').classList.add('hidden');
}

// Show toast notification
function showToast(message, type = 'success') {
    const normalizedType = String(type || 'info').toLowerCase();
    const icon = normalizedType === 'error' ? 'error' : normalizedType;

    if (typeof window.showAppToast === 'function') {
        window.showAppToast(message, icon, { timer: 3800 });
        return;
    }

    if (typeof Swal !== 'undefined') {
        Swal.fire({
            toast: true,
            position: 'top',
            icon,
            title: String(message || ''),
            showConfirmButton: false,
            timer: 3800,
            timerProgressBar: true
        });
        return;
    }

    alert(String(message || ''));
}

// Hide toast notification
function hideToast() {
    return;
}

// AJAX helper function
function sendRequest(action, data, callback) {
    showLoading();
    
    const formData = new FormData();
    formData.append('action', action);
    
    for (const key in data) {
        formData.append(key, data[key]);
    }
    
    fetch('notification_system.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showToast(data.message, 'success');
            if (callback) callback();
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Error:', error);
        showToast('An error occurred. Please try again.', 'error');
    });
}

// Mark notification as read
function markAsRead(notificationId) {
    sendRequest('mark_read', { notification_id: notificationId }, () => {
        const notification = document.querySelector(`[data-notification-id="${notificationId}"]`);
        if (notification) {
            notification.classList.remove('unread');
            notification.classList.add('read');
            
            // Update the action button
            const actionButton = notification.querySelector('button[onclick*="markAsRead"]');
            if (actionButton) {
                actionButton.outerHTML = `<button onclick="markAsUnread(${notificationId})" 
                    class="action-button p-2 text-gray-400 hover:text-yellow-600 hover:bg-yellow-50 rounded-lg" 
                    title="Mark as unread">
                    <i class="fas fa-envelope-open"></i>
                </button>`;
            }
            
            // Remove pulse animation
            const pulseIndicator = notification.querySelector('.pulse-notification');
            if (pulseIndicator) {
                pulseIndicator.remove();
            }
        }
        
        // Update unread count (would need to refresh for accurate count)
        setTimeout(() => location.reload(), 1000);
    });
}

// Mark notification as unread
function markAsUnread(notificationId) {
    sendRequest('mark_unread', { notification_id: notificationId }, () => {
        const notification = document.querySelector(`[data-notification-id="${notificationId}"]`);
        if (notification) {
            notification.classList.remove('read');
            notification.classList.add('unread');
            
            // Update the action button
            const actionButton = notification.querySelector('button[onclick*="markAsUnread"]');
            if (actionButton) {
                actionButton.outerHTML = `<button onclick="markAsRead(${notificationId})" 
                    class="action-button p-2 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg" 
                    title="Mark as read">
                    <i class="fas fa-check"></i>
                </button>`;
            }
            
            // Add pulse indicator to title area
            const title = notification.querySelector('h4');
            if (title && !notification.querySelector('.pulse-notification')) {
                const pulseSpan = document.createElement('span');
                pulseSpan.className = 'w-2 h-2 bg-blue-500 rounded-full pulse-notification ml-2';
                title.parentNode.insertBefore(pulseSpan, title.nextSibling);
            }
        }
        
        // Update unread count (would need to refresh for accurate count)
        setTimeout(() => location.reload(), 1000);
    });
}

// Delete notification
async function deleteNotification(notificationId) {
    if (await showConfirmModal('Are you sure you want to delete this notification? This action cannot be undone.', 'Delete Notification')) {
        sendRequest('delete', { notification_id: notificationId }, () => {
            const notification = document.querySelector(`[data-notification-id="${notificationId}"]`);
            if (notification) {
                notification.style.transition = 'all 0.3s ease';
                notification.style.transform = 'translateX(100%)';
                notification.style.opacity = '0';
                
                setTimeout(() => {
                    notification.remove();
                    
                    // Check if this was the last notification
                    const remainingNotifications = document.querySelectorAll('.notification-item');
                    if (remainingNotifications.length === 0) {
                        location.reload();
                    }
                }, 300);
            }
        });
    }
}

// Mark all notifications as read
async function markAllAsRead() {
    if (await showConfirmModal('Mark all notifications as read?', 'Mark All Read')) {
        sendRequest('mark_all_read', {}, () => {
            setTimeout(() => location.reload(), 1000);
        });
    }
}

// Clear all notifications (admin/staff only)
async function clearAllNotifications() {
    if (await showConfirmModal('Are you sure you want to delete ALL notifications? This action cannot be undone.', 'Clear Notifications')) {
        sendRequest('clear_all', {}, () => {
            setTimeout(() => location.reload(), 1000);
        });
    }
}

// Auto-refresh notifications every 5 minutes
setInterval(() => {
    // Only refresh if user is still on the page and there are unread notifications
    if (!document.hidden && document.querySelectorAll('.notification-item.unread').length > 0) {
        const currentUrl = new URL(window.location);
        fetch(`notification_system.php${currentUrl.search}`, {
            method: 'GET'
        })
        .then(response => response.text())
        .then(html => {
            // Parse the response to check for new notifications
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newNotifications = doc.querySelectorAll('.notification-item');
            const currentNotifications = document.querySelectorAll('.notification-item');
            
            // If notification count changed, refresh the page
            if (newNotifications.length !== currentNotifications.length) {
                location.reload();
            }
        })
        .catch(error => {
            console.log('Auto-refresh failed:', error);
        });
    }
}, 300000); // 5 minutes

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Only process shortcuts when not typing in input fields
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
    
    switch(e.key) {
        case 'r':
            if (e.ctrlKey || e.metaKey) {
                e.preventDefault();
                location.reload();
            }
            break;
        case 'a':
            if (e.ctrlKey || e.metaKey) {
                e.preventDefault();
                const unreadCount = document.querySelectorAll('.notification-item.unread').length;
                if (unreadCount > 0) {
                    markAllAsRead();
                }
            }
            break;
    }
});

// Add smooth scrolling for pagination
document.querySelectorAll('a[href*="page="]').forEach(link => {
    link.addEventListener('click', function(e) {
        // Add a smooth scroll to top when changing pages
        setTimeout(() => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }, 100);
    });
});

// Initialize tooltips for action buttons
document.addEventListener('DOMContentLoaded', function() {
    // Add hover effects and better UX
    const notificationItems = document.querySelectorAll('.notification-item');
    
    notificationItems.forEach(item => {
        // Add click handler to mark as read when clicking on the notification body
        const actionArea = item.querySelector('.notification-actions');
        
        item.addEventListener('click', function(e) {
            // Only trigger if not clicking on action buttons
            if (!actionArea.contains(e.target) && !item.classList.contains('read')) {
                const notificationId = item.dataset.notificationId;
                markAsRead(notificationId);
            }
        });
        
        // Improve accessibility
        item.setAttribute('role', 'button');
        item.setAttribute('tabindex', '0');
        
        // Add keyboard support
        item.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !item.classList.contains('read')) {
                const notificationId = item.dataset.notificationId;
                markAsRead(notificationId);
            }
        });
    });
});

// Show helpful tips for first-time users
if (document.querySelectorAll('.notification-item').length > 0 && 
    !localStorage.getItem('notification_tips_shown')) {
    
    setTimeout(() => {
        showToast('💡 Tip: Click on notifications to mark them as read, or use the action buttons for more options!', 'success');
        localStorage.setItem('notification_tips_shown', 'true');
    }, 2000);
}

// Performance optimization: Virtual scrolling for large notification lists
if (document.querySelectorAll('.notification-item').length > 50) {
    console.log('Large notification list detected. Consider implementing virtual scrolling for better performance.');
}
</script>

<?php
$notification_content = ob_get_clean();

// Set the content for app.php
$content = $notification_content;

// Include the app.php layout
include 'app.php';
?>
