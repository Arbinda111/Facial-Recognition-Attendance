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

// Get attendance statistics
$stats = [];

// Total attendance records
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance WHERE student_id = ?");
$stmt->execute([$student_id]);
$total_records = $stmt->fetch()['count'];

// This month's attendance
$month_start = date('Y-m-01');
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance WHERE student_id = ? AND DATE(attendance_time) >= ?");
$stmt->execute([$student_id, $month_start]);
$month_attendance = $stmt->fetch()['count'];

// This week's attendance
$week_start = date('Y-m-d', strtotime('monday this week'));
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance WHERE student_id = ? AND DATE(attendance_time) >= ?");
$stmt->execute([$student_id, $week_start]);
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

// Build query
$where_conditions = ["student_id = ?"];
$params = [$student_id];

if (!empty($search)) {
    $where_conditions[] = "DATE(attendance_time) LIKE ?";
    $params[] = "%$search%";
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(attendance_time) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(attendance_time) <= ?";
    $params[] = $date_to;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM attendance $where_clause";
$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total_records_filtered = $stmt->fetch()['total'];
$total_pages = ceil($total_records_filtered / $records_per_page);

// Get attendance records
$query = "
    SELECT 
        DATE(attendance_time) as attendance_date,
        TIME(attendance_time) as attendance_time,
        attendance_time as full_datetime
    FROM attendance 
    $where_clause
    ORDER BY attendance_time DESC 
    LIMIT $records_per_page OFFSET $offset
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
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
            <p class="subtitle">View your complete attendance history</p>
          </div>
          <div class="header-actions">
            <div class="date-info">
              <i class="fas fa-calendar"></i>
              <span><?php echo date('l, F j, Y'); ?></span>
            </div>
          </div>
        </div>
      </header>

      <!-- Filters Section -->
      <section class="dashboard-card">
        <div class="card-header">
          <h2><i class="fas fa-filter"></i> Filter Attendance Records</h2>
        </div>
        <form method="GET" class="toolbar">
          <input name="search" class="search" type="search" placeholder="Search by date (YYYY-MM-DD)..." 
                 value="<?php echo htmlspecialchars($search); ?>">
          <input name="date_from" type="date" class="search" placeholder="From Date"
                 value="<?php echo htmlspecialchars($date_from); ?>">
          <input name="date_to" type="date" class="search" placeholder="To Date"
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
                <th><i class="fas fa-calendar-alt"></i> Date</th>
                <th><i class="fas fa-clock"></i> Check-in Time</th>
                <th><i class="fas fa-calendar-day"></i> Day</th>
                <th><i class="fas fa-check-circle"></i> Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($attendance_records as $record): ?>
                <tr>
                  <td class="c-date"><?php echo date('M d, Y', strtotime($record['attendance_date'])); ?></td>
                  <td class="c-time"><?php echo date('g:i A', strtotime($record['attendance_time'])); ?></td>
                  <td class="c-day"><?php echo date('l', strtotime($record['attendance_date'])); ?></td>
                  <td class="c-status">
                    <span class="chip present">
                      <i class="fas fa-check"></i> Present
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
</body>
</html>
