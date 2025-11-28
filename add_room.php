<?php
// Start session and check authentication
session_start();
if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true){
    header("location: login.php");
    exit();
}

// Include database configuration
include_once '../includes/config.php';

// Initialize variables
$success = '';
$error = '';

// Process form when submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
    // Get and validate form data
    $room_type = mysqli_real_escape_string($conn, $_POST['room_type']);
    $room_number = mysqli_real_escape_string($conn, $_POST['room_number']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $price = floatval($_POST['price']);
    $capacity = intval($_POST['capacity']);
    $amenities = mysqli_real_escape_string($conn, $_POST['amenities']);
    
    // Validate required fields
    if(empty($room_type) || empty($room_number) || empty($description) || $price <= 0 || $capacity <= 0){
        $error = "Please fill in all required fields with valid values.";
    } else {
        // Check if room number already exists
        $check_sql = "SELECT id FROM rooms WHERE room_number = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "s", $room_number);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        
        if(mysqli_stmt_num_rows($check_stmt) > 0){
            $error = "Room number already exists. Please use a different room number.";
        } else {
            // Insert new room
            $sql = "INSERT INTO rooms (room_type, room_number, description, price, capacity, amenities, available) VALUES (?, ?, ?, ?, ?, ?, 1)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssdis", $room_type, $room_number, $description, $price, $capacity, $amenities);
            
            if(mysqli_stmt_execute($stmt)){
                // Log the activity
                $activity_sql = "INSERT INTO admin_activities (admin_id, activity_type, description) VALUES (?, 'room_create', ?)";
                $activity_stmt = mysqli_prepare($conn, $activity_sql);
                $description = "Created new room: $room_number ($room_type)";
                mysqli_stmt_bind_param($activity_stmt, "is", $_SESSION['admin_id'], $description);
                mysqli_stmt_execute($activity_stmt);
                mysqli_stmt_close($activity_stmt);
                
                $success = "Room added successfully!";
                
                // Clear form
                $_POST = array();
            } else {
                $error = "Error adding room. Please try again.";
            }
            
            mysqli_stmt_close($stmt);
        }
        
        mysqli_stmt_close($check_stmt);
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
    <title>Add New Room - Unicorn Hotel Admin</title>
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
                <a href="manage_bookings.php" class="menu-item">
                    <i class="fas fa-calendar-check"></i>
                    <span>Manage Bookings</span>
                </a>
                <a href="manage_rooms.php" class="menu-item active">
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
                    <h1>Add New Room</h1>
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
                
                <?php if(!empty($error)): ?>
                    <div class="alert alert-error">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <!-- Add Room Form -->
                <div class="card">
                    <div class="card-header">
                        <h3>Room Information</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="addRoomForm">
                            <div class="form-group">
                                <div class="form-control">
                                    <label for="room_type">Room Type *</label>
                                    <select id="room_type" name="room_type" required>
                                        <option value="">Select Room Type</option>
                                        <option value="standard" <?php echo isset($_POST['room_type']) && $_POST['room_type'] == 'standard' ? 'selected' : ''; ?>>Standard Room</option>
                                        <option value="deluxe" <?php echo isset($_POST['room_type']) && $_POST['room_type'] == 'deluxe' ? 'selected' : ''; ?>>Deluxe Room</option>
                                        <option value="suite" <?php echo isset($_POST['room_type']) && $_POST['room_type'] == 'suite' ? 'selected' : ''; ?>>Executive Suite</option>
                                        <option value="presidential" <?php echo isset($_POST['room_type']) && $_POST['room_type'] == 'presidential' ? 'selected' : ''; ?>>Presidential Suite</option>
                                    </select>
                                </div>
                                <div class="form-control">
                                    <label for="room_number">Room Number *</label>
                                    <input type="text" id="room_number" name="room_number" value="<?php echo isset($_POST['room_number']) ? $_POST['room_number'] : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-control">
                                <label for="description">Description *</label>
                                <textarea id="description" name="description" rows="3" required><?php echo isset($_POST['description']) ? $_POST['description'] : ''; ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <div class="form-control">
                                    <label for="price">Price per Night (₦) *</label>
                                    <input type="number" id="price" name="price" min="0" step="0.01" value="<?php echo isset($_POST['price']) ? $_POST['price'] : ''; ?>" required>
                                </div>
                                <div class="form-control">
                                    <label for="capacity">Capacity (Guests) *</label>
                                    <input type="number" id="capacity" name="capacity" min="1" max="10" value="<?php echo isset($_POST['capacity']) ? $_POST['capacity'] : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-control">
                                <label for="amenities">Amenities</label>
                                <textarea id="amenities" name="amenities" rows="4" placeholder="Enter amenities separated by commas"><?php echo isset($_POST['amenities']) ? $_POST['amenities'] : ''; ?></textarea>
                                <small>Example: Free Wi-Fi, Air Conditioning, TV, Mini Bar, Private Bathroom</small>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-plus"></i> Add Room
                                </button>
                                <a href="manage_rooms.php" class="btn btn-outline">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Room Type Guidelines -->
                <div class="card">
                    <div class="card-header">
                        <h3>Room Type Guidelines</h3>
                    </div>
                    <div class="card-body">
                        <div class="guidelines">
                            <div class="guideline-item">
                                <h4>Standard Room</h4>
                                <ul>
                                    <li>Price Range: ₦15,000 - ₦20,000</li>
                                    <li>Capacity: 2 guests</li>
                                    <li>Basic amenities</li>
                                </ul>
                            </div>
                            <div class="guideline-item">
                                <h4>Deluxe Room</h4>
                                <ul>
                                    <li>Price Range: ₦25,000 - ₦35,000</li>
                                    <li>Capacity: 2-3 guests</li>
                                    <li>Enhanced amenities</li>
                                </ul>
                            </div>
                            <div class="guideline-item">
                                <h4>Executive Suite</h4>
                                <ul>
                                    <li>Price Range: ₦40,000 - ₦60,000</li>
                                    <li>Capacity: 3-4 guests</li>
                                    <li>Premium amenities</li>
                                </ul>
                            </div>
                            <div class="guideline-item">
                                <h4>Presidential Suite</h4>
                                <ul>
                                    <li>Price Range: ₦75,000+</li>
                                    <li>Capacity: 4+ guests</li>
                                    <li>Luxury amenities</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .guidelines {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .guideline-item {
            background: var(--light);
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }
        
        .guideline-item h4 {
            margin-bottom: 10px;
            color: var(--primary);
        }
        
        .guideline-item ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .guideline-item li {
            margin-bottom: 5px;
            color: var(--gray);
        }
    </style>

    <script>
        // Mobile menu toggle
        document.getElementById('mobileMenuBtn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // Form validation
        document.getElementById('addRoomForm').addEventListener('submit', function(e) {
            const price = parseFloat(document.getElementById('price').value);
            const capacity = parseInt(document.getElementById('capacity').value);
            
            if (price <= 0) {
                e.preventDefault();
                alert('Price must be greater than 0.');
                return;
            }
            
            if (capacity < 1 || capacity > 10) {
                e.preventDefault();
                alert('Capacity must be between 1 and 10 guests.');
                return;
            }
        });

        // Auto-suggest amenities based on room type
        document.getElementById('room_type').addEventListener('change', function() {
            const roomType = this.value;
            const amenitiesTextarea = document.getElementById('amenities');
            
            const amenitySuggestions = {
                'standard': 'Free Wi-Fi, Air Conditioning, TV, Private Bathroom, Desk',
                'deluxe': 'Free Wi-Fi, Air Conditioning, Smart TV, Mini Bar, Private Bathroom, Balcony, Desk',
                'suite': 'Free Wi-Fi, Air Conditioning, Smart TV, Mini Bar, Private Bathroom, Balcony, Living Area, Work Desk, Premium Toiletries',
                'presidential': 'Free Wi-Fi, Air Conditioning, Smart TV, Mini Bar, Private Bathroom, Balcony, Living Area, Dining Area, Work Desk, Premium Toiletries, Jacuzzi, City View'
            };
            
            if (amenitySuggestions[roomType] && !amenitiesTextarea.value) {
                amenitiesTextarea.value = amenitySuggestions[roomType];
            }
        });
    </script>
</body>
</html>