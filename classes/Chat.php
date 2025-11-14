<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';

/**
 * Chat System Class
 * Handles real-time messaging and moderation
 */
class Chat {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Send a chat message
     */
    public function sendMessage($userId, $message) {
        // Validate input
        if (empty($message) || empty($userId)) {
            throw new Exception("Message and user ID are required");
        }

        if (strlen($message) > MAX_CHAT_LENGTH) {
            throw new Exception("Message too long");
        }

        // Check rate limiting
        if ($this->isChatRateLimited($userId)) {
            throw new Exception(ERROR_RATE_LIMIT_EXCEEDED);
        }

        // Check if user is allowed to chat
        $user = new User();
        $userInfo = $user->getUserById($userId);
        if (!$userInfo || $userInfo['is_blocked'] || $userInfo['status'] === USER_STATUS_BANNED) {
            throw new Exception("You are not allowed to send messages");
        }

        // Filter message
        $message = $this->filterMessage($message);

        try {
            $sql = "INSERT INTO chat_messages (user_id, message) VALUES (?, ?)";
            $this->db->execute($sql, [$userId, $message]);

            $messageId = $this->db->lastInsertId();
            return $this->getMessageById($messageId);

        } catch (Exception $e) {
            throw new Exception("Failed to send message: " . $e->getMessage());
        }
    }

    /**
     * Get recent messages
     */
    public function getMessages($limit = CHAT_MESSAGES_PER_PAGE, $offset = 0, $includeModerated = false) {
        $sql = "SELECT cm.*, u.username, up.profile_picture
                FROM chat_messages cm
                JOIN users u ON cm.user_id = u.user_id
                LEFT JOIN user_profiles up ON cm.user_id = up.user_id";

        if (!$includeModerated) {
            $sql .= " WHERE cm.is_moderated = 0";
        }

        $sql .= " ORDER BY cm.created_at DESC LIMIT ? OFFSET ?";
        $params = [$limit, $offset];

        $messages = $this->db->fetchAll($sql, $params);
        return array_reverse($messages); // Show in chronological order
    }

    /**
     * Get message by ID
     */
    public function getMessageById($messageId) {
        $sql = "SELECT cm.*, u.username, up.profile_picture
                FROM chat_messages cm
                JOIN users u ON cm.user_id = u.user_id
                LEFT JOIN user_profiles up ON cm.user_id = up.user_id
                WHERE cm.id = ?";
        return $this->db->fetch($sql, [$messageId]);
    }

    /**
     * Edit a message
     */
    public function editMessage($messageId, $userId, $newMessage) {
        // Validate input
        if (empty($newMessage) || strlen($newMessage) > MAX_CHAT_LENGTH) {
            throw new Exception("Invalid message content");
        }

        // Check if message exists and belongs to user
        $message = $this->db->fetch(
            "SELECT * FROM chat_messages WHERE id = ? AND user_id = ?",
            [$messageId, $userId]
        );

        if (!$message) {
            throw new Exception("Message not found or access denied");
        }

        // Check if message is too old to edit (5 minutes)
        $messageTime = strtotime($message['created_at']);
        if (time() - $messageTime > 300) {
            throw new Exception("Message can only be edited within 5 minutes");
        }

        // Filter new message
        $newMessage = $this->filterMessage($newMessage);

        try {
            $sql = "UPDATE chat_messages SET message = ?, is_edited = 1, edited_at = NOW() WHERE id = ?";
            $this->db->execute($sql, [$newMessage, $messageId]);

            return $this->getMessageById($messageId);

        } catch (Exception $e) {
            throw new Exception("Failed to edit message: " . $e->getMessage());
        }
    }

    /**
     * Delete a message (admin/moderator)
     */
    public function deleteMessage($messageId, $adminId, $reason = null) {
        // Check if message exists
        $message = $this->db->fetch("SELECT * FROM chat_messages WHERE id = ?", [$messageId]);
        if (!$message) {
            throw new Exception("Message not found");
        }

        try {
            $this->db->beginTransaction();

            // Mark message as moderated
            $sql = "UPDATE chat_messages SET is_moderated = 1, moderated_by = ?, moderated_at = NOW() WHERE id = ?";
            $this->db->execute($sql, [$adminId, $messageId]);

            // Log admin action
            $this->logAdminAction($adminId, ADMIN_ACTION_CHAT_DELETE, 'chat_message', $messageId, $reason);

            $this->db->commit();

            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception("Failed to delete message: " . $e->getMessage());
        }
    }

