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

// Initialize messages array
$messages = [];

// Handle profile update
if (isset($_POST['update_profile'])) {
    $firstName = sanitizeInput($_POST['first_name']);
    $lastName = sanitizeInput($_POST['last_name']);
    $phone = sanitizeInput($_POST['phone']);
    $address = sanitizeInput($_POST['address']);
    $city = sanitizeInput($_POST['city']);
    $state = sanitizeInput($_POST['state']);
    $zipCode = sanitizeInput($_POST['zip_code']);
    $country = sanitizeInput($_POST['country']);
    
    // Update user details
    $stmt = $conn->prepare("UPDATE users SET 
                          first_name = ?, 
                          last_name = ?, 
                          phone = ?, 
                          address = ?, 
                          city = ?, 
                          state = ?, 
                          zip_code = ?, 
                          country = ?, 
                          updated_at = NOW() 
                          WHERE id = ?");
    $stmt->bind_param("ssssssssi", $firstName, $lastName, $phone, $address, $city, $state, $zipCode, $country, $userId);
    
    if ($stmt->execute()) {
        $messages['profile'] = [
            'type' => 'success',
            'text' => 'Profile updated successfully!'
        ];
        
        // Refresh user data
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
    } else {
        $messages['profile'] = [
            'type' => 'danger',
            'text' => 'Error updating profile: ' . $conn->error
        ];
    }
}

// Handle password change
if (isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validate current password
    if (!password_verify($currentPassword, $user['password'])) {
        $messages['password'] = [
            'type' => 'danger',
            'text' => 'Current password is incorrect.'
        ];
    } 
    // Validate new password
    elseif (!validatePassword($newPassword)) {
        $messages['password'] = [
            'type' => 'danger',
            'text' => 'New password must be at least 8 characters and contain both letters and numbers.'
        ];
    }
    // Validate password confirmation
    elseif ($newPassword !== $confirmPassword) {
        $messages['password'] = [
            'type' => 'danger',
            'text' => 'New passwords do not match.'
        ];
    } 
    else {
        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        
        // Update password
        $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $hashedPassword, $userId);
        
        if ($stmt->execute()) {
            $messages['password'] = [
                'type' => 'success',
                'text' => 'Password changed successfully!'
            ];
        } else {
            $messages['password'] = [
                'type' => 'danger',
                'text' => 'Error changing password: ' . $conn->error
            ];
        }
    }
}

// Handle profile image upload
if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    $file = $_FILES['profile_image'];
    
    // Validate file type
    if (!in_array($file['type'], $allowedTypes)) {
        $messages['image'] = [
            'type' => 'danger',
            'text' => 'Invalid file type. Only JPG, PNG, and GIF are allowed.'
        ];
    }
    // Validate file size
    elseif ($file['size'] > $maxSize) {
        $messages['image'] = [
            'type' => 'danger',
            'text' => 'File size exceeds the maximum limit of 5MB.'
        ];
    }
    else {
        // Create uploads directory if it doesn't exist
        $uploadDir = '../uploads/profile_images/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Generate unique filename
        $filename = $userId . '_' . time() . '_' . basename($file['name']);
        $targetPath = $uploadDir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            // Update profile image in database
            $imagePath = 'uploads/profile_images/' . $filename;
            $stmt = $conn->prepare("UPDATE users SET profile_image = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $imagePath, $userId);
            
            if ($stmt->execute()) {
                $messages['image'] = [
                    'type' => 'success',
                    'text' => 'Profile image updated successfully!'
                ];
                
                // Refresh user data
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
            } else {
                $messages['image'] = [
                    'type' => 'danger',
                    'text' => 'Error updating profile image in database: ' . $conn->error
                ];
            }
        } else {
            $messages['image'] = [
                'type' => 'danger',
                'text' => 'Error uploading file. Please try again.'
            ];
        }
    }
}

// Get user notifications
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->bind_param("i", $userId);
$stmt->execute();
$notificationsResult = $stmt->get_result();
$notifications = [];
while ($notification = $notificationsResult->fetch_assoc()) {
    $notifications[] = $notification;
}

