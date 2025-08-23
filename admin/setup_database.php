<?php
// Database update script to add lecturers table
require_once '../config/database.php';

try {
    // Create lecturers table
    $sql = "CREATE TABLE IF NOT EXISTS lecturers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($sql);
    echo "✅ Lecturers table created successfully!<br>";
    
    // Add a default admin account if it doesn't exist
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin");
    $stmt->execute();
    $adminCount = $stmt->fetchColumn();
    
    if ($adminCount == 0) {
        $stmt = $pdo->prepare("INSERT INTO admin (username, email, password, full_name) VALUES (?, ?, ?, ?)");
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt->execute(['admin', 'admin@fullattend.com', $hashedPassword, 'System Administrator']);
        echo "✅ Default admin account created (username: admin, password: admin123)<br>";
    }
    
    // Add some sample students if table is empty
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students");
    $stmt->execute();
    $studentCount = $stmt->fetchColumn();
    
    if ($studentCount == 0) {
        $sampleStudents = [
            ['CIHE240001', 'John Doe', 'john.doe@student.com', password_hash('student123', PASSWORD_DEFAULT)],
            ['CIHE240002', 'Jane Smith', 'jane.smith@student.com', password_hash('student123', PASSWORD_DEFAULT)],
            ['CIHE240003', 'Mike Johnson', 'mike.johnson@student.com', password_hash('student123', PASSWORD_DEFAULT)]
        ];
        
        $stmt = $pdo->prepare("INSERT INTO students (student_id, name, email, password) VALUES (?, ?, ?, ?)");
        
        foreach ($sampleStudents as $student) {
            $stmt->execute($student);
        }
        echo "✅ Sample students added (password: student123)<br>";
    }
    
    echo "<br><strong>Database setup completed successfully!</strong><br>";
    echo "<a href='admin_login.php'>Go to Admin Login</a><br>";
    echo "<a href='../index.php'>Go to Main Login Page</a>";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
