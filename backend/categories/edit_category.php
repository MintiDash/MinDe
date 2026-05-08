<?php
/**
 * Edit Category Backend
 * File: C:\xampp\htdocs\MinC_Project\backend\categories\edit_category.php
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $slugify = static function ($value) {
            $slug = strtolower(trim((string)$value));
            $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
            $slug = trim($slug, '-');
            return $slug !== '' ? $slug : 'category';
        };

        $generateUniqueSlug = static function ($pdo, $baseSlug, $excludeId) {
            $slug = $baseSlug;
            $counter = 2;
            while (true) {
                $check = $pdo->prepare("SELECT category_id FROM categories WHERE category_slug = ? AND category_id != ? LIMIT 1");
                $check->execute([$slug, $excludeId]);
                if (!$check->fetch()) {
                    return $slug;
                }
                $slug = $baseSlug . '-' . $counter;
                $counter++;
                if ($counter > 9999) {
                    throw new Exception('Unable to generate a unique category slug.');
                }
            }
        };

        // Get form data
        $category_id = intval($_POST['category_id'] ?? 0);
        $category_name = trim($_POST['category_name'] ?? '');
        $category_description = trim($_POST['category_description'] ?? '');
        $display_order = intval($_POST['display_order'] ?? 0);
        $status = trim($_POST['status'] ?? 'active');
        
        // Validate required fields
        if (empty($category_id)) {
            throw new Exception('Category ID is required.');
        }
        
        if (empty($category_name)) {
            throw new Exception('Category name is required.');
        }
        
        // Validate status
        if (!in_array($status, ['active', 'inactive'])) {
            throw new Exception('Invalid status value.');
        }
        
        // Get old category data for audit trail
        $old_query = "SELECT * FROM categories WHERE category_id = ?";
        $old_stmt = $pdo->prepare($old_query);
        $old_stmt->execute([$category_id]);
        $old_data = $old_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$old_data) {
            throw new Exception('Category not found.');
        }
        
        $category_slug = $generateUniqueSlug($pdo, $slugify($category_name), $category_id);
        
        // Handle image upload
        $category_image = $old_data['category_image'];
        if (isset($_FILES['category_image']) && $_FILES['category_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../Assets/images/categories/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_tmp = $_FILES['category_image']['tmp_name'];
            $file_name = $_FILES['category_image']['name'];
            $file_size = $_FILES['category_image']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Validate file type
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            if (!in_array($file_ext, $allowed_extensions)) {
                throw new Exception('Invalid file type. Only JPG, PNG, WEBP, and GIF are allowed.');
            }
            
            // Validate file size (2MB max)
            if ($file_size > 2097152) {
                throw new Exception('File size too large. Maximum size is 2MB.');
            }
            
            // Delete old image if exists
            if ($old_data['category_image'] && file_exists($upload_dir . $old_data['category_image'])) {
                unlink($upload_dir . $old_data['category_image']);
            }
            
            // Generate unique filename
            $category_image = 'category_' . time() . '_' . uniqid() . '.' . $file_ext;
            $upload_path = $upload_dir . $category_image;
            
            // Move uploaded file
            if (!move_uploaded_file($file_tmp, $upload_path)) {
                throw new Exception('Failed to upload image.');
            }
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Update category
        $update_query = "
            UPDATE categories SET
                category_name = ?,
                category_slug = ?,
                category_description = ?,
                category_image = ?,
                display_order = ?,
                status = ?,
                updated_at = NOW()
            WHERE category_id = ?
        ";
        
        $stmt = $pdo->prepare($update_query);
        $stmt->execute([
            $category_name,
            $category_slug,
            $category_description,
            $category_image,
            $display_order,
            $status,
            $category_id
        ]);
        
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
            ) VALUES (?, ?, 'UPDATE', 'category', ?, ?, ?, 'Updated category information', NOW(), ?, ?, 'minc_system')
        ";
        
        $old_value = json_encode([
            'category_name' => $old_data['category_name'],
            'category_slug' => $old_data['category_slug'],
            'category_description' => $old_data['category_description'],
            'category_image' => $old_data['category_image'],
            'display_order' => $old_data['display_order'],
            'status' => $old_data['status']
        ]);
        
        $new_value = json_encode([
            'category_name' => $category_name,
            'category_slug' => $category_slug,
            'category_description' => $category_description,
            'category_image' => $category_image,
            'display_order' => $display_order,
            'status' => $status
        ]);
        
        $audit_stmt = $pdo->prepare($audit_query);
        $audit_stmt->execute([
            $_SESSION['user_id'],
            $_SESSION['full_name'] ?? $_SESSION['fname'],
            $category_id,
            $old_value,
            $new_value,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
        
        // Commit transaction
        $pdo->commit();
        
        $_SESSION['success_message'] = 'Category updated successfully!';
        header('Location: ../../app/frontend/categories.php');
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Delete newly uploaded image if exists and there was an error
        if (isset($category_image) && $category_image !== $old_data['category_image'] && file_exists('../../Assets/images/categories/' . $category_image)) {
            unlink('../../Assets/images/categories/' . $category_image);
        }
        
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        header('Location: ../../app/frontend/categories.php');
        exit;
    }
} else {
    $_SESSION['error_message'] = 'Invalid request method.';
    header('Location: ../../app/frontend/categories.php');
    exit;
}
?>
