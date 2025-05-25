<?php
header('Content-Type: application/json');

function logMessage($message)
{
    $logFile = __DIR__ . '/face_recognition.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

try {
    if (!isset($_FILES['image'])) {
        throw new Exception('No image file received');
    }

    $uploadDir = __DIR__ . '/temp/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Save uploaded image
    $tempImage = $uploadDir . 'temp_' . uniqid() . '.jpg';
    if (!move_uploaded_file($_FILES['image']['tmp_name'], $tempImage)) {
        throw new Exception('Failed to save uploaded image');
    }

    // Call Python script for face recognition
    $pythonScript = __DIR__ . '/python/realtime_recognition.py';
    $command = "python \"$pythonScript\" \"$tempImage\"";

    logMessage("Executing command: $command");
    $output = [];
    $returnCode = 0;
    exec($command . " 2>&1", $output, $returnCode);

    // Log raw output for debugging
    logMessage("Python output: " . print_r($output, true));

    if ($returnCode !== 0) {
        throw new Exception('Python script failed: ' . implode("\n", $output));
    }

    // Get the last line of output (should be JSON)
    $lastLine = end($output);
    $result = json_decode($lastLine, true);

    if ($result === null) {
        logMessage("Error parsing Python output: " . print_r($output, true));
        throw new Exception('Failed to parse recognition result');
    }

    // Clean up temporary file
    unlink($tempImage);

    if (!$result['success']) {
        // If Python script returns failure, forward the error
        throw new Exception($result['message']);
    }

    // Return recognition result
    echo json_encode([
        'success' => true,
        'predicted_student_id' => $result['student_id'],
        'confidence' => $result['confidence'],
        'face_location' => $result['face_location']
    ]);

} catch (Exception $e) {
    logMessage("Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    // Make sure to clean up temp file if it exists
    if (isset($tempImage) && file_exists($tempImage)) {
        unlink($tempImage);
    }
}
?>