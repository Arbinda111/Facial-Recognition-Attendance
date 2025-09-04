<?php
// setup/remove_lecturer_subjects_fk.php
// Run this script ONCE to remove the foreign key constraint from lecturer_subjects table
require_once '../config/database.php';

$pdo = getDBConnection();
try {
    // Find the constraint name for subject_id foreign key
    $stmt = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'lecturer_subjects' AND COLUMN_NAME = 'subject_id' AND TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL");
    $row = $stmt->fetch();
    $constraint = $row ? $row['CONSTRAINT_NAME'] : 'lecturer_subjects_ibfk_2';
    $pdo->exec("ALTER TABLE lecturer_subjects DROP FOREIGN KEY `{$constraint}`;");
    echo '<h2>Foreign key constraint ' . htmlspecialchars($constraint) . ' removed from lecturer_subjects table.</h2>';
} catch (PDOException $e) {
    echo '<h2>Error removing foreign key: ' . $e->getMessage() . '</h2>';
}
?>
