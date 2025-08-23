<?php
session_start();
require_once '../config/database.php';

// Check if student is already logged in
if (isset($_SESSION['student_logged_in']) && $_SESSION['student_logged_in'] === true) {
    header('Location: student_dashboard.php');
    exit();
}

$error_message = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = trim($_POST['student_id']);
    $password = trim($_POST['password']);
    
    try {
        // Database authentication
        $stmt = $pdo->prepare("SELECT id, student_id, name, email, password, status FROM students WHERE student_id = ? OR email = ?");
        $stmt->execute([$student_id, $student_id]);
        $student = $stmt->fetch();
        
        if ($student && $student['status'] === 'active' && password_verify($password, $student['password'])) {
            $_SESSION['student_logged_in'] = true;
            $_SESSION['student_db_id'] = $student['id'];
            $_SESSION['student_id'] = $student['student_id'];
            $_SESSION['student_name'] = $student['name'];
            $_SESSION['student_email'] = $student['email'];
            header('Location: student_dashboard.php');
            exit();
        } else {
            $error_message = 'Invalid student ID or password';
        }
    } catch (PDOException $e) {
        $error_message = 'Database error. Please try again.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Login - FullAttend</title>
  <link rel="stylesheet" href="student_styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="student-portal">
  <div class="container">
    <a class="back-button" href="../index.php">&larr; Back to Main Login</a>
    
    <div class="login-box">
      <div class="login-logos">
        <img src="../images/cihe_logo.png" alt="CIHE Logo">
        <img src="../images/fullattend_logo.png" alt="FullAttend Logo">
      </div>
      
      <h2>Student Login</h2>
      
      <?php if (!empty($error_message)): ?>
          <div class="error-message">
              <?php echo htmlspecialchars($error_message); ?>
          </div>
      <?php endif; ?>
      
      <form method="POST" action="" id="loginForm">
        <div class="input-group">
          <input 
            type="text" 
            name="student_id"
            placeholder="Student ID" 
            required
            autocomplete="username"
          >
        </div>
        
        <div class="input-group">
          <input 
            type="password" 
            name="password"
            placeholder="Password" 
            required
            autocomplete="current-password"
          >
        </div>
        
        <button type="submit" class="login-button">
          <i class="fas fa-sign-in-alt"></i>
          Log In
        </button>
      </form>
      
      <div class="forgot-password">
        <a href="forgot_password.php">Forgotten Password?</a>
      </div>
    </div>
  </div>

  <script>
    // Add loading state to login button
    document.getElementById('loginForm').addEventListener('submit', function(e) {
      const button = this.querySelector('.login-button');
      const originalContent = button.innerHTML;
      
      button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
      button.disabled = true;
    });
  </script>
</body>
</html>

    // Add floating animation to background elements
    document.addEventListener('DOMContentLoaded', function() {
      const container = document.querySelector('.container');
      
      // Create floating particles
      for (let i = 0; i < 5; i++) {
        const particle = document.createElement('div');
        particle.style.cssText = `
          position: absolute;
          width: ${Math.random() * 6 + 4}px;
          height: ${Math.random() * 6 + 4}px;
          background: rgba(108, 143, 245, 0.3);
          border-radius: 50%;
          animation: float ${Math.random() * 10 + 15}s ease-in-out infinite;
          animation-delay: ${Math.random() * 5}s;
          top: ${Math.random() * 100}%;
          left: ${Math.random() * 100}%;
          z-index: 1;
        `;
        container.appendChild(particle);
      }
    });

    // Add input focus animations
    document.querySelectorAll('.input-group input').forEach(input => {
      input.addEventListener('focus', function() {
        this.parentElement.style.transform = 'scale(1.02)';
      });
      
      input.addEventListener('blur', function() {
        this.parentElement.style.transform = 'scale(1)';
      });
    });
  </script>
</body>
</html>
