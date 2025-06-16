<?php
require_once '../includes/functions.php';
require_once '../config/database.php';

// Start session and check if user is logged in
startSession();
if (!isLoggedIn()) {
    redirect('../login.php');
}

// Check if user is a seller
if ($_SESSION['role'] !== 'seller' && $_SESSION['role'] !== 'admin') {
    // Redirect non-sellers to dashboard with error message
    $_SESSION['error'] = "Only sellers can create auctions.";
    redirect('dashboard.php');
}

// Check if user is verified
if ($_SESSION['status'] !== 'approved') {
    // Redirect unverified users to dashboard with error message
    $_SESSION['error'] = "Your account must be verified by an admin before you can create auctions.";
    redirect('dashboard.php');
}

// Get database connection
$conn = getDbConnection();

// Get all categories for dropdown
$categories = getAllCategories();

// Process form submission
$errors = [];
$success = false;
$auctionData = [
    'title' => '',
    'description' => '',
    'category_id' => '',
    'start_price' => '',
    'reserve_price' => '',
    'start_date' => '',
    'end_date' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $auctionData['title'] = sanitizeInput($_POST['title'] ?? '');
    $auctionData['description'] = sanitizeInput($_POST['description'] ?? '');
    $auctionData['category_id'] = (int)($_POST['category_id'] ?? 0);
    $auctionData['start_price'] = (float)($_POST['start_price'] ?? 0);
    $auctionData['reserve_price'] = !empty($_POST['reserve_price']) ? (float)$_POST['reserve_price'] : null;
    $auctionData['start_date'] = sanitizeInput($_POST['start_date'] ?? '');
    $auctionData['end_date'] = sanitizeInput($_POST['end_date'] ?? '');
    
    // Validate title
    if (empty($auctionData['title'])) {
        $errors[] = "Title is required.";
    } elseif (strlen($auctionData['title']) > 255) {
        $errors[] = "Title must be less than 255 characters.";
    }
    
    // Validate description
    if (empty($auctionData['description'])) {
        $errors[] = "Description is required.";
    }
    
    // Validate category
    if ($auctionData['category_id'] <= 0) {
        $errors[] = "Please select a valid category.";
    }
    
    // Validate start price
    if ($auctionData['start_price'] <= 0) {
        $errors[] = "Starting price must be greater than zero.";
    }
    
    // Validate reserve price (if provided)
    if ($auctionData['reserve_price'] !== null && $auctionData['reserve_price'] <= $auctionData['start_price']) {
        $errors[] = "Reserve price must be greater than starting price.";
    }
    
    // Validate dates
    if (empty($auctionData['start_date'])) {
        $errors[] = "Start date is required.";
    }
    
    if (empty($auctionData['end_date'])) {
        $errors[] = "End date is required.";
    }
    
    // Check if start date is in the future
    $startDateTime = new DateTime($auctionData['start_date']);
    $now = new DateTime();
    if ($startDateTime < $now) {
        $errors[] = "Start date must be in the future.";
    }
    
    // Check if end date is after start date
    $endDateTime = new DateTime($auctionData['end_date']);
    if ($endDateTime <= $startDateTime) {
        $errors[] = "End date must be after start date.";
    }
    
    // Validate image upload
    $uploadedImages = [];
    $primaryImageSet = false;
    
    // Check if at least one image was uploaded
    if (empty($_FILES['images']['name'][0])) {
        $errors[] = "At least one image is required.";
    } else {
        // Process each uploaded image
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxFileSize = 5 * 1024 * 1024; // 5MB
        
        for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
            if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['images']['tmp_name'][$i];
                $name = $_FILES['images']['name'][$i];
                $type = $_FILES['images']['type'][$i];
                $size = $_FILES['images']['size'][$i];
                
                // Check file type
                if (!in_array($type, $allowedTypes)) {
                    $errors[] = "File '$name' is not a valid image type. Only JPEG, PNG, and GIF are allowed.";
                    continue;
                }
                
                // Check file size
                if ($size > $maxFileSize) {
                    $errors[] = "File '$name' exceeds the maximum file size of 5MB.";
                    continue;
                }
                
                // Generate unique filename
                $extension = pathinfo($name, PATHINFO_EXTENSION);
                $newFilename = uniqid('auction_') . '.' . $extension;
                
                // Add to uploaded images array
                $uploadedImages[] = [
                    'tmp_name' => $tmpName,
                    'filename' => $newFilename,
                    'is_primary' => isset($_POST['primary_image']) && $_POST['primary_image'] == $i
                ];
                
                // Check if this is marked as primary
                if (isset($_POST['primary_image']) && $_POST['primary_image'] == $i) {
                    $primaryImageSet = true;
                }
            } elseif ($_FILES['images']['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                // Handle upload errors
                $errors[] = "Error uploading file: " . $_FILES['images']['name'][$i];
            }
        }
        
        // If no primary image was set, make the first one primary
        if (!$primaryImageSet && !empty($uploadedImages)) {
            $uploadedImages[0]['is_primary'] = true;
        }
    }
    
    // If no errors, insert auction into database
    if (empty($errors)) {
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // Insert auction
            $stmt = $conn->prepare("INSERT INTO auctions (
                title, description, seller_id, start_price, reserve_price, 
                start_date, end_date, status, category_id, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())");
            
            $sellerId = $_SESSION['user_id'];
            $stmt->bind_param(
                "ssiddssi",
                $auctionData['title'],
                $auctionData['description'],
                $sellerId,
                $auctionData['start_price'],
                $auctionData['reserve_price'],
                $auctionData['start_date'],
                $auctionData['end_date'],
                $auctionData['category_id']
            );
            
            $stmt->execute();
            $auctionId = $conn->insert_id;
            
            // Create upload directory if it doesn't exist
            $uploadDir = '../uploads/auctions/' . $auctionId;
            
            // First ensure the parent directories exist
            if (!is_dir('../uploads')) {
                if (!mkdir('../uploads', 0755, true)) {
                    throw new Exception("Failed to create uploads directory. Please check permissions.");
                }
            }
            
            if (!is_dir('../uploads/auctions')) {
                if (!mkdir('../uploads/auctions', 0755, true)) {
                    throw new Exception("Failed to create auctions directory. Please check permissions.");
                }
            }
            
            // Now create the auction-specific directory
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    throw new Exception("Failed to create auction directory. Please check permissions.");
                }
            }
            
            // Upload images and insert into database
            foreach ($uploadedImages as $image) {
                $destination = $uploadDir . '/' . $image['filename'];
                
                // Debug information
                error_log("Moving uploaded file from {$image['tmp_name']} to {$destination}");
                
                // Check if the temporary file exists
                if (!file_exists($image['tmp_name'])) {
                    throw new Exception("Temporary file not found: {$image['tmp_name']}");
                }
                
                // Check if the destination directory is writable
                if (!is_writable(dirname($destination))) {
                    throw new Exception("Destination directory is not writable: " . dirname($destination));
                }
                
                // Try to move the file
                if (move_uploaded_file($image['tmp_name'], $destination)) {
                    // Double check if the file was actually moved
                    if (!file_exists($destination)) {
                        throw new Exception("File was not moved to destination: {$destination}");
                    }
                    
                    // Insert image record
                    $stmt = $conn->prepare("INSERT INTO auction_images (
                        auction_id, image_url, is_primary, created_at
                    ) VALUES (?, ?, ?, NOW())");
                    
                    $imageUrl = 'uploads/auctions/' . $auctionId . '/' . $image['filename'];
                    $isPrimary = $image['is_primary'] ? 1 : 0;
                    
                    $stmt->bind_param("isi", $auctionId, $imageUrl, $isPrimary);
                    $stmt->execute();
                } else {
                    // Get the PHP error message
                    $errorMsg = error_get_last();
                    throw new Exception("Failed to move uploaded file. PHP Error: " . ($errorMsg ? $errorMsg['message'] : 'Unknown error'));
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            // Set success message
            $success = true;
            $_SESSION['success'] = "Your auction has been submitted for approval. You will be notified once it's reviewed.";
            
            // Clear form data
            $auctionData = [
                'title' => '',
                'description' => '',
                'category_id' => '',
                'start_price' => '',
                'reserve_price' => '',
                'start_date' => '',
                'end_date' => '',
            ];
            
            // Create notification for admin
            $adminNotification = "New auction listing requires approval: " . $auctionData['title'];
            createAdminNotification('new_auction', $adminNotification, $auctionId);
            
            // Redirect to dashboard with success message
            redirect('dashboard.php');
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $errors[] = "An error occurred: " . $e->getMessage();
            
            // Log the error for debugging
            error_log("Auction creation error: " . $e->getMessage());
        }
    }
}

