<?php
// config.example.php
// ─────────────────────────────────────────────────────────────────────────────
// INSTRUCTIONS:
//   1. Copy this file and rename it to config.php
//   2. Fill in your actual credentials below
//   3. NEVER commit config.php to version control (it is in .gitignore)
// ─────────────────────────────────────────────────────────────────────────────
session_start();

// Define Base URL for absolute paths
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . str_replace(basename($_SERVER['SCRIPT_NAME']), "", $_SERVER['SCRIPT_NAME']);
// Normalize base_url to root of the project if we are in admin/manager
if (strpos($base_url, '/admin/') !== false)
    $base_url = str_replace('/admin/', '/', $base_url);
if (strpos($base_url, '/manager/') !== false)
    $base_url = str_replace('/manager/', '/', $base_url);
define('BASE_URL', rtrim($base_url, '/') . '/');

// ── Database ──────────────────────────────────────────────────────────────────
$host = 'localhost';
$user = 'root';           // Your MySQL username
$pass = '';               // Your MySQL password
$db   = 'quicktable_db'; // Your database name

// Create connection using mysqli
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");

// ── Role Constants ────────────────────────────────────────────────────────────
define('ROLE_USER',    0);
define('ROLE_MANAGER', 1);
define('ROLE_ADMIN',   2);

// ── Google OAuth ──────────────────────────────────────────────────────────────
// Get these from https://console.cloud.google.com/apis/credentials
define('GOOGLE_CLIENT_ID',     'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');
define('GOOGLE_REDIRECT_URI',  str_replace(' ', '%20', BASE_URL . 'google-callback.php'));

// ── SMTP / Email (Gmail recommended) ─────────────────────────────────────────
// Use a Gmail App Password (NOT your regular Gmail password)
// How to get one: https://myaccount.google.com/apppasswords
define('SMTP_HOST',      'smtp.gmail.com');
define('SMTP_USER',      'your_email@gmail.com');   // Your Gmail address
define('SMTP_PASS',      'YOUR_GMAIL_APP_PASSWORD'); // 16-char App Password
define('SMTP_PORT',      587);
define('SMTP_FROM',      'your_email@gmail.com');
define('SMTP_FROM_NAME', 'QuickTable');

// ── Load Common Functions ─────────────────────────────────────────────────────
require_once __DIR__ . '/functions.php';
?>
