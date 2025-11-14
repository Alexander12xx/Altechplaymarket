    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3 class="footer-title">Platform</h3>
                    <ul class="footer-links">
                        <li><a href="/pages/index.php">Dashboard</a></li>
                        <li><a href="/pages/betting.php">Betting</a></li>
                        <li><a href="/pages/chat.php">Chat</a></li>
                        <li><a href="/pages/history.php">History</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3 class="footer-title">Account</h3>
                    <ul class="footer-links">
                        <?php if (isLoggedIn()): ?>
                            <li><a href="/pages/profile.php">Profile</a></li>
                            <li><a href="/pages/wallet.php">Wallet</a></li>
                            <li><a href="/pages/logout.php">Logout</a></li>
                        <?php else: ?>
                            <li><a href="/pages/login.php">Login</a></li>
                            <li><a href="/pages/register.php">Register</a></li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3 class="footer-title">Resources</h3>
                    <ul class="footer-links">
                        <li><a href="/pages/faq.php">FAQ</a></li>
                        <li><a href="/pages/terms.php">Terms of Service</a></li>
                        <li><a href="/pages/privacy.php">Privacy Policy</a></li>
                        <li><a href="/pages/contact.php">Contact</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3 class="footer-title">Community</h3>
                    <ul class="footer-links">
                        <li><a href="/pages/chat.php">Live Chat</a></li>
                        <li><a href="/pages/leaderboard.php">Leaderboard</a></li>
                        <li><a href="/pages/guides.php">Betting Guides</a></li>
                        <li><a href="/pages/support.php">Support</a></li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <div class="footer-stats">
                    <div class="stat-item">
                        <span class="stat-number"><?php echo number_format(getTotalUsers()); ?></span>
                        <span class="stat-label">Users</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo number_format(getTotalBets()); ?></span>
                        <span class="stat-label">Bets</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo number_format(getTotalTransactions()); ?></span>
                        <span class="stat-label">Transactions</span>
                    </div>
                </div>

                <div class="footer-copyright">
                    <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
                    <p class="version-info">Version <?php echo APP_VERSION; ?> | Built with PHP & MySQL</p>
                </div>

                <div class="footer-legal">
                    <p>This platform uses virtual currency only. No real money betting.</p>
                    <p>18+ only. Play responsibly.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- JavaScript -->
    <script src="/assets/js/main.js"></script>

    <!-- Page-specific JavaScript (if needed) -->
    <?php if (file_exists(__DIR__ . "/../assets/js/{$pageName}.js")): ?>
        <script src="/assets/js/<?php echo $pageName; ?>.js"></script>
    <?php endif; ?>

    <!-- Real-time updates (for pages that need it) -->
    <?php if (in_array($pageName, ['index', 'betting', 'chat', 'dashboard'])): ?>
        <script>
            // Enable real-time updates
            if (typeof enableRealTimeUpdates === 'function') {
                enableRealTimeUpdates();
            }
        </script>
    <?php endif; ?>

    <?php

    // Helper functions for footer statistics
    function getTotalUsers() {
        static $total = null;
        if ($total === null) {
            try {
                $db = Database::getInstance();
                $result = $db->fetch("SELECT COUNT(*) as count FROM users WHERE status = ?", [USER_STATUS_ACTIVE]);
                $total = $result['count'] ?? 0;
            } catch (Exception $e) {
                $total = 0;
            }
        }
        return $total;
    }

    function getTotalBets() {
        static $total = null;
        if ($total === null) {
            try {
                $db = Database::getInstance();
                $result = $db->fetch("SELECT COUNT(*) as count FROM bets");
                $total = $result['count'] ?? 0;
            } catch (Exception $e) {
                $total = 0;
            }
        }
        return $total;
    }

    function getTotalTransactions() {
        static $total = null;
        if ($total === null) {
            try {
                $db = Database::getInstance();
                $result = $db->fetch("SELECT COUNT(*) as count FROM transactions");
                $total = $result['count'] ?? 0;
            } catch (Exception $e) {
                $total = 0;
            }
        }
        return $total;
    }

    ?>

    <!-- Analytics (if enabled) -->
    <?php if (defined('ANALYTICS_ENABLED') && ANALYTICS_ENABLED): ?>
        <script async src="https://www.googletagmanager.com/gtag/js?id=GA_MEASUREMENT_ID"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', 'GA_MEASUREMENT_ID');
        </script>
    <?php endif; ?>

    <!-- Service Worker for PWA (optional) -->
    <?php if (file_exists(__DIR__ . '/../sw.js')): ?>
        <script>
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/sw.js')
                    .then(registration => console.log('SW registered'))
                    .catch(error => console.log('SW registration failed'));
            }
        </script>
    <?php endif; ?>

    <!-- Performance monitoring (development only) -->
    <?php if (defined('DEBUG') && DEBUG): ?>
        <script>
            // Log page load performance
            window.addEventListener('load', function() {
                if (window.performance && window.performance.timing) {
                    var timing = window.performance.timing;
                    var loadTime = timing.loadEventEnd - timing.navigationStart;
                    console.log('Page load time: ' + loadTime + 'ms');
                }
            });
        </script>
    <?php endif; ?>

</body>
</html>