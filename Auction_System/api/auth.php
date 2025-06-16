<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

startSession();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    switch ($action) {
        case 'login':
            handleLogin();
            break;
        case 'signup':
            handleSignup();
            break;
        case 'logout':
            handleLogout();
            break;
        case 'approve_user':
            handleApproveUser();
            break;
        case 'reject_user':
            handleRejectUser();
            break;
        default:
            jsonResponse(false, 'Invalid action');
    }
}

// Handle login
function handleLogin() {
    $email = isset($_POST['email']) ? sanitizeInput($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Debug logging
    error_log("Login attempt for: " . $email);
    
    // Validate inputs
    if (empty($email) || empty($password)) {
        error_log("Login failed: Empty email or password");
        jsonResponse(false, 'Email and password are required');
    }
    
    if (!validateEmail($email)) {
        error_log("Login failed: Invalid email format: " . $email);
        jsonResponse(false, 'Invalid email format');
    }
    
    // Connect to database
    $conn = getDbConnection();
    
    // Check if user exists
    $stmt = $conn->prepare("SELECT id, email, password, role, status, is_verified, rejection_reason FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        error_log("Login failed: User not found: " . $email);
        jsonResponse(false, 'Invalid email or password');
    }
    
    $user = $result->fetch_assoc();
    
    // Debug user data
    error_log("User found: " . json_encode($user));
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        error_log("Login failed: Invalid password for: " . $email);
        
        // Special case for admin - if it's the default admin and password doesn't match, update it
        if ($email === 'admin@auction.com' && $password === 'admin123') {
            $hashedPassword = password_hash('admin123', PASSWORD_BCRYPT);
            $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ? AND role = 'admin'");
            $updateStmt->bind_param("ss", $hashedPassword, $email);
            $updateStmt->execute();
            
            error_log("Admin password reset to default");
            
            // Set session variables for admin
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['status'] = $user['status'] ?? 'approved';
            
            jsonResponse(true, 'Login successful', ['redirect' => '../admin/dashboard.php']);
        } else {
            jsonResponse(false, 'Invalid email or password');
        }
    }
    
    // Check if user is admin
    if ($user['role'] === 'admin') {
        // Admin can always log in
    $_SESSION['user'] = [
    'user_id' => $user['id'],
    'email' => $user['email'],
    'role' => $user['role'],
    'status' => $user['status'] ?? 'approved'
];
        error_log("Admin login successful: " . $email);
        jsonResponse(true, 'Login successful', ['redirect' => '../admin/dashboard.php']);
    }
    
    // Check if user account is approved
    if (isset($user['status']) && $user['status'] === 'rejected') {
        $reason = !empty($user['rejection_reason']) ? $user['rejection_reason'] : 'No reason provided.';
        error_log("Login failed: User rejected: " . $email);
        jsonResponse(false, 'Your account has been rejected. Reason: ' . $reason);
    }
    
    if (isset($user['status']) && $user['status'] === 'pending') {
        // Set session for pending users so they can see the waiting page
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['status'] = 'pending';
        
        error_log("Login pending: User pending approval: " . $email);
        jsonResponse(true, 'Your account is pending approval. Please wait for admin verification.', 
            ['redirect' => '../waiting.php', 'status' => 'pending']);
    }
    
    if (isset($user['status']) && $user['status'] === 'deactivated') {
        error_log("Login failed: User deactivated: " . $email);
        jsonResponse(false, 'Your account has been deactivated. Please contact the administrator.');
    }
    
    // If we get here, the user is approved
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['status'] = $user['status'] ?? 'approved';
    
    // Log successful login
    error_log("User login successful: " . $email . " with role: " . $user['role']);
    
    jsonResponse(true, 'Login successful', ['redirect' => './index.php']);
}

