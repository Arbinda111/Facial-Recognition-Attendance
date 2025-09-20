<?php
session_start();
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit();
}

// Get database connection
$pdo = getDBConnection();

// Handle AJAX requests for student actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'delete_student':
                $student_id = $_POST['student_id'];
                $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
                $result = $stmt->execute([$student_id]);
                echo json_encode(['success' => $result]);
                break;
                
            case 'toggle_status':
                $student_id = $_POST['student_id'];
                $current_status = $_POST['current_status'];
                $new_status = $current_status === 'active' ? 'inactive' : 'active';
                $stmt = $pdo->prepare("UPDATE students SET status = ? WHERE id = ?");
                $result = $stmt->execute([$new_status, $student_id]);
                echo json_encode(['success' => $result, 'new_status' => $new_status]);
                break;
                
            case 'bulk_delete':
                $student_ids = json_decode($_POST['student_ids'], true);
                $placeholders = str_repeat('?,', count($student_ids) - 1) . '?';
                $stmt = $pdo->prepare("DELETE FROM students WHERE id IN ($placeholders)");
                $result = $stmt->execute($student_ids);
                echo json_encode(['success' => $result]);
                break;
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$per_page = 25;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

// Build query for students with enrollment count and attendance stats
$query = "SELECT s.*, 
                 COUNT(DISTINCT se.id) as enrolled_classes,
                 COUNT(DISTINCT a.id) as total_attendance,
                 ROUND((COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.id END) / 
                       NULLIF(COUNT(DISTINCT a.id), 0)) * 100, 1) as attendance_percentage,
                 s.created_at as registration_date
          FROM students s 
          LEFT JOIN student_enrollments se ON s.id = se.student_id AND se.status = 'enrolled'
          LEFT JOIN attendance a ON s.id = a.student_id
          WHERE 1=1";

$params = [];
$count_query = "SELECT COUNT(DISTINCT s.id) FROM students s WHERE 1=1";
$count_params = [];

