<?php
header('Content-Type: application/json');

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
    // Check if image was uploaded
    if (!isset($_FILES['image'])) {
        throw new Exception('No image uploaded');
    }

    // Create uploads directory if it doesn't exist
    $uploadsDir = __DIR__ . '/uploads';
    if (!file_exists($uploadsDir)) {
        if (!mkdir($uploadsDir, 0777, true)) {
            throw new Exception('Failed to create uploads directory');
        }
    }

    // Save the uploaded image
    $uploadedFile = $_FILES['image']['tmp_name'];
    $imagePath = $uploadsDir . '/detect_' . uniqid() . '.jpg';

    if (!move_uploaded_file($uploadedFile, $imagePath)) {
        throw new Exception('Failed to save uploaded image');
    }

    // Log the saved image path
    logMessage("Image saved to: " . $imagePath);

    // Use Python script for face detection
    $pythonScript = __DIR__ . '/python/detect_face.py';
    if (!file_exists($pythonScript)) {
        throw new Exception('Python script not found at: ' . $pythonScript);
    }

    $command = "python \"" . $pythonScript . "\" \"" . $imagePath . "\"";
    logMessage("Executing command: " . $command);

    $output = shell_exec($command);
    logMessage("Python script output: " . $output);

    // Clean up the temporary image
    if (file_exists($imagePath)) {
        unlink($imagePath);
    }

    if ($output === null) {
        throw new Exception('Face detection script failed to execute');
    }

    $result = json_decode($output, true);
    if ($result === null) {
        throw new Exception('Failed to parse Python script output: ' . json_last_error_msg());
    }

    // Return the result directly
    echo json_encode($result);

} catch (Exception $e) {
    logMessage("Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}