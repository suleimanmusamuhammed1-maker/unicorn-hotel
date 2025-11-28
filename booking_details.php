<?php
// Start session and check authentication
session_start();
if(!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true){
    header("location: login.php");
    exit();
}

// Check if booking ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])){
    header("location: bookings.php");
    exit();
}

$booking_id = $_GET['id'];

// Include database configuration
include_once '../includes/config.php';

// Get booking details
$sql = "SELECT r.*, rm.room_type, rm.room_number, rm.price as room_price, rm.amenities
        FROM reservations r 
        LEFT JOIN rooms rm ON r.room_id = rm.id 
        WHERE r.id = ? AND r.guest_email = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "is", $booking_id, $_SESSION['user_email']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$booking = mysqli_fetch_assoc($result);

// Redirect if booking not found or doesn't belong to user
if(!$booking){
    header("location: bookings.php");
    exit();
}

// Calculate nights
$nights = ceil((strtotime($booking['check_out']) - strtotime($booking['check_in'])) / (60 * 60 * 24));

// Close connection
mysqli_stmt_close($stmt);
mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Details - Unicorn Hotel Damaturu</title>
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
        
        .page-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .booking-details-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .booking-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 25px 30px;
        }
        
        .booking-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        
        .status-confirmed { background: rgba(255,255,255,0.2); }
        .status-checked_in { background: var(--success); }
        .status-checked_out { background: var(--info); }
        .status-cancelled { background: var(--error); }
        .status-pending { background: var(--warning); }
        
        .booking-body {
            padding: 30px;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .detail-section {
            background: var(--light);
            padding: 20px;
            border-radius: 8px;
        }
        
        .detail-section h3 {
            margin-bottom: 15px;
            color: var(--primary);
            border-bottom: 1px solid var(--gray-light);
            padding-bottom: 10px;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .detail-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .detail-label {
            font-weight: 600;
            color: var(--dark);
        }
        
        .detail-value {
            color: var(--gray);
            text-align: right;
        }
        
        .amenities-list {
            list-style: none;
            padding: 0;
        }
        
        .amenities-list li {
            padding: 8px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
        }
        
        .amenities-list li:before {
            content: "✓";
            color: var(--success);
            margin-right: 10px;
            font-weight: bold;
        }
        
        .amenities-list li:last-child {
            border-bottom: none;
        }
        
        .booking-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            padding-top: 20px;
            border-top: 1px solid var(--gray-light);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
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
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--gray-light);
            color: var(--dark);
        }
        
        .btn-outline:hover {
            background-color: var(--gray-light);
        }
        
        .btn-error {
            background-color: var(--error);
            color: white;
        }
        
        .btn-error:hover {
            background-color: #dc2626;
        }
        
        .btn-success {
            background-color: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #0da271;
        }
        
        .print-only {
            display: none;
        }
        
        @media print {
            .user-header, .booking-actions, .btn {
                display: none;
            }
            
            .print-only {
                display: block;
            }
            
            .booking-details-card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
        
        @media (max-width: 768px) {
            .details-grid {
                grid-template-columns: 1fr;
            }
            
            .booking-actions {
                flex-direction: column;
            }
            
            .booking-actions .btn {
                width: 100%;
                justify-content: center;
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
                <!-- Page Header -->
                <div class="page-header">
                    <div>
                        <h2>Booking Details</h2>
                        <p>Reference: <?php echo $booking['booking_ref']; ?></p>
                    </div>
                    <div class="print-only">
                        <h3>Unicorn Hotel Damaturu - Booking Confirmation</h3>
                        <p>Generated on: <?php echo date('F j, Y \a\t g:i A'); ?></p>
                    </div>
                    <a href="bookings.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Back to Bookings
                    </a>
                </div>

                <!-- Booking Details Card -->
                <div class="booking-details-card">
                    <div class="booking-header">
                        <div class="booking-status status-<?php echo $booking['status']; ?>">
                            <?php echo ucfirst($booking['status']); ?>
                        </div>
                        <h2><?php echo ucfirst($booking['room_type']); ?> Room</h2>
                        <p>Room <?php echo $booking['room_number']; ?> • <?php echo $nights; ?> night<?php echo $nights > 1 ? 's' : ''; ?> stay</p>
                    </div>
                    
                    <div class="booking-body">
                        <div class="details-grid">
                            <!-- Guest Information -->
                            <div class="detail-section">
                                <h3><i class="fas fa-user"></i> Guest Information</h3>
                                <div class="detail-item">
                                    <span class="detail-label">Full Name:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($booking['guest_name']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Email:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($booking['guest_email']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Phone:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($booking['guest_phone']); ?></span>
                                </div>
                                <?php if(!empty($booking['guest_address'])): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Address:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($booking['guest_address']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Stay Information -->
                            <div class="detail-section">
                                <h3><i class="fas fa-calendar"></i> Stay Information</h3>
                                <div class="detail-item">
                                    <span class="detail-label">Check-in:</span>
                                    <span class="detail-value"><?php echo date('F j, Y', strtotime($booking['check_in'])); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Check-out:</span>
                                    <span class="detail-value"><?php echo date('F j, Y', strtotime($booking['check_out'])); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Duration:</span>
                                    <span class="detail-value"><?php echo $nights; ?> night<?php echo $nights > 1 ? 's' : ''; ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Guests:</span>
                                    <span class="detail-value"><?php echo $booking['guests']; ?> person<?php echo $booking['guests'] > 1 ? 's' : ''; ?></span>
                                </div>
                            </div>

                            <!-- Room Information -->
                            <div class="detail-section">
                                <h3><i class="fas fa-bed"></i> Room Information</h3>
                                <div class="detail-item">
                                    <span class="detail-label">Room Type:</span>
                                    <span class="detail-value"><?php echo ucfirst($booking['room_type']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Room Number:</span>
                                    <span class="detail-value"><?php echo $booking['room_number']; ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Price per Night:</span>
                                    <span class="detail-value">₦<?php echo number_format($booking['room_price'], 2); ?></span>
                                </div>
                            </div>

                            <!-- Payment Information -->
                            <div class="detail-section">
                                <h3><i class="fas fa-receipt"></i> Payment Information</h3>
                                <div class="detail-item">
                                    <span class="detail-label">Room Rate:</span>
                                    <span class="detail-value">₦<?php echo number_format($booking['room_price'], 2); ?> × <?php echo $nights; ?> nights</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Total Amount:</span>
                                    <span class="detail-value"><strong>₦<?php echo number_format($booking['total_amount'], 2); ?></strong></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Booking Date:</span>
                                    <span class="detail-value"><?php echo date('F j, Y \a\t g:i A', strtotime($booking['created_at'])); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Booking Reference:</span>
                                    <span class="detail-value"><strong><?php echo $booking['booking_ref']; ?></strong></span>
                                </div>
                            </div>
                        </div>

                        <!-- Amenities -->
                        <?php if(!empty($booking['amenities'])): ?>
                        <div class="detail-section">
                            <h3><i class="fas fa-star"></i> Room Amenities</h3>
                            <ul class="amenities-list">
                                <?php 
                                $amenities = explode(',', $booking['amenities']);
                                foreach($amenities as $amenity): 
                                    if(trim($amenity)):
                                ?>
                                <li><?php echo htmlspecialchars(trim($amenity)); ?></li>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </ul>
                        </div>
                        <?php endif; ?>

                        <!-- Special Requests -->
                        <?php if(!empty($booking['special_requests'])): ?>
                        <div class="detail-section">
                            <h3><i class="fas fa-comment"></i> Special Requests</h3>
                            <p><?php echo htmlspecialchars($booking['special_requests']); ?></p>
                        </div>
                        <?php endif; ?>

                        <!-- Booking Actions -->
                        <div class="booking-actions">
                            <button onclick="window.print()" class="btn btn-outline">
                                <i class="fas fa-print"></i> Print Confirmation
                            </button>
                            
                            <?php if(in_array($booking['status'], ['pending', 'confirmed'])): ?>
                                <form method="POST" action="cancel_booking.php" style="display: inline;">
                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                    <button type="submit" class="btn btn-error" 
                                            onclick="return confirm('Are you sure you want to cancel this booking? This action cannot be undone.')">
                                        <i class="fas fa-times"></i> Cancel Booking
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <?php if($booking['status'] == 'confirmed' && strtotime($booking['check_in']) <= time()): ?>
                                <a href="#" class="btn btn-success">
                                    <i class="fas fa-sign-in-alt"></i> Check-in Online
                                </a>
                            <?php endif; ?>
                            
                            <a href="../index.php" class="btn btn-primary">
                                <i class="fas fa-home"></i> Back to Home
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Help Section -->
                <div class="detail-section">
                    <h3><i class="fas fa-question-circle"></i> Need Help?</h3>
                    <p>If you have any questions about your booking, please contact our reception:</p>
                    <div class="detail-item">
                        <span class="detail-label">Phone:</span>
                        <span class="detail-value">+234 801 234 5678</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Email:</span>
                        <span class="detail-value">info@unicornhoteldamaturu.com</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Address:</span>
                        <span class="detail-value">123 Hotel Street, Damaturu, Yobe State</span>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Add print functionality
        function printBooking() {
            window.print();
        }
        
        // Add confirmation for cancellation
        document.addEventListener('DOMContentLoaded', function() {
            const cancelBtn = document.querySelector('.btn-error');
            if(cancelBtn) {
                cancelBtn.addEventListener('click', function(e) {
                    if(!confirm('Are you sure you want to cancel this booking? This action cannot be undone.')) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</body>
</html>