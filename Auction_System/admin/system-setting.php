<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/settings-functions.php';

// Check if user is logged in and is admin
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php?error=unauthorized');
    exit;
}

// Run the database setup script if needed
if (!file_exists(__DIR__ . '/../database/system_settings_tables.php')) {
    die("System settings tables setup script not found");
} else {
    include_once __DIR__ . '/../database/system_settings_tables.php';
}

// Handle AJAX request for admin users
if (isset($_GET['action']) && $_GET['action'] === 'get_admins') {
    // Debug information
    error_log("Fetching admin users");
    
    $admins = getAdminUsers();
    
    if (empty($admins)) {
        echo '<tr><td colspan="4" class="text-center">No admin users found</td></tr>';
    } else {
        foreach ($admins as $admin) {
            $isCurrentUser = $admin['id'] == $_SESSION['user_id'];
            $statusChecked = $admin['status'] === 'approved' ? 'checked' : '';
            $statusDisabled = $isCurrentUser ? 'disabled' : '';
            $name = isset($admin['name']) && !empty($admin['name']) ? htmlspecialchars($admin['name']) : 'N/A';
            
            echo '<tr>
                <td>' . htmlspecialchars($admin['email']) . '</td>
                <td>' . htmlspecialchars($admin['status']) . '</td>
                <td>' . date('Y-m-d H:i', strtotime($admin['created_at'])) . '</td>
                <td>
                    <div class="form-check form-switch">
                        <input class="form-check-input admin-status-toggle" type="checkbox" 
                            data-admin-id="' . $admin['id'] . '" 
                            ' . $statusChecked . ' ' . $statusDisabled . '>
                        <label class="form-check-label">' . ($isCurrentUser ? 'Current User' : 'Active') . '</label>
                    </div>
                </td>
            </tr>';
        }
    }
    
    exit;
}

// Get all settings
$settings = getAllSettings();

// Group settings by type for easier display
$platformSettings = [];
$featureToggles = [];

foreach ($settings as $setting) {
    if ($setting['setting_type'] === 'toggle') {
        $featureToggles[] = $setting;
    } else {
        $platformSettings[] = $setting;
    }
}

