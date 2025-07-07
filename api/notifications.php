<?php
require_once '../includes/functions.php';
require_once '../config/database.php';

// Check if user is logged in
startSession();
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get user ID
$userId = $_SESSION['user_id'];

// Handle AJAX requests
$requestData = json_decode(file_get_contents('php://input'), true);

if (isset($requestData['action'])) {
    $conn = getDbConnection();
    
    switch ($requestData['action']) {
        case 'mark_read':
            if (isset($requestData['notification_id'])) {
                $notificationId = $requestData['notification_id'];
                
                // Verify the notification belongs to the user
                $stmt = $conn->prepare("SELECT * FROM notifications WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $notificationId, $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    // Mark notification as read
                    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1, updated_at = NOW() WHERE id = ?");
                    $stmt->bind_param("i", $notificationId);
                    
                    if ($stmt->execute()) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
                    } else {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Error marking notification as read']);
                    }
                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Notification not found or does not belong to you']);
                }
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Notification ID is required']);
            }
            break;
            
        case 'get_unread_count':
            // Get unread notification count
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $unreadCount = $result->fetch_assoc()['count'];
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'count' => $unreadCount]);
            break;
            
        case 'get_recent':
            // Get recent notifications
            $limit = isset($requestData['limit']) ? (int)$requestData['limit'] : 5;
            
            $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
            $stmt->bind_param("ii", $userId, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $notifications = [];
            while ($notification = $result->fetch_assoc()) {
                $notifications[] = [
                    'id' => $notification['id'],
                    'title' => $notification['title'],
                    'message' => $notification['message'],
                    'is_read' => (bool)$notification['is_read'],
                    'created_at' => $notification['created_at'],
                    'time_ago' => timeAgo($notification['created_at'])
                ];
            }
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'notifications' => $notifications]);
            break;
            
        default:
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Action is required']);
}

// Helper function to format time ago
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 31536000) {
        $months = floor($diff / 2592000);
        return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
    } else {
        $years = floor($diff / 31536000);
        return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
    }
}
?>
