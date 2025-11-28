<?php
// Start session and check authentication
session_start();
if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true){
    header("location: login.php");
    exit();
}

// Include database configuration
include_once '../includes/config.php';

// Initialize variables
$success = '';
$error = '';

// Handle form submissions
if($_SERVER["REQUEST_METHOD"] == "POST"){
    if(isset($_POST['update_profile'])){
        // Update admin profile
        $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        
        $sql = "UPDATE admin_users SET full_name = ?, email = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssi", $full_name, $email, $_SESSION['admin_id']);
        
        if(mysqli_stmt_execute($stmt)){
            $_SESSION['admin_full_name'] = $full_name;
            $success = "Profile updated successfully.";
        } else {
            $error = "Error updating profile.";
        }
        mysqli_stmt_close($stmt);
    }
    
    if(isset($_POST['change_password'])){
        // Change password
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        $sql = "SELECT password_hash FROM admin_users WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $_SESSION['admin_id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if(!password_verify($current_password, $user['password_hash'])){
            $error = "Current password is incorrect.";
        } elseif($new_password !== $confirm_password){
            $error = "New passwords do not match.";
        } elseif(strlen($new_password) < 6){
            $error = "New password must be at least 6 characters long.";
        } else {
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE admin_users SET password_hash = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "si", $new_password_hash, $_SESSION['admin_id']);
            
            if(mysqli_stmt_execute($stmt)){
                $success = "Password changed successfully.";
            } else {
                $error = "Error changing password.";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Get current admin data
$sql = "SELECT username, full_name, email FROM admin_users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['admin_id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$admin_data = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Close connection
mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Unicorn Hotel Admin</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="admin-sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>Unicorn Hotel</h2>
                <p>Admin Panel</p>
            </div>
            <div class="sidebar-menu">
                <a href="dashboard.php" class="menu-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="manage_bookings.php" class="menu-item">
                    <i class="fas fa-calendar-check"></i>
                    <span>Manage Bookings</span>
                </a>
                <a href="manage_rooms.php" class="menu-item">
                    <i class="fas fa-bed"></i>
                    <span>Manage Rooms</span>
                </a>
                <a href="manage_users.php" class="menu-item">
                    <i class="fas fa-users"></i>
                    <span>Manage Users</span>
                </a>
                <a href="reports.php" class="menu-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
                <a href="settings.php" class="menu-item active">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="admin-main">
            <!-- Header -->
            <div class="admin-header">
                <div class="header-left">
                    <h1>Settings</h1>
                </div>
                <div class="header-right">
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($_SESSION['admin_full_name'], 0, 1)); ?>
                        </div>
                        <div class="user-details">
                            <div class="user-name"><?php echo $_SESSION['admin_full_name']; ?></div>
                            <div class="user-role"><?php echo ucfirst($_SESSION['admin_role']); ?></div>
                        </div>
                    </div>
                    <a href="logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                    <div class="mobile-menu-btn" id="mobileMenuBtn">
                        <i class="fas fa-bars"></i>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="admin-content">
                <!-- Success/Error Messages -->
                <?php if(!empty($success)): ?>
                    <div class="alert alert-success">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if(!empty($error)): ?>
                    <div class="alert alert-error">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <div class="settings-tabs">
                    <div class="tab-buttons">
                        <button class="tab-button active" data-tab="profile">Profile Settings</button>
                        <button class="tab-button" data-tab="password">Change Password</button>
                        <button class="tab-button" data-tab="system">System Settings</button>
                        <button class="tab-button" data-tab="backup">Backup & Restore</button>
                    </div>

                    <div class="tab-content">
                        <!-- Profile Settings -->
                        <div class="tab-pane active" id="profile">
                            <div class="card">
                                <div class="card-header">
                                    <h3>Profile Information</h3>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <input type="hidden" name="update_profile" value="1">
                                        <div class="form-group">
                                            <div class="form-control">
                                                <label for="username">Username</label>
                                                <input type="text" id="username" value="<?php echo htmlspecialchars($admin_data['username']); ?>" disabled>
                                                <small>Username cannot be changed</small>
                                            </div>
                                            <div class="form-control">
                                                <label for="full_name">Full Name</label>
                                                <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($admin_data['full_name']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="form-control">
                                            <label for="email">Email Address</label>
                                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($admin_data['email']); ?>" required>
                                        </div>
                                        <button type="submit" class="btn btn-primary">Update Profile</button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Change Password -->
                        <div class="tab-pane" id="password">
                            <div class="card">
                                <div class="card-header">
                                    <h3>Change Password</h3>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <input type="hidden" name="change_password" value="1">
                                        <div class="form-control">
                                            <label for="current_password">Current Password</label>
                                            <input type="password" id="current_password" name="current_password" required>
                                        </div>
                                        <div class="form-group">
                                            <div class="form-control">
                                                <label for="new_password">New Password</label>
                                                <input type="password" id="new_password" name="new_password" required>
                                            </div>
                                            <div class="form-control">
                                                <label for="confirm_password">Confirm New Password</label>
                                                <input type="password" id="confirm_password" name="confirm_password" required>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-primary">Change Password</button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- System Settings -->
                        <div class="tab-pane" id="system">
                            <div class="card">
                                <div class="card-header">
                                    <h3>System Configuration</h3>
                                </div>
                                <div class="card-body">
                                    <form>
                                        <div class="form-group">
                                            <div class="form-control">
                                                <label for="hotel_name">Hotel Name</label>
                                                <input type="text" id="hotel_name" value="Unicorn Hotel Damaturu">
                                            </div>
                                            <div class="form-control">
                                                <label for="currency">Currency</label>
                                                <select id="currency">
                                                    <option value="NGN" selected>Nigerian Naira (₦)</option>
                                                    <option value="USD">US Dollar ($)</option>
                                                    <option value="EUR">Euro (€)</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <div class="form-control">
                                                <label for="checkin_time">Check-in Time</label>
                                                <input type="time" id="checkin_time" value="14:00">
                                            </div>
                                            <div class="form-control">
                                                <label for="checkout_time">Check-out Time</label>
                                                <input type="time" id="checkout_time" value="12:00">
                                            </div>
                                        </div>
                                        <div class="form-control">
                                            <label for="cancellation_policy">Cancellation Policy (Hours)</label>
                                            <input type="number" id="cancellation_policy" value="24" min="1">
                                            <small>Number of hours before check-in when cancellation is allowed</small>
                                        </div>
                                        <button type="button" class="btn btn-primary" onclick="saveSystemSettings()">Save Settings</button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Backup & Restore -->
                        <div class="tab-pane" id="backup">
                            <div class="card">
                                <div class="card-header">
                                    <h3>Backup & Restore</h3>
                                </div>
                                <div class="card-body">
                                    <div class="backup-actions">
                                        <div class="backup-card">
                                            <div class="backup-icon">
                                                <i class="fas fa-download"></i>
                                            </div>
                                            <div class="backup-info">
                                                <h4>Create Backup</h4>
                                                <p>Download a complete backup of your database</p>
                                            </div>
                                            <button type="button" class="btn btn-primary" onclick="createBackup()">
                                                <i class="fas fa-database"></i> Create Backup
                                            </button>
                                        </div>
                                        
                                        <div class="backup-card">
                                            <div class="backup-icon">
                                                <i class="fas fa-upload"></i>
                                            </div>
                                            <div class="backup-info">
                                                <h4>Restore Backup</h4>
                                                <p>Upload and restore from a previous backup</p>
                                            </div>
                                            <div class="file-upload">
                                                <input type="file" id="backupFile" accept=".sql,.backup">
                                                <button type="button" class="btn btn-outline" onclick="document.getElementById('backupFile').click()">
                                                    <i class="fas fa-file-upload"></i> Choose File
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div class="backup-card">
                                            <div class="backup-icon">
                                                <i class="fas fa-trash"></i>
                                            </div>
                                            <div class="backup-info">
                                                <h4>System Maintenance</h4>
                                                <p>Clear cache and optimize database</p>
                                            </div>
                                            <button type="button" class="btn btn-warning" onclick="runMaintenance()">
                                                <i class="fas fa-broom"></i> Run Maintenance
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="backup-history">
                                        <h4>Recent Backups</h4>
                                        <div class="table-responsive">
                                            <table class="data-table">
                                                <thead>
                                                    <tr>
                                                        <th>Backup Name</th>
                                                        <th>Date Created</th>
                                                        <th>Size</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td>No backups available</td>
                                                        <td>-</td>
                                                        <td>-</td>
                                                        <td>-</td>
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
        </div>
    </div>

    <style>
        .settings-tabs {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .tab-buttons {
            display: flex;
            background: var(--light);
            border-bottom: 1px solid var(--gray-light);
        }
        
        .tab-button {
            padding: 15px 25px;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 500;
            color: var(--gray);
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
        }
        
        .tab-button:hover {
            color: var(--primary);
            background: rgba(37, 99, 235, 0.05);
        }
        
        .tab-button.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            background: white;
        }
        
        .tab-content {
            padding: 0;
        }
        
        .tab-pane {
            display: none;
            padding: 30px;
        }
        
        .tab-pane.active {
            display: block;
        }
        
        .backup-actions {
            display: grid;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .backup-card {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            background: var(--light);
            border-radius: 8px;
        }
        
        .backup-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .backup-info {
            flex: 1;
        }
        
        .backup-info h4 {
            margin-bottom: 5px;
            color: var(--dark);
        }
        
        .backup-info p {
            color: var(--gray);
            margin: 0;
        }
        
        .file-upload {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .file-upload input[type="file"] {
            display: none;
        }
        
        .backup-history {
            margin-top: 30px;
        }
        
        @media (max-width: 768px) {
            .tab-buttons {
                flex-direction: column;
            }
            
            .backup-card {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>

    <script>
        // Mobile menu toggle
        document.getElementById('mobileMenuBtn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // Tab functionality
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', function() {
                // Remove active class from all buttons and panes
                document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
                document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
                
                // Add active class to clicked button and corresponding pane
                this.classList.add('active');
                const tabId = this.getAttribute('data-tab');
                document.getElementById(tabId).classList.add('active');
            });
        });

        // System settings functions
        function saveSystemSettings() {
            alert('System settings would be saved here. In a real implementation, this would make an AJAX call to update settings in the database.');
        }

        function createBackup() {
            alert('Backup creation would be initiated here. This would generate a SQL dump file for download.');
        }

        function runMaintenance() {
            if(confirm('Are you sure you want to run system maintenance? This may take a few moments.')) {
                alert('System maintenance would run here. This would optimize the database and clear temporary files.');
            }
        }

        // Password validation
        document.querySelector('form[action*="change_password"]')?.addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword.length < 6) {
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
    </script>
</body>
</html>