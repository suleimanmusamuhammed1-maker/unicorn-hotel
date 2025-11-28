<?php
// Start session and check authentication
session_start();
if(!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true){
    header("location: login.php");
    exit();
}

// Redirect if no booking details
if(!isset($_SESSION['booking_details'])){
    header("location: ../index.php");
    exit();
}

$booking_details = $_SESSION['booking_details'];

// Include database configuration
include_once '../includes/config.php';
include_once '../includes/functions.php';

// Process form when submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
    // Validate and sanitize inputs
    $special_requests = sanitize_input($_POST["special_requests"]);
    $payment_method = sanitize_input($_POST["payment_method"]);
    
    $errors = [];
    
    // Validate payment method
    if(empty($payment_method)){
        $errors[] = "Please select a payment method.";
    }
    
    // Process booking
    if(empty($errors)){
        $booking_ref = generate_booking_ref();
        $room_id = $booking_details['room_id'];
        $check_in = $booking_details['check_in'];
        $check_out = $booking_details['check_out'];
        $guests = $booking_details['guests'];
        $total_amount = $booking_details['total_amount'];
        
        // Insert reservation into database
        $sql = "INSERT INTO reservations (booking_ref, room_id, guest_name, guest_email, guest_phone, guest_address, special_requests, check_in, check_out, guests, total_amount, status, payment_method, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', ?, ?)";
        
        if($stmt = mysqli_prepare($conn, $sql)){
            // Prepare variables for binding
            $status = 'confirmed';
            $empty_address = ''; // Since we're not collecting address from logged-in users
            $user_id = $_SESSION['user_id'];
            
            // Debug: Count parameters
            $param_count = 13; // Count of ? in SQL query
            
            // Correct parameter binding - count carefully
            // Parameters: booking_ref, room_id, guest_name, guest_email, guest_phone, guest_address, special_requests, check_in, check_out, guests, total_amount, payment_method, user_id
            $bind_result = mysqli_stmt_bind_param($stmt, "sisssssssidss", 
                $booking_ref, 
                $room_id, 
                $_SESSION['user_name'], 
                $_SESSION['user_email'], 
                $_SESSION['user_phone'], 
                $empty_address, 
                $special_requests, 
                $check_in, 
                $check_out, 
                $guests, 
                $total_amount,
                $payment_method, 
                $user_id
            );
            
            if(!$bind_result) {
                $errors[] = "Database binding error: " . mysqli_error($conn);
                error_log("Bind error: " . mysqli_error($conn));
            } elseif(mysqli_stmt_execute($stmt)){
                $reservation_id = mysqli_insert_id($conn);
                
                // Store success data in session
                $_SESSION['booking_success'] = [
                    'booking_ref' => $booking_ref,
                    'reservation_id' => $reservation_id,
                    'guest_name' => $_SESSION['user_name'],
                    'email' => $_SESSION['user_email'],
                    'total_amount' => $total_amount,
                    'payment_method' => $payment_method
                ];
                
                // Clear booking details
                unset($_SESSION['booking_details']);
                
                // Redirect to confirmation page
                header("location: confirmation.php");
                exit();
            } else{
                $errors[] = "Error creating booking. Please try again.";
                error_log("Booking execution error: " . mysqli_error($conn));
            }
            
            mysqli_stmt_close($stmt);
        } else {
            $errors[] = "Database error. Please try again.";
            error_log("Prepare statement error: " . mysqli_error($conn));
        }
    }
    
    // Close connection
    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Booking - Unicorn Hotel Damaturu</title>
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
        
        .booking-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }
        
        .booking-summary {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            height: fit-content;
        }
        
        .summary-card {
            background-color: var(--light);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--gray-light);
        }
        
        .summary-item.total {
            border-bottom: none;
            font-weight: bold;
            font-size: 1.1rem;
            color: var(--primary);
        }
        
        .booking-form-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .user-info {
            background: var(--light);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .user-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .user-detail:last-child {
            margin-bottom: 0;
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
        
        .form-control textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--gray-light);
            border-radius: 8px;
            font-size: 1rem;
            transition: border 0.3s, box-shadow 0.3s;
            resize: vertical;
        }
        
        .form-control textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background-color: rgba(239, 68, 68, 0.1);
            border-left: 4px solid var(--error);
            color: var(--error);
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
        
        .btn-block {
            display: block;
            width: 100%;
        }
        
        /* Payment Methods Styles */
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }
        
        .payment-option {
            cursor: pointer;
        }
        
        .payment-option input[type="radio"] {
            display: none;
        }
        
        .payment-card {
            border: 2px solid var(--gray-light);
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s;
            background: white;
        }
        
        .payment-option input[type="radio"]:checked + .payment-card {
            border-color: var(--primary);
            background: rgba(37, 99, 235, 0.05);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .payment-card i {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 10px;
            display: block;
        }
        
        .payment-card span {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--dark);
        }
        
        .payment-card small {
            color: var(--gray);
            font-size: 0.8rem;
        }
        
        @media (max-width: 768px) {
            .booking-content {
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
                            <li><a href="profile.php">Profile</a></li>
                            <li><a href="logout.php">Logout</a></li>
                        </ul>
                    </nav>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="user-main">
            <div class="container">
                <div class="page-header" style="margin-bottom: 30px;">
                    <h2>Complete Your Booking</h2>
                    <p>Review your booking details and confirm your reservation</p>
                </div>

                <!-- Success/Error Messages -->
                <?php if(isset($errors) && !empty($errors)): ?>
                    <div class="alert alert-error">
                        <?php foreach($errors as $error): ?>
                            <p><?php echo $error; ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="booking-content">
                    <!-- Booking Summary -->
                    <div class="booking-summary">
                        <h3>Booking Summary</h3>
                        <div class="summary-card">
                            <div class="summary-item">
                                <span class="summary-label">Room Type:</span>
                                <span class="summary-value"><?php echo ucfirst($booking_details['room_type']); ?> Room</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Room Number:</span>
                                <span class="summary-value"><?php echo $booking_details['room_number']; ?></span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Check-in:</span>
                                <span class="summary-value"><?php echo date('F j, Y', strtotime($booking_details['check_in'])); ?></span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Check-out:</span>
                                <span class="summary-value"><?php echo date('F j, Y', strtotime($booking_details['check_out'])); ?></span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Nights:</span>
                                <span class="summary-value"><?php echo $booking_details['nights']; ?></span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Guests:</span>
                                <span class="summary-value"><?php echo $booking_details['guests']; ?></span>
                            </div>
                            <div class="summary-item total">
                                <span class="summary-label">Total Amount:</span>
                                <span class="summary-value">₦<?php echo number_format($booking_details['total_amount'], 2); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Booking Form -->
                    <div class="booking-form-container">
                        <h3>Confirm Booking</h3>
                        
                        <div class="user-info">
                            <h4>Your Information</h4>
                            <div class="user-detail">
                                <span>Name:</span>
                                <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                            </div>
                            <div class="user-detail">
                                <span>Email:</span>
                                <span><?php echo htmlspecialchars($_SESSION['user_email']); ?></span>
                            </div>
                            <div class="user-detail">
                                <span>Phone:</span>
                                <span><?php echo htmlspecialchars($_SESSION['user_phone']); ?></span>
                            </div>
                            <p style="margin-top: 15px; color: var(--gray); font-size: 0.9rem;">
                                Your profile information will be used for this booking. 
                                <a href="profile.php">Update profile</a> if needed.
                            </p>
                        </div>
                        
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            <!-- Payment Method Section -->
                            <div class="form-control">
                                <label for="payment_method">Payment Method *</label>
                                <div class="payment-methods">
                                    <label class="payment-option">
                                        <input type="radio" name="payment_method" value="bank_transfer" required>
                                        <div class="payment-card">
                                            <i class="fas fa-university"></i>
                                            <span>Bank Transfer</span>
                                            <small>Transfer funds to our bank account</small>
                                        </div>
                                    </label>
                                    <label class="payment-option">
                                        <input type="radio" name="payment_method" value="cash" required>
                                        <div class="payment-card">
                                            <i class="fas fa-money-bill-wave"></i>
                                            <span>Pay at Hotel</span>
                                            <small>Pay in cash when you arrive</small>
                                        </div>
                                    </label>
                                    <label class="payment-option">
                                        <input type="radio" name="payment_method" value="pos" required>
                                        <div class="payment-card">
                                            <i class="fas fa-credit-card"></i>
                                            <span>POS Payment</span>
                                            <small>Pay with card at the hotel</small>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="form-control">
                                <label for="special_requests">Special Requests (Optional)</label>
                                <textarea id="special_requests" name="special_requests" rows="4" placeholder="Any special requests or preferences..."><?php echo isset($_POST['special_requests']) ? $_POST['special_requests'] : ''; ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">Confirm Booking</button>
                        </form>
                        
                        <p style="text-align: center; margin-top: 20px; color: var(--gray);">
                            By confirming this booking, you agree to our <a href="#">Terms & Conditions</a>.
                        </p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const paymentSelected = document.querySelector('input[name="payment_method"]:checked');
            
            if (!paymentSelected) {
                e.preventDefault();
                alert('Please select a payment method.');
                return;
            }
        });

        // Auto-select first payment method for better UX
        document.addEventListener('DOMContentLoaded', function() {
            const firstPaymentMethod = document.querySelector('input[name="payment_method"]');
            if (firstPaymentMethod) {
                firstPaymentMethod.checked = true;
            }
        });
    </script>
</body>
</html>