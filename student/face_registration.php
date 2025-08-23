<?php
session_start();
require_once '../config/database.php';

// Check if student is logged in
if (!isset($_SESSION['student_logged_in']) || $_SESSION['student_logged_in'] !== true) {
    header('Location: student_login.php');
    exit();
}

$success_message = '';
$error_message = '';
$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];

// Check if student has already registered face
$stmt = $pdo->prepare("SELECT face_encoding FROM students WHERE student_id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();
$isRegistered = !empty($student['face_encoding']);

// Handle face registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photos'])) {
    $photos = $_FILES['photos'];
    $uploaded_count = 0;
    
    // Create uploads directory if it doesn't exist
    $upload_dir = '../uploads/face_registration/' . $student_id . '/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Process uploaded photos
    $photo_paths = [];
    for ($i = 0; $i < count($photos['name']); $i++) {
        if ($photos['error'][$i] === UPLOAD_ERR_OK) {
            $filename = 'photo_' . ($i + 1) . '_' . time() . '.jpg';
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($photos['tmp_name'][$i], $filepath)) {
                $photo_paths[] = $filepath;
                $uploaded_count++;
            }
        }
    }
    
    if ($uploaded_count >= 5) {
        // Call Python API to register face
        $api_url = 'http://localhost:8000/api/register-face/';
        
        $postFields = [
            'student_id' => $student_id,
            'student_name' => $student_name
        ];
        
        // Add photos to the request
        $cFile = new CURLFile($photo_paths[0], 'image/jpeg', 'photo1.jpg');
        $postFields['photo1'] = $cFile;
        
        for ($i = 1; $i < count($photo_paths) && $i < 5; $i++) {
            $cFile = new CURLFile($photo_paths[$i], 'image/jpeg', 'photo' . ($i + 1) . '.jpg');
            $postFields['photo' . ($i + 1)] = $cFile;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $result = json_decode($response, true);
            if ($result && isset($result['success'])) {
                // Update database with face encoding status
                $stmt = $pdo->prepare("UPDATE students SET face_encoding = 'registered' WHERE student_id = ?");
                $stmt->execute([$student_id]);
                
                $success_message = 'Face registration successful! You can now use face attendance.';
                $isRegistered = true;
            } else {
                $error_message = 'Face registration failed: ' . ($result['error'] ?? 'Unknown error');
            }
        } else {
            $error_message = 'Failed to connect to face registration service. Please try again.';
        }
        
        // Clean up uploaded files
        foreach ($photo_paths as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    } else {
        $error_message = 'Please upload exactly 5 photos for face registration.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Face Registration - FullAttend</title>
    <link rel="stylesheet" href="student_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .face-registration {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .registration-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .status-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .photo-upload-area {
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            margin: 20px 0;
            transition: border-color 0.3s;
        }
        
        .photo-upload-area:hover {
            border-color: #667eea;
        }
        
        .photo-preview {
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
        }
        
        .photo-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .photo-item .remove-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(255,0,0,0.8);
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            cursor: pointer;
        }
        
        .upload-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.3s;
        }
        
        .upload-btn:hover {
            transform: translateY(-2px);
        }
        
        .upload-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .instructions {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .instructions h4 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .instructions ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .instructions li {
            margin: 5px 0;
            color: #666;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            border: 1px solid #c3e6cb;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            border: 1px solid #f5c6cb;
        }
        
        .registered-status {
            background: #d4edda;
            color: #155724;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid #c3e6cb;
        }
        
        .attendance-link {
            display: inline-block;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 25px;
            margin-top: 15px;
            font-weight: 600;
            transition: transform 0.3s;
        }
        
        .attendance-link:hover {
            transform: translateY(-2px);
            text-decoration: none;
            color: white;
        }
        
        /* Camera Interface Styles */
        .method-selection {
            text-align: center;
            padding: 30px;
            border: 2px dashed #ddd;
            border-radius: 15px;
            margin: 20px 0;
            background: #f8f9fa;
        }
        
        .method-selection h4 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .method-selection p {
            color: #666;
            margin-bottom: 20px;
        }
        
        .camera-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
            border: 1px solid #e9ecef;
            text-align: center;
        }
        
        .camera-header {
            margin-bottom: 20px;
        }
        
        .camera-header h4 {
            color: #333;
            font-size: 18px;
            margin-bottom: 5px;
        }
        
        .camera-header p {
            color: #666;
            font-size: 14px;
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
        
        .camera-controls {
            margin: 20px 0;
        }
        
        .capture-btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin: 0 10px;
            transition: all 0.3s ease;
        }
        
        .capture-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(40,167,69,0.3);
        }
        
        .capture-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .cancel-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin: 0 10px;
            transition: all 0.3s ease;
        }
        
        .cancel-btn:hover {
            transform: translateY(-2px);
            background: #5a6268;
        }
        
        .captured-photos {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
            border: 1px solid #e9ecef;
        }
        
        .captured-photos h4 {
            color: #333;
            margin-bottom: 20px;
            text-align: center;
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
            transition: all 0.3s ease;
        }
        
        .photo-item:hover {
            border-color: #667eea;
            transform: scale(1.05);
        }
        
        .photo-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .photo-item .photo-number {
            position: absolute;
            top: 5px;
            left: 5px;
            background: rgba(102,126,234,0.9);
            color: white;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
        }
        
        .photo-item .retake-single {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(255,0,0,0.8);
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .photos-actions {
            text-align: center;
            margin-top: 20px;
        }
        
        .retake-btn {
            background: #ffc107;
            color: #000;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .retake-btn:hover {
            transform: translateY(-2px);
            background: #e0a800;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <h2>FULL ATTEND</h2>
            <nav>
                <a href="student_dashboard.php">Dashboard</a>
                <a href="face_registration.php" class="active">Face Registration</a>
                <a href="mark_attendance.php">Mark Attendance</a>
                <a href="my_attendance.php">My Attendance</a>
                <a href="student_timetable.php">Time Table</a>
                <a href="settings.php">Settings</a>
                <a href="student_logout.php">Logout</a>
            </nav>
            <div class="logos">
                <img src="../images/cihe_logo.png" alt="CIHE Logo">
                <img src="../images/fullattend_logo.png" alt="FullAttend Logo">
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="face-registration">
                <div class="status-card">
                    <h2><i class="fas fa-camera"></i> Face Registration System</h2>
                    <p>Register your face for automatic attendance marking</p>
                </div>

                <?php if (!empty($success_message)): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($isRegistered): ?>
                    <div class="registered-status">
                        <h3><i class="fas fa-check-circle"></i> Face Registration Complete!</h3>
                        <p>Your face has been successfully registered in the system.</p>
                        <p>You can now use the face attendance system to mark your attendance.</p>
                        <a href="mark_attendance.php" class="attendance-link">
                            <i class="fas fa-camera"></i> Go to Face Attendance
                        </a>
                    </div>
                <?php else: ?>
                    <div class="registration-card">
                        <h3><i class="fas fa-user-plus"></i> Register Your Face</h3>
                        <p>Upload 5 different photos of yourself for accurate face recognition</p>

                        <div class="instructions">
                            <h4><i class="fas fa-info-circle"></i> Photo Guidelines:</h4>
                            <ul>
                                <li>Take photos in good lighting conditions</li>
                                <li>Look directly at the camera</li>
                                <li>Use different angles (straight, slightly left, slightly right)</li>
                                <li>Avoid wearing glasses or hats if possible</li>
                                <li>Ensure your face is clearly visible</li>
                                <li>Photos should be at least 400x400 pixels</li>
                            </ul>
                        </div>

                        <form method="POST" id="registrationForm">
                            <!-- Camera Method Selection -->
                            <div class="method-selection">
                                <h4><i class="fas fa-camera"></i> Take Photos with Camera</h4>
                                <p>Use your camera to take 5 different photos for registration</p>
                                <button type="button" onclick="startCamera()" class="upload-btn" id="startCameraBtn">
                                    <i class="fas fa-video"></i> Start Camera
                                </button>
                            </div>

                            <!-- Camera Section -->
                            <div id="cameraSection" class="camera-section" style="display: none;">
                                <div class="camera-header">
                                    <h4>Photo <span id="photoCount">1</span> of 5</h4>
                                    <p>Position your face in the oval and click capture</p>
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
                                    <button type="button" onclick="retakeAllPhotos()" class="retake-btn">
                                        <i class="fas fa-redo"></i> Retake All Photos
                                    </button>
                                </div>
                            </div>

                            <!-- Hidden inputs for captured photos -->
                            <input type="hidden" name="captured_photos" id="capturedPhotosData">

                            <div style="text-align: center; margin-top: 20px;">
                                <button type="submit" id="submitBtn" class="upload-btn" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);" disabled>
                                    <i class="fas fa-user-check"></i> Register Face
                                </button>
                            </div>
                        </form>
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

        async function startCamera() {
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
                    <button type="button" class="retake-single" onclick="retakeSinglePhoto(${index})" title="Retake this photo">×</button>
                `;
                photosGrid.appendChild(photoDiv);
            });
            
            // Update submit button
            const submitBtn = document.getElementById('submitBtn');
            if (capturedPhotos.length === maxPhotos) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-user-check"></i> Register Face';
            } else {
                submitBtn.disabled = true;
                submitBtn.innerHTML = `<i class="fas fa-camera"></i> Need ${maxPhotos - capturedPhotos.length} more photos`;
            }
            
            // Update hidden input with photo data
            document.getElementById('capturedPhotosData').value = JSON.stringify(capturedPhotos);
        }

        function retakeSinglePhoto(index) {
            capturedPhotos.splice(index, 1);
            currentPhotoIndex = capturedPhotos.length;
            
            if (capturedPhotos.length === 0) {
                document.getElementById('capturedPhotos').style.display = 'none';
                document.getElementById('startCameraBtn').style.display = 'inline-block';
            } else {
                showCapturedPhotos();
            }
            
            // Re-enable capture button if needed
            const captureBtn = document.getElementById('captureBtn');
            captureBtn.disabled = false;
            captureBtn.innerHTML = '<i class="fas fa-camera"></i> Capture Photo';
        }

        function retakeAllPhotos() {
            capturedPhotos = [];
            currentPhotoIndex = 0;
            document.getElementById('capturedPhotos').style.display = 'none';
            document.getElementById('startCameraBtn').style.display = 'inline-block';
            
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-camera"></i> Take 5 photos first';
            
            // Reset capture button
            const captureBtn = document.getElementById('captureBtn');
            captureBtn.disabled = false;
            captureBtn.innerHTML = '<i class="fas fa-camera"></i> Capture Photo';
        }

        // Handle form submission
        document.getElementById('registrationForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (capturedPhotos.length !== maxPhotos) {
                alert('Please capture exactly 5 photos before registering.');
                return;
            }
            
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registering...';
            
            try {
                // Prepare form data for API call
                const formData = new FormData();
                formData.append('student_id', '<?php echo $student_id; ?>');
                formData.append('name', '<?php echo $student_name; ?>');
                
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
                
                const result = await response.json();
                
                if (response.ok && result.success) {
                    // Update database to mark face as registered
                    const dbResponse = await fetch('update_face_status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            student_id: '<?php echo $student_id; ?>',
                            status: 'registered'
                        })
                    });
                    
                    if (dbResponse.ok) {
                        alert('Face registration successful! You can now use face attendance.');
                        window.location.reload();
                    } else {
                        alert('Face registered but database update failed. Please contact admin.');
                    }
                } else {
                    alert('Face registration failed: ' + (result.error || result.message || 'Unknown error'));
                }
            } catch (error) {
                alert('Network error: ' + error.message);
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-user-check"></i> Register Face';
            }
        });

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
