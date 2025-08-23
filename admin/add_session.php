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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_id = trim($_POST['class_id']);
    $session_name = trim($_POST['session_name']);
    $session_date = trim($_POST['session_date']);
    $start_time = trim($_POST['start_time']);
    $end_time = trim($_POST['end_time']);
    $location = trim($_POST['location']);
    $session_type = trim($_POST['session_type']);
    
    // Basic validation
    if (empty($class_id) || empty($session_name) || empty($session_date) || empty($start_time) || empty($end_time) || empty($location)) {
        $error_message = 'All fields are required.';
    } elseif (strtotime($start_time) >= strtotime($end_time)) {
        $error_message = 'End time must be after start time.';
    } elseif (strtotime($session_date) < strtotime(date('Y-m-d'))) {
        $error_message = 'Session date cannot be in the past.';
    } else {
        try {
            // Insert session into database (using admin id = 1 as default if not in session)
            $admin_id = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 1;
            $stmt = $pdo->prepare("INSERT INTO sessions (session_name, class_id, session_date, start_time, end_time, location, session_type, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$session_name, $class_id, $session_date, $start_time, $end_time, $location, $session_type, $admin_id]);
            
            $success_message = "Session '$session_name' has been successfully scheduled for $session_date from $start_time to $end_time";
            
            // Clear form fields
            $class_id = $session_name = $session_date = $start_time = $end_time = $location = $session_type = '';
        } catch (PDOException $e) {
            $error_message = 'Error creating session: ' . $e->getMessage();
        }
    }
}

// Fetch classes from database
$classes = [];
try {
    $stmt = $pdo->query("SELECT id, class_name, class_code, instructor_name FROM classes WHERE status = 'active' ORDER BY class_name");
    $classes = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = 'Error fetching classes: ' . $e->getMessage();
}

