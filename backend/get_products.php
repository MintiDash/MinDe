<?php
require_once '../database/connect_database.php';
require_once __DIR__ . '/product-reviews/review_helpers.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    global $pdo;

    $category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
    $product_line_id = isset($_GET['product_line_id']) ? (int)$_GET['product_line_id'] : 0;

    // Initialize response data
    $category = [
        'category_id' => 0,
        'category_name' => 'All Products',
        'category_description' => 'Browse our complete collection of auto parts and accessories'
    ];

    // If category_id is specified, get category info
    if ($category_id > 0) {
        $categoryQuery = "
            SELECT 
                category_id,
                category_name,
                category_description
            FROM categories 
            WHERE category_id = :category_id AND status = 'active'
        ";
        $stmt = $pdo->prepare($categoryQuery);
        $stmt->execute([':category_id' => $category_id]);
        $categoryData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($categoryData) {
            $category = $categoryData;
        }
    }

    // If product_line_id is specified, get that info too
    if ($product_line_id > 0) {
        $lineQuery = "
            SELECT 
                product_line_name,
                product_line_description
            FROM product_lines 
            WHERE product_line_id = :product_line_id AND status = 'active'
        ";
        $stmt = $pdo->prepare($lineQuery);
        $stmt->execute([':product_line_id' => $product_line_id]);
        $line = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($line) {
            $category = array_merge($category, $line);
        }
    }

    // Build products query
    $productsQuery = "
        SELECT 
            p.product_id,
            p.product_name,
            p.product_description,
            p.price,
            p.stock_quantity,
            p.product_image,
            pl.product_line_id,
            pl.product_line_name,
            c.category_id,
            c.category_name
        FROM products p
        LEFT JOIN product_lines pl ON p.product_line_id = pl.product_line_id
        LEFT JOIN categories c ON pl.category_id = c.category_id
        WHERE p.status = 'active'
          AND pl.status = 'active'
          AND c.status = 'active'
    ";

    $params = [];

    // Add category filter if specified
    if ($category_id > 0) {
        $productsQuery .= " AND c.category_id = :category_id";
        $params[':category_id'] = $category_id;
    }

    // Add product line filter if specified
    if ($product_line_id > 0) {
        $productsQuery .= " AND p.product_line_id = :product_line_id";
        $params[':product_line_id'] = $product_line_id;
    }

    $productsQuery .= " ORDER BY c.category_name, pl.product_line_name, p.product_name ASC";

    $stmt = $pdo->prepare($productsQuery);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $ratingSummaryMap = getProductReviewSummaryMap(
        $pdo,
        array_map(static function ($product) {
            return (int)($product['product_id'] ?? 0);
        }, $products)
    );

    foreach ($products as &$product) {
        $productId = (int)($product['product_id'] ?? 0);
        $summary = $ratingSummaryMap[$productId] ?? getDefaultProductReviewSummary($productId);
        $product['average_rating'] = (float)$summary['average_rating'];
        $product['review_count'] = (int)$summary['review_count'];
    }
    unset($product);

    echo json_encode([
        'success' => true,
        'category' => $category,
        'products' => $products,
        'total_products' => count($products)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
