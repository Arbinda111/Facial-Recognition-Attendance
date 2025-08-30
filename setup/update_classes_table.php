<?php
// setup/update_classes_table.php
// Run this script ONCE to add subject_id and lecturer_id columns to classes table
require_once '../config/database.php';

$pdo = getDBConnection();
try {
    $pdo->exec("ALTER TABLE classes ADD COLUMN subject_id INT NOT NULL AFTER id;");
    $pdo->exec("ALTER TABLE classes ADD COLUMN lecturer_id INT NULL AFTER subject_id;");
    echo '<h2>Columns subject_id and lecturer_id added to classes table successfully.</h2>';
} catch (PDOException $e) {
    echo '<h2>Error updating table: ' . $e->getMessage() . '</h2>';
}
?>
