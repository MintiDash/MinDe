<?php require database/connect_database.php; print_r($pdo->query(SELECT order_status, COUNT(*) FROM orders GROUP BY order_status)->fetchAll()); ?>
