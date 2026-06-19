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

    // Initialize faculty calendar table (semesterID included from fresh install)
    $pdo->exec("CREATE TABLE IF NOT EXISTS tblfacultycalendar (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        facultyCode VARCHAR(50)  NOT NULL,
        semesterID  INT(10)      NOT NULL DEFAULT 0,
        classDate   DATE         NOT NULL,
        description VARCHAR(255) NULL,
        UNIQUE KEY uq_faculty_semester_date (facultyCode, semesterID, classDate)
    )");

    // Initialize semester table
    $pdo->exec("CREATE TABLE IF NOT EXISTS tblsemester (
        Id          INT(10)      NOT NULL AUTO_INCREMENT,
        facultyCode VARCHAR(50)  NOT NULL,
        name        VARCHAR(100) NOT NULL,
        startDate   DATE         NOT NULL,
        endDate     DATE         NOT NULL,
        isActive    TINYINT(1)   NOT NULL DEFAULT 0,
        dateCreated DATE         NOT NULL,
        PRIMARY KEY (Id),
        KEY idx_faculty (facultyCode),
        KEY idx_active  (facultyCode, isActive)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Ensure tblalertstate is up to date with lastThresholdAlertSent column
    try {
        $pdo->exec("ALTER TABLE tblalertstate ADD COLUMN lastThresholdAlertSent DATETIME NULL");
    } catch (PDOException $e) {
        // Column probably already exists or table doesn't exist yet, ignore
    }

    // Silently add semesterID to tblfacultycalendar if missing (upgrade path)
    try {
        $chk = $pdo->query("SHOW COLUMNS FROM `tblfacultycalendar` LIKE 'semesterID'");
        if ($chk->rowCount() === 0) {
            $pdo->exec("ALTER TABLE `tblfacultycalendar` ADD COLUMN `semesterID` INT(10) NOT NULL DEFAULT 0 AFTER `facultyCode`");
        }
    } catch (PDOException $e) { /* ignore */ }

    // Silently add semesterID to tblstudents if missing (upgrade path)
    try {
        $chk = $pdo->query("SHOW COLUMNS FROM `tblstudents` LIKE 'semesterID'");
        if ($chk->rowCount() === 0) {
            $pdo->exec("ALTER TABLE `tblstudents` ADD COLUMN `semesterID` INT(10) NOT NULL DEFAULT 0 AFTER `courseCode`");
        }
    } catch (PDOException $e) { /* ignore */ }

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

/**
 * Returns the currently active semester row for a given faculty, or null.
 */
if (!function_exists('getActiveSemester')) {
    function getActiveSemester($pdo, $facultyCode) {
        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM tblsemester WHERE facultyCode = ? AND isActive = 1 LIMIT 1"
            );
            $stmt->execute([$facultyCode]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            return null;
        }
    }
}

/**
 * Returns all semesters for a given faculty, ordered by startDate desc.
 */
if (!function_exists('getSemestersByFaculty')) {
    function getSemestersByFaculty($pdo, $facultyCode) {
        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM tblsemester WHERE facultyCode = ? ORDER BY startDate DESC"
            );
            $stmt->execute([$facultyCode]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}

/**
 * Returns all semesters (all faculties), joined with faculty name.
 */
if (!function_exists('getAllSemesters')) {
    function getAllSemesters($pdo) {
        try {
            $stmt = $pdo->query(
                "SELECT s.*, f.facultyName FROM tblsemester s
                 LEFT JOIN tblfaculty f ON s.facultyCode = f.facultyCode
                 ORDER BY s.facultyCode, s.startDate DESC"
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}
