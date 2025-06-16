<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/notification-functions.php';

// Check if user is logged in and is admin
startSession();
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php?admin=true');
}

$conn = getDbConnection();
$message = '';
$alertType = '';

// Get all users for the dropdown
$users = [];
$result = $conn->query("SELECT id, email, role FROM users ORDER BY role, email");
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipientType = $_POST['recipient_type'] ?? '';
    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $userRole = $_POST['user_role'] ?? '';
    $title = $_POST['title'] ?? '';
    $notificationMessage = $_POST['message'] ?? '';
    $type = $_POST['type'] ?? 'info';
    
    if (empty($title) || empty($notificationMessage)) {
        $message = 'Title and message are required.';
        $alertType = 'danger';
    } else {
        $success = false;
        
        if ($recipientType === 'user' && $userId > 0) {
            // Send to specific user
            $success = createNotification($userId, $title, $notificationMessage, $type) !== false;
            $message = $success ? 'Notification sent successfully to user.' : 'Failed to send notification.';
        } else if ($recipientType === 'role' && !empty($userRole)) {
            // Send to all users with specific role
            $count = sendNotificationToRole($userRole, $title, $notificationMessage, $type);
            $success = $count > 0;
            $message = $success ? "Notification sent successfully to {$count} users with role '{$userRole}'." : 'Failed to send notification.';
        } else if ($recipientType === 'all') {
            // Send to all users
            $stmt = $conn->prepare("SELECT id FROM users");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $count = 0;
            while ($row = $result->fetch_assoc()) {
                if (createNotification($row['id'], $title, $notificationMessage, $type) !== false) {
                    $count++;
                }
            }
            
            $success = $count > 0;
            $message = $success ? "Notification sent successfully to {$count} users." : 'Failed to send notification.';
        } else {
            $message = 'Invalid recipient selection.';
            $alertType = 'danger';
        }
        
        if ($success) {
            $alertType = 'success';
        } else {
            $alertType = 'danger';
        }
    }
}

