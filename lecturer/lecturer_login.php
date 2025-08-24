<?php
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['lecturer_logged_in']) && $_SESSION['lecturer_logged_in'] === true) {
    header('Location: lecturer_dashboard.php');
    exit();
}

require_once '../config/database.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error_message = 'Please enter both email and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM lecturers WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $lecturer = $stmt->fetch();
            
            if ($lecturer && password_verify($password, $lecturer['password'])) {
                // Set session variables
                $_SESSION['lecturer_logged_in'] = true;
                $_SESSION['lecturer_id'] = $lecturer['id'];
                $_SESSION['lecturer_name'] = $lecturer['name'];
                $_SESSION['lecturer_email'] = $lecturer['email'];
                
                header('Location: lecturer_dashboard.php');
                exit();
            } else {
                $error_message = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            $error_message = 'Database error. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Login - FullAttend</title>
    <link rel="stylesheet" href="lecturer_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="lecturer-portal">
    <div class="container">
        <a class="back-button" href="../index.php">&larr; Back to Main Login</a>
        
        <div class="login-box">
            <div class="login-logos">
                <img src="../images/cihe_logo.png" alt="CIHE Logo">
                <img src="../images/fullattend_logo.png" alt="FullAttend Logo">
            </div>
            
            <h2>Lecturer Login</h2>
            
            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm">
                <div class="input-group">
                    <input 
                        type="email" 
                        name="email"
                        placeholder="Email Address" 
                        value="<?php echo htmlspecialchars($email ?? ''); ?>"
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
                <a href="#" onclick="alert('Please contact admin for password reset')">Forgotten Password?</a>
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
                    background: rgba(102, 126, 234, 0.3);
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

        // Add CSS animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes float {
                0% { 
                    transform: translateY(0px) rotate(0deg); 
                    opacity: 0.3; 
                }
                33% { 
                    transform: translateY(-30px) rotate(120deg); 
                    opacity: 0.6; 
                }
                66% { 
                    transform: translateY(-60px) rotate(240deg); 
                    opacity: 0.3; 
                }
                100% { 
                    transform: translateY(0px) rotate(360deg); 
                    opacity: 0.3; 
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
