<?php
require 'c:\xampp\htdocs\Gym1\connection.php';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ip_login_attempts (
        ip_address VARCHAR(45) PRIMARY KEY,
        login_attempts INT DEFAULT 0,
        lockout_until TIMESTAMP NULL
    )");
    echo "Success";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
