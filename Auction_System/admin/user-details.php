<?php
require_once '../includes/functions.php';
require_once '../config/database.php';

// Check if user is logged in and is admin
startSession();
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php?admin=true');
}

// Get database connection
$conn = getDbConnection();

// Check if user ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "User ID is required.";
    redirect('users.php');
}

$userId = (int)$_GET['id'];

// Get user details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "User not found.";
    redirect('users.php');
}

$user = $result->fetch_assoc();

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'update_user':
            $email = isset($_POST['email']) ? sanitizeInput($_POST['email']) : '';
            $role = isset($_POST['role']) ? sanitizeInput($_POST['role']) : '';
            $status = isset($_POST['status']) ? sanitizeInput($_POST['status']) : '';
            $isVerified = isset($_POST['is_verified']) ? 1 : 0;
            
            // Validate inputs
            if (empty($email) || empty($role) || empty($status)) {
                $_SESSION['error_message'] = "All fields are required.";
                redirect("user-details.php?id=$userId");
            }
            
            if (!validateEmail($email)) {
                $_SESSION['error_message'] = "Invalid email format.";
                redirect("user-details.php?id=$userId");
            }
            
            // Check if email already exists (if email is changed)
            if ($email !== $user['email']) {
                $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $checkStmt->bind_param("si", $email, $userId);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                
                if ($checkResult->num_rows > 0) {
                    $_SESSION['error_message'] = "Email already exists.";
                    redirect("user-details.php?id=$userId");
                }
            }
            
            // Update user
            $updateStmt = $conn->prepare("UPDATE users SET email = ?, role = ?, status = ?, is_verified = ? WHERE id = ?");
            $updateStmt->bind_param("sssii", $email, $role, $status, $isVerified, $userId);
            
            if ($updateStmt->execute()) {
                // Log the action
                $changes = [];
                if ($email !== $user['email']) $changes[] = "Email: {$user['email']} → $email";
                if ($role !== $user['role']) $changes[] = "Role: {$user['role']} → $role";
                if ($status !== $user['status']) $changes[] = "Status: {$user['status']} → $status";
                if ($isVerified !== $user['is_verified']) $changes[] = "Verified: " . ($user['is_verified'] ? 'Yes' : 'No') . " → " . ($isVerified ? 'Yes' : 'No');
                
                logAdminAction('UPDATE_USER', $userId, "Updated user details: " . implode(', ', $changes));
                
                // Send email notification if status changed
                if ($status !== $user['status']) {
                    $subject = "Your Account Status Has Been Updated";
                    $message = "Dear User,\n\nYour account status has been updated from '{$user['status']}' to '$status'.\n\n";
                    
                    if ($status === 'approved') {
                        $message .= "You can now log in and access all features of the platform.";
                    } elseif ($status === 'rejected') {
                        $message .= "If you believe this is an error, please contact our support team.";
                    } elseif ($status === 'deactivated') {
                        $message .= "Your account has been deactivated. If you believe this is an error, please contact our support team.";
                    }
                    
                    $message .= "\n\nThank you,\nAuction Platform Team";
                    
                    sendEmailNotification($email, $subject, $message);
                }
                
                $_SESSION['success_message'] = "User updated successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to update user: " . $conn->error;
            }
            
            redirect("user-details.php?id=$userId");
            break;
            
        case 'impersonate_user':
            // Log the action
            logAdminAction('IMPERSONATE_USER', $userId, "Admin impersonated user: {$user['email']}");
            
            // Store admin session data for restoration
            $_SESSION['admin_id'] = $_SESSION['user_id'];
            $_SESSION['admin_email'] = $_SESSION['email'];
            $_SESSION['admin_role'] = $_SESSION['role'];
            $_SESSION['is_impersonating'] = true;
            
            // Set session to impersonated user
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['status'] = $user['status'];
            
            $_SESSION['success_message'] = "You are now impersonating {$user['email']}. Return to admin panel to stop impersonating.";
            
            // Redirect based on user role
            if ($user['role'] === 'buyer' || $user['role'] === 'seller') {
                redirect('../customer/dashboard.php');
            } else {
                redirect('../index.php');
            }
            break;
    }
}

// Get user activity logs (placeholder - in a real implementation, this would fetch from a logs table)
$userLogs = [
    [
        'action' => 'Account Created',
        'timestamp' => $user['created_at'],
        'details' => 'User account was created'
    ]
];

// If the user has been approved, add that to the logs
if ($user['status'] === 'approved') {
    $userLogs[] = [
        'action' => 'Account Approved',
        'timestamp' => $user['updated_at'],
        'details' => 'User account was approved by an administrator'
    ];
}

