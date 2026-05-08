<?php
include_once '../auth.php';
include_once '../../database/connect_database.php';

// Validate session
$validation = validateSession();
if (!$validation['valid']) {
    header('Location: ../../index.php?error=' . $validation['reason']);
    exit;
}

// Check permission
if (!isManagementLevel()) {
    die('Access denied');
}

try {
    $stmt = $pdo->query("
        SELECT 
            c.customer_id,
            c.first_name,
            c.last_name,
            c.email,
            c.phone,
            c.address,
            c.city,
            c.province,
            c.postal_code,
            c.customer_type,
            c.created_at,
            COUNT(DISTINCT o.order_id) as total_orders,
            COALESCE(SUM(o.total_amount), 0) as total_spent
        FROM customers c
        LEFT JOIN orders o ON c.customer_id = o.customer_id
        GROUP BY c.customer_id
        ORDER BY c.created_at DESC
    ");
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="customers_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add headers
    fputcsv($output, ['Customer ID', 'First Name', 'Last Name', 'Email', 'Phone', 'City', 'Province', 'Type', 'Total Orders', 'Total Spent', 'Registered Date']);
    
    // Add data
    foreach ($customers as $customer) {
        fputcsv($output, [
            $customer['customer_id'],
            $customer['first_name'],
            $customer['last_name'],
            $customer['email'],
            $customer['phone'],
            $customer['city'],
            $customer['province'],
            $customer['customer_type'],
            $customer['total_orders'],
            number_format($customer['total_spent'], 2),
            date('Y-m-d', strtotime($customer['created_at']))
        ]);
    }
    
    fclose($output);
    exit;
    
} catch (Exception $e) {
    die('Error exporting customers: ' . $e->getMessage());
}
?>