<?php
require_once 'includes/functions.php';
require_once 'config/database.php';

// Start session
startSession();

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if user is admin (redirect to admin dashboard)
if (isAdmin()) {
    redirect('admin/dashboard.php');
}

// Check if user is already verified (redirect to customer dashboard)
if (isVerified()) {
    redirect('customer/dashboard.php');
}

// Get user status
$userId = $_SESSION['user_id'];
$conn = getDbConnection();
$stmt = $conn->prepare("SELECT email, role, status, rejection_reason FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

// If user not found, redirect to login
if ($result->num_rows === 0) {
    // Clear session
    session_unset();
    session_destroy();
    redirect('login.php');
}

$user = $result->fetch_assoc();

// Determine status message and UI elements
$statusMessage = '';
$statusIcon = '';
$statusClass = '';

if ($user['status'] === 'pending') {
    $statusMessage = 'Your account is pending approval by an administrator.';
    $statusIcon = 'fa-clock';
    $statusClass = 'warning';
} else if ($user['status'] === 'rejected') {
    $rejectionReason = !empty($user['rejection_reason']) ? $user['rejection_reason'] : 'No reason provided.';
    $statusMessage = "Your account has been rejected. Reason: $rejectionReason";
    $statusIcon = 'fa-times-circle';
    $statusClass = 'danger';
} else {
    // This shouldn't happen, but just in case
    redirect('customer/dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Verification - Auction Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .waiting-card {
            max-width: 600px;
            width: 100%;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .status-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        .status-icon.warning {
            color: #ffc107;
        }
        .status-icon.danger {
            color: #dc3545;
        }
        .card-header {
            background-color: var(--primary-color);
            color: white;
            padding: 20px;
            text-align: center;
            font-weight: 600;
            font-size: 1.2rem;
        }
        .card-body {
            padding: 40px;
            text-align: center;
        }
        .btn-logout {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card waiting-card">
            <div class="card-header">
                <svg class="logo-icon me-2" style="width: 30px; height: 30px;" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                    <path d="M30 30 L70 30 L70 70 L30 70 Z" stroke="#FFF" stroke-width="6" fill="none" />
                    <path d="M20 20 L80 20 L80 80 L20 80 Z" stroke="#FFF" stroke-width="6" fill="none" transform="rotate(45 50 50)" />
                </svg>
                Account Verification Status
            </div>
            <div class="card-body">
                <div class="status-icon <?php echo $statusClass; ?>">
                    <i class="fas <?php echo $statusIcon; ?>"></i>
                </div>
                
                <h3>Hello, <?php echo $user['email']; ?></h3>
                
                <?php if ($user['status'] === 'pending'): ?>
                    <div class="alert alert-warning">
                        <p><strong>Your account is pending verification.</strong></p>
                        <p><?php echo $statusMessage; ?></p>
                        <p>Please check back later or contact support if you have any questions.</p>
                    </div>
                    <div class="mt-4">
                        <h5>What happens next?</h5>
                        <ol class="text-start">
                            <li>An administrator will review your registration details</li>
                            <li>You will receive an email notification once your account has been reviewed</li>
                            <li>If approved, you'll be able to access all features of the platform</li>
                            <li>If rejected, you'll be provided with a reason and next steps</li>
                        </ol>
                    </div>
                    <p>Once your account is approved, you'll be able to access all features of the platform.</p>
                <?php else: ?>
                    <div class="alert alert-danger">
                        <p><strong>Your account has been rejected.</strong></p>
                        <p><?php echo $statusMessage; ?></p>
                    </div>
                    <p>If you believe this is an error, please contact our support team.</p>
                <?php endif; ?>
                
                <button id="logout-btn" class="btn btn-primary btn-logout">Logout</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Handle logout
            $('#logout-btn').click(function() {
                $.ajax({
                    url: 'api/auth.php',
                    type: 'POST',
                    data: {
                        action: 'logout'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            window.location.href = response.data.redirect;
                        }
                    },
                    error: function() {
                        // Fallback if AJAX fails
                        window.location.href = 'login.php';
                    }
                });
            });
        
            // Auto-refresh the page every 30 seconds to check for status updates
            setTimeout(function() {
                location.reload();
            }, 30000);
        });
    </script>
</body>
</html>