    /**
     * Report a message
     */
    public function reportMessage($messageId, $reporterId, $reason) {
        if (empty($reason)) {
            throw new Exception("Reason for report is required");
        }

        // Check if message exists
        $message = $this->db->fetch("SELECT * FROM chat_messages WHERE id = ?", [$messageId]);
        if (!$message) {
            throw new Exception("Message not found");
        }

        // Check if already reported
        $existing = $this->db->fetch(
            "SELECT id FROM reported_content WHERE reporter_id = ? AND content_type = ? AND content_id = ?",
            [$reporterId, CONTENT_TYPE_CHAT, $messageId]
        );

        if ($existing) {
            throw new Exception("Message already reported");
        }

        try {
            $sql = "INSERT INTO reported_content (reporter_id, content_type, content_id, reason) VALUES (?, ?, ?, ?)";
            $this->db->execute($sql, [$reporterId, CONTENT_TYPE_CHAT, $messageId, $reason]);

            return true;

        } catch (Exception $e) {
            throw new Exception("Failed to report message: " . . $e->getMessage());
        }
    }

    /**
     * Get reported messages
     */
    public function getReportedMessages($status = null) {
        $sql = "SELECT rc.*, cm.message, u.username as reporter_name, um.username as message_author
                FROM reported_content rc
                JOIN chat_messages cm ON rc.content_id = cm.id
                JOIN users u ON rc.reporter_id = u.user_id
                JOIN users um ON cm.user_id = um.user_id
                WHERE rc.content_type = ?";

        $params = [CONTENT_TYPE_CHAT];

        if ($status) {
            $sql .= " AND rc.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY rc.created_at DESC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get chat statistics
     */
    public function getChatStats() {
        $sql = "SELECT
                    COUNT(*) as total_messages,
                    COUNT(CASE WHEN cm.is_moderated = 1 THEN 1 END) as moderated_messages,
                    COUNT(CASE WHEN cm.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as messages_24h,
                    COUNT(CASE WHEN cm.created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 END) as messages_1h,
                    COUNT(DISTINCT cm.user_id) as unique_users_today
                FROM chat_messages cm
                WHERE cm.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)";

        return $this->db->fetch($sql);
    }

    /**
     * Get most active chatters
     */
    public function getActiveUsers($limit = 10) {
        $sql = "SELECT u.username, up.profile_picture, COUNT(cm.id) as message_count
                FROM chat_messages cm
                JOIN users u ON cm.user_id = u.user_id
                LEFT JOIN user_profiles up ON cm.user_id = up.user_id
                WHERE cm.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                AND cm.is_moderated = 0
                GROUP BY cm.user_id
                ORDER BY message_count DESC
                LIMIT ?";

        return $this->db->fetchAll($sql, [$limit]);
    }

    /**
     * Search chat history
     */
    public function searchMessages($query, $limit = 20, $offset = 0) {
        $sql = "SELECT cm.*, u.username, up.profile_picture
                FROM chat_messages cm
                JOIN users u ON cm.user_id = u.user_id
                LEFT JOIN user_profiles up ON cm.user_id = up.user_id
                WHERE cm.is_moderated = 0
                AND cm.message LIKE ?
                ORDER BY cm.created_at DESC
                LIMIT ? OFFSET ?";

        $params = ["%$query%", $limit, $offset];
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Filter message content
     */
    private function filterMessage($message) {
        // Basic profanity filter
        $blockedWords = ['fuck', 'shit', 'asshole', 'bitch', 'bastard'];
        $message = strtolower($message);

        foreach ($blockedWords as $word) {
            $message = str_ireplace($word, str_repeat('*', strlen($word)), $message);
        }

        // Remove excessive whitespace
        $message = preg_replace('/\s+/', ' ', trim($message));

        // Convert URLs to safe format
        $message = preg_replace(
            '/(https?:\/\/[^\s]+)/',
            '[LINK]',
            $message
        );

        return $message;
    }

    /**
     * Check chat rate limiting
     */
    private function isChatRateLimited($userId) {
        $recentMessages = $this->db->fetch(
            "SELECT COUNT(*) as count FROM chat_messages WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)",
            [$userId, CHAT_COOLDOWN]
        );
        return $recentMessages['count'] > 0;
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
     * Update report status
     */
    public function updateReportStatus($reportId, $status, $adminId) {
        $sql = "UPDATE reported_content SET status = ? WHERE id = ?";
        $result = $this->db->execute($sql, [$status, $reportId]);

        if ($result) {
            $this->logAdminAction($adminId, ADMIN_ACTION_CHAT_MODERATE, 'report', $reportId, "Updated report status to $status");
        }

        return $result;
    }

    /**
     * Get user's message history
     */
    public function getUserMessages($userId, $limit = 20, $offset = 0) {
        $sql = "SELECT * FROM chat_messages WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?";
        return $this->db->fetchAll($sql, [$userId, $limit, $offset]);
    }

    /**
     * Get message count for user
     */
    public function getUserMessageCount($userId) {
        $sql = "SELECT COUNT(*) as count FROM chat_messages WHERE user_id = ?";
        $result = $this->db->fetch($sql, [$userId]);
        return $result['count'];
    }
}
?>