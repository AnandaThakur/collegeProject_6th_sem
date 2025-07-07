<?php
require_once '../includes/functions.php';
require_once '../config/database.php';

// Check if user is logged in
startSession();
if (!isLoggedIn()) {
    redirect('../login.php');
}

// Check if user is verified
if (!isVerified()) {
    redirect('../waiting.php');
}

// Get user information
$userId = $_SESSION['user_id'];
$userEmail = $_SESSION['email'];
$userRole = $_SESSION['role'];

// Get user details from database
$conn = getDbConnection();
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get total count of activities
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_activity_log WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$totalCount = $result->fetch_assoc()['count'];
$totalPages = ceil($totalCount / $perPage);

// Get user activity log with pagination
$stmt = $conn->prepare("SELECT * FROM user_activity_log WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param("iii", $userId, $perPage, $offset);
$stmt->execute();
$activityResult = $stmt->get_result();
$activities = [];
while ($activity = $activityResult->fetch_assoc()) {
    $activities[] = $activity;
}

// Create user_activity_log table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS user_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log - Auction Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            padding-bottom: 70px; /* Space for the fixed navbar */
            background-color: #f8f9fa;
        }
        .page-header {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
        }
        .activity-log-item {
            padding: 15px;
            margin-bottom: 15px;
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        .activity-log-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .activity-time {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .bottom-nav {
            background-color: white;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
        }
        .nav-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: #6c757d;
            padding: 0.5rem 0;
        }
        .nav-link.active {
            color: #ff6b6b;
        }
        .nav-icon {
            font-size: 1.5rem;
            margin-bottom: 0.2rem;
        }
        .pagination .page-link {
            color: #ff6b6b;
        }
        .pagination .page-item.active .page-link {
            background-color: #ff6b6b;
            border-color: #ff6b6b;
            color: white;
        }
        .btn-back {
            background-color: #ff6b6b;
            border-color: #ff6b6b;
            color: white;
        }
        .btn-back:hover {
            background-color: #ff5252;
            border-color: #ff5252;
            color: white;
        }
        @media (max-width: 576px) {
            .page-header {
                padding: 15px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container mt-4 mb-5">
        <div class="page-header d-flex justify-content-between align-items-center">
            <h1 class="h3 mb-0">Activity Log</h1>
            <a href="profile.php?tab=activity" class="btn btn-back">
                <i class="fas fa-arrow-left me-1"></i> Back to Profile
            </a>
        </div>
        
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Your Activity History</h5>
                    <span class="badge bg-primary"><?php echo $totalCount; ?> activities</span>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($activities)): ?>
                    <p class="text-muted text-center py-3">
                        <i class="fas fa-history fa-2x mb-2"></i><br>
                        No activity found.
                    </p>
                <?php else: ?>
                    <div class="activity-list">
                        <?php foreach ($activities as $activity): ?>
                            <div class="activity-log-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <h6 class="mb-1">
                                        <?php 
                                        $icon = '';
                                        $badgeClass = 'bg-secondary';
                                        switch ($activity['action']) {
                                            case 'login':
                                                $icon = '<i class="fas fa-sign-in-alt text-success me-1"></i>';
                                                $badgeClass = 'bg-success';
                                                break;
                                            case 'logout':
                                                $icon = '<i class="fas fa-sign-out-alt text-warning me-1"></i>';
                                                $badgeClass = 'bg-warning text-dark';
                                                break;
                                            case 'profile_update':
                                                $icon = '<i class="fas fa-user-edit text-primary me-1"></i>';
                                                $badgeClass = 'bg-primary';
                                                break;
                                            case 'password_change':
                                                $icon = '<i class="fas fa-key text-danger me-1"></i>';
                                                $badgeClass = 'bg-danger';
                                                break;
                                            case 'profile_image_update':
                                                $icon = '<i class="fas fa-camera text-info me-1"></i>';
                                                $badgeClass = 'bg-info';
                                                break;
                                            case 'notification_settings_update':
                                                $icon = '<i class="fas fa-bell text-primary me-1"></i>';
                                                $badgeClass = 'bg-primary';
                                                break;
                                            case 'bid_placed':
                                                $icon = '<i class="fas fa-gavel text-success me-1"></i>';
                                                $badgeClass = 'bg-success';
                                                break;
                                            case 'auction_created':
                                                $icon = '<i class="fas fa-plus-circle text-primary me-1"></i>';
                                                $badgeClass = 'bg-primary';
                                                break;
                                            case 'message_sent':
                                                $icon = '<i class="fas fa-comment text-info me-1"></i>';
                                                $badgeClass = 'bg-info';
                                                break;
                                            default:
                                                $icon = '<i class="fas fa-history text-secondary me-1"></i>';
                                        }
                                        ?>
                                        <span class="badge <?php echo $badgeClass; ?> me-2">
                                            <?php echo ucwords(str_replace('_', ' ', $activity['action'])); ?>
                                        </span>
                                    </h6>
                                    <span class="activity-time"><?php echo formatDate($activity['created_at'], 'M d, Y H:i'); ?></span>
                                </div>
                                <p class="mb-1"><?php echo htmlspecialchars($activity['description']); ?></p>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <small class="text-muted">
                                        <?php if (!empty($activity['ip_address'])): ?>
                                            <i class="fas fa-network-wired me-1"></i> IP: <?php echo htmlspecialchars($activity['ip_address']); ?>
                                        <?php endif; ?>
                                    </small>
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i> <?php echo timeAgo($activity['created_at']); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <nav aria-label="Activity log pagination">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Bottom Navigation -->
    <nav class="fixed-bottom bottom-nav">
        <div class="row text-center">
            <div class="col-3">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-home nav-icon"></i>
                    <span class="nav-text small">Home</span>
                </a>
            </div>
            <div class="col-3">
                <a href="auctions.php" class="nav-link">
                    <i class="fas fa-gavel nav-icon"></i>
                    <span class="nav-text small">Auctions</span>
                </a>
            </div>
            <div class="col-3">
                <a href="my-bids.php" class="nav-link">
                    <i class="fas fa-bookmark nav-icon"></i>
                    <span class="nav-text small">My Bids</span>
                </a>
            </div>
            <div class="col-3">
                <a href="profile.php" class="nav-link active">
                    <i class="fas fa-user nav-icon"></i>
                    <span class="nav-text small">Profile</span>
                </a>
            </div>
        </div>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Helper function to format time ago
        function timeAgo(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const seconds = Math.floor((now - date) / 1000);
            
            let interval = Math.floor(seconds / 31536000);
            if (interval >= 1) {
                return interval + " year" + (interval === 1 ? "" : "s") + " ago";
            }
            
            interval = Math.floor(seconds / 2592000);
            if (interval >= 1) {
                return interval + " month" + (interval === 1 ? "" : "s") + " ago";
            }
            
            interval = Math.floor(seconds / 86400);
            if (interval >= 1) {
                return interval + " day" + (interval === 1 ? "" : "s") + " ago";
            }
            
            interval = Math.floor(seconds / 3600);
            if (interval >= 1) {
                return interval + " hour" + (interval === 1 ? "" : "s") + " ago";
            }
            
            interval = Math.floor(seconds / 60);
            if (interval >= 1) {
                return interval + " minute" + (interval === 1 ? "" : "s") + " ago";
            }
            
            return Math.floor(seconds) + " second" + (seconds === 1 ? "" : "s") + " ago";
        }
    </script>
</body>
</html>