// Fetch recent sessions for display
$recent_sessions = [];
try {
    $stmt = $pdo->query("
        SELECT s.*, c.class_name, c.class_code 
        FROM sessions s 
        JOIN classes c ON s.class_id = c.id 
        WHERE s.session_date >= CURDATE() 
        ORDER BY s.session_date ASC, s.start_time ASC 
        LIMIT 5
    ");
    $recent_sessions = $stmt->fetchAll();
} catch (PDOException $e) {
    // Silent fail for display purposes
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Session - Full Attend</title>
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
                    <h1>Session Management</h1>
                    <p>Create and schedule new class sessions</p>
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
            
            <!-- Session Form -->
            <section class="session-form-section">
                <div class="card">
                    <div class="card-header">
                        <h3>Schedule New Session</h3>
                    </div>
                    
                    <div class="form-container">
                        <form method="POST" action="" class="session-form">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="class_id"><i class="fas fa-book"></i> Class</label>
                                    <select id="class_id" name="class_id" required>
                                        <option value="">Select a class</option>
                                        <?php foreach ($classes as $class): ?>
                                            <option value="<?php echo htmlspecialchars($class['id']); ?>" 
                                                    <?php echo (isset($class_id) && $class_id == $class['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($class['class_name']) . ' (' . htmlspecialchars($class['class_code']) . ')'; ?>
                                                <?php if ($class['instructor_name']): ?>
                                                    - <?php echo htmlspecialchars($class['instructor_name']); ?>
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="session_name"><i class="fas fa-tag"></i> Session Name</label>
                                    <input type="text" id="session_name" name="session_name" 
                                           placeholder="e.g., Introduction to Programming" 
                                           value="<?php echo isset($session_name) ? htmlspecialchars($session_name) : ''; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="session_date"><i class="fas fa-calendar-alt"></i> Session Date</label>
                                    <input type="date" id="session_date" name="session_date" 
                                           value="<?php echo isset($session_date) ? htmlspecialchars($session_date) : ''; ?>" 
                                           min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="session_type"><i class="fas fa-chalkboard-teacher"></i> Session Type</label>
                                    <select id="session_type" name="session_type" required>
                                        <option value="">Select session type</option>
                                        <option value="lecture" <?php echo (isset($session_type) && $session_type == 'lecture') ? 'selected' : ''; ?>>Lecture</option>
                                        <option value="lab" <?php echo (isset($session_type) && $session_type == 'lab') ? 'selected' : ''; ?>>Lab</option>
                                        <option value="tutorial" <?php echo (isset($session_type) && $session_type == 'tutorial') ? 'selected' : ''; ?>>Tutorial</option>
                                        <option value="exam" <?php echo (isset($session_type) && $session_type == 'exam') ? 'selected' : ''; ?>>Exam</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="start_time"><i class="fas fa-clock"></i> Start Time</label>
                                    <input type="time" id="start_time" name="start_time" 
                                           value="<?php echo isset($start_time) ? htmlspecialchars($start_time) : ''; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="end_time"><i class="fas fa-clock"></i> End Time</label>
                                    <input type="time" id="end_time" name="end_time" 
                                           value="<?php echo isset($end_time) ? htmlspecialchars($end_time) : ''; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="location"><i class="fas fa-map-marker-alt"></i> Location</label>
                                    <input type="text" id="location" name="location" placeholder="e.g., Room 101, Lab A" 
                                           value="<?php echo isset($location) ? htmlspecialchars($location) : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                               
                                <button type="submit" class="btn-primary">
                                    <i class="fas fa-plus"></i>
                                    Schedule Session
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
            
            <!-- Upcoming Sessions -->
            <section class="upcoming-sessions">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-calendar-check"></i> Upcoming Sessions</h3>
                        <a href="timetable.php" class="btn-link">View Full Timetable</a>
                    </div>
                    
                    <div class="sessions-list">
                        <?php if (count($recent_sessions) > 0): ?>
                            <?php foreach ($recent_sessions as $session): ?>
                                <div class="session-item">
                                    <div class="session-time">
                                        <div class="time"><?php echo date('g:i A', strtotime($session['start_time'])); ?></div>
                                        <div class="date">
                                            <?php 
                                            $session_date = new DateTime($session['session_date']);
                                            $today = new DateTime();
                                            if ($session_date->format('Y-m-d') == $today->format('Y-m-d')) {
                                                echo 'Today';
                                            } elseif ($session_date->format('Y-m-d') == $today->modify('+1 day')->format('Y-m-d')) {
                                                echo 'Tomorrow';
                                            } else {
                                                echo $session_date->format('M j');
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <div class="session-details">
                                        <h4><?php echo htmlspecialchars($session['session_name']); ?></h4>
                                        <p>
                                            <?php echo htmlspecialchars($session['location']); ?> • 
                                            <?php echo htmlspecialchars($session['class_name']); ?> 
                                            (<?php echo htmlspecialchars($session['class_code']); ?>)
                                        </p>
                                        <small class="session-type">
                                            <i class="fas fa-tag"></i> 
                                            <?php echo ucfirst($session['session_type']); ?>
                                        </small>
                                    </div>
                                    <div class="session-status">
                                        <?php
                                        $current_time = new DateTime();
                                        $session_start = new DateTime($session['session_date'] . ' ' . $session['start_time']);
                                        $session_end = new DateTime($session['session_date'] . ' ' . $session['end_time']);
                                        
                                        if ($current_time >= $session_start && $current_time <= $session_end) {
                                            $status_class = 'in-progress';
                                            $status_text = 'In Progress';
                                        } elseif ($current_time < $session_start) {
                                            $status_class = 'scheduled';
                                            $status_text = 'Scheduled';
                                        } else {
                                            $status_class = 'completed';
                                            $status_text = 'Completed';
                                        }
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <p>No upcoming sessions scheduled</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        // Form validation and enhancement
        document.addEventListener('DOMContentLoaded', function() {
            const startTimeInput = document.getElementById('start_time');
            const endTimeInput = document.getElementById('end_time');
            
            // Validate end time is after start time
            function validateTimes() {
                if (startTimeInput.value && endTimeInput.value) {
                    if (startTimeInput.value >= endTimeInput.value) {
                        endTimeInput.setCustomValidity('End time must be after start time');
                    } else {
                        endTimeInput.setCustomValidity('');
                    }
                }
            }
            
            startTimeInput.addEventListener('change', validateTimes);
            endTimeInput.addEventListener('change', validateTimes);
            
            // Auto-populate session name based on class selection
            const classSelect = document.getElementById('class_id');
            const sessionNameInput = document.getElementById('session_name');
            const sessionTypeSelect = document.getElementById('session_type');
            
            classSelect.addEventListener('change', function() {
                if (this.value && !sessionNameInput.value) {
                    const selectedOption = this.options[this.selectedIndex];
                    const className = selectedOption.text.split(' (')[0];
                    if (sessionTypeSelect.value) {
                        sessionNameInput.value = `${className} - ${sessionTypeSelect.value.charAt(0).toUpperCase() + sessionTypeSelect.value.slice(1)}`;
                    }
                }
            });
            
            sessionTypeSelect.addEventListener('change', function() {
                if (classSelect.value && this.value) {
                    const selectedOption = classSelect.options[classSelect.selectedIndex];
                    const className = selectedOption.text.split(' (')[0];
                    sessionNameInput.value = `${className} - ${this.value.charAt(0).toUpperCase() + this.value.slice(1)}`;
                }
            });
        });
    </script>
</body>
</html>
