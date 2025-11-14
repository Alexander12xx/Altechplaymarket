<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Match.php';
require_once __DIR__ . '/../classes/Chat.php';
require_once __DIR__ . '/../classes/Ad.php';

// Require login
requireLogin();

// Get current user
$currentUser = getCurrentUser();
$match = new Match();
$chat = new Chat();
$ad = new Ad();

// Get dashboard data
$upcomingMatches = $match->getActiveMatches(null, 5);
$recentBets = $match->getUserBets($currentUser['user_id'], 5);
$recentMessages = $chat->getMessages(5);
$availableAds = $ad->getAvailableAds($currentUser['user_id'], 3);
$userStats = $match->getBettingStats($currentUser['user_id']);

$pageTitle = "Dashboard - " . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-container">
    <!-- Welcome Section -->
    <section class="welcome-section">
        <div class="welcome-content">
            <h1 class="welcome-title">Welcome back, <?php echo e($currentUser['username'] ?? explode('@', $currentUser['email'])[0]); ?>!</h1>
            <p class="welcome-subtitle">Ready to test your prediction skills?</p>
        </div>
        <div class="welcome-stats">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="icon-coins"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-value"><?php echo formatCoins($currentUser['aura_coins']); ?></h3>
                    <p class="stat-label">Current Balance</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="icon-bet-count"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-value"><?php echo number_format($userStats['total_bets'] ?? 0); ?></h3>
                    <p class="stat-label">Total Bets</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="icon-win-rate"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-value">
                        <?php
                        $winRate = 0;
                        if (($userStats['total_bets'] ?? 0) > 0) {
                            $winRate = round(($userStats['winning_bets'] / $userStats['total_bets']) * 100, 1);
                        }
                        echo $winRate . '%';
                        ?>
                    </h3>
                    <p class="stat-label">Win Rate</p>
                </div>
            </div>
        </div>
    </section>

    <div class="dashboard-grid">
        <!-- Quick Actions -->
        <section class="quick-actions">
            <h2 class="section-title">Quick Actions</h2>
            <div class="action-grid">
                <a href="/pages/betting.php" class="action-card">
                    <div class="action-icon">
                        <i class="icon-trophy"></i>
                    </div>
                    <div class="action-content">
                        <h3>Place Bets</h3>
                        <p>Bet on active matches</p>
                    </div>
                </a>

                <a href="/pages/watch-ads.php" class="action-card">
                    <div class="action-icon">
                        <i class="icon-ad-reward"></i>
                    </div>
                    <div class="action-content">
                        <h3>Earn Coins</h3>
                        <p>Watch ads for rewards</p>
                    </div>
                </a>

                <a href="/pages/chat.php" class="action-card">
                    <div class="action-icon">
                        <i class="icon-chat"></i>
                    </div>
                    <div class="action-content">
                        <h3>Live Chat</h3>
                        <p>Join the conversation</p>
                    </div>
                </a>

                <a href="/pages/profile.php" class="action-card">
                    <div class="action-icon">
                        <i class="icon-user"></i>
                    </div>
                    <div class="action-content">
                        <h3>My Profile</h3>
                        <p>Update your info</p>
                    </div>
                </a>
            </div>
        </section>

        <!-- Upcoming Matches -->
        <section class="upcoming-matches">
            <div class="section-header">
                <h2 class="section-title">Upcoming Matches</h2>
                <a href="/pages/betting.php" class="section-link">View All</a>
            </div>

            <div class="matches-list">
                <?php if (empty($upcomingMatches)): ?>
                    <div class="empty-state">
                        <i class="empty-icon icon-no-matches"></i>
                        <h3>No active matches</h3>
                        <p>Check back later for new betting opportunities</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($upcomingMatches as $match): ?>
                        <div class="match-card">
                            <div class="match-header">
                                <span class="match-category"><?php echo e($match['category']); ?></span>
                                <span class="match-status <?php echo strtolower($match['status']); ?>"><?php echo e($match['status']); ?></span>
                            </div>
                            <div class="match-teams">
                                <div class="team">
                                    <h4><?php echo e($match['team_a']); ?></h4>
                                    <span class="odds"><?php echo number_format($match['odds_a'], 2); ?></span>
                                </div>
                                <div class="vs-divider">VS</div>
                                <div class="team">
                                    <h4><?php echo e($match['team_b']); ?></h4>
                                    <span class="odds"><?php echo number_format($match['odds_b'], 2); ?></span>
                                </div>
                            </div>
                            <div class="match-footer">
                                <span class="match-time"><?php echo formatDateTime($match['match_time']); ?></span>
                                <a href="/pages/betting.php#match-<?php echo $match['id']; ?>" class="btn btn-sm btn-primary">Bet Now</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- Recent Activity -->
        <section class="recent-activity">
            <div class="section-header">
                <h2 class="section-title">Recent Activity</h2>
            </div>

            <div class="activity-tabs">
                <button class="tab-btn active" data-tab="bets">My Bets</button>
                <button class="tab-btn" data-tab="messages">Chat</button>
            </div>

            <div class="tab-content">
                <div class="tab-pane active" id="bets-tab">
                    <?php if (empty($recentBets)): ?>
                        <div class="empty-state">
                            <i class="empty-icon icon-no-bets"></i>
                            <h3>No bets yet</h3>
                            <p>Start betting to see your activity here</p>
                        </div>
                    <?php else: ?>
                        <div class="bet-list">
                            <?php foreach ($recentBets as $bet): ?>
                                <div class="bet-item">
                                    <div class="bet-header">
                                        <span class="bet-match"><?php echo e($bet['title']); ?></span>
                                        <span class="bet-status <?php echo $bet['status']; ?>"><?php echo ucfirst($bet['status']); ?></span>
                                    </div>
                                    <div class="bet-details">
                                        <span class="bet-outcome">Bet: <?php echo e($bet['outcome']); ?></span>
                                        <span class="bet-amount"><?php echo formatCoins($bet['amount']); ?></span>
                                    </div>
                                    <?php if ($bet['status'] === 'won'): ?>
                                        <div class="bet-win">
                                            <span class="win-amount">+<?php echo formatCoins($bet['potential_win'] - $bet['amount']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="tab-pane" id="messages-tab">
                    <?php if (empty($recentMessages)): ?>
                        <div class="empty-state">
                            <i class="empty-icon icon-no-messages"></i>
                            <h3>No messages yet</h3>
                            <p>Be the first to start chatting</p>
                        </div>
                    <?php else: ?>
                        <div class="message-list">
                            <?php foreach ($recentMessages as $message): ?>
                                <div class="message-item">
                                    <img src="<?php echo getUserAvatar($message['profile_picture']); ?>" alt="Avatar" class="message-avatar">
                                    <div class="message-content">
                                        <div class="message-header">
                                            <span class="message-username"><?php echo e($message['username']); ?></span>
                                            <span class="message-time"><?php echo timeAgo($message['created_at']); ?></span>
                                        </div>
                                        <p class="message-text"><?php echo e($message['message']); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Available Ads -->
        <section class="available-ads">
            <div class="section-header">
                <h2 class="section-title">Earn Coins</h2>
                <span class="section-count"><?php echo count($availableAds); ?> available</span>
            </div>

            <?php if (empty($availableAds)): ?>
                <div class="empty-state">
                    <i class="empty-icon icon-no-ads"></i>
                    <h3>No ads available</h3>
                    <p>Check back later for new earning opportunities</p>
                </div>
            <?php else: ?>
                <div class="ad-grid">
                    <?php foreach ($availableAds as $ad): ?>
                        <div class="ad-card">
                            <div class="ad-preview">
                                <?php if ($ad['type'] === 'Image'): ?>
                                    <img src="<?php echo e($ad['content_url']); ?>" alt="Ad" class="ad-image">
                                <?php else: ?>
                                    <div class="ad-video-placeholder">
                                        <i class="icon-video"></i>
                                        <span>Video Ad</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="ad-info">
                                <h4 class="ad-title"><?php echo e($ad['title']); ?></h4>
                                <div class="ad-reward">
                                    <i class="icon-coins"></i>
                                    <?php echo formatCoins($ad['coin_reward']); ?>
                                </div>
                                <button class="btn btn-sm btn-primary watch-ad-btn" data-ad-id="<?php echo $ad['id']; ?>">
                                    Watch & Earn
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="ad-cta">
                <a href="/pages/watch-ads.php" class="btn btn-outline">View All Ads</a>
            </div>
        </section>
    </div>
</div>

<style>
/* Dashboard Styles */
.dashboard-container {
    padding: 2rem;
    max-width: 1400px;
    margin: 0 auto;
}

.welcome-section {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 2rem;
    margin-bottom: 3rem;
    align-items: center;
}

.welcome-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.welcome-subtitle {
    color: var(--text-secondary);
    font-size: 1.1rem;
}

.welcome-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

.stat-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 1rem;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    min-width: 150px;
}

