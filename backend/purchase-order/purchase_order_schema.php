<?php
/**
 * Purchase order schema bootstrap.
 */

if (!function_exists('ensurePurchaseOrdersTable')) {
    function ensurePurchaseOrdersTable(PDO $pdo) {
        $sql = "
            CREATE TABLE IF NOT EXISTS purchase_orders (
                po_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                po_number VARCHAR(50) NOT NULL,
                supplier_id BIGINT UNSIGNED NOT NULL,
                order_date DATE NOT NULL,
                expected_delivery_date DATE NOT NULL,
                total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                status ENUM('pending', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
                notes TEXT DEFAULT NULL,
                created_by BIGINT UNSIGNED DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (po_id),
                UNIQUE KEY uk_po_number (po_number),
                KEY idx_po_supplier (supplier_id),
                KEY idx_po_order_date (order_date),
                KEY idx_po_status (status),
                CONSTRAINT fk_po_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id) ON UPDATE CASCADE ON DELETE RESTRICT,
                CONSTRAINT fk_po_created_by FOREIGN KEY (created_by) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        $pdo->exec($sql);
    }
}
?>
