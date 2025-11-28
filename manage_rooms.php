<?php
// Start session and check authentication
session_start();
if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true){
    header("location: login.php");
    exit();
}

// Include database configuration
include_once '../includes/config.php';

// Handle room actions
if(isset($_POST['action']) && isset($_POST['room_id'])){
    $room_id = $_POST['room_id'];
    $action = $_POST['action'];
    
    switch($action){
        case 'toggle_availability':
            // Get current availability status
            $sql = "SELECT available FROM rooms WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $room_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $room = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
            
            $new_status = $room['available'] ? 0 : 1;
            $update_sql = "UPDATE rooms SET available = ? WHERE id = ?";
            break;
        case 'delete':
            $update_sql = "DELETE FROM rooms WHERE id = ?";
            break;
        default:
            $_SESSION['error'] = "Invalid action.";
            header("location: manage_rooms.php");
            exit();
    }
    
    $update_stmt = mysqli_prepare($conn, $update_sql);
    if($action == 'toggle_availability'){
        mysqli_stmt_bind_param($update_stmt, "ii", $new_status, $room_id);
    } else {
        mysqli_stmt_bind_param($update_stmt, "i", $room_id);
    }
    
    if(mysqli_stmt_execute($update_stmt)){
        // Log the activity
        $activity_sql = "INSERT INTO admin_activities (admin_id, activity_type, description) VALUES (?, 'room_update', ?)";
        $activity_stmt = mysqli_prepare($conn, $activity_sql);
        $description = "Updated room #$room_id - $action";
        mysqli_stmt_bind_param($activity_stmt, "is", $_SESSION['admin_id'], $description);
        mysqli_stmt_execute($activity_stmt);
        mysqli_stmt_close($activity_stmt);
        
        $_SESSION['success'] = "Room updated successfully.";
    } else {
        $_SESSION['error'] = "Error updating room.";
    }
    
    mysqli_stmt_close($update_stmt);
    header("location: manage_rooms.php");
    exit();
}

// Get all rooms
$sql = "SELECT * FROM rooms ORDER BY room_type, room_number";
$result = mysqli_query($conn, $sql);
$rooms = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Close connection
mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Rooms - Unicorn Hotel Admin</title>
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
                <a href="manage_rooms.php" class="menu-item active">
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
                    <h1>Manage Rooms</h1>
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

                <!-- Actions -->
                <div class="card">
                    <div class="card-header">
                        <h3>Room Management</h3>
                        <a href="add_room.php" class="btn btn-success">
                            <i class="fas fa-plus"></i> Add New Room
                        </a>
                    </div>
                </div>

                <!-- Rooms Table -->
                <div class="card">
                    <div class="card-header">
                        <h3>All Rooms (<?php echo count($rooms); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <?php if(empty($rooms)): ?>
                            <div class="empty-state">
                                <i class="fas fa-bed"></i>
                                <h3>No Rooms Found</h3>
                                <p>No rooms have been added yet.</p>
                                <a href="add_room.php" class="btn btn-primary">Add First Room</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Room Number</th>
                                            <th>Type</th>
                                            <th>Price/Night</th>
                                            <th>Capacity</th>
                                            <th>Amenities</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($rooms as $room): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo $room['room_number']; ?></strong>
                                            </td>
                                            <td><?php echo ucfirst($room['room_type']); ?></td>
                                            <td>₦<?php echo number_format($room['price'], 2); ?></td>
                                            <td><?php echo $room['capacity']; ?> guests</td>
                                            <td>
                                                <div class="amenities-preview">
                                                    <?php 
                                                    $amenities = explode(',', $room['amenities']);
                                                    $preview = implode(', ', array_slice($amenities, 0, 3));
                                                    echo $preview . (count($amenities) > 3 ? '...' : '');
                                                    ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if($room['available']): ?>
                                                    <span class="badge badge-success">Available</span>
                                                <?php else: ?>
                                                    <span class="badge badge-error">Unavailable</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <!-- Edit -->
                                                    <a href="edit_room.php?id=<?php echo $room['id']; ?>" class="btn btn-sm btn-outline">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    
                                                    <!-- Toggle Availability -->
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="room_id" value="<?php echo $room['id']; ?>">
                                                        <input type="hidden" name="action" value="toggle_availability">
                                                        <button type="submit" class="btn btn-sm <?php echo $room['available'] ? 'btn-warning' : 'btn-success'; ?>">
                                                            <i class="fas fa-power-off"></i>
                                                        </button>
                                                    </form>
                                                    
                                                    <!-- Delete -->
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="room_id" value="<?php echo $room['id']; ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <button type="submit" class="btn btn-sm btn-error" 
                                                                onclick="return confirm('Are you sure you want to delete this room? This action cannot be undone.')">
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

    <style>
        .amenities-preview {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: var(--gray-light);
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
            color: var(--dark);
        }
    </style>

    <script>
        // Mobile menu toggle
        document.getElementById('mobileMenuBtn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
    </script>
</body>
</html>