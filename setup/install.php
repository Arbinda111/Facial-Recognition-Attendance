<?php
// Database setup script
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$host = 'localhost';
$port = '3306';
$username = 'root';
$password = 'root';
$database = 'face_recog';

try {
    // First, connect without specifying database to create it
    $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to MySQL server successfully!<br>";
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$database`");
    echo "Database '$database' created/verified successfully!<br>";
    
    // Now connect to the specific database
    $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database '$database' successfully!<br><br>";
    
    // Read and execute SQL file
    $sqlFile = __DIR__ . '/../database/database.sql';
    if (file_exists($sqlFile)) {
        $sql = file_get_contents($sqlFile);
        
        // Split SQL commands by semicolon and execute each one
        $commands = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($commands as $command) {
            if (!empty($command) && !preg_match('/^--/', $command)) {
                try {
                    $pdo->exec($command);
                } catch (PDOException $e) {
                    // Skip errors for statements that might already exist
                    if (strpos($e->getMessage(), 'already exists') === false && 
                        strpos($e->getMessage(), 'Duplicate entry') === false) {
                        echo "Error executing command: " . $e->getMessage() . "<br>";
                    }
                }
            }
        }
        
        echo "Database tables created successfully!<br><br>";
    } else {
        echo "SQL file not found: $sqlFile<br>";
    }
    
    // Verify tables were created
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>Created Tables:</h3>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul><br>";
    
    // Check if admin user exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin WHERE username = 'admin'");
    $stmt->execute();
    $adminExists = $stmt->fetchColumn();
    
    if ($adminExists == 0) {
        // Create default admin user with hashed password
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admin (username, email, password, full_name) VALUES (?, ?, ?, ?)");
        $stmt->execute(['admin', 'admin@fullattend.com', $hashedPassword, 'System Administrator']);
        echo "Default admin user created (username: admin, password: admin123)<br>";
    } else {
        echo "Admin user already exists<br>";
    }
    
    echo "<br><h3>Setup completed successfully!</h3>";
    echo "<p><a href='../admin/admin_login.php'>Go to Admin Login</a></p>";
    echo "<p><a href='../student/student_login.php'>Go to Student Login</a></p>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}
?>
