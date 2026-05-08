<?php
var_dump(getenv('DB_HOST'));
var_dump(getenv('DB_USERNAME'));
var_dump(getenv('DB_PASSWORD'));
var_dump(getenv('DB_NAME'));

echo "<br><br>";

$host = '127.0.0.1';
$user = 'minc_user';
$pass = 'minc_pass';
$db = 'minc';

echo "Trying to connect to: $host as $user for db $db<br>";

$connection = mysqli_connect($host, $user, $pass, $db);

if (!$connection) {
    echo "Connection failed: " . mysqli_connect_error();
} else {
    echo "Connection successful!";
    mysqli_close($connection);
}
?>