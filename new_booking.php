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
$rooms_sql = "SELECT id, room_type, room_number, price, capacity, available FROM rooms WHERE available = 1 ORDER BY room_type, room_number";
$rooms_result = mysqli_query($conn, $rooms_sql);
$rooms = mysqli_fetch_all($rooms_result, MYSQLI_ASSOC);

// Get existing users for dropdown
$users_sql = "SELECT id, guest_name, guest_email, guest_phone FROM users WHERE active = 1 ORDER BY guest_name";
$users_result = mysqli_query($conn, $users_sql);
$users = mysqli_fetch_all($users_result, MYSQLI_ASSOC);

// Process form when submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
    // Get and validate form data
    $room_id = sanitize_input($_POST['room_id']);
    $user_type = sanitize_input($_POST['user_type']);
    $existing_user_id = sanitize_input($_POST['existing_user_id']);
    $guest_name = sanitize_input($_POST['guest_name']);
    $guest_email = sanitize_input($_POST['guest_email']);
    $guest_phone = sanitize_input($_POST['guest_phone']);
    $guest_address = sanitize_input($_POST['guest_address']);
    $check_in = sanitize_input($_POST['check_in']);
    $check_out = sanitize_input($_POST['check_out']);
    $guests = sanitize_input($_POST['guests']);
    $special_requests = sanitize_input($_POST['special_requests']);
    $booking_status = sanitize_input($_POST['booking_status']);
    
    // Validate required fields
    if(empty($room_id)) $errors[] = "Please select a room.";
    if(empty($check_in)) $errors[] = "Please select check-in date.";
    if(empty($check_out)) $errors[] = "Please select check-out date.";
    if(empty($guests)) $errors[] = "Please enter number of guests.";
    if(empty($booking_status)) $errors[] = "Please select booking status.";
    
    // Validate guest information based on user type
    if($user_type == 'existing'){
        if(empty($existing_user_id)) $errors[] = "Please select an existing user.";
    } else {
        if(empty($guest_name)) $errors[] = "Please enter guest name.";
        if(empty($guest_email)) $errors[] = "Please enter guest email.";
        if(empty($guest_phone)) $errors[] = "Please enter guest phone.";
        
        if(!empty($guest_email) && !filter_var($guest_email, FILTER_VALIDATE_EMAIL)){
            $errors[] = "Please enter a valid email address.";
        }
    }
    
    // Validate dates
    if(!empty($check_in) && !empty($check_out)){
        if(strtotime($check_out) <= strtotime($check_in)){
            $errors[] = "Check-out date must be after check-in date.";
        }
        
        if(strtotime($check_in) < strtotime(date('Y-m-d'))){
            $errors[] = "Check-in date cannot be in the past.";
        }
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
            // Get guest information
            if($user_type == 'existing'){
                $user_sql = "SELECT guest_name, guest_email, guest_phone FROM users WHERE id = ?";
                $user_stmt = mysqli_prepare($conn, $user_sql);
                mysqli_stmt_bind_param($user_stmt, "i", $existing_user_id);
                mysqli_stmt_execute($user_stmt);
                $user_result = mysqli_stmt_get_result($user_stmt);
                $user = mysqli_fetch_assoc($user_result);
                mysqli_stmt_close($user_stmt);
                
                $guest_name = $user['guest_name'];
                $guest_email = $user['guest_email'];
                $guest_phone = $user['guest_phone'];
                $user_id = $existing_user_id;
            } else {
                $user_id = null;
                
                // Check if guest email already exists in users table
                $check_user_sql = "SELECT id FROM users WHERE guest_email = ?";
                $check_user_stmt = mysqli_prepare($conn, $check_user_sql);
                mysqli_stmt_bind_param($check_user_stmt, "s", $guest_email);
                mysqli_stmt_execute($check_user_stmt);
                mysqli_stmt_store_result($check_user_stmt);
                
                if(mysqli_stmt_num_rows($check_user_stmt) == 0){
                    // Create new user account
                    $temp_password = bin2hex(random_bytes(8));
                    $password_hash = password_hash($temp_password, PASSWORD_DEFAULT);
                    
                    $create_user_sql = "INSERT INTO users (guest_name, guest_email, guest_phone, password_hash) VALUES (?, ?, ?, ?)";
                    $create_user_stmt = mysqli_prepare($conn, $create_user_sql);
                    mysqli_stmt_bind_param($create_user_stmt, "ssss", $guest_name, $guest_email, $guest_phone, $password_hash);
                    mysqli_stmt_execute($create_user_stmt);
                    $user_id = mysqli_insert_id($conn);
                    mysqli_stmt_close($create_user_stmt);
                } else {
                    mysqli_stmt_bind_result($check_user_stmt, $existing_user_id);
                    mysqli_stmt_fetch($check_user_stmt);
                    $user_id = $existing_user_id;
                }
                mysqli_stmt_close($check_user_stmt);
            }
            
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
            
            $insert_sql = "INSERT INTO reservations (booking_ref, room_id, guest_name, guest_email, guest_phone, guest_address, special_requests, check_in, check_out, guests, total_amount, status, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $insert_sql);
            mysqli_stmt_bind_param($stmt, "sisssisssiisi", $booking_ref, $room_id, $guest_name, $guest_email, $guest_phone, $guest_address, $special_requests, $check_in, $check_out, $guests, $total_amount, $booking_status, $user_id);
            
            if(mysqli_stmt_execute($stmt)){
                $reservation_id = mysqli_insert_id($conn);
                
                // Log the activity
                $activity_sql = "INSERT INTO admin_activities (admin_id, activity_type, description) VALUES (?, 'booking_create', ?)";
                $activity_stmt = mysqli_prepare($conn, $activity_sql);
                $description = "Created new booking #$reservation_id for $guest_name";
                mysqli_stmt_bind_param($activity_stmt, "is", $_SESSION['admin_id'], $description);
                mysqli_stmt_execute($activity_stmt);
                mysqli_stmt_close($activity_stmt);
                
                $success = "Booking created successfully! Booking Reference: <strong>$booking_ref</strong>";
                
                // Clear form if success
                $_POST = array();
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
    <title>Create New Booking - Unicorn Hotel Admin</title>
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
                            <!-- Room Selection -->
                            <div class="form-section">
                                <h4><i class="fas fa-bed"></i> Room Selection</h4>
                                <div class="form-group">
                                    <div class="form-control">
                                        <label for="room_id">Room *</label>
                                        <select id="room_id" name="room_id" required onchange="updateRoomDetails()">
                                            <option value="">Select a Room</option>
                                            <?php foreach($rooms as $room): ?>
                                                <option value="<?php echo $room['id']; ?>" 
                                                        data-price="<?php echo $room['price']; ?>"
                                                        data-capacity="<?php echo $room['capacity']; ?>"
                                                        data-type="<?php echo $room['room_type']; ?>"
                                                        data-number="<?php echo $room['room_number']; ?>"
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
                                
                                <div id="roomDetails" class="room-details" style="display: none;">
                                    <div class="room-info-card">
                                        <h5>Selected Room Details</h5>
                                        <div class="room-info">
                                            <span id="roomType"></span>
                                            <span id="roomNumber"></span>
                                            <span id="roomPrice"></span>
                                            <span id="roomCapacity"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Dates -->
                            <div class="form-section">
                                <h4><i class="fas fa-calendar"></i> Stay Dates</h4>
                                <div class="form-group">
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
                            </div>

                            <!-- Guest Information -->
                            <div class="form-section">
                                <h4><i class="fas fa-user"></i> Guest Information</h4>
                                
                                <div class="form-control">
                                    <label>Guest Type *</label>
                                    <div class="radio-group">
                                        <label class="radio-label">
                                            <input type="radio" name="user_type" value="existing" <?php echo (!isset($_POST['user_type']) || $_POST['user_type'] == 'existing') ? 'checked' : ''; ?> onchange="toggleUserType()">
                                            <span class="radiomark"></span>
                                            Existing User
                                        </label>
                                        <label class="radio-label">
                                            <input type="radio" name="user_type" value="new" <?php echo isset($_POST['user_type']) && $_POST['user_type'] == 'new' ? 'checked' : ''; ?> onchange="toggleUserType()">
                                            <span class="radiomark"></span>
                                            New Guest
                                        </label>
                                    </div>
                                </div>

                                <!-- Existing User Selection -->
                                <div id="existingUserSection" class="form-control">
                                    <label for="existing_user_id">Select Existing User *</label>
                                    <select id="existing_user_id" name="existing_user_id">
                                        <option value="">Select a User</option>
                                        <?php foreach($users as $user): ?>
                                            <option value="<?php echo $user['id']; ?>" 
                                                    data-name="<?php echo htmlspecialchars($user['guest_name']); ?>"
                                                    data-email="<?php echo htmlspecialchars($user['guest_email']); ?>"
                                                    data-phone="<?php echo htmlspecialchars($user['guest_phone']); ?>"
                                                    <?php echo isset($_POST['existing_user_id']) && $_POST['existing_user_id'] == $user['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($user['guest_name']) . ' - ' . htmlspecialchars($user['guest_email']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- New Guest Information -->
                                <div id="newUserSection" class="form-group" style="display: none;">
                                    <div class="form-control">
                                        <label for="guest_name">Full Name *</label>
                                        <input type="text" id="guest_name" name="guest_name" 
                                               value="<?php echo isset($_POST['guest_name']) ? $_POST['guest_name'] : ''; ?>">
                                    </div>
                                    <div class="form-control">
                                        <label for="guest_email">Email Address *</label>
                                        <input type="email" id="guest_email" name="guest_email" 
                                               value="<?php echo isset($_POST['guest_email']) ? $_POST['guest_email'] : ''; ?>">
                                    </div>
                                    <div class="form-control">
                                        <label for="guest_phone">Phone Number *</label>
                                        <input type="tel" id="guest_phone" name="guest_phone" 
                                               value="<?php echo isset($_POST['guest_phone']) ? $_POST['guest_phone'] : ''; ?>">
                                    </div>
                                </div>
                                
                                <div class="form-control">
                                    <label for="guest_address">Address</label>
                                    <input type="text" id="guest_address" name="guest_address" 
                                           value="<?php echo isset($_POST['guest_address']) ? $_POST['guest_address'] : ''; ?>">
                                </div>
                            </div>

                            <!-- Additional Information -->
                            <div class="form-section">
                                <h4><i class="fas fa-info-circle"></i> Additional Information</h4>
                                
                                <div class="form-group">
                                    <div class="form-control">
                                        <label for="booking_status">Booking Status *</label>
                                        <select id="booking_status" name="booking_status" required>
                                            <option value="">Select Status</option>
                                            <option value="pending" <?php echo isset($_POST['booking_status']) && $_POST['booking_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="confirmed" <?php echo (!isset($_POST['booking_status']) || $_POST['booking_status'] == 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                                            <option value="checked_in" <?php echo isset($_POST['booking_status']) && $_POST['booking_status'] == 'checked_in' ? 'selected' : ''; ?>>Checked In</option>
                                            <option value="checked_out" <?php echo isset($_POST['booking_status']) && $_POST['booking_status'] == 'checked_out' ? 'selected' : ''; ?>>Checked Out</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-control">
                                    <label for="special_requests">Special Requests</label>
                                    <textarea id="special_requests" name="special_requests" rows="4"><?php echo isset($_POST['special_requests']) ? $_POST['special_requests'] : ''; ?></textarea>
                                </div>
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
                                <button type="button" class="btn btn-primary" onclick="calculateBooking()">
                                    <i class="fas fa-calculator"></i> Calculate
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--gray-light);
        }
        
        .form-section h4 {
            margin-bottom: 15px;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .room-details {
            margin-top: 15px;
        }
        
        .room-info-card {
            background: var(--light);
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }
        
        .room-info-card h5 {
            margin-bottom: 10px;
            color: var(--dark);
        }
        
        .room-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
        }
        
        .room-info span {
            padding: 5px 10px;
            background: white;
            border-radius: 4px;
            font-size: 0.9rem;
            text-align: center;
        }
        
        .radio-group {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .radio-label {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-weight: 500;
            color: var(--dark);
        }
        
        .radio-label input[type="radio"] {
            display: none;
        }
        
        .radiomark {
            width: 18px;
            height: 18px;
            border: 2px solid var(--gray-light);
            border-radius: 50%;
            margin-right: 8px;
            position: relative;
            transition: all 0.3s;
        }
        
        .radio-label input[type="radio"]:checked + .radiomark {
            border-color: var(--primary);
        }
        
        .radio-label input[type="radio"]:checked + .radiomark:after {
            content: '';
            position: absolute;
            width: 10px;
            height: 10px;
            background: var(--primary);
            border-radius: 50%;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        
        .booking-summary {
            background: var(--light);
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

        // Set minimum dates
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('check_in').min = today;
            
            // Update checkout min date when checkin changes
            document.getElementById('check_in').addEventListener('change', function() {
                document.getElementById('check_out').min = this.value;
                calculateBooking();
            });
            
            document.getElementById('check_out').addEventListener('change', calculateBooking);
            document.getElementById('guests').addEventListener('input', calculateBooking);
            
            // Initialize user type toggle
            toggleUserType();
            
            // Initialize room details if room is preselected
            updateRoomDetails();
        });

        // Toggle between existing user and new guest
        function toggleUserType() {
            const userType = document.querySelector('input[name="user_type"]:checked').value;
            const existingSection = document.getElementById('existingUserSection');
            const newSection = document.getElementById('newUserSection');
            
            if (userType === 'existing') {
                existingSection.style.display = 'block';
                newSection.style.display = 'none';
                
                // Clear new user fields
                document.getElementById('guest_name').value = '';
                document.getElementById('guest_email').value = '';
                document.getElementById('guest_phone').value = '';
                
                // Make existing user selection required
                document.getElementById('existing_user_id').required = true;
                document.getElementById('guest_name').required = false;
                document.getElementById('guest_email').required = false;
                document.getElementById('guest_phone').required = false;
            } else {
                existingSection.style.display = 'none';
                newSection.style.display = 'flex';
                
                // Clear existing user selection
                document.getElementById('existing_user_id').value = '';
                
                // Make new user fields required
                document.getElementById('existing_user_id').required = false;
                document.getElementById('guest_name').required = true;
                document.getElementById('guest_email').required = true;
                document.getElementById('guest_phone').required = true;
            }
        }

        // Update room details display
        function updateRoomDetails() {
            const roomSelect = document.getElementById('room_id');
            const roomDetails = document.getElementById('roomDetails');
            const roomOption = roomSelect.options[roomSelect.selectedIndex];
            
            if (roomOption.value) {
                const roomType = roomOption.getAttribute('data-type');
                const roomNumber = roomOption.getAttribute('data-number');
                const roomPrice = roomOption.getAttribute('data-price');
                const roomCapacity = roomOption.getAttribute('data-capacity');
                
                document.getElementById('roomType').textContent = ucfirst(roomType) + ' Room';
                document.getElementById('roomNumber').textContent = 'Room ' + roomNumber;
                document.getElementById('roomPrice').textContent = '₦' + parseFloat(roomPrice).toLocaleString('en-US', {minimumFractionDigits: 2}) + '/night';
                document.getElementById('roomCapacity').textContent = 'Capacity: ' + roomCapacity + ' guests';
                
                roomDetails.style.display = 'block';
                
                // Validate capacity
                const guests = parseInt(document.getElementById('guests').value);
                if (guests > roomCapacity) {
                    alert(`Selected room can only accommodate ${roomCapacity} guests.`);
                    document.getElementById('guests').value = roomCapacity;
                }
            } else {
                roomDetails.style.display = 'none';
            }
            
            calculateBooking();
        }

        // Calculate booking summary
        function calculateBooking() {
            const roomSelect = document.getElementById('room_id');
            const checkInInput = document.getElementById('check_in');
            const checkOutInput = document.getElementById('check_out');
            const guestsInput = document.getElementById('guests');
            const bookingSummary = document.getElementById('bookingSummary');
            
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

        // Auto-fill guest info when selecting existing user
        document.getElementById('existing_user_id').addEventListener('change', function() {
            const userOption = this.options[this.selectedIndex];
            if (userOption.value) {
                // You can auto-fill the guest info fields if needed
                const userName = userOption.getAttribute('data-name');
                const userEmail = userOption.getAttribute('data-email');
                const userPhone = userOption.getAttribute('data-phone');
                
                // Optionally display user info
                console.log('Selected user:', userName, userEmail, userPhone);
            }
        });

        // Helper function to capitalize first letter
        function ucfirst(string) {
            return string.charAt(0).toUpperCase() + string.slice(1);
        }

        // Form validation
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            const roomSelect = document.getElementById('room_id');
            const guests = parseInt(document.getElementById('guests').value);
            const roomCapacity = roomSelect.options[roomSelect.selectedIndex].getAttribute('data-capacity');
            
            if (guests > roomCapacity) {
                e.preventDefault();
                alert(`Selected room can only accommodate ${roomCapacity} guests.`);
                return;
            }
            
            // Validate user type specific fields
            const userType = document.querySelector('input[name="user_type"]:checked').value;
            if (userType === 'existing' && !document.getElementById('existing_user_id').value) {
                e.preventDefault();
                alert('Please select an existing user.');
                return;
            }
            
            if (userType === 'new') {
                const guestName = document.getElementById('guest_name').value;
                const guestEmail = document.getElementById('guest_email').value;
                const guestPhone = document.getElementById('guest_phone').value;
                
                if (!guestName || !guestEmail || !guestPhone) {
                    e.preventDefault();
                    alert('Please fill in all guest information fields.');
                    return;
                }
                
                // Basic email validation
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(guestEmail)) {
                    e.preventDefault();
                    alert('Please enter a valid email address.');
                    return;
                }
            }
        });
    </script>
</body>
</html>