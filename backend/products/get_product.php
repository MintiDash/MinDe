<?php
/**
 * Get Product Details Backend
 * File: C:\xampp\htdocs\MinC_Project\backend\products\get_product.php
 */

session_start();
require_once '../../database/connect_database.php';
require_once '../auth.php';

// Set JSON header
header('Content-Type: application/json');

// Validate session
$validation = validateSession();
if (!$validation['valid']) {
    echo json_encode([
        'success' => false,
        'message' => 'Session expired. Please login again.'
    ]);
    exit;
}

// Check if user has permission (IT Personnel, Owner, Manager)
if (!isManagementLevel()) {
    echo json_encode([
        'success' => false,
        'message' => 'Access denied. You do not have permission to perform this action.'
    ]);
    exit;
}

// Check if product ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Product ID is required.'
    ]);
    exit;
}

try {
    $product_id = intval($_GET['id']);

    // Fetch product details
    $query = "
        SELECT 
            p.*,
            pl.category_id,
            pl.product_line_name,
            c.category_name
        FROM products p
        INNER JOIN product_lines pl ON p.product_line_id = pl.product_line_id
        INNER JOIN categories c ON pl.category_id = c.category_id
        WHERE p.product_id = :product_id
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute(['product_id' => $product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        echo json_encode([
            'success' => false,
            'message' => 'Product not found.'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'product' => $product
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching product: ' . $e->getMessage()
    ]);
}
?>