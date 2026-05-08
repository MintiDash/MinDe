<?php
require 'database/connect_database.php';
$start_date = '2026-05-01';
$end_date = '2026-05-31';
try {
    $summary_query = "
        SELECT 
            COUNT(*) as total_orders,
            COUNT(DISTINCT customer_id) as total_customers,
            SUM(total_amount) as total_revenue,
            AVG(total_amount) as avg_order_value
        FROM orders
        WHERE DATE(created_at) BETWEEN :start_date AND :end_date
          AND order_status IN ('delivered', 'shipped')
    ";
    $summary_stmt = $pdo->prepare($summary_query);
    $summary_stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
    print_r("Summary OK\n");

    $top_products_query = "
        SELECT 
            p.product_name,
            SUM(oi.quantity) as total_quantity,
            SUM(oi.quantity * oi.price) as total_revenue
        FROM order_items oi
        JOIN products p ON oi.product_id = p.product_id
        JOIN orders o ON oi.order_id = o.order_id
        WHERE DATE(o.created_at) BETWEEN :start_date AND :end_date
          AND o.order_status IN ('delivered', 'shipped')
        GROUP BY p.product_id, p.product_name
        ORDER BY total_revenue DESC
        LIMIT 10
    ";
    $top_products_stmt = $pdo->prepare($top_products_query);
    $top_products_stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
    print_r("Top Products OK\n");

    $category_sales_query = "
        SELECT 
            c.category_name,
            COUNT(DISTINCT o.order_id) as order_count,
            SUM(oi.quantity) as total_items,
            SUM(oi.quantity * oi.price) as total_revenue
        FROM order_items oi
        JOIN products p ON oi.product_id = p.product_id
        JOIN product_lines pl ON p.product_line_id = pl.product_line_id
        JOIN categories c ON pl.category_id = c.category_id
        JOIN orders o ON oi.order_id = o.order_id
        WHERE DATE(o.created_at) BETWEEN :start_date AND :end_date
          AND o.order_status IN ('delivered', 'shipped')
        GROUP BY c.category_id, c.category_name
        ORDER BY total_revenue DESC
    ";
    $category_sales_stmt = $pdo->prepare($category_sales_query);
    $category_sales_stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
    print_r("Category Sales OK\n");

    $daily_sales_query = "
        SELECT 
            DATE(created_at) as sale_date,
            COUNT(*) as order_count,
            SUM(total_amount) as daily_total
        FROM orders
        WHERE DATE(created_at) BETWEEN :start_date AND :end_date
          AND order_status IN ('delivered', 'shipped')
        GROUP BY DATE(created_at)
        ORDER BY sale_date ASC
    ";
    $daily_sales_stmt = $pdo->prepare($daily_sales_query);
    $daily_sales_stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
    print_r("Daily Sales OK\n");

} catch (PDOException $e) {
    print_r("PDO ERROR: " . $e->getMessage() . "\n");
}
?>
