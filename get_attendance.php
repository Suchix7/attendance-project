<?php
header('Content-Type: application/json');

$attendanceFile = 'attendance.json';
$records = [];

if (file_exists($attendanceFile)) {
    $records = json_decode(file_get_contents($attendanceFile), true);
}

echo json_encode([
    'success' => true,
    'records' => $records
]);