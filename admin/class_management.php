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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_class':
                $class_name = trim($_POST['class_name']);
                $class_code = trim($_POST['class_code']);
                $description = trim($_POST['description']);
                $instructor_name = trim($_POST['instructor_name']);
                $semester = trim($_POST['semester']);
                $academic_year = trim($_POST['academic_year']);
                
                if (empty($class_name) || empty($class_code) || empty($instructor_name)) {
                    $error_message = 'Class name, class code, and instructor are required fields.';
                } else {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO classes (class_name, class_code, description, instructor_name, semester, academic_year) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$class_name, $class_code, $description, $instructor_name, $semester, $academic_year]);
                        $success_message = "Class '$class_name' has been successfully created.";
                    } catch (PDOException $e) {
                        if ($e->getCode() == '23000') {
                            $error_message = 'Class code already exists. Please use a different code.';
                        } else {
                            $error_message = 'Error creating class: ' . $e->getMessage();
                        }
                    }
                }
                break;
                
            case 'edit_class':
                $class_id = (int)$_POST['class_id'];
                $class_name = trim($_POST['class_name']);
                $class_code = trim($_POST['class_code']);
                $description = trim($_POST['description']);
                $instructor_name = trim($_POST['instructor_name']);
                $semester = trim($_POST['semester']);
                $academic_year = trim($_POST['academic_year']);
                $status = $_POST['status'];
                
                if (empty($class_name) || empty($class_code) || empty($instructor_name)) {
                    $error_message = 'Class name, class code, and instructor are required fields.';
                } else {
                    try {
                        $stmt = $pdo->prepare("UPDATE classes SET class_name = ?, class_code = ?, description = ?, instructor_name = ?, semester = ?, academic_year = ?, status = ? WHERE id = ?");
                        $stmt->execute([$class_name, $class_code, $description, $instructor_name, $semester, $academic_year, $status, $class_id]);
                        $success_message = "Class details have been updated successfully.";
                    } catch (PDOException $e) {
                        if ($e->getCode() == '23000') {
                            $error_message = 'Class code already exists. Please use a different code.';
                        } else {
                            $error_message = 'Error updating class: ' . $e->getMessage();
                        }
                    }
                }
                break;
                
            case 'delete_class':
                $class_id = (int)$_POST['class_id'];
                try {
                    $stmt = $pdo->prepare("UPDATE classes SET status = 'inactive' WHERE id = ?");
                    $stmt->execute([$class_id]);
                    $success_message = "Class has been deactivated successfully.";
                } catch (PDOException $e) {
                    $error_message = 'Error deactivating class: ' . $e->getMessage();
                }
                break;
                
            case 'toggle_status':
                $class_id = (int)$_POST['class_id'];
                $new_status = $_POST['new_status'];
                try {
                    $stmt = $pdo->prepare("UPDATE classes SET status = ? WHERE id = ?");
                    $stmt->execute([$new_status, $class_id]);
                    $success_message = "Class status updated successfully.";
                } catch (PDOException $e) {
                    $error_message = 'Error updating class status: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Fetch classes from database with enrollment counts
$classes = [];
try {
    $stmt = $pdo->query("
        SELECT 
            c.*,
            COUNT(DISTINCT se.student_id) as enrolled_count,
            COUNT(DISTINCT s.id) as total_sessions,
            COUNT(DISTINCT CASE WHEN s.session_date >= CURDATE() THEN s.id END) as upcoming_sessions
        FROM classes c
        LEFT JOIN student_enrollments se ON c.id = se.class_id AND se.status = 'enrolled'
        LEFT JOIN sessions s ON c.id = s.class_id
        GROUP BY c.id
        ORDER BY c.created_at DESC
    ");
    $classes = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = 'Error fetching classes: ' . $e->getMessage();
}

// Get unique instructors for dropdown
$instructors = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT instructor_name FROM classes WHERE instructor_name IS NOT NULL ORDER BY instructor_name");
    $instructors = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Use default instructors if query fails
    $instructors = ['Dr. Smith', 'Prof. Johnson', 'Dr. Wilson', 'Dr. Brown', 'Prof. Davis'];
}

// Calculate statistics
$total_classes = count($classes);
$total_enrolled = array_sum(array_column($classes, 'enrolled_count'));
$total_instructors = count(array_unique(array_column($classes, 'instructor_name')));
$active_classes = count(array_filter($classes, function($class) { return $class['status'] === 'active'; }));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Management - Full Attend</title>
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
                    <h1>Class Management</h1>
                    <p>Manage classes, schedules, and assignments</p>
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
            
            <!-- Class Statistics -->
            <section class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_classes; ?></h3>
                        <p>Total Classes</p>
                    </div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_enrolled; ?></h3>
                        <p>Enrolled Students</p>
                    </div>
                </div>
                
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_instructors; ?></h3>
                        <p>Instructors</p>
                    </div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $active_classes; ?></h3>
                        <p>Active Classes</p>
                    </div>
                </div>
            </section>
            
            <!-- Add Class Form -->
            <section class="add-class-section" style="margin-bottom: 30px;">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-plus-circle"></i> Add New Class</h3>
                        <button type="button" class="btn-secondary" id="toggleForm">
                            <i class="fas fa-plus"></i>
                            Add Class
                        </button>
                    </div>
                    
                    <div class="form-container" id="addClassForm" style="display: none;">
                        <form method="POST" action="" class="class-form">
                            <input type="hidden" name="action" value="add_class">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="class_name"><i class="fas fa-book"></i> Class Name</label>
                                    <input type="text" id="class_name" name="class_name" 
                                           placeholder="e.g., Data Structures and Algorithms" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="class_code"><i class="fas fa-code"></i> Class Code</label>
                                    <input type="text" id="class_code" name="class_code" 
                                           placeholder="e.g., CS201" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="instructor_name"><i class="fas fa-user-tie"></i> Instructor</label>
                                    <input type="text" id="instructor_name" name="instructor_name" 
                                           placeholder="e.g., Dr. Smith" list="instructors-list" required>
                                    <datalist id="instructors-list">
                                        <?php foreach ($instructors as $instr): ?>
                                            <option value="<?php echo htmlspecialchars($instr); ?>">
                                        <?php endforeach; ?>
                                    </datalist>
                                </div>
                                
                                <div class="form-group">
                                    <label for="semester"><i class="fas fa-calendar-alt"></i> Semester</label>
                                    <select id="semester" name="semester">
                                        <option value="">Select Semester</option>
                                        <option value="Fall 2025">Fall 2025</option>
                                        <option value="Spring 2026">Spring 2026</option>
                                        <option value="Summer 2026">Summer 2026</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="academic_year"><i class="fas fa-graduation-cap"></i> Academic Year</label>
                                    <select id="academic_year" name="academic_year">
                                        <option value="">Select Academic Year</option>
                                        <option value="2025-26">2025-26</option>
                                        <option value="2026-27">2026-27</option>
                                    </select>
                                </div>
                                
                                <div class="form-group full-width">
                                    <label for="description"><i class="fas fa-align-left"></i> Description</label>
                                    <textarea id="description" name="description" rows="3" 
                                              placeholder="Optional class description..."></textarea>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="button" class="btn-secondary" onclick="toggleForm()">
                                    <i class="fas fa-times"></i>
                                    Cancel
                                </button>
                                <button type="submit" class="btn-primary">
                                    <i class="fas fa-plus"></i>
                                    Create Class
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
            
            <!-- Classes Table -->
            <section class="classes-table-section">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-table"></i> All Classes (<?php echo count($classes); ?>)</h3>
                        <div class="header-actions">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="searchInput" placeholder="Search classes..." onkeyup="searchClasses()">
                            </div>
                           
                        </div>
                    </div>
                    
                    <div class="table-container">
                        <?php if (count($classes) > 0): ?>
                        <table class="classes-table" id="classesTable">
                            <thead>
                                <tr>
                                    <th>Class Info</th>
                                    <th>Instructor</th>
                                    <th>Semester</th>
                                    <th>Enrolled</th>
                                    <th>Sessions</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($classes as $class): ?>
                                <tr>
                                    <td>
                                        <div class="class-info">
                                            <strong><?php echo htmlspecialchars($class['class_name']); ?></strong>
                                            <div class="class-code"><?php echo htmlspecialchars($class['class_code']); ?></div>
                                            <?php if ($class['description']): ?>
                                                <small class="class-description"><?php echo htmlspecialchars(substr($class['description'], 0, 50)) . '...'; ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="instructor-info">
                                            <i class="fas fa-user-tie"></i>
                                            <?php echo htmlspecialchars($class['instructor_name']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="semester-info">
                                            <?php if ($class['semester']): ?>
                                                <span class="semester"><?php echo htmlspecialchars($class['semester']); ?></span>
                                            <?php endif; ?>
                                            <?php if ($class['academic_year']): ?>
                                                <div class="academic-year"><?php echo htmlspecialchars($class['academic_year']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="enrollment-info">
                                            <span class="enrolled-count">
                                                <i class="fas fa-users"></i>
                                                <?php echo $class['enrolled_count']; ?> students
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="session-info">
                                            <span class="session-count">
                                                <i class="fas fa-calendar"></i>
                                                <?php echo $class['total_sessions']; ?> total
                                            </span>
                                            <?php if ($class['upcoming_sessions'] > 0): ?>
                                                <div class="upcoming-sessions">
                                                    <small><?php echo $class['upcoming_sessions']; ?> upcoming</small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo strtolower($class['status']); ?>">
                                            <?php echo ucfirst($class['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-icon" title="View Details" onclick="viewClass(<?php echo $class['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn-icon" title="Edit Class" onclick="editClass(<?php echo $class['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-icon" title="Manage Students" onclick="manageStudents(<?php echo $class['id']; ?>)">
                                                <i class="fas fa-users"></i>
                                            </button>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to toggle this class status?')">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                                                <input type="hidden" name="new_status" value="<?php echo $class['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                                <button type="submit" class="btn-icon <?php echo $class['status'] === 'active' ? 'warning' : 'success'; ?>" 
                                                        title="<?php echo $class['status'] === 'active' ? 'Deactivate' : 'Activate'; ?> Class">
                                                    <i class="fas fa-<?php echo $class['status'] === 'active' ? 'pause' : 'play'; ?>"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <h3>No Classes Found</h3>
                            <p>Create your first class to get started with managing your courses.</p>
                            <button class="btn-primary" onclick="toggleForm()">
                                <i class="fas fa-plus"></i>
                                Add First Class
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </main>
    </div>
    
    <script>
        function toggleForm() {
            const form = document.getElementById('addClassForm');
            const toggleBtn = document.getElementById('toggleForm');
            
            if (form.style.display === 'none') {
                form.style.display = 'block';
                toggleBtn.innerHTML = '<i class="fas fa-times"></i> Cancel';
                // Focus on first input
                document.getElementById('class_name').focus();
            } else {
                form.style.display = 'none';
                toggleBtn.innerHTML = '<i class="fas fa-plus"></i> Add Class';
                // Reset form
                document.querySelector('.class-form').reset();
            }
        }
        
        function searchClasses() {
            const searchInput = document.getElementById('searchInput');
            const filter = searchInput.value.toLowerCase();
            const table = document.getElementById('classesTable');
            
            if (!table) return;
            
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) {
                let row = rows[i];
                let shouldShow = false;
                let cells = row.getElementsByTagName('td');
                
                for (let j = 0; j < cells.length; j++) {
                    let cell = cells[j];
                    if (cell.textContent.toLowerCase().indexOf(filter) > -1) {
                        shouldShow = true;
                        break;
                    }
                }
                
                row.style.display = shouldShow ? '' : 'none';
            }
        }
        
        function viewClass(classId) {
            // Navigate to class details page
            window.location.href = `class_details.php?id=${classId}`;
        }
        
        function editClass(classId) {
            // Navigate to edit class page
            window.location.href = `edit_class.php?id=${classId}`;
        }
        
        function manageStudents(classId) {
            // Navigate to student management page for this class
            window.location.href = `class_students.php?id=${classId}`;
        }
        
        // Auto-generate class code based on class name
        document.getElementById('class_name').addEventListener('input', function() {
            const className = this.value;
            const classCodeInput = document.getElementById('class_code');
            
            if (className && !classCodeInput.value) {
                // Generate a simple class code
                const words = className.split(' ');
                let code = '';
                
                words.forEach(word => {
                    if (word.length > 0) {
                        code += word.charAt(0).toUpperCase();
                    }
                });
                
                // Add some numbers if code is too short
                if (code.length < 3) {
                    code += '101';
                } else {
                    code += Math.floor(Math.random() * 900) + 100;
                }
                
                classCodeInput.value = code;
            }
        });
        
        // Form validation
        document.querySelector('.class-form').addEventListener('submit', function(e) {
            const className = document.getElementById('class_name').value.trim();
            const classCode = document.getElementById('class_code').value.trim();
            const instructor = document.getElementById('instructor_name').value.trim();
            
            if (!className || !classCode || !instructor) {
                e.preventDefault();
                alert('Please fill in all required fields: Class Name, Class Code, and Instructor.');
                return false;
            }
            
            // Validate class code format
            if (!/^[A-Z]{2,4}\d{3}$/.test(classCode)) {
                e.preventDefault();
                alert('Class code should be in format like "CS101" or "MATH201".');
                return false;
            }
        });
        
        // Initialize event listeners
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('toggleForm').addEventListener('click', toggleForm);
            
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 300);
                }, 5000);
            });
        });
    </script>
</body>
</html>
