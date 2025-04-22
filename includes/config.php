<?php
// Database credentials
$db_host = "localhost";
$db_user = "root";  // Change this to your MySQL username
$db_pass = "";      // Change this to your MySQL password
$db_name = "crypto_tracker";

// Create database connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set character set to UTF8
$conn->set_charset("utf8");

// Configure application settings
define('SITE_NAME', 'Crypto Tracker');
define('APP_VERSION', '1.0.0');

// Set timezone
date_default_timezone_set('UTC');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// API configuration
define('COINGECKO_API_URL', 'https://api.coingecko.com/api/v3/');
define('API_USER_AGENT', 'Crypto Tracker Application');

// Function to sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

