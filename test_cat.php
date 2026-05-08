<?php require database/connect_database.php; print_r($pdo->query(SHOW TABLES)->fetchAll(PDO::FETCH_ASSOC)); ?>
