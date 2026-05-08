<?php
/**
 * Edit Product Backend
 * File: C:\xampp\htdocs\MinC_Project\backend\products\edit_product.php
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

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $slugify = static function ($value) {
            $slug = strtolower(trim((string)$value));
            $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
            $slug = trim($slug, '-');
            return $slug !== '' ? $slug : 'product';
        };

        $generateUniqueSlug = static function ($pdo, $baseSlug, $excludeId) {
            $slug = $baseSlug;
            $counter = 2;
            while (true) {
                $check = $pdo->prepare("SELECT product_id FROM products WHERE product_slug = ? AND product_id != ? LIMIT 1");
                $check->execute([$slug, $excludeId]);
                if (!$check->fetch()) {
                    return $slug;
                }
                $slug = $baseSlug . '-' . $counter;
                $counter++;
                if ($counter > 9999) {
                    throw new Exception('Unable to generate a unique product slug.');
                }
            }
        };

        // Get form data
        $product_id = intval($_POST['product_id'] ?? 0);
        $product_line_id = $_POST['product_line_id'] ?? null;
        $product_name = trim($_POST['product_name'] ?? '');
        $product_code = trim($_POST['product_code'] ?? '');
        $product_description = trim($_POST['product_description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $stock_quantity = intval($_POST['stock_quantity'] ?? 0);
        $min_stock_level = intval($_POST['min_stock_level'] ?? 10);
        $display_order = intval($_POST['display_order'] ?? 0);
        $status = $_POST['status'] ?? 'active';
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;

        // Validation
        if ($product_id <= 0) {
            throw new Exception('Invalid product ID.');
        }
        if (empty($product_line_id)) {
            throw new Exception('Product line is required.');
        }
        if (empty($product_name)) {
            throw new Exception('Product name is required.');
        }
        if (empty($product_code)) {
            throw new Exception('Product code is required.');
        }

        // Get old product data for audit trail
        $old_query = "SELECT * FROM products WHERE product_id = :product_id";
        $old_stmt = $pdo->prepare($old_query);
        $old_stmt->execute(['product_id' => $product_id]);
        $old_data = $old_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$old_data) {
            throw new Exception('Product not found.');
        }

        // Check if product code already exists (excluding current product)
        $check_query = "SELECT product_id FROM products WHERE product_code = :product_code AND product_id != :product_id";
        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->execute([
            'product_code' => $product_code,
            'product_id' => $product_id
        ]);
        
        if ($check_stmt->rowCount() > 0) {
            throw new Exception('Product code already exists. Please use a unique code.');
        }

        $product_slug = $generateUniqueSlug($pdo, $slugify($product_name), $product_id);

        // Handle image upload
        $product_image = $old_data['product_image']; // Keep old image by default
        
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../Assets/images/products/';
            
            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_tmp = $_FILES['product_image']['tmp_name'];
            $file_name = $_FILES['product_image']['name'];
            $file_size = $_FILES['product_image']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            // Allowed extensions
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            if (!in_array($file_ext, $allowed_extensions)) {
                throw new Exception('Invalid file type. Only JPG, JPEG, PNG, WEBP, and GIF are allowed.');
            }

            if ($file_size > 2097152) { // 2MB
                throw new Exception('File size must not exceed 2MB.');
            }

            // Generate unique filename
            $unique_filename = 'product_' . time() . '_' . uniqid() . '.' . $file_ext;
            $upload_path = $upload_dir . $unique_filename;

            if (move_uploaded_file($file_tmp, $upload_path)) {
                // Delete old image if exists
                if (!empty($old_data['product_image']) && file_exists($upload_dir . $old_data['product_image'])) {
                    unlink($upload_dir . $old_data['product_image']);
                }
                $product_image = $unique_filename;
            } else {
                throw new Exception('Failed to upload image.');
            }
        }

        // Determine stock status based on quantity
        $stock_status = 'in_stock';
        if ($stock_quantity == 0) {
            $stock_status = 'out_of_stock';
        } elseif ($stock_quantity <= $min_stock_level) {
            $stock_status = 'low_stock';
        }

        // Begin transaction
        $pdo->beginTransaction();

        // Update product
        $update_query = "
            UPDATE products SET
                product_line_id = :product_line_id,
                product_name = :product_name,
                product_slug = :product_slug,
                product_code = :product_code,
                product_description = :product_description,
                product_image = :product_image,
                price = :price,
                stock_quantity = :stock_quantity,
                stock_status = :stock_status,
                min_stock_level = :min_stock_level,
                is_featured = :is_featured,
                display_order = :display_order,
                status = :status,
                updated_at = NOW()
            WHERE product_id = :product_id
        ";

        $update_stmt = $pdo->prepare($update_query);
        $update_stmt->execute([
            'product_line_id' => $product_line_id,
            'product_name' => $product_name,
            'product_slug' => $product_slug,
            'product_code' => $product_code,
            'product_description' => $product_description,
            'product_image' => $product_image,
            'price' => $price,
            'stock_quantity' => $stock_quantity,
            'stock_status' => $stock_status,
            'min_stock_level' => $min_stock_level,
            'is_featured' => $is_featured,
            'display_order' => $display_order,
            'status' => $status,
            'product_id' => $product_id
        ]);

        // Prepare audit trail data
        $new_value = [
            'product_line_id' => $product_line_id,
            'product_name' => $product_name,
            'product_slug' => $product_slug,
            'product_code' => $product_code,
            'product_description' => $product_description,
            'product_image' => $product_image,
            'price' => $price,
            'stock_quantity' => $stock_quantity,
            'stock_status' => $stock_status,
            'min_stock_level' => $min_stock_level,
            'is_featured' => $is_featured,
            'display_order' => $display_order,
            'status' => $status
        ];

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
            ':old_value' => json_encode($old_data),
            ':new_value' => json_encode($new_value),
            ':change_reason' => 'Updated product information',
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ':system_id' => 'minc_system'
        ]);

        // Commit transaction
        $pdo->commit();

        $_SESSION['success_message'] = 'Product updated successfully!';
        header('Location: ../../app/frontend/products.php');
        exit;

    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // Delete newly uploaded image if exists and there was an error
        if (isset($unique_filename) && file_exists('../../Assets/images/products/' . $unique_filename)) {
            unlink('../../Assets/images/products/' . $unique_filename);
        }

        $_SESSION['error_message'] = 'Error updating product: ' . $e->getMessage();
        header('Location: ../../app/frontend/products.php');
        exit;
    }
} else {
    $_SESSION['error_message'] = 'Invalid request method.';
    header('Location: ../../app/frontend/products.php');
    exit;
}
?>
