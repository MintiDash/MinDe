<?php
session_start();
require_once '../../database/connect_database.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$cart_item_id = isset($input['cart_item_id']) ? intval($input['cart_item_id']) : 0;

if ($cart_item_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid cart item']);
    exit;
}

try {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $session_id = session_id();

    // Verify ownership and delete
    if ($user_id) {
        $stmt = mysqli_prepare($connection, "
            DELETE ci FROM cart_items ci
            JOIN cart c ON ci.cart_id = c.cart_id
            WHERE ci.cart_item_id = ? AND c.user_id = ?
        ");
        mysqli_stmt_bind_param($stmt, "ii", $cart_item_id, $user_id);
    } else {
        $stmt = mysqli_prepare($connection, "
            DELETE ci FROM cart_items ci
            JOIN cart c ON ci.cart_id = c.cart_id
            WHERE ci.cart_item_id = ? AND c.session_id = ?
        ");
        mysqli_stmt_bind_param($stmt, "is", $cart_item_id, $session_id);
    }
    
    mysqli_stmt_execute($stmt);

    if (mysqli_stmt_affected_rows($stmt) > 0) {
        echo json_encode(['success' => true, 'message' => 'Item removed from cart']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Item not found or already removed']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>