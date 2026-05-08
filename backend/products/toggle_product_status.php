<?php
/**
 * Toggle Product Status Backend
 * File: C:\xampp\htdocs\MinC_Project\backend\products\toggle_product_status.php
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

// Check if user has permission (IT Personnel, Owner, Manager)
if (!isManagementLevel()) {
    $_SESSION['error_message'] = 'Access denied. You do not have permission to perform this action.';
    header('Location: ../../app/frontend/products.php');
    exit;
}

// Check if required parameters are provided
if (!isset($_GET['id']) || !isset($_GET['status'])) {
    $_SESSION['error_message'] = 'Missing required parameters.';
    header('Location: ../../app/frontend/products.php');
    exit;
}

try {
    $product_id = intval($_GET['id']);
    $new_status = $_GET['status'];

    // Validate status
    $valid_statuses = ['active', 'inactive', 'discontinued'];
    if (!in_array($new_status, $valid_statuses)) {
        throw new Exception('Invalid status value.');
    }

    // Get old product data for audit trail
    $old_query = "SELECT status FROM products WHERE product_id = :product_id";
    $old_stmt = $pdo->prepare($old_query);
    $old_stmt->execute(['product_id' => $product_id]);
    $old_data = $old_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$old_data) {
        throw new Exception('Product not found.');
    }

    // Begin transaction
    $pdo->beginTransaction();

    // Update product status
    $update_query = "UPDATE products SET status = :status, updated_at = NOW() WHERE product_id = :product_id";
    $update_stmt = $pdo->prepare($update_query);
    $update_stmt->execute([
        'status' => $new_status,
        'product_id' => $product_id
    ]);

    // Determine action text for audit trail
    $action_text = $new_status === 'active' ? 'activated' : ($new_status === 'inactive' ? 'deactivated' : 'discontinued');
    
    // Insert audit trail
    $audit_query = "
        INSERT INTO audit_trail (
            user_id, session_username, action, entity_type, entity_id,
            old_value, new_value, change_reason, timestamp, ip_address, user_agent, system_id
        ) VALUES (
            :user_id, :session_username, :action, :entity_type, :entity_id,
            :old_value, :new_value, :change_reason, NOW(), :ip_address, :user_agent, :system_id
        )
    ";

    $audit_stmt = $pdo->prepare($audit_query);
    $audit_stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':session_username' => $_SESSION['full_name'] ?? $_SESSION['fname'] ?? 'System',
        ':action' => 'UPDATE',
        ':entity_type' => 'product',
        ':entity_id' => $product_id,
        ':old_value' => json_encode(['status' => $old_data['status']]),
        ':new_value' => json_encode(['status' => $new_status]),
        ':change_reason' => "Product {$action_text}",
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ':system_id' => 'minc_system'
    ]);

    // Commit transaction
    $pdo->commit();

    $_SESSION['success_message'] = "Product {$action_text} successfully!";
    header('Location: ../../app/frontend/products.php');
    exit;

} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['error_message'] = 'Error updating product status: ' . $e->getMessage();
    header('Location: ../../app/frontend/products.php');
    exit;
}
?>