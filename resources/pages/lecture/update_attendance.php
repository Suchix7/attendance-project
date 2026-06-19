<?php
header('Content-Type: application/json');
require_once('../../../database/database_connection.php');
require_once('alert_service.php');

// Get the JSON data from the request
$data = json_decode(file_get_contents("php://input"), true);

if ($data) {
    try {
        // Ensure date is set
        if (!isset($data['date'])) {
            $data['date'] = date('Y-m-d');
        }

        // --- Calendar validation: block marking on non-scheduled days ---
        if (!is_scheduled_class_day($pdo, $data['course'], $data['date'])) {
            echo json_encode([
                'success' => false,
                'message' => "Today ({$data['date']}) is not a scheduled class day for this faculty. Attendance has not been recorded.",
                'blocked_reason' => 'unscheduled_day'
            ]);
            exit;
        }

        // Check if an attendance record already exists for this student, course, unit, and date
        $checkSql = "SELECT * FROM tblattendance WHERE 
            studentRegistrationNumber = :studentID AND 
            course = :course AND 
            unit = :unit AND 
            DATE(dateMarked) = :date";

        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([
            ':studentID' => $data['studentID'],
            ':course' => $data['course'],
            ':unit' => $data['unit'],
            ':date' => $data['date']
        ]);

        if ($checkStmt->rowCount() > 0) {
            // Update existing record
            $sql = "UPDATE tblattendance SET 
                attendanceStatus = :attendanceStatus 
                WHERE studentRegistrationNumber = :studentID 
                AND course = :course 
                AND unit = :unit 
                AND dateMarked = :date";
        } else {
            // Insert new record
            $sql = "INSERT INTO tblattendance 
                (studentRegistrationNumber, course, unit, attendanceStatus, dateMarked) 
                VALUES (:studentID, :course, :unit, :attendanceStatus, :date)";
        }

        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':studentID' => $data['studentID'],
            ':course' => $data['course'],
            ':unit' => $data['unit'],
            ':attendanceStatus' => $data['attendanceStatus'],
            ':date' => $data['date']
        ]);

        if ($result) {
            evaluate_and_send_alerts($pdo, $data['studentID'], $data['course'], $data['unit'], $data['attendanceStatus']);
            echo json_encode([
                'success' => true,
                'message' => 'Attendance updated successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update attendance'
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid data received'
    ]);
}
?>