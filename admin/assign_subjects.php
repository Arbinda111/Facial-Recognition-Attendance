<?php
// admin/assign_subjects.php
require_once '../config/database.php';

// Fetch lecturers
$lecturers = [];
$subjects = [];
$students = [];
$conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) { die('Connection failed: ' . $conn->connect_error); }

$subject_success = '';
$subject_error = '';

// Handle subject creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subject'])) {
    $subject_name = trim($_POST['subject_name']);
    $subject_code = trim($_POST['subject_code']);
    $description = trim($_POST['description']);
    if (empty($subject_name) || empty($subject_code)) {
        $subject_error = 'Subject name and code are required.';
    } else {
        $stmt = $conn->prepare("INSERT INTO subjects (subject_name, subject_code, description) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $subject_name, $subject_code, $description);
        if ($stmt->execute()) {
            $subject_success = 'Subject added successfully!';
        } else {
            $subject_error = 'Error adding subject: ' . $conn->error;
        }
        $stmt->close();
    }
}

// Get lecturers

$lq = $conn->query("SELECT id, name, email FROM lecturers WHERE status='active'");
if ($lq === false) {
    die('Lecturer query error: ' . $conn->error);
}
while ($row = $lq->fetch_assoc()) { $lecturers[] = $row; }

// Get subjects

$sq = $conn->query("SELECT id, subject_name, subject_code FROM subjects WHERE status='active'");
if ($sq === false) {
    die('Subject query error: ' . $conn->error);
}
while ($row = $sq->fetch_assoc()) { $subjects[] = $row; }

// Get students
$stq = $conn->query("SELECT id, name, student_id FROM students WHERE status='active'");
while ($row = $stq->fetch_assoc()) { $students[] = $row; }

// Handle lecturer-subject assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_subject'])) {
    $lecturer_id = intval($_POST['lecturer_id']);
    $subject_id = intval($_POST['subject_id']);
    if ($lecturer_id && $subject_id) {
        $stmt = $conn->prepare("INSERT IGNORE INTO lecturer_subjects (lecturer_id, subject_id) VALUES (?, ?)");
        $stmt->bind_param('ii', $lecturer_id, $subject_id);
        if ($stmt->execute()) {
            $success = 'Subject assigned to lecturer.';
        } else {
            $error_message = 'Error assigning subject: ' . $conn->error;
        }
        $stmt->close();
    } else {
        $error_message = 'Please select both a lecturer and a subject.';
    }
}

