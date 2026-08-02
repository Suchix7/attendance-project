<?php
header('Content-Type: application/json');

// Include database connection
require_once 'database/database_connection.php';
require_once 'resources/pages/lecture/alert_service.php';

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request data'
    ]);
    exit;
}

try {
    // Extract data
    $studentID = $data['studentID'];
    $course = $data['course'];
    $unit = isset($data['unit']) ? $data['unit'] : '';
    $attendanceStatus = $data['attendanceStatus'];
    $date = isset($data['date']) ? $data['date'] : date('Y-m-d');

    // --- Calendar validation: warn if not a scheduled day, but still record ---
    $calendarWarning = null;
    if (!is_scheduled_class_day($pdo, $course, $date)) {
        $calendarWarning = "Note: Today ($date) is not in the configured schedule for this faculty, but attendance has been recorded.";
    }

    // First check if there's an existing attendance record
    $checkSql = "SELECT attendanceID, attendanceStatus FROM tblattendance 
                 WHERE studentRegistrationNumber = :studentID 
                 AND course = :course 
                 AND DATE(dateMarked) = :date";
    if (!empty($unit)) {
        $checkSql .= " AND unit = :unit";
    }

    $checkParams = [
        ':studentID' => $studentID,
        ':course' => $course,
        ':date' => $date
    ];
    if (!empty($unit)) {
        $checkParams[':unit'] = $unit;
    }

    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute($checkParams);

    $existingRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($existingRecord) {
        // If record exists and is marked as 'Absent', update it to 'Present'
        if ($existingRecord['attendanceStatus'] === 'Absent') {
            $updateSql = "UPDATE tblattendance 
                         SET attendanceStatus = :status,
                             dateMarked = NOW() 
                         WHERE attendanceID = :attendanceID";

            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                ':status' => $attendanceStatus,
                ':attendanceID' => $existingRecord['attendanceID']
            ]);

            evaluate_and_send_alerts($pdo, $studentID, $course, $unit, $attendanceStatus);

            echo json_encode([
                'success' => true,
                'message' => 'Attendance updated successfully',
                'updated' => true,
                'calendar_warning' => $calendarWarning
            ]);
        } else {
            // Record exists but is already marked as Present
            echo json_encode([
                'success' => true,
                'message' => 'Attendance already marked as Present',
                'updated' => false,
                'calendar_warning' => $calendarWarning
            ]);
        }
    } else {
        // No existing record, insert new one
        $insertSql = "INSERT INTO tblattendance 
                      (studentRegistrationNumber, course, unit, attendanceStatus, dateMarked) 
                      VALUES (:studentID, :course, :unit, :status, NOW())";

        $insertStmt = $pdo->prepare($insertSql);
        $insertStmt->execute([
            ':studentID' => $studentID,
            ':course' => $course,
            ':unit' => $unit,
            ':status' => $attendanceStatus
        ]);

        evaluate_and_send_alerts($pdo, $studentID, $course, $unit, $attendanceStatus);

        echo json_encode([
            'success' => true,
            'message' => 'New attendance record created successfully',
            'updated' => true,
            'calendar_warning' => $calendarWarning
        ]);
    }

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
?>