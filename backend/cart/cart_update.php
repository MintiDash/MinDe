<?php
session_start();
require_once '../../database/connect_database.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$cart_item_id = isset($input['cart_item_id']) ? intval($input['cart_item_id']) : 0;
$quantity = isset($input['quantity']) ? intval($input['quantity']) : 0;

if ($cart_item_id <= 0 || $quantity < 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    // Verify cart ownership
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $session_id = session_id();

    if ($user_id) {
        $stmt = mysqli_prepare($connection, "
            SELECT ci.cart_item_id, p.stock_quantity 
            FROM cart_items ci
            JOIN cart c ON ci.cart_id = c.cart_id
            JOIN products p ON ci.product_id = p.product_id
            WHERE ci.cart_item_id = ? AND c.user_id = ?
        ");
        mysqli_stmt_bind_param($stmt, "ii", $cart_item_id, $user_id);
    } else {
        $stmt = mysqli_prepare($connection, "
            SELECT ci.cart_item_id, p.stock_quantity 
            FROM cart_items ci
            JOIN cart c ON ci.cart_id = c.cart_id
            JOIN products p ON ci.product_id = p.product_id
            WHERE ci.cart_item_id = ? AND c.session_id = ?
        ");
        mysqli_stmt_bind_param($stmt, "is", $cart_item_id, $session_id);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) === 0) {
        echo json_encode(['success' => false, 'message' => 'Cart item not found']);
        exit;
    }

    $item = mysqli_fetch_assoc($result);

    if ($quantity > $item['stock_quantity']) {
        echo json_encode(['success' => false, 'message' => 'Quantity exceeds available stock']);
        exit;
    }

    if ($quantity === 0) {
        // Remove item
        $stmt = mysqli_prepare($connection, "DELETE FROM cart_items WHERE cart_item_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $cart_item_id);
        mysqli_stmt_execute($stmt);
        $message = 'Item removed from cart';
    } else {
        // Update quantity
        $stmt = mysqli_prepare($connection, "UPDATE cart_items SET quantity = ?, updated_at = NOW() WHERE cart_item_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $quantity, $cart_item_id);
        mysqli_stmt_execute($stmt);
        $message = 'Cart updated successfully';
    }

    echo json_encode(['success' => true, 'message' => $message]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>