<?php
// Start session and check authentication
session_start();
if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true){
    header("location: login.php");
    exit();
}

// Include database configuration
include_once '../includes/config.php';

// Get report data
$reports = [];

// Total revenue
$sql = "SELECT SUM(total_amount) as total FROM reservations WHERE status IN ('confirmed', 'checked_in', 'checked_out')";
$result = mysqli_query($conn, $sql);
$reports['total_revenue'] = mysqli_fetch_assoc($result)['total'] ?? 0;

// Monthly revenue
$sql = "SELECT YEAR(created_at) as year, MONTH(created_at) as month, SUM(total_amount) as revenue 
        FROM reservations 
        WHERE status IN ('confirmed', 'checked_in', 'checked_out')
        GROUP BY YEAR(created_at), MONTH(created_at) 
        ORDER BY year DESC, month DESC 
        LIMIT 6";
$result = mysqli_query($conn, $sql);
$reports['monthly_revenue'] = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Room type performance
$sql = "SELECT rm.room_type, COUNT(r.id) as bookings, SUM(r.total_amount) as revenue
        FROM reservations r
        LEFT JOIN rooms rm ON r.room_id = rm.id
        WHERE r.status IN ('confirmed', 'checked_in', 'checked_out')
        GROUP BY rm.room_type
        ORDER BY revenue DESC";
$result = mysqli_query($conn, $sql);
$reports['room_performance'] = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Booking status distribution
$sql = "SELECT status, COUNT(*) as count FROM reservations GROUP BY status";
$result = mysqli_query($conn, $sql);
$reports['status_distribution'] = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Recent activities
$sql = "SELECT a.*, u.username 
        FROM admin_activities a 
        LEFT JOIN admin_users u ON a.admin_id = u.id 
        ORDER BY a.created_at DESC 
        LIMIT 10";
