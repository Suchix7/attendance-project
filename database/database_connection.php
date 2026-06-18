<?php

$host = "localhost";

//your database name
$database = "attendance-db";

//database user which by default is root unless you have configured with another name
$user = "root";

//password as empty string
$password = "";
try {
    $pdo = new PDO("mysql:host=$host;dbname=$database", $user, $password);
    // Set PDO error mode to exception for better error handling
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Initialize settings table and default values
    $pdo->exec("CREATE TABLE IF NOT EXISTS tblsettings (
        setting_key VARCHAR(255) PRIMARY KEY,
        setting_value TEXT
    )");
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO tblsettings (setting_key, setting_value) VALUES 
        ('face_confidence_threshold', '65'),
        ('email_alerts_mode', 'auto'),
        ('attendance_threshold', '75')
    ");
    $stmt->execute();

    // Initialize faculty calendar table
    $pdo->exec("CREATE TABLE IF NOT EXISTS tblfacultycalendar (
        id INT AUTO_INCREMENT PRIMARY KEY,
        facultyCode VARCHAR(50) NOT NULL,
        classDate DATE NOT NULL,
        description VARCHAR(255) NULL,
        UNIQUE KEY uq_faculty_date (facultyCode, classDate)
    )");

    // Ensure tblalertstate is up to date with lastThresholdAlertSent column
    try {
        $pdo->exec("ALTER TABLE tblalertstate ADD COLUMN lastThresholdAlertSent DATETIME NULL");
    } catch (PDOException $e) {
        // Column probably already exists or table doesn't exist yet, ignore
    }
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

if (!function_exists('get_setting')) {
    function get_setting($pdo, $key, $default = '') {
        try {
            $stmt = $pdo->prepare("SELECT setting_value FROM tblsettings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row['setting_value'] : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
}
