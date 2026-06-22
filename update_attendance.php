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
    $attendanceStatus = $data['attendanceStatus'];
    $date = isset($data['date']) ? $data['date'] : date('Y-m-d');

    // --- Calendar validation: block marking on non-scheduled days ---
    if (!is_scheduled_class_day($pdo, $course, $date)) {
        echo json_encode([
            'success' => false,
            'message' => "Today ($date) is not a scheduled class day for this faculty. Attendance has not been recorded.",
            'blocked_reason' => 'unscheduled_day'
        ]);
        exit;
    }

    // First check if there's an existing attendance record
    $checkSql = "SELECT attendanceID, attendanceStatus FROM tblattendance 
                 WHERE studentRegistrationNumber = :studentID 
                 AND course = :course 
                 AND DATE(dateMarked) = :date";

    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([
        ':studentID' => $studentID,
        ':course' => $course,
        ':date' => $date
    ]);

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

            evaluate_and_send_alerts($pdo, $studentID, $course, $attendanceStatus);

            echo json_encode([
                'success' => true,
                'message' => 'Attendance updated successfully',
                'updated' => true
            ]);
        } else {
            // Record exists but is already marked as Present
            echo json_encode([
                'success' => true,
                'message' => 'Attendance already marked as Present',
                'updated' => false
            ]);
        }
    } else {
        // No existing record, insert new one
        $insertSql = "INSERT INTO tblattendance 
                      (studentRegistrationNumber, course, attendanceStatus, dateMarked) 
                      VALUES (:studentID, :course, :status, NOW())";

        $insertStmt = $pdo->prepare($insertSql);
        $insertStmt->execute([
            ':studentID' => $studentID,
            ':course' => $course,
            ':status' => $attendanceStatus
        ]);

        evaluate_and_send_alerts($pdo, $studentID, $course, $attendanceStatus);

        echo json_encode([
            'success' => true,
            'message' => 'New attendance record created successfully',
            'updated' => true
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