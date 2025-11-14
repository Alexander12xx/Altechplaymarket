<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/User.php';

/**
 * Authentication Helper Functions
 */

/**
 * Require user to be logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        secureRedirect('/pages/login.php');
        exit();
    }

    // Validate session
    $user = new User();
    if (!$user->validateSession()) {
        $_SESSION['error'] = ERROR_SESSION_EXPIRED;
        session_unset();
        session_destroy();
        secureRedirect('/pages/login.php');
        exit();
    }
}

/**
 * Require admin access
 */
function requireAdmin() {
    requireLogin();

    if (!isAdmin()) {
        $_SESSION['error'] = ERROR_PERMISSION_DENIED;
        secureRedirect('/pages/index.php');
        exit();
    }
}

/**
 * Get current user data
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }

    $user = new User();
    return $user->getUserById(getCurrentUserId());
}

/**
 * Check if current user can perform action
 */
function canPerformAction($requiredAction = null) {
    if (!isLoggedIn()) {
        return false;
    }

    $user = getCurrentUser();
    if (!$user) {
        return false;
    }

    // Admins can do everything
    if (isAdmin()) {
        return true;
    }

    // Additional permission checks can be added here
    switch ($requiredAction) {
        case 'bet':
            return $user['status'] === USER_STATUS_ACTIVE && !$user['is_blocked'];
        case 'chat':
            return $user['status'] === USER_STATUS_ACTIVE && !$user['is_blocked'];
        case 'watch_ads':
            return $user['status'] === USER_STATUS_ACTIVE && !$user['is_blocked'];
        default:
            return $user['status'] === USER_STATUS_ACTIVE;
    }
}

/**
 * Initialize CSRF token for forms
 */
function initCSRF() {
    return generateCSRFToken();
}

/**
 * Validate and sanitize form input
 */
function validateFormInput($data, $rules) {
    $errors = [];
    $sanitized = [];

    foreach ($rules as $field => $rule) {
        $value = $data[$field] ?? null;

        // Required field check
        if (isset($rule['required']) && $rule['required'] && empty($value)) {
            $errors[$field] = $rule['label'] ?? $field . " is required";
            continue;
        }

        // If field is empty and not required, skip validation
        if (empty($value) && (!isset($rule['required']) || !$rule['required'])) {
            $sanitized[$field] = null;
            continue;
        }

        // Type-specific validation
        switch ($rule['type'] ?? 'text') {
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field] = "Invalid email format";
                } else {
                    $sanitized[$field] = filter_var($value, FILTER_SANITIZE_EMAIL);
                }
                break;

            case 'password':
                if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
                    $errors[$field] = "Password must be at least {$rule['min_length']} characters long";
                } elseif (isset($rule['confirm']) && $value !== $data[$rule['confirm']]) {
                    $errors[$field] = "Passwords do not match";
                } else {
                    $sanitized[$field] = $value;
                }
                break;

            case 'number':
                if (!is_numeric($value)) {
                    $errors[$field] = "Must be a valid number";
                } else {
                    $sanitized[$field] = (float) $value;

                    if (isset($rule['min']) && $sanitized[$field] < $rule['min']) {
                        $errors[$field] = "Minimum value is {$rule['min']}";
                    }
                    if (isset($rule['max']) && $sanitized[$field] > $rule['max']) {
                        $errors[$field] = "Maximum value is {$rule['max']}";
                    }
                }
                break;

            case 'text':
            default:
                if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
                    $errors[$field] = "Maximum length is {$rule['max_length']} characters";
                } else {
                    $sanitized[$field] = cleanInput($value);
                }
                break;
        }

        // Custom validation
        if (isset($rule['custom']) && is_callable($rule['custom'])) {
            $customResult = $rule['custom']($value, $sanitized[$field] ?? $value);
            if ($customResult !== true) {
                $errors[$field] = $customResult;
            }
        }
    }

    return ['errors' => $errors, 'data' => $sanitized];
}

