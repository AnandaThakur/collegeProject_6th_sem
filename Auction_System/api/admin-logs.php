<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/notification-functions.php';

// Start session
startSession();

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    jsonResponse(false, 'Unauthorized access');
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    switch ($action) {
        case 'get_recent_notifications':
            handleGetRecentNotifications();
            break;
        default:
            jsonResponse(false, 'Invalid action');
    }
} else {
    jsonResponse(false, 'Invalid request method');
}

// Handle getting recent notifications
function handleGetRecentNotifications() {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT n.id, n.title, n.message, n.type, n.created_at, 
                           u.email as recipient_email, u.role as recipient_role
                           FROM notifications n 
                           JOIN users u ON n.user_id = u.id 
                           ORDER BY n.created_at DESC LIMIT 10");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'message' => $row['message'],
            'type' => $row['type'],
            'sent_at' => formatDate($row['created_at']),
            'recipient' => $row['recipient_email'] . ' (' . ucfirst($row['recipient_role']) . ')'
        ];
    }
    
    jsonResponse(true, 'Recent notifications retrieved successfully', ['notifications' => $notifications]);
}
?>
