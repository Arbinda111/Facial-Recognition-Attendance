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
                $subject_id = intval($_POST['subject_id']);
                $class_code = trim($_POST['class_code']);
                $description = trim($_POST['description']);
                $lecturer_name = trim($_POST['lecturer_name']);
                $lecturer_id = intval($_POST['lecturer_id']);
                $semester = trim($_POST['semester']);
                $academic_year = trim($_POST['academic_year']);

                // Get subject name for class_name
                $class_name = '';
                if ($subject_id) {
                    $stmt_sub = $pdo->prepare("SELECT subject_name FROM subjects WHERE id = ?");
                    $stmt_sub->execute([$subject_id]);
                    $class_name = $stmt_sub->fetchColumn();
                }

                if (empty($class_name) || empty($class_code) || empty($lecturer_name) || empty($lecturer_id)) {
                    $error_message = 'Subject, class code, and lecturer are required fields.';
                } else {
                    try {
                        // Insert subject_id and lecturer_id into classes table
                        $stmt = $pdo->prepare("INSERT INTO classes (subject_id, lecturer_id, class_name, class_code, description, instructor_name, semester, academic_year, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
                        $stmt->execute([$subject_id, $lecturer_id, $class_name, $class_code, $description, $lecturer_name, $semester, $academic_year]);
                        $success_message = "Class for subject '$class_name' and lecturer '$lecturer_name' has been successfully created.";
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
    $instructors = ['Dr. Smith', 'Prof. Johnson', 'Dr. Wilson', 'Dr. Brown', 'Prof. Davis'];
}

// Get subjects for dropdown (only those with a lecturer assigned)
$subjects = [];
$subjectLecturerMap = [];
try {
    $stmt = $pdo->query("SELECT s.id, s.subject_name, s.subject_code, l.id as lecturer_id, l.name as lecturer_name 
                         FROM subjects s 
                         INNER JOIN lecturer_subjects ls ON s.id = ls.subject_id 
                         INNER JOIN lecturers l ON ls.lecturer_id = l.id 
                         WHERE s.status = 'active' 
                         ORDER BY s.subject_name");
    while ($row = $stmt->fetch()) {
        $subjects[] = [
            'id' => $row['id'],
            'subject_name' => $row['subject_name'],
            'subject_code' => $row['subject_code']
        ];
        $subjectLecturerMap[$row['id']] = [
            'lecturer_id' => $row['lecturer_id'],
            'lecturer_name' => $row['lecturer_name']
        ];
    }
} catch (PDOException $e) {
    $subjects = [];
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
    <style>
        .admin-main {
            flex: 1;
            padding: 20px;
            max-width: 100%;
            overflow-x: hidden;
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
                
                <!-- <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_enrolled; ?></h3>
                        <p>Enrolled Students</p>
                    </div>
                </div> -->
                
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
                        <button type="button" class="btn-secondary" id="toggleForm" onclick="toggleForm()">
                            <i class="fas fa-plus"></i>
                            Add Class
                        </button>
                    </div>
                    
                    <div class="form-container" id="addClassForm" style="display: none;">
                        <form method="POST" action="" class="class-form">
                            <input type="hidden" name="action" value="add_class">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="subject_id"><i class="fas fa-book"></i> Subject</label>
                                    <select id="subject_id" name="subject_id" required onchange="updateLecturer()">
                                        <option value="">Select Subject</option>
                                        <?php foreach ($subjects as $sub): ?>
                                            <option value="<?php echo $sub['id']; ?>">
                                                <?php echo htmlspecialchars($sub['subject_name']) . ' (' . htmlspecialchars($sub['subject_code']) . ')'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="class_code"><i class="fas fa-code"></i> Class Code</label>
                                    <input type="text" id="class_code" name="class_code" 
                                           placeholder="e.g., CS201" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="lecturer_name"><i class="fas fa-user-tie"></i> Lecturer</label>
                                    <input type="text" id="lecturer_name" name="lecturer_name" readonly required placeholder="Select subject first">
                                    <input type="hidden" id="lecturer_id" name="lecturer_id">
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
                    
                    <div class="table-content">
                        <?php if (count($classes) > 0): ?>
                        <table class="classes-table" id="classesTable">
                            <thead>
                                <tr>
                                    <th>Subject Name</th>
                                    <th>Subject Code</th>
                                    <th>Class Name</th>
                                    <th>Class Code</th>
                                    <th>Description</th>
                                    <th>Instructor</th>
                                    <th>Semester</th>
                                    <th>Academic Year</th>
                                    <th>Status</th>
                                    <th>Enrolled</th>
                                    <th>Sessions</th>
                                    <th>Upcoming</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($classes as $class): ?>
                                <tr>
                                    <?php
                                    $subjectInfo = null;
                                    foreach ($subjects as $subject) {
                                        if ($subject['id'] == $class['subject_id']) {
                                            $subjectInfo = $subject;
                                            break;
                                        }
                                    }
                                    ?>
                                    <td><?php echo $subjectInfo ? htmlspecialchars($subjectInfo['subject_name']) : '-'; ?></td>
                                    <td><?php echo $subjectInfo ? htmlspecialchars($subjectInfo['subject_code']) : '-'; ?></td>
                                    <td><?php echo htmlspecialchars($class['class_name']); ?></td>
                                    <td><?php echo htmlspecialchars($class['class_code']); ?></td>
                                    <td><?php echo htmlspecialchars($class['description']); ?></td>
                                    <td><?php echo htmlspecialchars($class['instructor_name']); ?></td>
                                    <td><?php echo htmlspecialchars($class['semester']); ?></td>
                                    <td><?php echo htmlspecialchars($class['academic_year']); ?></td>
                                    <td><span class="status-badge <?php echo strtolower($class['status']); ?>"><?php echo ucfirst($class['status']); ?></span></td>
                                    <td><?php echo (int)$class['enrolled_count']; ?></td>
                                    <td><?php echo (int)$class['total_sessions']; ?></td>
                                    <td><?php echo (int)$class['upcoming_sessions']; ?></td>
                                    <td>
                                        <div class="action-buttons">
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
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </main>
    </div>
    
    <script>
        // Create the subject-lecturer mapping from PHP to JavaScript
        const subjectLecturerMap = <?php echo json_encode($subjectLecturerMap); ?>;
        
        function updateLecturer() {
            const subjectSelect = document.getElementById('subject_id');
            const lecturerInput = document.getElementById('lecturer_name');
            const lecturerIdInput = document.getElementById('lecturer_id');
            
            const subjectId = subjectSelect.value;
            
            if (subjectId && subjectLecturerMap[subjectId]) {
                lecturerInput.value = subjectLecturerMap[subjectId].lecturer_name;
                lecturerIdInput.value = subjectLecturerMap[subjectId].lecturer_id;
            } else {
                lecturerInput.value = '';
                lecturerIdInput.value = '';
            }
        }
        
        function toggleForm() {
            const form = document.getElementById('addClassForm');
            const toggleBtn = document.getElementById('toggleForm');
            if (form.style.display === 'none') {
                form.style.display = 'block';
                toggleBtn.innerHTML = '<i class="fas fa-times"></i> Cancel';
            } else {
                form.style.display = 'none';
                toggleBtn.innerHTML = '<i class="fas fa-plus"></i> Add Class';
                document.querySelector('.class-form').reset();
                document.getElementById('lecturer_name').value = '';
                document.getElementById('lecturer_id').value = '';
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
            const subjectId = document.getElementById('subject_id').value.trim();
            const classCode = document.getElementById('class_code').value.trim();
            const lecturer = document.getElementById('lecturer_name').value.trim();
            
            if (!subjectId || !classCode || !lecturer) {
                e.preventDefault();
                alert('Please fill in all required fields: Subject, Class Code, and Lecturer.');
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