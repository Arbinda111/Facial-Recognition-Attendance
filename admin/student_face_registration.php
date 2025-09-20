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

// Get student list for selection
$stmt = $pdo->prepare("SELECT student_id, name FROM students WHERE status = 'active' ORDER BY name ASC");
$stmt->execute();
$students = $stmt->fetchAll();

$selected_student = null;
if (isset($_GET['student_id'])) {
    $student_id = $_GET['student_id'];
    $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $selected_student = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Face Registration - Admin Panel</title>
    <link rel="stylesheet" href="admin_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .face-registration {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .registration-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .student-selection {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .method-selection {
            text-align: center;
            padding: 30px;
            border: 2px dashed #ddd;
            border-radius: 15px;
            margin: 20px 0;
            background: #f8f9fa;
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
        
        .upload-btn, .capture-btn, .cancel-btn {
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
        
        .capture-btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        
        .cancel-btn {
            background: #6c757d;
        }
        
        .upload-btn:hover, .capture-btn:hover, .cancel-btn:hover {
            transform: translateY(-2px);
        }
        
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            margin-bottom: 15px;
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
                    <h1>Face Registration</h1>
                    <p>Register student faces for attendance tracking</p>
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?php echo $success_message; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="header-right">
                    <div class="date-info">
                        <i class="fas fa-calendar"></i>
                        <span><?php echo date('l, F j, Y'); ?></span>
                    </div>
                   
                </div>
            </header>

        <!-- Main Content -->
        <main class="main-content">
            <div class="face-registration">
                <!-- Student Selection -->
                <div class="registration-card">
                    <h3><i class="fas fa-user-check"></i> Select Student</h3>
                    <div class="student-selection">
                        <label for="studentSelect">Choose a student to register face:</label>
                        <select id="studentSelect" onchange="selectStudent()">
                            <option value="">-- Select Student --</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['student_id']; ?>" 
                                        <?php echo ($selected_student && $selected_student['student_id'] === $student['student_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($student['name'] . ' (' . $student['student_id'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <?php if ($selected_student): ?>
                    <div class="registration-card">
                        <h3><i class="fas fa-user"></i> Registering Face for: <?php echo htmlspecialchars($selected_student['name']); ?></h3>
                        <p><strong>Student ID:</strong> <?php echo htmlspecialchars($selected_student['student_id']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($selected_student['email']); ?></p>

                        <?php if (!empty($success_message)): ?>
                            <div class="alert success">
                                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($error_message)): ?>
                            <div class="alert error">
                                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                            </div>
                        <?php endif; ?>

                        <!-- Camera Method -->
                        <div class="method-selection">
                            <h4><i class="fas fa-camera"></i> Take 5 Photos with Camera</h4>
                            <p>Use camera to capture 5 different angles for accurate recognition</p>
                            <button type="button" onclick="startCamera()" class="upload-btn" id="startCameraBtn">
                                <i class="fas fa-video"></i> Start Camera
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
                                <button type="button" onclick="capturePhoto()" class="capture-btn" id="captureBtn">
                                    <i class="fas fa-camera"></i> Capture Photo
                                </button>
                                <button type="button" onclick="stopCamera()" class="cancel-btn">
                                    <i class="fas fa-times"></i> Stop Camera
                                </button>
                            </div>
                        </div>

                        <!-- Captured Photos Preview -->
                        <div id="capturedPhotos" class="captured-photos" style="display: none;">
                            <h4><i class="fas fa-images"></i> Captured Photos</h4>
                            <div class="photos-grid" id="photosGrid"></div>
                            <div class="photos-actions">
                                <button type="button" onclick="retakeAllPhotos()" class="cancel-btn">
                                    <i class="fas fa-redo"></i> Retake All Photos
                                </button>
                                <button type="button" onclick="registerFace()" id="registerBtn" class="upload-btn" disabled>
                                    <i class="fas fa-user-check"></i> Register Face
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        let stream = null;
        let capturedPhotos = [];
        let currentPhotoIndex = 0;
        const maxPhotos = 5;
        const selectedStudentId = '<?php echo $selected_student ? $selected_student['student_id'] : ''; ?>';
        const selectedStudentName = '<?php echo $selected_student ? $selected_student['name'] : ''; ?>';

        function selectStudent() {
            const studentId = document.getElementById('studentSelect').value;
            if (studentId) {
                window.location.href = 'student_face_registration.php?student_id=' + studentId;
            }
        }

        async function startCamera() {
            if (!selectedStudentId) {
                alert('Please select a student first');
                return;
            }

            try {
                stream = await navigator.mediaDevices.getUserMedia({ 
                    video: { 
                        width: 640, 
                        height: 480, 
                        facingMode: 'user' 
                    } 
                });
                
                document.getElementById('startCameraBtn').style.display = 'none';
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
            document.getElementById('startCameraBtn').style.display = 'inline-block';
            
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
                    <div class="photo-number">${index + 1}</div>
                `;
                photosGrid.appendChild(photoDiv);
            });
            
            // Update register button
            const registerBtn = document.getElementById('registerBtn');
            if (capturedPhotos.length === maxPhotos) {
                registerBtn.disabled = false;
            }
        }

        function retakeAllPhotos() {
            capturedPhotos = [];
            currentPhotoIndex = 0;
            document.getElementById('capturedPhotos').style.display = 'none';
            document.getElementById('startCameraBtn').style.display = 'inline-block';
            
            const registerBtn = document.getElementById('registerBtn');
            registerBtn.disabled = true;
            
            // Reset capture button
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
                // Prepare form data for API call
                const formData = new FormData();
                formData.append('student_id', selectedStudentId);
                formData.append('name', selectedStudentName);
                
                // Convert base64 images to blobs and add to form data with specific field names
                const photoFieldNames = ['photo_front', 'photo_left', 'photo_right', 'photo_up', 'photo_slight_left'];
                for (let i = 0; i < capturedPhotos.length; i++) {
                    const blob = dataURLtoBlob(capturedPhotos[i]);
                    formData.append(photoFieldNames[i], blob, `${photoFieldNames[i]}.jpg`);
                }
                
                // Call Python API
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
                
                // Check if the API call was successful based on HTTP status
                if (response.ok) {
                    // API call succeeded - now update database
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
                            alert('Face registration successful for ' + selectedStudentName + '!\n\nAPI Response: ' + (result.message || 'Registration completed successfully.'));
                            window.location.reload();
                        } else {
                            const dbResult = await dbResponse.text();
                            alert('Face registered successfully with API, but database update failed.\n\nAPI Success: ' + (result.message || 'Face data processed') + '\nDB Error: ' + dbResult + '\n\nPlease contact admin to update the database status.');
                        }
                    } catch (dbError) {
                        alert('Face registered successfully with API, but database update failed.\n\nAPI Success: ' + (result.message || 'Face data processed') + '\nDB Error: ' + dbError.message + '\n\nPlease contact admin.');
                    }
                } else {
                    // API call failed
                    const errorMessage = result.error || result.detail || result.message || `HTTP ${response.status}: ${response.statusText}`;
                    alert('Face registration failed.\n\nError: ' + errorMessage + '\n\nPlease try again or contact support.');
                    console.error('API Error:', result);
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

        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
            }
        });
    </script>
</body>
</html>
