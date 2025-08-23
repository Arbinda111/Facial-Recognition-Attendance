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

// Get database connection
$pdo = getDBConnection();

// Fetch current settings from database
$current_settings = [];
try {
    $stmt = $pdo->query("SELECT setting_name, setting_value FROM system_settings");
    $settings_data = $stmt->fetchAll();
    
    // Convert to associative array
    foreach ($settings_data as $setting) {
        $current_settings[$setting['setting_name']] = $setting['setting_value'];
    }
    
    // Set default values if not found
    $default_settings = [
        'institute_name' => 'Full Attend Institute',
        'admin_email' => 'admin@fullattend.com',
        'attendance_threshold' => '75',
        'late_arrival_minutes' => '15',
        'session_timeout' => '30',
        'email_notifications' => '1'
    ];
    
    foreach ($default_settings as $key => $value) {
        if (!isset($current_settings[$key])) {
            $current_settings[$key] = $value;
        }
    }
    
} catch (PDOException $e) {
    $error_message = 'Error fetching settings: ' . $e->getMessage();
    $current_settings = [
        'institute_name' => 'Full Attend Institute',
        'admin_email' => 'admin@fullattend.com',
        'attendance_threshold' => '75',
        'late_arrival_minutes' => '15',
        'session_timeout' => '30',
        'email_notifications' => '1'
    ];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $institute_name = trim($_POST['institute_name'] ?? '');
    $admin_email = trim($_POST['admin_email'] ?? '');
    $attendance_threshold = (int)($_POST['attendance_threshold'] ?? 75);
    $late_arrival_minutes = (int)($_POST['late_arrival_minutes'] ?? 15);
    $session_timeout = (int)($_POST['session_timeout'] ?? 30);
    $email_notifications = isset($_POST['email_notifications']) ? '1' : '0';
    
    if (empty($institute_name) || empty($admin_email)) {
        $error_message = 'Institute name and admin email are required.';
    } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        try {
            // Update settings in database
            $settings_to_update = [
                'institute_name' => $institute_name,
                'admin_email' => $admin_email,
                'attendance_threshold' => (string)$attendance_threshold,
                'late_arrival_minutes' => (string)$late_arrival_minutes,
                'session_timeout' => (string)$session_timeout,
                'email_notifications' => $email_notifications
            ];
            
            $admin_id = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 1;
            
            foreach ($settings_to_update as $setting_name => $setting_value) {
                $stmt = $pdo->prepare("
                    INSERT INTO system_settings (setting_name, setting_value, description, updated_by) 
                    VALUES (?, ?, '', ?) 
                    ON DUPLICATE KEY UPDATE 
                    setting_value = VALUES(setting_value), 
                    updated_by = VALUES(updated_by),
                    updated_at = CURRENT_TIMESTAMP
                ");
                $stmt->execute([$setting_name, $setting_value, $admin_id]);
            }
            
            $success_message = "Settings updated successfully!";
            
            // Update current settings array
            $current_settings = array_merge($current_settings, $settings_to_update);
            
        } catch (PDOException $e) {
            $error_message = 'Error updating settings: ' . $e->getMessage();
        }
    }
}

// Get system information
$system_info = [
    'php_version' => phpversion(),
    'current_time' => date('Y-m-d H:i:s'),
    'total_classes' => 0,
    'total_students' => 0,
    'total_sessions' => 0
];

