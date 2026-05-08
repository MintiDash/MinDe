<?php
header('Content-Type: application/json');
include_once '../auth.php';
include_once '../../database/connect_database.php';

// Validate session
$validation = validateSession();
if (!$validation['valid']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check permission
if (!isManagementLevel()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Customer ID is required']);
    exit;
}

$customer_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

try {
    // Get customer details
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            CONCAT(c.first_name, ' ', c.last_name) as full_name,
            u.user_status,
            COUNT(DISTINCT o.order_id) as total_orders,
            COALESCE(SUM(o.total_amount), 0) as total_spent,
            COALESCE(AVG(o.total_amount), 0) as avg_order_value
        FROM customers c
        LEFT JOIN users u ON c.user_id = u.user_id
        LEFT JOIN orders o ON c.customer_id = o.customer_id
        WHERE c.customer_id = ?
        GROUP BY c.customer_id
    ");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        echo json_encode(['success' => false, 'message' => 'Customer not found']);
        exit;
    }
    
    // Get recent orders
    $stmt = $pdo->prepare("
        SELECT 
            order_id,
            order_number,
            total_amount,
            order_status,
            created_at,
            (SELECT COUNT(*) FROM order_items WHERE order_id = orders.order_id) as total_items
        FROM orders
        WHERE customer_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$customer_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'customer' => $customer,
        'orders' => $orders
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>