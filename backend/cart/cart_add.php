<?php
session_start();
require_once '../../database/connect_database.php';

header('Content-Type: application/json');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$product_id = isset($input['product_id']) ? intval($input['product_id']) : 0;
$quantity = isset($input['quantity']) ? intval($input['quantity']) : 1;

if ($product_id <= 0 || $quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product or quantity']);
    exit;
}

try {
    // Get or create cart
    $cart_id = null;
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $session_id = session_id();

    if ($user_id) {
        // Logged in user
        $stmt = mysqli_prepare($connection, "SELECT cart_id FROM cart WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $cart_id = mysqli_fetch_assoc($result)['cart_id'];
        } else {
            $stmt = mysqli_prepare($connection, "INSERT INTO cart (user_id) VALUES (?)");
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $cart_id = mysqli_insert_id($connection);
        }
    } else {
        // Guest user
        $stmt = mysqli_prepare($connection, "SELECT cart_id FROM cart WHERE session_id = ?");
        mysqli_stmt_bind_param($stmt, "s", $session_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $cart_id = mysqli_fetch_assoc($result)['cart_id'];
        } else {
            $stmt = mysqli_prepare($connection, "INSERT INTO cart (session_id) VALUES (?)");
            mysqli_stmt_bind_param($stmt, "s", $session_id);
            mysqli_stmt_execute($stmt);
            $cart_id = mysqli_insert_id($connection);
        }
    }

    // Get product details
    $stmt = mysqli_prepare($connection, "SELECT product_name, price, stock_quantity FROM products WHERE product_id = ? AND status = 'active'");
    mysqli_stmt_bind_param($stmt, "i", $product_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $product = mysqli_fetch_assoc($result);

    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found or unavailable']);
        exit;
    }

    // Check stock
    if ($product['stock_quantity'] < $quantity) {
        echo json_encode(['success' => false, 'message' => 'Insufficient stock']);
        exit;
    }

    // Check if product already in cart
    $stmt = mysqli_prepare($connection, "SELECT cart_item_id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $cart_id, $product_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        // Update quantity
        $item = mysqli_fetch_assoc($result);
        $new_quantity = $item['quantity'] + $quantity;
        
        if ($new_quantity > $product['stock_quantity']) {
            echo json_encode(['success' => false, 'message' => 'Cannot add more than available stock']);
            exit;
        }
        
        $stmt = mysqli_prepare($connection, "UPDATE cart_items SET quantity = ?, updated_at = NOW() WHERE cart_item_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $new_quantity, $item['cart_item_id']);
        mysqli_stmt_execute($stmt);
    } else {
        // Add new item
        $stmt = mysqli_prepare($connection, "INSERT INTO cart_items (cart_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iiid", $cart_id, $product_id, $quantity, $product['price']);
        mysqli_stmt_execute($stmt);
    }

    // Get cart count
    $stmt = mysqli_prepare($connection, "SELECT SUM(quantity) as total FROM cart_items WHERE cart_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $cart_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $cart_count = mysqli_fetch_assoc($result)['total'] ?? 0;

    echo json_encode([
        'success' => true,
        'message' => 'Product added to cart',
        'cart_count' => $cart_count
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>