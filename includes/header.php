<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Get current page for navigation highlighting
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pageName = basename($currentPath, '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo e(getPageDescription($pageName)); ?>">
    <meta name="keywords" content="<?php echo e(getPageKeywords($pageName)); ?>">
    <title><?php echo e(getPageTitle($pageName)); ?> - <?php echo APP_NAME; ?></title>

    <!-- CSS -->
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/images/favicon.ico">
    <link rel="apple-touch-icon" href="/assets/images/apple-touch-icon.png">

    <!-- Meta tags for PWA (optional) -->
    <meta name="theme-color" content="#0d1117">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
</head>
<body>
    <!-- Skip to main content for accessibility -->
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <!-- Header -->
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <div class="navbar-brand">
                    <a href="/pages/index.php" class="brand-link">
                        <img src="/assets/images/logo.png" alt="<?php echo APP_NAME; ?>" class="brand-logo" onerror="this.style.display='none'">
                        <span class="brand-text"><?php echo APP_NAME; ?></span>
                    </a>
                </div>

                <button class="navbar-toggle" id="navbar-toggle" aria-label="Toggle navigation">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>

                <div class="navbar-menu" id="navbar-menu">
                    <!-- Main Navigation -->
                    <ul class="nav-links">
                        <?php if (isLoggedIn()): ?>
                            <li>
                                <a href="/pages/index.php" class="nav-link <?php echo $pageName === 'index' ? 'active' : ''; ?>">
                                    <i class="icon-home"></i>
                                    Dashboard
                                </a>
                            </li>
                            <li>
                                <a href="/pages/betting.php" class="nav-link <?php echo $pageName === 'betting' ? 'active' : ''; ?>">
                                    <i class="icon-trophy"></i>
                                    Betting
                                </a>
                            </li>
                            <li>
                                <a href="/pages/chat.php" class="nav-link <?php echo $pageName === 'chat' ? 'active' : ''; ?>">
                                    <i class="icon-chat"></i>
                                    Chat
                                </a>
                            </li>
                            <li>
                                <a href="/pages/history.php" class="nav-link <?php echo $pageName === 'history' ? 'active' : ''; ?>">
                                    <i class="icon-history"></i>
                                    History
                                </a>
                            </li>
                            <li>
                                <a href="/pages/profile.php" class="nav-link <?php echo $pageName === 'profile' ? 'active' : ''; ?>">
                                    <i class="icon-user"></i>
                                    Profile
                                </a>
                            </li>

                            <?php if (isAdmin()): ?>
                                <li class="nav-divider"></li>
                                <li>
                                    <a href="/pages/admin/dashboard.php" class="nav-link nav-admin <?php echo strpos($currentPath, '/admin/') !== false ? 'active' : ''; ?>">
                                        <i class="icon-admin"></i>
                                        Admin
                                    </a>
                                </li>
                            <?php endif; ?>

                        <?php else: ?>
                            <li>
                                <a href="/pages/index.php" class="nav-link <?php echo $pageName === 'index' ? 'active' : ''; ?>">
                                    <i class="icon-home"></i>
                                    Home
                                </a>
                            </li>
                            <li>
                                <a href="/pages/login.php" class="nav-link <?php echo $pageName === 'login' ? 'active' : ''; ?>">
                                    <i class="icon-login"></i>
                                    Login
                                </a>
                            </li>
                            <li>
                                <a href="/pages/register.php" class="nav-link <?php echo $pageName === 'register' ? 'active' : ''; ?>">
                                    <i class="icon-register"></i>
                                    Register
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>

                    <!-- User Menu -->
                    <?php if (isLoggedIn()): ?>
                        <div class="user-menu">
                            <div class="user-info">
                                <img src="<?php echo getUserAvatar(getCurrentUser()['profile_picture'] ?? null); ?>"
                                     alt="User Avatar" class="user-avatar">
                                <div class="user-details">
                                    <span class="user-name"><?php echo e(getCurrentUsername()); ?></span>
                                    <span class="user-coins"><?php echo formatCoins(getCurrentUser()['aura_coins'] ?? 0); ?></span>
                                </div>
                            </div>

                            <div class="user-dropdown">
                                <button class="dropdown-toggle" id="user-dropdown-toggle" aria-label="User menu">
                                    <i class="icon-chevron-down"></i>
                                </button>
                                <div class="dropdown-menu" id="user-dropdown-menu">
                                    <a href="/pages/profile.php" class="dropdown-item">
                                        <i class="icon-user"></i> My Profile
                                    </a>
                                    <a href="/pages/history.php" class="dropdown-item">
                                        <i class="icon-history"></i> Bet History
                                    </a>
                                    <a href="/pages/wallet.php" class="dropdown-item">
                                        <i class="icon-wallet"></i> Wallet
                                    </a>
                                    <?php if (isAdmin()): ?>
                                        <div class="dropdown-divider"></div>
                                        <a href="/pages/admin/dashboard.php" class="dropdown-item">
                                            <i class="icon-admin"></i> Admin Panel
                                        </a>
                                    <?php endif; ?>
                                    <div class="dropdown-divider"></div>
                                    <a href="/pages/logout.php" class="dropdown-item dropdown-logout">
                                        <i class="icon-logout"></i> Logout
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </nav>
        </div>
    </header>

    <!-- Flash Messages -->
    <?php $flashMessages = getFlashMessages(); ?>
    <?php if (!empty($flashMessages)): ?>
        <div class="flash-messages">
            <?php foreach ($flashMessages as $type => $message): ?>
                <?php echo displayMessage($type, $message); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Main Content -->
    <main id="main-content" class="main-content">
        <?php

        // Helper functions for getting page metadata
        function getPageTitle($pageName) {
            $titles = [
                'index' => 'Dashboard',
                'login' => 'Login',
                'register' => 'Register',
                'profile' => 'Profile',
                'betting' => 'Betting',
                'chat' => 'Chat',
                'history' => 'History',
                'wallet' => 'Wallet',
                'admin' => 'Admin Dashboard',
                'users' => 'User Management',
                'matches' => 'Match Management',
                'ads' => 'Advertisement Management',
                'dashboard' => 'Dashboard'
            ];
            return $titles[$pageName] ?? 'Digital Forecast Hub';
        }

        function getPageDescription($pageName) {
            $descriptions = [
                'index' => 'Welcome to Digital Forecast Hub - Your premier betting and digital entertainment platform',
                'login' => 'Login to your Digital Forecast Hub account to access betting, chat, and rewards',
                'register' => 'Create your free Digital Forecast Hub account and start earning coins today',
                'betting' => 'Place bets on sports, crypto, and stock predictions with virtual coins',
                'chat' => 'Connect with other users in our real-time chat community',
                'profile' => 'Manage your profile, track your statistics, and customize your experience',
                'history' => 'View your complete betting and transaction history',
                'wallet' => 'Manage your virtual coins and track your earnings'
            ];
            return $descriptions[$pageName] ?? 'Digital Forecast Hub - Betting and Digital Entertainment Platform';
        }

        function getPageKeywords($pageName) {
            $keywords = [
                'index' => 'betting, virtual coins, digital hub, entertainment, games',
                'login' => 'login, account, digital forecast hub, access',
                'register' => 'register, signup, free account, virtual coins',
                'betting' => 'betting, sports betting, crypto predictions, stock betting, virtual coins',
                'chat' => 'chat, messaging, community, real-time chat',
                'profile' => 'profile, user profile, account settings, customization',
                'history' => 'betting history, transaction history, statistics, records'
            ];
            return $keywords[$pageName] ?? 'digital forecast hub, betting, virtual coins, entertainment';
        }