$result = mysqli_query($conn, $sql);
$reports['recent_activities'] = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Close connection
mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Unicorn Hotel Admin</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <a href="manage_rooms.php" class="menu-item">
                    <i class="fas fa-bed"></i>
                    <span>Manage Rooms</span>
                </a>
                <a href="manage_users.php" class="menu-item">
                    <i class="fas fa-users"></i>
                    <span>Manage Users</span>
                </a>
                <a href="reports.php" class="menu-item active">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
                <a href="settings.php" class="menu-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</a>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="admin-main">
            <!-- Header -->
            <div class="admin-header">
                <div class="header-left">
                    <h1>Reports & Analytics</h1>
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
                <!-- Report Filters -->
                <div class="card">
                    <div class="card-header">
                        <h3>Report Filters</h3>
                    </div>
                    <div class="card-body">
                        <form class="filter-form">
                            <div class="form-row">
                                <div class="form-control">
                                    <label for="date_range">Date Range</label>
                                    <select id="date_range">
                                        <option value="7days">Last 7 Days</option>
                                        <option value="30days" selected>Last 30 Days</option>
                                        <option value="3months">Last 3 Months</option>
                                        <option value="6months">Last 6 Months</option>
                                        <option value="1year">Last 1 Year</option>
                                        <option value="custom">Custom Range</option>
                                    </select>
                                </div>
                                <div class="form-control">
                                    <label for="report_type">Report Type</label>
                                    <select id="report_type">
                                        <option value="financial">Financial Report</option>
                                        <option value="occupancy">Occupancy Report</option>
                                        <option value="performance">Performance Report</option>
                                        <option value="all" selected>All Reports</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Apply Filters
                                </button>
                                <button type="button" class="btn btn-outline" onclick="printReports()">
                                    <i class="fas fa-print"></i> Print Reports
                                </button>
                                <button type="button" class="btn btn-success" onclick="exportReports('csv')">
                                    <i class="fas fa-file-csv"></i> Export CSV
                                </button>
                                <button type="button" class="btn btn-info" onclick="exportReports('pdf')">
                                    <i class="fas fa-file-pdf"></i> Export PDF
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Financial Overview -->
                <div class="card">
                    <div class="card-header">
                        <h3>Financial Overview</h3>
                    </div>
                    <div class="card-body">
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-icon primary">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <div class="stat-info">
                                    <h3>₦<?php echo number_format($reports['total_revenue'], 2); ?></h3>
                                    <p>Total Revenue</p>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon success">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div class="stat-info">
                                    <h3>
                                        <?php
                                        $total_bookings = array_sum(array_column($reports['status_distribution'], 'count'));
                                        echo $total_bookings;
                                        ?>
                                    </h3>
                                    <p>Total Bookings</p>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon warning">
                                    <i class="fas fa-bed"></i>
                                </div>
                                <div class="stat-info">
                                    <h3>
                                        <?php
                                        $confirmed_bookings = 0;
                                        foreach($reports['status_distribution'] as $status){
                                            if(in_array($status['status'], ['confirmed', 'checked_in', 'checked_out'])){
                                                $confirmed_bookings += $status['count'];
                                            }
                                        }
                                        echo $confirmed_bookings;
                                        ?>
                                    </h3>
                                    <p>Successful Bookings</p>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon error">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                                <div class="stat-info">
                                    <h3>
                                        <?php
                                        $cancelled_bookings = 0;
                                        foreach($reports['status_distribution'] as $status){
                                            if($status['status'] == 'cancelled'){
                                                $cancelled_bookings = $status['count'];
                                                break;
                                            }
                                        }
                                        echo $cancelled_bookings;
                                        ?>
                                    </h3>
                                    <p>Cancelled Bookings</p>
                                </div>
                            </div>
                        </div>

                        <!-- Revenue Chart -->
                        <div class="chart-container">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="content-row">
                    <div class="content-col">
                        <!-- Room Performance -->
                        <div class="card">
                            <div class="card-header">
                                <h3>Room Performance</h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Room Type</th>
                                                <th>Bookings</th>
                                                <th>Revenue</th>
                                                <th>Avg. Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($reports['room_performance'] as $room): ?>
                                            <tr>
                                                <td><?php echo ucfirst($room['room_type']); ?></td>
                                                <td><?php echo $room['bookings']; ?></td>
                                                <td>₦<?php echo number_format($room['revenue'], 2); ?></td>
                                                <td>₦<?php echo number_format($room['revenue'] / $room['bookings'], 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Booking Status Distribution -->
                        <div class="card">
                            <div class="card-header">
                                <h3>Booking Status</h3>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="statusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="content-col">
                        <!-- Recent Activities -->
                        <div class="card">
                            <div class="card-header">
                                <h3>Recent Activities</h3>
                            </div>
                            <div class="card-body">
                                <div class="activities-list">
                                    <?php foreach($reports['recent_activities'] as $activity): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon">
                                            <i class="fas fa-<?php echo getActivityIcon($activity['activity_type']); ?>"></i>
                                        </div>
                                        <div class="activity-details">
                                            <div class="activity-description">
                                                <?php echo htmlspecialchars($activity['description']); ?>
                                            </div>
                                            <div class="activity-meta">
                                                <span class="activity-user"><?php echo $activity['username']; ?></span>
                                                <span class="activity-time"><?php echo time_elapsed_string($activity['created_at']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 20px;
        }
        
        .activities-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 15px;
            background: var(--light);
            border-radius: 8px;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .activity-details {
            flex: 1;
        }
        
        .activity-description {
            margin-bottom: 5px;
            color: var(--dark);
        }
        
        .activity-meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            color: var(--gray);
        }
        
        .content-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-top: 30px;
        }
        
        @media (max-width: 992px) {
            .content-row {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <script>
        // Mobile menu toggle
        document.getElementById('mobileMenuBtn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: [
                    <?php 
                    $months = [];
                    foreach(array_reverse($reports['monthly_revenue']) as $data){
                        $months[] = "'" . date('M Y', mktime(0, 0, 0, $data['month'], 1, $data['year'])) . "'";
                    }
                    echo implode(', ', $months);
                    ?>
                ],
                datasets: [{
                    label: 'Monthly Revenue',
                    data: [
                        <?php 
                        $revenues = [];
                        foreach(array_reverse($reports['monthly_revenue']) as $data){
                            $revenues[] = $data['revenue'];
                        }
                        echo implode(', ', $revenues);
                        ?>
                    ],
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₦' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: [
                    <?php 
                    $statuses = [];
                    foreach($reports['status_distribution'] as $status){
                        $statuses[] = "'" . ucfirst($status['status']) . "'";
                    }
                    echo implode(', ', $statuses);
                    ?>
                ],
                datasets: [{
                    data: [
                        <?php 
                        $counts = [];
                        foreach($reports['status_distribution'] as $status){
                            $counts[] = $status['count'];
                        }
                        echo implode(', ', $counts);
                        ?>
                    ],
                    backgroundColor: [
                        '#10b981', // confirmed - green
                        '#2563eb', // checked_in - blue
                        '#3b82f6', // checked_out - light blue
                        '#f59e0b', // pending - yellow
                        '#ef4444'  // cancelled - red
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Utility functions
        function printReports() {
            window.print();
        }

        function exportReports() {
            alert('Export functionality would be implemented here. This would generate a CSV/PDF report.');
        }
    </script>
</body>
</html>

<?php
// Helper functions for reports
function getActivityIcon($activityType) {
    $icons = [
        'login' => 'sign-in-alt',
        'logout' => 'sign-out-alt',
        'booking_create' => 'calendar-plus',
        'booking_update' => 'calendar-check',
        'room_update' => 'bed',
        'user_update' => 'user-cog'
    ];
    return $icons[$activityType] ?? 'circle';
}

function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>