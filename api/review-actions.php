<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isAdmin()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$conn = getDbConnection();
$response = ['success' => false, 'message' => 'Invalid request'];

// Helper function to log admin actions
function logReviewModeration($admin_id, $action, $target_id, $details) {
    global $conn;
    
    // Check if admin_logs table exists
    $result = $conn->query("SHOW TABLES LIKE 'admin_logs'");
    if ($result->num_rows == 0) {
        // Create admin_logs table if it doesn't exist
        $conn->query("CREATE TABLE IF NOT EXISTS admin_logs (
            log_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            admin_id INT UNSIGNED NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            target_id VARCHAR(50) NOT NULL,
            details TEXT NULL,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (log_id),
            KEY admin_id (admin_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    
    $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action_type, target_id, details) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $admin_id, $action, $target_id, $details);
    $stmt->execute();
}

// Get reviews with pagination
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'getReviews') {
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = 10; // Number of reviews per page
    $offset = ($page - 1) * $limit;
    
    // Build the query with filters
    $query = "SELECT r.review_id, r.review_content, r.rating, r.timestamp, r.status,
              a.id as product_id, a.title as product_title,
              u.id as user_id, u.email as user_email, 
              CASE WHEN u.status = 'deactivated' THEN 1 ELSE 0 END as is_banned
              FROM product_reviews r
              LEFT JOIN auctions a ON r.product_id = a.id
              LEFT JOIN users u ON r.user_id = u.id
              WHERE 1=1";
    
    $countQuery = "SELECT COUNT(*) as total FROM product_reviews r WHERE 1=1";
    $params = [];
    $types = "";
    
    // Apply filters if provided
    if (!empty($_GET['product_id'])) {
        $query .= " AND r.product_id = ?";
        $countQuery .= " AND r.product_id = ?";
        $params[] = intval($_GET['product_id']);
        $types .= "i";
    }
    
    if (isset($_GET['status']) && $_GET['status'] !== '') {
        $query .= " AND r.status = ?";
        $countQuery .= " AND r.status = ?";
        $params[] = $_GET['status'];
        $types .= "s";
    }
    
    if (!empty($_GET['search'])) {
        $searchTerm = '%' . $_GET['search'] . '%';
        $query .= " AND r.review_content LIKE ?";
        $countQuery .= " AND r.review_content LIKE ?";
        $params[] = $searchTerm;
        $types .= "s";
    }
    
    // Add order by and limit
    $query .= " ORDER BY r.timestamp DESC LIMIT ?, ?";
    $params[] = $offset;
    $params[] = $limit;
    $types .= "ii";
    
    // Execute count query
    $countStmt = $conn->prepare($countQuery);
    if (!empty($params) && count($params) > 0) {
        // Remove the last two parameters which are for pagination
        $countParams = array_slice($params, 0, -2);
        if (!empty($countParams)) {
            $countTypes = substr($types, 0, -2);
            $countStmt->bind_param($countTypes, ...$countParams);
        }
    }
    $countStmt->execute();
    $totalResult = $countStmt->get_result();
    $totalRow = $totalResult->fetch_assoc();
    $total = $totalRow['total'];
    
    // Execute main query
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $reviews = [];
    while ($row = $result->fetch_assoc()) {
        // Get flagged words to highlight in the review
        $flaggedWordsQuery = "SELECT word FROM flagged_words";
        $flaggedWordsResult = $conn->query($flaggedWordsQuery);
        $flaggedWords = [];
        
        if ($flaggedWordsResult) {
            while ($wordRow = $flaggedWordsResult->fetch_assoc()) {
                $flaggedWords[] = $wordRow['word'];
            }
        }
        
        // Highlight flagged words in the review
        $review = htmlspecialchars($row['review_content']);
        foreach ($flaggedWords as $word) {
            $review = preg_replace(
                '/\b(' . preg_quote($word, '/') . ')\b/i',
                '<span class="flagged-word">$1</span>',
                $review
            );
        }
        
        $row['review_content'] = $review;
        $reviews[] = $row;
    }
    
    // Calculate pagination
    $totalPages = ceil($total / $limit);
    
    $response = [
        'success' => true,
        'reviews' => $reviews,
        'pagination' => [
            'total' => $total,
            'per_page' => $limit,
            'current_page' => $page,
            'last_page' => $totalPages
        ]
    ];
}

// Approve a review
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve' && isset($_POST['review_id'])) {
    $reviewId = intval($_POST['review_id']);
    
    $stmt = $conn->prepare("UPDATE product_reviews SET status = 'approved' WHERE review_id = ?");
    $stmt->bind_param("i", $reviewId);
    
    if ($stmt->execute()) {
        logReviewModeration($_SESSION['user_id'], 'approved review', $reviewId, "Review ID: $reviewId");
        
        $response = [
            'success' => true,
            'message' => 'Review approved successfully'
        ];
    } else {
        $response = [
            'success' => false,
            'message' => 'Failed to approve review'
        ];
    }
}

// Delete a review (soft delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['review_id'])) {
    $reviewId = intval($_POST['review_id']);
    
    $stmt = $conn->prepare("UPDATE product_reviews SET status = 'deleted' WHERE review_id = ?");
    $stmt->bind_param("i", $reviewId);
    
    if ($stmt->execute()) {
        logReviewModeration($_SESSION['user_id'], 'deleted review', $reviewId, "Review ID: $reviewId");
        
        $response = [
            'success' => true,
            'message' => 'Review deleted successfully'
        ];
    } else {
        $response = [
            'success' => false,
            'message' => 'Failed to delete review'
        ];
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
