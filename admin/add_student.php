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
                
                // Get the newly inserted student's database ID
                $new_student_db_id = $pdo->lastInsertId();
                
                $success_message = "Student '$name' has been successfully registered with ID: $student_id";
                $show_face_registration = true;
                $registered_student_id = $student_id;
                $registered_student_name = $name;
                $registered_student_db_id = $new_student_db_id;
                
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
    <style>
        .face-registration-section {
            background: #e3f2fd;
            border-radius: 15px;
            padding: 25px;
            margin-top: 20px;
            border-left: 4px solid #2196f3;
        }
        
        .camera-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
            border: 1px solid #e9ecef;
            text-align: center;
        }
        
        .webcam-container {
            position: relative;
            display: inline-block;
            border-radius: 15px;
            overflow: hidden;
            margin: 20px 0;
            background: #000;
        }
        
        .webcam {
            width: 100%;
            max-width: 500px;
            height: auto;
            border-radius: 15px;
        }
        
        .face-guide {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            pointer-events: none;
        }
        
        .face-oval {
            width: 200px;
            height: 250px;
            border: 3px solid rgba(255,255,255,0.8);
            border-radius: 50%;
            box-shadow: 0 0 20px rgba(0,0,0,0.5);
            animation: pulse-border 2s infinite;
        }
        
        @keyframes pulse-border {
            0% { border-color: rgba(255,255,255,0.8); }
            50% { border-color: rgba(102,126,234,0.8); }
            100% { border-color: rgba(255,255,255,0.8); }
        }
        
        .photos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .photo-item {
            position: relative;
            border-radius: 10px;
            overflow: hidden;
            aspect-ratio: 1;
            border: 2px solid #e9ecef;
        }
        
        .photo-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.3s;
            margin: 5px;
        }
        
        .btn.success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        
        .btn.secondary {
            background: #6c757d;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
    </style>
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
                            
                            <?php if (isset($show_face_registration) && $show_face_registration): ?>
                                <div class="face-registration-section">
                                    <h3><i class="fas fa-camera"></i> Face Registration for <?php echo htmlspecialchars($registered_student_name); ?></h3>
                                    <p><strong>Student ID:</strong> <?php echo htmlspecialchars($registered_student_id); ?></p>
                                    <p>Complete the setup by registering the student's face for attendance recognition.</p>
                                    
                                    <!-- Face Registration Interface -->
                                    <div class="method-selection" style="text-align: center; padding: 20px; background: white; border-radius: 10px; margin: 15px 0;">
                                        <h4><i class="fas fa-camera"></i> Take 5 Photos with Camera</h4>
                                        <p>Use camera to capture 5 different angles for accurate recognition</p>
                                        <button type="button" onclick="startFaceRegistration()" class="btn" id="startFaceRegBtn">
                                            <i class="fas fa-video"></i> Start Face Registration
                                        </button>
                                    </div>

                                    <!-- Camera Section -->
                                    <div id="cameraSection" class="camera-section" style="display: none;">
                                        <div class="camera-header">
                                            <h4>Photo <span id="photoCount">1</span> of 5</h4>
                                            <p>Position face in the oval and click capture</p>
                                        </div>
                                        
                                        <div class="webcam-container">
                                            <video id="webcam" class="webcam" autoplay playsinline></video>
                                            <div class="face-guide">
                                                <div class="face-oval"></div>
                                            </div>
                                        </div>

                                        <div class="camera-controls">
                                            <button type="button" onclick="capturePhoto()" class="btn success" id="captureBtn">
                                                <i class="fas fa-camera"></i> Capture Photo
                                            </button>
                                            <button type="button" onclick="stopCamera()" class="btn secondary">
                                                <i class="fas fa-times"></i> Stop Camera
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Captured Photos Preview -->
                                    <div id="capturedPhotos" class="captured-photos" style="display: none;">
                                        <h4><i class="fas fa-images"></i> Captured Photos</h4>
                                        <div class="photos-grid" id="photosGrid"></div>
                                        <div class="photos-actions" style="text-align: center;">
                                            <button type="button" onclick="retakeAllPhotos()" class="btn secondary">
                                                <i class="fas fa-redo"></i> Retake All Photos
                                            </button>
                                            <button type="button" onclick="registerFace()" id="registerBtn" class="btn success" disabled>
                                                <i class="fas fa-user-check"></i> Register Face
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <script>
                                let stream = null;
                                let capturedPhotos = [];
                                let currentPhotoIndex = 0;
                                const maxPhotos = 5;
                                const selectedStudentId = '<?php echo $registered_student_id; ?>';
                                const selectedStudentName = '<?php echo htmlspecialchars($registered_student_name); ?>';
                                const selectedStudentDbId = <?php echo $registered_student_db_id; ?>;

                                async function startFaceRegistration() {
                                    try {
                                        stream = await navigator.mediaDevices.getUserMedia({ 
                                            video: { 
                                                width: 640, 
                                                height: 480, 
                                                facingMode: 'user' 
                                            } 
                                        });
                                        
                                        document.getElementById('startFaceRegBtn').style.display = 'none';
                                        document.getElementById('cameraSection').style.display = 'block';
                                        document.getElementById('webcam').srcObject = stream;
                                        
                                        updatePhotoCounter();
                                    } catch (error) {
                                        alert('Failed to access camera: ' + error.message);
                                    }
                                }

                                function stopCamera() {
                                    if (stream) {
                                        stream.getTracks().forEach(track => track.stop());
                                        stream = null;
                                    }
                                    
                                    document.getElementById('cameraSection').style.display = 'none';
                                    document.getElementById('startFaceRegBtn').style.display = 'inline-block';
                                    
                                    if (capturedPhotos.length > 0) {
                                        showCapturedPhotos();
                                    }
                                }

                                function capturePhoto() {
                                    const video = document.getElementById('webcam');
                                    const canvas = document.createElement('canvas');
                                    canvas.width = video.videoWidth;
                                    canvas.height = video.videoHeight;
                                    
                                    const ctx = canvas.getContext('2d');
                                    ctx.drawImage(video, 0, 0);
                                    
                                    const imageData = canvas.toDataURL('image/jpeg', 0.8);
                                    capturedPhotos.push(imageData);
                                    currentPhotoIndex++;
                                    
                                    updatePhotoCounter();
                                    
                                    if (capturedPhotos.length >= maxPhotos) {
                                        stopCamera();
                                        showCapturedPhotos();
                                    }
                                }

                                function updatePhotoCounter() {
                                    document.getElementById('photoCount').textContent = capturedPhotos.length + 1;
                                    
                                    if (capturedPhotos.length >= maxPhotos) {
                                        document.getElementById('captureBtn').disabled = true;
                                        document.getElementById('captureBtn').innerHTML = '<i class="fas fa-check"></i> Complete';
                                    }
                                }

                                function showCapturedPhotos() {
                                    document.getElementById('capturedPhotos').style.display = 'block';
                                    const photosGrid = document.getElementById('photosGrid');
                                    photosGrid.innerHTML = '';
                                    
                                    capturedPhotos.forEach((photo, index) => {
                                        const photoDiv = document.createElement('div');
                                        photoDiv.className = 'photo-item';
                                        photoDiv.innerHTML = `
                                            <img src="${photo}" alt="Photo ${index + 1}">
                                            <div class="photo-number" style="position: absolute; bottom: 5px; right: 5px; background: rgba(0,0,0,0.7); color: white; padding: 2px 6px; border-radius: 3px; font-size: 12px;">${index + 1}</div>
                                        `;
                                        photosGrid.appendChild(photoDiv);
                                    });
                                    
                                    const registerBtn = document.getElementById('registerBtn');
                                    if (capturedPhotos.length === maxPhotos) {
                                        registerBtn.disabled = false;
                                    }
                                }

                                function retakeAllPhotos() {
                                    capturedPhotos = [];
                                    currentPhotoIndex = 0;
                                    document.getElementById('capturedPhotos').style.display = 'none';
                                    document.getElementById('startFaceRegBtn').style.display = 'inline-block';
                                    
                                    const registerBtn = document.getElementById('registerBtn');
                                    registerBtn.disabled = true;
                                    
                                    const captureBtn = document.getElementById('captureBtn');
                                    captureBtn.disabled = false;
                                    captureBtn.innerHTML = '<i class="fas fa-camera"></i> Capture Photo';
                                }

                                async function registerFace() {
                                    if (capturedPhotos.length !== maxPhotos) {
                                        alert('Please capture exactly 5 photos before registering.');
                                        return;
                                    }
                                    
                                    const registerBtn = document.getElementById('registerBtn');
                                    registerBtn.disabled = true;
                                    registerBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registering...';
                                    
                                    try {
                                        const formData = new FormData();
                                        formData.append('student_id', selectedStudentId);
                                        formData.append('name', selectedStudentName);
                                        
                                        const photoFieldNames = ['photo_front', 'photo_left', 'photo_right', 'photo_up', 'photo_slight_left'];
                                        for (let i = 0; i < capturedPhotos.length; i++) {
                                            const blob = dataURLtoBlob(capturedPhotos[i]);
                                            formData.append(photoFieldNames[i], blob, `${photoFieldNames[i]}.jpg`);
                                        }
                                        
                                        const response = await fetch('http://localhost:8000/api/register-multi-angle/', {
                                            method: 'POST',
                                            body: formData
                                        });
                                        
                                        let result;
                                        try {
                                            result = await response.json();
                                        } catch (jsonError) {
                                            result = { error: 'Invalid response format from API' };
                                        }
                                        
                                        if (response.ok) {
                                            try {
                                                const dbResponse = await fetch('update_student_face_status.php', {
                                                    method: 'POST',
                                                    headers: {
                                                        'Content-Type': 'application/json',
                                                    },
                                                    body: JSON.stringify({
                                                        student_id: selectedStudentId,
                                                        status: 'registered'
                                                    })
                                                });
                                                
                                                if (dbResponse.ok) {
                                                    alert('Face registration successful for ' + selectedStudentName + '!');
                                                    // Hide the face registration section and show success
                                                    document.querySelector('.face-registration-section').innerHTML = '<div style="text-align: center; padding: 20px; background: #d4edda; border-radius: 10px; color: #155724;"><i class="fas fa-check-circle" style="font-size: 48px; margin-bottom: 10px;"></i><h3>Face Registration Complete!</h3><p>Student can now use face recognition for attendance.</p></div>';
                                                } else {
                                                    alert('Face registered with API but database update failed. Please contact admin.');
                                                }
                                            } catch (dbError) {
                                                alert('Face registered with API but database update failed: ' + dbError.message);
                                            }
                                        } else {
                                            const errorMessage = result.error || result.detail || result.message || `HTTP ${response.status}`;
                                            alert('Face registration failed: ' + errorMessage);
                                        }
                                    } catch (error) {
                                        alert('Network error: ' + error.message);
                                    } finally {
                                        registerBtn.disabled = false;
                                        registerBtn.innerHTML = '<i class="fas fa-user-check"></i> Register Face';
                                    }
                                }

                                function dataURLtoBlob(dataURL) {
                                    const arr = dataURL.split(',');
                                    const mime = arr[0].match(/:(.*?);/)[1];
                                    const bstr = atob(arr[1]);
                                    let n = bstr.length;
                                    const u8arr = new Uint8Array(n);
                                    while (n--) {
                                        u8arr[n] = bstr.charCodeAt(n);
                                    }
                                    return new Blob([u8arr], { type: mime });
                                }

                                window.addEventListener('beforeunload', function() {
                                    if (stream) {
                                        stream.getTracks().forEach(track => track.stop());
                                    }
                                });
                                </script>
                            <?php endif; ?>
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
