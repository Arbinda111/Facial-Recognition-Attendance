<?php
session_start();
require_once '../config/database.php';

// Check if student is logged in
if (!isset($_SESSION['student_logged_in']) || $_SESSION['student_logged_in'] !== true) {
    header('Location: student_login.php');
    exit();
}

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];
$student_email = $_SESSION['student_email'];
$student_contact = $_SESSION['student_contact'] ?? '';

// Get the student's complete information from database
$stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
$stmt->execute([$student_id]);
$student_data = $stmt->fetch();

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $contact = trim($_POST['contact']);
        
        // Validate inputs
        if (empty($name) || empty($email)) {
            $message = 'Name and email are required fields.';
            $message_type = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $message_type = 'error';
        } else {
            // Update student information
            $stmt = $pdo->prepare("UPDATE students SET name = ?, email = ?, contact = ? WHERE student_id = ?");
            if ($stmt->execute([$name, $email, $contact, $student_id])) {
                // Update session variables
                $_SESSION['student_name'] = $name;
                $_SESSION['student_email'] = $email;
                $_SESSION['student_contact'] = $contact;
                
                // Refresh student data
                $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
                $stmt->execute([$student_id]);
                $student_data = $stmt->fetch();
                
                $message = 'Profile updated successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error updating profile. Please try again.';
                $message_type = 'error';
            }
        }
    }
    
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate current password
        if (password_verify($current_password, $student_data['password'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 8) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE students SET password = ? WHERE student_id = ?");
                    if ($stmt->execute([$hashed_password, $student_id])) {
                        $message = 'Password changed successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Error changing password. Please try again.';
                        $message_type = 'error';
                    }
                } else {
                    $message = 'New password must be at least 8 characters long.';
                    $message_type = 'error';
                }
            } else {
                $message = 'New passwords do not match.';
                $message_type = 'error';
            }
        } else {
            $message = 'Current password is incorrect.';
            $message_type = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Student Settings - FullAttend</title>
  <link rel="stylesheet" href="student_styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="student-portal">
  <div class="dashboard">
    <!-- Enhanced Sidebar -->
    <aside class="sidebar">
      <div class="sidebar-header">
        <div class="student-avatar">
          <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($student_name); ?>&background=667eea&color=fff&size=60" alt="Student Avatar">
        </div>
        <div class="student-info">
          <h3><?php echo htmlspecialchars($student_name); ?></h3>
          <p class="student-id">ID: <?php echo htmlspecialchars($_SESSION['student_id']); ?></p>
          <span class="status-badge online">Online</span>
        </div>
      </div>
      
      <nav class="sidebar-nav">
        <a href="student_dashboard.php" class="nav-item">
          <i class="fas fa-chart-pie"></i>
          <span>Dashboard</span>
        </a>
        <a href="my_attendance.php" class="nav-item">
          <i class="fas fa-calendar-check"></i>
          <span>My Attendance</span>
        </a>
        <a href="student_timetable.php" class="nav-item">
          <i class="fas fa-calendar-alt"></i>
          <span>Timetable</span>
        </a>
        <a href="settings.php" class="nav-item active">
          <i class="fas fa-cog"></i>
          <span>Settings</span>
        </a>
        <div class="nav-divider"></div>
        <a href="student_logout.php" class="nav-item logout">
          <i class="fas fa-sign-out-alt"></i>
          <span>Logout</span>
        </a>
      </nav>
      
      <div class="sidebar-footer">
        <div class="logos">
          <img src="../images/cihe_logo.png" alt="CIHE Logo" class="logo">
          <img src="../images/fullattend_logo.png" alt="FullAttend Logo" class="logo">
        </div>
      </div>
    </aside>

    <!-- Main Dashboard Content -->
    <main class="dashboard-content">
      <!-- Header Section -->
      <header class="dashboard-header">
        <div class="header-content">
          <div class="welcome-section">
            <h1>Settings</h1>
            <p class="subtitle">Update your profile, password, and preferences</p>
          </div>
          <div class="header-actions">
            <div class="date-info">
              <i class="fas fa-calendar"></i>
              <span><?php echo date('l, F j, Y'); ?></span>
            </div>
          </div>
        </div>
      </header>

      <!-- Message Display -->
      <?php if (!empty($message)): ?>
      <div class="message <?php echo $message_type; ?>">
        <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
        <?php echo htmlspecialchars($message); ?>
      </div>
      <?php endif; ?>

      <!-- Profile Section -->
      <section class="dashboard-card">
        <div class="card-header">
          <h2><i class="fas fa-user"></i> Profile Information</h2>
        </div>
        <form class="settings-form" id="profileForm" method="POST">
          <div class="form-row">
            <div class="form-group">
              <label for="name">Full Name</label>
              <input id="name" name="name" type="text" value="<?php echo htmlspecialchars($student_data['name']); ?>" required>
            </div>
            <div class="form-group">
              <label for="email">Email</label>
              <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($student_data['email']); ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label for="contact">Contact Number</label>
            <input id="contact" name="contact" type="tel" value="<?php echo htmlspecialchars($student_data['contact'] ?? ''); ?>" placeholder="+61 4xx xxx xxx">
          </div>

          <button type="submit" name="update_profile"><i class="fas fa-save"></i> Save Profile</button>
        </form>
      </section>

      <!-- Password Change Section -->
      <section class="dashboard-card">
        <div class="card-header">
          <h2><i class="fas fa-lock"></i> Change Password</h2>
        </div>
        <form class="settings-form" id="passwordForm" method="POST">
          <div class="form-group">
            <label for="current_password">Current Password</label>
            <input id="current_password" name="current_password" type="password" required>
          </div>
          
          <div class="form-row">
            <div class="form-group">
              <label for="new_password">New Password</label>
              <input id="new_password" name="new_password" type="password" required>
              <div id="passwordStrength" class="password-strength"></div>
            </div>
            <div class="form-group">
              <label for="confirm_password">Confirm New Password</label>
              <input id="confirm_password" name="confirm_password" type="password" required>
            </div>
          </div>

          <button type="submit" name="change_password"><i class="fas fa-key"></i> Change Password</button>
        </form>
      </section>

    </main>
  </div>

  <!-- Enhanced JavaScript for better UX -->
  <script>
    const avatarBox = document.getElementById('avatar');
    const fileIn    = document.getElementById('avatarInput');
    const nameIn    = document.getElementById('name');
    const newPwdIn  = document.getElementById('new_password');

    function setInitials(){
      const n = (nameIn.value||'').trim().split(/\s+/);
      const init = (n[0]?.[0]||'') + (n[1]?.[0]||'');
      if (!fileIn.files.length){
        avatarBox.style.backgroundImage = '';
        avatarBox.textContent = (init || 'ST').toUpperCase();
      }
    }
    nameIn.addEventListener('input', setInitials);

    fileIn.addEventListener('change', e=>{
      const f = e.target.files[0]; if(!f) return;
      const rd = new FileReader();
      rd.onload = ev => { 
        avatarBox.textContent=''; 
        avatarBox.style.backgroundImage = `url('${ev.target.result}')`; 
      };
      rd.readAsDataURL(f);
    });

    // Password strength indicator
    function checkPasswordStrength(password) {
      const strengthIndicator = document.getElementById('passwordStrength');
      if (!strengthIndicator) return;
      
      let strength = 0;
      let strengthText = '';
      
      if (password.length >= 8) strength++;
      if (/[a-z]/.test(password)) strength++;
      if (/[A-Z]/.test(password)) strength++;
      if (/[0-9]/.test(password)) strength++;
      if (/[^A-Za-z0-9]/.test(password)) strength++;

      strengthIndicator.className = 'password-strength';
      if (strength <= 2) {
        strengthIndicator.classList.add('weak');
        strengthText = 'Weak';
      } else if (strength <= 3) {
        strengthIndicator.classList.add('medium');
        strengthText = 'Medium';
      } else {
        strengthIndicator.classList.add('strong');
        strengthText = 'Strong';
      }
      
      strengthIndicator.textContent = password.length > 0 ? `Password strength: ${strengthText}` : '';
    }

    if (newPwdIn) {
      newPwdIn.addEventListener('input', e => {
        checkPasswordStrength(e.target.value);
      });
    }

    // Password form validation
    document.getElementById('passwordForm').addEventListener('submit', e=>{
      const current = document.getElementById('current_password').value;
      const newPwd = document.getElementById('new_password').value;
      const confirm = document.getElementById('confirm_password').value;
      
      if (!current) {
        e.preventDefault();
        alert('Please enter your current password.');
        return;
      }
      
      if (newPwd !== confirm){ 
        e.preventDefault(); 
        alert('New passwords do not match.'); 
        return;
      }
      
      if (newPwd.length < 8) {
        e.preventDefault();
        alert('Password must be at least 8 characters long.');
        return;
      }
    });

    // Auto-hide messages after 5 seconds
    const messageEl = document.querySelector('.message');
    if (messageEl) {
      setTimeout(() => {
        messageEl.style.animation = 'slideOut 0.3s ease forwards';
        setTimeout(() => messageEl.remove(), 300);
      }, 5000);
    }

    // Add slide animations and message styles
    const style = document.createElement('style');
    style.textContent = `
      .message {
        margin: 20px 0;
        padding: 15px 20px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 500;
        animation: slideIn 0.3s ease;
      }
      
      .message.success {
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
        border-left: 4px solid #047857;
      }
      
      .message.error {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
        border-left: 4px solid #b91c1c;
      }
      
      .password-strength {
        margin-top: 5px;
        font-size: 12px;
        font-weight: 500;
      }
      
      .password-strength.weak {
        color: #ef4444;
      }
      
      .password-strength.medium {
        color: #f59e0b;
      }
      
      .password-strength.strong {
        color: #10b981;
      }
      
      @keyframes slideIn {
        from { transform: translateY(-20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
      }
      
      @keyframes slideOut {
        from { transform: translateY(0); opacity: 1; }
        to { transform: translateY(-20px); opacity: 0; }
      }
    `;
    document.head.appendChild(style);

    // Initialize avatar with current user's initials
    setInitials();
  </script>
</body>
</html>
