<?php
/**
 * Global Security Layer for Arts Gym
 * Handles Secure Headers, Session Hardening, and CSRF Protection
 */

// 1. Secure HTTP Headers
header("X-Frame-Options: SAMEORIGIN"); // Prevents clickjacking
header("X-Content-Type-Options: nosniff"); // Prevents MIME sniffing
header("X-XSS-Protection: 1; mode=block"); // Enables browser XSS filter
header("Referrer-Policy: strict-origin-when-cross-origin");
// Content Security Policy (CSP) - Allows Supabase, Google Fonts, and CDNs
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com https://olczvynzhpwnaotzjaig.supabase.co; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; img-src 'self' data: https://images.unsplash.com https://olczvynzhpwnaotzjaig.supabase.co; connect-src 'self' https://olczvynzhpwnaotzjaig.supabase.co https://api.brevo.com https://cdn.jsdelivr.net;");

// 2. Session Hardening
if (session_status() === PHP_SESSION_NONE) {
    // Set secure cookie parameters before starting session
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']), // Only send over HTTPS if available
        'httponly' => true, // Prevents JS access to session cookie
        'samesite' => 'Lax' // Helps mitigate CSRF
    ]);
    session_start();
}

// 3. CSRF Protection Logic
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Generates a hidden CSRF input field for forms
 */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
}

/**
 * Script for adding CSRF to jQuery AJAX requests
 */
function csrf_script() {
    return '<script>$.ajaxSetup({ headers: { "X-CSRF-TOKEN": "' . $_SESSION['csrf_token'] . '" } });</script>';
}

/**
 * Validates CSRF token for POST requests
 */
function validate_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'], $token)) {
            header('Content-Type: application/json');
            http_response_code(403);
            die(json_encode(['status' => 'error', 'message' => 'CSRF token validation failed. Request denied.']));
        }
    }
}

/**
 * XSS Protection helper
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// 4. Global Input Truncation Layer (Max 255 chars)
if (!isset($_SESSION['limits_applied'])) {
    $sanitize_limits = function(&$array) use (&$sanitize_limits) {
        foreach ($array as $key => &$value) {
            if (is_string($value)) {
                if (strlen($value) > 255) {
                    $value = substr($value, 0, 255);
                }
            } else if (is_array($value)) {
                $sanitize_limits($value);
            }
        }
    };
    $sanitize_limits($_POST);
    $sanitize_limits($_GET);
    $sanitize_limits($_REQUEST);
}

// 5. Global Upload Size Limit Layer (Max 5MB)
if (!empty($_FILES)) {
    $max_file_size = 5 * 1024 * 1024; // 5 MB
    $check_files = function($files) use (&$check_files, $max_file_size) {
        foreach ($files as $file) {
            if (is_array($file) && isset($file['size'])) {
                if (is_array($file['size'])) {
                    foreach ($file['size'] as $sz) {
                        if ($sz > $max_file_size) die("Error: File exceeds the 5MB upload limit.");
                    }
                } else {
                    if ($file['size'] > $max_file_size) die("Error: File exceeds the 5MB upload limit.");
                }
            } else if (is_array($file)) {
                $check_files($file);
            }
        }
    };
    $check_files($_FILES);
}

?>