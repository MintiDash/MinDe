<?php
/**
 * Delete Category Backend
 * File: C:\xampp\htdocs\MinC_Project\backend\categories\delete_category.php
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

if (isset($_GET['id'])) {
    try {
        $category_id = intval($_GET['id']);
        
        // Get category data before deletion
        $query = "SELECT * FROM categories WHERE category_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$category_id]);
        $category_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$category_data) {
            throw new Exception('Category not found.');
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Delete category
        $delete_query = "DELETE FROM categories WHERE category_id = ?";
        $delete_stmt = $pdo->prepare($delete_query);
        $delete_stmt->execute([$category_id]);
        
        // Delete category image if exists
        if ($category_data['category_image']) {
            $image_path = '../../Assets/images/categories/' . $category_data['category_image'];
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
            ) VALUES (?, ?, 'DELETE', 'category', ?, ?, 'Deleted category', NOW(), ?, ?, 'minc_system')
        ";
        
        $old_value = json_encode([
            'category_name' => $category_data['category_name'],
            'category_slug' => $category_data['category_slug'],
            'category_description' => $category_data['category_description'],
            'category_image' => $category_data['category_image'],
            'display_order' => $category_data['display_order'],
            'status' => $category_data['status']
        ]);
        
        $audit_stmt = $pdo->prepare($audit_query);
        $audit_stmt->execute([
            $_SESSION['user_id'],
            $_SESSION['full_name'] ?? $_SESSION['fname'],
            $category_id,
            $old_value,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
        
        // Commit transaction
        $pdo->commit();
        
        $_SESSION['success_message'] = "Category '{$category_data['category_name']}' has been deleted successfully!";
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    }
} else {
    $_SESSION['error_message'] = 'Category ID is required.';
}

header('Location: ../../app/frontend/categories.php');
exit;
?>
