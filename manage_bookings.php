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
$search = $status_filter = '';
$where_conditions = [];
$params = [];
$param_types = '';

// Handle search and filters
if($_SERVER["REQUEST_METHOD"] == "GET"){
    if(isset($_GET['search']) && !empty($_GET['search'])){
        $search = trim($_GET['search']);
        $where_conditions[] = "(r.booking_ref LIKE ? OR r.guest_name LIKE ? OR r.guest_email LIKE ? OR rm.room_number LIKE ?)";
        $search_term = "%$search%";
        $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
        $param_types .= 'ssss';
    }
    
    if(isset($_GET['status']) && !empty($_GET['status'])){
        $status_filter = $_GET['status'];
        $where_conditions[] = "r.status = ?";
        $params[] = $status_filter;
        $param_types .= 's';
    }
}

// Build the query
$sql = "SELECT r.*, rm.room_type, rm.room_number, rm.price as room_price 
        FROM reservations r 
        LEFT JOIN rooms rm ON r.room_id = rm.id";
        
if(!empty($where_conditions)){
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

$sql .= " ORDER BY r.created_at DESC";

// Prepare and execute query
$stmt = mysqli_prepare($conn, $sql);
if(!empty($params)){
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$bookings = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Handle booking actions (cancel, confirm, etc.)
if(isset($_POST['action']) && isset($_POST['booking_id'])){
    $booking_id = $_POST['booking_id'];
    $action = $_POST['action'];
    
    switch($action){
        case 'cancel':
            $update_sql = "UPDATE reservations SET status = 'cancelled' WHERE id = ?";
            break;
        case 'confirm':
            $update_sql = "UPDATE reservations SET status = 'confirmed' WHERE id = ?";
            break;
        case 'checkin':
            $update_sql = "UPDATE reservations SET status = 'checked_in' WHERE id = ?";
            break;
        case 'checkout':
            $update_sql = "UPDATE reservations SET status = 'checked_out' WHERE id = ?";
            break;
        case 'delete':
            $update_sql = "DELETE FROM reservations WHERE id = ?";
            break;
        default:
            $_SESSION['error'] = "Invalid action.";
            header("location: manage_bookings.php");
            exit();
    }
    
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "i", $booking_id);
    
    if(mysqli_stmt_execute($update_stmt)){
        // Log the activity
        $activity_sql = "INSERT INTO admin_activities (admin_id, activity_type, description) VALUES (?, 'booking_update', ?)";
        $activity_stmt = mysqli_prepare($conn, $activity_sql);
        $description = "Updated booking #$booking_id - $action";
        mysqli_stmt_bind_param($activity_stmt, "is", $_SESSION['admin_id'], $description);
        mysqli_stmt_execute($activity_stmt);
        mysqli_stmt_close($activity_stmt);
        
        $_SESSION['success'] = "Booking updated successfully.";
    } else {
        $_SESSION['error'] = "Error updating booking.";
    }
    
    mysqli_stmt_close($update_stmt);
    header("location: manage_bookings.php");
    exit();
}

// Close connection
mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings - Unicorn Hotel Admin</title>
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
                <a href="manage_bookings.php" class="menu-item active">
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
                    <h1>Manage Bookings</h1>
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

                <!-- Filters and Search -->
                <div class="card">
                    <div class="card-header">
                        <h3>Filters & Search</h3>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="filter-form">
                            <div class="form-row">
                                <div class="form-control">
                                    <label for="search">Search</label>
                                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="Search by booking ref, guest name, email, or room number">
                                </div>
                                <div class="form-control">
                                    <label for="status">Status</label>
                                    <select id="status" name="status">
                                        <option value="">All Statuses</option>
                                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                        <option value="checked_in" <?php echo $status_filter == 'checked_in' ? 'selected' : ''; ?>>Checked In</option>
                                        <option value="checked_out" <?php echo $status_filter == 'checked_out' ? 'selected' : ''; ?>>Checked Out</option>
                                        <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Apply Filters
                                </button>
                                <a href="manage_bookings.php" class="btn btn-outline">Clear Filters</a>
                                <a href="new_booking.php" class="btn btn-success">
                                    <i class="fas fa-plus"></i> New Booking
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Bookings Table -->
                <div class="card">
                    <div class="card-header">
                        <h3>All Bookings (<?php echo count($bookings); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <?php if(empty($bookings)): ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <h3>No Bookings Found</h3>
                                <p>No bookings match your current filters.</p>
                                <a href="manage_bookings.php" class="btn btn-primary">Clear Filters</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Booking Ref</th>
                                            <th>Guest</th>
                                            <th>Room</th>
                                            <th>Check-in/out</th>
                                            <th>Guests</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($bookings as $booking): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo $booking['booking_ref']; ?></strong>
                                            </td>
                                            <td>
                                                <div class="guest-info">
                                                    <div class="guest-name"><?php echo htmlspecialchars($booking['guest_name']); ?></div>
                                                    <div class="guest-email"><?php echo htmlspecialchars($booking['guest_email']); ?></div>
                                                    <div class="guest-phone"><?php echo htmlspecialchars($booking['guest_phone']); ?></div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="room-info">
                                                    <div class="room-type"><?php echo ucfirst($booking['room_type']); ?></div>
                                                    <div class="room-number"><?php echo $booking['room_number']; ?></div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="date-info">
                                                    <div class="check-in">
                                                        <small>Check-in:</small>
                                                        <?php echo date('M j, Y', strtotime($booking['check_in'])); ?>
                                                    </div>
                                                    <div class="check-out">
                                                        <small>Check-out:</small>
                                                        <?php echo date('M j, Y', strtotime($booking['check_out'])); ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo $booking['guests']; ?></td>
                                            <td>
                                                <strong>₦<?php echo number_format($booking['total_amount'], 2); ?></strong>
                                            </td>
                                            <td>
                                                <?php 
                                                $status_class = '';
                                                switch($booking['status']){
                                                    case 'confirmed':
                                                        $status_class = 'badge-success';
                                                        break;
                                                    case 'checked_in':
                                                        $status_class = 'badge-primary';
                                                        break;
                                                    case 'checked_out':
                                                        $status_class = 'badge-info';
                                                        break;
                                                    case 'cancelled':
                                                        $status_class = 'badge-error';
                                                        break;
                                                    case 'pending':
                                                    default:
                                                        $status_class = 'badge-warning';
                                                }
                                                ?>
                                                <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($booking['status']); ?></span>
                                            </td>
                                            <td>
                                                <?php echo date('M j, Y', strtotime($booking['created_at'])); ?>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <!-- View Details -->
                                                    <button type="button" class="btn btn-sm btn-outline view-booking" 
                                                            data-booking='<?php echo htmlspecialchars(json_encode($booking), ENT_QUOTES, 'UTF-8'); ?>'>
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    
                                                    <!-- Status Actions -->
                                                    <?php if($booking['status'] == 'pending'): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                            <input type="hidden" name="action" value="confirm">
                                                            <button type="submit" class="btn btn-sm btn-success" 
                                                                    onclick="return confirm('Confirm this booking?')">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <?php if($booking['status'] == 'confirmed'): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                            <input type="hidden" name="action" value="checkin">
                                                            <button type="submit" class="btn btn-sm btn-primary" 
                                                                    onclick="return confirm('Check-in this guest?')">
                                                                <i class="fas fa-sign-in-alt"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <?php if($booking['status'] == 'checked_in'): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                            <input type="hidden" name="action" value="checkout">
                                                            <button type="submit" class="btn btn-sm btn-info" 
                                                                    onclick="return confirm('Check-out this guest?')">
                                                                <i class="fas fa-sign-out-alt"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Cancel Action -->
                                                    <?php if(in_array($booking['status'], ['pending', 'confirmed'])): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                            <input type="hidden" name="action" value="cancel">
                                                            <button type="submit" class="btn btn-sm btn-warning" 
                                                                    onclick="return confirm('Cancel this booking?')">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Delete Action -->
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <button type="submit" class="btn btn-sm btn-error" 
                                                                onclick="return confirm('Are you sure you want to delete this booking? This action cannot be undone.')">
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

    <!-- Booking Details Modal -->
    <div id="bookingModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Booking Details</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body" id="bookingDetails">
                <!-- Details will be loaded here -->
            </div>
        </div>
    </div>

    <style>
        .filter-form {
            margin-bottom: 0;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .guest-info, .room-info, .date-info {
            font-size: 0.9rem;
        }
        
        .guest-name, .room-type {
            font-weight: 600;
            margin-bottom: 2px;
        }
        
        .guest-email, .guest-phone, .room-number, .check-in, .check-out {
            color: var(--gray);
            font-size: 0.8rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
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
            max-width: 600px;
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
        
        .detail-section {
            margin-bottom: 25px;
        }
        
        .detail-section h4 {
            margin-bottom: 15px;
            color: var(--primary);
            border-bottom: 1px solid var(--gray-light);
            padding-bottom: 8px;
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 10px;
        }
        
        .detail-label {
            font-weight: 600;
            width: 120px;
            color: var(--dark);
        }
        
        .detail-value {
            flex: 1;
            color: var(--gray);
        }
        
        @media (max-width: 768px) {
            .form-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .action-buttons {
                justify-content: center;
            }
            
            .detail-row {
                flex-direction: column;
            }
            
            .detail-label {
                width: 100%;
                margin-bottom: 5px;
            }
        }
    </style>

    <script>
        // Mobile menu toggle
        document.getElementById('mobileMenuBtn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // Modal functionality
        const modal = document.getElementById('bookingModal');
        const closeBtn = document.querySelector('.close');
        const viewButtons = document.querySelectorAll('.view-booking');

        // Open modal with booking details
        viewButtons.forEach(button => {
            button.addEventListener('click', function() {
                const booking = JSON.parse(this.getAttribute('data-booking'));
                displayBookingDetails(booking);
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

        // Display booking details in modal
        function displayBookingDetails(booking) {
            const modalBody = document.getElementById('bookingDetails');
            
            const nights = Math.ceil((new Date(booking.check_out) - new Date(booking.check_in)) / (1000 * 60 * 60 * 24));
            
            modalBody.innerHTML = `
                <div class="detail-section">
                    <h4>Booking Information</h4>
                    <div class="detail-row">
                        <div class="detail-label">Booking Ref:</div>
                        <div class="detail-value"><strong>${booking.booking_ref}</strong></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Status:</div>
                        <div class="detail-value">
                            <span class="badge ${getStatusClass(booking.status)}">${capitalizeFirst(booking.status)}</span>
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Created:</div>
                        <div class="detail-value">${formatDate(booking.created_at)}</div>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h4>Guest Information</h4>
                    <div class="detail-row">
                        <div class="detail-label">Name:</div>
                        <div class="detail-value">${booking.guest_name}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Email:</div>
                        <div class="detail-value">${booking.guest_email}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Phone:</div>
                        <div class="detail-value">${booking.guest_phone || 'N/A'}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Address:</div>
                        <div class="detail-value">${booking.guest_address || 'N/A'}</div>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h4>Room Information</h4>
                    <div class="detail-row">
                        <div class="detail-label">Room Type:</div>
                        <div class="detail-value">${capitalizeFirst(booking.room_type)}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Room Number:</div>
                        <div class="detail-value">${booking.room_number}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Room Price:</div>
                        <div class="detail-value">₦${parseFloat(booking.room_price).toLocaleString('en-US', {minimumFractionDigits: 2})}/night</div>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h4>Stay Details</h4>
                    <div class="detail-row">
                        <div class="detail-label">Check-in:</div>
                        <div class="detail-value">${formatDate(booking.check_in)}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Check-out:</div>
                        <div class="detail-value">${formatDate(booking.check_out)}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Nights:</div>
                        <div class="detail-value">${nights}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Guests:</div>
                        <div class="detail-value">${booking.guests}</div>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h4>Payment Information</h4>
                    <div class="detail-row">
                        <div class="detail-label">Total Amount:</div>
                        <div class="detail-value"><strong>₦${parseFloat(booking.total_amount).toLocaleString('en-US', {minimumFractionDigits: 2})}</strong></div>
                    </div>
                </div>
                
                ${booking.special_requests ? `
                <div class="detail-section">
                    <h4>Special Requests</h4>
                    <div class="detail-row">
                        <div class="detail-value">${booking.special_requests}</div>
                    </div>
                </div>
                ` : ''}
            `;
        }

        // Helper functions
        function getStatusClass(status) {
            const statusClasses = {
                'confirmed': 'badge-success',
                'checked_in': 'badge-primary',
                'checked_out': 'badge-info',
                'cancelled': 'badge-error',
                'pending': 'badge-warning'
            };
            return statusClasses[status] || 'badge-warning';
        }

        function capitalizeFirst(string) {
            return string.charAt(0).toUpperCase() + string.slice(1);
        }

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