<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';

/**
 * Match and Betting Management Class
 * Handles match creation, odds calculation, and betting operations
 */
class Match {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Create a new match
     */
    public function createMatch($data) {
        $requiredFields = ['title', 'category', 'match_time', 'team_a', 'team_b', 'odds_a', 'odds_b'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }

        // Validate odds
        if ($data['odds_a'] <= 0 || $data['odds_b'] <= 0) {
            throw new Exception("Odds must be greater than 0");
        }

        // Validate category
        $validCategories = [CATEGORY_SPORTS, CATEGORY_E_SPORTS, CATEGORY_CRYPTO, CATEGORY_STOCKS, CATEGORY_CUSTOM];
        if (!in_array($data['category'], $validCategories)) {
            throw new Exception("Invalid category");
        }

        $sql = "INSERT INTO matches (title, category, match_time, status, team_a, team_b, odds_a, odds_b, max_bet, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $params = [
            $data['title'],
            $data['category'],
            $data['match_time'],
            MATCH_STATUS_UPCOMING,
            $data['team_a'],
            $data['team_b'],
            $data['odds_a'],
            $data['odds_b'],
            $data['max_bet'] ?? MAX_BET_AMOUNT,
            $data['created_by'] ?? null
        ];

        $this->db->execute($sql, $params);
        return $this->db->lastInsertId();
    }

    /**
     * Get match by ID
     */
    public function getMatchById($matchId) {
        $sql = "SELECT * FROM matches WHERE id = ?";
        return $this->db->fetch($sql, [$matchId]);
    }

    /**
     * Get all active matches
     */
    public function getActiveMatches($category = null) {
        $sql = "SELECT * FROM matches WHERE status IN (?, ?)";
        $params = [MATCH_STATUS_UPCOMING, MATCH_STATUS_LIVE];

        if ($category) {
            $sql .= " AND category = ?";
            $params[] = $category;
        }

        $sql .= " ORDER BY match_time ASC";
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get matches for betting page
     */
    public function getBettingMatches($limit = 20, $offset = 0, $category = null) {
        $sql = "SELECT m.*,
                       COUNT(b.id) as total_bets,
                       SUM(CASE WHEN b.outcome = 'team_a' THEN b.amount ELSE 0 END) as team_a_volume,
                       SUM(CASE WHEN b.outcome = 'team_b' THEN b.amount ELSE 0 END) as team_b_volume
                FROM matches m
                LEFT JOIN bets b ON m.id = b.match_id AND b.status = ?
                WHERE m.status IN (?, ?)";

        $params = [BET_STATUS_PENDING, MATCH_STATUS_UPCOMING, MATCH_STATUS_LIVE];

        if ($category) {
            $sql .= " AND m.category = ?";
            $params[] = $category;
        }

        $sql .= " GROUP BY m.id ORDER BY m.match_time ASC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Place a bet
     */
    public function placeBet($userId, $matchId, $outcome, $amount) {
        // Validate input
        if (!in_array($outcome, [BET_OUTCOME_TEAM_A, BET_OUTCOME_TEAM_B])) {
            throw new Exception("Invalid bet outcome");
        }

        if ($amount < MIN_BET_AMOUNT || $amount > MAX_BET_AMOUNT) {
            throw new Exception(ERROR_INVALID_BET_AMOUNT);
        }

        // Check rate limiting
        if ($this->isBetRateLimited($userId)) {
            throw new Exception(ERROR_RATE_LIMIT_EXCEEDED);
        }

        // Get match and validate
        $match = $this->getMatchById($matchId);
        if (!$match) {
            throw new Exception(ERROR_MATCH_NOT_FOUND);
        }

        if (!in_array($match['status'], [MATCH_STATUS_UPCOMING, MATCH_STATUS_LIVE])) {
            throw new Exception(ERROR_BET_CLOSED);
        }

        if ($amount > $match['max_bet']) {
            throw new Exception("Bet amount exceeds maximum allowed for this match");
        }

        // Check user balance
        $user = new User();
        $balance = $user->getBalance($userId);
        if ($balance < $amount) {
            throw new Exception(ERROR_INSUFFICIENT_COINS);
        }

        try {
            $this->db->beginTransaction();

            // Get odds based on outcome
            $odds = $outcome === BET_OUTCOME_TEAM_A ? $match['odds_a'] : $match['odds_b'];
            $potentialWin = $amount * $odds;

            // Create bet record
            $betId = generateUniqueId('bet-');
            $sql = "INSERT INTO bets (bet_id, user_id, match_id, outcome, amount, odds, potential_win, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $this->db->execute($sql, [$betId, $userId, $matchId, $outcome, $amount, $odds, $potentialWin, BET_STATUS_PENDING]);

            // Deduct coins from user
            $user->updateCoins($userId, -$amount, TRANSACTION_BET_PLACED, "Bet placed on {$match['title']}", $betId);

            // Update max bet if needed (decrease as bets are placed)
            $newMaxBet = max(MIN_BET_AMOUNT, $match['max_bet'] - $amount);
            $this->db->execute("UPDATE matches SET max_bet = ? WHERE id = ?", [$newMaxBet, $matchId]);

            $this->db->commit();

            return [
                'bet_id' => $betId,
                'amount' => $amount,
                'potential_win' => $potentialWin,
                'odds' => $odds
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception("Failed to place bet: " . $e->getMessage());
        }
    }

    /**
     * Settle a match
     */
    public function settleMatch($matchId, $winningOutcome) {
        $match = $this->getMatchById($matchId);
        if (!$match) {
            throw new Exception(ERROR_MATCH_NOT_FOUND);
        }

        if ($match['status'] !== MATCH_STATUS_LIVE && $match['status'] !== MATCH_STATUS_UPCOMING) {
            throw new Exception("Match cannot be settled in current status");
        }

        try {
            $this->db->beginTransaction();

            // Update match status and winning outcome
            $sql = "UPDATE matches SET status = ?, winning_outcome = ?, updated_at = NOW() WHERE id = ?";
            $this->db->execute($sql, [MATCH_STATUS_SETTLED, $winningOutcome, $matchId]);

            // Get all pending bets for this match
            $bets = $this->db->fetchAll("SELECT * FROM bets WHERE match_id = ? AND status = ?", [$matchId, BET_STATUS_PENDING]);

            $user = new User();
            foreach ($bets as $bet) {
                if ($bet['outcome'] === $winningOutcome) {
                    // Winning bet
                    $this->db->execute(
                        "UPDATE bets SET status = ?, settled_at = NOW() WHERE bet_id = ?",
                        [BET_STATUS_WON, $bet['bet_id']]
                    );

                    // Award coins to user
                    $user->updateCoins(
                        $bet['user_id'],
                        $bet['potential_win'],
                        TRANSACTION_BET_WIN,
                        "Won bet on {$match['title']}",
                        $bet['bet_id']
                    );
                } else {
                    // Losing bet
                    $this->db->execute(
                        "UPDATE bets SET status = ?, settled_at = NOW() WHERE bet_id = ?",
                        [BET_STATUS_LOST, $bet['bet_id']]
                    );
                }
            }

            $this->db->commit();

            return [
                'match_id' => $matchId,
                'winning_outcome' => $winningOutcome,
                'total_bets' => count($bets)
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception("Failed to settle match: " . $e->getMessage());
        }
    }

    /**
     * Get user's bets
     */
    public function getUserBets($userId, $limit = ITEMS_PER_PAGE, $offset = 0, $status = null) {
        $sql = "SELECT b.*, m.title, m.category, m.team_a, m.team_b, m.status as match_status, m.winning_outcome
                FROM bets b
                JOIN matches m ON b.match_id = m.id
                WHERE b.user_id = ?";
        $params = [$userId];

        if ($status) {
            $sql .= " AND b.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY b.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get match statistics
     */
    public function getMatchStats($matchId) {
        $sql = "SELECT
                    COUNT(*) as total_bets,
                    SUM(amount) as total_volume,
                    SUM(CASE WHEN outcome = 'team_a' THEN amount ELSE 0 END) as team_a_volume,
                    SUM(CASE WHEN outcome = 'team_b' THEN amount ELSE 0 END) as team_b_volume,
                    AVG(amount) as average_bet,
                    MAX(amount) as largest_bet
                FROM bets
                WHERE match_id = ? AND status = ?";

        return $this->db->fetch($sql, [$matchId, BET_STATUS_PENDING]);
    }

    /**
     * Update match odds
     */
    public function updateOdds($matchId, $oddsA, $oddsB) {
        if ($oddsA <= 0 || $oddsB <= 0) {
            throw new Exception("Odds must be greater than 0");
        }

        $sql = "UPDATE matches SET odds_a = ?, odds_b = ?, updated_at = NOW() WHERE id = ?";
        return $this->db->execute($sql, [$oddsA, $oddsB, $matchId]);
    }

    /**
     * Cancel a match
     */
    public function cancelMatch($matchId, $reason = null) {
        $match = $this->getMatchById($matchId);
        if (!$match) {
            throw new Exception(ERROR_MATCH_NOT_FOUND);
        }

        if (!in_array($match['status'], [MATCH_STATUS_UPCOMING, MATCH_STATUS_LIVE])) {
            throw new Exception("Cannot cancel match in current status");
        }

        try {
            $this->db->beginTransaction();

            // Update match status
            $sql = "UPDATE matches SET status = ?, updated_at = NOW() WHERE id = ?";
            $this->db->execute($sql, [MATCH_STATUS_CANCELLED, $matchId]);

            // Refund all pending bets
            $bets = $this->db->fetchAll("SELECT * FROM bets WHERE match_id = ? AND status = ?", [$matchId, BET_STATUS_PENDING]);
            $user = new User();

            foreach ($bets as $bet) {
                // Update bet status
                $this->db->execute(
                    "UPDATE bets SET status = ?, settled_at = NOW() WHERE bet_id = ?",
                    [BET_STATUS_LOST, $bet['bet_id']]
                );

                // Refund coins
                $user->updateCoins(
                    $bet['user_id'],
                    $bet['amount'],
                    TRANSACTION_REFUND,
                    "Refund for cancelled match: {$match['title']}",
                    $bet['bet_id']
                );
            }

            $this->db->commit();

            return [
                'match_id' => $matchId,
                'refunded_bets' => count($bets),
                'total_refunded' => array_sum(array_column($bets, 'amount'))
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception("Failed to cancel match: " . $e->getMessage());
        }
    }

    /**
     * Check bet rate limiting
     */
    private function isBetRateLimited($userId) {
        $recentBets = $this->db->fetch(
            "SELECT COUNT(*) as count FROM bets WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)",
            [$userId, BET_COOLDOWN]
        );
        return $recentBets['count'] > 0;
    }

    /**
     * Get betting statistics for dashboard
     */
    public function getBettingStats($userId = null) {
        $params = [];
        $userFilter = "";

        if ($userId) {
            $userFilter = "WHERE b.user_id = ?";
            $params[] = $userId;
        }

        $sql = "SELECT
                    COUNT(*) as total_bets,
                    SUM(CASE WHEN b.status = ? THEN 1 ELSE 0 END) as winning_bets,
                    SUM(CASE WHEN b.status = ? THEN 1 ELSE 0 END) as losing_bets,
                    SUM(CASE WHEN b.status = ? THEN b.amount ELSE 0 END) as total_wagered,
                    SUM(CASE WHEN b.status = ? THEN b.potential_win - b.amount ELSE 0 END) as net_profit
                FROM bets b
                $userFilter";

        $params = [BET_STATUS_WON, BET_STATUS_LOST, BET_STATUS_WON, BET_STATUS_WON];
        if ($userId) {
            $params[] = $userId;
        }

        return $this->db->fetch($sql, $params);
    }
}
?>