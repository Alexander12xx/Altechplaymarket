<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';

/**
 * Advertisement System Class
 * Handles ad management and coin rewards for watching ads
 */
class Ad {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Create a new advertisement
     */
    public function createAd($data) {
        $requiredFields = ['title', 'type', 'content_url', 'coin_reward', 'created_by'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }

        // Validate ad type
        if (!in_array($data['type'], [AD_TYPE_IMAGE, AD_TYPE_VIDEO, AD_TYPE_INTERACTIVE])) {
            throw new Exception("Invalid ad type");
        }

        // Validate coin reward
        if ($data['coin_reward'] <= 0 || $data['coin_reward'] > 100) {
            throw new Exception("Coin reward must be between 0.01 and 100");
        }

        // Validate duration for video/interactive ads
        if (($data['type'] === AD_TYPE_VIDEO || $data['type'] === AD_TYPE_INTERACTIVE) &&
            (!isset($data['duration']) || $data['duration'] <= 0)) {
            throw new Exception("Duration is required for video/interactive ads");
        }

        try {
            $sql = "INSERT INTO ads (title, type, content_url, target_url, coin_reward, duration, budget, watermark_overlay, is_active, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $data['title'],
                $data['type'],
                $data['content_url'],
                $data['target_url'] ?? null,
                $data['coin_reward'],
                $data['duration'] ?? 5,
                $data['budget'] ?? 0,
                $data['watermark_overlay'] ?? 1,
                $data['is_active'] ?? 1,
                $data['created_by']
            ];

            $this->db->execute($sql, $params);
            return $this->db->lastInsertId();

        } catch (Exception $e) {
            throw new Exception("Failed to create ad: " . $e->getMessage());
        }
    }

    /**
     * Get ad by ID
     */
    public function getAdById($adId) {
        $sql = "SELECT * FROM ads WHERE id = ?";
        return $this->db->fetch($sql, [$adId]);
    }

    /**
     * Get all active ads
     */
    public function getActiveAds($limit = 10) {
        $sql = "SELECT * FROM ads WHERE is_active = 1 ORDER BY created_at DESC LIMIT ?";
        return $this->db->fetchAll($sql, [$limit]);
    }

    /**
     * Get available ads for users
     */
    public function getAvailableAds($userId, $limit = 5) {
        // Get ads user hasn't watched recently (in last 24 hours)
        $sql = "SELECT a.*
                FROM ads a
                WHERE a.is_active = 1
                AND a.id NOT IN (
                    SELECT DISTINCT ad_reference_id
                    FROM transactions
                    WHERE user_id = ?
                    AND type = ?
                    AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                )
                ORDER BY RAND()
                LIMIT ?";

        return $this->db->fetchAll($sql, [$userId, TRANSACTION_AD_WATCH, $limit]);
    }

    /**
     * Process ad view and award coins
     */
    public function processAdView($userId, $adId, $viewDuration) {
        // Get ad details
        $ad = $this->getAdById($adId);
        if (!$ad || !$ad['is_active']) {
            throw new Exception("Ad not found or inactive");
        }

        // Check if user has already watched this ad recently
        $recentView = $this->db->fetch(
            "SELECT id FROM transactions WHERE user_id = ? AND reference_id = ? AND type = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            [$userId, $adId, TRANSACTION_AD_WATCH]
        );

        if ($recentView) {
            throw new Exception("You have already watched this ad recently");
        }

        // Check if duration is sufficient (for video/interactive ads)
        $requiredDuration = ($ad['type'] === AD_TYPE_IMAGE) ? 3 : $ad['duration'];
        if ($viewDuration < $requiredDuration) {
            throw new Exception("Insufficient view duration");
        }

        try {
            $this->db->beginTransaction();

            // Award coins to user
            $user = new User();
            $user->updateCoins(
                $userId,
                $ad['coin_reward'],
                TRANSACTION_AD_WATCH,
                "Watched ad: {$ad['title']}",
                $adId
            );

            // Update ad statistics
            $this->db->execute(
                "UPDATE ads SET impressions = impressions + 1 WHERE id = ?",
                [$adId]
            );

            $this->db->commit();

            return [
                'coins_awarded' => $ad['coin_reward'],
                'ad_title' => $ad['title'],
                'new_balance' => $user->getBalance($userId)
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception("Failed to process ad view: " . $e->getMessage());
        }
    }

    /**
     * Record ad click
     */
    public function recordAdClick($adId) {
        $this->db->execute("UPDATE ads SET clicks = clicks + 1 WHERE id = ?", [$adId]);
    }

    /**
     * Update ad
     */
    public function updateAd($adId, $data) {
        $allowedFields = ['title', 'type', 'content_url', 'target_url', 'coin_reward', 'duration', 'budget', 'watermark_overlay', 'is_active'];
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

        $params[] = $adId;
        $sql = "UPDATE ads SET " . implode(', ', $updates) . " WHERE id = ?";

        return $this->db->execute($sql, $params);
    }

    /**
     * Delete ad
     */
    public function deleteAd($adId, $adminId) {
        try {
            $this->db->beginTransaction();

            // Get ad details for logging
            $ad = $this->getAdById($adId);
            if (!$ad) {
                throw new Exception("Ad not found");
            }

            // Delete ad
            $this->db->execute("DELETE FROM ads WHERE id = ?", [$adId]);

            // Log admin action
            $this->logAdminAction($adminId, ADMIN_ACTION_AD_DELETE, 'ad', $adId, "Deleted ad: {$ad['title']}");

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception("Failed to delete ad: " . $e->getMessage());
        }
    }

    /**
     * Get all ads with pagination (admin)
     */
    public function getAllAds($limit = ITEMS_PER_PAGE, $offset = 0, $status = null) {
        $sql = "SELECT a.*, u.username as created_by_name
                FROM ads a
                LEFT JOIN users u ON a.created_by = u.user_id";

        $params = [];

        if ($status !== null) {
            $sql .= " WHERE a.is_active = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY a.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get ad statistics
     */
    public function getAdStats() {
        $sql = "SELECT
                    COUNT(*) as total_ads,
                    COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_ads,
                    SUM(impressions) as total_impressions,
                    SUM(clicks) as total_clicks,
                    SUM(coin_reward * impressions) as total_coins_awarded,
                    AVG(coin_reward) as avg_coin_reward
                FROM ads";

        return $this->db->fetch($sql);
    }

    /**
     * Get ad performance data
     */
    public function getAdPerformance($adId) {
        $sql = "SELECT
                    a.*,
                    t.total_views,
                    t.total_coins_given,
                    t.unique_viewers,
                    ROUND((clicks / NULLIF(impressions, 0)) * 100, 2) as ctr_percentage
                FROM ads a
                LEFT JOIN (
                    SELECT
                        reference_id as ad_id,
                        COUNT(*) as total_views,
                        SUM(amount) as total_coins_given,
                        COUNT(DISTINCT user_id) as unique_viewers
                    FROM transactions
                    WHERE type = ?
                    GROUP BY reference_id
                ) t ON a.id = t.ad_id
                WHERE a.id = ?";

        return $this->db->fetch($sql, [TRANSACTION_AD_WATCH, $adId]);
    }

    /**
     * Get top performing ads
     */
    public function getTopPerformingAds($limit = 10, $metric = 'impressions') {
        $orderBy = 'a.impressions DESC';
        $join = '';

        if ($metric === 'views') {
            $join = "LEFT JOIN (
                        SELECT reference_id as ad_id, COUNT(*) as view_count
                        FROM transactions
                        WHERE type = '" . TRANSACTION_AD_WATCH . "'
                        GROUP BY reference_id
                     ) t ON a.id = t.ad_id";
            $orderBy = 't.view_count DESC';
        } elseif ($metric === 'ctr') {
            $join = "LEFT JOIN (
                        SELECT reference_id as ad_id, COUNT(*) as view_count
                        FROM transactions
                        WHERE type = '" . TRANSACTION_AD_WATCH . "'
                        GROUP BY reference_id
                     ) t ON a.id = t.ad_id";
            $orderBy = '((a.clicks / NULLIF(a.impressions, 0)) * 100) DESC';
        }

        $sql = "SELECT a.* $join
                FROM ads a
                WHERE a.is_active = 1
                ORDER BY $orderBy
                LIMIT ?";

        return $this->db->fetchAll($sql, [$limit]);
    }

    /**
     * Get user's ad watching history
     */
    public function getUserAdHistory($userId, $limit = ITEMS_PER_PAGE, $offset = 0) {
        $sql = "SELECT t.*, a.title, a.type
                FROM transactions t
                JOIN ads a ON t.reference_id = a.id
                WHERE t.user_id = ? AND t.type = ?
                ORDER BY t.created_at DESC
                LIMIT ? OFFSET ?";

        return $this->db->fetchAll($sql, [$userId, TRANSACTION_AD_WATCH, $limit, $offset]);
    }

    /**
     * Get available ad count for user
     */
    public function getAvailableAdCount($userId) {
        $sql = "SELECT COUNT(*) as count
                FROM ads a
                WHERE a.is_active = 1
                AND a.id NOT IN (
                    SELECT DISTINCT ad_reference_id
                    FROM transactions
                    WHERE user_id = ?
                    AND type = ?
                    AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                )";

        $result = $this->db->fetch($sql, [$userId, TRANSACTION_AD_WATCH]);
        return $result['count'];
    }

    /**
     * Upload ad content
     */
    public function uploadAdContent($file, $adType) {
        // Validate file
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception("Invalid file upload");
        }

        $maxSize = $adType === AD_TYPE_IMAGE ? MAX_AVATAR_SIZE : MAX_AD_SIZE;
        if ($file['size'] > $maxSize) {
            throw new Exception("File size too large");
        }

        $allowedTypes = $adType === AD_TYPE_IMAGE ? ALLOWED_IMAGE_TYPES : ALLOWED_VIDEO_TYPES;
        $fileInfo = pathinfo($file['name']);
        $extension = strtolower($fileInfo['extension']);

        if (!in_array($extension, $allowedTypes)) {
            throw new Exception("File type not allowed");
        }

        // Generate unique filename
        $filename = generateUniqueId('ad_') . '.' . $extension;
        $uploadPath = AD_PATH . $filename;

        // Create upload directory if it doesn't exist
        if (!is_dir(AD_PATH)) {
            mkdir(AD_PATH, 0755, true);
        }

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new Exception("Failed to upload file");
        }

        return 'uploads/ads/' . $filename;
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
}
?>