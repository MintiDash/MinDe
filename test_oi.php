<?php
require 'database/connect_database.php';
$stmt = $pdo->query("SHOW COLUMNS FROM order_items");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
