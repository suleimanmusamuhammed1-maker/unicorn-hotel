<?php
// Include configuration and functions
include_once 'includes/config.php';
include_once 'includes/functions.php';

// Initialize variables
$checkin = $checkout = $guests = $room_type = $selected_room_id = '';
$errors = [];
$available_rooms = [];

// Process form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
    
    // Validate and sanitize input
    $checkin = sanitize_input($_POST["checkin"]);
    $checkout = sanitize_input($_POST["checkout"]);
    $guests = sanitize_input($_POST["guests"]);
    $room_type = sanitize_input($_POST["room_type"]);
    $selected_room_id = isset($_POST["selected_room_id"]) ? sanitize_input($_POST["selected_room_id"]) : '';
    
    // Validate dates
    if(empty($checkin)){
        $errors[] = "Please select check-in date.";
    }
    
    if(empty($checkout)){
        $errors[] = "Please select check-out date.";
    }
    
    if(!empty($checkin) && !empty($checkout)){
        if(strtotime($checkout) <= strtotime($checkin)){
            $errors[] = "Check-out date must be after check-in date.";
        }
        
        if(strtotime($checkin) < strtotime(date('Y-m-d'))){
            $errors[] = "Check-in date cannot be in the past.";
        }
    }
    
    // If no errors, check availability
    if(empty($errors)){
        // If a specific room is selected (from room card)
        if(!empty($selected_room_id)){
            $sql = "SELECT r.*, 
                    (SELECT COUNT(*) FROM reservations res 
                     WHERE res.room_id = r.id 
                     AND res.status != 'cancelled'
                     AND ((res.check_in <= ? AND res.check_out >= ?) 
                          OR (res.check_in <= ? AND res.check_out >= ?)
                          OR (res.check_in >= ? AND res.check_out <= ?))) as booking_count
                    FROM rooms r 
                    WHERE r.id = ? AND r.capacity >= ? AND r.available = 1";
            
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssssssii", $checkout, $checkin, $checkin, $checkout, $checkin, $checkout, $selected_room_id, $guests);
        } else {
            // Regular availability check by room type
            $sql = "SELECT r.*, 
                    (SELECT COUNT(*) FROM reservations res 
                     WHERE res.room_id = r.id 
                     AND res.status != 'cancelled'
                     AND ((res.check_in <= ? AND res.check_out >= ?) 
                          OR (res.check_in <= ? AND res.check_out >= ?)
                          OR (res.check_in >= ? AND res.check_out <= ?))) as booking_count
                    FROM rooms r 
                    WHERE r.room_type = ? AND r.capacity >= ? AND r.available = 1";
            
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssssssi", $checkout, $checkin, $checkin, $checkout, $checkin, $checkout, $room_type, $guests);
        }
        
        if(mysqli_stmt_execute($stmt)){
            $result = mysqli_stmt_get_result($stmt);
            
            // Check if rooms are available
            while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
                if($row['booking_count'] == 0){
                    $available_rooms[] = $row;
                }
            }
            
            if(empty($available_rooms)){
                if(!empty($selected_room_id)){
                    $errors[] = "The selected room is not available for the chosen dates. Please try different dates.";
                } else {
                    $errors[] = "No available " . ucfirst($room_type) . " rooms for the selected dates. Please try different dates or room type.";
                }
            } else {
                // Store booking details in session
                session_start();
                $room_id = $available_rooms[0]['id'];
                $nights = calculate_nights($checkin, $checkout);
                $total_amount = $available_rooms[0]['price'] * $nights;
                
                $_SESSION['booking_details'] = [
                    'room_id' => $room_id,
                    'room_type' => $available_rooms[0]['room_type'],
                    'room_number' => $available_rooms[0]['room_number'],
                    'room_price' => $available_rooms[0]['price'],
                    'check_in' => $checkin,
                    'check_out' => $checkout,
                    'guests' => $guests,
                    'nights' => $nights,
                    'total_amount' => $total_amount
                ];
                
                // Redirect to appropriate page based on user login status
                if(isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true){
                    header("location: user/booking_form.php");
                } else {
                    header("location: user/guest_booking.php");
                }
                exit();
            }
        } else{
            $errors[] = "Something went wrong. Please try again later.";
        }
        
        // Close statement
        mysqli_stmt_close($stmt);
    }
}

// If errors, show them on the homepage
if(!empty($errors)){
    session_start();
    $_SESSION['errors'] = $errors;
    $_SESSION['form_data'] = [
        'checkin' => $checkin,
        'checkout' => $checkout,
        'guests' => $guests,
        'room_type' => $room_type,
        'selected_room_id' => $selected_room_id
    ];
    header("location: index.php#booking");
    exit();
}
?>