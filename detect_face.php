<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['image'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request. Please send an image file.'
    ]);
    exit;
}

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create a log file for debugging
$logFile = __DIR__ . '/face_detection.log';
function logMessage($message)
{
    global $logFile;
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

try {
    // Save the uploaded image temporarily
    $tmpImage = $_FILES['image']['tmp_name'];

    // Execute the Python script for face detection
    $command = escapeshellcmd("python python/detect_face.py " . escapeshellarg($tmpImage));
    $output = shell_exec($command);

    // Parse the JSON output from Python script
    $result = json_decode($output, true);

    if ($result === null) {
        throw new Exception('Failed to parse Python script output');
    }

    // Return the result
    echo json_encode($result);

} catch (Exception $e) {
    logMessage("Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}