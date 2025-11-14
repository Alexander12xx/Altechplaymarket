<?php
require_once __DIR__ . '/../config/config.php';

/**
 * Utility Functions
 */

/**
 * Format file size
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= (1 << (10 * $pow));

    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Generate pagination HTML
 */
function generatePagination($currentPage, $totalPages, $baseUrl) {
    if ($totalPages <= 1) {
        return '';
    }

    $html = '<div class="pagination">';

    // Previous button
    if ($currentPage > 1) {
        $html .= '<a href="' . $baseUrl . '?page=' . ($currentPage - 1) . '" class="page-link">&laquo; Previous</a>';
    }

    // Page numbers
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);

    if ($start > 1) {
        $html .= '<a href="' . $baseUrl . '?page=1" class="page-link">1</a>';
        if ($start > 2) {
            $html .= '<span class="page-ellipsis">...</span>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        $class = $i == $currentPage ? 'page-link current' : 'page-link';
        $html .= '<a href="' . $baseUrl . '?page=' . $i . '" class="' . $class . '">' . $i . '</a>';
    }

    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $html .= '<span class="page-ellipsis">...</span>';
        }
        $html .= '<a href="' . $baseUrl . '?page=' . $totalPages . '" class="page-link">' . $totalPages . '</a>';
    }

    // Next button
    if ($currentPage < $totalPages) {
        $html .= '<a href="' . $baseUrl . '?page=' . ($currentPage + 1) . '" class="page-link">Next &raquo;</a>';
    }

    $html .= '</div>';
    return $html;
}

/**
 * Truncate text
 */
function truncateText($text, $length = 50, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

/**
 * Convert time ago
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60) {
        return 'just now';
    }

    $intervals = [
        60 => ['minute', 'minutes'],
        3600 => ['hour', 'hours'],
        86400 => ['day', 'days'],
        604800 => ['week', 'weeks'],
        2592000 => ['month', 'months'],
        31536000 => ['year', 'years']
    ];

    foreach ($intervals as $seconds => $labels) {
        $interval = floor($diff / $seconds);
        if ($interval >= 1) {
            $label = $interval == 1 ? $labels[0] : $labels[1];
            return "$interval $label ago";
        }
    }

    return date('M j, Y', $time);
}

/**
 * Generate CSRF token field
 */
function csrfField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Display success/error message
 */
function displayMessage($type, $message) {
    $alertClass = $type === 'success' ? 'alert-success' : 'alert-error';
    return '<div class="alert ' . $alertClass . '">' . htmlspecialchars($message) . '</div>';
}

/**
 * Display flash messages
 */
function displayFlashMessages() {
    $messages = getFlashMessages();
    $output = '';

    foreach ($messages as $type => $message) {
        $output .= displayMessage($type, $message);
    }

    return $output;
}

/**
 * Get user avatar URL
 */
function getUserAvatar($profilePicture = null) {
    $avatar = $profilePicture ?: DEFAULT_AVATAR;

    if (filter_var($avatar, FILTER_VALIDATE_URL)) {
        return $avatar;
    }

    if (file_exists(AVATAR_PATH . $avatar)) {
        return 'uploads/avatars/' . $avatar;
    }

    return 'assets/images/' . DEFAULT_AVATAR;
}

/**
 * Validate image upload
 */
function validateImageUpload($file, $maxSize = MAX_AVATAR_SIZE) {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['valid' => false, 'error' => 'Invalid file upload'];
    }

    if ($file['size'] > $maxSize) {
        return ['valid' => false, 'error' => 'File size too large'];
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        return ['valid' => false, 'error' => 'Invalid file type'];
    }

    return ['valid' => true];
}

/**
 * Upload image file
 */
function uploadImage($file, $destinationPath, $prefix = '') {
    if (!file_exists($destinationPath)) {
        mkdir($destinationPath, 0755, true);
    }

    $fileInfo = pathinfo($file['name']);
    $extension = strtolower($fileInfo['extension']);
    $filename = $prefix . uniqid() . '.' . $extension;
    $uploadPath = $destinationPath . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception('Failed to upload file');
    }

    return $filename;
}

/**
 * Create thumbnail from image
 */
function createThumbnail($sourcePath, $destinationPath, $maxWidth = 200, $maxHeight = 200) {
    if (!extension_loaded('gd')) {
        return false; // GD extension not available
    }

    $imageInfo = getimagesize($sourcePath);
    if (!$imageInfo) {
        return false;
    }

    list($width, $height, $type) = $imageInfo;

    // Calculate new dimensions
    $ratio = min($maxWidth / $width, $maxHeight / $height);
    $newWidth = round($width * $ratio);
    $newHeight = round($height * $ratio);

    // Create image resource
    switch ($type) {
        case IMAGETYPE_JPEG:
            $source = imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $source = imagecreatefrompng($sourcePath);
            break;
        case IMAGETYPE_GIF:
            $source = imagecreatefromgif($sourcePath);
            break;
        default:
            return false;
    }

    if (!$source) {
        return false;
    }

    // Create thumbnail
    $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
    imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    // Save thumbnail
    $result = false;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $result = imagejpeg($thumbnail, $destinationPath, 85);
            break;
        case IMAGETYPE_PNG:
            $result = imagepng($thumbnail, $destinationPath, 8);
            break;
        case IMAGETYPE_GIF:
            $result = imagegif($thumbnail, $destinationPath);
            break;
    }

    imagedestroy($source);
    imagedestroy($thumbnail);

    return $result;
}

/**
 * Log error to file
 */
function logError($message, $context = []) {
    $logFile = __DIR__ . '/../logs/error.log';
    $logDir = dirname($logFile);

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
    $logEntry = "[$timestamp] $message$contextStr\n";

    error_log($logEntry, 3, $logFile);
}

/**
 * Send JSON response
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

/**
 * Send error JSON response
 */
function jsonError($message, $statusCode = 400) {
    jsonResponse(['error' => $message], $statusCode);
}

/**
 * Send success JSON response
 */
function jsonSuccess($data = null, $message = 'Success') {
    jsonResponse(['success' => true, 'message' => $message, 'data' => $data], 200);
}

/**
 * Get client IP address
 */
function getClientIp() {
    $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];

    foreach ($ipKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $ips = explode(',', $_SERVER[$key]);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Generate random string
 */
function generateRandomString($length = 10) {
    return substr(str_shuffle(str_repeat($x = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length / strlen($x)))), 1, $length);
}

/**
 * Escape HTML output
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Get array value by key with default
 */
function getArrayValue($array, $key, $default = null) {
    return isset($array[$key]) ? $array[$key] : $default;
}

/**
 * Check if date is valid
 */
function isValidDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Format duration in seconds to human readable
 */
function formatDuration($seconds) {
    if ($seconds < 60) {
        return $seconds . 's';
    }

    $minutes = floor($seconds / 60);
    $remainingSeconds = $seconds % 60;

    if ($minutes < 60) {
        return $minutes . 'm ' . $remainingSeconds . 's';
    }

    $hours = floor($minutes / 60);
    $remainingMinutes = $minutes % 60;

    return $hours . 'h ' . $remainingMinutes . 'm';
}

/**
 * Get pagination calculation
 */
function getPaginationData($page, $totalItems, $itemsPerPage = ITEMS_PER_PAGE) {
    $page = max(1, (int)$page);
    $totalPages = ceil($totalItems / $itemsPerPage);
    $offset = ($page - 1) * $itemsPerPage;

    return [
        'page' => $page,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'limit' => $itemsPerPage,
        'has_next' => $page < $totalPages,
        'has_prev' => $page > 1
    ];
}
?>