// Fix the handleSignup function to properly store customer data
function handleSignup() {
    $email = isset($_POST['email']) ? sanitizeInput($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    $role = isset($_POST['role']) ? sanitizeInput($_POST['role']) : '';
    
    // Debug logging
    error_log("Signup attempt for: " . $email . " with role: " . $role);
    
    // Validate inputs
    if (empty($email) || empty($password) || empty($confirmPassword) || empty($role)) {
        jsonResponse(false, 'All fields are required');
    }
    
    if (!validateEmail($email)) {
        jsonResponse(false, 'Invalid email format');
    }
    
    if (!validatePassword($password)) {
        jsonResponse(false, 'Password must be at least 8 characters and contain both letters and numbers');
    }
    
    if ($password !== $confirmPassword) {
        jsonResponse(false, 'Passwords do not match');
    }
    
    if ($role !== 'buyer' && $role !== 'seller') {
        jsonResponse(false, 'Invalid role selected');
    }
    
    // Connect to database
    $conn = getDbConnection();
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        jsonResponse(false, 'Email already exists');
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    
    // Insert new user with pending status
    $stmt = $conn->prepare("INSERT INTO users (email, password, role, status, is_verified) VALUES (?, ?, ?, 'pending', 0)");
    $stmt->bind_param("sss", $email, $hashedPassword, $role);
    
    if ($stmt->execute()) {
        // Get the new user's ID
        $userId = $conn->insert_id;
        
        // Set session variables for the new user
        $_SESSION['user_id'] = $userId;
        $_SESSION['email'] = $email;
        $_SESSION['role'] = $role;
        $_SESSION['status'] = 'pending';
        
        // Log successful registration
        error_log("User registration successful: " . $email . " with role: " . $role . " and ID: " . $userId);
        
        // Notify admin about new user registration
        $adminEmail = 'admin@auction.com';
        $subject = "New User Registration Pending Approval";
        $message = "A new user has registered and is pending approval:\n\nEmail: $email\nRole: $role\n\nPlease log in to the admin panel to review this registration.";
        sendEmailNotification($adminEmail, $subject, $message);
        
        jsonResponse(true, 'Registration successful. Your account is pending approval by admin. You will be notified once your account has been reviewed.', 
            ['redirect' => '../waiting.php']);
    } else {
        error_log("User registration failed: " . $conn->error);
        jsonResponse(false, 'Registration failed: ' . $conn->error);
    }
}

// Fix the handleApproveUser function
function handleApproveUser() {
    // Check if user is admin
    if (!isAdmin()) {
        jsonResponse(false, 'Unauthorized access');
    }
    
    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    
    if ($userId <= 0) {
        jsonResponse(false, 'Invalid user ID');
    }
    
    // Connect to database
    $conn = getDbConnection();
    
    // Get user email for notification
    $stmt = $conn->prepare("SELECT email, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        jsonResponse(false, 'User not found');
    }
    
    $user = $result->fetch_assoc();
    $userEmail = $user['email'];
    
    // Update user status to approved
    $stmt = $conn->prepare("UPDATE users SET status = 'approved', is_verified = 1 WHERE id = ?");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        // Send notification email
        $subject = "Your Account Has Been Approved";
        $message = "Dear User,\n\nYour account has been approved. You can now log in and access all features of the platform.\n\nThank you,\nAuction Platform Team";
        sendEmailNotification($userEmail, $subject, $message);
        
        // Log the action
        logAdminAction('APPROVE_USER', $userId, "Approved user: $userEmail with role: {$user['role']}");
        
        jsonResponse(true, 'User approved successfully. Email notification sent.');
    } else {
        jsonResponse(false, 'Failed to approve user: ' . $conn->error);
    }
}

// Handle reject user
function handleRejectUser() {
    // Check if user is admin
    if (!isAdmin()) {
        jsonResponse(false, 'Unauthorized access');
    }
    
    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $reason = isset($_POST['reason']) ? sanitizeInput($_POST['reason']) : '';
    
    if ($userId <= 0) {
        jsonResponse(false, 'Invalid user ID');
    }
    
    // Connect to database
    $conn = getDbConnection();
    
    // Get user email for notification
    $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        jsonResponse(false, 'User not found');
    }
    
    $userEmail = $result->fetch_assoc()['email'];
    
    // Update user status to rejected
    $stmt = $conn->prepare("UPDATE users SET status = 'rejected', rejection_reason = ? WHERE id = ?");
    $stmt->bind_param("si", $reason, $userId);
    
    if ($stmt->execute()) {
        // Send notification email
        $subject = "Your Account Registration Status";
        $message = "Dear User,\n\nUnfortunately, your account registration has been rejected";
        if (!empty($reason)) {
            $message .= " for the following reason:\n\n$reason";
        }
        $message .= "\n\nIf you believe this is an error, please contact our support team.\n\nThank you,\nAuction Platform Team";
        sendEmailNotification($userEmail, $subject, $message);
        
        // Log the action
        logAdminAction('REJECT_USER', $userId, "Rejected user: $userEmail. Reason: $reason");
        
        jsonResponse(true, 'User rejected successfully. Email notification sent.');
    } else {
        jsonResponse(false, 'Failed to reject user: ' . $conn->error);
    }
}

// Handle logout
function handleLogout() {
    // Clear session
    session_unset();
    session_destroy();
    
    jsonResponse(true, 'Logout successful', ['redirect' => '../login.php']);
}
?>