// Page title
$pageTitle = "System Settings";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Admin Dashboard</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Admin CSS -->
    <link rel="stylesheet" href="../assets/css/admin.css">
    
    <style>
        .form-switch .form-check-input {
            width: 3em;
            height: 1.5em;
            cursor: pointer;
        }
        .card-header {
            font-weight: 600;
        }
        .settings-section {
            margin-bottom: 2rem;
        }
        #toastContainer {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
        }
        .feature-item {
            padding: 15px;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .feature-item:hover {
            background-color: rgba(0,0,0,0.03);
        }
        .form-check-label {
            cursor: pointer;
        }
        .info-icon {
            color: #6c757d;
            cursor: pointer;
            transition: color 0.2s;
        }
        .info-icon:hover {
            color: var(--primary-color);
        }
        .card {
            box-shadow: var(--card-shadow);
            border: none;
            margin-bottom: 20px;
        }
        .card-header {
            border-bottom: 1px solid rgba(0,0,0,0.1);
            padding: 15px 20px;
        }
        .card-header i {
            margin-right: 8px;
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .btn-primary:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(255, 107, 107, 0.25);
        }
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .table th {
            font-weight: 600;
            color: #6c757d;
        }
        .table td {
            vertical-align: middle;
        }
        .admin-table-container {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="admin-sidebar">
            <?php include '../includes/admin-sidebar.php'; ?>
        </div>
        
        <!-- Main content -->
        <div class="admin-content">
            <div class="admin-navbar">
                <div class="menu-toggle">
                    <i class="fas fa-bars"></i>
                </div>
                <div class="navbar-right">
                    <div class="admin-profile">
                        <i class="fas fa-user-circle"></i>
                    </div>
                </div>
            </div>
            
            <div class="content-area">
                <div class="content-header">
                    <h1><?php echo $pageTitle; ?></h1>
                </div>
                
                <!-- Toast container for notifications -->
                <div id="toastContainer"></div>
                
                <div class="row">
                    <!-- Platform Settings -->
                    <div class="col-lg-6 settings-section">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <i class="fas fa-cogs"></i> Platform Settings
                            </div>
                            <div class="card-body">
                                <form id="platformSettingsForm">
                                    <?php foreach ($platformSettings as $setting): ?>
                                        <div class="mb-4">
                                            <label for="setting_<?php echo $setting['setting_key']; ?>" class="form-label d-flex align-items-center">
                                                <?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?>
                                                <i class="fas fa-info-circle ms-2 info-icon" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($setting['setting_description']); ?>"></i>
                                            </label>
                                            
                                            <?php if ($setting['setting_type'] === 'textarea'): ?>
                                                <textarea class="form-control" id="setting_<?php echo $setting['setting_key']; ?>" name="setting_<?php echo $setting['setting_key']; ?>" rows="5"><?php echo htmlspecialchars($setting['setting_value']); ?></textarea>
                                            
                                            <?php elseif ($setting['setting_type'] === 'dropdown'): ?>
                                                <select class="form-select" id="setting_<?php echo $setting['setting_key']; ?>" name="setting_<?php echo $setting['setting_key']; ?>">
                                                    <?php 
                                                    $options = explode(',', $setting['setting_options']);
                                                    $selectedValues = explode(',', $setting['setting_value']);
                                                    foreach ($options as $option): 
                                                    ?>
                                                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo in_array($option, $selectedValues) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($option); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            
                                            <?php elseif ($setting['setting_type'] === 'number'): ?>
                                                <input type="number" class="form-control" id="setting_<?php echo $setting['setting_key']; ?>" name="setting_<?php echo $setting['setting_key']; ?>" value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                            
                                            <?php else: ?>
                                                <input type="text" class="form-control" id="setting_<?php echo $setting['setting_key']; ?>" name="setting_<?php echo $setting['setting_key']; ?>" value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i> Save Settings
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Feature Toggles and Admin Management -->
                    <div class="col-lg-6 settings-section">
                        <!-- Feature Toggles -->
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <i class="fas fa-toggle-on"></i> Feature Toggles
                            </div>
                            <div class="card-body">
                                <div class="list-group">
                                    <?php foreach ($featureToggles as $toggle): ?>
                                        <div class="feature-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1"><?php echo ucwords(str_replace('_', ' ', $toggle['setting_key'])); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($toggle['setting_description']); ?></small>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input feature-toggle" type="checkbox" 
                                                    id="toggle_<?php echo $toggle['setting_key']; ?>" 
                                                    data-key="<?php echo $toggle['setting_key']; ?>" 
                                                    <?php echo $toggle['setting_value'] == '1' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="toggle_<?php echo $toggle['setting_key']; ?>">
                                                    <?php echo $toggle['setting_value'] == '1' ? 'Enabled' : 'Disabled'; ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Admin Management -->
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <i class="fas fa-user-shield"></i> Admin Management
                            </div>
                            <div class="card-body">
                                <form id="addAdminForm" class="mb-4">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="email" class="form-label">Email</label>
                                            <input type="email" class="form-control" id="email" name="email" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="password" class="form-label">Password</label>
                                            <input type="password" class="form-control" id="password" name="password" required>
                                        </div>
                                    </div>
                                    <div class="form-text mb-3">Password must be at least 8 characters long.</div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i> Add Admin
                                    </button>
                                </form>
                                
                                <h5 class="mt-4 mb-3">Admin Users</h5>
                                <div class="admin-table-container">
                                    <table class="table table-striped table-hover mb-0" id="adminTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Email</th>
                                                <th>Status</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Admin users will be loaded via JavaScript -->
                                            <tr>
                                                <td colspan="4" class="text-center">
                                                    <i class="fas fa-spinner fa-spin me-2"></i> Loading...
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- System Settings JS -->
    <script src="../assets/js/system-settings.js"></script>
</body>
</html>
