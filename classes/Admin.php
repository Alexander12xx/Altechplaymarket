<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';

/**
 * Admin Management Class
 * Handles admin dashboard, user management, and system administration
 */
class Admin {
    private $db;
    private $user;
    private $match;
    private $chat;
    private $ad;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->user = new User();
        $this->match = new Match();
        $this->chat = new Chat();
        $this->ad = new Ad();
    }

    /**
     * Get dashboard statistics
     */
    public function getDashboardStats() {
        $stats = [];

        // User statistics
        $userStats = $this->db->fetch("SELECT
            COUNT(*) as total_users,
            COUNT(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as new_users_week,
            COUNT(CASE WHEN last_login > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as active_users_today,
            SUM(aura_coins) as total_coins_in_circulation
            FROM users WHERE status = ?", [USER_STATUS_ACTIVE]);
        $stats['users'] = $userStats;

        // Betting statistics
        $bettingStats = $this->match->getBettingStats();
        $stats['betting'] = $bettingStats;

        // Match statistics
        $matchStats = $this->db->fetch("SELECT
            COUNT(*) as total_matches,
            COUNT(CASE WHEN status = ? THEN 1 END) as upcoming_matches,
            COUNT(CASE WHEN status = ? THEN 1 END) as live_matches,
            COUNT(CASE WHEN status = ? THEN 1 END) as settled_matches
            FROM matches", [MATCH_STATUS_UPCOMING, MATCH_STATUS_LIVE, MATCH_STATUS_SETTLED]);
        $stats['matches'] = $matchStats;

        // Chat statistics
        $chatStats = $this->chat->getChatStats();
        $stats['chat'] = $chatStats;

        // Advertisement statistics
        $adStats = $this->ad->getAdStats();
        $stats['ads'] = $adStats;

        // Transaction statistics (last 24 hours)
        $transactionStats = $this->db->fetch("SELECT
            COUNT(*) as transactions_24h,
            SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as ad_rewards_24h,
            SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as bet_wins_24h,
            SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as bets_placed_24h
            FROM transactions
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            [TRANSACTION_AD_WATCH, TRANSACTION_BET_WIN, TRANSACTION_BET_PLACED]);
        $stats['transactions'] = $transactionStats;

        // Recent activities
        $stats['recent_activities'] = $this->getRecentActivities(10);

        // System alerts
        $stats['alerts'] = $this->getSystemAlerts();

        return $stats;
    }

    /**
     * Get recent system activities
     */
    public function getRecentActivities($limit = 20) {
        $sql = "SELECT ua.*, u.username
                FROM user_activities ua
                JOIN users u ON ua.user_id = u.user_id
                ORDER BY ua.created_at DESC
                LIMIT ?";
        return $this->db->fetchAll($sql, [$limit]);
    }

    /**
     * Get system alerts
     */
    private function getSystemAlerts() {
        $alerts = [];

        // Check for reported content
        $reports = $this->db->fetch("SELECT COUNT(*) as count FROM reported_content WHERE status = ?", [REPORT_STATUS_PENDING]);
        if ($reports['count'] > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Pending Reports',
                'message' => $reports['count'] . ' reported items awaiting review',
                'action_url' => 'admin/reports.php'
            ];
        }

        // Check for low user activity (if very few active users today)
        $activeUsers = $this->db->fetch("SELECT COUNT(*) as count FROM users WHERE last_login > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        if ($activeUsers['count'] < 5) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Low Activity',
                'message' => 'Only ' . $activeUsers['count'] . ' users active in last 24 hours',
                'action_url' => null
            ];
        }

        return $alerts;
    }

    /**
     * Search users
     */
    public function searchUsers($query, $limit = ITEMS_PER_PAGE, $offset = 0) {
        $sql = "SELECT u.*, up.full_name, up.country
                FROM users u
                LEFT JOIN user_profiles up ON u.user_id = up.user_id
                WHERE u.email LIKE ? OR u.username LIKE ? OR up.full_name LIKE ?
                ORDER BY u.created_at DESC
                LIMIT ? OFFSET ?";

        $searchParam = "%$query%";
        return $this->db->fetchAll($sql, [$searchParam, $searchParam, $searchParam, $limit, $offset]);
    }

    /**
     * Update user status
     */
    public function updateUserStatus($userId, $status, $adminId, $reason = null) {
        try {
            $this->db->beginTransaction();

            // Update user status
            $this->user->updateUserStatus($userId, $status);

            // Log admin action
            $this->logAdminAction($adminId, ADMIN_ACTION_USER_UPDATE, 'user', $userId, "Updated status to $status" . ($reason ? ": $reason" : ""));

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception("Failed to update user status: " . $e->getMessage());
        }
    }

    /**
     * Update user coins (admin adjustment)
     */
    public function updateUserCoins($userId, $amount, $adminId, $reason = null) {
        try {
            $this->db->beginTransaction();

            // Update user coins
            $this->user->updateCoins($userId, $amount, TRANSACTION_BONUS, $reason ?? "Admin coin adjustment", null);

            // Log admin action
            $this->logAdminAction($adminId, ADMIN_ACTION_USER_UPDATE, 'user', $userId, "Adjusted coins by $amount: $reason");

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception("Failed to update user coins: " . $e->getMessage());
        }
    }

    /**
     * Create custom match
     */
    public function createCustomMatch($data, $adminId) {
        $data['created_by'] = $adminId;
        $matchId = $this->match->createMatch($data);

        // Log admin action
        $this->logAdminAction($adminId, ADMIN_ACTION_MATCH_CREATE, 'match', $matchId, "Created match: {$data['title']}");

        return $matchId;
    }

    /**
     * Update match
     */
    public function updateMatch($matchId, $data, $adminId) {
        $allowedFields = ['title', 'match_time', 'status', 'team_a', 'team_b', 'odds_a', 'odds_b', 'max_bet'];
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

        $params[] = $matchId;
        $sql = "UPDATE matches SET " . implode(', ', $updates) . " WHERE id = ?";

        try {
            $this->db->beginTransaction();

            $result = $this->db->execute($sql, $params);

            // Log admin action
            $this->logAdminAction($adminId, ADMIN_ACTION_MATCH_UPDATE, 'match', $matchId, "Updated match");

            $this->db->commit();
            return $result;

        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception("Failed to update match: " . $e->getMessage());
        }
    }

    /**
     * Settle match
     */
    public function settleMatch($matchId, $winningOutcome, $adminId) {
        try {
            $result = $this->match->settleMatch($matchId, $winningOutcome);

            // Log admin action
            $this->logAdminAction($adminId, ADMIN_ACTION_MATCH_SETTLE, 'match', $matchId, "Settled match with outcome: $winningOutcome");

            return $result;

        } catch (Exception $e) {
            throw new Exception("Failed to settle match: " . $e->getMessage());
        }
    }

    /**
     * Get reported content
     */
    public function getReportedContent($content_type = null, $status = null) {
        $sql = "SELECT rc.*, u.username as reporter_name
                FROM reported_content rc
                JOIN users u ON rc.reporter_id = u.user_id";
        $params = [];

        $whereConditions = [];
        if ($content_type) {
            $whereConditions[] = "rc.content_type = ?";
            $params[] = $content_type;
        }

        if ($status) {
            $whereConditions[] = "rc.status = ?";
            $params[] = $status;
        }

        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(' AND ', $whereConditions);
        }

        $sql .= " ORDER BY rc.created_at DESC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get admin actions log
     */
    public function getAdminActions($limit = ITEMS_PER_PAGE, $offset = 0, $adminId = null) {
        $sql = "SELECT aa.*, u.username as admin_name
                FROM admin_actions aa
                JOIN users u ON aa.admin_id = u.user_id";
        $params = [];

        if ($adminId) {
            $sql .= " WHERE aa.admin_id = ?";
            $params[] = $adminId;
        }

        $sql .= " ORDER BY aa.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get system analytics
     */
    public function getAnalytics($startDate = null, $endDate = null) {
        $analytics = [];

        // User growth
        $userGrowth = $this->getUserGrowthAnalytics($startDate, $endDate);
        $analytics['user_growth'] = $userGrowth;

        // Betting trends
        $bettingTrends = $this->getBettingTrends($startDate, $endDate);
        $analytics['betting_trends'] = $bettingTrends;

        // Revenue/coin flow
        $coinFlow = $this->getCoinFlowAnalytics($startDate, $endDate);
        $analytics['coin_flow'] = $coinFlow;

        // Activity patterns
        $activityPatterns = $this->getActivityPatterns($startDate, $endDate);
        $analytics['activity_patterns'] = $activityPatterns;

        return $analytics;
    }

    /**
     * Get user growth analytics
     */
    private function getUserGrowthAnalytics($startDate, $endDate) {
        $sql = "SELECT
                    DATE(created_at) as date,
                    COUNT(*) as new_users
                FROM users
                    WHERE created_at BETWEEN ? AND ?
                GROUP BY DATE(created_at)
                ORDER BY date ASC";

        $params = [$startDate ?? '2024-01-01', $endDate ?? date('Y-m-d')];
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get betting trends
     */
    private function getBettingTrends($startDate, $endDate) {
        $sql = "SELECT
                    DATE(created_at) as date,
                    COUNT(*) as total_bets,
                    SUM(amount) as total_volume,
                    AVG(amount) as avg_bet
                FROM bets
                    WHERE created_at BETWEEN ? AND ?
                GROUP BY DATE(created_at)
                ORDER BY date ASC";

        $params = [$startDate ?? '2024-01-01', $endDate ?? date('Y-m-d')];
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get coin flow analytics
     */
    private function getCoinFlowAnalytics($startDate, $endDate) {
        $sql = "SELECT
                    type,
                    SUM(amount) as total_coins,
                    COUNT(*) as transaction_count
                FROM transactions
                    WHERE created_at BETWEEN ? AND ?
                GROUP BY type
                ORDER BY total_coins DESC";

        $params = [$startDate ?? '2024-01-01', $endDate ?? date('Y-m-d')];
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get activity patterns (hourly)
     */
    private function getActivityPatterns($startDate, $endDate) {
        $sql = "SELECT
                    HOUR(created_at) as hour,
                    COUNT(*) as activity_count
                FROM user_activities
                    WHERE created_at BETWEEN ? AND ?
                GROUP BY HOUR(created_at)
                ORDER BY hour ASC";

        $params = [$startDate ?? '2024-01-01', $endDate ?? date('Y-m-d')];
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Export data to CSV
     */
    public function exportData($dataType, $startDate = null, $endDate = null) {
        switch ($dataType) {
            case 'users':
                return $this->exportUsers($startDate, $endDate);
            case 'transactions':
                return $this->exportTransactions($startDate, $endDate);
            case 'bets':
                return $this->exportBets($startDate, $endDate);
            default:
                throw new Exception("Invalid data type for export");
        }
    }

    /**
     * Export users data
     */
    private function exportUsers($startDate, $endDate) {
        $sql = "SELECT u.user_id, u.email, u.username, u.aura_coins, u.status, u.created_at, u.last_login
                FROM users u
                WHERE u.created_at BETWEEN ? AND ?
                ORDER BY u.created_at ASC";

        return $this->db->fetchAll($sql, [$startDate ?? '2024-01-01', $endDate ?? date('Y-m-d')]);
    }

    /**
     * Export transactions
     */
    private function exportTransactions($startDate, $endDate) {
        $sql = "SELECT t.*, u.username
                FROM transactions t
                JOIN users u ON t.user_id = u.user_id
                WHERE t.created_at BETWEEN ? AND ?
                ORDER BY t.created_at ASC";

        return $this->db->fetchAll($sql, [$startDate ?? '2024-01-01', $endDate ?? date('Y-m-d')]);
    }

    /**
     * Export bets
     */
    private function exportBets($startDate, $endDate) {
        $sql = "SELECT b.*, u.username, m.title
                FROM bets b
                JOIN users u ON b.user_id = u.user_id
                JOIN matches m ON b.match_id = m.id
                WHERE b.created_at BETWEEN ? AND ?
                ORDER BY b.created_at ASC";

        return $this->db->fetchAll($sql, [$startDate ?? '2024-01-01', $endDate ?? date('Y-m-d')]);
    }

    /**
     * Log admin action
     */
    private function logAdminAction($adminId, $actionType, $targetType, $targetId, $description = null) {
        $sql = "INSERT INTO admin_actions (admin_id, action_type, target_type, target_id, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)";
        $this->db->execute($sql, [
            $adminId,
            $actionType,
            $targetType,
            $targetId,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    }

    /**
     * Check if user is admin
     */
    public function isAdmin($userId) {
        $result = $this->db->fetch("SELECT is_admin FROM users WHERE user_id = ?", [$userId]);
        return $result && $result['is_admin'] == 1;
    }

    /**
     * Get admin statistics for specific admin
     */
    public function getAdminStats($adminId) {
        $sql = "SELECT
                    COUNT(*) as total_actions,
                    COUNT(DISTINCT DATE(created_at)) as active_days,
                    MAX(created_at) as last_action
                FROM admin_actions
                WHERE admin_id = ?";

        return $this->db->fetch($sql, [$adminId]);
    }
}
?>