.stat-icon {
    width: 50px;
    height: 50px;
    background: rgba(74, 222, 128, 0.1);
    border-radius: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 1.5rem;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.stat-label {
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.dashboard-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
}

.section-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 1.5rem;
}

.section-header {
    display: flex;
    justify-content: between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.section-link {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 500;
}

.section-count {
    background: var(--primary-color);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.875rem;
    font-weight: 500;
}

/* Quick Actions */
.quick-actions {
    grid-column: 1 / -1;
}

.action-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.action-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 1rem;
    padding: 1.5rem;
    text-decoration: none;
    color: inherit;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.2s;
}

.action-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    border-color: var(--primary-color);
}

.action-icon {
    width: 60px;
    height: 60px;
    background: rgba(74, 222, 128, 0.1);
    border-radius: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 1.5rem;
}

.action-content h3 {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.action-content p {
    color: var(--text-secondary);
    font-size: 0.875rem;
}

/* Match Cards */
.matches-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.match-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 1rem;
    padding: 1.5rem;
}

.match-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.match-category {
    background: var(--primary-color);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
}

.match-status {
    padding: 0.25rem 0.75rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
}

.match-status.upcoming {
    background: rgba(74, 222, 128, 0.1);
    color: var(--primary-color);
}

.match-status.live {
    background: rgba(251, 191, 36, 0.1);
    color: var(--warning-color);
}

