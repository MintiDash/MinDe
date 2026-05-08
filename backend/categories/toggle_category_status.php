<?php
/**
 * Toggle Category Status Backend
 * File: C:\xampp\htdocs\MinC_Project\backend\categories\toggle_category_status.php
 */

session_start();
require_once '../../database/connect_database.php';
require_once '../auth.php';

// Validate session
$validation = validateSession();
if (!$validation['valid']) {
    $_SESSION['error_message'] = 'Session expired. Please login again.';
    header('Location: ../../index.php');
    exit;
}

// Check management level permission
if (!isManagementLevel()) {
    $_SESSION['error_message'] = 'Access denied. Insufficient permissions.';
    header('Location: ../../app/frontend/categories.php');
    exit;
}

if (isset($_GET['id']) && isset($_GET['status'])) {
    try {
        $category_id = intval($_GET['id']);
        $new_status = trim($_GET['status']);
        
        // Validate status
        if (!in_array($new_status, ['active', 'inactive'])) {
            throw new Exception('Invalid status value.');
        }
        
        // Get old category data
        $old_query = "SELECT category_name, status FROM categories WHERE category_id = ?";
        $old_stmt = $pdo->prepare($old_query);
        $old_stmt->execute([$category_id]);
        $old_data = $old_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$old_data) {
            throw new Exception('Category not found.');
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Update status
        $update_query = "UPDATE categories SET status = ?, updated_at = NOW() WHERE category_id = ?";
        $stmt = $pdo->prepare($update_query);
        $stmt->execute([$new_status, $category_id]);
        
        // Log audit trail
        $audit_query = "
            INSERT INTO audit_trail (
                user_id,
                session_username,
                action,
                entity_type,
                entity_id,
                old_value,
                new_value,
                change_reason,
                timestamp,
                ip_address,
                user_agent,
                system_id
            ) VALUES (?, ?, 'UPDATE', 'category', ?, ?, ?, ?, NOW(), ?, ?, 'minc_system')
        ";
        
        $old_value = json_encode(['status' => $old_data['status']]);
        $new_value = json_encode(['status' => $new_status]);
        $change_reason = $new_status === 'active' ? 'Category activated' : 'Category deactivated';
        
        $audit_stmt = $pdo->prepare($audit_query);
        $audit_stmt->execute([
            $_SESSION['user_id'],
            $_SESSION['full_name'] ?? $_SESSION['fname'],
            $category_id,
            $old_value,
            $new_value,
            $change_reason,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
        
        // Commit transaction
        $pdo->commit();
        
        $action = $new_status === 'active' ? 'activated' : 'deactivated';
        $_SESSION['success_message'] = "Category '{$old_data['category_name']}' has been {$action} successfully!";
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    }
} else {
    $_SESSION['error_message'] = 'Invalid request parameters.';
}

header('Location: ../../app/frontend/categories.php');
exit;
?>