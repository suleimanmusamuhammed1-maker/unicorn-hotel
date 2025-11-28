<?php
// Start session
session_start();

// Redirect if no booking success data
if(!isset($_SESSION['booking_success'])){
    header("location: ../index.php");
    exit();
}

$booking_data = $_SESSION['booking_success'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmation - Unicorn Hotel Damaturu</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                    <li><a href="../index.php">Home</a></li>
                    <li><a href="../index.php#rooms">Rooms</a></li>
                    <li><a href="../index.php#contact">Contact</a></li>
                </ul>
            </div>
        </div>
    </header>

    <!-- Confirmation Section -->
     <!-- Add payment instructions section -->
<div class="payment-instructions">
    <h3>Payment Instructions</h3>
    
    <?php if($booking_data['payment_method'] == 'bank_transfer'): ?>
        <div class="payment-details">
            <h4>Bank Transfer Details</h4>
            <div class="bank-details">
                <div class="detail-item">
                    <span class="detail-label">Bank Name:</span>
                    <span class="detail-value">Unicorn Bank</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Account Name:</span>
                    <span class="detail-value">Unicorn Hotel Damaturu</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Account Number:</span>
                    <span class="detail-value">0123456789</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Amount to Pay:</span>
                    <span class="detail-value">₦<?php echo number_format($booking_data['total_amount'], 2); ?></span>
                </div>
            </div>
            <p class="note">Please use your booking reference <strong><?php echo $booking_data['booking_ref']; ?></strong> as the payment reference.</p>
        </div>
    <?php elseif($booking_data['payment_method'] == 'cash'): ?>
        <div class="payment-details">
            <h4>Pay at Hotel</h4>
            <p>You have chosen to pay in cash when you arrive at the hotel.</p>
            <p>Please bring the total amount of <strong>₦<?php echo number_format($booking_data['total_amount'], 2); ?></strong> with you.</p>
        </div>
    <?php elseif($booking_data['payment_method'] == 'pos'): ?>
        <div class="payment-details">
            <h4>POS Payment</h4>
            <p>You can pay with your debit/credit card when you check in at the hotel.</p>
            <p>Total amount: <strong>₦<?php echo number_format($booking_data['total_amount'], 2); ?></strong></p>
        </div>
    <?php endif; ?>
</div>
    <section class="section-padding">
        <div class="container">
            <div class="confirmation-container">
                <div class="confirmation-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h2>Booking Confirmed!</h2>
                <p class="confirmation-subtitle">Thank you for choosing Unicorn Hotel Damaturu. Your reservation has been successfully completed.</p>
                
                <div class="confirmation-details">
                    <div class="detail-card">
                        <h3>Booking Details</h3>
                        <div class="detail-item">
                            <span class="detail-label">Booking Reference:</span>
                            <span class="detail-value"><?php echo $booking_data['booking_ref']; ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Guest Name:</span>
                            <span class="detail-value"><?php echo $booking_data['guest_name']; ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Email:</span>
                            <span class="detail-value"><?php echo $booking_data['email']; ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Reservation ID:</span>
                            <span class="detail-value">#<?php echo $booking_data['reservation_id']; ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="confirmation-message">
                    <h3>What's Next?</h3>
                    <p>A confirmation email has been sent to <strong><?php echo $booking_data['email']; ?></strong> with all the details of your booking.</p>
                    <p>Please present your booking reference at the reception upon arrival.</p>
                </div>
                
                <div class="confirmation-actions">
                    <a href="index.php" class="btn btn-primary">Back to Home</a>
                    <button onclick="window.print()" class="btn btn-outline">Print Confirmation</button>
                </div>
            </div>
        </div>
    </section>

    <style>
        .confirmation-container {
            max-width: 700px;
            margin: 0 auto;
            text-align: center;
        }
        
        .confirmation-icon {
            font-size: 5rem;
            color: var(--success);
            margin-bottom: 20px;
        }
        
        .confirmation-subtitle {
            font-size: 1.2rem;
            color: var(--gray);
            margin-bottom: 40px;
        }
        
        .confirmation-details {
            margin: 40px 0;
        }
        
        .detail-card {
            background-color: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            text-align: left;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--gray-light);
        }
        
        .detail-label {
            font-weight: 600;
            color: var(--dark);
        }
        
        .detail-value {
            color: var(--primary);
            font-weight: 500;
        }
        
        .confirmation-message {
            background-color: var(--light);
            padding: 30px;
            border-radius: 12px;
            margin: 30px 0;
            text-align: left;
        }
        
        .confirmation-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        @media (max-width: 768px) {
            .confirmation-actions {
                flex-direction: column;
            }
            
            .confirmation-actions .btn {
                width: 100%;
            }
        }
    </style>

    <?php 
    // Clear the success data after displaying
    unset($_SESSION['booking_success']);
    include_once '../includes/footer.php'; 
    ?>
</body>
</html>