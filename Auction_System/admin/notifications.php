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
if (!$conn) {
    die("Database connection failed. Please check your database configuration.");
}

// Get all notifications with user information
$query = "SELECT n.*, u.email, u.role 
          FROM notifications n 
          JOIN users u ON n.user_id = u.id 
          ORDER BY n.created_at DESC 
          LIMIT 100";
$result = $conn->query($query);

if (!$result) {
    die("Query failed: " . $conn->error);
}

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $row['created_at_formatted'] = date('M j, Y g:i A', strtotime($row['created_at']));
    $row['type_badge'] = getNotificationTypeBadge($row['type']);
    $notifications[] = $row;
}

// Add this function if it doesn't exist in notification-functions.php
if (!function_exists('getNotificationTypeBadge')) {
    function getNotificationTypeBadge($type) {
        switch ($type) {
            case 'success':
                return '<span class="badge bg-success">Success</span>';
            case 'warning':
                return '<span class="badge bg-warning text-dark">Warning</span>';
            case 'error':
                return '<span class="badge bg-danger">Error</span>';
            case 'info':
            default:
                return '<span class="badge bg-info text-dark">Info</span>';
        }
    }
}

// Debug information
debug_log("Admin notifications page accessed by: " . $_SESSION['email']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
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
                    <li class="active">
                        <a href="notifications.php">
                            <i class="fas fa-bell"></i>
                            <span>Notifications</span>
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
                        <span class="badge">3</span>
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
                    <h1>Notifications</h1>
                    <div class="date-range">
                        <i class="fas fa-calendar"></i>
                        <span><?php echo date('F d, Y'); ?></span>
                    </div>
                </div>

                <!-- Notifications Management -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">All Notifications</h5>
                                <a href="send-notification.php" class="btn btn-sm btn-primary">
                                    <i class="fas fa-plus"></i> Send New Notification
                                </a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>User</th>
                                                <th>Title</th>
                                                <th>Type</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($notifications)): ?>
                                                <tr>
                                                    <td colspan="7" class="text-center">No notifications found.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($notifications as $notification): ?>
                                                    <tr>
                                                        <td><?php echo $notification['id']; ?></td>
                                                        <td>
                                                            <span class="user-info">
                                                                <?php echo htmlspecialchars($notification['email']); ?>
                                                                <small class="badge bg-secondary"><?php echo ucfirst($notification['role']); ?></small>
                                                            </span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($notification['title']); ?></td>
                                                        <td><?php echo $notification['type_badge']; ?></td>
                                                        <td>
                                                            <?php if ($notification['is_read']): ?>
                                                                <span class="badge bg-secondary">Read</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-success">Unread</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo $notification['created_at_formatted']; ?></td>
                                                        <td>
                                                            <div class="action-buttons">
                                                                <button class="btn btn-sm btn-info view-notification" data-id="<?php echo $notification['id']; ?>" data-title="<?php echo htmlspecialchars($notification['title']); ?>" data-message="<?php echo htmlspecialchars($notification['message']); ?>" data-type="<?php echo $notification['type']; ?>">
                                                                    <i class="fas fa-eye"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-danger delete-notification" data-id="<?php echo $notification['id']; ?>">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notification Stats -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Notification Types</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="notificationTypesChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Notification Status</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="notificationStatusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification Modal -->
    <div class="modal fade" id="notificationModal" tabindex="-1" aria-labelledby="notificationModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="notificationModalLabel">Notification Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="notification-details">
                        <h5 id="notification-title"></h5>
                        <div class="mb-2">
                            <span id="notification-type-badge"></span>
                        </div>
                        <p id="notification-message"></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this notification?</p>
                    <p>This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirm-delete">Delete</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            
            // View notification modal
            $('.view-notification').click(function() {
                const id = $(this).data('id');
                const title = $(this).data('title');
                const message = $(this).data('message');
                const type = $(this).data('type');
                
                $('#notification-title').text(title);
                $('#notification-message').text(message);
                
                let typeBadge = '';
                switch (type) {
                    case 'success':
                        typeBadge = '<span class="badge bg-success">Success</span>';
                        break;
                    case 'warning':
                        typeBadge = '<span class="badge bg-warning text-dark">Warning</span>';
                        break;
                    case 'error':
                        typeBadge = '<span class="badge bg-danger">Error</span>';
                        break;
                    case 'info':
                    default:
                        typeBadge = '<span class="badge bg-info text-dark">Info</span>';
                        break;
                }
                
                $('#notification-type-badge').html(typeBadge);
                
                $('#notificationModal').modal('show');
                
                // Mark as read if unread
                $.ajax({
                    url: '../api/notifications.php',
                    type: 'POST',
                    data: {
                        action: 'mark_read',
                        notification_id: id
                    },
                    dataType: 'json'
                });
            });
            
            // Delete notification
            $('.delete-notification').click(function() {
                const id = $(this).data('id');
                $('#confirm-delete').data('id', id);
                $('#deleteModal').modal('show');
            });
            
            // Confirm delete
            $('#confirm-delete').click(function() {
                const id = $(this).data('id');
                
                $.ajax({
                    url: '../api/notifications.php',
                    type: 'POST',
                    data: {
                        action: 'delete_notification',
                        notification_id: id
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#deleteModal').modal('hide');
                            location.reload();
                        } else {
                            alert(response.message);
                        }
                    },
                    error: function() {
                        alert('An error occurred. Please try again.');
                    }
                });
            });
            
            // Initialize charts
            <?php
            // Get notification type counts
            $typeQuery = "SELECT 
                COUNT(CASE WHEN type = 'info' THEN 1 END) as info_count,
                COUNT(CASE WHEN type = 'success' THEN 1 END) as success_count,
                COUNT(CASE WHEN type = 'warning' THEN 1 END) as warning_count,
                COUNT(CASE WHEN type = 'error' THEN 1 END) as error_count
                FROM notifications";
            $typeResult = $conn->query($typeQuery);
            $typeData = $typeResult ? $typeResult->fetch_assoc() : ['info_count' => 0, 'success_count' => 0, 'warning_count' => 0, 'error_count' => 0];
            
            // Get notification status counts
            $statusQuery = "SELECT 
                COUNT(CASE WHEN is_read = 1 THEN 1 END) as read_count,
                COUNT(CASE WHEN is_read = 0 THEN 1 END) as unread_count
                FROM notifications";
            $statusResult = $conn->query($statusQuery);
            $statusData = $statusResult ? $statusResult->fetch_assoc() : ['read_count' => 0, 'unread_count' => 0];
            ?>
            
            // Notification Types Chart
            const typeCtx = document.getElementById('notificationTypesChart');
            if (typeCtx) {
                new Chart(typeCtx, {
                    type: 'pie',
                    data: {
                        labels: ['Info', 'Success', 'Warning', 'Error'],
                        datasets: [{
                            data: [
                                <?php echo $typeData['info_count']; ?>,
                                <?php echo $typeData['success_count']; ?>,
                                <?php echo $typeData['warning_count']; ?>,
                                <?php echo $typeData['error_count']; ?>
                            ],
                            backgroundColor: ['#36b9cc', '#1cc88a', '#f6c23e', '#e74a3b'],
                            hoverBackgroundColor: ['#2c9faf', '#17a673', '#dda20a', '#be2617'],
                            hoverBorderColor: "rgba(234, 236, 244, 1)",
                        }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
            
            // Notification Status Chart
            const statusCtx = document.getElementById('notificationStatusChart');
            if (statusCtx) {
                new Chart(statusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Read', 'Unread'],
                        datasets: [{
                            data: [
                                <?php echo $statusData['read_count']; ?>,
                                <?php echo $statusData['unread_count']; ?>
                            ],
                            backgroundColor: ['#858796', '#4e73df'],
                            hoverBackgroundColor: ['#6e707e', '#2e59d9'],
                            hoverBorderColor: "rgba(234, 236, 244, 1)",
                        }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>
