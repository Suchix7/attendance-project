<?php
require_once __DIR__ . '/database_connection.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS tblalertstate (
        id INT AUTO_INCREMENT PRIMARY KEY,
        studentRegistrationNumber VARCHAR(255) NOT NULL,
        courseCode VARCHAR(255) NOT NULL,
        unitCode VARCHAR(255) NOT NULL,
        lastAbsentAlertSent DATETIME NULL,
        consecutivePresentCount INT DEFAULT 0,
        lastMomentumAlertSent DATETIME NULL,
        lastThresholdAlertSent DATETIME NULL,
        UNIQUE KEY uq_student_unit (studentRegistrationNumber, courseCode, unitCode)
    )";

    $pdo->exec($sql);
    echo "Table 'tblalertstate' created successfully.\n";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
?>