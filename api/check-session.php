<?php
// Session check endpoint
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in the response

try {
    // Start session if not already started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if user is logged in
    $isLoggedIn = isset($_SESSION['user_id']);
    $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    
    // Get session data
    $sessionData = [
        'session_id' => session_id(),
        'user_id' => $_SESSION['user_id'] ?? null,
        'email' => $_SESSION['email'] ?? null,
        'role' => $_SESSION['role'] ?? null,
        'is_logged_in' => $isLoggedIn,
        'is_admin' => $isAdmin,
        'session_started_at' => $_SESSION['session_started_at'] ?? null,
        'last_activity' => $_SESSION['last_activity'] ?? null
    ];
    
    // Return session data
    echo json_encode([
        'status' => 'success',
        'message' => $isLoggedIn ? 'User is logged in' : 'User is not logged in',
        'session' => $sessionData,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    // Log the error
    error_log("Error in check-session.php: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
