<?php
/**
 * Get Order Details
 * File: C:\xampp\htdocs\MinC_Project\backend\order-management\get_order.php
 */

header('Content-Type: application/json');

include_once '../auth.php';
include_once '../../database/connect_database.php';
include_once 'order_workflow_helper.php';

// Validate session
$validation = validateSession();
if (!$validation['valid']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check management level access
if (!isManagementLevel()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

try {
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('Order ID is required');
    }

    $order_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($order_id === false) {
        throw new Exception('Invalid order ID');
    }

    // Get order details
    $order_query = "
        SELECT 
            o.*,
            c.first_name,
            c.last_name,
            CONCAT(c.first_name, ' ', c.last_name) as customer_name,
            c.email as customer_email,
            c.phone as customer_phone,
            c.customer_type
        FROM orders o
        INNER JOIN customers c ON o.customer_id = c.customer_id
        WHERE o.order_id = :order_id
    ";
    
    $stmt = $pdo->prepare($order_query);
    $stmt->execute(['order_id' => $order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('Order not found');
    }

    // Get order items
    $items_query = "
        SELECT *
        FROM order_items
        WHERE order_id = :order_id
        ORDER BY order_item_id
    ";
    
    $stmt = $pdo->prepare($items_query);
    $stmt->execute(['order_id' => $order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'order' => $order,
        'items' => $items
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
