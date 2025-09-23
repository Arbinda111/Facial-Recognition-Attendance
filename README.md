# Face Recognition Auto Attendance System - Database Setup

## Prerequisites

- MySQL server running on localhost:3306
- Username: root, Password: root

## Installation Steps

1. **Start MYSQL Services**

2. **Run Database Setup**

   - Open your browser and navigate to: `http://localhost/AI_Face_recog_with_AutoAttendance/setup/install.php`
   - This will automatically:
     - Create the `face_recog` database
     - Create all required tables
     - Insert default admin user and sample data

3. **Default Login Credentials**

   **Admin Login:**

   - URL: `http://localhost/AI_Face_recog_with_AutoAttendance/admin/admin_login.php`
   - Username: `admin`
   - Password: `password`

   **Student Login:**

   - URL: `http://localhost/AI_Face_recog_with_AutoAttendance/student/student_login.php`
   - Students need to be registered by admin first

## Database Structure

### Tables Created:

- `admin` - Admin users
- `students` - Student information
- `classes` - Course/class information
- `sessions` - Attendance sessions
- `student_enrollments` - Student-class relationships
- `attendance` - Attendance records
- `system_settings` - System configuration
- `face_recognition_logs` - Face recognition attempt logs

## Features Implemented:

1. ✅ Admin authentication with database
2. ✅ Student registration with validation
3. ✅ Student authentication with database
4. ✅ Student directory with search and filters
5. ✅ Responsive design
6. ✅ Session management
7. ✅ Password hashing for security

## Troubleshooting:

- If you get "Database connection failed", check MySQL is running
- If tables aren't created, run the install.php script again
- Check XAMPP error logs for detailed error information

