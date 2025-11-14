<?php
/**
 * Application Configuration
 */

// Start session
session_start();

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('UTC');

// Application constants
define('APP_NAME', 'Digital Forecast Hub');
define('APP_URL', 'https://' . $_SERVER['HTTP_HOST']);
define('APP_VERSION', '1.0.0');

// Security settings
define('HASH_ALGO', PASSWORD_DEFAULT);
define('SESSION_LIFETIME', 86400); // 24 hours
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_ATTEMPT_WINDOW', 900); // 15 minutes

// Coin system settings
define('STARTING_COINS', 10.00);
define('AD_REWARD_COINS', 0.50);
define('DAILY_BONUS_COINS', 1.00);
define('REFERRAL_BONUS_COINS', 5.00);

// API settings
define('API_REQUEST_TIMEOUT', 10);
define('CACHE_DURATION', 300); // 5 minutes

// File upload settings
define('MAX_AVATAR_SIZE', 2 * 1024 * 1024); // 2MB
define('MAX_AD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_VIDEO_TYPES', ['mp4', 'webm', 'ogg']);

// Pagination
define('ITEMS_PER_PAGE', 20);
define('CHAT_MESSAGES_PER_PAGE', 50);

// Rate limiting
define('CHAT_COOLDOWN', 3); // seconds between messages
define('BET_COOLDOWN', 30); // seconds between bets
define('API_RATE_LIMIT', 100); // requests per hour

// Include database configuration
require_once __DIR__ . '/database.php';

// Helper function for debugging
function debug($var, $label = 'DEBUG') {
    if (defined('DEBUG') && DEBUG) {
        echo "<pre><strong>$label:</strong>\n";
        print_r($var);
        echo "</pre>";
    }
}

// Helper function for secure redirects
function secureRedirect($url) {
    // Prevent open redirects
    $url = filter_var($url, FILTER_SANITIZE_URL);
    if (filter_var($url, FILTER_VALIDATE_URL) && strpos($url, APP_URL) === 0) {
        header("Location: $url");
        exit();
    } else {
        secureRedirect('/pages/index.php');
    }
}

// Generate CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate CSRF token
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Clean input data
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

// Get current user ID
function getCurrentUserId() {
    return isLoggedIn() ? $_SESSION['user_id'] : null;
}

// Get current username
function getCurrentUsername() {
    return isLoggedIn() ? ($_SESSION['username'] ?? 'Unknown') : null;
}

// Format coins display
function formatCoins($amount) {
    return number_format($amount, 2) . ' AC';
}

// Format date/time
function formatDateTime($datetime) {
    $date = new DateTime($datetime);
    return $date->format('M j, Y H:i');
}

// Generate unique ID
function generateUniqueId($prefix = '') {
    return $prefix . uniqid() . '-' . bin2hex(random_bytes(8));
}
?>