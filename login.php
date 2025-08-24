<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Main Login</title>
  <link rel="stylesheet" href="../styles.css">
  <link rel="stylesheet" href="student.css">
  <style>
    .login-box { box-shadow: 0 2px 12px rgba(108,143,245,0.10); border-radius:18px; }
    .login-button { background: #6C8FF5; color: #fff; font-weight:700; border-radius:10px; transition: background 0.2s; }
    .login-button:hover { background: #597CE6; }
    .input-group input:focus { border-color: #6C8FF5; box-shadow: 0 0 0 2px #EEF3FF; }
  </style>
</head>
<body class="student-portal">
  <div class="container">
    <div class="login-box">
      <div class="login-logos">
        <img src="../images/cihe_logo.png" alt="CIHE Logo">
        <img src="../images/fullattend_logo.png" alt="FullAttend Logo">
      </div>
      <form action="student_dashboard.php">
        <div class="input-group">
          <input type="text" placeholder="Username">
        </div>
        <div class="input-group">
          <input type="password" placeholder="Password">
        </div>
        <button type="submit" class="login-button">LOG IN</button>
      </form>
    </div>
  </div>
</body>
</html>
