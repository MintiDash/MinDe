<?php
/**
 * Return logged-in user's orders.
 */

header('Content-Type: application/json');

require_once '../database/connect_database.php';
require_once 'auth.php';
require_once 'order-management/order_workflow_helper.php';

$validation = validateSession(false);
if (!$validation['valid']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $email = trim((string)($_SESSION['email'] ?? ''));

    if ($userId <= 0 && $email === '') {
        throw new Exception('Session data incomplete');
    }

    $query = "
        SELECT
            o.order_id,
            o.order_number,
            o.order_status,
            o.payment_status,
            o.payment_method,
            " . mincOptionalColumnSelect($pdo, 'orders', 'o', 'delivery_method') . ",
            " . mincOptionalColumnSelect($pdo, 'orders', 'o', 'payment_reference') . ",
            " . mincOptionalColumnSelect($pdo, 'orders', 'o', 'payment_proof_path') . ",
            " . mincOptionalColumnSelect($pdo, 'orders', 'o', 'payment_review_notes') . ",
            " . mincOptionalColumnSelect($pdo, 'orders', 'o', 'receipt_path') . ",
            " . mincOptionalColumnSelect($pdo, 'orders', 'o', 'cancel_reason') . ",
            " . mincOptionalColumnSelect($pdo, 'orders', 'o', 'pickup_date') . ",
            " . mincOptionalColumnSelect($pdo, 'orders', 'o', 'pickup_time') . ",
            o.total_amount,
            o.shipping_fee,
            o.subtotal,
            o.created_at,
            o.updated_at,
            o.tracking_number,
            o.notes,
            (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.order_id) AS total_items,
            (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = o.order_id) AS total_quantity
        FROM orders o
        INNER JOIN customers c ON c.customer_id = o.customer_id
        WHERE (c.user_id = :user_id OR c.email = :email)
        ORDER BY o.created_at DESC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':user_id' => $userId,
        ':email' => $email
    ]);

    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'orders' => $orders
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
