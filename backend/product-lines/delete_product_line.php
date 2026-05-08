<?php
/**
 * Delete Product Line Backend
 * File: C:\xampp\htdocs\MinC_Project\backend\product-lines\delete_product_line.php
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
    // Get product line ID
    $product_line_id = $_GET['id'] ?? null;
    
    if (empty($product_line_id)) {
        throw new Exception('Product line ID is required.');
    }
    
    // Get current product line data for audit trail
    $current_query = "SELECT * FROM product_lines WHERE product_line_id = ?";
    $current_stmt = $pdo->prepare($current_query);
    $current_stmt->execute([$product_line_id]);
    $current_data = $current_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$current_data) {
        throw new Exception('Product line not found.');
    }
    
    // Check if product line has associated products (if products table exists)
    // Uncomment this when products table is created
    /*
    $products_check = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE product_line_id = ?");
    $products_check->execute([$product_line_id]);
    $products_count = $products_check->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($products_count > 0) {
        throw new Exception('Cannot delete product line. It has ' . $products_count . ' associated products. Please remove or reassign the products first.');
    }
    */
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Delete product line
    $delete_query = "DELETE FROM product_lines WHERE product_line_id = ?";
    $stmt = $pdo->prepare($delete_query);
    $stmt->execute([$product_line_id]);
    
    // Delete product line image if exists
    if ($current_data['product_line_image']) {
        $image_path = '../../Assets/images/product-lines/' . $current_data['product_line_image'];
        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }
    
    // Log audit trail
    $audit_query = "
        INSERT INTO audit_trail (
            user_id,
            session_username,
            action,
            entity_type,
            entity_id,
            old_value,
            change_reason,
            timestamp,
            ip_address,
            user_agent,
            system_id
        ) VALUES (?, ?, 'DELETE', 'product_line', ?, ?, 'Deleted product line', NOW(), ?, ?, 'minc_system')
    ";
    
    $old_value = json_encode([
        'category_id' => $current_data['category_id'],
        'product_line_name' => $current_data['product_line_name'],
        'product_line_slug' => $current_data['product_line_slug'],
        'product_line_description' => $current_data['product_line_description'],
        'product_line_image' => $current_data['product_line_image'],
        'display_order' => $current_data['display_order'],
        'status' => $current_data['status']
    ]);
    
    $audit_stmt = $pdo->prepare($audit_query);
    $audit_stmt->execute([
        $_SESSION['user_id'],
        $_SESSION['full_name'] ?? $_SESSION['fname'],
        $product_line_id,
        $old_value,
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    ]);
    
    // Commit transaction
    $pdo->commit();
    
    $_SESSION['success_message'] = 'Product line deleted successfully!';
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