// Handle student enrollment to subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_students'])) {
    $subject_id = intval($_POST['subject_id']);
    $student_ids = $_POST['student_ids'] ?? [];
    
    if (empty($student_ids)) {
        $subject_error = 'Please select at least one student to enroll.';
    } else {
        // Find the lecturer assigned to this subject
        $lecturer_stmt = $conn->prepare("SELECT lecturer_id FROM lecturer_subjects WHERE subject_id = ? LIMIT 1");
        $lecturer_stmt->bind_param('i', $subject_id);
        $lecturer_stmt->execute();
        $lecturer_stmt->bind_result($lecturer_id);
        $lecturer_found = $lecturer_stmt->fetch();
        $lecturer_stmt->close();

        $enrolled_count = 0;
        foreach ($student_ids as $sid) {
            $sid = intval($sid);
            // Insert into lecturer_student_enrollments (no class_id used)
            if ($lecturer_found && $lecturer_id) {
                $lse_stmt = $conn->prepare("INSERT IGNORE INTO lecturer_student_enrollments (lecturer_id, student_id, subject_id) VALUES (?, ?, ?)");
                $lse_stmt->bind_param('iii', $lecturer_id, $sid, $subject_id);
                if ($lse_stmt->execute()) {
                    $enrolled_count++;
                }
                $lse_stmt->close();
            }
        }
        
        if ($enrolled_count > 0) {
            $subject_success = "Successfully enrolled $enrolled_count student(s) to the subject!";
        } else {
            $subject_error = 'No students were enrolled. They may already be enrolled in this subject.';
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Assign Subjects to Lecturer</title>
    <link rel="stylesheet" href="admin_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="admin-container">
    <?php include 'includes/sidebar.php'; ?>
    <main style="flex:1; margin-left:280px; padding:40px 30px; background:#f1f5f9; min-height:100vh;">
        <div style="max-width:1100px; margin:auto;">
             <header class="dashboard-header">
                <div class="header-left">
                    <h1>Subject Assignment</h1>
                    <p>Assign and manage subjects for lecturers and students</p>
                    <?php if (!empty($subject_success)): ?>
                        <div class="alert alert-success" style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 8px; margin-top: 15px;">
                            <i class="fas fa-check-circle"></i>
                            <?php echo htmlspecialchars($subject_success); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($subject_error)): ?>
                        <div class="alert alert-error" style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 8px; margin-top: 15px;">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo htmlspecialchars($subject_error); ?>
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
            <!-- Add Subject Section -->
            <div style="background:#fff; border-radius:18px; box-shadow:0 2px 8px rgba(123,47,242,0.08); padding:32px; margin-bottom:32px;">
                <h2 style="color:#6366f1; font-size:1.5rem; font-weight:700; margin-bottom:18px; text-align:center;">Add New Subject</h2>
                <?php if (!empty($subject_success)): ?>
                    <div class="alert alert-success" style="color:#22c55e; text-align:center; margin-bottom:16px; font-weight:600;"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($subject_success) ?></div>
                <?php endif; ?>
                <?php if (!empty($subject_error)): ?>
                    <div class="alert alert-error" style="color:#ef4444; text-align:center; margin-bottom:16px; font-weight:600;"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($subject_error) ?></div>
                <?php endif; ?>
                <form method="post" style="max-width:500px; margin:auto;">
                    <div style="margin-bottom:18px;">
                        <label for="subject_name" style="font-weight:600; color:#222;">Subject Name:</label><br>
                        <input type="text" name="subject_name" id="subject_name" required style="width:100%; padding:12px; border-radius:8px; border:1px solid #ccc; font-size:1rem; margin-top:6px;">
                    </div>
                    <div style="margin-bottom:18px;">
                        <label for="subject_code" style="font-weight:600; color:#222;">Subject Code:</label><br>
                        <input type="text" name="subject_code" id="subject_code" required style="width:100%; padding:12px; border-radius:8px; border:1px solid #ccc; font-size:1rem; margin-top:6px;">
                    </div>
                    <div style="margin-bottom:18px;">
                        <label for="description" style="font-weight:600; color:#222;">Description (optional):</label><br>
                        <textarea name="description" id="description" rows="2" style="width:100%; padding:12px; border-radius:8px; border:1px solid #ccc; font-size:1rem; margin-top:6px;"></textarea>
                    </div>
                    <button type="submit" name="add_subject" style="width:100%; background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color:#fff; padding:14px 0; border:none; border-radius:8px; font-weight:700; font-size:1.1rem; cursor:pointer; margin-top:10px;">Add Subject</button>
                </form>
            </div>
           
            <!-- Main Card Section -->
            <div style="background:#fff; border-radius:18px; box-shadow:0 2px 8px rgba(123,47,242,0.08); padding:32px;">
                <?php if (!empty($success)) echo "<p style='color:green; font-weight:bold; text-align:center;'>$success</p>"; ?>
                <h2 style="color:#7b2ff2; font-size:1.5rem; font-weight:700; margin-bottom:18px; text-align:center;">Assign Subject to Lecturer</h2>
                <form method="post" style="margin-bottom:32px; max-width:500px; margin:auto;">
                    <div style="margin-bottom:18px;">
                        <label for="lecturer_id" style="font-weight:600; color:#222;">Select Lecturer:</label><br>
                        <select name="lecturer_id" id="lecturer_id" required style="width:100%; padding:12px; border-radius:8px; border:1px solid #ccc; font-size:1rem; margin-top:6px;">
                            <option value="">--Select--</option>
                            <?php foreach ($lecturers as $lec): ?>
                                <option value="<?= $lec['id'] ?>"><?= htmlspecialchars($lec['name']) ?> (<?= htmlspecialchars($lec['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="margin-bottom:18px;">
                        <label for="subject_id" style="font-weight:600; color:#222;">Select Subject:</label><br>
                        <select name="subject_id" id="subject_id" required style="width:100%; padding:12px; border-radius:8px; border:1px solid #ccc; font-size:1rem; margin-top:6px;">
                            <option value="">--Select--</option>
                            <?php foreach ($subjects as $sub): ?>
                                <option value="<?= $sub['id'] ?>"><?= htmlspecialchars($sub['subject_name']) ?> (<?= htmlspecialchars($sub['subject_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="assign_subject" style="width:100%; background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color:#fff; padding:14px 0; border:none; border-radius:8px; font-weight:700; font-size:1.1rem; cursor:pointer; margin-top:10px;">Assign Subject</button>
                </form>
                <hr style="margin:32px 0; border:0; border-top:1px solid #eee;">
                <h2 style="color:#7b2ff2; font-size:1.5rem; font-weight:700; margin-bottom:18px; text-align:center;">Enroll Students to Subject</h2>
                <form method="post" style="max-width:500px; margin:auto;">
                    <div style="margin-bottom:18px;">
                        <label for="subject_id" style="font-weight:600; color:#222;">Select Subject:</label><br>
                        <select name="subject_id" id="subject_id" required style="width:100%; padding:12px; border-radius:8px; border:1px solid #ccc; font-size:1rem; margin-top:6px;">
                            <option value="">--Select--</option>
                            <?php foreach ($subjects as $sub): ?>
                                <option value="<?= $sub['id'] ?>"><?= htmlspecialchars($sub['subject_name']) ?> (<?= htmlspecialchars($sub['subject_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="margin-bottom:18px;">
                        <label style="font-weight:600; color:#222;">Select Students:</label><br>
                        <div style="max-height:200px; overflow-y:auto; border:1px solid #eee; border-radius:8px; padding:10px; background:#fafafa; margin-top:6px;">
                            <?php foreach ($students as $stu): ?>
                                <label style="display:block; margin-bottom:8px; font-size:1rem;">
                                    <input type="checkbox" name="student_ids[]" value="<?= $stu['id'] ?>"> <?= htmlspecialchars($stu['name']) ?> (<?= htmlspecialchars($stu['student_id']) ?>)
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button type="submit" name="enroll_students" style="width:100%; background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color:#fff; padding:14px 0; border:none; border-radius:8px; font-weight:700; font-size:1.1rem; cursor:pointer; margin-top:10px;">Enroll Students</button>
                </form>
            </div>
        </div>
    </main>
</div>
</body>
</html>

        <br>
        <button type="submit" name="enroll_students">Enroll Students</button>
    </form>
</body>
</html>
