<?php
session_start();
require_once '../config/database.php';

// Check if lecturer is logged in
if (!isset($_SESSION['lecturer_logged_in']) || $_SESSION['lecturer_logged_in'] !== true) {
    header('Location: lecturer_login.php');
    exit();
}

$lecturer_id = $_SESSION['lecturer_id'];
$lecturer_name = $_SESSION['lecturer_name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Face Attendance - Lecturer Panel</title>
    <link rel="stylesheet" href="lecturer_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .attendance-container {
            max-width: 1000px;
            margin: 0 auto;
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
            animation: pulse-border 2s infinite;
        }
        
        @keyframes pulse-border {
            0% { border-color: rgba(255,255,255,0.8); }
            50% { border-color: rgba(102,126,234,0.8); }
            100% { border-color: rgba(255,255,255,0.8); }
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
        
        .btn-danger {
            background: #dc3545;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            margin: 10px;
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
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <h2>LECTURER PANEL</h2>
            <nav>
                <a href="lecturer_dashboard.php">Dashboard</a>
                <a href="face_attendance.php" class="active">Face Attendance</a>
                <a href="all_students.php">All Students</a>
                <a href="lecturer_logout.php">Logout</a>
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
                        <h2><i class="fas fa-camera"></i> Student Face Attendance</h2>
                        <p>Take attendance using facial recognition for all students</p>
                        <p><strong>Lecturer:</strong> <?php echo htmlspecialchars($lecturer_name); ?></p>
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
                            <h3><i class="fas fa-camera"></i> Ready to Take Attendance?</h3>
                            <p class="instruction">Start the attendance system for students</p>
                            <button id="startBtn" onclick="startAutoCapture()" class="start-camera-btn" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                                    <i class="fas fa-magic"></i> Start Auto-Attendance
                                </button>
                            <div id="attendanceInstructions" style="margin-top: 15px; padding: 15px; background: #e8f4f8; border-radius: 10px; font-size: 14px;">
                                <i class="fas fa-info-circle"></i> <strong>How it works:</strong><br>
                                • Click "Start Auto-Attendance" to begin<br>
                                • System will automatically detect and confirm one student<br>
                                • Auto-attendance stops after each confirmation<br>
                                • Click the button again for the next student
                            </div>
                        </div>

                        <div id="autoCaptureSection" class="camera-section" style="display: none;">
                            <div class="camera-instructions">
                                <h3><i class="fas fa-magic"></i> Auto-Attendance Active</h3>
                                <p>System is automatically capturing and recognizing students</p>
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
                                        <div id="autoMessage">🚀 Starting attendance system...</div>
                                    </div>
                                </div>
                                <button onclick="stopAutoCapture()" class="btn-danger">
                                    <i class="fas fa-stop"></i> Stop Attendance
                                </button>
                            </div>
                        </div>
                    </div>

                    <div id="messageArea"></div>
                    <div id="resultArea"></div>
                </div>
            </div>
        </main>
    </div>

    <script>
        let stream = null;
        let isAutoCapturing = false;
        let autoInterval = null;
        let captureCount = 0;

        async function startAutoCapture() {
            try {
                // Reset and disable start button when starting
                const startBtn = document.getElementById('startBtn');
                startBtn.innerHTML = '<i class="fas fa-magic"></i> Starting...';
                startBtn.disabled = true;
                
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
                
                // Start auto-capture every 3 seconds
                autoInterval = setInterval(autoCapture, 3000);
                
            } catch (error) {
                alert('Failed to access camera: ' + error.message);
                const startBtn = document.getElementById('startBtn');
                if (startBtn) { startBtn.disabled = false; startBtn.innerHTML = '<i class="fas fa-magic"></i> Start Auto-Attendance'; }
            }
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
            
            // Update the start button text for next student and enable it
            const startBtn = document.getElementById('startBtn');
            if (startBtn) { startBtn.innerHTML = '<i class="fas fa-magic"></i> Start Attendance for Next Student'; startBtn.disabled = false; }
            
            // Reset auto message
            document.getElementById('autoMessage').textContent = '⏹️ Ready for next student attendance.';
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
                `🔄 Capture ${captureCount} - Checking for students...`;
            
            try {
                const blob = dataURLtoBlob(imageData);
                const formData = new FormData();
                formData.append('photo', blob, `attendance-${Date.now()}.jpg`);
                formData.append('lecturer_id', '<?php echo $lecturer_id; ?>');
                formData.append('is_test', document.getElementById('testMode').checked ? '1' : '0');
                
                const response = await fetch('http://localhost:8000/api/mark-attendance/', {
                    method: 'POST',
                    body: formData
                });
                

                const result = await response.json();
                // Debug: log API response so developer can inspect shape in browser console
                console.log('mark-attendance API response:', result);

                // Normalize possible payload shapes: { student_id, student_name, confidence, timestamp, message }
                let payload = result;
                if (result && result.data) payload = result.data;

                // Some APIs return fields under different keys; try a few common ones
                const student = {
                    student_id: payload && (payload.student_id || payload.id || payload.s_id || payload.studentId) ,
                    student_name: payload && (payload.student_name || payload.name || payload.studentName),
                    confidence: payload && (payload.confidence || payload.score || payload.conf),
                    timestamp: payload && (payload.timestamp || payload.time || payload.t)
                };

                const messageText = (payload && payload.message) || (result && result.message) || '';

                const hasStudentData = student.student_id || student.student_name || (/marked|already/i.test(messageText));

                if (hasStudentData) {
                    // Fill fallback values
                    if (!student.timestamp) student.timestamp = new Date().toISOString();
                    if (!student.confidence) student.confidence = 0;

                    // Prepare a unified result object to pass to UI functions
                    const unified = {
                        student_id: student.student_id || '',
                        student_name: student.student_name || 'Unknown Student',
                        confidence: parseFloat(student.confidence) || 0,
                        timestamp: student.timestamp,
                        message: messageText
                    };

                    document.getElementById('autoMessage').textContent = `✅ ${unified.message || 'Attendance processed for ' + unified.student_name}`;

                    showAttendanceResult(unified);

                    if (!document.getElementById('testMode').checked && unified.student_id) {
                        saveAttendanceToDatabase(unified);
                    }

                    // Small delay to ensure the result renders before stopping camera
                    setTimeout(() => stopAutoCapture(), 600);
                } else {
                    document.getElementById('autoMessage').textContent = `❌ Capture ${captureCount}: ${result.error || result.message || 'No student detected'} - Continuing...`;
                }
            } catch (error) {
                document.getElementById('autoMessage').textContent = 
                    `❌ Capture ${captureCount}: Network error - Continuing...`;
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

        function showAttendanceResult(result) {
            const resultArea = document.getElementById('resultArea');
            const timestamp = new Date(result.timestamp).toLocaleString();
            
            // Determine the status message
            let statusMessage = 'Attendance Marked';
            let statusIcon = 'check-circle';
            let statusColor = '#28a745';
            
            if (result.message && result.message.includes('already marked')) {
                statusMessage = 'Already Marked Today';
                statusIcon = 'info-circle';
                statusColor = '#17a2b8';
            }
            
            const resultHtml = `
                <div class="attendance-success">
                    <h3><i class="fas fa-${statusIcon}" style="color: ${statusColor};"></i> ${statusMessage}</h3>
                    <div class="student-info">
                        <p><strong>Student:</strong> ${result.student_name}</p>
                        <p><strong>Student ID:</strong> ${result.student_id}</p>
                        <p><strong>Confidence:</strong> ${(result.confidence * 100).toFixed(2)}%</p>
                        <p><strong>Time:</strong> ${timestamp}</p>
                        ${result.message ? `<p><strong>Status:</strong> ${result.message}</p>` : ''}
                    </div>
                </div>
            `;
            
            resultArea.innerHTML = resultHtml + resultArea.innerHTML;
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
                        method: 'face_recognition',
                        lecturer_id: '<?php echo $lecturer_id; ?>'
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

        // Add toggle slider styles
        const style = document.createElement('style');
        style.textContent = `
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
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
