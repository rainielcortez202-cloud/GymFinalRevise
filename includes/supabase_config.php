<?php
// Supabase Configuration
// This file is safe to include in JS bridges because it doesn't connect to the database.

require_once __DIR__ . '/env.php';

$supabase_url = getenv('SUPABASE_URL') ?: ($_SERVER['SUPABASE_URL'] ?? '');
$supabase_anon_key = getenv('SUPABASE_ANON_KEY') ?: ($_SERVER['SUPABASE_ANON_KEY'] ?? '');
?>
