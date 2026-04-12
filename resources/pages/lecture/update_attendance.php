<?php
header('Content-Type: application/json');
require_once('../../config/config.php');

// Get the JSON data from the request
$data = json_decode(file_get_contents("php://input"), true);

if ($data) {
    try {
        // Check if an attendance record already exists for this student, course, unit, and date
        $checkSql = "SELECT * FROM tblattendance WHERE 
            studentRegistrationNumber = :studentID AND 
            course = :course AND 
            unit = :unit AND 
            dateMarked = :date";

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