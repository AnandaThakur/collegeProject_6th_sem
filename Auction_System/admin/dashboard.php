<?php
require_once '../includes/functions.php';
require_once '../config/database.php';

// Check if user is logged in and is admin
startSession();
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php?admin=true');
}

// Get dashboard statistics
$conn = getDbConnection();

// Get user counts
$stmt = $conn->prepare("SELECT 
    COUNT(CASE WHEN role = 'buyer' THEN 1 END) as total_buyers,
    COUNT(CASE WHEN role = 'seller' THEN 1 END) as total_sellers,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_users,
    COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_users,
    COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_users
    FROM users WHERE role != 'admin'");
$stmt->execute();
$userStats = $stmt->get_result()->fetch_assoc();

// Get recent users
$stmt = $conn->prepare("SELECT id, email, role, status, created_at FROM users 
    WHERE role != 'admin' ORDER BY created_at DESC LIMIT 5");
$stmt->execute();
$recentUsers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Debug information
debug_log("Admin dashboard accessed by: " . $_SESSION['email']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Auction Platform</title>
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
                    <li class="active">
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
                        <a href="system-setting.php">
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
                    <h1>Dashboard</h1>
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
                                <h5>Total Buyers</h5>
                                <h2><?php echo $userStats['total_buyers']; ?></h2>
                            </div>
                            <div class="stat-card-icon">
                                <i class="fas fa-user-tag"></i>
                            </div>
                        </div>
                        <div class="stat-card-footer">
                            <span><i class="fas fa-arrow-up"></i> 12% from last month</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-content">
                            <div class="stat-card-info">
                                <h5>Total Sellers</h5>
                                <h2><?php echo $userStats['total_sellers']; ?></h2>
                            </div>
                            <div class="stat-card-icon">
                                <i class="fas fa-store"></i>
                            </div>
                        </div>
                        <div class="stat-card-footer">
                            <span><i class="fas fa-arrow-up"></i> 8% from last month</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-content">
                            <div class="stat-card-info">
                                <h5>Total Auctions</h5>
                                <h2>0</h2>
                            </div>
                            <div class="stat-card-icon">
                                <i class="fas fa-gavel"></i>
                            </div>
                        </div>
                        <div class="stat-card-footer">
                            <span>No auctions yet</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-content">
                            <div class="stat-card-info">
                                <h5>Total Revenue</h5>
                                <h2>Rs0.00</h2>
                            </div>
                            <div class="stat-card-icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                        </div>
                        <div class="stat-card-footer">
                            <span>No revenue yet</span>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="charts-row">
                    <div class="chart-container">
                        <div class="chart-header">
                            <h4>User Distribution</h4>
                        </div>
                        <div class="chart-body">
                            <canvas id="userDistributionChart"></canvas>
                        </div>
                    </div>
                    <div class="chart-container">
                        <div class="chart-header">
                            <h4>User Status</h4>
                        </div>
                        <div class="chart-body">
                            <canvas id="userStatusChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Recent Users -->
                <div class="recent-section">
                    <div class="section-header">
                        <h4>Recent Users</h4>
                        <a href="users.php" class="view-all">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Registered On</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentUsers as $user): ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td><?php echo $user['email']; ?></td>
                                        <td><?php echo ucfirst($user['role']); ?></td>
                                        <td><?php echo getUserStatusBadge($user['status']); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="user-details.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if ($user['status'] === 'pending'): ?>
                                                <button type="button" class="btn btn-sm btn-success approve-user" data-user-id="<?php echo $user['id']; ?>">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger reject-user" data-user-id="<?php echo $user['id']; ?>" data-user-email="<?php echo $user['email']; ?>">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recentUsers)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No users found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent Auctions -->
                <div class="recent-section">
                    <div class="section-header">
                        <h4>Recent Auctions</h4>
                        <a href="auctions.php" class="view-all">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Item</th>
                                    <th>Seller</th>
                                    <th>Start Price</th>
                                    <th>Current Bid</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="7" class="text-center">No auctions found</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectModalLabel">Reject User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>You are about to reject the user: <strong id="reject-user-email"></strong></p>
                    <form id="reject-form">
                        <input type="hidden" id="reject-user-id" name="user_id">
                        <div class="mb-3">
                            <label for="rejection-reason" class="form-label">Rejection Reason:</label>
                            <textarea class="form-control" id="rejection-reason" name="reason" rows="3" placeholder="Please provide a reason for rejection"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirm-reject">Confirm Rejection</button>
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
            
            // Handle approve user
            $('.approve-user').click(function() {
                const userId = $(this).data('user-id');
                const button = $(this);
                
                // Disable button to prevent multiple clicks
                button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
                
                $.ajax({
                    url: '../api/auth.php',
                    type: 'POST',
                    data: {
                        action: 'approve_user',
                        user_id: userId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Show success message
                            const alertHtml = `
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    ${response.message}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            `;
                            $('.content-header').after(alertHtml);
                            
                            // Reload page after a short delay
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            // Re-enable button
                            button.prop('disabled', false).html('<i class="fas fa-check"></i>');
                            alert(response.message);
                        }
                    },
                    error: function() {
                        // Re-enable button
                        button.prop('disabled', false).html('<i class="fas fa-check"></i>');
                        alert('An error occurred. Please try again.');
                    }
                });
            });
            
            // Handle reject user modal
            $('.reject-user').click(function() {
                const userId = $(this).data('user-id');
                const userEmail = $(this).data('user-email');
                
                // Set values in modal
                $('#reject-user-id').val(userId);
                $('#reject-user-email').text(userEmail);
                
                // Show modal
                $('#rejectModal').modal('show');
            });
            
            // Handle confirm reject
            $('#confirm-reject').click(function() {
                const userId = $('#reject-user-id').val();
                const reason = $('#rejection-reason').val();
                const button = $(this);
                
                // Disable button to prevent multiple clicks
                button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
                
                $.ajax({
                    url: '../api/auth.php',
                    type: 'POST',
                    data: {
                        action: 'reject_user',
                        user_id: userId,
                        reason: reason
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Hide modal
                            $('#rejectModal').modal('hide');
                            
                            // Reload page after a short delay
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            // Re-enable button
                            button.prop('disabled', false).text('Confirm Rejection');
                            alert(response.message);
                        }
                    },
                    error: function() {
                        // Re-enable button
                        button.prop('disabled', false).text('Confirm Rejection');
                        alert('An error occurred. Please try again.');
                    }
                });
            });

            // Initialize charts
            const userDistributionChart = new Chart(
                document.getElementById('userDistributionChart'),
                {
                    type: 'pie',
                    data: {
                        labels: ['Buyers', 'Sellers'],
                        datasets: [{
                            data: [<?php echo $userStats['total_buyers']; ?>, <?php echo $userStats['total_sellers']; ?>],
                            backgroundColor: ['#4e73df', '#1cc88a'],
                            hoverBackgroundColor: ['#2e59d9', '#17a673'],
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
                }
            );

            const userStatusChart = new Chart(
                document.getElementById('userStatusChart'),
                {
                    type: 'doughnut',
                    data: {
                        labels: ['Pending', 'Approved', 'Rejected'],
                        datasets: [{
                            data: [
                                <?php echo $userStats['pending_users']; ?>, 
                                <?php echo $userStats['approved_users']; ?>, 
                                <?php echo $userStats['rejected_users']; ?>
                            ],
                            backgroundColor: ['#f6c23e', '#36b9cc', '#e74a3b'],
                            hoverBackgroundColor: ['#dda20a', '#2c9faf', '#be2617'],
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
                }
            );
        });
    </script>
</body>
</html>