// If the user has been rejected, add that to the logs
if ($user['status'] === 'rejected') {
    $userLogs[] = [
        'action' => 'Account Rejected',
        'timestamp' => $user['updated_at'],
        'details' => 'User account was rejected. Reason: ' . ($user['rejection_reason'] ?? 'No reason provided')
    ];
}

// If the user has been deactivated, add that to the logs
if ($user['status'] === 'deactivated') {
    $userLogs[] = [
        'action' => 'Account Deactivated',
        'timestamp' => $user['updated_at'],
        'details' => 'User account was deactivated by an administrator'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .user-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: #6c757d;
            margin: 0 auto 20px;
        }
        .user-info-card {
            text-align: center;
            padding: 20px;
        }
        .user-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
        }
        .tab-content {
            padding: 20px 0;
        }
        .activity-timeline {
            position: relative;
            padding-left: 30px;
        }
        .activity-timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background-color: #dee2e6;
        }
        .activity-item {
            position: relative;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f8f9fa;
        }
        .activity-item:last-child {
            border-bottom: none;
        }
        .activity-item::before {
            content: '';
            position: absolute;
            left: -30px;
            top: 0;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background-color: #fff;
            border: 2px solid #6c757d;
        }
        .activity-item.created::before {
            border-color: #28a745;
        }
        .activity-item.approved::before {
            border-color: #28a745;
        }
        .activity-item.rejected::before {
            border-color: #dc3545;
        }
        .activity-item.deactivated::before {
            border-color: #6c757d;
        }
        .activity-date {
            font-size: 12px;
            color: #6c757d;
        }
        .activity-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .activity-details {
            font-size: 14px;
        }
    </style>
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
                    <li class="active">
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
                    <h1>User Details</h1>
                    <a href="users.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to User List
                    </a>
                </div>

                <!-- Alert Messages -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['success_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['error_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <div class="row">
                    <!-- User Profile Card -->
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-body user-info-card">
                                <div class="user-avatar">
                                    <i class="fas fa-user"></i>
                                </div>
                                <h4><?php echo $user['email']; ?></h4>
                                <p class="text-muted">User ID: <?php echo $user['id']; ?></p>
                                
                                <div class="d-flex justify-content-center gap-2 mb-3">
                                    <span class="badge bg-info"><?php echo ucfirst($user['role']); ?></span>
                                    <?php echo getUserStatusBadge($user['status']); ?>
                                    <?php if ($user['is_verified']): ?>
                                        <span class="badge bg-success">Verified</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Not Verified</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="user-actions">
                                    <?php if ($user['status'] === 'pending'): ?>
                                        <form action="users.php" method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-success">
                                                <i class="fas fa-check me-1"></i> Approve
                                            </button>
                                        </form>
                                        
                                        <button type="button" class="btn btn-sm btn-danger reject-user" data-user-id="<?php echo $user['id']; ?>" data-user-email="<?php echo $user['email']; ?>">
                                            <i class="fas fa-times me-1"></i> Reject
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($user['status'] !== 'deactivated'): ?>
                                        <button type="button" class="btn btn-sm btn-warning deactivate-user" data-user-id="<?php echo $user['id']; ?>" data-user-email="<?php echo $user['email']; ?>">
                                            <i class="fas fa-ban me-1"></i> Deactivate
                                        </button>
                                    <?php else: ?>
                                        <form action="users.php" method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-success">
                                                <i class="fas fa-check me-1"></i> Reactivate
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <button type="button" class="btn btn-sm btn-primary reset-password" data-user-id="<?php echo $user['id']; ?>" data-user-email="<?php echo $user['email']; ?>">
                                        <i class="fas fa-key me-1"></i> Reset Password
                                    </button>
                                    
                                    <button type="button" class="btn btn-sm btn-danger delete-user" data-user-id="<?php echo $user['id']; ?>" data-user-email="<?php echo $user['email']; ?>">
                                        <i class="fas fa-trash me-1"></i> Delete
                                    </button>
                                    
                                    <form action="user-details.php?id=<?php echo $userId; ?>" method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="impersonate_user">
                                        <button type="submit" class="btn btn-sm btn-secondary">
                                            <i class="fas fa-user-secret me-1"></i> Impersonate
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- User Information Card -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Account Information</h5>
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>Registered On</span>
                                        <span class="text-muted"><?php echo formatDate($user['created_at'], 'F d, Y H:i:s'); ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>Last Updated</span>
                                        <span class="text-muted"><?php echo formatDate($user['updated_at'], 'F d, Y H:i:s'); ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>Account Type</span>
                                        <span class="badge bg-info"><?php echo ucfirst($user['role']); ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>Status</span>
                                        <?php echo getUserStatusBadge($user['status']); ?>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>Verified</span>
                                        <?php if ($user['is_verified']): ?>
                                            <span class="badge bg-success">Yes</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">No</span>
                                        <?php endif; ?>
                                    </li>
                                    <?php if ($user['status'] === 'rejected' && !empty($user['rejection_reason'])): ?>
                                        <li class="list-group-item">
                                            <span>Rejection Reason</span>
                                            <p class="text-danger mt-2 mb-0"><?php echo $user['rejection_reason']; ?></p>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- User Details Tabs -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <ul class="nav nav-tabs card-header-tabs" id="userDetailsTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="edit-tab" data-bs-toggle="tab" data-bs-target="#edit" type="button" role="tab" aria-controls="edit" aria-selected="true">Edit User</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button" role="tab" aria-controls="activity" aria-selected="false">Activity Log</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="auctions-tab" data-bs-toggle="tab" data-bs-target="#auctions" type="button" role="tab" aria-controls="auctions" aria-selected="false">Auctions</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="bids-tab" data-bs-toggle="tab" data-bs-target="#bids" type="button" role="tab" aria-controls="bids" aria-selected="false">Bids</button>
                                    </li>
                                </ul>
                            </div>
                            <div class="card-body">
                                <div class="tab-content" id="userDetailsTabsContent">
                                    <!-- Edit User Tab -->
                                    <div class="tab-pane fade show active" id="edit" role="tabpanel" aria-labelledby="edit-tab">
                                        <form action="user-details.php?id=<?php echo $userId; ?>" method="POST">
                                            <input type="hidden" name="action" value="update_user">
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label for="email" class="form-label">Email</label>
                                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo $user['email']; ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="role" class="form-label">Role</label>
                                                    <select class="form-select" id="role" name="role" required>
                                                        <option value="buyer" <?php echo $user['role'] === 'buyer' ? 'selected' : ''; ?>>Buyer</option>
                                                        <option value="seller" <?php echo $user['role'] === 'seller' ? 'selected' : ''; ?>>Seller</option>
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label for="status" class="form-label">Status</label>
                                                    <select class="form-select" id="status" name="status" required>
                                                        <option value="pending" <?php echo $user['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="approved" <?php echo $user['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                                        <option value="rejected" <?php echo $user['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                        <option value="deactivated" <?php echo $user['status'] === 'deactivated' ? 'selected' : ''; ?>>Deactivated</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label d-block">&nbsp;</label>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="is_verified" name="is_verified" <?php echo $user['is_verified'] ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="is_verified">
                                                            Verified User
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between">
                                                <a href="users.php" class="btn btn-secondary">Cancel</a>
                                                <button type="submit" class="btn btn-primary">Update User</button>
                                            </div>
                                        </form>
                                    </div>
                                    
                                    <!-- Activity Log Tab -->
                                    <div class="tab-pane fade" id="activity" role="tabpanel" aria-labelledby="activity-tab">
                                        <div class="activity-timeline">
                                            <?php foreach ($userLogs as $index => $log): ?>
                                                <?php 
                                                    $activityClass = 'activity-item';
                                                    if (strpos(strtolower($log['action']), 'created') !== false) {
                                                        $activityClass .= ' created';
                                                    } elseif (strpos(strtolower($log['action']), 'approved') !== false) {
                                                        $activityClass .= ' approved';
                                                    } elseif (strpos(strtolower($log['action']), 'rejected') !== false) {
                                                        $activityClass .= ' rejected';
                                                    } elseif (strpos(strtolower($log['action']), 'deactivated') !== false) {
                                                        $activityClass .= ' deactivated';
                                                    }
                                                ?>
                                                <div class="<?php echo $activityClass; ?>">
                                                    <div class="activity-date"><?php echo formatDate($log['timestamp'], 'F d, Y H:i:s'); ?></div>
                                                    <div class="activity-title"><?php echo $log['action']; ?></div>
                                                    <div class="activity-details"><?php echo $log['details']; ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                            
                                            <?php if (empty($userLogs)): ?>
                                                <p class="text-muted">No activity logs found for this user.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Auctions Tab -->
                                    <div class="tab-pane fade" id="auctions" role="tabpanel" aria-labelledby="auctions-tab">
                                        <?php if ($user['role'] === 'seller'): ?>
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle me-2"></i> This section shows auctions created by this seller.
                                            </div>
                                            <!-- Placeholder for seller's auctions -->
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>ID</th>
                                                            <th>Item</th>
                                                            <th>Start Price</th>
                                                            <th>Current Bid</th>
                                                            <th>Status</th>
                                                            <th>Created On</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td colspan="7" class="text-center">No auctions found for this user.</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle me-2"></i> This user is a buyer and cannot create auctions.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Bids Tab -->
                                    <div class="tab-pane fade" id="bids" role="tabpanel" aria-labelledby="bids-tab">
                                        <?php if ($user['role'] === 'buyer' || $user['role'] === 'seller'): ?>
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle me-2"></i> This section shows bids placed by this user.
                                            </div>
                                            <!-- Placeholder for user's bids -->
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>ID</th>
                                                            <th>Auction</th>
                                                            <th>Bid Amount</th>
                                                            <th>Status</th>
                                                            <th>Placed On</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td colspan="6" class="text-center">No bids found for this user.</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-warning">
                                                <i class="fas fa-exclamation-triangle me-2"></i> This user type cannot place bids.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <!-- Reject User Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectModalLabel">Reject User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>You are about to reject the user: <strong id="reject-user-email"></strong></p>
                    <form id="reject-form" action="users.php" method="POST">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" id="reject-user-id" name="user_id">
                        <div class="mb-3">
                            <label for="reason" class="form-label">Rejection Reason:</label>
                            <textarea class="form-control" id="reason" name="reason" rows="3" placeholder="Please provide a reason for rejection" required></textarea>
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

    <!-- Delete User Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Delete User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i> Warning: This action cannot be undone!
                    </div>
                    <p>Are you sure you want to permanently delete the user: <strong id="delete-user-email"></strong>?</p>
                    <p>All associated data will be permanently removed from the system.</p>
                    <form id="delete-form" action="users.php" method="POST">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" id="delete-user-id" name="user_id">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirm-delete">Confirm Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Deactivate User Modal -->
    <div class="modal fade" id="deactivateModal" tabindex="-1" aria-labelledby="deactivateModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deactivateModalLabel">Deactivate User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to deactivate the user: <strong id="deactivate-user-email"></strong>?</p>
                    <p>The user will no longer be able to log in, but their data will be preserved.</p>
                    <form id="deactivate-form" action="users.php" method="POST">
                        <input type="hidden" name="action" value="deactivate">
                        <input type="hidden" id="deactivate-user-id" name="user_id">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" id="confirm-deactivate">Confirm Deactivate</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="resetPasswordModalLabel">Reset User Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>You are about to reset the password for user: <strong id="reset-user-email"></strong></p>
                    <p>A new temporary password will be generated. The user will need to change it upon next login.</p>
                    <form id="reset-password-form" action="users.php" method="POST">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" id="reset-user-id" name="user_id">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirm-reset">Reset Password</button>
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
            
            // Handle reject user modal
            $('.reject-user').click(function(e) {
                e.preventDefault();
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
                $('#reject-form').submit();
            });
            
            // Handle delete user modal
            $('.delete-user').click(function(e) {
                e.preventDefault();
                const userId = $(this).data('user-id');
                const userEmail = $(this).data('user-email');
                
                // Set values in modal
                $('#delete-user-id').val(userId);
                $('#delete-user-email').text(userEmail);
                
                // Show modal
                $('#deleteModal').modal('show');
            });
            
            // Handle confirm delete
            $('#confirm-delete').click(function() {
                $('#delete-form').submit();
            });
            
            // Handle deactivate user modal
            $('.deactivate-user').click(function(e) {
                e.preventDefault();
                const userId = $(this).data('user-id');
                const userEmail = $(this).data('user-email');
                
                // Set values in modal
                $('#deactivate-user-id').val(userId);
                $('#deactivate-user-email').text(userEmail);
                
                // Show modal
                $('#deactivateModal').modal('show');
            });
            
            // Handle confirm deactivate
            $('#confirm-deactivate').click(function() {
                $('#deactivate-form').submit();
            });
            
            // Handle reset password modal
            $('.reset-password').click(function(e) {
                e.preventDefault();
                const userId = $(this).data('user-id');
                const userEmail = $(this).data('user-email');
                
                // Set values in modal
                $('#reset-user-id').val(userId);
                $('#reset-user-email').text(userEmail);
                
                // Show modal
                $('#resetPasswordModal').modal('show');
            });
            
            // Handle confirm reset password
            $('#confirm-reset').click(function() {
                $('#reset-password-form').submit();
            });
            
            // Auto-dismiss alerts after 5 seconds
            setTimeout(function() {
                $('.alert-dismissible').alert('close');
            }, 5000);
        });
    </script>
</body>
</html>
