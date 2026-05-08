<?php
/**
 * Toggle Product Line Status Backend
 * File: C:\xampp\htdocs\MinC_Project\backend\product-lines\toggle_product_line_status.php
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
    header('Location: ../../app/frontend/product-lines.php');
    exit;
}

try {
    // Get parameters
    $product_line_id = $_GET['id'] ?? null;
    $new_status = $_GET['status'] ?? null;
    
    // Validate parameters
    if (empty($product_line_id)) {
        throw new Exception('Product line ID is required.');
    }
    
    if (empty($new_status) || !in_array($new_status, ['active', 'inactive'])) {
        throw new Exception('Invalid status value.');
    }
    
    // Get current product line data
    $current_query = "SELECT * FROM product_lines WHERE product_line_id = ?";
    $current_stmt = $pdo->prepare($current_query);
    $current_stmt->execute([$product_line_id]);
    $current_data = $current_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$current_data) {
        throw new Exception('Product line not found.');
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Update status
    $update_query = "UPDATE product_lines SET status = ?, updated_at = NOW() WHERE product_line_id = ?";
    $stmt = $pdo->prepare($update_query);
    $stmt->execute([$new_status, $product_line_id]);
    
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
        ) VALUES (?, ?, 'UPDATE', 'product_line', ?, ?, ?, ?, NOW(), ?, ?, 'minc_system')
    ";
    
    $old_value = json_encode(['status' => $current_data['status']]);
    $new_value = json_encode(['status' => $new_status]);
    $change_reason = $new_status === 'active' ? 'Product line activated' : 'Product line deactivated';
    
    $audit_stmt = $pdo->prepare($audit_query);
    $audit_stmt->execute([
        $_SESSION['user_id'],
        $_SESSION['full_name'] ?? $_SESSION['fname'],
        $product_line_id,
        $old_value,
        $new_value,
        $change_reason,
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    ]);
    
    // Commit transaction
    $pdo->commit();
    
    $_SESSION['success_message'] = ucfirst($change_reason) . ' successfully!';
    header('Location: ../../app/frontend/product-lines.php');
    exit;
    
} catch (Exception $e) {
    // Rollback transaction
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    header('Location: ../../app/frontend/product-lines.php');
    exit;
}
?>