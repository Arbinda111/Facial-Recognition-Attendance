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

// Get the actual student database ID first
$stmt = $pdo->prepare("SELECT id FROM students WHERE student_id = ?");
$stmt->execute([$student_id]);
$student_record = $stmt->fetch();
$student_db_id = $student_record ? $student_record['id'] : null;

// Store in session for future use
if ($student_db_id) {
    $_SESSION['student_db_id'] = $student_db_id;
}

// Get attendance statistics from both tables
$stats = [];

// Total attendance records from attendance_student table using string student_id
$total_records = 0;
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance_student WHERE student_id = ?");
$stmt->execute([$student_id]);
$total_records = $stmt->fetch()['count'];

// This month's attendance from attendance_student table using string student_id
$month_start = date('Y-m-01');
$month_attendance = 0;
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance_student WHERE student_id = ?");
$stmt->execute([$student_id]);
$month_attendance = $stmt->fetch()['count'];

// This week's attendance from attendance_student table using string student_id
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_attendance = 0;
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance_student WHERE student_id = ?");
$stmt->execute([$student_id]);
$week_attendance = $stmt->fetch()['count'];

// Calculate attendance percentage
$working_days_this_month = max(1, date('j')); // Days elapsed in current month
$attendance_percentage = round(($month_attendance / min($working_days_this_month, 22)) * 100); // Assuming 22 working days in a month

// Get all attendance records with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 15;
$offset = ($page - 1) * $records_per_page;

// Search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query for attendance data from attendance_student table using string student_id
$where_conditions = ["student_id = ?"];
$params = [$student_id];

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Get total count for pagination from attendance_student table using string student_id
$count_query = "SELECT COUNT(*) as total FROM attendance_student WHERE student_id = ?";
$count_params = [$student_id];

$stmt = $pdo->prepare($count_query);
$stmt->execute($count_params);
$total_records_filtered = $stmt->fetch()['total'];
$total_pages = ceil($total_records_filtered / $records_per_page);

// Get attendance records from attendance_student table using string student_id
$query = "
    SELECT 
        student_id,
        name,
        'face_recognition' as source
    FROM attendance_student 
    WHERE student_id = ?
    ORDER BY name DESC 
    LIMIT $records_per_page OFFSET $offset
";

$query_params = [$student_id];

