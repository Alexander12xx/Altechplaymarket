<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';

/**
 * User Management Class
 * Handles user registration, authentication, and profile management
 */
class User {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Register a new user
     */
    public function register($email, $password, $username = null) {
        // Validate input
        if (empty($email) || empty($password)) {
            throw new Exception("Email and password are required");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }

        if (strlen($password) < 8) {
            throw new Exception("Password must be at least 8 characters long");
        }

        // Check if email already exists
        $existing = $this->db->fetch("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) {
            throw new Exception("Email already registered");
        }

        // Check username if provided
        if ($username) {
            $username = trim($username);
            if (strlen($username) > MAX_USERNAME_LENGTH) {
                throw new Exception("Username too long");
            }

            $existingUsername = $this->db->fetch("SELECT id FROM users WHERE username = ?", [$username]);
            if ($existingUsername) {
                throw new Exception("Username already taken");
            }
        }

        try {
            $this->db->beginTransaction();

            // Generate user ID
            $userId = generateUniqueId('user-');
            $passwordHash = password_hash($password, HASH_ALGO);

            // Insert user
            $sql = "INSERT INTO users (user_id, email, password_hash, username, aura_coins) VALUES (?, ?, ?, ?, ?)";
            $this->db->execute($sql, [$userId, $email, $passwordHash, $username, STARTING_COINS]);

            // Create user profile
            $profileSql = "INSERT INTO user_profiles (user_id, full_name) VALUES (?, ?)";
            $this->db->execute($profileSql, [$userId, $username]);

            // Record initial transaction
            $transactionId = generateUniqueId('tx-');
            $transSql = "INSERT INTO transactions (transaction_id, user_id, type, amount, description) VALUES (?, ?, ?, ?, ?)";
            $this->db->execute($transSql, [$transactionId, $userId, TRANSACTION_BONUS, STARTING_COINS, 'Starting bonus']);

            // Log activity
            $this->logActivity($userId, ACTION_REGISTER, "New user registration");

            $this->db->commit();

            return [
                'user_id' => $userId,
                'email' => $email,
                'username' => $username,
                'aura_coins' => STARTING_COINS
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception("Registration failed: " . $e->getMessage());
        }
    }

    /**
     * User login
     */
    public function login($email, $password, $remember = false) {
        // Check rate limiting
        if ($this->isRateLimited($email)) {
            throw new Exception(ERROR_ACCOUNT_LOCKED);
        }

        $user = $this->db->fetch("SELECT * FROM users WHERE email = ?", [$email]);
        if (!$user) {
            $this->recordLoginAttempt($email, false);
            throw new Exception(ERROR_INVALID_LOGIN);
        }

        if (!password_verify($password, $user['password_hash'])) {
            $this->recordLoginAttempt($email, false);
            throw new Exception(ERROR_INVALID_LOGIN);
        }

        // Check if user is blocked/banned
        if ($user['is_blocked'] || $user['status'] === USER_STATUS_BANNED) {
            throw new Exception("Account is suspended or banned");
        }

        // Clear login attempts on successful login
        $this->clearLoginAttempts($email);

        // Create session
        $this->createSession($user['user_id'], $user['email'], $user['username'], $user['is_admin']);

        // Update last login
        $this->db->execute("UPDATE users SET last_login = NOW() WHERE user_id = ?", [$user['user_id']]);

        // Log activity
        $this->logActivity($user['user_id'], ACTION_LOGIN, "User logged in");

        return [
            'user_id' => $user['user_id'],
            'email' => $user['email'],
            'username' => $user['username'],
            'aura_coins' => $user['aura_coins'],
            'is_admin' => $user['is_admin']
        ];
    }

    /**
     * Create user session
     */
    private function createSession($userId, $email, $username, $isAdmin) {
        $sessionToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);

        // Clear old sessions
        $this->db->execute("DELETE FROM user_sessions WHERE user_id = ? OR expires_at < NOW()", [$userId]);

        // Create new session
        $sql = "INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)";
        $this->db->execute($sql, [
            $userId,
            $sessionToken,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $expiresAt
        ]);

        // Set session variables
        $_SESSION['user_id'] = $userId;
        $_SESSION['email'] = $email;
        $_SESSION['username'] = $username;
        $_SESSION['is_admin'] = (bool)$isAdmin;
        $_SESSION['session_token'] = $sessionToken;
    }

    /**
     * Check if user session is valid
     */
    public function validateSession() {
        if (!isLoggedIn()) {
            return false;
        }

        $session = $this->db->fetch(
            "SELECT * FROM user_sessions WHERE user_id = ? AND session_token = ? AND expires_at > NOW()",
            [getCurrentUserId(), $_SESSION['session_token']]
        );

        return $session !== false;
    }

