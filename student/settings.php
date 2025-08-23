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

      <!-- Profile Section -->
      <section class="dashboard-card">
        <div class="card-header">
          <h2><i class="fas fa-user"></i> Profile Information</h2>
        </div>
        <form class="settings-form" id="profileForm" action="#">
          <div class="avatar-section">
            <div class="avatar-container">
              <div id="avatar">JD</div>
            </div>
            <div class="avatar-info">
              <h3>Profile Photo</h3>
              <p>Upload a new profile picture or keep the current one.</p>
              <input id="avatarInput" type="file" accept="image/*" style="margin-top: 12px;">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="name">Full Name</label>
              <input id="name" type="text" placeholder="Jane Doe" required>
            </div>
            <div class="form-group">
              <label for="email">Email</label>
              <input id="email" type="email" placeholder="jane.doe@student.cihe.edu.au" required>
            </div>
          </div>

          <div class="form-group">
            <label for="phone">Phone</label>
            <input id="phone" type="tel" placeholder="+61 4xx xxx xxx">
          </div>

          <button type="submit"><i class="fas fa-save"></i> Save Profile</button>
        </form>
      </section>

    </main>
  </div>

  <!-- Enhanced JavaScript for better UX -->
  <script>
    const avatarBox = document.getElementById('avatar');
    const fileIn    = document.getElementById('avatarInput');
    const nameIn    = document.getElementById('name');
    const newPwdIn  = document.getElementById('new');

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
      let strength = 0;
      
      if (password.length >= 8) strength++;
      if (/[a-z]/.test(password)) strength++;
      if (/[A-Z]/.test(password)) strength++;
      if (/[0-9]/.test(password)) strength++;
      if (/[^A-Za-z0-9]/.test(password)) strength++;

      strengthIndicator.className = 'password-strength';
      if (strength <= 2) {
        strengthIndicator.classList.add('weak');
      } else if (strength <= 3) {
        strengthIndicator.classList.add('medium');
      } else {
        strengthIndicator.classList.add('strong');
      }
    }

    newPwdIn.addEventListener('input', e => {
      checkPasswordStrength(e.target.value);
    });

    document.getElementById('pwdForm').addEventListener('submit', e=>{
      const n = document.getElementById('new').value;
      const c = document.getElementById('conf').value;
      if(n !== c){ 
        e.preventDefault(); 
        alert('New passwords do not match.'); 
        return;
      }
      if(n.length < 8) {
        e.preventDefault();
        alert('Password must be at least 8 characters long.');
        return;
      }
    });

    // Theme preference with visual feedback
    document.getElementById('theme').addEventListener('change', e=>{
      document.documentElement.dataset.theme = e.target.value;
      
      // Show success message with modern styling
      const message = document.createElement('div');
      message.style.cssText = `
        position: fixed; top: 20px; right: 20px; 
        background: linear-gradient(135deg, #10b981, #059669);
        color: white; padding: 12px 20px; border-radius: 12px;
        box-shadow: 0 8px 32px rgba(16, 185, 129, 0.3);
        z-index: 10000; animation: slideIn 0.3s ease;
      `;
      message.innerHTML = '<i class="fas fa-check"></i> Theme preference saved!';
      document.body.appendChild(message);
      
      setTimeout(() => {
        message.style.animation = 'slideOut 0.3s ease forwards';
        setTimeout(() => message.remove(), 300);
      }, 2000);
    });

    // Add slide animations
    const style = document.createElement('style');
    style.textContent = `
      @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
      }
      @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
      }
    `;
    document.head.appendChild(style);

    // Initialize
    setInitials();
  </script>
</body>
</html>
