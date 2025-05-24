<?php
header('Content-Type: application/json');

// Check if required data is provided
if (!isset($_POST['studentId']) || !isset($_POST['studentName']) || !isset($_POST['faces'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

$studentId = $_POST['studentId'];
$studentName = $_POST['studentName'];
$faces = json_decode($_POST['faces'], true);

// Create students directory if it doesn't exist
$studentsDir = 'students';
if (!file_exists($studentsDir)) {
    mkdir($studentsDir, 0777, true);
}

// Create student directory
$studentDir = $studentsDir . '/' . $studentId;
if (!file_exists($studentDir)) {
    mkdir($studentDir, 0777, true);
}

// Save student info
$studentInfo = [
    'id' => $studentId,
    'name' => $studentName,
    'registered_date' => date('Y-m-d H:i:s')
];

file_put_contents($studentDir . '/info.json', json_encode($studentInfo));

// Move face images to student directory
foreach ($faces as $index => $facePath) {
    $newPath = $studentDir . '/face_' . ($index + 1) . '.jpg';
    if (file_exists($facePath)) {
        rename($facePath, $newPath);
    }
}

// Train LBPH model
$command = "python train_model.py " . escapeshellarg($studentId);
$output = shell_exec($command);
$result = json_decode($output, true);

if ($result && $result['success']) {
    echo json_encode([
        'success' => true,
        'message' => 'Student registered successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to train face recognition model'
    ]);
}