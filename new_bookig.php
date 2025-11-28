<?php
// Start session and check authentication
session_start();
if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true){
    header("location: login.php");
    exit();
}

// Include database configuration and functions
include_once '../includes/config.php';
include_once '../includes/functions.php';

// Initialize variables
$errors = [];
$success = '';

// Get available rooms for dropdown
$rooms_sql = "SELECT id, room_type, room_number, price, capacity FROM rooms WHERE available = 1 ORDER BY room_type, room_number";
$rooms_result = mysqli_query($conn, $rooms_sql);
$rooms = mysqli_fetch_all($rooms_result, MYSQLI_ASSOC);

// Process form when submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
    // Get and validate form data
    $room_id = sanitize_input($_POST['room_id']);
    $guest_name = sanitize_input($_POST['guest_name']);
    $guest_email = sanitize_input($_POST['guest_email']);
    $guest_phone = sanitize_input($_POST['guest_phone']);
    $guest_address = sanitize_input($_POST['guest_address']);
    $check_in = sanitize_input($_POST['check_in']);
    $check_out = sanitize_input($_POST['check_out']);
    $guests = sanitize_input($_POST['guests']);
    $special_requests = sanitize_input($_POST['special_requests']);
    
    // Validate required fields
    if(empty($room_id)) $errors[] = "Please select a room.";
    if(empty($guest_name)) $errors[] = "Please enter guest name.";
    if(empty($guest_email)) $errors[] = "Please enter guest email.";
    if(empty($guest_phone)) $errors[] = "Please enter guest phone.";
    if(empty($check_in)) $errors[] = "Please select check-in date.";
    if(empty($check_out)) $errors[] = "Please select check-out date.";
    if(empty($guests)) $errors[] = "Please enter number of guests.";
    
    // Validate dates
    if(!empty($check_in) && !empty($check_out)){
        if(strtotime($check_out) <= strtotime($check_in)){
            $errors[] = "Check-out date must be after check-in date.";
        }
    }
    
    // Validate email
    if(!empty($guest_email) && !filter_var($guest_email, FILTER_VALIDATE_EMAIL)){
        $errors[] = "Please enter a valid email address.";
    }
    
    // If no errors, create booking
    if(empty($errors)){
        // Check room availability for the selected dates
        $availability_sql = "SELECT COUNT(*) as count FROM reservations 
                            WHERE room_id = ? AND status != 'cancelled'
                            AND ((check_in <= ? AND check_out >= ?) 
                                OR (check_in <= ? AND check_out >= ?)
                                OR (check_in >= ? AND check_out <= ?))";
        $stmt = mysqli_prepare($conn, $availability_sql);
        mysqli_stmt_bind_param($stmt, "issssss", $room_id, $check_out, $check_in, $check_in, $check_out, $check_in, $check_out);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $availability = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if($availability['count'] > 0){
            $errors[] = "Selected room is not available for the chosen dates.";
        } else {
            // Calculate total amount
            $room_sql = "SELECT price FROM rooms WHERE id = ?";
            $stmt = mysqli_prepare($conn, $room_sql);
            mysqli_stmt_bind_param($stmt, "i", $room_id);
            mysqli_stmt_execute($stmt);
            $room_result = mysqli_stmt_get_result($stmt);
            $room = mysqli_fetch_assoc($room_result);
            mysqli_stmt_close($stmt);
            
            $nights = calculate_nights($check_in, $check_out);
            $total_amount = $room['price'] * $nights;
            
            // Generate booking reference and create booking
            $booking_ref = generate_booking_ref();
            
            $insert_sql = "INSERT INTO reservations (booking_ref, room_id, guest_name, guest_email, guest_phone, guest_address, special_requests, check_in, check_out, guests, total_amount, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed')";
            $stmt = mysqli_prepare($conn, $insert_sql);
            mysqli_stmt_bind_param($stmt, "sisssssssid", $booking_ref, $room_id, $guest_name, $guest_email, $guest_phone, $guest_address, $special_requests, $check_in, $check_out, $guests, $total_amount);
            
            if(mysqli_stmt_execute($stmt)){
                $reservation_id = mysqli_insert_id($conn);
                
                // Log the activity
                $activity_sql = "INSERT INTO admin_activities (admin_id, activity_type, description) VALUES (?, 'booking_create', ?)";
                $activity_stmt = mysqli_prepare($conn, $activity_sql);
                $description = "Created new booking #$reservation_id for $guest_name";
                mysqli_stmt_bind_param($activity_stmt, "is", $_SESSION['admin_id'], $description);
                mysqli_stmt_execute($activity_stmt);
                mysqli_stmt_close($activity_stmt);
                
                $success = "Booking created successfully! Booking Reference: $booking_ref";
                
                // Clear form
                $guest_name = $guest_email = $guest_phone = $guest_address = $special_requests = '';
                $check_in = $check_out = $guests = '';
            } else {
                $errors[] = "Error creating booking. Please try again.";
            }
            
            mysqli_stmt_close($stmt);
        }
    }
}

