<?php
// Start session and check authentication
session_start();
if(!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true){
    header("location: login.php");
    exit();
}

// Check if booking ID is provided
if(!isset($_POST['booking_id']) || empty($_POST['booking_id'])){
    $_SESSION['error'] = "Invalid booking ID.";
    header("location: bookings.php");
    exit();
}

$booking_id = $_POST['booking_id'];

// Include database configuration
include_once '../includes/config.php';

// Check if booking belongs to user and can be cancelled
$sql = "SELECT id, status FROM reservations WHERE id = ? AND guest_email = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "is", $booking_id, $_SESSION['user_email']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$booking = mysqli_fetch_assoc($result);

if(!$booking){
    $_SESSION['error'] = "Booking not found or you don't have permission to cancel it.";
    header("location: bookings.php");
    exit();
}

// Check if booking can be cancelled
if(!in_array($booking['status'], ['pending', 'confirmed'])){
    $_SESSION['error'] = "This booking cannot be cancelled because it's already " . $booking['status'] . ".";
    header("location: bookings.php");
    exit();
}

// Cancel the booking
$update_sql = "UPDATE reservations SET status = 'cancelled' WHERE id = ?";
$update_stmt = mysqli_prepare($conn, $update_sql);
mysqli_stmt_bind_param($update_stmt, "i", $booking_id);

if(mysqli_stmt_execute($update_stmt)){
    $_SESSION['success'] = "Booking cancelled successfully.";
} else {
    $_SESSION['error'] = "Error cancelling booking. Please try again.";
}

mysqli_stmt_close($update_stmt);
mysqli_close($conn);

header("location: bookings.php");
exit();
?>