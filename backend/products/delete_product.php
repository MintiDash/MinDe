<?php
/**
 * Delete Product Backend
 * File: C:\xampp\htdocs\MinC_Project\backend\products\delete_product.php
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

// Check if product ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = 'Product ID is required.';
    header('Location: ../../app/frontend/products.php');
    exit;
}

try {
    $product_id = intval($_GET['id']);

    // Get product data for audit trail and image deletion
    $query = "SELECT * FROM products WHERE product_id = :product_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['product_id' => $product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        throw new Exception('Product not found.');
    }

    // Begin transaction
    $pdo->beginTransaction();

    // Delete product
    $delete_query = "DELETE FROM products WHERE product_id = :product_id";
    $delete_stmt = $pdo->prepare($delete_query);
    $delete_stmt->execute(['product_id' => $product_id]);

    // Delete product image if exists
    if (!empty($product['product_image'])) {
        $image_path = '../../Assets/images/products/' . $product['product_image'];
        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }

    // Insert audit trail
    $audit_query = "
        INSERT INTO audit_trail (
            user_id, session_username, action, entity_type, entity_id,
            old_value, new_value, change_reason, timestamp, ip_address, user_agent, system_id
        ) VALUES (
            :user_id, :session_username, :action, :entity_type, :entity_id,
            :old_value, NULL, :change_reason, NOW(), :ip_address, :user_agent, :system_id
        )
    ";

    $audit_stmt = $pdo->prepare($audit_query);
    $audit_stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':session_username' => $_SESSION['full_name'] ?? $_SESSION['fname'] ?? 'System',
        ':action' => 'DELETE',
        ':entity_type' => 'product',
        ':entity_id' => $product_id,
        ':old_value' => json_encode($product),
        ':change_reason' => 'Deleted product',
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ':system_id' => 'minc_system'
    ]);

    // Commit transaction
    $pdo->commit();

    $_SESSION['success_message'] = 'Product deleted successfully!';
    header('Location: ../../app/frontend/products.php');
    exit;

} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['error_message'] = 'Error deleting product: ' . $e->getMessage();
    header('Location: ../../app/frontend/products.php');
    exit;
}
?>