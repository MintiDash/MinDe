<?php
// Correct path to your actual connection file
require_once '../database/connect_database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Optional: adjust as needed for CORS

try {
    // Use the $pdo that was created in connect_database.php
    global $pdo;

    // Fetch active categories
    $query = "
        SELECT 
            category_id,
            category_name,
            category_slug,
            category_description,
            category_image,
            display_order
        FROM categories 
        WHERE status = 'active' 
        ORDER BY display_order ASC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each category, fetch up to 3 active product lines
    foreach ($categories as &$category) {
        $query = "
            SELECT 
                product_line_id,
                product_line_name,
                product_line_slug,
                product_line_description,
                product_line_image,
                display_order
            FROM product_lines 
            WHERE category_id = :category_id 
              AND status = 'active' 
            ORDER BY display_order ASC 
            LIMIT 3
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([':category_id' => $category['category_id']]);
        $productLines = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // For each product line, get the count of active products
        foreach ($productLines as &$productLine) {
            $countQuery = "
                SELECT COUNT(*) 
                FROM products 
                WHERE product_line_id = :product_line_id 
                  AND status = 'active'
            ";
            $countStmt = $pdo->prepare($countQuery);
            $countStmt->execute([':product_line_id' => $productLine['product_line_id']]);
            $productLine['product_count'] = (int)$countStmt->fetchColumn();
        }

        $category['product_lines'] = $productLines;
    }
    unset($category); // Break reference
    unset($productLine);

    // Success response
    echo json_encode([
        'success' => true,
        'categories' => $categories
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>