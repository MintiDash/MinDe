<?php
/**
 * Get Product Line Data Backend
 * File: C:\xampp\htdocs\MinC_Project\backend\product-lines\get_product_line.php
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

// Check if user has permission
if (!isManagementLevel()) {
    echo json_encode([
        'success' => false,
        'message' => 'Access denied. You do not have permission to perform this action.'
    ]);
    exit;
}

try {
    // Get product line ID from query string
    $product_line_id = $_GET['id'] ?? null;

    if (empty($product_line_id)) {
        throw new Exception('Product line ID is required.');
    }

    // Fetch product line data
    $query = "
        SELECT 
            pl.*,
            c.category_name
        FROM product_lines pl
        LEFT JOIN categories c ON pl.category_id = c.category_id
        WHERE pl.product_line_id = ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$product_line_id]);
    $product_line = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product_line) {
        throw new Exception('Product line not found.');
    }

    echo json_encode([
        'success' => true,
        'product_line' => $product_line
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>