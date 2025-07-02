<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .sortable {
            cursor: pointer;
        }
        .sortable:hover {
            background-color: rgba(0,0,0,0.05);
        }
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
        }
        .bulk-actions {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
        }
        .bulk-actions.show {
            display: block;
        }
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            background-color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .stat-card .number {
            font-size: 24px;
            font-weight: bold;
            margin: 10px 0;
        }
        .stat-card .label {
            color: #6c757d;
            font-size: 14px;
        }
        .stat-card .icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
        }
        .bg-pending {
            background-color: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }
        .bg-approved {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }
        .bg-rejected {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        .bg-deactivated {
            background-color: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }
        .bg-buyer {
            background-color: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
        }
        .bg-seller {
            background-color: rgba(111, 66, 193, 0.1);
            color: #6f42c1;
        }
        .export-btn {
            margin-left: 10px;
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
                    <h1>User Management</h1>
                    <div class="date-range">
                        <i class="fas fa-calendar"></i>
                        <span><?php echo date('F d, Y'); ?></span>
                    </div>
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

                <!-- User Statistics -->
                <div class="stats-cards">
                    <div class="stat-card">
                        <div class="icon bg-pending">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="number"><?php echo $pendingCount; ?></div>
                        <div class="label">Pending Users</div>
                    </div>
                    <div class="stat-card">
                        <div class="icon bg-approved">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="number"><?php echo $approvedCount; ?></div>
                        <div class="label">Approved Users</div>
                    </div>
                    <div class="stat-card">
                        <div class="icon bg-rejected">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="number"><?php echo $rejectedCount; ?></div>
                        <div class="label">Rejected Users</div>
                    </div>
                    <div class="stat-card">
                        <div class="icon bg-deactivated">
                            <i class="fas fa-ban"></i>
                        </div>
                        <div class="number"><?php echo $deactivatedCount; ?></div>
                        <div class="label">Deactivated Users</div>
                    </div>
                    <div class="stat-card">
                        <div class="icon bg-buyer">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="number"><?php echo $buyerCount; ?></div>
                        <div class="label">Buyers</div>
                    </div>
                    <div class="stat-card">
                        <div class="icon bg-seller">
                            <i class="fas fa-store"></i>
                        </div>
                        <div class="number"><?php echo $sellerCount; ?></div>
                        <div class="label">Sellers</div>
                    </div>
                </div>

                <!-- Add a dedicated section for pending approvals if there are any -->
                <?php if ($pendingCount > 0 && ($statusFilter === 'pending' || empty($statusFilter))): ?>
                <div class="card mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Pending Approvals (<?php echo $pendingCount; ?>)</h5>
                    </div>
                    <div class="card-body">
                        <p>The following users are waiting for your approval. Please review their information and take appropriate action.</p>
                        
                        <!-- Quick approval buttons -->
                        <div class="d-flex justify-content-end mb-3">
                            <button type="button" class="btn btn-success me-2" id="approveAllPending">
                                <i class="fas fa-check me-1"></i> Approve All Pending
                            </button>
                        </div>
                        
                        <!-- Only show this section if we're not already filtering by pending status -->
                        <?php if ($statusFilter !== 'pending'): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th width="40px">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="selectAllPending">
                                            </div>
                                        </th>
                                        <th>ID</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Registered On</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Get pending users
                                    $pendingStmt = $conn->prepare("SELECT id, email, role, created_at FROM users WHERE status = 'pending' AND role != 'admin' ORDER BY created_at DESC LIMIT 5");
                                    $pendingStmt->execute();
                                    $pendingUsers = $pendingStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                    
                                    if (count($pendingUsers) > 0):
                                        foreach ($pendingUsers as $pendingUser):
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="form-check">
                                                <input class="form-check-input pending-checkbox" type="checkbox" name="pending_users[]" value="<?php echo $pendingUser['id']; ?>">
                                            </div>
                                        </td>
                                        <td><?php echo $pendingUser['id']; ?></td>
                                        <td><?php echo $pendingUser['email']; ?></td>
                                        <td><span class="badge bg-info"><?php echo ucfirst($pendingUser['role']); ?></span></td>
                                        <td><?php echo formatDate($pendingUser['created_at']); ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-success approve-user" data-user-id="<?php echo $pendingUser['id']; ?>">
                                                    <i class="fas fa-check me-1"></i> Approve
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger reject-user" data-user-id="<?php echo $pendingUser['id']; ?>" data-user-email="<?php echo $pendingUser['email']; ?>">
                                                    <i class="fas fa-times me-1"></i> Reject
                                                </button>
                                                <a href="user-details.php?id=<?php echo $pendingUser['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye me-1"></i> Details
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php 
                                        endforeach;
                                    else:
                                    ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No pending users found</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (count($pendingUsers) >= 5): ?>
                        <div class="text-center mt-3">
                            <a href="users.php?status=pending" class="btn btn-outline-warning">View All Pending Users</a>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Bulk Actions -->
                <div class="bulk-actions" id="bulkActionsContainer">
                    <form action="users.php<?php echo isset($_GET) && !empty($_GET) ? '?' . http_build_query($_GET) : ''; ?>" method="POST" id="bulkActionForm">
                        <input type="hidden" name="action" value="bulk_action">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="d-flex align-items-center">
                                    <select class="form-select" name="bulk_action_type" id="bulkActionType">
                                        <option value="">Select Action</option>
                                        <option value="approve">Approve Selected</option>
                                        <option value="deactivate">Deactivate Selected</option>
                                        <option value="delete">Delete Selected</option>
                                    </select>
                                    <button type="submit" class="btn btn-primary ms-2" id="applyBulkAction" disabled>Apply</button>
                                </div>
                            </div>
                            <div class="col-md-6 text-end">
                                <span id="selectedCount">0 users selected</span>
                                <button type="button" class="btn btn-sm btn-outline-secondary ms-2" id="clearSelection">Clear Selection</button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Filter Controls -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form action="users.php" method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    <option value="deactivated" <?php echo $statusFilter === 'deactivated' ? 'selected' : ''; ?>>Deactivated</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="role" class="form-label">Role</label>
                                <select class="form-select" id="role" name="role">
                                    <option value="">All Roles</option>
                                    <option value="buyer" <?php echo $roleFilter === 'buyer' ? 'selected' : ''; ?>>Buyer</option>
                                    <option value="seller" <?php echo $roleFilter === 'seller' ? 'selected' : ''; ?>>Seller</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" placeholder="Search by email or ID" value="<?php echo $searchTerm; ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                                <a href="#" class="btn btn-success export-btn" id="exportUsers" title="Export Users"><i class="fas fa-file-export"></i></a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">User List</h5>
                        <span class="badge bg-primary"><?php echo $totalUsers; ?> users found</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th width="40px">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="selectAll">
                                            </div>
                                        </th>
                                        <th class="sortable" onclick="window.location.href='<?php echo getSortUrl('id', $sortBy, $sortOrder); ?>'">
                                            ID <?php echo getSortIcon('id', $sortBy, $sortOrder); ?>
                                        </th>
                                        <th class="sortable" onclick="window.location.href='<?php echo getSortUrl('email', $sortBy, $sortOrder); ?>'">
                                            Email <?php echo getSortIcon('email', $sortBy, $sortOrder); ?>
                                        </th>
                                        <th class="sortable" onclick="window.location.href='<?php echo getSortUrl('role', $sortBy, $sortOrder); ?>'">
                                            Role <?php echo getSortIcon('role', $sortBy, $sortOrder); ?>
                                        </th>
                                        <th class="sortable" onclick="window.location.href='<?php echo getSortUrl('status', $sortBy, $sortOrder); ?>'">
                                            Status <?php echo getSortIcon('status', $sortBy, $sortOrder); ?>
                                        </th>
                                        <th class="sortable" onclick="window.location.href='<?php echo getSortUrl('is_verified', $sortBy, $sortOrder); ?>'">
                                            Verified <?php echo getSortIcon('is_verified', $sortBy, $sortOrder); ?>
                                        </th>
                                        <th class="sortable" onclick="window.location.href='<?php echo getSortUrl('created_at', $sortBy, $sortOrder); ?>'">
                                            Registered On <?php echo getSortIcon('created_at', $sortBy, $sortOrder); ?>
                                        </th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($users) > 0): ?>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td>
                                                    <div class="form-check">
                                                        <input class="form-check-input user-checkbox" type="checkbox" name="selected_users[]" value="<?php echo $user['id']; ?>" form="bulkActionForm">
                                                    </div>
                                                </td>
                                                <td><?php echo $user['id']; ?></td>
                                                <td><?php echo $user['email']; ?></td>
                                                <td><span class="badge bg-info"><?php echo ucfirst($user['role']); ?></span></td>
                                                <td><?php echo getUserStatusBadge($user['status']); ?></td>
                                                <td>
                                                    <?php if ($user['is_verified']): ?>
                                                        <span class="badge bg-success">Yes</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">No</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo formatDate($user['created_at']); ?></td>
                                                <td>
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton<?php echo $user['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                            Actions
                                                        </button>
                                                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?php echo $user['id']; ?>">
                                                            <li><a class="dropdown-item" href="user-details.php?id=<?php echo $user['id']; ?>"><i class="fas fa-eye me-2"></i> View Details</a></li>
                                                            
                                                            <?php if ($user['status'] === 'pending'): ?>
                                                                <li><a class="dropdown-item approve-user" href="#" data-user-id="<?php echo $user['id']; ?>"><i class="fas fa-check me-2"></i> Approve</a></li>
                                                                <li><a class="dropdown-item reject-user" href="#" data-user-id="<?php echo $user['id']; ?>" data-user-email="<?php echo $user['email']; ?>"><i class="fas fa-times me-2"></i> Reject</a></li>
                                                            <?php endif; ?>
                                                            
                                                            <?php if ($user['status'] !== 'deactivated'): ?>
                                                                <li><a class="dropdown-item deactivate-user" href="#" data-user-id="<?php echo $user['id']; ?>" data-user-email="<?php echo $user['email']; ?>"><i class="fas fa-ban me-2"></i> Deactivate</a></li>
                                                            <?php else: ?>
                                                                <li><a class="dropdown-item approve-user" href="#" data-user-id="<?php echo $user['id']; ?>"><i class="fas fa-check me-2"></i> Reactivate</a></li>
                                                            <?php endif; ?>
                                                            
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li><a class="dropdown-item reset-password" href="#" data-user-id="<?php echo $user['id']; ?>" data-user-email="<?php echo $user['email']; ?>"><i class="fas fa-key me-2"></i> Reset Password</a></li>
                                                            <li><a class="dropdown-item delete-user" href="#" data-user-id="<?php echo $user['id']; ?>" data-user-email="<?php echo $user['email']; ?>"><i class="fas fa-trash me-2"></i> Delete</a></li>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center">No users found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <div class="pagination-container">
                                <div>
                                    Showing <?php echo min(($page - 1) * $perPage + 1, $totalUsers); ?> to <?php echo min($page * $perPage, $totalUsers); ?> of <?php echo $totalUsers; ?> users
                                </div>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination">
                                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="<?php echo $page > 1 ? '?page=' . ($page - 1) . (isset($_GET) && !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : '') : '#'; ?>" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                        
                                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET) && !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : ''; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="<?php echo $page < $totalPages ? '?page=' . ($page + 1) . (isset($_GET) && !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : '') : '#'; ?>" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
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
