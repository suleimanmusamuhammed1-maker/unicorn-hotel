<?php
// Start session and check authentication
session_start();
if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true){
    header("location: login.php");
    exit();
}

// Include database configuration
include_once '../includes/config.php';

// Handle user actions
if(isset($_POST['action']) && isset($_POST['user_id'])){
    $user_id = $_POST['user_id'];
    $action = $_POST['action'];
    
    switch($action){
        case 'toggle_status':
            // Get current status
            $sql = "SELECT active FROM users WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
            
            $new_status = $user['active'] ? 0 : 1;
            $update_sql = "UPDATE users SET active = ? WHERE id = ?";
            break;
        case 'delete':
            $update_sql = "DELETE FROM users WHERE id = ?";
            break;
        default:
            $_SESSION['error'] = "Invalid action.";
            header("location: manage_users.php");
            exit();
    }
    
    $update_stmt = mysqli_prepare($conn, $update_sql);
    if($action == 'toggle_status'){
        mysqli_stmt_bind_param($update_stmt, "ii", $new_status, $user_id);
    } else {
        mysqli_stmt_bind_param($update_stmt, "i", $user_id);
    }
    
    if(mysqli_stmt_execute($update_stmt)){
        // Log the activity
        $activity_sql = "INSERT INTO admin_activities (admin_id, activity_type, description) VALUES (?, 'user_update', ?)";
        $activity_stmt = mysqli_prepare($conn, $activity_sql);
        $description = "Updated user #$user_id - $action";
        mysqli_stmt_bind_param($activity_stmt, "is", $_SESSION['admin_id'], $description);
        mysqli_stmt_execute($activity_stmt);
        mysqli_stmt_close($activity_stmt);
        
        $_SESSION['success'] = "User updated successfully.";
    } else {
        $_SESSION['error'] = "Error updating user.";
    }
    
    mysqli_stmt_close($update_stmt);
    header("location: manage_users.php");
    exit();
}

// Get all users
$sql = "SELECT u.*, 
        (SELECT COUNT(*) FROM reservations r WHERE r.guest_email = u.guest_email) as booking_count,
        (SELECT SUM(total_amount) FROM reservations r WHERE r.guest_email = u.guest_email AND r.status IN ('confirmed', 'checked_in', 'checked_out')) as total_spent
        FROM users u 
        ORDER BY u.created_at DESC";
