<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Full Attend - Login Interface</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-interface {
            background: rgba(255, 255, 255, 0.95);
            padding: 50px 40px;
            border-radius: 25px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }

        .header-text {
            color: #333;
            font-size: 18px;
            margin-bottom: 40px;
            font-weight: 300;
            letter-spacing: 1px;
        }

        .logos {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 30px;
            margin-bottom: 50px;
        }

        .logos img {
            width: 80px;
            height: 80px;
            object-fit: contain;
        }

        .fullattend-logo {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .login-buttons {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .login-btn {
            padding: 18px 30px;
            border: none;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            cursor: pointer;
            display: block;
            text-align: center;
        }

        .student-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .lecturer-btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .admin-btn {
            background: linear-gradient(135deg, #a8a8a8 0%, #8a8a8a 100%);
            color: white;
        }

        .login-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }

        .student-btn:hover {
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.4);
        }

        .lecturer-btn:hover {
            box-shadow: 0 15px 30px rgba(40, 167, 69, 0.4);
        }

        .admin-btn:hover {
            box-shadow: 0 15px 30px rgba(138, 138, 138, 0.4);
        }

        @media (max-width: 480px) {
            .login-interface {
                padding: 30px 25px;
                margin: 10px;
            }
            
            .logos {
                flex-direction: column;
                gap: 20px;
            }
            
            .logos img {
                width: 60px;
                height: 60px;
            }
        }
    </style>
</head>
<body>
    <div class="login-interface">
        <div class="header-text">login interface</div>
        
        <div class="logos">
            <img src="images/cihe_logo.png" alt="CIHE Logo">
            <div class="fullattend-logo">
                <img src="images/fullattend_logo.png" alt="FullAttend Logo">
            </div>
        </div>
        
        <div class="login-buttons">
            <a href="student/student_login.php" class="login-btn student-btn">Log in as Student</a>
            <a href="lecturer/lecturer_login.php" class="login-btn lecturer-btn">Login as Lecturer</a>
            <a href="admin/admin_login.php" class="login-btn admin-btn">Login as Admin</a>
        </div>
    </div>
</body>
</html>
