<?php
/**
 * Export Orders to CSV
 * File: C:\xampp\htdocs\MinC_Project\backend\order-management\export_orders.php
 */

include_once '../auth.php';
include_once '../../database/connect_database.php';
include_once 'order_workflow_helper.php';

// Validate session
$validation = validateSession();
if (!$validation['valid']) {
    header('Location: ../../index.php?error=' . $validation['reason']);
    exit;
}

// Check management level access
if (!isManagementLevel()) {
    die('Access denied');
}

try {
    // Get orders data
    $query = "
        SELECT 
            o.order_number,
            o.tracking_number,
            CONCAT(c.first_name, ' ', c.last_name) as customer_name,
            c.email as customer_email,
            c.phone as customer_phone,
            c.customer_type,
            o.subtotal,
            o.shipping_fee,
            o.total_amount,
            o.payment_method,
            o.payment_status,
            o.order_status,
            CONCAT(o.shipping_address, ', ', o.shipping_city, ', ', o.shipping_province) as full_address,
            o.delivery_date,
            o.created_at,
            COUNT(oi.order_item_id) as total_items,
            SUM(oi.quantity) as total_quantity
        FROM orders o
        INNER JOIN customers c ON o.customer_id = c.customer_id
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        GROUP BY o.order_id
        ORDER BY o.created_at DESC
    ";
    
    $result = $pdo->query($query);
    $orders = $result->fetchAll(PDO::FETCH_ASSOC);

    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="orders_' . date('Y-m-d_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Add UTF-8 BOM for Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Add CSV headers
    fputcsv($output, [
        'Order Number',
        'Tracking Number',
        'Customer Name',
        'Customer Email',
        'Customer Phone',
        'Customer Type',
        'Subtotal',
        'Shipping Fee',
        'Total Amount',
        'Payment Method',
        'Payment Status',
        'Order Status',
        'Shipping Address',
        'Delivery Date',
        'Total Items',
        'Total Quantity',
        'Order Date'
    ]);

    // Add data rows
    foreach ($orders as $order) {
        fputcsv($output, [
            $order['order_number'],
            $order['tracking_number'] ?? 'N/A',
            $order['customer_name'],
            $order['customer_email'],
            $order['customer_phone'],
            ucfirst($order['customer_type']),
            number_format($order['subtotal'], 2),
            number_format($order['shipping_fee'], 2),
            number_format($order['total_amount'], 2),
            mincDescribePaymentMethod($order['payment_method']),
            ucfirst($order['payment_status']),
            ucfirst($order['order_status']),
            $order['full_address'],
            $order['delivery_date'] ?? 'N/A',
            $order['total_items'],
            $order['total_quantity'],
            date('Y-m-d H:i:s', strtotime($order['created_at']))
        ]);
    }

    fclose($output);
    exit;

} catch (Exception $e) {
    die('Export error: ' . $e->getMessage());
}