$stmt = $pdo->prepare($query);
$stmt->execute($query_params);
$attendance_records = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Attendance - FullAttend</title>
  <link rel="stylesheet" href="student_styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="student-portal">
  <div class="dashboard">
    <!-- Enhanced Sidebar -->
    <aside class="sidebar">
      <div class="sidebar-header">
        <div class="student-avatar">
          <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($student_name); ?>&background=667eea&color=fff&size=60" alt="Student Avatar">
        </div>
        <div class="student-info">
          <h3><?php echo htmlspecialchars($student_name); ?></h3>
          <p class="student-id">ID: <?php echo htmlspecialchars($_SESSION['student_id']); ?></p>
          <span class="status-badge online">Online</span>
        </div>
      </div>
      
      <nav class="sidebar-nav">
        <a href="student_dashboard.php" class="nav-item">
          <i class="fas fa-chart-pie"></i>
          <span>Dashboard</span>
        </a>
        <a href="my_attendance.php" class="nav-item active">
          <i class="fas fa-calendar-check"></i>
          <span>My Attendance</span>
        </a>
        <a href="student_timetable.php" class="nav-item">
          <i class="fas fa-calendar-alt"></i>
          <span>Timetable</span>
        </a>
        <a href="settings.php" class="nav-item">
          <i class="fas fa-cog"></i>
          <span>Settings</span>
        </a>
        <div class="nav-divider"></div>
        <a href="student_logout.php" class="nav-item logout">
          <i class="fas fa-sign-out-alt"></i>
          <span>Logout</span>
        </a>
      </nav>
      
      <div class="sidebar-footer">
        <div class="logos">
          <img src="../images/cihe_logo.png" alt="CIHE Logo" class="logo">
          <img src="../images/fullattend_logo.png" alt="FullAttend Logo" class="logo">
        </div>
      </div>
    </aside>

    <!-- Main Dashboard Content -->
    <main class="dashboard-content">
      <!-- Header Section -->
      <header class="dashboard-header">
        <div class="header-content">
          <div class="welcome-section">
            <h1>My Attendance Records</h1>
            <p class="subtitle">View your attendance records from face recognition system</p>
          </div>
          <div class="header-actions">
            <div class="date-info">
              <i class="fas fa-calendar"></i>
              <span><?php echo date('l, F j, Y'); ?></span>
            </div>
          </div>
        </div>
      </header>

      <!-- Attendance Statistics Cards -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon">
            <i class="fas fa-calendar-check"></i>
          </div>
          <div class="stat-content">
            <h3><?php echo $total_records; ?></h3>
            <p>Total Records</p>
          </div>
        </div>
        
        <div class="stat-card">
          <div class="stat-icon">
            <i class="fas fa-calendar-week"></i>
          </div>
          <div class="stat-content">
            <h3><?php echo $week_attendance; ?></h3>
            <p>This Week</p>
          </div>
        </div>
        
        <div class="stat-card">
          <div class="stat-icon">
            <i class="fas fa-calendar-alt"></i>
          </div>
          <div class="stat-content">
            <h3><?php echo $month_attendance; ?></h3>
            <p>This Month</p>
          </div>
        </div>
        
        <div class="stat-card">
          <div class="stat-icon">
            <i class="fas fa-percentage"></i>
          </div>
          <div class="stat-content">
            <h3><?php echo round(($month_attendance / max(1, date('j'))) * 100); ?>%</h3>
            <p>Monthly Rate</p>
          </div>
        </div>
      </div>

      <!-- Filters Section -->
      <section class="dashboard-card">
        <div class="card-header">
          <h2><i class="fas fa-filter"></i> Filter Attendance Records</h2>
        </div>
        <form method="GET" class="toolbar">
          <input name="search" class="search" type="search" placeholder="Search by name or student ID..." 
                 value="<?php echo htmlspecialchars($search); ?>">
          <!-- Date filtering disabled for attendance_student table -->
          <input name="date_from" type="date" class="search" placeholder="From Date" disabled
                 value="<?php echo htmlspecialchars($date_from); ?>">
          <input name="date_to" type="date" class="search" placeholder="To Date" disabled
                 value="<?php echo htmlspecialchars($date_to); ?>">
          <button type="submit" class="btn primary">
            <i class="fas fa-search"></i> Filter
          </button>
          <a href="my_attendance.php" class="btn secondary">
            <i class="fas fa-times"></i> Clear
          </a>
        </form>
      </section>

      <!-- Attendance Records -->
      <section class="dashboard-card">
        <div class="card-header">
          <h2><i class="fas fa-list"></i> Attendance Records</h2>
          <div class="summary-info">
            <span class="record-count">Total: <?php echo $total_records; ?> records</span>
            <?php if ($total_records_filtered != $total_records): ?>
              <span class="filtered-count">(Showing <?php echo $total_records_filtered; ?> filtered)</span>
            <?php endif; ?>
          </div>
        </div>
        <?php if (empty($attendance_records)): ?>
          <div class="no-records">
            <i class="fas fa-calendar-times" style="font-size: 48px; color: #ccc; margin-bottom: 20px;"></i>
            <h3>No Attendance Records Found</h3>
            <p>No attendance records match your search criteria.</p>
            <?php if (!empty($search) || !empty($date_from) || !empty($date_to)): ?>
              <a href="my_attendance.php" class="btn">View All Records</a>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <table class="attendance-table" id="attTable">
            <thead>
              <tr>
                <th><i class="fas fa-id-card"></i> Student ID</th>
                <th><i class="fas fa-user"></i> Name</th>
                <th><i class="fas fa-check-circle"></i> Status</th>
                <th><i class="fas fa-cog"></i> Source</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($attendance_records as $record): ?>
                <tr>
                  <td class="c-id"><?php echo htmlspecialchars($record['student_id'] ?? ''); ?></td>
                  <td class="c-name"><?php echo htmlspecialchars($record['name'] ?? 'Unknown'); ?></td>
                  <td class="c-status">
                    <span class="chip present">
                      <i class="fas fa-check"></i> Present
                    </span>
                  </td>
                  <td class="c-source">
                    <span class="chip face-recognition">
                      <i class="fas fa-user-check"></i> Face Recognition
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>

          <!-- Pagination -->
          <?php if ($total_pages > 1): ?>
            <div class="pagination">
              <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">« Previous</a>
              <?php endif; ?>
              
              <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                <?php if ($i == $page): ?>
                  <span class="current"><?php echo $i; ?></span>
                <?php else: ?>
                  <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>"><?php echo $i; ?></a>
                <?php endif; ?>
              <?php endfor; ?>
              
              <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">Next »</a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </section>
    </main>
  </div>

  <style>
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }

    .stat-card {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border-radius: 16px;
      padding: 24px;
      box-shadow: 0 4px 20px rgba(102, 126, 234, 0.25);
      display: flex;
      align-items: center;
      gap: 16px;
      transition: all 0.3s ease;
      border: 1px solid rgba(255, 255, 255, 0.1);
      position: relative;
      overflow: hidden;
    }

    .stat-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
      opacity: 0;
      transition: opacity 0.3s ease;
    }

    .stat-card:hover::before {
      opacity: 1;
    }

    .stat-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 8px 32px rgba(102, 126, 234, 0.4);
    }

    .stat-icon {
      width: 64px;
      height: 64px;
      border-radius: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 28px;
      color: #667eea;
      background: rgba(255, 255, 255, 0.95);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      flex-shrink: 0;
    }

    .stat-content h3 {
      font-size: 36px;
      font-weight: 800;
      margin: 0;
      color: white;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .stat-content p {
      font-size: 14px;
      color: rgba(255, 255, 255, 0.9);
      margin: 4px 0 0 0;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    /* Enhanced Filter Section */
    .dashboard-card {
      background: white;
      border-radius: 16px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
      border: 1px solid rgba(102, 126, 234, 0.1);
      overflow: hidden;
      margin-bottom: 24px;
      transition: all 0.3s ease;
    }

    .dashboard-card:hover {
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
      transform: translateY(-2px);
    }

    .card-header {
      background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
      padding: 20px 24px;
      border-bottom: 2px solid #667eea;
      position: relative;
    }

    .card-header::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      height: 2px;
      background: linear-gradient(90deg, #667eea, #764ba2);
    }

    .card-header h2 {
      margin: 0;
      color: #334155;
      font-size: 20px;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .card-header h2 i {
      color: #667eea;
      font-size: 22px;
    }

    /* Enhanced Toolbar/Filter Styling */
    .toolbar {
      padding: 24px;
      display: flex;
      gap: 16px;
      flex-wrap: wrap;
      align-items: center;
      background: #f8fafc;
    }

    .search {
      padding: 12px 16px;
      border: 2px solid #e2e8f0;
      border-radius: 12px;
      font-size: 14px;
      transition: all 0.3s ease;
      background: white;
      flex: 1;
      min-width: 200px;
    }

    .search:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
      transform: translateY(-1px);
    }

    .btn {
      padding: 12px 20px;
      border: none;
      border-radius: 12px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
      white-space: nowrap;
    }

    .btn.primary {
      background: linear-gradient(135deg, #667eea, #764ba2);
      color: white;
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    .btn.primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    }

    .btn.secondary {
      background: linear-gradient(135deg, #64748b, #475569);
      color: white;
      box-shadow: 0 4px 12px rgba(100, 116, 139, 0.3);
    }

    .btn.secondary:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(100, 116, 139, 0.4);
    }

    /* Enhanced Table Styling */
    .attendance-table {
      width: 100%;
      border-collapse: collapse;
      margin: 0;
      background: white;
    }

    .attendance-table thead {
      background: linear-gradient(135deg, #667eea, #764ba2);
    }

    .attendance-table thead th {
      padding: 16px;
      color: white;
      font-weight: 600;
      text-align: left;
      font-size: 14px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .attendance-table tbody tr {
      border-bottom: 1px solid #e2e8f0;
      transition: all 0.2s ease;
    }

    .attendance-table tbody tr:hover {
      background: linear-gradient(135deg, #f8fafc, #f1f5f9);
      transform: scale(1.01);
    }

    .attendance-table tbody td {
      padding: 16px;
      font-size: 14px;
      color: #475569;
    }

    .chip.face-recognition {
      background: linear-gradient(135deg, #10b981, #059669);
      color: white;
      box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
    }

    .chip.manual {
      background: linear-gradient(135deg, #3b82f6, #2563eb);
      color: white;
      box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
    }

    .chip.present {
      background: linear-gradient(135deg, #10b981, #059669);
      color: white;
      box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
    }

    .chip {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 14px;
      border-radius: 24px;
      font-size: 12px;
      font-weight: 600;
      white-space: nowrap;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    /* Enhanced No Records Section */
    .no-records {
      text-align: center;
      padding: 60px 24px;
      background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    }

    .no-records i {
      font-size: 64px !important;
      color: #cbd5e1 !important;
      margin-bottom: 24px !important;
    }

    .no-records h3 {
      color: #475569;
      font-size: 24px;
      font-weight: 700;
      margin: 0 0 12px 0;
    }

    .no-records p {
      color: #64748b;
      font-size: 16px;
      margin: 0 0 24px 0;
    }

    /* Enhanced Pagination */
    .pagination {
      display: flex;
      justify-content: center;
      gap: 8px;
      padding: 24px;
      background: #f8fafc;
    }

    .pagination a, .pagination .current {
      padding: 10px 16px;
      border-radius: 10px;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.3s ease;
    }

    .pagination a {
      background: white;
      color: #667eea;
      border: 2px solid #e2e8f0;
    }

    .pagination a:hover {
      background: #667eea;
      color: white;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    .pagination .current {
      background: linear-gradient(135deg, #667eea, #764ba2);
      color: white;
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    /* Summary Info Styling */
    .summary-info {
      display: flex;
      gap: 16px;
      align-items: center;
      color: #64748b;
      font-size: 14px;
      font-weight: 500;
    }

    .record-count {
      background: linear-gradient(135deg, #667eea, #764ba2);
      color: white;
      padding: 6px 12px;
      border-radius: 20px;
      font-weight: 600;
      font-size: 12px;
    }

    .filtered-count {
      background: #f59e0b;
      color: white;
      padding: 6px 12px;
      border-radius: 20px;
      font-weight: 600;
      font-size: 12px;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      .stats-grid {
        grid-template-columns: 1fr;
      }
      
      .toolbar {
        flex-direction: column;
        align-items: stretch;
      }
      
      .search {
        min-width: auto;
      }
      
      .attendance-table {
        font-size: 12px;
      }
      
      .attendance-table th,
      .attendance-table td {
        padding: 12px 8px;
      }
    }
  </style>
</body>
</html>
