<?php
// Start session and check authentication
session_start();
if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true){
    header("location: login.php");
    exit();
}

// Check if room ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])){
    header("location: manage_rooms.php");
    exit();
}

$room_id = $_GET['id'];

// Include database configuration
include_once '../includes/config.php';

// Get room data
$sql = "SELECT * FROM rooms WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $room_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$room = mysqli_fetch_assoc($result);

// Redirect if room not found
if(!$room){
    header("location: manage_rooms.php");
    exit();
}

// Initialize variables
$success = '';
$error = '';

// Process form when submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
    // Get and validate form data
    $room_type = mysqli_real_escape_string($conn, $_POST['room_type']);
    $room_number = mysqli_real_escape_string($conn, $_POST['room_number']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $price = floatval($_POST['price']);
    $capacity = intval($_POST['capacity']);
    $amenities = mysqli_real_escape_string($conn, $_POST['amenities']);
    $available = isset($_POST['available']) ? 1 : 0;
    
    // Validate required fields
    if(empty($room_type) || empty($room_number) || empty($description) || $price <= 0 || $capacity <= 0){
        $error = "Please fill in all required fields with valid values.";
    } else {
        // Check if room number already exists (excluding current room)
        $check_sql = "SELECT id FROM rooms WHERE room_number = ? AND id != ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "si", $room_number, $room_id);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        
        if(mysqli_stmt_num_rows($check_stmt) > 0){
            $error = "Room number already exists. Please use a different room number.";
        } else {
            // Update room
            $sql = "UPDATE rooms SET room_type = ?, room_number = ?, description = ?, price = ?, capacity = ?, amenities = ?, available = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssdisii", $room_type, $room_number, $description, $price, $capacity, $amenities, $available, $room_id);
            
            if(mysqli_stmt_execute($stmt)){
                // Log the activity
                $activity_sql = "INSERT INTO admin_activities (admin_id, activity_type, description) VALUES (?, 'room_update', ?)";
                $activity_stmt = mysqli_prepare($conn, $activity_sql);
                $description = "Updated room: $room_number ($room_type)";
                mysqli_stmt_bind_param($activity_stmt, "is", $_SESSION['admin_id'], $description);
                mysqli_stmt_execute($activity_stmt);
                mysqli_stmt_close($activity_stmt);
                
                $success = "Room updated successfully!";
                
                // Refresh room data
                $sql = "SELECT * FROM rooms WHERE id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "i", $room_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $room = mysqli_fetch_assoc($result);
            } else {
                $error = "Error updating room. Please try again.";
            }
            
            mysqli_stmt_close($stmt);
        }
        
        mysqli_stmt_close($check_stmt);
    }
}

