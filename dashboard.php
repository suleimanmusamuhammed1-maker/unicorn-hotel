<?php
// Start session and check authentication
session_start();
if(!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true){
    header("location: login.php");
    exit();
}

// Include database configuration
include_once '../includes/config.php';

// Get user's bookings
$user_id = $_SESSION['user_id'];
$sql = "SELECT r.*, rm.room_type, rm.room_number 
        FROM reservations r 
        LEFT JOIN rooms rm ON r.room_id = rm.id 
        WHERE r.guest_email = ? 
        ORDER BY r.created_at DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $_SESSION['user_email']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$bookings = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Close connection
mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Unicorn Hotel Damaturu</title>
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
        
        .welcome-card {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            text-align: center;
        }
        
        .stat-card i {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .stat-card h3 {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }
        
        .booking-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .booking-header {
            padding: 15px 20px;
            background-color: var(--light);
            border-bottom: 1px solid var(--gray-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .booking-body {
            padding: 20px;
        }
        
        .booking-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 0.8rem;
            color: var(--gray);
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-weight: 500;
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
                            <li><a href="dashboard.php" class="active">Dashboard</a></li>
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
                <!-- Welcome Card -->
                <div class="welcome-card">
                    <h2>Welcome back, <?php echo $_SESSION['user_name']; ?>! 👋</h2>
                    <p>Manage your bookings and profile from your personal dashboard.</p>
                </div>

                <!-- Stats Cards -->
                <div class="stats-cards">
                    <div class="stat-card">
                        <i class="fas fa-calendar-check"></i>
                        <h3><?php echo count($bookings); ?></h3>
                        <p>Total Bookings</p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-bed"></i>
                        <h3>
                            <?php 
                            $upcoming = array_filter($bookings, function($booking) {
                                return in_array($booking['status'], ['confirmed', 'checked_in']) && 
                                       strtotime($booking['check_in']) >= time();
                            });
                            echo count($upcoming);
                            ?>
                        </h3>
                        <p>Upcoming Stays</p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-history"></i>
                        <h3>
                            <?php 
                            $past = array_filter($bookings, function($booking) {
                                return $booking['status'] == 'checked_out' || 
                                       strtotime($booking['check_out']) < time();
                            });
                            echo count($past);
                            ?>
                        </h3>
                        <p>Past Stays</p>
                    </div>
                </div>

                <!-- Recent Bookings -->
                <div class="card">
                    <div class="card-header">
                        <h3>Recent Bookings</h3>
                        <a href="bookings.php" class="btn btn-outline btn-sm">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if(empty($bookings)): ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <h3>No Bookings Yet</h3>
                                <p>You haven't made any bookings yet.</p>
                                <a href="../index.php" class="btn btn-primary">Book a Room</a>
                            </div>
                        <?php else: ?>
                            <?php foreach(array_slice($bookings, 0, 3) as $booking): ?>
                            <div class="booking-card">
                                <div class="booking-header">
                                    <div class="booking-ref">
                                        <strong><?php echo $booking['booking_ref']; ?></strong>
                                    </div>
                                    <div class="booking-status">
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
                                    </div>
                                </div>
                                <div class="booking-body">
                                    <div class="booking-details">
                                        <div class="detail-item">
                                            <span class="detail-label">Room</span>
                                            <span class="detail-value"><?php echo ucfirst($booking['room_type']) . ' (' . $booking['room_number'] . ')'; ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Check-in</span>
                                            <span class="detail-value"><?php echo date('M j, Y', strtotime($booking['check_in'])); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Check-out</span>
                                            <span class="detail-value"><?php echo date('M j, Y', strtotime($booking['check_out'])); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Total Amount</span>
                                            <span class="detail-value">₦<?php echo number_format($booking['total_amount'], 2); ?></span>
                                        </div>
                                    </div>
                                    <div class="booking-actions">
                                        <a href="booking_details.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-outline">View Details</a>
                                        <?php if(in_array($booking['status'], ['pending', 'confirmed'])): ?>
                                            <form method="POST" action="cancel_booking.php" style="display: inline;">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-error" 
                                                        onclick="return confirm('Are you sure you want to cancel this booking?')">
                                                    Cancel Booking
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <style>
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .badge-primary {
            background-color: rgba(37, 99, 235, 0.1);
            color: var(--primary);
        }
        
        .badge-info {
            background-color: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }
        
        .badge-error {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--error);
        }
        
        .badge-warning {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        .booking-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            text-align: center;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
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
    </style>
</body>
</html>