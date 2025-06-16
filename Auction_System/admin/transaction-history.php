<?php
// Include necessary files
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/wallet-functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

// Check if wallet tables exist, if not create them
if (!tableExists('wallet_balances') || !tableExists('wallet_transactions') || !tableExists('payment_settings')) {
    require_once '../database/wallet_tables.php';
}

// Function to check if table exists
function tableExists($tableName) {
    $conn = getDbConnection();
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result->num_rows > 0;
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filters
$filters = [
    'user_id' => isset($_GET['user_id']) ? (int)$_GET['user_id'] : null,
    'type' => isset($_GET['type']) ? $_GET['type'] : null,
    'status' => isset($_GET['status']) ? $_GET['status'] : null,
    'date_from' => isset($_GET['date_from']) ? $_GET['date_from'] : null,
    'date_to' => isset($_GET['date_to']) ? $_GET['date_to'] : null
];

// Get transactions
$transactions = getTransactionHistory($limit, $offset, $filters);
$totalTransactions = countTotalTransactions($filters);
$totalPages = ceil($totalTransactions / $limit);

// Get user details if filtering by user
$userData = null;
if (!empty($filters['user_id'])) {
    $userData = getUserById($filters['user_id']);
}

// Set page title for header
$pageTitle = "Transaction History";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History - Admin Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Datepicker CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <!-- Admin CSS -->
    <link rel="stylesheet" href="../assets/css/admin.css">
    <!-- Wallet Management CSS -->
    <link rel="stylesheet" href="../assets/css/wallet-management.css">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="admin-sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <img src="../assets/img/logo.svg" alt="Logo" class="logo-icon">
                    <span>Admin Panel</span>
                </div>
            </div>
            <div class="sidebar-menu">
                <?php include_once '../includes/admin-sidebar.php'; ?>
            </div>
            <div class="sidebar-footer">
                <a href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="admin-content">
            <!-- Navbar -->
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
                        <i class="fas fa-user-circle"></i>
                        <span>Admin</span>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="content-area">
                <div class="content-header">
                    <h1>Transaction History</h1>
                    <div class="btn-toolbar">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.location.reload()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportTransactions()">
                                <i class="fas fa-file-export"></i> Export
                            </button>
                        </div>
                    </div>
                </div>

                <?php if ($userData): ?>
                <div class="alert alert-info">
                    <h5>Viewing transactions for: <?php echo htmlspecialchars($userData['email']); ?></h5>
                    <p>User ID: <?php echo $userData['id']; ?> | Role: <?php echo ucfirst($userData['role']); ?> | Status: <?php echo ucfirst($userData['status']); ?></p>
                    <a href="wallet-management.php" class="btn btn-sm btn-primary">Back to Wallet Management</a>
                </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="card mb-4 filter-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <button class="btn btn-link" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="true" aria-controls="filterCollapse">
                                <i class="fas fa-filter"></i> Filters
                            </button>
                        </h5>
                    </div>
                    <div id="filterCollapse" class="collapse show">
                        <div class="card-body">
                              class="collapse show">
                        <div class="card-body">
                            <form method="GET" action="" class="row g-3">
                                <?php if (!$userData): ?>
                                <div class="col-md-3">
                                    <label for="user_id" class="form-label">User ID</label>
                                    <input type="number" class="form-control" id="user_id" name="user_id" value="<?php echo $filters['user_id'] ?? ''; ?>" placeholder="Filter by user ID">
                                </div>
                                <?php else: ?>
                                <input type="hidden" name="user_id" value="<?php echo $userData['id']; ?>">
                                <?php endif; ?>
                                
                                <div class="col-md-3">
                                    <label for="type" class="form-label">Transaction Type</label>
                                    <select class="form-select" id="type" name="type">
                                        <option value="">All Types</option>
                                        <option value="deduct" <?php echo $filters['type'] === 'deduct' ? 'selected' : ''; ?>>Deduct</option>
                                        <option value="refund" <?php echo $filters['type'] === 'refund' ? 'selected' : ''; ?>>Refund</option>
                                        <option value="deposit" <?php echo $filters['type'] === 'deposit' ? 'selected' : ''; ?>>Deposit</option>
                                        <option value="withdrawal" <?php echo $filters['type'] === 'withdrawal' ? 'selected' : ''; ?>>Withdrawal</option>
                                        <option value="bid" <?php echo $filters['type'] === 'bid' ? 'selected' : ''; ?>>Bid</option>
                                        <option value="win" <?php echo $filters['type'] === 'win' ? 'selected' : ''; ?>>Win</option>
                                        <option value="commission" <?php echo $filters['type'] === 'commission' ? 'selected' : ''; ?>>Commission</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="">All Statuses</option>
                                        <option value="pending" <?php echo $filters['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="completed" <?php echo $filters['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="failed" <?php echo $filters['status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                        <option value="reversed" <?php echo $filters['status'] === 'reversed' ? 'selected' : ''; ?>>Reversed</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="date_from" class="form-label">Date From</label>
                                    <input type="text" class="form-control datepicker" id="date_from" name="date_from" value="<?php echo $filters['date_from'] ?? ''; ?>" placeholder="From date">
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="date_to" class="form-label">Date To</label>
                                    <input type="text" class="form-control datepicker" id="date_to" name="date_to" value="<?php echo $filters['date_to'] ?? ''; ?>" placeholder="To date">
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search"></i> Apply Filters
                                        </button>
                                        <a href="transaction-history.php" class="btn btn-secondary">
                                            <i class="fas fa-redo"></i> Reset
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Transactions Table -->
                <div class="recent-section">
                    <div class="section-header">
                        <h4>Transactions</h4>
                        <div class="dropdown">
                            <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-ellipsis-v"></i>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuLink">
                                <li><a class="dropdown-item" href="#" onclick="window.location.reload()">Refresh Data</a></li>
                                <li><a class="dropdown-item" href="#" onclick="exportTransactions()">Export to CSV</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="wallet-management.php">Back to Wallet Management</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered" id="transactionsTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Transaction ID</th>
                                    <th>User</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date & Time</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">No transactions found</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td><?php echo $transaction['transaction_id']; ?></td>
                                        <td><?php echo htmlspecialchars($transaction['user_email']); ?></td>
                                        <td><?php echo getTransactionTypeBadge($transaction['type']); ?></td>
                                        <td class="transaction-amount <?php echo $transaction['amount'] >= 0 ? 'positive' : 'negative'; ?>">
                                            Rs <?php echo number_format($transaction['amount'], 2); ?>
                                        </td>
                                        <td><?php echo getTransactionStatusBadge($transaction['status']); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($transaction['created_at'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="showTransactionDetails('<?php echo $transaction['transaction_id']; ?>', '<?php echo htmlspecialchars(addslashes($transaction['user_email'])); ?>', '<?php echo $transaction['type']; ?>', '<?php echo $transaction['amount']; ?>', '<?php echo $transaction['status']; ?>', '<?php echo $transaction['created_at']; ?>', '<?php echo htmlspecialchars(addslashes($transaction['description'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes($transaction['admin_email'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes($transaction['reference_id'] ?? '')); ?>')">
                                                <i class="fas fa-info-circle"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&user_id=<?php echo $filters['user_id'] ?? ''; ?>&type=<?php echo $filters['type'] ?? ''; ?>&status=<?php echo $filters['status'] ?? ''; ?>&date_from=<?php echo $filters['date_from'] ?? ''; ?>&date_to=<?php echo $filters['date_to'] ?? ''; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&user_id=<?php echo $filters['user_id'] ?? ''; ?>&type=<?php echo $filters['type'] ?? ''; ?>&status=<?php echo $filters['status'] ?? ''; ?>&date_from=<?php echo $filters['date_from'] ?? ''; ?>&date_to=<?php echo $filters['date_to'] ?? ''; ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&user_id=<?php echo $filters['user_id'] ?? ''; ?>&type=<?php echo $filters['type'] ?? ''; ?>&status=<?php echo $filters['status'] ?? ''; ?>&date_from=<?php echo $filters['date_from'] ?? ''; ?>&date_to=<?php echo $filters['date_to'] ?? ''; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Transaction Details Modal -->
    <div class="modal fade" id="transactionDetailsModal" tabindex="-1" aria-labelledby="transactionDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="transactionDetailsModalLabel">Transaction Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="transactionDetailsModalBody">
                    <div class="transaction-details">
                        <p><strong>Transaction ID:</strong> <span id="modal-transaction-id"></span></p>
                        <p><strong>User:</strong> <span id="modal-user"></span></p>
                        <p><strong>Type:</strong> <span id="modal-type"></span></p>
                        <p><strong>Amount:</strong> <span id="modal-amount"></span></p>
                        <p><strong>Status:</strong> <span id="modal-status"></span></p>
                        <p><strong>Date & Time:</strong> <span id="modal-date"></span></p>
                        <p><strong>Description:</strong> <span id="modal-description"></span></p>
                        <p><strong>Admin:</strong> <span id="modal-admin"></span></p>
                        <p><strong>Reference ID:</strong> <span id="modal-reference"></span></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Flatpickr -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <!-- Custom JavaScript -->
    <script>
        // Toggle sidebar
        document.querySelector('.menu-toggle').addEventListener('click', function() {
            document.querySelector('.admin-container').classList.toggle('sidebar-collapsed');
        });
        
        // Initialize datepickers
        document.addEventListener('DOMContentLoaded', function() {
            flatpickr(".datepicker", {
                dateFormat: "Y-m-d",
                allowInput: true
            });
        });
        
        // Function to show transaction details
        function showTransactionDetails(transactionId, user, type, amount, status, date, description, admin, reference) {
            document.getElementById('modal-transaction-id').textContent = transactionId;
            document.getElementById('modal-user').textContent = user;
            
            // Format type with badge
            let typeBadge = '';
            switch(type) {
                case 'deduct':
                    typeBadge = '<span class="badge bg-danger">Deduct</span>';
                    break;
                case 'refund':
                    typeBadge = '<span class="badge bg-success">Refund</span>';
                    break;
                case 'deposit':
                    typeBadge = '<span class="badge bg-primary">Deposit</span>';
                    break;
                case 'withdrawal':
                    typeBadge = '<span class="badge bg-warning text-dark">Withdrawal</span>';
                    break;
                case 'bid':
                    typeBadge = '<span class="badge bg-info">Bid</span>';
                    break;
                case 'win':
                    typeBadge = '<span class="badge bg-success">Win</span>';
                    break;
                case 'commission':
                    typeBadge = '<span class="badge bg-secondary">Commission</span>';
                    break;
                default:
                    typeBadge = '<span class="badge bg-secondary">' + type.charAt(0).toUpperCase() + type.slice(1) + '</span>';
            }
            document.getElementById('modal-type').innerHTML = typeBadge;
            
            // Format amount
            const formattedAmount = 'Rs ' + parseFloat(amount).toFixed(2);
            document.getElementById('modal-amount').textContent = formattedAmount;
            document.getElementById('modal-amount').className = parseFloat(amount) >= 0 ? 'transaction-amount positive' : 'transaction-amount negative';
            
            // Format status with badge
            let statusBadge = '';
            switch(status) {
                case 'pending':
                    statusBadge = '<span class="badge bg-warning text-dark">Pending</span>';
                    break;
                case 'completed':
                    statusBadge = '<span class="badge bg-success">Completed</span>';
                    break;
                case 'failed':
                    statusBadge = '<span class="badge bg-danger">Failed</span>';
                    break;
                case 'reversed':
                    statusBadge = '<span class="badge bg-secondary">Reversed</span>';
                    break;
                default:
                    statusBadge = '<span class="badge bg-secondary">' + status.charAt(0).toUpperCase() + status.slice(1) + '</span>';
            }
            document.getElementById('modal-status').innerHTML = statusBadge;
            
            // Format date
            const formattedDate = new Date(date).toLocaleString();
            document.getElementById('modal-date').textContent = formattedDate;
            
            // Set description, admin, and reference
            document.getElementById('modal-description').textContent = description || 'N/A';
            document.getElementById('modal-admin').textContent = admin || 'N/A';
            document.getElementById('modal-reference').textContent = reference || 'N/A';
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('transactionDetailsModal'));
            modal.show();
        }
        
        // Function to export transactions
        function exportTransactions() {
            // Get current filters
            const urlParams = new URLSearchParams(window.location.search);
            const userId = urlParams.get('user_id') || '';
            const type = urlParams.get('type') || '';
            const status = urlParams.get('status') || '';
            const dateFrom = urlParams.get('date_from') || '';
            const dateTo = urlParams.get('date_to') || '';
            
            // Construct export URL with filters
            const exportUrl = `../api/wallet-actions.php?action=export_transactions&user_id=${userId}&type=${type}&status=${status}&date_from=${dateFrom}&date_to=${dateTo}`;
            
            // Redirect to export URL
            window.location.href = exportUrl;
        }
    </script>
</body>
</html>
