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

// Get recent attendance records
$stmt = $pdo->prepare("
    SELECT a.*, s.session_name, c.class_name 
    FROM attendance a 
    JOIN sessions s ON a.session_id = s.id 
    JOIN classes c ON s.class_id = c.id 
    WHERE a.student_id = (SELECT id FROM students WHERE student_id = ?) 
    ORDER BY a.attendance_time DESC 
    LIMIT 5
");
$stmt->execute([$student_id]);
$recent_attendance = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Face Attendance - FullAttend</title>
    <link rel="stylesheet" href="student_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .attendance-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .attendance-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .header-section {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            color: white;
        }
        
        .mode-toggle {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .toggle-label {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-weight: 600;
        }
        
        .toggle-label input {
            margin-right: 10px;
        }
        
        .toggle-slider {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
            background-color: #ccc;
            border-radius: 24px;
            margin-right: 10px;
            transition: 0.3s;
        }
        
        .toggle-slider::before {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background-color: white;
            top: 2px;
            left: 2px;
            transition: 0.3s;
        }
        
        input:checked + .toggle-slider {
            background-color: #667eea;
        }
        
        input:checked + .toggle-slider::before {
            transform: translateX(26px);
        }
        
        .capture-section {
            margin: 30px 0;
        }
        
        .start-capture {
            text-align: center;
            padding: 40px;
            border: 2px dashed #ddd;
            border-radius: 15px;
            background: #f8f9fa;
        }
        
        .start-camera-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin: 10px;
            transition: transform 0.3s;
        }
        
        .start-camera-btn:hover {
            transform: translateY(-2px);
        }
        
        .camera-section {
            text-align: center;
            padding: 20px;
            border-radius: 15px;
            background: #f8f9fa;
        }
        
        .webcam-container {
            position: relative;
            display: inline-block;
            border-radius: 15px;
            overflow: hidden;
            margin: 20px 0;
        }
        
        .webcam {
            border-radius: 15px;
            max-width: 100%;
            height: auto;
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
            box-shadow: 0 0 20px rgba(0,0,0,0.3);
        }
        
        .auto-capture-controls {
            margin: 20px 0;
        }
        
        .auto-status {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin: 15px 0;
            border: 1px solid #e9ecef;
        }
        
        .auto-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
        }
        
        .pulse-dot {
            width: 12px;
            height: 12px;
            background: #28a745;
            border-radius: 50%;
            margin-right: 10px;
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
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
            margin: 10px;
            transition: transform 0.3s;
        }
        
        .capture-btn:hover {
            transform: translateY(-2px);
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
            margin: 10px;
            transition: transform 0.3s;
        }
        
        .cancel-btn:hover {
            transform: translateY(-2px);
        }
        
        .photo-preview {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 15px;
            border: 1px solid #e9ecef;
            margin: 20px 0;
        }
        
        .preview-image {
            max-width: 400px;
            width: 100%;
            height: auto;
            border-radius: 10px;
            margin: 15px 0;
        }
        
        .photo-controls {
            margin: 20px 0;
        }
        
        .retake-btn {
            background: #ffc107;
            color: #000;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            margin: 10px;
            transition: transform 0.3s;
        }
        
        .retake-btn:hover {
            transform: translateY(-2px);
        }
        
        .mark-attendance-btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            margin: 10px;
            transition: transform 0.3s;
        }
        
        .mark-attendance-btn:hover {
            transform: translateY(-2px);
        }
        
        .mark-attendance-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .message {
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            font-weight: 600;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .message.info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .attendance-success, .test-results {
            background: white;
            padding: 25px;
            border-radius: 15px;
            border: 1px solid #e9ecef;
            margin: 20px 0;
        }
        
        .student-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 15px 0;
        }
        
        .tips-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            margin: 20px 0;
        }
        
        .tips-section h4 {
            color: #333;
            margin-bottom: 15px;
        }
        
        .tips-section ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .tips-section li {
            margin: 8px 0;
            color: #666;
        }
        
        .recent-attendance {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin: 20px 0;
            border: 1px solid #e9ecef;
        }
        
        .attendance-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .attendance-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .attendance-item:last-child {
            border-bottom: none;
        }
        
        .attendance-info h5 {
            margin: 0 0 5px 0;
            color: #333;
        }
        
        .attendance-info p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-badge.present {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.late {
            background: #fff3cd;
            color: #856404;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.3s;
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .attendance-container {
                padding: 10px;
            }
            
            .attendance-card {
                padding: 20px;
            }
            
            .webcam {
                width: 100%;
                max-width: 400px;
            }
            
            .face-oval {
                width: 150px;
                height: 180px;
            }
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
                <a href="face_registration.php">Face Registration</a>
                <a href="mark_attendance.php" class="active">Mark Attendance</a>
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
            <div class="attendance-container">
                <div class="attendance-card">
                    <div class="header-section">
                        <h2><i class="fas fa-camera"></i> Face Attendance System</h2>
                        <p>Mark your attendance using facial recognition</p>
                        <p><strong>Student:</strong> <?php echo htmlspecialchars($student_name); ?> (<?php echo htmlspecialchars($student_id); ?>)</p>
                    </div>

                    <div class="mode-toggle">
                        <label class="toggle-label">
                            <input type="checkbox" id="testMode">
                            <span class="toggle-slider"></span>
                            Test Mode (Detailed Analysis)
                        </label>
                    </div>

                    <div class="capture-section">
                        <div id="startCapture" class="start-capture">
                            <h3><i class="fas fa-camera"></i> Ready to Mark Attendance?</h3>
                            <p class="instruction">Choose your preferred method to capture your photo</p>
                            <button onclick="startCamera()" class="start-camera-btn">
                                <i class="fas fa-camera"></i> Start Camera
                            </button>
                            <button onclick="startAutoCapture()" class="start-camera-btn" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                                <i class="fas fa-magic"></i> Start Auto-Capture
                            </button>
                        </div>

                        <div id="cameraSection" class="camera-section" style="display: none;">
                            <div class="camera-instructions">
                                <h3><i class="fas fa-user"></i> Position Your Face</h3>
                                <ul style="text-align: left; display: inline-block;">
                                    <li>✓ Look directly at the camera</li>
                                    <li>✓ Ensure good lighting</li>
                                    <li>✓ Keep your face clearly visible</li>
                                    <li>✓ Avoid shadows or glare</li>
                                </ul>
                            </div>

                            <div class="webcam-container">
                                <video id="webcam" class="webcam" autoplay playsinline></video>
                                <div class="face-guide">
                                    <div class="face-oval"></div>
                                </div>
                            </div>

                            <div class="camera-controls">
                                <button onclick="capturePhoto()" class="capture-btn">
                                    <i class="fas fa-camera"></i> Capture Photo
                                </button>
                                <button onclick="stopCamera()" class="cancel-btn">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            </div>
                        </div>

                        <div id="autoCaptureSection" class="camera-section" style="display: none;">
                            <div class="camera-instructions">
                                <h3><i class="fas fa-magic"></i> Auto-Capture Active</h3>
                                <p>System is automatically capturing photos every 2 seconds until recognition succeeds</p>
                            </div>

                            <div class="webcam-container">
                                <video id="autoWebcam" class="webcam" autoplay playsinline></video>
                                <div class="face-guide">
                                    <div class="face-oval"></div>
                                </div>
                            </div>

                            <div class="auto-capture-controls">
                                <div class="auto-status">
                                    <div class="auto-indicator">
                                        <div class="pulse-dot"></div>
                                        <span>Auto-capturing...</span>
                                    </div>
                                    <div class="auto-message">
                                        <div id="autoMessage">🚀 Starting auto-capture process...</div>
                                    </div>
                                </div>
                                <button onclick="stopAutoCapture()" class="btn-danger">
                                    <i class="fas fa-stop"></i> Stop Auto-Capture
                                </button>
                            </div>
                        </div>

                        <div id="photoPreview" class="photo-preview" style="display: none;">
                            <h3>Captured Photo</h3>
                            <img id="previewImage" src="" alt="Captured" class="preview-image">
                            <div class="photo-controls">
                                <button onclick="retakePhoto()" class="retake-btn">
                                    <i class="fas fa-redo"></i> Retake Photo
                                </button>
                                <button onclick="markAttendance()" id="markBtn" class="mark-attendance-btn">
                                    <i class="fas fa-check"></i> Mark Attendance
                                </button>
                            </div>
                        </div>
                    </div>

                    <div id="messageArea"></div>
                    <div id="resultArea"></div>

                    <div class="tips-section">
                        <h4><i class="fas fa-lightbulb"></i> Tips for Better Recognition</h4>
                        <ul>
                            <li>Ensure good lighting on your face</li>
                            <li>Look directly at the camera</li>
                            <li>Avoid wearing hats or dark glasses</li>
                            <li>Keep your face clearly visible</li>
                            <li>If recognition fails, try adjusting your position</li>
                        </ul>
                    </div>
                </div>

                <?php if (!empty($recent_attendance)): ?>
                <div class="recent-attendance">
                    <h3><i class="fas fa-history"></i> Recent Attendance</h3>
                    <ul class="attendance-list">
                        <?php foreach ($recent_attendance as $record): ?>
                        <li class="attendance-item">
                            <div class="attendance-info">
                                <h5><?php echo htmlspecialchars($record['class_name']); ?></h5>
                                <p><?php echo htmlspecialchars($record['session_name']); ?></p>
                                <p><i class="fas fa-clock"></i> <?php echo date('M j, Y g:i A', strtotime($record['attendance_time'])); ?></p>
                            </div>
                            <span class="status-badge <?php echo strtolower($record['status']); ?>">
                                <?php echo ucfirst($record['status']); ?>
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        let stream = null;
        let capturedPhoto = null;
        let isAutoCapturing = false;
        let autoInterval = null;
        let captureCount = 0;

        async function startCamera() {
            try {
                stream = await navigator.mediaDevices.getUserMedia({ 
                    video: { 
                        width: 640, 
                        height: 480, 
                        facingMode: 'user' 
                    } 
                });
                
                document.getElementById('startCapture').style.display = 'none';
                document.getElementById('cameraSection').style.display = 'block';
                document.getElementById('webcam').srcObject = stream;
            } catch (error) {
                showMessage('error', 'Failed to access camera: ' + error.message);
            }
        }

        async function startAutoCapture() {
            try {
                stream = await navigator.mediaDevices.getUserMedia({ 
                    video: { 
                        width: 640, 
                        height: 480, 
                        facingMode: 'user' 
                    } 
                });
                
                document.getElementById('startCapture').style.display = 'none';
                document.getElementById('autoCaptureSection').style.display = 'block';
                document.getElementById('autoWebcam').srcObject = stream;
                
                isAutoCapturing = true;
                captureCount = 0;
                
                setTimeout(() => {
                    autoInterval = setInterval(autoCapture, 2000);
                }, 1000);
                
            } catch (error) {
                showMessage('error', 'Failed to access camera: ' + error.message);
            }
        }

        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
            
            document.getElementById('cameraSection').style.display = 'none';
            document.getElementById('startCapture').style.display = 'block';
        }

        function stopAutoCapture() {
            if (autoInterval) {
                clearInterval(autoInterval);
                autoInterval = null;
            }
            
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
            
            isAutoCapturing = false;
            document.getElementById('autoCaptureSection').style.display = 'none';
            document.getElementById('startCapture').style.display = 'block';
            document.getElementById('autoMessage').textContent = '⏹️ Auto-capture stopped.';
        }

        function capturePhoto() {
            const video = document.getElementById('webcam');
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0);
            
            capturedPhoto = canvas.toDataURL('image/jpeg');
            
            document.getElementById('previewImage').src = capturedPhoto;
            document.getElementById('cameraSection').style.display = 'none';
            document.getElementById('photoPreview').style.display = 'block';
            
            stopCamera();
        }

        async function autoCapture() {
            if (!isAutoCapturing) return;
            
            const video = document.getElementById('autoWebcam');
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0);
            
            const imageData = canvas.toDataURL('image/jpeg');
            captureCount++;
            
            document.getElementById('autoMessage').textContent = 
                `🔄 Auto-capture attempt ${captureCount} - Checking recognition...`;
            
            try {
                const blob = dataURLtoBlob(imageData);
                const formData = new FormData();
                formData.append('photo', blob, `auto-attendance-${Date.now()}.jpg`);
                formData.append('student_id', '<?php echo $student_id; ?>');
                formData.append('name', '<?php echo $student_name; ?>');
                formData.append('is_test', document.getElementById('testMode').checked ? '1' : '0');
                
                const response = await fetch('http://localhost:8000/api/mark-attendance/', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (response.ok) {
                    stopAutoCapture();
                    capturedPhoto = imageData;
                    document.getElementById('previewImage').src = capturedPhoto;
                    document.getElementById('photoPreview').style.display = 'block';
                    
                    if (document.getElementById('testMode').checked) {
                        showTestResult(result);
                        document.getElementById('autoMessage').textContent = '✅ Auto-capture successful! Test completed.';
                    } else {
                        showAttendanceResult(result);
                        document.getElementById('autoMessage').textContent = `✅ Attendance marked for ${result.student_name}!`;
                        
                        // Save to database
                        saveAttendanceToDatabase(result);
                    }
                } else {
                    document.getElementById('autoMessage').textContent = 
                        `❌ Attempt ${captureCount}: ${result.error || result.message} - Trying again in 2 seconds...`;
                }
            } catch (error) {
                document.getElementById('autoMessage').textContent = 
                    `❌ Attempt ${captureCount}: Network error - ${error.message} - Trying again...`;
            }
        }

        function retakePhoto() {
            capturedPhoto = null;
            document.getElementById('photoPreview').style.display = 'none';
            document.getElementById('startCapture').style.display = 'block';
            clearMessages();
        }

        async function markAttendance() {
            if (!capturedPhoto) {
                showMessage('error', 'Please capture a photo first');
                return;
            }
            
            const markBtn = document.getElementById('markBtn');
            markBtn.disabled = true;
            markBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            
            showMessage('info', '🔍 Recognizing face...');
            
            try {
                const blob = dataURLtoBlob(capturedPhoto);
                const formData = new FormData();
                formData.append('photo', blob, 'attendance.jpg');
                formData.append('student_id', '<?php echo $student_id; ?>');
                formData.append('name', '<?php echo $student_name; ?>');
                formData.append('is_test', document.getElementById('testMode').checked ? '1' : '0');
                
                const response = await fetch('http://localhost:8000/api/mark-attendance/', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (response.ok) {
                    if (document.getElementById('testMode').checked) {
                        showTestResult(result);
                        showMessage('info', '🧪 Test completed - see results below');
                    } else {
                        showAttendanceResult(result);
                        showMessage('success', `✅ ${result.message}`);
                        
                        // Save to database
                        saveAttendanceToDatabase(result);
                    }
                } else {
                    showMessage('error', `❌ ${result.error || result.message}`);
                    if (result.suggestion) {
                        showMessage('info', `💡 ${result.suggestion}`);
                    }
                }
            } catch (error) {
                showMessage('error', `❌ Network error: ${error.message}`);
            } finally {
                markBtn.disabled = false;
                markBtn.innerHTML = '<i class="fas fa-check"></i> Mark Attendance';
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

        function showMessage(type, message) {
            const messageArea = document.getElementById('messageArea');
            messageArea.innerHTML = `<div class="message ${type}"><pre>${message}</pre></div>`;
        }

        function clearMessages() {
            document.getElementById('messageArea').innerHTML = '';
            document.getElementById('resultArea').innerHTML = '';
        }

        function showAttendanceResult(result) {
            const resultArea = document.getElementById('resultArea');
            resultArea.innerHTML = `
                <div class="attendance-success">
                    <h3><i class="fas fa-check-circle"></i> Attendance Marked</h3>
                    <div class="student-info">
                        <p><strong>Student:</strong> ${result.student_name}</p>
                        <p><strong>ID:</strong> ${result.student_id}</p>
                        <p><strong>Confidence:</strong> ${(result.confidence * 100).toFixed(1)}%</p>
                        <p><strong>Time:</strong> ${new Date(result.timestamp).toLocaleString()}</p>
                    </div>
                </div>
            `;
        }

        function showTestResult(result) {
            const resultArea = document.getElementById('resultArea');
            let comparisonsHtml = '';
            
            if (result.individual_comparisons) {
                result.individual_comparisons.forEach(comparison => {
                    comparisonsHtml += `
                        <div class="comparison-item ${comparison.is_ml_prediction ? 'ml-match' : ''}">
                            <p><strong>${comparison.student_name}</strong> (${comparison.student_id})</p>
                            <p>Similarity: ${(comparison.cosine_similarity * 100).toFixed(1)}%</p>
                            ${comparison.is_ml_prediction ? '<span class="ml-badge">ML Choice</span>' : ''}
                        </div>
                    `;
                });
            }
            
            resultArea.innerHTML = `
                <div class="test-results">
                    <h3><i class="fas fa-flask"></i> Test Results</h3>
                    <div class="ml-prediction">
                        <h4>ML Model Prediction</h4>
                        <div class="prediction-card">
                            <p><strong>Predicted Student:</strong> ${result.ml_prediction?.predicted_student_id || 'Not recognized'}</p>
                            <p><strong>Confidence:</strong> ${(result.ml_prediction?.confidence * 100).toFixed(1)}%</p>
                            <p><strong>Threshold:</strong> ${(result.ml_prediction?.threshold_used * 100).toFixed(1)}%</p>
                        </div>
                    </div>
                    <div class="individual-comparisons">
                        <h4>Individual Student Comparisons</h4>
                        <div class="comparisons-list">${comparisonsHtml}</div>
                    </div>
                </div>
            `;
        }

        async function saveAttendanceToDatabase(result) {
            try {
                const response = await fetch('save_attendance.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        student_id: result.student_id,
                        confidence: result.confidence,
                        timestamp: result.timestamp,
                        method: 'face_recognition'
                    })
                });
                
                if (!response.ok) {
                    console.error('Failed to save attendance to database');
                }
            } catch (error) {
                console.error('Error saving attendance:', error);
            }
        }

        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
            }
            if (autoInterval) {
                clearInterval(autoInterval);
            }
        });
    </script>
</body>
</html>
