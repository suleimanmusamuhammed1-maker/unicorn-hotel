<?php
session_start();

// Redirect if already logged in
if(isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true){
    header("location: dashboard.php");
    exit();
}

// Include database configuration and functions
include_once '../includes/config.php';
include_once '../includes/functions.php';

// Define variables and initialize with empty values
$name = $email = $phone = $password = $confirm_password = "";
$errors = [];

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
    
    // Validate name
    if(empty(trim($_POST["name"]))){
        $errors[] = "Please enter your name.";
    } else{
        $name = trim($_POST["name"]);
    }
    
    // Validate email
    if(empty(trim($_POST["email"]))){
        $errors[] = "Please enter an email address.";
    } else{
        $email = trim($_POST["email"]);
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
            $errors[] = "Please enter a valid email address.";
        } else{
            // Check if email already exists
            $sql = "SELECT id FROM users WHERE guest_email = ?";
            if($stmt = mysqli_prepare($conn, $sql)){
                mysqli_stmt_bind_param($stmt, "s", $param_email);
                $param_email = $email;
                if(mysqli_stmt_execute($stmt)){
                    mysqli_stmt_store_result($stmt);
                    if(mysqli_stmt_num_rows($stmt) == 1){
                        $errors[] = "This email is already registered.";
                    }
                } else{
                    $errors[] = "Oops! Something went wrong. Please try again later.";
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
    
    // Validate phone
    if(empty(trim($_POST["phone"]))){
        $errors[] = "Please enter your phone number.";
    } else{
        $phone = trim($_POST["phone"]);
    }
    
    // Validate password
    if(empty(trim($_POST["password"]))){
        $errors[] = "Please enter a password.";
    } elseif(strlen(trim($_POST["password"])) < 6){
        $errors[] = "Password must have at least 6 characters.";
    } else{
        $password = trim($_POST["password"]);
    }
    
    // Validate confirm password
    if(empty(trim($_POST["confirm_password"]))){
        $errors[] = "Please confirm password.";
    } else{
        $confirm_password = trim($_POST["confirm_password"]);
        if(empty($errors) && ($password != $confirm_password)){
            $errors[] = "Password did not match.";
        }
    }
    
    // Check input errors before inserting in database
    if(empty($errors)){
        
        // Prepare an insert statement
        $sql = "INSERT INTO users (guest_name, guest_email, guest_phone, password_hash) VALUES (?, ?, ?, ?)";
         
        if($stmt = mysqli_prepare($conn, $sql)){
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "ssss", $param_name, $param_email, $param_phone, $param_password);
            
            // Set parameters
            $param_name = $name;
            $param_email = $email;
            $param_phone = $phone;
            $param_password = password_hash($password, PASSWORD_DEFAULT); // Creates a password hash
            
            // Attempt to execute the prepared statement
            if(mysqli_stmt_execute($stmt)){
                // Redirect to login page
                $_SESSION['success'] = "Registration successful. Please login.";
                header("location: login.php");
                exit();
            } else{
                $errors[] = "Something went wrong. Please try again later.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
    }
    
    // Close connection
    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Registration - Unicorn Hotel Damaturu</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .register-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }
        
        .register-box {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px;
        }
        
        .register-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .register-logo .logo-icon {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .register-logo h1 {
            color: var(--dark);
            font-size: 1.5rem;
            margin-bottom: 5px;
        }
        
        .register-logo p {
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        .form-control {
            margin-bottom: 20px;
        }
        
        .form-control label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .form-control input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--gray-light);
            border-radius: 8px;
            font-size: 1rem;
            transition: border 0.3s, box-shadow 0.3s;
        }
        
        .form-control input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .btn-register {
            width: 100%;
            padding: 12px;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-register:hover {
            background-color: var(--primary-dark);
        }
        
        .alert-error {
            background-color: rgba(239, 68, 68, 0.1);
            border-left: 4px solid var(--error);
            color: var(--error);
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-error p {
            margin: 5px 0;
        }
        
        .register-footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--gray-light);
        }
        
        .register-footer a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .register-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-box">
            <div class="register-logo">
                <div class="logo-icon">
                    <i class="fas fa-crown"></i>
                </div>
                <h1>Unicorn Hotel Damaturu</h1>
                <p>Create Your Account</p>
            </div>
            
            <?php 
            if(!empty($errors)){
                echo '<div class="alert-error">';
                foreach($errors as $error){
                    echo '<p>' . $error . '</p>';
                }
                echo '</div>';
            }        
            ?>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-control">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" value="<?php echo $name; ?>" required>
                </div>
                
                <div class="form-control">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="<?php echo $email; ?>" required>
                </div>
                
                <div class="form-control">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" value="<?php echo $phone; ?>" required>
                </div>
                
                <div class="form-control">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-control">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <button type="submit" class="btn-register">Create Account</button>
            </form>
            
            <div class="register-footer">
                <p>Already have an account? <a href="login.php">Login here</a></p>
                <p><a href="../index.php">← Back to Website</a></p>
            </div>
        </div>
    </div>
</body>
</html>