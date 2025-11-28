<?php
session_start();

// Redirect if no booking details
if(!isset($_SESSION['booking_details'])){
    header("location: ../index.php");
    exit();
}

$booking_details = $_SESSION['booking_details'];

// Process form when submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
    // Include database configuration and functions
    include_once '../includes/config.php';
    include_once '../includes/functions.php';
    
    // Validate and sanitize inputs
    $full_name = sanitize_input($_POST["full_name"]);
    $email = sanitize_input($_POST["email"]);
    $phone = sanitize_input($_POST["phone"]);
    $address = sanitize_input($_POST["address"]);
    $special_requests = sanitize_input($_POST["special_requests"]);
    $payment_method = sanitize_input($_POST["payment_method"]);
    
    $errors = [];
    
    // Validate required fields
    if(empty($full_name)){
        $errors[] = "Please enter your full name.";
    }
    
    if(empty($email)){
        $errors[] = "Please enter your email address.";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        $errors[] = "Please enter a valid email address.";
    }
    
    if(empty($phone)){
        $errors[] = "Please enter your phone number.";
    }
    
    if(empty($payment_method)){
        $errors[] = "Please select a payment method.";
    }
    
    // If no errors, process booking
    if(empty($errors)){
        $booking_ref = generate_booking_ref();
        $room_id = $booking_details['room_id'];
        $check_in = $booking_details['check_in'];
        $check_out = $booking_details['check_out'];
        $guests = $booking_details['guests'];
        $total_amount = $booking_details['total_amount'];
        
        // Insert reservation into database
        $sql = "INSERT INTO reservations (booking_ref, room_id, guest_name, guest_email, guest_phone, guest_address, special_requests, check_in, check_out, guests, total_amount, status, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', ?)";
        
        if($stmt = mysqli_prepare($conn, $sql)){
            mysqli_stmt_bind_param($stmt, "sississsiids", $booking_ref, $room_id, $full_name, $email, $phone, $address, $special_requests, $check_in, $check_out, $guests, $total_amount, $payment_method);
            
            if(mysqli_stmt_execute($stmt)){
                $reservation_id = mysqli_insert_id($conn);
                
                // Check if user exists, if not create account
                $user_sql = "SELECT id FROM users WHERE guest_email = ?";
                $user_stmt = mysqli_prepare($conn, $user_sql);
                mysqli_stmt_bind_param($user_stmt, "s", $email);
                mysqli_stmt_execute($user_stmt);
                $user_result = mysqli_stmt_get_result($user_stmt);
                
                if(mysqli_num_rows($user_result) == 0){
                    // Create user account with temporary password
                    $temp_password = bin2hex(random_bytes(8)); // Generate random password
                    $password_hash = password_hash($temp_password, PASSWORD_DEFAULT);
                    
                    $create_user_sql = "INSERT INTO users (guest_name, guest_email, guest_phone, password_hash) VALUES (?, ?, ?, ?)";
                    $create_user_stmt = mysqli_prepare($conn, $create_user_sql);
                    mysqli_stmt_bind_param($create_user_stmt, "ssss", $full_name, $email, $phone, $password_hash);
                    mysqli_stmt_execute($create_user_stmt);
                    mysqli_stmt_close($create_user_stmt);
                    
                    // In production, you would send an email with the temporary password
                }
                
                mysqli_stmt_close($user_stmt);
                
                // Store success data in session
                $_SESSION['booking_success'] = [
                    'booking_ref' => $booking_ref,
                    'reservation_id' => $reservation_id,
                    'guest_name' => $full_name,
                    'email' => $email,
                    'total_amount' => $total_amount,
                    'payment_method' => $payment_method
                ];
                
                // Clear booking details
                unset($_SESSION['booking_details']);
                
                // Redirect to confirmation page
                header("location: confirmation.php");
                exit();
            } else{
                $errors[] = "Something went wrong. Please try again.";
            }
            
            mysqli_stmt_close($stmt);
        }
    }
    
    // Close connection
    mysqli_close($conn);
}
?>

<!-- Add this payment method section to the form -->
<div class="form-section">
    <h4><i class="fas fa-credit-card"></i> Payment Method</h4>
    <div class="form-control">
        <label for="payment_method">Select Payment Method *</label>
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
</div>