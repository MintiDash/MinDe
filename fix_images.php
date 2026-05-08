<?php
require 'database/connect_database.php';

// Fix Products
$count = 0;
foreach(glob('Assets/images/products/product_*.*') as $file) {
    if (preg_match('/product_(\d+)_/', basename($file), $m)) {
        $pdo->prepare("UPDATE products SET product_image = ? WHERE product_id = ?")
            ->execute([basename($file), $m[1]]);
        $count++;
    }
}
echo "Successfully fixed $count product images in the database!\n";
?>
