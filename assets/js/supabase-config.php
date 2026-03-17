<?php
header('Content-Type: application/javascript');
// Disable error reporting for this script to avoid breaking JS syntax
error_reporting(0);
ini_set('display_errors', 0);

// Use the lightweight config file instead of the full connection.php
// This prevents database connection errors from breaking the JS
require_once __DIR__ . '/../../includes/supabase_config.php';

// JavaScript output starts here
?>
// Supabase Configuration (Dynamically injected via PHP)
const SUPABASE_URL = "<?php echo isset($supabase_url) ? $supabase_url : ''; ?>";
const SUPABASE_ANON_KEY = "<?php echo isset($supabase_anon_key) ? $supabase_anon_key : ''; ?>";

console.log("Supabase URL loaded:", SUPABASE_URL ? "Yes" : "No");
console.log("Supabase Key loaded:", SUPABASE_ANON_KEY ? "Yes" : "No");

// Initialize Supabase Client
if (typeof supabase !== 'undefined' && SUPABASE_URL && SUPABASE_ANON_KEY) {
    try {
        window.supabaseClient = supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY);
        console.log("Supabase Client initialized successfully:", !!window.supabaseClient);
    } catch (e) {
        console.error("Supabase initialization failed:", e.message);
    }
} else {
    if (typeof supabase === 'undefined') console.error("Supabase SDK (supabase-js) NOT loaded on this page.");
    else console.error("Supabase configuration missing: URL=" + (SUPABASE_URL ? "OK" : "MISSING") + ", Key=" + (SUPABASE_ANON_KEY ? "OK" : "MISSING"));
}

// Session helper
async function checkSupabaseSession() {
    if (!window.supabaseClient || !window.supabaseClient.auth) return null;
    try {
        const { data, error } = await window.supabaseClient.auth.getSession();
        return data.session;
    } catch (e) {
        return null;
    }
}