// Connection will be closed at the end of the file
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Room - Unicorn Hotel Admin</title>
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
                    <h1>Edit Room</h1>
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

                <!-- Room Information Card -->
                <div class="card">
                    <div class="card-header">
                        <h3>Room Information</h3>
                        <div class="room-status">
                            <span class="badge <?php echo $room['available'] ? 'badge-success' : 'badge-error'; ?>">
                                <?php echo $room['available'] ? 'Available' : 'Unavailable'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="editRoomForm">
                            <div class="form-group">
                                <div class="form-control">
                                    <label for="room_type">Room Type *</label>
                                    <select id="room_type" name="room_type" required>
                                        <option value="">Select Room Type</option>
                                        <option value="standard" <?php echo $room['room_type'] == 'standard' ? 'selected' : ''; ?>>Standard Room</option>
                                        <option value="deluxe" <?php echo $room['room_type'] == 'deluxe' ? 'selected' : ''; ?>>Deluxe Room</option>
                                        <option value="suite" <?php echo $room['room_type'] == 'suite' ? 'selected' : ''; ?>>Executive Suite</option>
                                        <option value="presidential" <?php echo $room['room_type'] == 'presidential' ? 'selected' : ''; ?>>Presidential Suite</option>
                                    </select>
                                </div>
                                <div class="form-control">
                                    <label for="room_number">Room Number *</label>
                                    <input type="text" id="room_number" name="room_number" value="<?php echo htmlspecialchars($room['room_number']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-control">
                                <label for="description">Description *</label>
                                <textarea id="description" name="description" rows="3" required><?php echo htmlspecialchars($room['description']); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <div class="form-control">
                                    <label for="price">Price per Night (₦) *</label>
                                    <input type="number" id="price" name="price" min="0" step="0.01" value="<?php echo $room['price']; ?>" required>
                                </div>
                                <div class="form-control">
                                    <label for="capacity">Capacity (Guests) *</label>
                                    <input type="number" id="capacity" name="capacity" min="1" max="10" value="<?php echo $room['capacity']; ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-control">
                                <label for="amenities">Amenities</label>
                                <textarea id="amenities" name="amenities" rows="4" placeholder="Enter amenities separated by commas"><?php echo htmlspecialchars($room['amenities']); ?></textarea>
                                <small>Example: Free Wi-Fi, Air Conditioning, TV, Mini Bar, Private Bathroom</small>
                            </div>
                            
                            <div class="form-control">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="available" value="1" <?php echo $room['available'] ? 'checked' : ''; ?>>
                                    <span class="checkmark"></span>
                                    Room is available for booking
                                </label>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Update Room
                                </button>
                                <a href="manage_rooms.php" class="btn btn-outline">Cancel</a>
                                <button type="button" class="btn btn-error" onclick="confirmDelete()">
                                    <i class="fas fa-trash"></i> Delete Room
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Room Statistics -->
                <div class="card">
                    <div class="card-header">
                        <h3>Room Statistics</h3>
                    </div>
                    <div class="card-body">
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-icon primary">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div class="stat-info">
                                    <h3>
                                        <?php
                                        // Get booking count for this room
                                        include_once '../includes/config.php';
                                        $sql = "SELECT COUNT(*) as count FROM reservations WHERE room_id = ? AND status != 'cancelled'";
                                        $stmt = mysqli_prepare($conn, $sql);
                                        mysqli_stmt_bind_param($stmt, "i", $room_id);
                                        mysqli_stmt_execute($stmt);
                                        $result = mysqli_stmt_get_result($stmt);
                                        $booking_count = mysqli_fetch_assoc($result)['count'];
                                        mysqli_stmt_close($stmt);
                                        echo $booking_count;
                                        ?>
                                    </h3>
                                    <p>Total Bookings</p>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon success">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <div class="stat-info">
                                    <h3>
                                        <?php
                                        // Get total revenue from this room
                                        $sql = "SELECT SUM(total_amount) as revenue FROM reservations WHERE room_id = ? AND status IN ('confirmed', 'checked_in', 'checked_out')";
                                        $stmt = mysqli_prepare($conn, $sql);
                                        mysqli_stmt_bind_param($stmt, "i", $room_id);
                                        mysqli_stmt_execute($stmt);
                                        $result = mysqli_stmt_get_result($stmt);
                                        $revenue = mysqli_fetch_assoc($result)['revenue'] ?? 0;
                                        mysqli_stmt_close($stmt);
                                        echo '₦' . number_format($revenue, 2);
                                        ?>
                                    </h3>
                                    <p>Total Revenue</p>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon warning">
                                    <i class="fas fa-star"></i>
                                </div>
                                <div class="stat-info">
                                    <h3>
                                        <?php
                                        // Calculate occupancy rate (simplified)
                                        $occupancy_rate = $booking_count > 0 ? min(100, ($booking_count * 10)) : 0;
                                        echo $occupancy_rate . '%';
                                        ?>
                                    </h3>
                                    <p>Occupancy Rate</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Bookings for This Room -->
                <div class="card">
                    <div class="card-header">
                        <h3>Recent Bookings</h3>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get recent bookings for this room
                        include_once '../includes/config.php';
                        $sql = "SELECT r.booking_ref, r.guest_name, r.check_in, r.check_out, r.status 
                                FROM reservations r 
                                WHERE r.room_id = ? 
                                ORDER BY r.created_at DESC 
                                LIMIT 5";
                        $stmt = mysqli_prepare($conn, $sql);
                        mysqli_stmt_bind_param($stmt, "i", $room_id);
                        mysqli_stmt_execute($stmt);
                        $result = mysqli_stmt_get_result($stmt);
                        $recent_bookings = mysqli_fetch_all($result, MYSQLI_ASSOC);
                        mysqli_stmt_close($stmt);
                        // Close connection at the end
                        mysqli_close($conn);
                        ?>
                        
                        <?php if(empty($recent_bookings)): ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <p>No bookings found for this room.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Booking Ref</th>
                                            <th>Guest Name</th>
                                            <th>Check-in</th>
                                            <th>Check-out</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($recent_bookings as $booking): ?>
                                        <tr>
                                            <td><?php echo $booking['booking_ref']; ?></td>
                                            <td><?php echo htmlspecialchars($booking['guest_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($booking['check_in'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($booking['check_out'])); ?></td>
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

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Deletion</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this room? This action cannot be undone.</p>
                <p><strong>Room:</strong> <?php echo htmlspecialchars($room['room_number']) . ' - ' . ucfirst($room['room_type']); ?></p>
                <p class="text-warning"><i class="fas fa-exclamation-triangle"></i> Warning: This will also delete all future bookings for this room.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="manage_rooms.php" id="deleteForm">
                    <input type="hidden" name="room_id" value="<?php echo $room_id; ?>">
                    <input type="hidden" name="action" value="delete">
                    <button type="button" class="btn btn-outline" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" class="btn btn-error">Delete Room</button>
                </form>
            </div>
        </div>
    </div>

    <style>
        .room-status {
            display: flex;
            align-items: center;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-weight: 500;
            color: var(--dark);
        }

        .checkbox-label input[type="checkbox"] {
            display: none;
        }

        .checkmark {
            width: 20px;
            height: 20px;
            border: 2px solid var(--gray-light);
            border-radius: 4px;
            margin-right: 10px;
            position: relative;
            transition: all 0.3s;
        }

        .checkbox-label input[type="checkbox"]:checked + .checkmark {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .checkbox-label input[type="checkbox"]:checked + .checkmark:after {
            content: '✓';
            position: absolute;
            color: white;
            font-size: 14px;
            font-weight: bold;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: var(--gray-light);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease-out;
        }

        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.3s ease-out;
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
            color: var(--dark);
        }

        .close {
            color: var(--gray);
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s;
        }

        .close:hover {
            color: var(--error);
        }

        .modal-body {
            padding: 20px 25px;
        }

        .modal-body p {
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid var(--gray-light);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .text-warning {
            color: var(--warning);
            background: rgba(245, 158, 11, 0.1);
            padding: 10px;
            border-radius: 6px;
            margin: 10px 0;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>

    <script>
        // Mobile menu toggle
        document.getElementById('mobileMenuBtn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // Delete confirmation modal
        const deleteModal = document.getElementById('deleteModal');
        const closeBtn = document.querySelector('.close');

        function confirmDelete() {
            deleteModal.style.display = 'block';
        }

        function closeDeleteModal() {
            deleteModal.style.display = 'none';
        }

        closeBtn.addEventListener('click', closeDeleteModal);

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target == deleteModal) {
                closeDeleteModal();
            }
        });

        // Form validation
        document.getElementById('editRoomForm').addEventListener('submit', function(e) {
            const price = parseFloat(document.getElementById('price').value);
            const capacity = parseInt(document.getElementById('capacity').value);
            
            if (price <= 0) {
                e.preventDefault();
                alert('Price must be greater than 0.');
                return;
            }
            
            if (capacity < 1 || capacity > 10) {
                e.preventDefault();
                alert('Capacity must be between 1 and 10 guests.');
                return;
            }
        });

        // Auto-suggest amenities based on room type
        document.getElementById('room_type').addEventListener('change', function() {
            const roomType = this.value;
            const amenitiesTextarea = document.getElementById('amenities');
            
            const amenitySuggestions = {
                'standard': 'Free Wi-Fi, Air Conditioning, TV, Private Bathroom, Desk',
                'deluxe': 'Free Wi-Fi, Air Conditioning, Smart TV, Mini Bar, Private Bathroom, Balcony, Desk',
                'suite': 'Free Wi-Fi, Air Conditioning, Smart TV, Mini Bar, Private Bathroom, Balcony, Living Area, Work Desk, Premium Toiletries',
                'presidential': 'Free Wi-Fi, Air Conditioning, Smart TV, Mini Bar, Private Bathroom, Balcony, Living Area, Dining Area, Work Desk, Premium Toiletries, Jacuzzi, City View'
            };
            
            if (amenitySuggestions[roomType] && !amenitiesTextarea.value) {
                if(confirm('Would you like to update amenities based on the selected room type?')) {
                    amenitiesTextarea.value = amenitySuggestions[roomType];
                }
            }
        });

        // Price suggestion based on room type
        document.getElementById('room_type').addEventListener('change', function() {
            const roomType = this.value;
            const priceInput = document.getElementById('price');
            
            const priceSuggestions = {
                'standard': 15000,
                'deluxe': 25000,
                'suite': 40000,
                'presidential': 75000
            };
            
            if (priceSuggestions[roomType] && priceInput.value == <?php echo $room['price']; ?>) {
                if(confirm('Would you like to update the price based on the selected room type?')) {
                    priceInput.value = priceSuggestions[roomType];
                }
            }
        });
    </script>
</body>
</html>