if (!empty($search)) {
    $search_condition = " AND (s.name LIKE ? OR s.student_id LIKE ? OR s.email LIKE ? OR s.contact LIKE ?)";
    $query .= $search_condition;
    $count_query .= $search_condition;
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
    $count_params = array_merge($count_params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

if (!empty($status_filter)) {
    $status_condition = " AND s.status = ?";
    $query .= $status_condition;
    $count_query .= $status_condition;
    $params[] = $status_filter;
    $count_params[] = $status_filter;
}

$query .= " GROUP BY s.id ORDER BY s.created_at DESC LIMIT $per_page OFFSET $offset";

try {
    // Check if we have any students, if not, insert sample data
    $check_stmt = $pdo->query("SELECT COUNT(*) as count FROM students");
    $student_count = $check_stmt->fetch()['count'];
    
    if ($student_count == 0) {
        // Insert sample students
        $sample_students = [
            ['STU001', 'John Smith', 'john.smith@example.com', '1234567890', 'active'],
            ['STU002', 'Jane Doe', 'jane.doe@example.com', '1234567891', 'active'],
            ['STU003', 'Mike Johnson', 'mike.johnson@example.com', '1234567892', 'active'],
            ['STU004', 'Sarah Wilson', 'sarah.wilson@example.com', '1234567893', 'active'],
            ['STU005', 'David Brown', 'david.brown@example.com', '1234567894', 'inactive'],
            ['STU006', 'Emily Davis', 'emily.davis@example.com', '1234567895', 'active'],
            ['STU007', 'Robert Miller', 'robert.miller@example.com', '1234567896', 'active'],
            ['STU008', 'Lisa Anderson', 'lisa.anderson@example.com', '1234567897', 'active']
        ];
        
        $insert_stmt = $pdo->prepare("INSERT INTO students (student_id, name, email, contact, status, password) VALUES (?, ?, ?, ?, ?, ?)");
        $default_password = password_hash('password123', PASSWORD_DEFAULT);
        
        foreach ($sample_students as $student) {
            $student[] = $default_password;
            $insert_stmt->execute($student);
        }
    }
    
    // Get total count
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($count_params);
    $total_students = $count_stmt->fetchColumn();
    
    // Get students
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $students = [];
    $total_students = 0;
    $error_message = "Error fetching students: " . $e->getMessage();
}

$total_pages = ceil($total_students / $per_page);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Directory - Full Attend</title>
    <link rel="stylesheet" href="admin_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Container and Main Layout */
        .admin-container {
            display: flex;
            min-height: 100vh;
            background: #f1f5f9;
        }
        
        .admin-main {
            margin-left: 280px;
            flex: 1;
            background: #f1f5f9;
            min-height: 100vh;
            padding: 20px;
            max-width: calc(100vw - 280px);
            overflow-x: hidden;
        }
        
        /* Header Styles */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .header-left h1 {
            color: #1e293b;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .header-left p {
            color: #64748b;
            font-size: 14px;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .date-info, .admin-profile {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        
        .admin-profile img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
        }
        
        /* Statistics Overview */
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid var(--accent-color);
        }
        
        .stat-card h3 {
            margin: 0 0 8px 0;
            color: #2d3748;
            font-size: 24px;
            font-weight: 700;
        }
        
        .stat-card p {
            margin: 0;
            color: #718096;
            font-size: 14px;
        }
        
        /* Search Section */
        .search-section {
            margin-bottom: 20px;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .search-container {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            padding: 20px;
            align-items: center;
        }
        
        .search-box {
            display: flex;
            align-items: center;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 10px 12px;
            flex: 1;
            min-width: 300px;
            max-width: 400px;
        }
        
        .search-box i {
            color: #6c757d;
            margin-right: 8px;
        }
        
        .search-box input {
            border: none;
            background: none;
            outline: none;
            flex: 1;
            font-size: 14px;
        }
        
        .filter-options {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-select {
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            background: white;
            font-size: 14px;
            min-width: 120px;
        }
        
        .btn-primary, .btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-secondary {
            background: #f8f9fa;
            color: #495057;
            border: 1px solid #dee2e6;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary:hover {
            background: #e9ecef;
        }
        
        /* Table Styles */
        .students-table-section {
            margin-top: 20px;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .card-header h3 {
            margin: 0;
            color: #2d3748;
            font-size: 18px;
            font-weight: 600;
        }
        
        .results-info {
            color: #718096;
            font-size: 14px;
        }
        
        .table-container {
            overflow-x: auto;
            border-radius: 0 0 12px 12px;
        }
        
        .students-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            font-size: 14px;
        }
        
        .students-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border: none;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .students-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }
        
        .students-table tr:hover {
            background-color: #f8f9ff;
        }
        
        .student-info {
            display: flex;
            align-items: center;
            gap: 12px;
            max-width: 200px;
        }
        
        .student-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }
        
        .student-details h4 {
            margin: 0 0 4px 0;
            color: #2d3748;
            font-weight: 600;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .student-details p {
            margin: 0;
            color: #718096;
            font-size: 12px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        
        .status-badge.active {
            background-color: #c6f6d5;
            color: #22543d;
        }
        
        .status-badge.inactive {
            background-color: #fed7d7;
            color: #742a2a;
        }
        
        .badge {
            background: #e2e8f0;
            color: #4a5568;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            white-space: nowrap;
        }
        
        .attendance-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
            align-items: center;
        }
        
        .attendance-bar {
            width: 50px;
            height: 4px;
            background-color: #e2e8f0;
            border-radius: 2px;
            overflow: hidden;
        }
        
        .attendance-fill {
            height: 100%;
            border-radius: 2px;
            transition: width 0.3s ease;
        }
        
        .attendance-good { background-color: #48bb78; }
        .attendance-warning { background-color: #ed8936; }
        .attendance-poor { background-color: #f56565; }
        
        .action-buttons {
            display: flex;
            gap: 4px;
        }
        
        .btn-icon {
            padding: 6px;
            border: none;
            border-radius: 4px;
            background: #f7fafc;
            color: #4a5568;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 12px;
        }
        
        .btn-icon:hover {
            background: #edf2f7;
            transform: translateY(-1px);
        }
        
        .btn-icon.danger:hover {
            background: #fed7d7;
            color: #e53e3e;
        }
        
        /* Bulk Actions */
        .bulk-actions {
            display: none;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            padding: 12px 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 8px;
            color: white;
            font-size: 14px;
        }
        
        .bulk-actions.show {
            display: flex;
        }
        
        .bulk-actions .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
        }
        
        .bulk-actions .btn-danger {
            background: #e53e3e;
            color: white;
        }
        
        .bulk-actions .btn-secondary {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        
        /* Pagination */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 16px;
            padding: 16px 20px;
            background: white;
            border-top: 1px solid #eee;
        }
        
        .pagination-info {
            color: #718096;
            font-size: 14px;
        }
        
        .pagination {
            display: flex;
            gap: 4px;
        }
        
        .pagination-btn {
            padding: 6px 12px;
            border: 1px solid #e2e8f0;
            background: white;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            color: #4a5568;
            font-size: 14px;
        }
        
        .pagination-btn:hover:not(.active):not(:disabled) {
            background: #f7fafc;
            border-color: #cbd5e0;
        }
        
        .pagination-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
        }
        
        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* No Data Message */
        .no-data {
            text-align: center;
            padding: 40px 20px;
        }
        
        .no-data-message {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }
        
        .no-data-message i {
            font-size: 48px;
            color: #cbd5e0;
        }
        
        .no-data-message h3 {
            margin: 0;
            color: #4a5568;
            font-size: 18px;
        }
        
        .no-data-message p {
            margin: 0;
            color: #718096;
            font-size: 14px;
        }
        
        /* Responsive Design */
        @media (max-width: 1024px) {
            .admin-main {
                margin-left: 0;
                max-width: 100vw;
                padding: 16px;
            }
            
            .stats-overview {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 12px;
            }
            
            .search-container {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                min-width: 100%;
                max-width: none;
            }
            
            .filter-options {
                justify-content: space-between;
            }
        }
        
        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                gap: 16px;
                text-align: center;
            }
            
            .stats-overview {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .table-container {
                font-size: 12px;
            }
            
            .students-table th,
            .students-table td {
                padding: 8px;
            }
            
            .student-info {
                max-width: 150px;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 2px;
            }
        }
        
        @media (max-width: 480px) {
            .stats-overview {
                grid-template-columns: 1fr;
            }
            
            .pagination-container {
                flex-direction: column;
                gap: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="admin-main">
            <!-- Header -->
            <header class="dashboard-header">
                <div class="header-left">
                    <h1>Student Directory</h1>
                    <p>Manage and view all registered students</p>
                </div>
                <div class="header-right">
                    <div class="date-info">
                        <i class="fas fa-calendar"></i>
                        <span><?php echo date('l, F j, Y'); ?></span>
                    </div>
                    
                </div>
            </header>

            <!-- Statistics Overview -->
            <div class="stats-overview">
                <div class="stat-card" style="--accent-color: #48bb78;">
                    <h3><?php echo isset($total_students) ? $total_students : 0; ?></h3>
                    <p>Total Students</p>
                </div>
                <div class="stat-card" style="--accent-color: #4299e1;">
                    <h3><?php echo isset($students) ? count(array_filter($students, function($s) { return isset($s['status']) && $s['status'] === 'active'; })) : 0; ?></h3>
                    <p>Active Students</p>
                </div>
                <div class="stat-card" style="--accent-color: #ed8936;">
                    <h3><?php echo isset($students) ? count(array_filter($students, function($s) { return isset($s['enrolled_classes']) && $s['enrolled_classes'] > 0; })) : 0; ?></h3>
                    <p>Enrolled Students</p>
                </div>
            </div>
            
            <!-- Search and Filters -->
            <section class="search-section">
                <div class="card">
                    <form method="GET" class="search-container">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" placeholder="Search students by name, ID, email, or contact..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
                        </div>
                        <div class="filter-options">
                            
                            <button type="submit" class="btn-secondary">
                                <i class="fas fa-search"></i>
                                Search
                            </button>
                            
                        </div>
                    </form>
                </div>
            </section>
            
            <!-- Bulk Actions -->
            <div class="bulk-actions" id="bulkActions">
                <span id="selectedCount">0 students selected</span>
                <button type="button" class="btn btn-danger" onclick="bulkDelete()">
                    <i class="fas fa-trash"></i>
                    Delete Selected
                </button>
                <button type="button" class="btn btn-secondary" onclick="clearSelection()">
                    Cancel
                </button>
            </div>
            
            <!-- Students Table -->
            <section class="students-table-section">
                <div class="card">
                    <div class="card-header">
                        <h3>All Students (<?php echo isset($total_students) ? number_format($total_students) : 0; ?>)</h3>
                        <div class="header-actions">
                            <span class="results-info">
                                Showing <?php echo isset($offset) ? ($offset + 1) : 1; ?> to <?php echo isset($offset, $per_page, $total_students) ? min($offset + $per_page, $total_students) : 0; ?> of <?php echo isset($total_students) ? number_format($total_students) : 0; ?> students
                            </span>
                        </div>
                    </div>
                    
                    <div class="table-container">
                        <table class="students-table">
                            <thead>
                                <tr>
                                    <th>
                                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                                    </th>
                                    <th>Student</th>
                                    <th>Student ID</th>
                                    <th>Contact</th>
                                    <th>Status</th>
                                    <th>Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!isset($students) || empty($students)): ?>
                                <tr>
                                    <td colspan="9" class="no-data">
                                        <div class="no-data-message">
                                            <i class="fas fa-users"></i>
                                            <h3>No students found</h3>
                                            <p>No students match your search criteria</p>
                                            <a href="add_student.php" class="btn-primary">Add First Student</a>
                                        </div>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($students as $student): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="student-select" value="<?php echo $student['id']; ?>" onchange="updateBulkActions()">
                                    </td>
                                    <td>
                                        <div class="student-info">
                                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($student['name']); ?>&background=6c8ff5&color=fff&size=40" 
                                                 alt="<?php echo htmlspecialchars($student['name']); ?>" class="student-avatar">
                                            <div class="student-details">
                                                <h4><?php echo htmlspecialchars($student['name']); ?></h4>
                                                <p><?php echo htmlspecialchars($student['email']); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($student['student_id']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($student['contact'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo strtolower($student['status']); ?>" 
                                              onclick="toggleStatus(<?php echo $student['id']; ?>, '<?php echo $student['status']; ?>')" 
                                              style="cursor: pointer;" title="Click to toggle status">
                                            <?php echo ucfirst($student['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?php echo date('M j, Y', strtotime($student['registration_date'])); ?></small>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <!-- <button class="btn-icon" title="View Profile" onclick="viewStudent(<?php echo $student['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button> -->
                                            <!-- <button class="btn-icon" title="Edit Student" onclick="editStudent(<?php echo $student['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button> -->
                                            <button class="btn-icon danger" title="Delete Student" onclick="deleteStudent(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['name']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if (isset($total_pages) && $total_pages > 1): ?>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Showing <?php echo isset($offset) ? ($offset + 1) : 1; ?> to <?php echo isset($offset, $per_page, $total_students) ? min($offset + $per_page, $total_students) : 0; ?> of <?php echo isset($total_students) ? number_format($total_students) : 0; ?> students
                        </div>
                        <div class="pagination">
                            <?php if (isset($page) && $page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search ?? ''); ?>&status=<?php echo urlencode($status_filter ?? ''); ?>" class="pagination-btn">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            if (isset($page, $total_pages)) {
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search ?? ''); ?>&status=<?php echo urlencode($status_filter ?? ''); ?>" 
                                   class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php 
                                endfor;
                            }
                            ?>
                            
                            <?php if (isset($page, $total_pages) && $page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search ?? ''); ?>&status=<?php echo urlencode($status_filter ?? ''); ?>" class="pagination-btn">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>

    <script>
        let selectedStudents = [];

        function toggleSelectAll(checkbox) {
            const studentCheckboxes = document.querySelectorAll('.student-select');
            studentCheckboxes.forEach(cb => {
                cb.checked = checkbox.checked;
            });
            updateBulkActions();
        }

        function updateBulkActions() {
            const selectedCheckboxes = document.querySelectorAll('.student-select:checked');
            selectedStudents = Array.from(selectedCheckboxes).map(cb => cb.value);
            
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            
            if (selectedStudents.length > 0) {
                bulkActions.classList.add('show');
                selectedCount.textContent = `${selectedStudents.length} student(s) selected`;
            } else {
                bulkActions.classList.remove('show');
            }
            
            // Update select all checkbox
            const selectAllCheckbox = document.getElementById('selectAll');
            const totalCheckboxes = document.querySelectorAll('.student-select');
            selectAllCheckbox.indeterminate = selectedStudents.length > 0 && selectedStudents.length < totalCheckboxes.length;
            selectAllCheckbox.checked = selectedStudents.length === totalCheckboxes.length && totalCheckboxes.length > 0;
        }

        function clearSelection() {
            document.querySelectorAll('.student-select').forEach(cb => cb.checked = false);
            document.getElementById('selectAll').checked = false;
            updateBulkActions();
        }

        function deleteStudent(studentId, studentName) {
            if (confirm(`Are you sure you want to delete ${studentName}? This action cannot be undone.`)) {
                fetch('student_directory.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=delete_student&student_id=${studentId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error deleting student: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
            }
        }

        function bulkDelete() {
            if (selectedStudents.length === 0) return;
            
            if (confirm(`Are you sure you want to delete ${selectedStudents.length} student(s)? This action cannot be undone.`)) {
                fetch('student_directory.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=bulk_delete&student_ids=${JSON.stringify(selectedStudents)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error deleting students: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
            }
        }

        function toggleStatus(studentId, currentStatus) {
            fetch('student_directory.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=toggle_status&student_id=${studentId}&current_status=${currentStatus}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error updating status: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        }

        function viewStudent(studentId) {
            // TODO: Implement student profile view
            alert('Student profile view - Feature coming soon!');
        }

        function editStudent(studentId) {
            window.location.href = `add_student.php?edit=${studentId}`;
        }

        function exportStudents() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', '1');
            window.location.href = 'student_directory.php?' + params.toString();
        }

        // Initialize bulk actions
        document.addEventListener('DOMContentLoaded', function() {
            updateBulkActions();
        });
    </script>
</body>
</html>