// Function to create admin notification
function createAdminNotification($type, $message, $auctionId) {
    global $conn;
    
    // Get all admin users
    $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin'");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($admin = $result->fetch_assoc()) {
        // Insert notification for each admin
        $stmt = $conn->prepare("INSERT INTO notifications (
            user_id, type, message, related_id, is_read, created_at
        ) VALUES (?, 'admin_review', ?, ?, 0, NOW())");
        
        $stmt->bind_param("isi", $admin['id'], $message, $auctionId);
        $stmt->execute();
    }
}

// Get today's date in the format required for the date input min attribute
$today = date('Y-m-d\TH:i');
$tomorrow = date('Y-m-d\TH:i', strtotime('+1 day'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Auction | Auction Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            padding-bottom: 70px; /* Space for fixed bottom navbar */
            background-color: #f8f9fa;
        }
        .form-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            border: none;
        }
        .card-header {
            border-radius: 10px 10px 0 0 !important;
            font-weight: 600;
        }
        .form-label {
            font-weight: 500;
        }
        .btn-primary {
            background-color: #4e73df;
            border-color: #4e73df;
        }
        .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2e59d9;
        }
        .image-preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 15px;
        }
        .image-preview {
            position: relative;
            width: 120px;
            height: 120px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        .image-preview:hover {
            transform: scale(1.05);
        }
        .image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .primary-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            background-color: #4e73df;
            color: white;
            font-size: 10px;
            padding: 3px 8px;
            border-radius: 20px;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        .remove-image {
            position: absolute;
            top: 8px;
            left: 8px;
            background-color: #e74a3b;
            color: white;
            border: none;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        .set-primary {
            position: absolute;
            bottom: 8px;
            left: 8px;
            right: 8px;
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            border: none;
            font-size: 11px;
            padding: 4px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .set-primary:hover {
            background-color: rgba(0, 0, 0, 0.9);
        }
        .category-select {
            max-height: 200px;
            overflow-y: auto;
        }
        .image-upload-area {
            border: 2px dashed #d1d3e2;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background-color: #f8f9fa;
        }
        .image-upload-area:hover {
            border-color: #4e73df;
            background-color: #eef0f8;
        }
        .image-upload-icon {
            font-size: 48px;
            color: #4e73df;
            margin-bottom: 15px;
        }
        .image-upload-text {
            color: #6c757d;
        }
        .form-floating > .form-control {
            height: calc(3.5rem + 2px);
            padding: 1rem 0.75rem;
        }
        .form-floating > label {
            padding: 1rem 0.75rem;
        }
        .section-title {
            color: #4e73df;
            font-weight: 600;
            margin-bottom: 20px;
            border-bottom: 2px solid #e3e6f0;
            padding-bottom: 10px;
        }
        .navbar-dark {
            background-color: #4e73df !important;
        }
        .nav-link {
            color: rgba(255, 255, 255, 0.8) !important;
        }
        .nav-link.active {
            color: white !important;
            font-weight: 600;
        }
        .form-text {
            color: #858796;
        }
        .alert {
            border-radius: 10px;
        }
        #file-input {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container mt-4 mb-5">
        <div class="form-container">
            <h1 class="h3 mb-4 text-center text-gray-800">Create New Auction</h1>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    Your auction has been submitted for approval. You will be notified once it's reviewed.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Please fix the following errors:</strong>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data" id="auction-form">
                <!-- Basic Information -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white py-3">
                        <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Basic Information</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-4">
                            <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-lg" id="title" name="title" value="<?php echo $auctionData['title']; ?>" required placeholder="Enter a descriptive title">
                            <div class="form-text">Enter a clear, descriptive title for your item (max 255 characters).</div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="5" required placeholder="Describe your item in detail..."><?php echo $auctionData['description']; ?></textarea>
                            <div class="form-text">Provide a detailed description of your item, including condition, features, and any relevant details.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select form-select-lg" id="category_id" name="category_id" required>
                                <option value="">Select a category</option>
                                <?php foreach ($categories as $category): ?>
                                    <?php if (!isset($category['parent_id']) || $category['parent_id'] === null): ?>
                                        <option value="<?php echo $category['id']; ?>" <?php echo ($auctionData['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                            <?php echo $category['name']; ?>
                                        </option>
                                        
                                        <?php 
                                        // Get child categories
                                        $childCategories = getChildCategories($category['id']);
                                        foreach ($childCategories as $childCategory): 
                                        ?>
                                            <option value="<?php echo $childCategory['id']; ?>" <?php echo ($auctionData['category_id'] == $childCategory['id']) ? 'selected' : ''; ?>>
                                                &nbsp;&nbsp;&nbsp;└ <?php echo $childCategory['name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Select the most appropriate category for your item.</div>
                        </div>
                    </div>
                </div>
                
                <!-- Pricing Information -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white py-3">
                        <h5 class="mb-0"><i class="bi bi-currency-dollar me-2"></i>Pricing Information</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label for="start_price" class="form-label">Starting Price <span class="text-danger">*</span></label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="start_price" name="start_price" min="0.01" step="0.01" value="<?php echo $auctionData['start_price']; ?>" required placeholder="0.00">
                                </div>
                                <div class="form-text">The minimum bid amount to start the auction.</div>
                            </div>
                            
                            <div class="col-md-6 mb-4">
                                <label for="reserve_price" class="form-label">Reserve Price (Optional)</label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="reserve_price" name="reserve_price" min="0.01" step="0.01" value="<?php echo $auctionData['reserve_price']; ?>" placeholder="0.00">
                                </div>
                                <div class="form-text">The minimum price you're willing to accept. Item won't sell if bids don't reach this amount.</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Auction Duration -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white py-3">
                        <h5 class="mb-0"><i class="bi bi-calendar-event me-2"></i>Auction Duration</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label for="start_date" class="form-label">Start Date & Time <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control form-control-lg" id="start_date" name="start_date" min="<?php echo $today; ?>" value="<?php echo $auctionData['start_date']; ?>" required>
                                <div class="form-text">When should the auction begin? Must be in the future.</div>
                            </div>
                            
                            <div class="col-md-6 mb-4">
                                <label for="end_date" class="form-label">End Date & Time <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control form-control-lg" id="end_date" name="end_date" min="<?php echo $tomorrow; ?>" value="<?php echo $auctionData['end_date']; ?>" required>
                                <div class="form-text">When should the auction end? Must be after the start date.</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Images -->
                <div class="card mb-4">
                    <div class="card-header bg-warning text-dark py-3">
                        <h5 class="mb-0"><i class="bi bi-images me-2"></i>Item Images</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-4">
                            <label class="form-label d-block">Upload Images <span class="text-danger">*</span></label>
                            <div class="image-upload-area" id="image-upload-area">
                                <input type="file" id="file-input" name="images[]" accept="image/jpeg, image/png, image/gif" multiple required>
                                <div class="image-upload-icon">
                                    <i class="bi bi-cloud-arrow-up"></i>
                                </div>
                                <h5>Drag & Drop or Click to Upload</h5>
                                <p class="image-upload-text">Upload up to 5 images (JPEG, PNG, or GIF, max 5MB each)</p>
                                <button type="button" class="btn btn-outline-primary mt-2">Select Files</button>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Image Preview</label>
                            <div class="image-preview-container" id="image-preview-container">
                                <!-- Image previews will be added here via JavaScript -->
                                <div class="text-center text-muted py-3 w-100" id="no-images-message">
                                    No images selected yet
                                </div>
                            </div>
                        </div>
                        
                        <input type="hidden" id="primary_image" name="primary_image" value="0">
                    </div>
                </div>
                
                <!-- Submit Button -->
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg py-3">
                        <i class="bi bi-check-circle me-2"></i>Submit Auction for Approval
                    </button>
                    <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Bottom Navigation Bar -->
    <nav class="navbar navbar-expand navbar-dark bg-dark fixed-bottom">
        <div class="container-fluid justify-content-around">
            <a class="nav-link text-center" href="dashboard.php">
                <i class="bi bi-house-door"></i><br>
                <small>Home</small>
            </a>
            <a class="nav-link text-center" href="auctions.php">
                <i class="bi bi-hammer"></i><br>
                <small>Auctions</small>
            </a>
            <a class="nav-link text-center" href="my-bids.php">
                <i class="bi bi-list-check"></i><br>
                <small>My Bids</small>
            </a>
            <a class="nav-link text-center active" href="profile.php">
                <i class="bi bi-person"></i><br>
                <small>Profile</small>
            </a>
        </div>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Image upload area functionality
        const uploadArea = document.getElementById('image-upload-area');
        const fileInput = document.getElementById('file-input');
        const previewContainer = document.getElementById('image-preview-container');
        const noImagesMessage = document.getElementById('no-images-message');
        
        // Click on upload area to trigger file input
        uploadArea.addEventListener('click', function() {
            fileInput.click();
        });
        
        // Drag and drop functionality
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            uploadArea.classList.add('bg-light');
        }
        
        function unhighlight() {
            uploadArea.classList.remove('bg-light');
        }
        
        uploadArea.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
            handleFiles(files);
        }
        
        // Handle file selection
        fileInput.addEventListener('change', function() {
            handleFiles(this.files);
        });
        
        function handleFiles(files) {
            // Clear previous previews
            previewContainer.innerHTML = '';
            
            // Check if files were selected
            if (files.length === 0) {
                previewContainer.appendChild(noImagesMessage);
                return;
            } else {
                // Hide no images message
                if (noImagesMessage.parentNode === previewContainer) {
                    previewContainer.removeChild(noImagesMessage);
                }
            }
            
            // Limit to 5 images
            const maxImages = 5;
            const numFiles = Math.min(files.length, maxImages);
            
            if (files.length > maxImages) {
                alert(`You can only upload up to ${maxImages} images. Only the first ${maxImages} will be used.`);
            }
            
            // Create preview for each file
            for (let i = 0; i < numFiles; i++) {
                const file = files[i];
                
                // Check if file is an image
                if (!file.type.match('image.*')) continue;
                
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const preview = document.createElement('div');
                    preview.className = 'image-preview';
                    preview.innerHTML = `
                        <img src="${e.target.result}" alt="Preview">
                        ${i === 0 ? '<span class="primary-badge">Primary</span>' : ''}
                        <button type="button" class="set-primary" data-index="${i}">Set as Primary</button>
                    `;
                    
                    previewContainer.appendChild(preview);
                    
                    // Add event listener to "Set as Primary" button
                    preview.querySelector('.set-primary').addEventListener('click', function() {
                        const index = this.getAttribute('data-index');
                        document.getElementById('primary_image').value = index;
                        
                        // Update all preview badges
                        document.querySelectorAll('.primary-badge').forEach(badge => badge.remove());
                        document.querySelectorAll('.image-preview').forEach(preview => {
                            preview.querySelector('.set-primary').style.display = 'block';
                        });
                        
                        // Add badge to this preview and hide its "Set as Primary" button
                        const badge = document.createElement('span');
                        badge.className = 'primary-badge';
                        badge.textContent = 'Primary';
                        preview.appendChild(badge);
                        this.style.display = 'none';
                    });
                };
                
                reader.readAsDataURL(file);
            }
        }
        
        // Form validation
        document.getElementById('auction-form').addEventListener('submit', function(e) {
            const startDate = new Date(document.getElementById('start_date').value);
            const endDate = new Date(document.getElementById('end_date').value);
            const now = new Date();
            
            let isValid = true;
            let errorMessage = '';
            
            // Check if start date is in the future
            if (startDate <= now) {
                isValid = false;
                errorMessage += 'Start date must be in the future.\n';
            }
            
            // Check if end date is after start date
            if (endDate <= startDate) {
                isValid = false;
                errorMessage += 'End date must be after start date.\n';
            }
            
            // Check if at least one image is selected
            const images = document.getElementById('file-input').files;
            if (images.length === 0) {
                isValid = false;
                errorMessage += 'At least one image is required.\n';
            }
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fix the following errors:\n\n' + errorMessage);
            }
        });
    </script>
</body>
</html>