.match-teams {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: 1rem;
    align-items: center;
    margin-bottom: 1.5rem;
}

.team {
    text-align: center;
}

.team h4 {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.odds {
    background: rgba(74, 222, 128, 0.1);
    color: var(--primary-color);
    padding: 0.25rem 0.75rem;
    border-radius: 0.5rem;
    font-weight: 600;
    font-size: 0.875rem;
}

.vs-divider {
    color: var(--text-secondary);
    font-weight: 600;
    font-size: 0.875rem;
}

.match-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.match-time {
    color: var(--text-secondary);
    font-size: 0.875rem;
}

/* Activity Tabs */
.activity-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    border-bottom: 1px solid var(--border-color);
}

.tab-btn {
    background: none;
    border: none;
    padding: 0.75rem 1rem;
    color: var(--text-secondary);
    font-weight: 500;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
}

.tab-btn.active {
    color: var(--primary-color);
    border-bottom-color: var(--primary-color);
}

.tab-pane {
    display: none;
}

.tab-pane.active {
    display: block;
}

/* Bet Items */
.bet-item {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 0.5rem;
    padding: 1rem;
    margin-bottom: 0.75rem;
}

.bet-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.bet-match {
    font-weight: 500;
    color: var(--text-primary);
}

.bet-status {
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 500;
}

.bet-status.pending {
    background: rgba(251, 191, 36, 0.1);
    color: var(--warning-color);
}

.bet-status.won {
    background: rgba(74, 222, 128, 0.1);
    color: var(--primary-color);
}

.bet-status.lost {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger-color);
}

.bet-details {
    display: flex;
    justify-content: space-between;
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.bet-win {
    margin-top: 0.5rem;
    padding-top: 0.5rem;
    border-top: 1px solid var(--border-color);
}

.win-amount {
    color: var(--success-color);
    font-weight: 600;
}

/* Message Items */
.message-item {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.message-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
}

.message-content {
    flex: 1;
}

.message-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.25rem;
}

.message-username {
    font-weight: 500;
    color: var(--text-primary);
    font-size: 0.875rem;
}

.message-time {
    color: var(--text-secondary);
    font-size: 0.75rem;
}

.message-text {
    color: var(--text-secondary);
    font-size: 0.875rem;
    line-height: 1.4;
}

/* Ad Cards */
.ad-grid {
    display: grid;
    gap: 1rem;
}

.ad-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 1rem;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.ad-preview {
    height: 120px;
    background: var(--border-color);
    display: flex;
    align-items: center;
    justify-content: center;
}

.ad-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.ad-video-placeholder {
    text-align: center;
    color: var(--text-secondary);
}

.ad-video-placeholder i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    display: block;
}

.ad-info {
    padding: 1rem;
}

.ad-title {
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 0.75rem;
    font-size: 0.875rem;
}

.ad-reward {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    color: var(--primary-color);
    font-weight: 600;
    margin-bottom: 0.75rem;
}

.ad-cta {
    margin-top: 1.5rem;
    text-align: center;
}

/* Empty States */
.empty-state {
    text-align: center;
    padding: 2rem;
    color: var(--text-secondary);
}

.empty-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state h3 {
    margin-bottom: 0.5rem;
    color: var(--text-primary);
}

/* Responsive Design */
@media (max-width: 1024px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }

    .quick-actions {
        grid-column: 1;
    }
}

@media (max-width: 768px) {
    .dashboard-container {
        padding: 1rem;
    }

    .welcome-section {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }

    .welcome-title {
        font-size: 2rem;
    }

    .welcome-stats {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }

    .action-grid {
        grid-template-columns: 1fr;
    }

    .match-teams {
        grid-template-columns: 1fr;
        gap: 0.5rem;
        text-align: center;
    }

    .vs-divider {
        display: none;
    }
}
</style>

<script>
// Tab switching
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const tabName = this.dataset.tab;

        // Update button states
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');

        // Update tab panes
        document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
        document.getElementById(tabName + '-tab').classList.add('active');
    });
});

// Watch ad handlers
document.querySelectorAll('.watch-ad-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        const adId = this.dataset.adId;
        const btn = this;

        try {
            btn.disabled = true;
            btn.textContent = 'Loading...';

            // Redirect to ad viewing page
            window.location.href = '/pages/watch-ad.php?id=' + adId;

        } catch (error) {
            console.error('Error loading ad:', error);
            btn.disabled = false;
            btn.textContent = 'Watch & Earn';
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>