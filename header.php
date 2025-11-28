<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Unicorn Hotel Damaturu'; ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .user-menu {
            position: relative;
        }

        .user-welcome {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: var(--light);
            border-radius: 6px;
            text-decoration: none;
            color: var(--dark);
            transition: all 0.3s;
        }

        .user-welcome:hover {
            background: var(--primary);
            color: white;
        }

        .user-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            min-width: 200px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s;
            z-index: 1000;
        }

        .user-dropdown.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .user-dropdown a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            text-decoration: none;
            color: var(--dark);
            border-bottom: 1px solid var(--gray-light);
            transition: all 0.3s;
        }

        .user-dropdown a:last-child {
            border-bottom: none;
        }

        .user-dropdown a:hover {
            background: var(--light);
            color: var(--primary);
        }

        .user-dropdown a i {
            width: 16px;
            text-align: center;
        }

        @media (max-width: 768px) {
            .user-dropdown {
                position: static;
                box-shadow: none;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container header-container">
            <div class="logo">
                <span class="logo-icon"><i class="fas fa-crown"></i></span>
                <h1>Unicorn Hotel Damaturu</h1>
            </div>
            <nav>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="index.php#rooms">Rooms</a></li>
                    <li><a href="index.php#amenities">Amenities</a></li>
                    <li><a href="index.php#gallery">Gallery</a></li>
                    <li><a href="index.php#contact">Contact</a></li>
                    <?php if(isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true): ?>
                        <li class="user-menu">
                            <a href="user/dashboard.php" class="user-welcome">
                                <i class="fas fa-user"></i> Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                            </a>
                            <div class="user-dropdown">
                                <a href="user/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                                <a href="user/bookings.php"><i class="fas fa-calendar-check"></i> My Bookings</a>
                                <a href="user/profile.php"><i class="fas fa-user-cog"></i> Profile</a>
                                <a href="user/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                            </div>
                        </li>
                    <?php elseif(isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true): ?>
                        <li>
                            <a href="admin/dashboard.php" class="btn btn-primary">
                                <i class="fas fa-cog"></i> Admin Panel
                            </a>
                        </li>
                        <li>
                            <a href="admin/logout.php" class="btn btn-outline">Logout</a>
                        </li>
                    <?php else: ?>
                        <li>
                            <a href="user/login.php" class="btn btn-outline">Login</a>
                        </li>
                        <li>
                            <a href="user/register.php" class="btn btn-primary">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <div class="mobile-menu-btn">
                <i class="fas fa-bars"></i>
            </div>
        </div>
    </header>

    <script>
        // Mobile menu functionality
        document.querySelector('.mobile-menu-btn').addEventListener('click', function() {
            document.querySelector('nav ul').classList.toggle('show');
        });

        // User dropdown functionality
        const userWelcome = document.querySelector('.user-welcome');
        if(userWelcome) {
            userWelcome.addEventListener('click', function(e) {
                e.preventDefault();
                this.nextElementSibling.classList.toggle('show');
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if(!userWelcome.parentElement.contains(e.target)) {
                    userWelcome.nextElementSibling.classList.remove('show');
                }
            });
        }
    </script>
