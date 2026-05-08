<?php
require 'database/connect_database.php';
$stmt = $pdo->query("SELECT order_number, total_amount, order_status, created_at, completed_at FROM orders");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
