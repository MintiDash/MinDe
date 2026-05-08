<?php
/**
 * Add Category Backend
 * File: C:\xampp\htdocs\MinC_Project\backend\categories\add_category.php
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

        $generateUniqueSlug = static function ($pdo, $baseSlug) {
            $slug = $baseSlug;
            $counter = 2;
            while (true) {
                $check = $pdo->prepare("SELECT category_id FROM categories WHERE category_slug = ? LIMIT 1");
                $check->execute([$slug]);
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
        $category_name = trim($_POST['category_name'] ?? '');
        $category_description = trim($_POST['category_description'] ?? '');
        $display_order = intval($_POST['display_order'] ?? 0);
        
        // Validate required fields
        if (empty($category_name)) {
            throw new Exception('Category name is required.');
        }

        $category_slug = $generateUniqueSlug($pdo, $slugify($category_name));
        
        // Handle image upload
        $category_image = null;
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
        
        // Insert category
        $insert_query = "
            INSERT INTO categories (
                category_name, 
                category_slug, 
                category_description, 
                category_image, 
                display_order, 
                status,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())
        ";
        
        $stmt = $pdo->prepare($insert_query);
        $stmt->execute([
            $category_name,
            $category_slug,
            $category_description,
            $category_image,
            $display_order
        ]);
        
        $category_id = $pdo->lastInsertId();
        
        // Log audit trail
        $audit_query = "
            INSERT INTO audit_trail (
                user_id,
                session_username,
                action,
                entity_type,
                entity_id,
                new_value,
                change_reason,
                timestamp,
                ip_address,
                user_agent,
                system_id
            ) VALUES (?, ?, 'CREATE', 'category', ?, ?, 'Added new category', NOW(), ?, ?, 'minc_system')
        ";
        
        $new_value = json_encode([
            'category_name' => $category_name,
            'category_slug' => $category_slug,
            'category_description' => $category_description,
            'category_image' => $category_image,
            'display_order' => $display_order,
            'status' => 'active'
        ]);
        
        $audit_stmt = $pdo->prepare($audit_query);
        $audit_stmt->execute([
            $_SESSION['user_id'],
            $_SESSION['full_name'] ?? $_SESSION['fname'],
            $category_id,
            $new_value,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
        
        // Commit transaction
        $pdo->commit();
        
        $_SESSION['success_message'] = 'Category added successfully!';
        header('Location: ../../app/frontend/categories.php');
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Delete uploaded image if exists
        if (isset($category_image) && file_exists('../../Assets/images/categories/' . $category_image)) {
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
