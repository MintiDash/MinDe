<?php
session_start();
require_once '../../database/connect_database.php';

header('Content-Type: application/json');

try {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $session_id = session_id();

    // Get cart
    if ($user_id) {
        $stmt = mysqli_prepare($connection, "SELECT cart_id FROM cart WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
    } else {
        $stmt = mysqli_prepare($connection, "SELECT cart_id FROM cart WHERE session_id = ?");
        mysqli_stmt_bind_param($stmt, "s", $session_id);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) === 0) {
        echo json_encode([
            'success' => true,
            'cart_items' => [],
            'cart_count' => 0,
            'subtotal' => 0
        ]);
        exit;
    }

    $cart_id = mysqli_fetch_assoc($result)['cart_id'];

    // Get cart items with product details
    $stmt = mysqli_prepare($connection, "
        SELECT 
            ci.cart_item_id,
            ci.product_id,
            ci.quantity,
            ci.price,
            p.product_name,
            p.product_code,
            p.product_image,
            p.stock_quantity,
            p.stock_status,
            pl.product_line_name,
            (ci.quantity * ci.price) as item_total
        FROM cart_items ci
        JOIN products p ON ci.product_id = p.product_id
        JOIN product_lines pl ON p.product_line_id = pl.product_line_id
        WHERE ci.cart_id = ? AND p.status = 'active'
        ORDER BY ci.created_at DESC
    ");
    mysqli_stmt_bind_param($stmt, "i", $cart_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $cart_items = [];
    $subtotal = 0;
    $cart_count = 0;

    while ($row = mysqli_fetch_assoc($result)) {
        $cart_items[] = $row;
        $subtotal += $row['item_total'];
        $cart_count += $row['quantity'];
    }

    echo json_encode([
        'success' => true,
        'cart_items' => $cart_items,
        'cart_count' => $cart_count,
        'subtotal' => number_format($subtotal, 2, '.', '')
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>