$result = mysqli_query($conn, $sql);
$users = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Close connection
mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Unicorn Hotel Admin</title>
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
                <a href="manage_users.php" class="menu-item active">
                    <i class="fas fa-users"></i>
                    <span>Manage Users</span>
                </a>
                <a href="reports.php" class="menu-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
                <a href="settings.php" class="menu-item">
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
                    <h1>Manage Users</h1>
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
                <?php if(isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-error">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <!-- Users Table -->
                <div class="card">
                    <div class="card-header">
                        <h3>All Users (<?php echo count($users); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <?php if(empty($users)): ?>
                            <div class="empty-state">
                                <i class="fas fa-users"></i>
                                <h3>No Users Found</h3>
                                <p>No users have registered yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Contact</th>
                                            <th>Bookings</th>
                                            <th>Total Spent</th>
                                            <th>Member Since</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($users as $user): ?>
                                        <tr>
                                            <td>
                                                <div class="user-info-small">
                                                    <div class="user-avatar-small">
                                                        <?php echo strtoupper(substr($user['guest_name'], 0, 1)); ?>
                                                    </div>
                                                    <div class="user-details-small">
                                                        <div class="user-name"><?php echo htmlspecialchars($user['guest_name']); ?></div>
                                                        <div class="user-email"><?php echo htmlspecialchars($user['guest_email']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="contact-info">
                                                    <div class="phone"><?php echo htmlspecialchars($user['guest_phone']); ?></div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="booking-stats">
                                                    <span class="stat-number"><?php echo $user['booking_count']; ?></span>
                                                    <span class="stat-label">bookings</span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="spent-amount">
                                                    ₦<?php echo number_format($user['total_spent'] ?: 0, 2); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                            </td>
                                            <td>
                                                <?php if($user['active']): ?>
                                                    <span class="badge badge-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge badge-error">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <!-- View User Details -->
                                                    <button type="button" class="btn btn-sm btn-outline view-user" 
                                                            data-user='<?php echo htmlspecialchars(json_encode($user), ENT_QUOTES, 'UTF-8'); ?>'>
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    
                                                    <!-- Toggle Status -->
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <button type="submit" class="btn btn-sm <?php echo $user['active'] ? 'btn-warning' : 'btn-success'; ?>">
                                                            <i class="fas fa-power-off"></i>
                                                        </button>
                                                    </form>
                                                    
                                                    <!-- Delete -->
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <button type="submit" class="btn btn-sm btn-error" 
                                                                onclick="return confirm('Are you sure you want to delete this user? This will also delete all their bookings.')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- User Details Modal -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>User Details</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body" id="userDetails">
                <!-- Details will be loaded here -->
            </div>
        </div>
    </div>

    <style>
        .user-info-small {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-avatar-small {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .user-details-small .user-name {
            font-weight: 600;
            margin-bottom: 2px;
        }
        
        .user-details-small .user-email {
            color: var(--gray);
            font-size: 0.8rem;
        }
        
        .contact-info .phone {
            color: var(--dark);
        }
        
        .booking-stats {
            text-align: center;
        }
        
        .stat-number {
            display: block;
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary);
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: var(--gray);
        }
        
        .spent-amount {
            font-weight: 600;
            color: var(--success);
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid var(--gray-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
        }
        
        .close {
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray);
        }
        
        .close:hover {
            color: var(--dark);
        }
        
        .modal-body {
            padding: 25px;
        }
        
        .user-detail-section {
            margin-bottom: 25px;
        }
        
        .user-detail-section h4 {
            margin-bottom: 15px;
            color: var(--primary);
            border-bottom: 1px solid var(--gray-light);
            padding-bottom: 8px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--gray-light);
        }
        
        .detail-label {
            font-weight: 600;
            color: var(--dark);
        }
        
        .detail-value {
            color: var(--gray);
        }
    </style>

    <script>
        // Mobile menu toggle
        document.getElementById('mobileMenuBtn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // Modal functionality
        const modal = document.getElementById('userModal');
        const closeBtn = document.querySelector('.close');
        const viewButtons = document.querySelectorAll('.view-user');

        // Open modal with user details
        viewButtons.forEach(button => {
            button.addEventListener('click', function() {
                const user = JSON.parse(this.getAttribute('data-user'));
                displayUserDetails(user);
                modal.style.display = 'block';
            });
        });

        // Close modal
        closeBtn.addEventListener('click', function() {
            modal.style.display = 'none';
        });

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        });

        // Display user details in modal
        function displayUserDetails(user) {
            const modalBody = document.getElementById('userDetails');
            
            modalBody.innerHTML = `
                <div class="user-detail-section">
                    <h4>Personal Information</h4>
                    <div class="detail-row">
                        <div class="detail-label">Full Name:</div>
                        <div class="detail-value">${user.guest_name}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Email:</div>
                        <div class="detail-value">${user.guest_email}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Phone:</div>
                        <div class="detail-value">${user.guest_phone}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Member Since:</div>
                        <div class="detail-value">${formatDate(user.created_at)}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Status:</div>
                        <div class="detail-value">
                            <span class="badge ${user.active ? 'badge-success' : 'badge-error'}">
                                ${user.active ? 'Active' : 'Inactive'}
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="user-detail-section">
                    <h4>Booking Statistics</h4>
                    <div class="detail-row">
                        <div class="detail-label">Total Bookings:</div>
                        <div class="detail-value">${user.booking_count}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Total Amount Spent:</div>
                        <div class="detail-value">₦${parseFloat(user.total_spent || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</div>
                    </div>
                </div>
                
                <div class="user-detail-section">
                    <h4>Account Actions</h4>
                    <div style="display: grid; gap: 10px;">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="user_id" value="${user.id}">
                            <input type="hidden" name="action" value="toggle_status">
                            <button type="submit" class="btn btn-sm ${user.active ? 'btn-warning' : 'btn-success'}" style="width: 100%;">
                                <i class="fas fa-power-off"></i> ${user.active ? 'Deactivate User' : 'Activate User'}
                            </button>
                        </form>
                        
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="user_id" value="${user.id}">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn btn-sm btn-error" style="width: 100%;"
                                    onclick="return confirm('Are you sure you want to delete this user? This will also delete all their bookings.')">
                                <i class="fas fa-trash"></i> Delete User
                            </button>
                        </form>
                    </div>
                </div>
            `;
        }

        // Helper function to format date
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            });
        }
    </script>
</body>
</html>