// Close connection
mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Booking - Unicorn Hotel Admin</title>
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
                    <h1>Create New Booking</h1>
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
                
                <?php if(!empty($errors)): ?>
                    <div class="alert alert-error">
                        <?php foreach($errors as $error): ?>
                            <p><?php echo $error; ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Booking Form -->
                <div class="card">
                    <div class="card-header">
                        <h3>Booking Information</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="bookingForm">
                            <div class="form-row">
                                <div class="form-control">
                                    <label for="room_id">Room *</label>
                                    <select id="room_id" name="room_id" required>
                                        <option value="">Select a Room</option>
                                        <?php foreach($rooms as $room): ?>
                                            <option value="<?php echo $room['id']; ?>" 
                                                    data-price="<?php echo $room['price']; ?>"
                                                    data-capacity="<?php echo $room['capacity']; ?>"
                                                    <?php echo isset($_POST['room_id']) && $_POST['room_id'] == $room['id'] ? 'selected' : ''; ?>>
                                                <?php echo ucfirst($room['room_type']) . ' - ' . $room['room_number'] . ' (₦' . number_format($room['price'], 2) . '/night)'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-control">
                                    <label for="guests">Number of Guests *</label>
                                    <input type="number" id="guests" name="guests" min="1" max="10" 
                                           value="<?php echo isset($_POST['guests']) ? $_POST['guests'] : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-control">
                                    <label for="check_in">Check-in Date *</label>
                                    <input type="date" id="check_in" name="check_in" 
                                           value="<?php echo isset($_POST['check_in']) ? $_POST['check_in'] : ''; ?>" required>
                                </div>
                                <div class="form-control">
                                    <label for="check_out">Check-out Date *</label>
                                    <input type="date" id="check_out" name="check_out" 
                                           value="<?php echo isset($_POST['check_out']) ? $_POST['check_out'] : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-control">
                                    <label for="guest_name">Guest Name *</label>
                                    <input type="text" id="guest_name" name="guest_name" 
                                           value="<?php echo isset($_POST['guest_name']) ? $_POST['guest_name'] : ''; ?>" required>
                                </div>
                                <div class="form-control">
                                    <label for="guest_email">Guest Email *</label>
                                    <input type="email" id="guest_email" name="guest_email" 
                                           value="<?php echo isset($_POST['guest_email']) ? $_POST['guest_email'] : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-control">
                                    <label for="guest_phone">Guest Phone *</label>
                                    <input type="tel" id="guest_phone" name="guest_phone" 
                                           value="<?php echo isset($_POST['guest_phone']) ? $_POST['guest_phone'] : ''; ?>" required>
                                </div>
                                <div class="form-control">
                                    <label for="guest_address">Guest Address</label>
                                    <input type="text" id="guest_address" name="guest_address" 
                                           value="<?php echo isset($_POST['guest_address']) ? $_POST['guest_address'] : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="form-control">
                                <label for="special_requests">Special Requests</label>
                                <textarea id="special_requests" name="special_requests" rows="4"><?php echo isset($_POST['special_requests']) ? $_POST['special_requests'] : ''; ?></textarea>
                            </div>
                            
                            <!-- Booking Summary -->
                            <div id="bookingSummary" class="booking-summary" style="display: none;">
                                <h4>Booking Summary</h4>
                                <div class="summary-details">
                                    <div class="summary-row">
                                        <span class="summary-label">Room:</span>
                                        <span id="summaryRoom" class="summary-value"></span>
                                    </div>
                                    <div class="summary-row">
                                        <span class="summary-label">Nights:</span>
                                        <span id="summaryNights" class="summary-value"></span>
                                    </div>
                                    <div class="summary-row">
                                        <span class="summary-label">Total Amount:</span>
                                        <span id="summaryTotal" class="summary-value"></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Create Booking
                                </button>
                                <a href="manage_bookings.php" class="btn btn-outline">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .booking-summary {
            background-color: var(--light);
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid var(--primary);
        }
        
        .booking-summary h4 {
            margin-bottom: 15px;
            color: var(--primary);
        }
        
        .summary-details {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .summary-label {
            font-weight: 600;
            color: var(--dark);
        }
        
        .summary-value {
            color: var(--primary);
            font-weight: 500;
        }
        
        #summaryTotal {
            font-size: 1.2rem;
            font-weight: bold;
        }
    </style>

    <script>
        // Mobile menu toggle
        document.getElementById('mobileMenuBtn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // Calculate and display booking summary
        const roomSelect = document.getElementById('room_id');
        const checkInInput = document.getElementById('check_in');
        const checkOutInput = document.getElementById('check_out');
        const guestsInput = document.getElementById('guests');
        const bookingSummary = document.getElementById('bookingSummary');
        
        function calculateBookingSummary() {
            const roomOption = roomSelect.options[roomSelect.selectedIndex];
            const roomPrice = roomOption.getAttribute('data-price');
            const roomCapacity = roomOption.getAttribute('data-capacity');
            const checkIn = new Date(checkInInput.value);
            const checkOut = new Date(checkOutInput.value);
            const guests = parseInt(guestsInput.value);
            
            // Validate capacity
            if (guests > roomCapacity) {
                alert(`Selected room can only accommodate ${roomCapacity} guests.`);
                guestsInput.value = roomCapacity;
                return;
            }
            
            if (roomPrice && checkInInput.value && checkOutInput.value) {
                const nights = Math.ceil((checkOut - checkIn) / (1000 * 60 * 60 * 24));
                const totalAmount = roomPrice * nights;
                
                document.getElementById('summaryRoom').textContent = roomOption.text;
                document.getElementById('summaryNights').textContent = nights;
                document.getElementById('summaryTotal').textContent = '₦' + totalAmount.toLocaleString('en-US', {minimumFractionDigits: 2});
                
                bookingSummary.style.display = 'block';
            } else {
                bookingSummary.style.display = 'none';
            }
        }
        
        // Add event listeners
        roomSelect.addEventListener('change', calculateBookingSummary);
        checkInInput.addEventListener('change', calculateBookingSummary);
        checkOutInput.addEventListener('change', calculateBookingSummary);
        guestsInput.addEventListener('input', calculateBookingSummary);
        
        // Set minimum dates
        const today = new Date().toISOString().split('T')[0];
        checkInInput.min = today;
        
        checkInInput.addEventListener('change', function() {
            checkOutInput.min = this.value;
        });
    </script>
</body>
</html>