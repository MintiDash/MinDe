<?php
require_once '../database/connect_database.php';
require_once __DIR__ . '/product-reviews/review_helpers.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    global $pdo;

    $product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

    if ($product_id === 0) {
        throw new Exception('Product ID is required');
    }

    // Get product details with related information
    $productQuery = "
        SELECT 
            p.product_id,
            p.product_name,
            p.product_slug,
            p.product_code,
            p.product_description,
            p.product_image,
            p.price,
            p.stock_quantity,
            p.stock_status,
            p.is_featured,
            pl.product_line_id,
            pl.product_line_name,
            pl.product_line_slug,
            c.category_id,
            c.category_name,
            c.category_slug
        FROM products p
        LEFT JOIN product_lines pl ON p.product_line_id = pl.product_line_id
        LEFT JOIN categories c ON pl.category_id = c.category_id
        WHERE p.product_id = :product_id 
          AND p.status = 'active'
    ";

    $stmt = $pdo->prepare($productQuery);
    $stmt->execute([':product_id' => $product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        throw new Exception('Product not found or unavailable');
    }

    // Get related products from same product line
    $relatedQuery = "
        SELECT 
            p.product_id,
            p.product_name,
            p.product_image,
            p.price,
            p.stock_quantity
        FROM products p
        WHERE p.product_line_id = :product_line_id 
          AND p.product_id != :product_id
          AND p.status = 'active'
        ORDER BY RAND()
        LIMIT 4
    ";

    $stmt = $pdo->prepare($relatedQuery);
    $stmt->execute([
        ':product_line_id' => $product['product_line_id'],
        ':product_id' => $product_id
    ]);
    $relatedProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $ratingSummaryMap = getProductReviewSummaryMap(
        $pdo,
        array_merge(
            [$product_id],
            array_map(static function ($relatedProduct) {
                return (int)($relatedProduct['product_id'] ?? 0);
            }, $relatedProducts)
        )
    );

    $productSummary = $ratingSummaryMap[$product_id] ?? getDefaultProductReviewSummary($product_id);
    $product['average_rating'] = (float)$productSummary['average_rating'];
    $product['review_count'] = (int)$productSummary['review_count'];

    foreach ($relatedProducts as &$relatedProduct) {
        $relatedProductId = (int)($relatedProduct['product_id'] ?? 0);
        $summary = $ratingSummaryMap[$relatedProductId] ?? getDefaultProductReviewSummary($relatedProductId);
        $relatedProduct['average_rating'] = (float)$summary['average_rating'];
        $relatedProduct['review_count'] = (int)$summary['review_count'];
    }
    unset($relatedProduct);

    echo json_encode([
        'success' => true,
        'product' => $product,
        'related_products' => $relatedProducts
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
