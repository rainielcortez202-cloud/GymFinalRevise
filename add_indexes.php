<?php
require 'connection.php';

try {
    echo "Adding indexes...\n";
    
    // Index for LATERAL JOIN performance
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sales_user_expiry ON sales (user_id, expires_at DESC)");
    
    // Indexes for search performance
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_full_name ON users (full_name)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_email ON users (email)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_role ON users (role)");

    echo "Indexes added successfully!\n";
} catch (Exception $e) {
    echo "Error adding indexes: " . $e->getMessage() . "\n";
}
