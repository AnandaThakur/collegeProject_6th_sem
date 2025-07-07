<?php
require_once 'includes/functions.php';

// Start session if not already started
startSession();

// Log the logout action
if (isset($_SESSION['user_id']) && isset($_SESSION['email'])) {
    $userId = $_SESSION['user_id'];
    $userEmail = $_SESSION['email'];
    error_log("User logged out - ID: $userId, Email: $userEmail");
}

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit;
?>