    /**
     * Logout user
     */
    public function logout() {
        if (isLoggedIn()) {
            // Remove session from database
            $this->db->execute(
                "DELETE FROM user_sessions WHERE user_id = ? AND session_token = ?",
                [getCurrentUserId(), $_SESSION['session_token']]
            );

            // Log activity
            $this->logActivity(getCurrentUserId(), ACTION_LOGOUT, "User logged out");
        }

        // Destroy session
        session_unset();
        session_destroy();
    }

    /**
     * Get user by ID
     */
    public function getUserById($userId) {
        $sql = "SELECT u.*, up.full_name, up.phone, up.country, up.date_of_birth, up.bio, up.profile_picture
                FROM users u
                LEFT JOIN user_profiles up ON u.user_id = up.user_id
                WHERE u.user_id = ?";
        return $this->db->fetch($sql, [$userId]);
    }

    /**
     * Update user profile
     */
    public function updateProfile($userId, $data) {
        $allowedFields = ['full_name', 'phone', 'country', 'date_of_birth', 'bio'];
        $updates = [];
        $params = [];

        foreach ($data as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $updates[] = "$field = ?";
                $params[] = $value;
            }
        }

        if (empty($updates)) {
            return false;
        }

        $params[] = $userId;
        $sql = "UPDATE user_profiles SET " . implode(', ', $updates) . " WHERE user_id = ?";

        $result = $this->db->execute($sql, $params);

        if ($result) {
            $this->logActivity($userId, ACTION_PROFILE_UPDATE, "Profile updated");
        }

        return $result;
    }

    /**
     * Update user coins
     */
    public function updateCoins($userId, $amount, $type, $description, $referenceId = null) {
        try {
            $this->db->beginTransaction();

            // Update user coins
            $this->db->execute("UPDATE users SET aura_coins = aura_coins + ? WHERE user_id = ?", [$amount, $userId]);

            // Record transaction
            $transactionId = generateUniqueId('tx-');
            $sql = "INSERT INTO transactions (transaction_id, user_id, type, amount, description, reference_id) VALUES (?, ?, ?, ?, ?, ?)";
            $this->db->execute($sql, [$transactionId, $userId, $type, $amount, $description, $referenceId]);

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception("Failed to update coins: " . $e->getMessage());
        }
    }

    /**
     * Get user balance
     */
    public function getBalance($userId) {
        $result = $this->db->fetch("SELECT aura_coins FROM users WHERE user_id = ?", [$userId]);
        return $result ? (float)$result['aura_coins'] : 0;
    }

    /**
     * Get user transaction history
     */
    public function getTransactionHistory($userId, $limit = ITEMS_PER_PAGE, $offset = 0) {
        $sql = "SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?";
        return $this->db->fetchAll($sql, [$userId, $limit, $offset]);
    }

    /**
     * Check rate limiting for login attempts
     */
    private function isRateLimited($email) {
        $timeWindow = time() - LOGIN_ATTEMPT_WINDOW;
        $attempts = $this->db->fetch(
            "SELECT COUNT(*) as count FROM login_attempts WHERE identifier = ? AND time > ?",
            [$email, $timeWindow]
        );
        return $attempts['count'] >= MAX_LOGIN_ATTEMPTS;
    }

    /**
     * Record login attempt
     */
    private function recordLoginAttempt($email, $success) {
        $this->db->execute(
            "INSERT INTO login_attempts (identifier, time, success) VALUES (?, ?, ?)",
            [$email, time(), $success ? 1 : 0]
        );
    }

    /**
     * Clear login attempts
     */
    private function clearLoginAttempts($email) {
        $this->db->execute("DELETE FROM login_attempts WHERE identifier = ?", [$email]);
    }

    /**
     * Log user activity
     */
    private function logActivity($userId, $action, $description = null) {
        $sql = "INSERT INTO user_activities (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)";
        $this->db->execute($sql, [
            $userId,
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }

    /**
     * Get all users (for admin)
     */
    public function getAllUsers($limit = ITEMS_PER_PAGE, $offset = 0, $search = null) {
        $sql = "SELECT u.*, up.full_name FROM users u LEFT JOIN user_profiles up ON u.user_id = up.user_id";
        $params = [];

        if ($search) {
            $sql .= " WHERE u.email LIKE ? OR u.username LIKE ? OR up.full_name LIKE ?";
            $searchParam = "%$search%";
            $params = [$searchParam, $searchParam, $searchParam];
        }

        $sql .= " ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Update user status (for admin)
     */
    public function updateUserStatus($userId, $status) {
        $sql = "UPDATE users SET status = ? WHERE user_id = ?";
        return $this->db->execute($sql, [$status, $userId]);
    }

    /**
     * Block/unblock user (for admin)
     */
    public function blockUser($userId, $blocked) {
        $sql = "UPDATE users SET is_blocked = ? WHERE user_id = ?";
        return $this->db->execute($sql, [$blocked, $userId]);
    }
}
?>