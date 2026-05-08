<?php
/**
 * Toggle User Status Backend with Audit Trail
 * File: C:\xampp\htdocs\MinC_Project\backend\user-management\toggle_user_status.php
 */

session_start();
require_once '../../database/connect_database.php';
require_once '../auth.php';

// Validate session
$validation = validateSession();
if (!$validation['valid']) {
    $_SESSION['error_message'] = 'Session invalid. Please login again.';
    header('Location: ../../app/frontend/user-management.php');
    exit;
}

// Check if user has permission
if (!isManagementLevel()) {
    $_SESSION['error_message'] = 'Access denied. You do not have permission to change user status.';
    header('Location: ../../app/frontend/user-management.php');
    exit;
}

// Get parameters
$user_id = intval($_GET['id'] ?? 0);
$new_status = $_GET['status'] ?? '';

// Validate inputs
if ($user_id === 0 || !in_array($new_status, ['active', 'inactive'])) {
    $_SESSION['error_message'] = 'Invalid parameters.';
    header('Location: ../../app/frontend/user-management.php');
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Prevent users from deactivating themselves
    if ($user_id == $_SESSION['user_id'] && $new_status === 'inactive') {
        throw new Exception('You cannot deactivate your own account.');
    }

    // Get current status for audit trail
    $current_status_query = "SELECT user_status FROM users WHERE user_id = :user_id";
    $current_status_stmt = $pdo->prepare($current_status_query);
    $current_status_stmt->execute([':user_id' => $user_id]);
    $current_data = $current_status_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current_data) {
        throw new Exception('User not found.');
    }

    // Update user status
    $update_query = "UPDATE users SET user_status = :status, updated_at = NOW() WHERE user_id = :user_id";
    $stmt = $pdo->prepare($update_query);
    $stmt->execute([
        ':status' => $new_status,
        ':user_id' => $user_id
    ]);

    // Prepare audit trail data
    $old_value = [
        'user_status' => $current_data['user_status']
    ];

    $new_value = [
        'user_status' => $new_status
    ];

    $action = $new_status === 'active' ? 'activated' : 'deactivated';
    $change_reason = 'User ' . $action;

    // Insert audit trail
    $audit_query = "
        INSERT INTO audit_trail (
            user_id, session_username, action, entity_type, entity_id,
            old_value, new_value, change_reason, timestamp, ip_address, user_agent
        ) VALUES (
            :user_id, :session_username, :action, :entity_type, :entity_id,
            :old_value, :new_value, :change_reason, NOW(), :ip_address, :user_agent
        )
    ";

    $audit_stmt = $pdo->prepare($audit_query);
    $audit_stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':session_username' => $_SESSION['username'] ?? $_SESSION['full_name'] ?? 'System',
        ':action' => 'update',
        ':entity_type' => 'user',
        ':entity_id' => $user_id,
        ':old_value' => json_encode($old_value),
        ':new_value' => json_encode($new_value),
        ':change_reason' => $change_reason,
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    // Commit transaction
    $pdo->commit();

    $_SESSION['success_message'] = "User $action successfully!";
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $_SESSION['error_message'] = 'Error updating user status: ' . $e->getMessage();
}

header('Location: ../../app/frontend/user-management.php');
exit;