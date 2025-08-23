<?php
session_start();
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit();
}

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $student_id = trim($_POST['student_id']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $contact = trim($_POST['contact']);
    
    // Basic validation
    if (empty($name) || empty($email) || empty($student_id) || empty($password) || empty($confirm_password) || empty($contact)) {
        $error_message = 'All fields are required.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password must be at least 6 characters long.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        try {
            // Check if student ID or email already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE student_id = ? OR email = ?");
            $stmt->execute([$student_id, $email]);
            $exists = $stmt->fetchColumn();
            
            if ($exists > 0) {
                $error_message = 'Student ID or email already exists.';
            } else {
                // Hash password and insert student
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO students (student_id, name, email, password, contact) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$student_id, $name, $email, $hashed_password, $contact]);
                
                $success_message = "Student '$name' has been successfully registered with ID: $student_id";
                
                // Clear form fields
                $name = $email = $student_id = $password = $confirm_password = $contact = '';
            }
        } catch (PDOException $e) {
            $error_message = 'Database error: Unable to register student. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Student - Full Attend</title>
    <link rel="stylesheet" href="admin_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="admin-main">
            <header class="dashboard-header">
                <div class="header-left">
                    <h1>Student Registration</h1>
                    <p>Add new students to the system</p>
                </div>
                <div class="header-right">
                    <div class="date-info">
                        <i class="fas fa-calendar"></i>
                        <span><?php echo date('l, F j, Y'); ?></span>
                    </div>
                </div>
            </header>
            
            <!-- Registration Form Section -->
            <div class="register-container">
                <div class="register-right">
                    <div class="form-tabs">
                        <button class="tab-btn active">REGISTER</button>
                    </div>
                    
                    <div class="register-form">
                        <h2>REGISTER NEW STUDENT</h2>
                        
                        <?php if (!empty($success_message)): ?>
                            <div class="success-message">
                                <i class="fas fa-check-circle"></i>
                                <?php echo htmlspecialchars($success_message); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($error_message)): ?>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <?php echo htmlspecialchars($error_message); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="name">NAME</label>
                                <div class="input-container">
                                    <input type="text" id="name" name="name" placeholder="Full Name" 
                                           value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">STUDENT E-MAIL</label>
                                <div class="input-container">
                                    <input type="email" id="email" name="email" placeholder="student@example.com" 
                                           value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="student_id">STUDENT ID</label>
                                <div class="input-container">
                                    <input type="text" id="student_id" name="student_id" placeholder="CIHE240XXX" 
                                           value="<?php echo isset($student_id) ? htmlspecialchars($student_id) : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="password">PASSWORD</label>
                                <div class="input-container">
                                    <input type="password" id="password" name="password" placeholder="Create Password" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">CONFIRM PASSWORD</label>
                                <div class="input-container">
                                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="contact">CONTACT NO.</label>
                                <div class="input-container">
                                    <input type="tel" id="contact" name="contact" placeholder="Phone Number" 
                                           value="<?php echo isset($contact) ? htmlspecialchars($contact) : ''; ?>" required>
                                </div>
                            </div>
                            
                            <button type="submit" class="create-account-btn">CREATE ACCOUNT</button>
                        </form>
                        
                        <div class="form-footer">
                            <p>Need to view existing students? <a href="student_directory.php">Student Directory</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
