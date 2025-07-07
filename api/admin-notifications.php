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
        case 'get_notifications':
            handleGetNotifications();
            break;
        case 'get_notification_details':
            handleGetNotificationDetails();
            break;
        case 'delete_notification':
            handleDeleteNotification();
            break;
        case 'get_notification_stats':
            handleGetNotificationStats();
            break;
        default:
            jsonResponse(false, 'Invalid action');
    }
} else {
    jsonResponse(false, 'Invalid request method');
}

// Handle getting notifications
function handleGetNotifications() {
    $conn = getDbConnection();
    
    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
    $perPage = isset($_POST['per_page']) ? (int)$_POST['per_page'] : 10;
    $offset = ($page - 1) * $perPage;
    
    // Build query based on filters
    $query = "SELECT n.*, u.email as user_email, u.role as user_role 
              FROM notifications n 
              JOIN users u ON n.user_id = u.id";
    
    $whereConditions = [];
    $params = [];
    $types = '';
    
    // Apply filters
    if (isset($_POST['type']) && !empty($_POST['type'])) {
        $whereConditions[] = "n.type = ?";
        $params[] = $_POST['type'];
        $types .= 's';
    }
    
    if (isset($_POST['read_status'])) {
        if ($_POST['read_status'] === 'read') {
            $whereConditions[] = "n.is_read = 1";
        } elseif ($_POST['read_status'] === 'unread') {
            $whereConditions[] = "n.is_read = 0";
        }
    }
    
    if (isset($_POST['date_range']) && !empty($_POST['date_range'])) {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $last7days = date('Y-m-d', strtotime('-7 days'));
        $last30days = date('Y-m-d', strtotime('-30 days'));
        
        switch ($_POST['date_range']) {
            case 'today':
                $whereConditions[] = "DATE(n.created_at) = ?";
                $params[] = $today;
                $types .= 's';
                break;
            case 'yesterday':
                $whereConditions[] = "DATE(n.created_at) = ?";
                $params[] = $yesterday;
                $types .= 's';
                break;
            case 'last7days':
                $whereConditions[] = "DATE(n.created_at) >= ?";
                $params[] = $last7days;
                $types .= 's';
                break;
            case 'last30days':
                $whereConditions[] = "DATE(n.created_at) >= ?";
                $params[] = $last30days;
                $types .= 's';
                break;
        }
    }
    
    if (isset($_POST['user']) && !empty($_POST['user'])) {
        $user = $_POST['user'];
        
        // Check if user input is an ID or email
        if (is_numeric($user)) {
            $whereConditions[] = "n.user_id = ?";
            $params[] = (int)$user;
            $types .= 'i';
        } else {
            $whereConditions[] = "u.email LIKE ?";
            $params[] = "%$user%";
            $types .= 's';
        }
    }
    
    // Add WHERE clause if there are conditions
    if (!empty($whereConditions)) {
        $query .= " WHERE " . implode(" AND ", $whereConditions);
    }
    
    // Add ORDER BY and LIMIT
    $query .= " ORDER BY n.created_at DESC LIMIT ?, ?";
    $params[] = $offset;
    $params[] = $perPage;
    $types .= 'ii';
    
    // Prepare and execute query
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $row['formatted_date'] = formatDate($row['created_at']);
        $row['type_badge'] = getNotificationTypeBadge($row['type']);
        $row['icon_class'] = getNotificationIconClass($row['type']);
        $notifications[] = $row;
    }
    
    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM notifications n JOIN users u ON n.user_id = u.id";
    
    if (!empty($whereConditions)) {
        $countQuery .= " WHERE " . implode(" AND ", $whereConditions);
    }
    
    $countStmt = $conn->prepare($countQuery);
    
    if (!empty($params) && count($params) > 2) {
        // Remove the last two parameters (offset and limit)
        $countParams = array_slice($params, 0, -2);
        $countTypes = substr($types, 0, -2);
        
        if (!empty($countParams)) {
            $countStmt->bind_param($countTypes, ...$countParams);
        }
    }
    
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalCount = $countResult->fetch_assoc()['total'];
    
    jsonResponse(true, 'Notifications retrieved successfully', [
        'notifications' => $notifications,
        'total_count' => $totalCount,
        'has_more' => ($offset + $perPage) < $totalCount
    ]);
}

// Handle getting notification details
function handleGetNotificationDetails() {
    $notificationId = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
    
    if ($notificationId <= 0) {
        jsonResponse(false, 'Invalid notification ID');
    }
    
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT n.*, u.email as user_email, u.role as user_role 
                           FROM notifications n 
                           JOIN users u ON n.user_id = u.id 
                           WHERE n.id = ?");
    $stmt->bind_param("i", $notificationId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        jsonResponse(false, 'Notification not found');
    }
    
    $notification = $result->fetch_assoc();
    $notification['formatted_date'] = formatDate($notification['created_at']);
    $notification['type_badge'] = getNotificationTypeBadge($notification['type']);
    $notification['icon_class'] = getNotificationIconClass($notification['type']);
    
    jsonResponse(true, 'Notification details retrieved successfully', ['notification' => $notification]);
}

// Handle deleting a notification
function handleDeleteNotification() {
    $notificationId = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
    
    if ($notificationId <= 0) {
        jsonResponse(false, 'Invalid notification ID');
    }
    
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ?");
    $stmt->bind_param("i", $notificationId);
    
    if ($stmt->execute()) {
        jsonResponse(true, 'Notification deleted successfully');
    } else {
        jsonResponse(false, 'Failed to delete notification: ' . $conn->error);
    }
}

// Handle getting notification stats
function handleGetNotificationStats() {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT 
        COUNT(*) as total_notifications,
        COUNT(CASE WHEN is_read = 0 THEN 1 END) as unread_notifications,
        COUNT(CASE WHEN type = 'info' THEN 1 END) as info_notifications,
        COUNT(CASE WHEN type = 'success' THEN 1 END) as success_notifications,
        COUNT(CASE WHEN type = 'warning' THEN 1 END) as warning_notifications,
        COUNT(CASE WHEN type = 'error' THEN 1 END) as error_notifications
        FROM notifications");
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    
    jsonResponse(true, 'Notification stats retrieved successfully', ['stats' => $stats]);
}
?>
