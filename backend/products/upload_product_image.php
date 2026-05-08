<?php
session_start();
require_once '../../database/connect_database.php';
require_once '../auth.php';

header('Content-Type: application/json');

$uploaded_path = null;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    $validation = validateSession(false);
    if (!$validation['valid']) {
        http_response_code(401);
        throw new Exception('Please login first.');
    }

    // Role-1 (IT Personnel) only.
    if (!hasUserLevel(1)) {
        http_response_code(403);
        throw new Exception('Only role-1 admins can update product images.');
    }

    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    if ($product_id <= 0) {
        throw new Exception('Invalid product ID.');
    }

    if (!isset($_FILES['product_image']) || $_FILES['product_image']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Please select an image to upload.');
    }

    $file_tmp = $_FILES['product_image']['tmp_name'];
    $file_name = $_FILES['product_image']['name'];
    $file_size = (int) $_FILES['product_image']['size'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    if (!in_array($file_ext, $allowed_extensions, true)) {
        throw new Exception('Invalid file type. Use JPG, JPEG, PNG, WEBP, or GIF.');
    }

    if ($file_size > 2097152) {
        throw new Exception('Image size must not exceed 2MB.');
    }

    $image_info = @getimagesize($file_tmp);
    if ($image_info === false) {
        throw new Exception('Uploaded file is not a valid image.');
    }

    $product_stmt = $pdo->prepare("SELECT product_id, product_image FROM products WHERE product_id = :product_id LIMIT 1");
    $product_stmt->execute([':product_id' => $product_id]);
    $product = $product_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        throw new Exception('Product not found.');
    }

    $upload_dir = '../../Assets/images/products/';
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
        throw new Exception('Failed to prepare upload directory.');
    }

    $new_filename = 'product_' . $product_id . '_' . time() . '_' . uniqid('', true) . '.' . $file_ext;
    $upload_path = $upload_dir . $new_filename;

    if (!move_uploaded_file($file_tmp, $upload_path)) {
        throw new Exception('Failed to save uploaded image.');
    }
    $uploaded_path = $upload_path;

    $update_stmt = $pdo->prepare("
        UPDATE products 
        SET product_image = :product_image, updated_at = NOW()
        WHERE product_id = :product_id
    ");
    $update_stmt->execute([
        ':product_image' => $new_filename,
        ':product_id' => $product_id
    ]);

    if (!empty($product['product_image'])) {
        $old_path = $upload_dir . $product['product_image'];
        if (is_file($old_path)) {
            @unlink($old_path);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Product image updated successfully.',
        'product_image' => $new_filename,
        'image_url' => '../Assets/images/products/' . $new_filename
    ]);
} catch (Exception $e) {
    if ($uploaded_path && is_file($uploaded_path)) {
        @unlink($uploaded_path);
    }

    if (http_response_code() === 200) {
        http_response_code(400);
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
