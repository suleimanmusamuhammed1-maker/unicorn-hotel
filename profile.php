<?php
// Start session and check authentication
session_start();
if(!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true){
    header("location: login.php");
    exit();
}

// Include database configuration and functions
include_once '../includes/config.php';
include_once '../includes/functions.php';

// Initialize variables
$name = $_SESSION['user_name'];
$email = $_SESSION['user_email'];
$phone = $_SESSION['user_phone'];
$success = '';
$errors = [];

// Get user's booking count
$sql = "SELECT COUNT(*) as count FROM reservations WHERE guest_email = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$booking_count = mysqli_fetch_assoc($result)['count'];
mysqli_stmt_close($stmt);

// Process form when submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
    // Get form data
    $name = sanitize_input($_POST['name']);
    $phone = sanitize_input($_POST['phone']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate required fields
    if(empty($name)){
        $errors[] = "Please enter your name.";
    }
    
    if(empty($phone)){
        $errors[] = "Please enter your phone number.";
    }
    
    // Check if password change is requested
    $password_change = !empty($current_password) || !empty($new_password) || !empty($confirm_password);
    
    if($password_change){
        if(empty($current_password)){
            $errors[] = "Please enter your current password to change password.";
        }
        
        if(empty($new_password)){
            $errors[] = "Please enter a new password.";
        } elseif(strlen($new_password) < 6){
            $errors[] = "New password must have at least 6 characters.";
        }
        
        if(empty($confirm_password)){
            $errors[] = "Please confirm your new password.";
        } elseif($new_password !== $confirm_password){
            $errors[] = "New passwords do not match.";
        }
    }
    
    // If no errors, update profile
    if(empty($errors)){
        // Verify current password if changing password
        if($password_change){
            $sql = "SELECT password_hash FROM users WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
            
            if(!password_verify($current_password, $user['password_hash'])){
                $errors[] = "Current password is incorrect.";
            }
        }
        
        if(empty($errors)){
            // Update profile
            if($password_change){
                $sql = "UPDATE users SET guest_name = ?, guest_phone = ?, password_hash = ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                mysqli_stmt_bind_param($stmt, "sssi", $name, $phone, $new_password_hash, $_SESSION['user_id']);
            } else {
                $sql = "UPDATE users SET guest_name = ?, guest_phone = ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "ssi", $name, $phone, $_SESSION['user_id']);
            }
            
            if(mysqli_stmt_execute($stmt)){
                // Update session variables
                $_SESSION['user_name'] = $name;
                $_SESSION['user_phone'] = $phone;
                
                $success = "Profile updated successfully!";
            } else {
                $errors[] = "Error updating profile. Please try again.";
            }
            
            mysqli_stmt_close($stmt);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Unicorn Hotel Damaturu</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .user-container {
            min-height: 100vh;
            background-color: #f8fafc;
        }
        
        .user-header {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
        }
        
        .user-nav ul {
            display: flex;
            list-style: none;
            gap: 20px;
        }
        
        .user-nav a {
            text-decoration: none;
            color: var(--dark);
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .user-nav a:hover, .user-nav a.active {
            color: var(--primary);
        }
        
        .user-main {
            padding: 40px 0;
        }
        
        .profile-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
        }
        
        .profile-sidebar {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 30px;
            height: fit-content;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            margin: 0 auto 20px;
        }
        
        .profile-info {
            text-align: center;
        }
        
        .profile-info h3 {
            margin-bottom: 5px;
        }
        
        .profile-info p {
            color: var(--gray);
            margin-bottom: 20px;
        }
        
        .profile-stats {
            display: flex;
            justify-content: space-around;
            margin: 20px 0;
            padding: 20px 0;
            border-top: 1px solid var(--gray-light);
            border-bottom: 1px solid var(--gray-light);
        }
        
        .stat {
            text-align: center;
        }
        
        .stat-number {
            display: block;
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary);
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: var(--gray);
        }
        
        .profile-content {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 30px;
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .form-section h3 {
            margin-bottom: 20px;
            color: var(--primary);
            border-bottom: 1px solid var(--gray-light);
            padding-bottom: 10px;
        }
        
        .form-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-control {
            margin-bottom: 20px;
        }
        
        .form-control label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .form-control input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--gray-light);
            border-radius: 8px;
            font-size: 1rem;
            transition: border 0.3s, box-shadow 0.3s;
        }
        
        .form-control input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .form-control input:disabled {
            background-color: var(--light);
            color: var(--gray);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--gray-light);
            color: var(--dark);
        }
        
        .btn-outline:hover {
            background-color: var(--gray-light);
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            border-left: 4px solid var(--success);
            color: var(--success);
        }
        
        .alert-error {
            background-color: rgba(239, 68, 68, 0.1);
            border-left: 4px solid var(--error);
            color: var(--error);
        }
        
        @media (max-width: 768px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
            
            .form-group {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="user-container">
        <!-- Header -->
        <header class="user-header">
            <div class="container">
                <div class="header-content">
                    <div class="logo">
                        <span class="logo-icon"><i class="fas fa-crown"></i></span>
                        <h1>Unicorn Hotel Damaturu</h1>
                    </div>
                    <nav class="user-nav">
                        <ul>
                            <li><a href="dashboard.php">Dashboard</a></li>
                            <li><a href="bookings.php">My Bookings</a></li>
                            <li><a href="profile.php" class="active">Profile</a></li>
                            <li><a href="logout.php">Logout</a></li>
                        </ul>
                    </nav>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="user-main">
            <div class="container">
                <!-- Page Header -->
                <div class="page-header" style="margin-bottom: 30px;">
                    <h2>My Profile</h2>
                    <p>Manage your account information and preferences</p>
                </div>

                <!-- Success/Error Messages -->
                <?php if(!empty($success)): ?>
                    <div class="alert alert-success">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if(!empty($errors)): ?>
                    <div class="alert alert-error">
                        <?php foreach($errors as $error): ?>
                            <p><?php echo $error; ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="profile-container">
                    <!-- Profile Sidebar -->
                    <div class="profile-sidebar">
                        <div class="profile-avatar">
                            <?php echo strtoupper(substr($name, 0, 1)); ?>
                        </div>
                        <div class="profile-info">
                            <h3><?php echo htmlspecialchars($name); ?></h3>
                            <p><?php echo htmlspecialchars($email); ?></p>
                            <p><?php echo htmlspecialchars($phone); ?></p>
                        </div>
                        
                        <div class="profile-stats">
                            <div class="stat">
                                <span class="stat-number">
                                    <?php echo $booking_count; ?>
                                </span>
                                <span class="stat-label">Bookings</span>
                            </div>
                            <div class="stat">
                                <span class="stat-number">
                                    <?php
                                    // Calculate member since (months)
                                    $member_since = "1+";
                                    echo $member_since;
                                    ?>
                                </span>
                                <span class="stat-label">Months</span>
                            </div>
                        </div>
                        
                        <div class="profile-actions">
                            <a href="dashboard.php" class="btn btn-outline" style="width: 100%; margin-bottom: 10px;">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                            <a href="bookings.php" class="btn btn-outline" style="width: 100%;">
                                <i class="fas fa-calendar-check"></i> My Bookings
                            </a>
                        </div>
                    </div>

                    <!-- Profile Content -->
                    <div class="profile-content">
                        <!-- Personal Information Form -->
                        <form method="POST" id="profileForm">
                            <div class="form-section">
                                <h3><i class="fas fa-user"></i> Personal Information</h3>
                                
                                <div class="form-group">
                                    <div class="form-control">
                                        <label for="name">Full Name</label>
                                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                                    </div>
                                    <div class="form-control">
                                        <label for="phone">Phone Number</label>
                                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="form-control">
                                    <label for="email">Email Address</label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" disabled>
                                    <small style="color: var(--gray); margin-top: 5px; display: block;">
                                        Email address cannot be changed. Contact support if you need to update your email.
                                    </small>
                                </div>
                            </div>

                            <!-- Password Change Section -->
                            <div class="form-section">
                                <h3><i class="fas fa-lock"></i> Change Password</h3>
                                <p style="color: var(--gray); margin-bottom: 20px;">
                                    Leave these fields blank if you don't want to change your password.
                                </p>
                                
                                <div class="form-control">
                                    <label for="current_password">Current Password</label>
                                    <input type="password" id="current_password" name="current_password">
                                </div>
                                
                                <div class="form-group">
                                    <div class="form-control">
                                        <label for="new_password">New Password</label>
                                        <input type="password" id="new_password" name="new_password">
                                    </div>
                                    <div class="form-control">
                                        <label for="confirm_password">Confirm New Password</label>
                                        <input type="password" id="confirm_password" name="confirm_password">
                                    </div>
                                </div>
                            </div>

                            <!-- Form Actions -->
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Profile
                                </button>
                                <a href="dashboard.php" class="btn btn-outline">Cancel</a>
                            </div>
                        </form>

                        <!-- Account Management -->
                        <div class="form-section">
                            <h3><i class="fas fa-cog"></i> Account Management</h3>
                            
                            <div style="display: grid; gap: 15px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px; background: var(--light); border-radius: 8px;">
                                    <div>
                                        <strong>Delete Account</strong>
                                        <p style="margin: 5px 0 0; color: var(--gray); font-size: 0.9rem;">
                                            Permanently delete your account and all associated data
                                        </p>
                                    </div>
                                    <button type="button" class="btn btn-error" onclick="confirmDelete()">
                                        <i class="fas fa-trash"></i> Delete Account
                                    </button>
                                </div>
                                
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px; background: var(--light); border-radius: 8px;">
                                    <div>
                                        <strong>Export Data</strong>
                                        <p style="margin: 5px 0 0; color: var(--gray); font-size: 0.9rem;">
                                            Download all your personal data and booking history
                                        </p>
                                    </div>
                                    <button type="button" class="btn btn-outline">
                                        <i class="fas fa-download"></i> Export Data
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Password validation
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const currentPassword = document.getElementById('current_password').value;
            
            // Check if password fields are partially filled
            if ((newPassword || confirmPassword) && !currentPassword) {
                e.preventDefault();
                alert('Please enter your current password to change your password.');
                return;
            }
            
            if (newPassword && newPassword.length < 6) {
                e.preventDefault();
                alert('New password must be at least 6 characters long.');
                return;
            }
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match.');
                return;
            }
        });
        
        // Account deletion confirmation
        function confirmDelete() {
            if(confirm('Are you sure you want to delete your account? This action cannot be undone and will permanently delete all your data including booking history.')) {
                if(confirm('This is your final warning. All your data will be lost permanently. Are you absolutely sure?')) {
                    // In a real implementation, this would redirect to a delete account script
                    alert('Account deletion would be processed here. For security reasons, please contact support to delete your account.');
                }
            }
        }
        
        // Show/hide password fields based on current password
        document.getElementById('current_password').addEventListener('input', function() {
            const newPasswordFields = document.querySelectorAll('input[type="password"]:not(#current_password)');
            if (this.value) {
                newPasswordFields.forEach(field => {
                    field.required = true;
                });
            } else {
                newPasswordFields.forEach(field => {
                    field.required = false;
                    field.value = '';
                });
            }
        });
    </script>
</body>
</html>