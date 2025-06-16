<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/notification-functions.php';

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = isset($_SESSION['role']) ? $_SESSION['role'] : '';

// Get user notification settings
$settings = getUserNotificationSettings($userId);
if (!$settings) {
    // Create default settings if they don't exist
    $defaultSettings = [
        'email_notifications' => 1,
        'browser_notifications' => 1,
        'auction_updates' => 1,
        'bid_alerts' => 1,
        'system_messages' => 1
    ];
    updateNotificationSettings($userId, $defaultSettings);
    $settings = getUserNotificationSettings($userId);
}

// Get all notifications for the user
$notifications = getUserNotifications($userId, 10);
$unreadCount = getUnreadNotificationCount($userId);
$totalCount = count(getUserNotifications($userId));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Notifications</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/notifications.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">
                <svg class="logo-icon me-2" style="width: 30px; height: 30px;" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                    <path d="M30 30 L70 30 L70 70 L30 70 Z" stroke="#FFF" stroke-width="6" fill="none" />
                    <path d="M20 20 L80 20 L80 80 L20 80 Z" stroke="#FFF" stroke-width="6" fill="none" transform="rotate(45 50 50)" />
                </svg>
                Auction Platform
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <?php if ($userRole === 'seller'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="my-listings.php">My Listings</a>
                    </li>
                    <?php endif; ?>
                    <?php if ($userRole === 'buyer'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="my-bids.php">My Bids</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="notifications.php">
                            Notifications
                            <?php if ($unreadCount > 0): ?>
                                <span class="badge bg-danger"><?php echo $unreadCount; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" id="logout-link">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">My Notifications</h5>
                        <div>
                            <?php if ($unreadCount > 0): ?>
                                <button id="mark-all-read" class="btn btn-sm btn-outline-primary me-2">
                                    <i class="fas fa-check-double"></i> Mark All as Read
                                </button>
                            <?php endif; ?>
                            <button id="refresh-notifications" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="notification-list" id="notification-list">
                            <?php if (empty($notifications)): ?>
                                <div class="notification-empty">
                                    <i class="fas fa-bell-slash"></i>
                                    <h5>No Notifications</h5>
                                    <p>You don't have any notifications yet.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <div class="notification-item d-flex <?php echo $notification['is_read'] ? '' : 'unread'; ?>" data-id="<?php echo $notification['id']; ?>">
                                        <div class="notification-icon">
                                            <i class="<?php echo getNotificationIconClass($notification['type']); ?>"></i>
                                        </div>
                                        <div class="notification-content">
                                            <div class="notification-title">
                                                <?php echo $notification['title']; ?>
                                                <?php if (!$notification['is_read']): ?>
                                                    <span class="badge bg-primary notification-badge">New</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="notification-message"><?php echo $notification['message']; ?></div>
                                            <div class="notification-meta">
                                                <span><?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?></span>
                                                <span><?php echo getNotificationTypeBadge($notification['type']); ?></span>
                                            </div>
                                        </div>
                                        <div class="notification-actions">
                                            <?php if (!$notification['is_read']): ?>
                                                <button class="btn btn-sm btn-outline-primary mark-read" data-id="<?php echo $notification['id']; ?>" title="Mark as Read">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-outline-danger delete-notification" data-id="<?php echo $notification['id']; ?>" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <div class="text-center p-3">
                                    <button id="load-more" class="btn btn-outline-primary">Load More</button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Notification Settings</h5>
                    </div>
                    <div class="card-body">
                        <form id="notification-settings-form">
                            <div class="notification-settings-section">
                                <h5>Delivery Methods</h5>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" value="1" <?php echo $settings['email_notifications'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="email_notifications">Email Notifications</label>
                                    <div class="form-text">Receive notifications via email</div>
                                </div>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="browser_notifications" name="browser_notifications" value="1" <?php echo $settings['browser_notifications'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="browser_notifications">Browser Notifications</label>
                                    <div class="form-text">Receive notifications in your browser</div>
                                </div>
                            </div>
                            
                            <div class="notification-settings-section">
                                <h5>Notification Types</h5>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="auction_updates" name="auction_updates" value="1" <?php echo $settings['auction_updates'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="auction_updates">Auction Updates</label>
                                    <div class="form-text">Notifications about auction status changes</div>
                                </div>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="bid_alerts" name="bid_alerts" value="1" <?php echo $settings['bid_alerts'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="bid_alerts">Bid Alerts</label>
                                    <div class="form-text">Notifications about bids on your auctions or when you're outbid</div>
                                </div>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="system_messages" name="system_messages" value="1" <?php echo $settings['system_messages'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="system_messages">System Messages</label>
                                    <div class="form-text">Notifications about system updates and important announcements</div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">Save Settings</button>
                        </form>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Notification Stats</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <div>Total Notifications:</div>
                            <div><strong id="total-count"><?php echo $totalCount; ?></strong></div>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <div>Unread Notifications:</div>
                            <div><strong id="unread-count"><?php echo $unreadCount; ?></strong></div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <div>Last Notification:</div>
                            <div><strong><?php echo !empty($notifications) ? date('M j, Y', strtotime($notifications[0]['created_at'])) : 'N/A'; ?></strong></div>
                        </div>
                    </div>
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
                    <p>Are you sure you want to delete this notification? This action cannot be undone.</p>
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
    <script src="../assets/js/notifications.js"></script>
    <script>
        $(document).ready(function() {
            // Handle delete notification
            $(document).on('click', '.delete-notification', function(e) {
                e.stopPropagation();
                const notificationId = $(this).data('id');
                
                // Store notification ID for deletion
                $('#confirm-delete').data('id', notificationId);
                
                // Show confirmation modal
                $('#deleteModal').modal('show');
            });
            
            // Handle confirm delete
            $('#confirm-delete').click(function() {
                const notificationId = $(this).data('id');
                
                const formData = new FormData();
                formData.append('action', 'delete_notification');
                formData.append('notification_id', notificationId);
                
                fetch('../api/notifications.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Hide modal
                        $('#deleteModal').modal('hide');
                        
                        // Remove notification from list
                        $(`.notification-item[data-id="${notificationId}"]`).fadeOut(300, function() {
                            $(this).remove();
                            
                            // Check if there are no notifications left
                            if ($('.notification-item').length === 0) {
                                $('#notification-list').html(`
                                    <div class="notification-empty">
                                        <i class="fas fa-bell-slash"></i>
                                        <h5>No Notifications</h5>
                                        <p>You don't have any notifications yet.</p>
                                    </div>
                                `);
                                
                                // Hide load more button
                                $('#load-more').hide();
                            }
                        });
                        
                        // Update counts
                        $('#total-count').text(parseInt($('#total-count').text()) - 1);
                        $('#unread-count').text(data.data.unread_count);
                    }
                })
                .catch(error => {
                    console.error('Error deleting notification:', error);
                });
            });
            
            // Handle refresh notifications
            $('#refresh-notifications').click(function() {
                location.reload();
            });
            
            // Handle load more
            $('#load-more').click(function() {
                const currentCount = $('.notification-item').length;
                
                const formData = new FormData();
                formData.append('action', 'get_notifications');
                formData.append('limit', 10);
                formData.append('offset', currentCount);
                
                fetch('../api/notifications.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.notifications.length > 0) {
                        let html = '';
                        
                        data.data.notifications.forEach(notification => {
                            html += `
                                <div class="notification-item d-flex ${notification.is_read ? '' : 'unread'}" data-id="${notification.id}">
                                    <div class="notification-icon">
                                        <i class="${notification.icon_class}"></i>
                                    </div>
                                    <div class="notification-content">
                                        <div class="notification-title">
                                            ${notification.title}
                                            ${!notification.is_read ? '<span class="badge bg-primary notification-badge">New</span>' : ''}
                                        </div>
                                        <div class="notification-message">${notification.message}</div>
                                        <div class="notification-meta">
                                            <span>${notification.formatted_date}</span>
                                            <span>${notification.type_badge}</span>
                                        </div>
                                    </div>
                                    <div class="notification-actions">
                                        ${!notification.is_read ? `
                                            <button class="btn btn-sm btn-outline-primary mark-read" data-id="${notification.id}" title="Mark as Read">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        ` : ''}
                                        <button class="btn btn-sm btn-outline-danger delete-notification" data-id="${notification.id}" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            `;
                        });
                        
                        // Remove load more button
                        $('#load-more').parent().remove();
                        
                        // Append new notifications
                        $('#notification-list').append(html);
                        
                        // Add load more button if there are more notifications
                        if (data.data.has_more) {
                            $('#notification-list').append(`
                                <div class="text-center p-3">
                                    <button id="load-more" class="btn btn-outline-primary">Load More</button>
                                </div>
                            `);
                        }
                    } else if (data.success && data.data.notifications.length === 0) {
                        // No more notifications to load
                        $('#load-more').parent().remove();
                    }
                })
                .catch(error => {
                    console.error('Error loading more notifications:', error);
                });
            });
            
            // Handle logout
            $('#logout-link').click(function(e) {
                e.preventDefault();
                
                fetch('../api/auth.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=logout'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = '../login.php';
                    }
                })
                .catch(error => {
                    console.error('Error logging out:', error);
                });
            });
        });
    </script>
</body>
</html>
