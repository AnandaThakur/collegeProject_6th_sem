<?php
/**
 * Wallet Functions
 * 
 * This file contains functions for wallet management
 */

// Include database configuration if not already included
if (!function_exists('getDbConnection')) {
    require_once __DIR__ . '/../config/database.php';
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Get wallet balance for a user
 * 
 * @param int $userId User ID
 * @return float Wallet balance
 */
function getWalletBalance($userId) {
    $conn = getDbConnection();
    
    // Check if wallet_balances table exists
    $result = $conn->query("SHOW TABLES LIKE 'wallet_balances'");
    if ($result->num_rows == 0) {
        // Create wallet tables if they don't exist
        require_once __DIR__ . '/../database/wallet_tables.php';
    }
    
    // Get wallet balance
    $stmt = $conn->prepare("SELECT balance FROM wallet_balances WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['balance'];
    } else {
        // Create wallet balance record if it doesn't exist
        $stmt = $conn->prepare("INSERT INTO wallet_balances (user_id, balance) VALUES (?, 0.00)");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        
        return 0.00;
    }
}

/**
 * Update wallet balance
 * 
 * @param int $userId User ID
 * @param float $amount Amount to update
 * @param string $type Transaction type (deposit, withdrawal, bid, win, refund, deduct)
 * @param string $description Transaction description
 * @param int $adminId Admin ID (optional)
 * @param string $referenceId Reference ID (optional)
 * @return array Result with success status, message, and new balance
 */
function updateWalletBalance($userId, $amount, $type, $description = '', $adminId = null, $referenceId = null) {
    $conn = getDbConnection();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get current balance
        $stmt = $conn->prepare("SELECT balance FROM wallet_balances WHERE user_id = ? FOR UPDATE");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            // Create wallet balance record if it doesn't exist
            $currentBalance = 0.00;
            $stmt = $conn->prepare("INSERT INTO wallet_balances (user_id, balance) VALUES (?, 0.00)");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
        } else {
            $currentBalance = $result->fetch_assoc()['balance'];
        }
        
        // Calculate new balance based on transaction type
        $newBalance = $currentBalance;
        $transactionAmount = 0;
        
        switch ($type) {
            case 'deposit':
                $newBalance += $amount;
                $transactionAmount = $amount;
                break;
            case 'withdrawal':
                if ($currentBalance < $amount) {
                    throw new Exception("Insufficient funds");
                }
                $newBalance -= $amount;
                $transactionAmount = -$amount;
                break;
            case 'bid':
                if ($currentBalance < $amount) {
                    throw new Exception("Insufficient funds");
                }
                $newBalance -= $amount;
                $transactionAmount = -$amount;
                break;
            case 'win':
                $newBalance += $amount;
                $transactionAmount = $amount;
                break;
            case 'refund':
                $newBalance += $amount;
                $transactionAmount = $amount;
                break;
            case 'deduct':
                if ($currentBalance < $amount) {
                    throw new Exception("Insufficient funds");
                }
                $newBalance -= $amount;
                $transactionAmount = -$amount;
                break;
            default:
                throw new Exception("Invalid transaction type");
        }
        
        // Update wallet balance
        $stmt = $conn->prepare("UPDATE wallet_balances SET balance = ?, updated_at = NOW() WHERE user_id = ?");
        $stmt->bind_param("di", $newBalance, $userId);
        $stmt->execute();
        
        // Create transaction record
        $transactionId = generateTransactionId();
        $stmt = $conn->prepare("INSERT INTO wallet_transactions 
                              (transaction_id, user_id, type, amount, status, description, admin_id, reference_id, created_at) 
                              VALUES (?, ?, ?, ?, 'completed', ?, ?, ?, NOW())");
        $stmt->bind_param("sisdsis", $transactionId, $userId, $type, $transactionAmount, $description, $adminId, $referenceId);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        return [
            'success' => true,
            'message' => 'Wallet balance updated successfully',
            'new_balance' => $newBalance,
            'transaction_id' => $transactionId
        ];
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Generate transaction ID
 * 
 * @return string Transaction ID
 */
function generateTransactionId() {
    return 'TXN' . date('YmdHis') . rand(1000, 9999);
}

/**
 * Get recent transactions for a user
 * 
 * @param int $userId User ID
 * @param int $limit Limit number of transactions
 * @return array Recent transactions
 */
function getRecentTransactions($userId, $limit = 5) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT * FROM wallet_transactions 
                          WHERE user_id = ? 
                          ORDER BY created_at DESC 
                          LIMIT ?");
    $stmt->bind_param("ii", $userId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
    
    return $transactions;
}

/**
 * Get pending transactions for a user
 * 
 * @param int $userId User ID (optional, if not provided, get all pending transactions)
 * @return array Pending transactions
 */
function getPendingTransactions($userId = null) {
    $conn = getDbConnection();
    
    if ($userId) {
        $stmt = $conn->prepare("SELECT * FROM wallet_transactions 
                              WHERE user_id = ? AND status = 'pending' 
                              ORDER BY created_at DESC");
        $stmt->bind_param("i", $userId);
    } else {
        $stmt = $conn->prepare("SELECT wt.*, u.email as user_email 
                              FROM wallet_transactions wt
                              JOIN users u ON wt.user_id = u.id
                              WHERE wt.status = 'pending' 
                              ORDER BY wt.created_at DESC");
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
    
    return $transactions;
}

/**
 * Cancel a pending transaction
 * 
 * @param string $transactionId Transaction ID
 * @param int $userId User ID
 * @return array Result with success status and message
 */
function cancelTransaction($transactionId, $userId) {
    $conn = getDbConnection();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Check if transaction exists and belongs to the user
        $stmt = $conn->prepare("SELECT * FROM wallet_transactions 
                              WHERE transaction_id = ? AND user_id = ? AND status = 'pending'");
        $stmt->bind_param("si", $transactionId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            throw new Exception("Transaction not found or cannot be cancelled");
        }
        
        // Update transaction status
        $stmt = $conn->prepare("UPDATE wallet_transactions 
                              SET status = 'cancelled', updated_at = NOW() 
                              WHERE transaction_id = ?");
        $stmt->bind_param("s", $transactionId);
        $stmt->execute();
        
        // Create notification for user
        if (function_exists('createNotification')) {
            $userNotification = "Your transaction #" . $transactionId . " has been cancelled";
            createNotification($userId, "Transaction Cancelled", $userNotification, "info", "system");
        }
        
        // Commit transaction
        $conn->commit();
        
        return [
            'success' => true,
            'message' => 'Transaction cancelled successfully'
        ];
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Get transaction history for a user
 * 
 * @param int $userId User ID
 * @param int $limit Limit number of transactions
 * @param int $offset Offset for pagination
 * @param array $filters Filters for transactions
 * @return array Transaction history
 */
function getTransactionHistory($userId, $limit = 10, $offset = 0, $filters = []) {
    $conn = getDbConnection();
    
    $query = "SELECT * FROM wallet_transactions WHERE user_id = ?";
    $params = [$userId];
    $types = "i";
    
    // Apply filters
    if (!empty($filters['type'])) {
        $query .= " AND type = ?";
        $params[] = $filters['type'];
        $types .= "s";
    }
    
    if (!empty($filters['status'])) {
        $query .= " AND status = ?";
        $params[] = $filters['status'];
        $types .= "s";
    }
    
    if (!empty($filters['date_from'])) {
        $query .= " AND created_at >= ?";
        $params[] = $filters['date_from'] . ' 00:00:00';
        $types .= "s";
    }
    
    if (!empty($filters['date_to'])) {
        $query .= " AND created_at <= ?";
        $params[] = $filters['date_to'] . ' 23:59:59';
        $types .= "s";
    }
    
    $query .= " ORDER BY created_at DESC LIMIT ?, ?";
    $params[] = $offset;
    $params[] = $limit;
    $types .= "ii";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
    
    return $transactions;
}

/**
 * Count total transactions for a user
 * 
 * @param int $userId User ID
 * @param array $filters Filters for transactions
 * @return int Total transactions
 */
function countTransactions($userId, $filters = []) {
    $conn = getDbConnection();
    
    $query = "SELECT COUNT(*) as count FROM wallet_transactions WHERE user_id = ?";
    $params = [$userId];
    $types = "i";
    
    // Apply filters
    if (!empty($filters['type'])) {
        $query .= " AND type = ?";
        $params[] = $filters['type'];
        $types .= "s";
    }
    
    if (!empty($filters['status'])) {
        $query .= " AND status = ?";
        $params[] = $filters['status'];
        $types .= "s";
    }
    
    if (!empty($filters['date_from'])) {
        $query .= " AND created_at >= ?";
        $params[] = $filters['date_from'] . ' 00:00:00';
        $types .= "s";
    }
    
    if (!empty($filters['date_to'])) {
        $query .= " AND created_at <= ?";
        $params[] = $filters['date_to'] . ' 23:59:59';
        $types .= "s";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc()['count'];
}

/**
 * Get all user wallets for admin (excluding admin users)
 * 
 * @param int $limit Limit number of users
 * @param int $offset Offset for pagination
 * @param string $search Search term
 * @return array User wallets
 */
function getAllUserWallets($limit = 10, $offset = 0, $search = '') {
    $conn = getDbConnection();
    
    $query = "SELECT u.id, u.email, u.role, u.status, 
              COALESCE(wb.balance, 0.00) as balance, 
              (SELECT MAX(created_at) FROM wallet_transactions WHERE user_id = u.id) as last_transaction
              FROM users u
              LEFT JOIN wallet_balances wb ON u.id = wb.user_id
              WHERE u.role != 'admin'";
    
    $params = [];
    $types = "";
    
    if (!empty($search)) {
        $query .= " AND (u.email LIKE ? OR u.id = ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $search;
        $types .= "ss";
    }
    
    $query .= " ORDER BY wb.balance DESC LIMIT ?, ?";
    $params[] = $offset;
    $params[] = $limit;
    $types .= "ii";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    
    return $users;
}

/**
 * Count total users for admin (excluding admin users)
 * 
 * @param string $search Search term
 * @return int Total users
 */
function countTotalUsers($search = '') {
    $conn = getDbConnection();
    
    $query = "SELECT COUNT(*) as count FROM users WHERE role != 'admin'";
    
    $params = [];
    $types = "";
    
    if (!empty($search)) {
        $query .= " AND (email LIKE ? OR id = ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $search;
        $types .= "ss";
    }
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc()['count'];
}

/**
 * Get wallet statistics for admin dashboard
 * 
 * @return array Wallet statistics
 */
function getWalletStatistics() {
    $conn = getDbConnection();
    
    // Get total wallet balance
    $result = $conn->query("SELECT SUM(balance) as total_balance FROM wallet_balances 
                          WHERE user_id IN (SELECT id FROM users WHERE role != 'admin')");
    $totalBalance = $result->fetch_assoc()['total_balance'] ?? 0;
    
    // Get total transactions
    $result = $conn->query("SELECT COUNT(*) as total_transactions FROM wallet_transactions 
                          WHERE user_id IN (SELECT id FROM users WHERE role != 'admin')");
    $totalTransactions = $result->fetch_assoc()['total_transactions'] ?? 0;
    
    // Get total deposits
    $result = $conn->query("SELECT COUNT(*) as total_deposits FROM wallet_transactions 
                          WHERE type = 'deposit' AND status = 'completed' 
                          AND user_id IN (SELECT id FROM users WHERE role != 'admin')");
    $totalDeposits = $result->fetch_assoc()['total_deposits'] ?? 0;
    
    // Get total withdrawals
    $result = $conn->query("SELECT COUNT(*) as total_withdrawals FROM wallet_transactions 
                          WHERE type = 'withdrawal' AND status = 'completed' 
                          AND user_id IN (SELECT id FROM users WHERE role != 'admin')");
    $totalWithdrawals = $result->fetch_assoc()['total_withdrawals'] ?? 0;
    
    // Get total pending transactions
    $result = $conn->query("SELECT COUNT(*) as total_pending FROM wallet_transactions 
                          WHERE status = 'pending' 
                          AND user_id IN (SELECT id FROM users WHERE role != 'admin')");
    $totalPending = $result->fetch_assoc()['total_pending'] ?? 0;
    
    // Get recent transactions
    $result = $conn->query("SELECT wt.*, u.email as user_email 
                          FROM wallet_transactions wt
                          JOIN users u ON wt.user_id = u.id
                          WHERE u.role != 'admin'
                          ORDER BY wt.created_at DESC
                          LIMIT 5");
    
    $recentTransactions = [];
    while ($row = $result->fetch_assoc()) {
        $recentTransactions[] = $row;
    }
    
    return [
        'total_balance' => $totalBalance,
        'total_transactions' => $totalTransactions,
        'total_deposits' => $totalDeposits,
        'total_withdrawals' => $totalWithdrawals,
        'total_pending' => $totalPending,
        'recent_transactions' => $recentTransactions
    ];
}

/**
 * Get transaction type badge HTML
 * 
 * @param string $type Transaction type
 * @return string HTML badge
 */
function getTransactionTypeBadge($type) {
    switch ($type) {
        case 'deposit':
            return '<span class="badge bg-success">Deposit</span>';
        case 'withdrawal':
            return '<span class="badge bg-danger">Withdrawal</span>';
        case 'bid':
            return '<span class="badge bg-primary">Bid</span>';
        case 'win':
            return '<span class="badge bg-warning text-dark">Win</span>';
        case 'refund':
            return '<span class="badge bg-info">Refund</span>';
        case 'deduct':
            return '<span class="badge bg-danger">Deduct</span>';
        default:
            return '<span class="badge bg-secondary">' . ucfirst($type) . '</span>';
    }
}

/**
 * Get transaction status badge HTML
 * 
 * @param string $status Transaction status
 * @return string HTML badge
 */
function getTransactionStatusBadge($status) {
    switch ($status) {
        case 'pending':
            return '<span class="badge bg-warning text-dark">Pending</span>';
        case 'completed':
            return '<span class="badge bg-success">Completed</span>';
        case 'failed':
            return '<span class="badge bg-danger">Failed</span>';
        case 'cancelled':
            return '<span class="badge bg-secondary">Cancelled</span>';
        case 'rejected':
            return '<span class="badge bg-danger">Rejected</span>';
        default:
            return '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
    }
}

/**
 * Get system setting
 * 
 * @param string $key Setting key
 * @return string Setting value
 */
function getSystemSetting($key) {
    $conn = getDbConnection();
    
    // Check if system_settings table exists
    $result = $conn->query("SHOW TABLES LIKE 'system_settings'");
    if ($result->num_rows == 0) {
        return '';
    }
    
    // Check column names in system_settings table
    $result = $conn->query("SHOW COLUMNS FROM system_settings");
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    
    // Determine the correct column names
    $keyColumn = in_array('setting_key', $columns) ? 'setting_key' : 'key';
    $valueColumn = in_array('setting_value', $columns) ? 'setting_value' : 'value';
    
    // Get setting value
    $stmt = $conn->prepare("SELECT $valueColumn FROM system_settings WHERE $keyColumn = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()[$valueColumn];
    }
    
    return '';
}

/**
 * Verify and approve a pending transaction
 * 
 * @param string $transactionId Transaction ID
 * @param int $adminId Admin ID
 * @return array Result with success status and message
 */
function approveTransaction($transactionId, $adminId) {
    $conn = getDbConnection();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get transaction details
        $stmt = $conn->prepare("SELECT * FROM wallet_transactions WHERE transaction_id = ? AND status = 'pending'");
        $stmt->bind_param("s", $transactionId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            throw new Exception("Transaction not found or already processed");
        }
        
        $transaction = $result->fetch_assoc();
        $userId = $transaction['user_id'];
        $amount = $transaction['amount'];
        $type = $transaction['type'];
        
        // Update transaction status
        $stmt = $conn->prepare("UPDATE wallet_transactions 
                              SET status = 'completed', admin_id = ?, updated_at = NOW() 
                              WHERE transaction_id = ?");
        $stmt->bind_param("is", $adminId, $transactionId);
        $stmt->execute();
        
        // Update wallet balance
        $stmt = $conn->prepare("SELECT balance FROM wallet_balances WHERE user_id = ? FOR UPDATE");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            // Create wallet balance record if it doesn't exist
            $currentBalance = 0.00;
            $stmt = $conn->prepare("INSERT INTO wallet_balances (user_id, balance) VALUES (?, 0.00)");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
        } else {
            $currentBalance = $result->fetch_assoc()['balance'];
        }
        
        // Calculate new balance
        $newBalance = $currentBalance + $amount;
        
        // Update wallet balance
        $stmt = $conn->prepare("UPDATE wallet_balances SET balance = ?, updated_at = NOW() WHERE user_id = ?");
        $stmt->bind_param("di", $newBalance, $userId);
        $stmt->execute();
        
        // Create notification for user
        if (function_exists('createNotification')) {
            $userNotification = "Your " . ucfirst($type) . " transaction of Rs " . number_format($amount, 2) . " has been approved";
            createNotification($userId, "Transaction Approved", $userNotification, "success", "system");
        }
        
        // Commit transaction
        $conn->commit();
        
        return [
            'success' => true,
            'message' => 'Transaction approved successfully',
            'new_balance' => $newBalance
        ];
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Reject a pending transaction
 * 
 * @param string $transactionId Transaction ID
 * @param int $adminId Admin ID
 * @param string $reason Rejection reason
 * @return array Result with success status and message
 */
function rejectTransaction($transactionId, $adminId, $reason = '') {
    $conn = getDbConnection();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get transaction details
        $stmt = $conn->prepare("SELECT * FROM wallet_transactions WHERE transaction_id = ? AND status = 'pending'");
        $stmt->bind_param("s", $transactionId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            throw new Exception("Transaction not found or already processed");
        }
        
        $transaction = $result->fetch_assoc();
        $userId = $transaction['user_id'];
        $amount = $transaction['amount'];
        $type = $transaction['type'];
        
        // Update transaction status
        $stmt = $conn->prepare("UPDATE wallet_transactions 
                              SET status = 'rejected', admin_id = ?, description = CONCAT(description, ' (Rejected: ', ?, ')'), updated_at = NOW() 
                              WHERE transaction_id = ?");
        $stmt->bind_param("iss", $adminId, $reason, $transactionId);
        $stmt->execute();
        
        // Create notification for user
        if (function_exists('createNotification')) {
            $userNotification = "Your " . ucfirst($type) . " transaction of Rs " . number_format($amount, 2) . " has been rejected";
            if (!empty($reason)) {
                $userNotification .= ". Reason: " . $reason;
            }
            createNotification($userId, "Transaction Rejected", $userNotification, "error", "system");
        }
        
        // Commit transaction
        $conn->commit();
        
        return [
            'success' => true,
            'message' => 'Transaction rejected successfully'
        ];
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
?>
