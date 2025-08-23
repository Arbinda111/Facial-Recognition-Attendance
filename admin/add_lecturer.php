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

// Handle lecturer registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Validate input
    if (empty($name) || empty($email) || empty($password)) {
        $error_message = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password must be at least 6 characters long.';
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM lecturers WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $error_message = 'Email already exists. Please use a different email.';
            } else {
                // Hash password and insert lecturer
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("INSERT INTO lecturers (name, email, password) VALUES (?, ?, ?)");
                $stmt->execute([$name, $email, $hashed_password]);
                
                $success_message = 'Lecturer registered successfully!';
                
                // Clear form
                $name = $email = $password = '';
            }
        } catch (PDOException $e) {
            $error_message = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get all lecturers
$stmt = $pdo->prepare("SELECT * FROM lecturers ORDER BY created_at DESC");
$stmt->execute();
$lecturers = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Lecturer - Full Attend</title>
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
                    <h1>Lecturer Management</h1>
                    <p>Add and manage teaching staff</p>
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

            <!-- Registration Form Section -->
            <div class="register-container">
                <div class="register-right">
                    <div class="form-tabs">
                        <button class="tab-btn active">REGISTER</button>
                    </div>
                    
                    <div class="register-form">
                        <h2>REGISTER NEW LECTURER</h2>
                        
                        <?php if (!empty($success_message)): ?>
                            <div class="success-message">
                                <i class="fas fa-check-circle"></i>
                                <?php echo htmlspecialchars($success_message); ?>
                            </div>
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
                                    <input type="text" id="name" name="name" placeholder="Dr. John Smith" 
                                           value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">LECTURER E-MAIL</label>
                                <div class="input-container">
                                    <input type="email" id="email" name="email" placeholder="lecturer@cihe.edu" 
                                           value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="password">PASSWORD</label>
                                <div class="input-container">
                                    <input type="password" id="password" name="password" placeholder="Create Password" required>
                                </div>
                            </div>
                            
                            <button type="submit" class="create-account-btn">CREATE ACCOUNT</button>
                        </form>
                        
                    </div>
                </div>
            </div>

            
            <!-- Lecturers List Section -->
            <div class="table-container">
                <div class="table-header">
                    <h3><i class="fas fa-chalkboard-teacher"></i> Registered Lecturers</h3>
                    <div class="search-filter">
                        <input type="text" class="search-input" placeholder="Search lecturers..." id="searchLecturers">
                        <select class="filter-select" id="statusFilter">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="table-content">
                    <?php if (empty($lecturers)): ?>
                        <div class="empty-state">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <p>No lecturers registered yet</p>
                            <small>Register the first lecturer using the form above</small>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lecturers as $lecturer): ?>
                                    <tr>
                                        <td>
                                            <span class="lecturer-id">L<?php echo str_pad($lecturer['id'], 3, '0', STR_PAD_LEFT); ?></span>
                                        </td>
                                        <td>
                                            <div class="lecturer-info">
                                                <div class="lecturer-avatar">
                                                    <?php echo strtoupper(substr($lecturer['name'], 0, 2)); ?>
                                                </div>
                                                <div class="lecturer-details">
                                                    <span class="lecturer-name"><?php echo htmlspecialchars($lecturer['name']); ?></span>
                                                    <small>Teaching Staff</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($lecturer['email']); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $lecturer['status'] ?? 'active'; ?>">
                                                <?php echo ucfirst($lecturer['status'] ?? 'active'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($lecturer['created_at'])); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-sm" onclick="editLecturer(<?php echo $lecturer['id']; ?>)" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm <?php echo ($lecturer['status'] ?? 'active') === 'active' ? 'btn-warning' : 'btn-success'; ?>" 
                                                        onclick="toggleStatus(<?php echo $lecturer['id']; ?>)" 
                                                        title="<?php echo ($lecturer['status'] ?? 'active') === 'active' ? 'Deactivate' : 'Activate'; ?>">
                                                    <i class="fas fa-toggle-<?php echo ($lecturer['status'] ?? 'active') === 'active' ? 'on' : 'off'; ?>"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchLecturers').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.data-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        // Status filter
        document.getElementById('statusFilter').addEventListener('change', function() {
            const filterValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('.data-table tbody tr');
            
            rows.forEach(row => {
                if (filterValue === '') {
                    row.style.display = '';
                } else {
                    const statusBadge = row.querySelector('.status-badge');
                    const status = statusBadge.textContent.toLowerCase();
                    row.style.display = status.includes(filterValue) ? '' : 'none';
                }
            });
        });

        function editLecturer(lecturerId) {
            // Implement edit functionality
            alert('Edit functionality to be implemented for lecturer ID: ' + lecturerId);
        }

        function toggleStatus(lecturerId) {
            if (confirm('Are you sure you want to change the lecturer status?')) {
                // AJAX call to toggle status
                fetch('toggle_lecturer_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ lecturer_id: lecturerId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error updating status: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Network error occurred');
                });
            }
        }
    </script>
</body>
</html>