// Get active tab from URL parameter or default to 'profile'
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Auction Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            padding-bottom: 70px; /* Space for the fixed navbar */
            background-color: #f8f9fa;
        }
        .profile-header {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            position: relative;
        }
        .profile-image-container {
            position: relative;
            width: 100px;
            height: 100px;
            margin: 0 auto 15px;
        }
        .profile-image {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .profile-image-edit {
            position: absolute;
            bottom: 0;
            right: 0;
            background-color: #ff6b6b;
            color: white;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .verification-badge {
            background-color: #28a745;
            color: white;
            font-size: 0.8rem;
            padding: 3px 8px;
            border-radius: 10px;
            display: inline-block;
            margin-top: 5px;
        }
        .nav-tabs .nav-link {
            color: #495057;
            border: none;
            padding: 10px 15px;
            border-radius: 0;
            border-bottom: 2px solid transparent;
        }
        .nav-tabs .nav-link.active {
            color: #ff6b6b;
            background-color: transparent;
            border-bottom: 2px solid #ff6b6b;
        }
        .tab-content {
            padding: 20px 0;
        }
        .form-label {
            font-weight: 500;
        }
        .notification-item {
            border-left: 3px solid #ff6b6b;
            padding: 10px 15px;
            margin-bottom: 10px;
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .notification-time {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .notification-unread {
            background-color: #f8f9fa;
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
        .logout-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 50px;
            padding: 8px 20px;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        .logout-btn:hover {
            background-color: #c82333;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container mt-4 mb-5">
        <div class="profile-header text-center">
            <div class="profile-image-container">
                <img src="<?php echo isset($user['profile_image']) ? '../' . $user['profile_image'] : '../assets/img/default-profile.png'; ?>" alt="Profile Image" class="profile-image">
                <label for="profile-image-upload" class="profile-image-edit">
                    <i class="fas fa-camera"></i>
                </label>
                <form id="image-upload-form" method="post" enctype="multipart/form-data" style="display: none;">
                    <input type="file" id="profile-image-upload" name="profile_image" accept="image/*" onchange="document.getElementById('image-upload-form').submit();">
                </form>
            </div>
            <h2 class="h4 mb-0"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
            <p class="mb-0"><?php echo htmlspecialchars($user['email']); ?></p>
            <div class="verification-badge">
                <i class="fas fa-check-circle me-1"></i> Verified by Admin
            </div>
        </div>
        
        <ul class="nav nav-tabs" id="profileTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $activeTab === 'profile' ? 'active' : ''; ?>" id="profile-tab" data-bs-toggle="tab" href="#profile" role="tab" aria-controls="profile" aria-selected="<?php echo $activeTab === 'profile' ? 'true' : 'false'; ?>">
                    <i class="fas fa-user me-1"></i> Profile
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $activeTab === 'security' ? 'active' : ''; ?>" id="security-tab" data-bs-toggle="tab" href="#security" role="tab" aria-controls="security" aria-selected="<?php echo $activeTab === 'security' ? 'true' : 'false'; ?>">
                    <i class="fas fa-lock me-1"></i> Security
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $activeTab === 'notifications' ? 'active' : ''; ?>" id="notifications-tab" data-bs-toggle="tab" href="#notifications" role="tab" aria-controls="notifications" aria-selected="<?php echo $activeTab === 'notifications' ? 'true' : 'false'; ?>">
                    <i class="fas fa-bell me-1"></i> Notifications
                </a>
            </li>
        </ul>
        
        <div class="tab-content" id="profileTabsContent">
            <!-- Profile Tab -->
            <div class="tab-pane fade <?php echo $activeTab === 'profile' ? 'show active' : ''; ?>" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                <?php if (isset($messages['profile'])): ?>
                <div class="alert alert-<?php echo $messages['profile']['type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo $messages['profile']['text']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <form method="post" action="profile.php?tab=profile">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                        <div class="form-text">Email cannot be changed. Contact support if needed.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <input type="text" class="form-control" id="address" name="address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="city" class="form-label">City</label>
                            <input type="text" class="form-control" id="city" name="city" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="state" class="form-label">State/Province</label>
                            <input type="text" class="form-control" id="state" name="state" value="<?php echo htmlspecialchars($user['state'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="zip_code" class="form-label">ZIP/Postal Code</label>
                            <input type="text" class="form-control" id="zip_code" name="zip_code" value="<?php echo htmlspecialchars($user['zip_code'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="country" class="form-label">Country</label>
                            <input type="text" class="form-control" id="country" name="country" value="<?php echo htmlspecialchars($user['country'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Account Type</label>
                        <input type="text" class="form-control" value="<?php echo ucfirst(htmlspecialchars($user['role'])); ?>" disabled>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Member Since</label>
                        <input type="text" class="form-control" value="<?php echo formatDate($user['created_at'], 'F d, Y'); ?>" disabled>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
                </form>
            </div>
            
            <!-- Security Tab -->
            <div class="tab-pane fade <?php echo $activeTab === 'security' ? 'show active' : ''; ?>" id="security" role="tabpanel" aria-labelledby="security-tab">
                <?php if (isset($messages['password'])): ?>
                <div class="alert alert-<?php echo $messages['password']['type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo $messages['password']['text']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Change Password</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="profile.php?tab=security">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <div class="form-text">Password must be at least 8 characters and contain both letters and numbers.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            
                            <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                        </form>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Account Security</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Account Status</label>
                            <div class="verification-badge">
                                <i class="fas fa-check-circle me-1"></i> Verified by Admin
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Last Login</label>
                            <p class="mb-0"><?php echo isset($user['last_login']) ? formatDate($user['last_login'], 'F d, Y H:i') : 'Not available'; ?></p>
                        </div>
                        
                        <a href="../logout.php" class="btn logout-btn">
                            <i class="fas fa-sign-out-alt me-1"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Notifications Tab -->
            <div class="tab-pane fade <?php echo $activeTab === 'notifications' ? 'show active' : ''; ?>" id="notifications" role="tabpanel" aria-labelledby="notifications-tab">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Notifications</h5>
                        <a href="#" class="btn btn-sm btn-outline-primary">Mark All as Read</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($notifications)): ?>
                            <p class="text-muted">No notifications found.</p>
                        <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item <?php echo $notification['is_read'] ? '' : 'notification-unread'; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                        <span class="notification-time"><?php echo formatDate($notification['created_at'], 'M d, H:i'); ?></span>
                                    </div>
                                    <p class="mb-0"><?php echo htmlspecialchars($notification['message']); ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Notification Settings</h5>
                    </div>
                    <div class="card-body">
                        <form>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="emailNotifications" checked>
                                <label class="form-check-label" for="emailNotifications">Email Notifications</label>
                            </div>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="bidUpdates" checked>
                                <label class="form-check-label" for="bidUpdates">Bid Updates</label>
                            </div>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="auctionAlerts" checked>
                                <label class="form-check-label" for="auctionAlerts">Auction Alerts</label>
                            </div>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="systemNotifications" checked>
                                <label class="form-check-label" for="systemNotifications">System Notifications</label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Save Preferences</button>
                        </form>
                    </div>
                </div>
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
</body>
</html>
