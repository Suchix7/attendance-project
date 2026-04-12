<?php
// Prevent any output before headers
header('Content-Type: application/json');

ob_start();

// Disable error display but log them
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set JSON headers first
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

try {
    require_once '../../../database/database_connection.php';

    // Get input data
    $data = json_decode(file_get_contents('php://input'), true);

    // Get required fields
    $student_id = $data['student_id'] ?? '';
    $status = $data['status'] ?? '';
    $course = $data['course'] ?? '';
    $unit = $data['unit'] ?? '';
    $today = date('Y-m-d');

    // Basic validation
    if (!$student_id || !$status || !$course || !$unit) {
        throw new Exception('All fields are required');
    }

    if (!in_array($status, ['Present', 'Absent'])) {
        throw new Exception('Status must be Present or Absent');
    }

    // Check if record exists for today
    $stmt = $pdo->prepare("SELECT attendanceID FROM tblattendance 
  WHERE studentRegistrationNumber = ? 
  AND course = ? 
  AND unit = ? 
  AND DATE(dateMarked) = ?");

    $stmt->execute([$student_id, $course, $unit, $today]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Update existing record
        $stmt = $pdo->prepare("UPDATE tblattendance 
                              SET attendanceStatus = ? 
                              WHERE attendanceID = ?");
        $stmt->execute([$status, $existing['attendanceID']]);
    } else {
        // Insert new record
        $stmt = $pdo->prepare("INSERT INTO tblattendance 
                              (studentRegistrationNumber, course, unit, attendanceStatus, dateMarked) 
                              VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$student_id, $course, $unit, $status, $today]);
    }

    // Log the attendance change
    error_log("Attendance updated for student $student_id in course $course, unit $unit: $status");

    // Clear any output buffers
    while (ob_get_level())
        ob_end_clean();

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    error_log("Database error marking attendance: " . $e->getMessage());
    // Clear any output buffers
    while (ob_get_level())
        ob_end_clean();

    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error marking attendance: " . $e->getMessage());
    // Clear any output buffers
    while (ob_get_level())
        ob_end_clean();

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}