/**
 * Set flash message
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash'][$type] = $message;
}

/**
 * Get and clear flash messages
 */
function getFlashMessages() {
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/**
 * Handle form submission
 */
function handleFormSubmission($formConfig) {
    $method = $formConfig['method'] ?? 'POST';

    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        return false;
    }

    // CSRF validation
    if ($method === 'POST' && isset($formConfig['csrf'])) {
        $token = $_POST['csrf_token'] ?? '';
        if (!validateCSRFToken($token)) {
            setFlashMessage('error', 'Invalid form submission');
            return false;
        }
    }

    // Rate limiting check
    if (isset($formConfig['rate_limit'])) {
        $rateLimit = $formConfig['rate_limit'];
        $key = 'rate_limit_' . md5($_SERVER['REMOTE_ADDR'] . ($formConfig['action'] ?? ''));

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [];
        }

        $now = time();
        $_SESSION[$key] = array_filter($_SESSION[$key], function($time) use ($now, $rateLimit) {
            return $now - $time < $rateLimit['window'];
        });

        if (count($_SESSION[$key]) >= $rateLimit['max']) {
            setFlashMessage('error', ERROR_RATE_LIMIT_EXCEEDED);
            return false;
        }

        $_SESSION[$key][] = $now;
    }

    return true;
}

/**
 * Redirect after login
 */
function handleLoginRedirect() {
    $redirect = $_SESSION['redirect_after_login'] ?? '/pages/index.php';
    unset($_SESSION['redirect_after_login']);
    secureRedirect($redirect);
}

/**
 * Check if user has sufficient coins
 */
function hasSufficientCoins($required) {
    if (!isLoggedIn()) {
        return false;
    }

    $user = new User();
    $balance = $user->getBalance(getCurrentUserId());
    return $balance >= $required;
}

/**
 * Log user activity
 */
function logActivity($action, $description = null) {
    if (!isLoggedIn()) {
        return;
    }

    $user = new User();
    // This would need to be implemented in the User class
    // $user->logActivity(getCurrentUserId(), $action, $description);
}

/**
 * Generate remember me token
 */
function generateRememberToken($userId) {
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $expiry = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 days

    $db = Database::getInstance();
    $db->execute(
        "DELETE FROM user_sessions WHERE user_id = ? AND session_token LIKE 'remember_%'",
        [$userId]
    );

    $db->execute(
        "INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)",
        [$userId, 'remember_' . $hash, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null, $expiry]
    );

    // Set cookie
    setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);

    return $token;
}

/**
 * Validate remember me token
 */
function validateRememberToken($token) {
    if (empty($token)) {
        return false;
    }

    $hash = hash('sha256', $token);
    $db = Database::getInstance();

    $session = $db->fetch(
        "SELECT * FROM user_sessions WHERE session_token = ? AND expires_at > NOW()",
        ['remember_' . $hash]
    );

    if (!$session) {
        return false;
    }

    // Get user and create session
    $user = new User();
    $userInfo = $user->getUserById($session['user_id']);

    if ($userInfo) {
        $_SESSION['user_id'] = $userInfo['user_id'];
        $_SESSION['email'] = $userInfo['email'];
        $_SESSION['username'] = $userInfo['username'];
        $_SESSION['is_admin'] = (bool)$userInfo['is_admin'];

        return true;
    }

    return false;
}

/**
 * Clear remember me token
 */
function clearRememberToken() {
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
        unset($_COOKIE['remember_token']);
    }
}

/**
 * Initialize authentication state
 */
function initAuth() {
    // Check remember me token if no session
    if (!isLoggedIn() && isset($_COOKIE['remember_token'])) {
        validateRememberToken($_COOKIE['remember_token']);
    }

    // Validate current session if logged in
    if (isLoggedIn()) {
        $user = new User();
        if (!$user->validateSession()) {
            session_unset();
            session_destroy();
            clearRememberToken();
            setFlashMessage('error', ERROR_SESSION_EXPIRED);
        }
    }
}

// Initialize authentication state on every page load
initAuth();
?>