try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM classes WHERE status = 'active'");
    $system_info['total_classes'] = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM students WHERE status = 'active'");
    $system_info['total_students'] = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sessions");
    $system_info['total_sessions'] = $stmt->fetch()['count'];
} catch (PDOException $e) {
    // Silent fail for system info
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings - Full Attend</title>
    <link rel="stylesheet" href="admin_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="admin-main">
            <header class="dashboard-header">
                <div class="header-left">
                    <h1>System Settings</h1>
                    <p>Configure basic system preferences</p>
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
            
            <!-- System Information -->
            <section class="stats-grid">
                
                
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $system_info['total_classes']; ?></h3>
                        <p>Active Classes</p>
                    </div>
                </div>
                
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $system_info['total_students']; ?></h3>
                        <p>Total Students</p>
                    </div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $system_info['total_sessions']; ?></h3>
                        <p>Total Sessions</p>
                    </div>
                </div>
            </section>
            
            <!-- Settings Form -->
            <section class="settings-section" style="margin-bottom: 30px;">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-cogs"></i> Basic Settings</h3>
                        <p>Configure essential system preferences</p>
                    </div>
                    
                    <div class="form-container" >
                        <form method="POST" action="" class="settings-form">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="institute_name"><i class="fas fa-building"></i> Institute Name</label>
                                    <input type="text" id="institute_name" name="institute_name" 
                                           value="<?php echo htmlspecialchars($current_settings['institute_name']); ?>" 
                                           placeholder="Enter institute name" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="admin_email"><i class="fas fa-envelope"></i> Admin Email</label>
                                    <input type="email" id="admin_email" name="admin_email" 
                                           value="<?php echo htmlspecialchars($current_settings['admin_email']); ?>" 
                                           placeholder="admin@example.com" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="attendance_threshold"><i class="fas fa-percentage"></i> Attendance Threshold (%)</label>
                                    <input type="number" id="attendance_threshold" name="attendance_threshold" 
                                           value="<?php echo htmlspecialchars($current_settings['attendance_threshold']); ?>" 
                                           min="0" max="100" placeholder="75">
                                    <small>Minimum attendance percentage required</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="late_arrival_minutes"><i class="fas fa-clock"></i> Late Arrival (Minutes)</label>
                                    <input type="number" id="late_arrival_minutes" name="late_arrival_minutes" 
                                           value="<?php echo htmlspecialchars($current_settings['late_arrival_minutes']); ?>" 
                                           min="1" max="60" placeholder="15">
                                    <small>Minutes after which student is marked late</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="session_timeout"><i class="fas fa-hourglass-half"></i> Session Timeout (Minutes)</label>
                                    <input type="number" id="session_timeout" name="session_timeout" 
                                           value="<?php echo htmlspecialchars($current_settings['session_timeout']); ?>" 
                                           min="5" max="120" placeholder="30">
                                    <small>Admin session timeout duration</small>
                                </div>
                                
                                <!-- <div class="form-group checkbox-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="email_notifications" 
                                               <?php echo $current_settings['email_notifications'] === '1' ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        <div class="checkbox-text">
                                            <strong><i class="fas fa-bell"></i> Email Notifications</strong>
                                            <small>Enable system email notifications</small>
                                        </div>
                                    </label>
                                </div> -->
                            </div>
                            
                            <div class="form-actions">
                                <button type="reset" class="btn-secondary">
                                    <i class="fas fa-undo"></i>
                                    Reset
                                </button>
                                <button type="submit" class="btn-primary">
                                    <i class="fas fa-save"></i>
                                    Save Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
            
            <!-- System Information -->
            <section class="system-info-section">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-info-circle"></i> System Information</h3>
                    </div>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-code"></i>
                                <span>PHP Version</span>
                            </div>
                            <div class="info-value"><?php echo $system_info['php_version']; ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-clock"></i>
                                <span>Current Time</span>
                            </div>
                            <div class="info-value"><?php echo $system_info['current_time']; ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-database"></i>
                                <span>Database Status</span>
                            </div>
                            <div class="info-value">
                                <span class="status-badge success">Connected</span>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-chart-line"></i>
                                <span>System Status</span>
                            </div>
                            <div class="info-value">
                                <span class="status-badge success">Active</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        // Form validation
        document.querySelector('.settings-form').addEventListener('submit', function(e) {
            const instituteName = document.getElementById('institute_name').value.trim();
            const adminEmail = document.getElementById('admin_email').value.trim();
            
            if (!instituteName || !adminEmail) {
                e.preventDefault();
                alert('Institute name and admin email are required.');
                return false;
            }
            
            // Validate email format
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(adminEmail)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return false;
            }
        });
        
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 300);
                }, 5000);
            });
            
            // Add some interactivity to info items
            const infoItems = document.querySelectorAll('.info-item');
            infoItems.forEach(item => {
                item.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                
                item.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>
