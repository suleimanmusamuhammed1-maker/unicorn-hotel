<?php
// Function to sanitize input data
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to format date
function format_date($date) {
    return date('F j, Y', strtotime($date));
}

// Function to calculate number of nights
function calculate_nights($check_in, $check_out) {
    $start = new DateTime($check_in);
    $end = new DateTime($check_out);
    $interval = $start->diff($end);
    return $interval->days;
}

// Function to generate booking reference
function generate_booking_ref() {
    return 'UNI' . strtoupper(uniqid());
}

// Function to get room price
function get_room_price($room_type) {
    $prices = [
        'standard' => 15000,
        'deluxe' => 25000,
        'suite' => 40000,
        'presidential' => 75000
    ];
    
    return isset($prices[$room_type]) ? $prices[$room_type] : 0;
}

// Function to send email notification
function send_booking_confirmation($email, $booking_details) {
    // In a real implementation, this would send an actual email
    // For now, we'll just log it
    error_log("Booking confirmation email would be sent to: " . $email);
    return true;
}
?>