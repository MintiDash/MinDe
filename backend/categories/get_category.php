<?php
/**
 * Get Category Backend
 * File: C:\xampp\htdocs\MinC_Project\backend\categories\get_category.php
 */

session_start();
require_once '../../database/connect_database.php';
require_once '../auth.php';

// Set JSON header
header('Content-Type: application/json');

// Validate session
$validation = validateSession();
if (!$validation['valid']) {
    echo json_encode(['success' => false, 'message' => 'Session expired']);
    exit;
}

// Check management level permission
if (!isManagementLevel()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if (isset($_GET['id'])) {
    try {
        $category_id = intval($_GET['id']);
        
        // Fetch category data
        $query = "
            SELECT 
                category_id,
                category_name,
                category_slug,
                category_description,
                category_image,
                display_order,
                status,
                created_at,
                updated_at
            FROM categories
            WHERE category_id = ?
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$category_id]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($category) {
            echo json_encode([
                'success' => true,
                'category' => $category
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Category not found'
            ]);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Category ID is required'
    ]);
}
?>