// Get user counts for stats
$stmt = $conn->prepare("SELECT 
    COUNT(CASE WHEN role = 'buyer' THEN 1 END) as total_buyers,
    COUNT(CASE WHEN role = 'seller' THEN 1 END) as total_sellers
    FROM users WHERE role != 'admin'");
$stmt->execute();
$userStats = $stmt->get_result()->fetch_assoc();

// Get notification stats
$notificationStats = [
    'total' => 0,
    'unread' => 0,
    'today' => 0
];

$result = $conn->query("SELECT COUNT(*) as total FROM notifications");
if ($result && $row = $result->fetch_assoc()) {
    $notificationStats['total'] = $row['total'];
}

$result = $conn->query("SELECT COUNT(*) as unread FROM notifications WHERE is_read = 0");
if ($result && $row = $result->fetch_assoc()) {
    $notificationStats['unread'] = $row['unread'];
}

$result = $conn->query("SELECT COUNT(*) as today FROM notifications WHERE DATE(created_at) = CURDATE()");
if ($result && $row = $result->fetch_assoc()) {
    $notificationStats['today'] = $row['today'];
}

// Debug information
debug_log("Admin send notification page accessed by: " . $_SESSION['email']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Notification - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="admin-sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <svg class="logo-icon" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                        <path d="M30 30 L70 30 L70 70 L30 70 Z" stroke="#FFF" stroke-width="6" fill="none" />
                        <path d="M20 20 L80 20 L80 80 L20 80 Z" stroke="#FFF" stroke-width="6" fill="none" transform="rotate(45 50 50)" />
                    </svg>
                    <span>Auction Admin</span>
                </div>
            </div>
            <div class="sidebar-menu">
                <ul>
                    <li>
                        <a href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="users.php">
                            <i class="fas fa-users"></i>
                            <span>User Management</span>
                        </a>
                    </li>
                    <li>
                        <a href="auctions.php">
                            <i class="fas fa-gavel"></i>
                            <span>Auction Management</span>
                        </a>
                    </li>
                    <li>
                        <a href="bids.php">
                            <i class="fas fa-chart-line"></i>
                            <span>Bid Monitoring</span>
                        </a>
                    </li>
                    <li>
                        <a href="notifications.php">
                            <i class="fas fa-bell"></i>
                            <span>Notifications</span>
                        </a>
                    </li>
                    <li class="active">
                        <a href="send-notification.php">
                            <i class="fas fa-paper-plane"></i>
                            <span>Send Notification</span>
                        </a>
                    </li>
                    <li>
                        <a href="wallet-management.php">
                            <i class="fas fa-wallet"></i>
                            <span>Wallet Management</span>
                        </a>
                    </li>
                    <li>
                        <a href="reports.php">
                            <i class="fas fa-chart-bar"></i>
                            <span>Reports & Logs</span>
                        </a>
                    </li>
                    <li>
                        <a href="chat-monitoring.php">
                            <i class="fas fa-comments"></i>
                            <span>Chat Monitoring</span>
                        </a>
                    </li>
                    <li>
                        <a href="system-settings.php">
                            <i class="fas fa-cog"></i>
                            <span>System Settings</span>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="sidebar-footer">
                <a href="#" id="logout-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="admin-content">
            <!-- Top Navbar -->
            <div class="admin-navbar">
                <div class="menu-toggle">
                    <i class="fas fa-bars"></i>
                </div>
                <div class="navbar-right">
                    <div class="notifications">
                        <i class="fas fa-bell"></i>
                        <span class="badge"><?php echo $notificationStats['unread']; ?></span>
                    </div>
                    <div class="admin-profile">
                        <span><?php echo $_SESSION['email']; ?></span>
                        <i class="fas fa-user-circle"></i>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="content-area">
                <div class="content-header">
                    <h1>Send Notification</h1>
                    <div class="date-range">
                        <i class="fas fa-calendar"></i>
                        <span><?php echo date('F d, Y'); ?></span>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="stats-cards">
                    <div class="stat-card">
                        <div class="stat-card-content">
                            <div class="stat-card-info">
                                <h5>Total Notifications</h5>
                                <h2><?php echo $notificationStats['total']; ?></h2>
                            </div>
                            <div class="stat-card-icon">
                                <i class="fas fa-bell"></i>
                            </div>
                        </div>
                        <div class="stat-card-footer">
                            <span>All time notifications</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-content">
                            <div class="stat-card-info">
                                <h5>Unread Notifications</h5>
                                <h2><?php echo $notificationStats['unread']; ?></h2>
                            </div>
                            <div class="stat-card-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                        </div>
                        <div class="stat-card-footer">
                            <span>Pending to be read</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-content">
                            <div class="stat-card-info">
                                <h5>Today's Notifications</h5>
                                <h2><?php echo $notificationStats['today']; ?></h2>
                            </div>
                            <div class="stat-card-icon">
                                <i class="fas fa-calendar-day"></i>
                            </div>
                        </div>
                        <div class="stat-card-footer">
                            <span>Sent today</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-content">
                            <div class="stat-card-info">
                                <h5>Total Recipients</h5>
                                <h2><?php echo ($userStats['total_buyers'] + $userStats['total_sellers']); ?></h2>
                            </div>
                            <div class="stat-card-icon">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                        <div class="stat-card-footer">
                            <span>Potential recipients</span>
                        </div>
                    </div>
                </div>

                <!-- Notification Form Section -->
                <div class="recent-section">
                    <div class="section-header">
                        <h4>Create New Notification</h4>
                        <a href="notifications.php" class="view-all">View All Notifications</a>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?php echo $alertType; ?> alert-dismissible fade show" role="alert">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" id="notificationForm">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="recipient_type" class="form-label">Recipient Type <span class="text-danger">*</span></label>
                                    <select class="form-select" id="recipient_type" name="recipient_type" required>
                                        <option value="">Select recipient type</option>
                                        <option value="user">Specific User</option>
                                        <option value="role">User Role</option>
                                        <option value="all">All Users</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="type" class="form-label">Notification Type <span class="text-danger">*</span></label>
                                    <select class="form-select" id="type" name="type" required>
                                        <option value="info">Info</option>
                                        <option value="success">Success</option>
                                        <option value="warning">Warning</option>
                                        <option value="error">Error</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3 recipient-option" id="user_option" style="display: none;">
                                <label for="user_id" class="form-label">Select User <span class="text-danger">*</span></label>
                                <select class="form-select" id="user_id" name="user_id">
                                    <option value="">Select user</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>">
                                            <?php echo htmlspecialchars($user['email'] . ' - ' . ucfirst($user['role'])); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a user.</div>
                            </div>
                            
                            <div class="mb-3 recipient-option" id="role_option" style="display: none;">
                                <label for="user_role" class="form-label">Select Role <span class="text-danger">*</span></label>
                                <select class="form-select" id="user_role" name="user_role">
                                    <option value="">Select role</option>
                                    <option value="admin">Admin</option>
                                    <option value="seller">Seller</option>
                                    <option value="buyer">Buyer</option>
                                </select>
                                <div class="invalid-feedback">Please select a role.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" required>
                                <div class="invalid-feedback">Please enter a title.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="message" class="form-label">Message <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="message" name="message" rows="4" required></textarea>
                                <div class="invalid-feedback">Please enter a message.</div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="reset" class="btn btn-secondary me-md-2">Reset</button>
                                <button type="submit" class="btn btn-primary">Send Notification</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Recent Notifications -->
                <div class="recent-section">
                    <div class="section-header">
                        <h4>Recent Notifications</h4>
                        <a href="notifications.php" class="view-all">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Type</th>
                                    <th>Recipient</th>
                                    <th>Sent On</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $recentNotifications = $conn->query("
                                    SELECT n.id, n.title, n.type, n.is_read, n.created_at, u.email as recipient 
                                    FROM notifications n 
                                    JOIN users u ON n.user_id = u.id 
                                    ORDER BY n.created_at DESC LIMIT 5
                                ");
                                
                                if ($recentNotifications && $recentNotifications->num_rows > 0) {
                                    while ($notification = $recentNotifications->fetch_assoc()) {
                                        echo '<tr>';
                                        echo '<td>' . $notification['id'] . '</td>';
                                        echo '<td>' . htmlspecialchars($notification['title']) . '</td>';
                                        echo '<td>' . getNotificationTypeBadge($notification['type']) . '</td>';
                                        echo '<td>' . htmlspecialchars($notification['recipient']) . '</td>';
                                        echo '<td>' . date('M d, Y H:i', strtotime($notification['created_at'])) . '</td>';
                                        echo '<td>' . ($notification['is_read'] ? '<span class="badge bg-success">Read</span>' : '<span class="badge bg-warning">Unread</span>') . '</td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="6" class="text-center">No notifications found</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Toggle sidebar
            $('.menu-toggle').click(function() {
                $('.admin-container').toggleClass('sidebar-collapsed');
            });

            // Handle logout
            $('#logout-link').click(function(e) {
                e.preventDefault();
                
                $.ajax({
                    url: '../api/auth.php',
                    type: 'POST',
                    data: {
                        action: 'logout'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            window.location.href = response.data.redirect;
                        }
                    }
                });
            });
            
            // Show/hide recipient options based on selection
            $('#recipient_type').change(function() {
                const recipientType = $(this).val();
                $('.recipient-option').hide();
                
                if (recipientType === 'user') {
                    $('#user_option').show();
                    $('#user_id').prop('required', true);
                    $('#user_role').prop('required', false);
                } else if (recipientType === 'role') {
                    $('#role_option').show();
                    $('#user_id').prop('required', false);
                    $('#user_role').prop('required', true);
                } else {
                    $('#user_id').prop('required', false);
                    $('#user_role').prop('required', false);
                }
            });
            
            // Form validation
            $('#notificationForm').submit(function(e) {
                let isValid = true;
                const recipientType = $('#recipient_type').val();
                
                if (recipientType === 'user' && $('#user_id').val() === '') {
                    $('#user_id').addClass('is-invalid');
                    isValid = false;
                } else {
                    $('#user_id').removeClass('is-invalid');
                }
                
                if (recipientType === 'role' && $('#user_role').val() === '') {
                    $('#user_role').addClass('is-invalid');
                    isValid = false;
                } else {
                    $('#user_role').removeClass('is-invalid');
                }
                
                if ($('#title').val() === '') {
                    $('#title').addClass('is-invalid');
                    isValid = false;
                } else {
                    $('#title').removeClass('is-invalid');
                }
                
                if ($('#message').val() === '') {
                    $('#message').addClass('is-invalid');
                    isValid = false;
                } else {
                    $('#message').removeClass('is-invalid');
                }
                
                if (!isValid) {
                    e.preventDefault();
                }
            });
        });

        // Helper function for notification type badge
        function getNotificationTypeBadge(type) {
            switch(type) {
                case 'info':
                    return '<span class="badge bg-info">Info</span>';
                case 'success':
                    return '<span class="badge bg-success">Success</span>';
                case 'warning':
                    return '<span class="badge bg-warning">Warning</span>';
                case 'error':
                    return '<span class="badge bg-danger">Error</span>';
                default:
                    return '<span class="badge bg-secondary">Unknown</span>';
            }
        }
    </script>
</body>
</html>
