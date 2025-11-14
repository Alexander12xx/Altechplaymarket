<?php
/**
 * Application Constants
 */

// Transaction types
define('TRANSACTION_AD_WATCH', 'AD_WATCH');
define('TRANSACTION_BET_WIN', 'BET_WIN');
define('TRANSACTION_BET_LOSS', 'BET_LOSS');
define('TRANSACTION_BET_PLACED', 'BET_PLACED');
define('TRANSACTION_DEPOSIT', 'DEPOSIT');
define('TRANSACTION_WITHDRAWAL', 'WITHDRAWAL');
define('TRANSACTION_REFUND', 'REFUND');
define('TRANSACTION_BONUS', 'BONUS');

// User statuses
define('USER_STATUS_ACTIVE', 'active');
define('USER_STATUS_SUSPENDED', 'suspended');
define('USER_STATUS_BANNED', 'banned');

// Match statuses
define('MATCH_STATUS_UPCOMING', 'Upcoming');
define('MATCH_STATUS_LIVE', 'Live');
define('MATCH_STATUS_SETTLED', 'Settled');
define('MATCH_STATUS_CANCELLED', 'Cancelled');

// Match categories
define('CATEGORY_SPORTS', 'Sports');
define('CATEGORY_E_SPORTS', 'E-Sports');
define('CATEGORY_CRYPTO', 'Trading');
define('CATEGORY_STOCKS', 'Market');
define('CATEGORY_CUSTOM', 'Custom');

// Bet outcomes
define('BET_OUTCOME_TEAM_A', 'team_a');
define('BET_OUTCOME_TEAM_B', 'team_b');

// Bet statuses
define('BET_STATUS_PENDING', 'pending');
define('BET_STATUS_WON', 'won');
define('BET_STATUS_LOST', 'lost');

// Ad types
define('AD_TYPE_IMAGE', 'Image');
define('AD_TYPE_VIDEO', 'Video');
define('AD_TYPE_INTERACTIVE', 'Interactive');

// Report statuses
define('REPORT_STATUS_PENDING', 'pending');
define('REPORT_STATUS_REVIEWED', 'reviewed');
define('REPORT_STATUS_RESOLVED', 'resolved');

// Content types for reporting
define('CONTENT_TYPE_CHAT', 'chat');
define('CONTENT_TYPE_USER', 'user');
define('CONTENT_TYPE_BET', 'bet');

// Transaction statuses
define('TRANSACTION_STATUS_PENDING', 'pending');
define('TRANSACTION_STATUS_COMPLETED', 'completed');
define('TRANSACTION_STATUS_FAILED', 'failed');
define('TRANSACTION_STATUS_CANCELLED', 'cancelled');

// User activity actions
define('ACTION_LOGIN', 'login');
define('ACTION_LOGOUT', 'logout');
define('ACTION_REGISTER', 'register');
define('ACTION_BET_PLACE', 'bet_place');
define('ACTION_CHAT_MESSAGE', 'chat_message');
define('ACTION_AD_WATCH', 'ad_watch');
define('ACTION_PROFILE_UPDATE', 'profile_update');

// Admin action types
define('ADMIN_ACTION_USER_CREATE', 'user_create');
define('ADMIN_ACTION_USER_UPDATE', 'user_update');
define('ADMIN_ACTION_USER_DELETE', 'user_delete');
define('ADMIN_ACTION_USER_SUSPEND', 'user_suspend');
define('ADMIN_ACTION_USER_BAN', 'user_ban');
define('ADMIN_ACTION_MATCH_CREATE', 'match_create');
define('ADMIN_ACTION_MATCH_UPDATE', 'match_update');
define('ADMIN_ACTION_MATCH_DELETE', 'match_delete');
define('ADMIN_ACTION_MATCH_SETTLE', 'match_settle');
define('ADMIN_ACTION_AD_CREATE', 'ad_create');
define('ADMIN_ACTION_AD_UPDATE', 'ad_update');
define('ADMIN_ACTION_AD_DELETE', 'ad_delete');
define('ADMIN_ACTION_CHAT_DELETE', 'chat_delete');
define('ADMIN_ACTION_CHAT_MODERATE', 'chat_moderate');

// File paths
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('AVATAR_PATH', UPLOAD_PATH . 'avatars/');
define('AD_PATH', UPLOAD_PATH . 'ads/');

// API endpoints
define('API_SPORTS_ENDPOINT', 'https://v3.football.api-sports.io');
define('API_CRYPTO_ENDPOINT', 'https://api.freecryptoapi.com/v1');
define('API_STOCKS_ENDPOINT', 'https://www.alphavantage.co/query');

// Default values
define('DEFAULT_AVATAR', 'default-avatar.png');
define('MAX_BET_AMOUNT', 1000.00);
define('MIN_BET_AMOUNT', 0.50);
define('MAX_CHAT_LENGTH', 500);
define('MAX_USERNAME_LENGTH', 50);
define('MAX_EMAIL_LENGTH', 100);

// Pagination
define('DEFAULT_PAGE_SIZE', 20);
define('MAX_PAGE_SIZE', 100);

// Session security
define('SESSION_NAME', 'digital_forecast_session');
define('SESSION_SECURE', true); // Set to false if not using HTTPS
define('SESSION_HTTPONLY', true);
define('SESSION_SAMESITE', 'Strict');

// Cache keys
define('CACHE_KEY_MATCHES', 'matches_');
define('CACHE_KEY_API_RESPONSES', 'api_');
define('CACHE_KEY_USER_STATS', 'user_stats_');

// Error messages
define('ERROR_INVALID_LOGIN', 'Invalid email or password');
define('ERROR_ACCOUNT_LOCKED', 'Account temporarily locked due to multiple failed attempts');
define('ERROR_INSUFFICIENT_COINS', 'Insufficient coins for this bet');
define('ERROR_INVALID_BET_AMOUNT', 'Invalid bet amount');
define('ERROR_MATCH_NOT_FOUND', 'Match not found');
define('ERROR_BET_CLOSED', 'Betting is closed for this match');
define('ERROR_SESSION_EXPIRED', 'Your session has expired. Please log in again');
define('ERROR_PERMISSION_DENIED', 'You do not have permission to perform this action');
define('ERROR_RATE_LIMIT_EXCEEDED', 'Rate limit exceeded. Please try again later');

// Success messages
define('SUCCESS_REGISTRATION', 'Registration successful! You have been awarded 10 starting coins.');
define('SUCCESS_LOGIN', 'Login successful!');
define('SUCCESS_BET_PLACED', 'Bet placed successfully!');
define('SUCCESS_PROFILE_UPDATED', 'Profile updated successfully!');
define('SUCCESS_PASSWORD_CHANGED', 'Password changed successfully!');
define('SUCCESS_COINS_AWARDED', 'Coins awarded successfully!');
?>