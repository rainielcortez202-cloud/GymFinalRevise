<?php
// Test DB connection with newly created .env
try {
    require_once 'includes/env.php';
    
    $host = getenv('SUPABASE_DB_HOST') ?: ($_SERVER['SUPABASE_DB_HOST'] ?? '');
    $port = getenv('SUPABASE_DB_PORT') ?: ($_SERVER['SUPABASE_DB_PORT'] ?? '6543');
    $db   = getenv('SUPABASE_DB_NAME') ?: ($_SERVER['SUPABASE_DB_NAME'] ?? 'postgres');
    $user = getenv('SUPABASE_DB_USER') ?: ($_SERVER['SUPABASE_DB_USER'] ?? '');
    $pass = getenv('SUPABASE_DB_PASSWORD') ?: ($_SERVER['SUPABASE_DB_PASSWORD'] ?? '');
    
    echo "Testing connection to:\n";
    echo "Host: $host\n";
    echo "Port: $port\n";
    echo "User: $user\n";
    echo "DB: $db\n";
    echo "Pass: " . ($pass ? "SET" : "MISSING") . "\n";
    
    if (!$host || !$user || !$pass) {
        die("Error: Missing credentials even after loading .env\n");
    }

    $dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    echo "SUCCESS: Connected to database!\n";
    
} catch (PDOException $e) {
    echo "FAILURE: Connection failed: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "FAILURE: General error: " . $e->getMessage() . "\n";
}
