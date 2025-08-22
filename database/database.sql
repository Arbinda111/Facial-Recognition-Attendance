-- Face Recognition Auto Attendance Database Schema
-- Database: face_recog

CREATE DATABASE IF NOT EXISTS face_recog;
USE face_recog;

-- Admin table
CREATE TABLE IF NOT EXISTS admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Students table
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    contact VARCHAR(20),
    face_encoding TEXT,
    profile_image VARCHAR(255),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Classes table
CREATE TABLE IF NOT EXISTS classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(100) NOT NULL,
    class_code VARCHAR(20) UNIQUE NOT NULL,
    description TEXT,
    instructor_name VARCHAR(100),
    semester VARCHAR(20),
    academic_year VARCHAR(10),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Sessions table (for attendance sessions)
CREATE TABLE IF NOT EXISTS sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_name VARCHAR(100) NOT NULL,
    class_id INT NOT NULL,
    session_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    location VARCHAR(100),
    session_type ENUM('lecture', 'lab', 'tutorial', 'exam') DEFAULT 'lecture',
    status ENUM('scheduled', 'ongoing', 'completed', 'cancelled') DEFAULT 'scheduled',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES admin(id) ON DELETE CASCADE
);

-- Student class enrollments
CREATE TABLE IF NOT EXISTS student_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    class_id INT NOT NULL,
    enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('enrolled', 'dropped', 'completed') DEFAULT 'enrolled',
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (student_id, class_id)
);

-- Attendance records
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    student_id INT NOT NULL,
    attendance_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('present', 'absent', 'late') DEFAULT 'present',
    method ENUM('face_recognition', 'manual', 'qr_code') DEFAULT 'face_recognition',
    marked_by INT NULL, -- admin id if manually marked
    confidence_score DECIMAL(5,4) NULL, -- for face recognition confidence
    notes TEXT,
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (marked_by) REFERENCES admin(id) ON DELETE SET NULL,
    UNIQUE KEY unique_attendance (session_id, student_id)
);

-- System settings
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_name VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_by INT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES admin(id) ON DELETE CASCADE
);

-- Face recognition logs
CREATE TABLE IF NOT EXISTS face_recognition_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    session_id INT,
    recognition_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confidence_score DECIMAL(5,4),
    status ENUM('success', 'failed', 'low_confidence') DEFAULT 'success',
    image_path VARCHAR(255),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE
);

-- Insert default admin user
INSERT INTO admin (username, email, password, full_name) VALUES 
('admin', 'admin@fullattend.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator');

-- Insert default system settings
INSERT INTO system_settings (setting_name, setting_value, description, updated_by) VALUES
('face_recognition_threshold', '0.85', 'Minimum confidence score for face recognition', 1),
('attendance_grace_period', '15', 'Grace period in minutes for late attendance', 1),
('auto_absent_time', '30', 'Minutes after session start to mark students absent', 1),
('system_timezone', 'Asia/Kolkata', 'Default system timezone', 1);

-- Insert sample classes
INSERT INTO classes (class_name, class_code, description, instructor_name, semester, academic_year) VALUES
('Computer Science Fundamentals', 'CS101', 'Introduction to Computer Science', 'Dr. Smith', 'Fall 2025', '2025-26'),
('Database Management Systems', 'CS201', 'Database design and implementation', 'Prof. Johnson', 'Fall 2025', '2025-26'),
('Web Development', 'CS301', 'Full stack web development', 'Dr. Brown', 'Fall 2025', '2025-26');

-- Create indexes for better performance
CREATE INDEX idx_students_student_id ON students(student_id);
CREATE INDEX idx_students_email ON students(email);
CREATE INDEX idx_sessions_date ON sessions(session_date);
CREATE INDEX idx_attendance_session ON attendance(session_id);
CREATE INDEX idx_attendance_student ON attendance(student_id);
CREATE INDEX idx_enrollments_student ON student_enrollments(student_id);
CREATE INDEX idx_enrollments_class ON student_enrollments(class_id);
