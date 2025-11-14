<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $user = new User();
    $user->logout();
    setFlashMessage('success', 'You have been logged out successfully.');
} catch (Exception $e) {
    setFlashMessage('error', 'An error occurred during logout. Please try again.');
}

secureRedirect('/pages/login.php');
?>