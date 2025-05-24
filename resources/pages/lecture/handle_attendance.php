<?php
// Debug log file
$logFile = __DIR__ . '/face_recognition_debug.log';

function debugLog($message)
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// Start output buffering
ob_start();

// Set JSON content type header
header('Content-Type: application/json');

// Ensure all errors are caught
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Log request details
    debugLog("Request started");
    debugLog("POST data: " . print_r($_POST, true));
    debugLog("FILES data: " . print_r($_FILES, true));

    // Get the absolute path to the project root
    $projectRoot = realpath(__DIR__ . '/../../..');
    define('PROJECT_ROOT', $projectRoot);

    debugLog("Project root: " . PROJECT_ROOT);

    // Validate required files exist
    $pythonScript = PROJECT_ROOT . '/python/recognize_face.py';
    $haarCascade = PROJECT_ROOT . '/assets/haarcascade_frontalface_default.xml';

    if (!file_exists($pythonScript)) {
        throw new Exception("Python script not found at: $pythonScript");
    }
    if (!file_exists($haarCascade)) {
        throw new Exception("Haar cascade file not found at: $haarCascade");
    }

    // Check if image was uploaded
    if (!isset($_FILES['image']) || !isset($_FILES['image']['tmp_name'])) {
        throw new Exception("No image file uploaded");
    }

    if (!is_uploaded_file($_FILES['image']['tmp_name'])) {
        throw new Exception("Invalid image upload");
    }

    // Create uploads directory if it doesn't exist
    $uploadsDir = PROJECT_ROOT . '/uploads';
    if (!file_exists($uploadsDir)) {
        if (!mkdir($uploadsDir, 0777, true)) {
            throw new Exception("Failed to create uploads directory");
        }
    }

    // Save the uploaded image
    $tempImage = $uploadsDir . '/temp_' . uniqid() . '.jpg';
    debugLog("Saving image to: " . $tempImage);

    if (!move_uploaded_file($_FILES['image']['tmp_name'], $tempImage)) {
        throw new Exception("Failed to save uploaded image");
    }

    // Build and execute Python command
    $pythonCmd = "python";  // or "python3" depending on your system
    $command = sprintf(
        '%s "%s" "%s"',
        escapeshellcmd($pythonCmd),
        escapeshellarg($pythonScript),
        escapeshellarg($tempImage)
    );

    if (isset($_POST['detect_only']) && $_POST['detect_only'] === 'true') {
        $command .= ' --detect-only';
    }

    debugLog("Executing command: " . $command);

    // Execute command with proper error handling
    $descriptorspec = array(
        1 => array("pipe", "w"),  // stdout
        2 => array("pipe", "w")   // stderr
    );

    $process = proc_open($command, $descriptorspec, $pipes);
    if (!is_resource($process)) {
        throw new Exception("Failed to execute Python script");
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $returnValue = proc_close($process);

    // Log outputs
    debugLog("Command stdout: " . $stdout);
    if ($stderr) {
        debugLog("Command stderr: " . $stderr);
    }
    debugLog("Command return value: " . $returnValue);

    // Clean up temp file
    if (file_exists($tempImage)) {
        unlink($tempImage);
        debugLog("Temporary image deleted");
    }

    // Check for execution errors
    if ($returnValue !== 0) {
        throw new Exception("Python script failed: " . $stderr);
    }

    if (empty($stdout)) {
        throw new Exception("Python script produced no output");
    }

    // Parse JSON response
    $result = json_decode($stdout, true);
    if ($result === null && json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON response: " . json_last_error_msg() . "\nOutput: " . $stdout);
    }

    // Add debug information
    $result['debug'] = array(
        'command' => $command,
        'return_value' => $returnValue,
        'stdout' => $stdout,
        'stderr' => $stderr
    );

    debugLog("Sending response: " . json_encode($result));
    echo json_encode($result);

} catch (Exception $e) {
    debugLog("Error: " . $e->getMessage());
    $error = array(
        'success' => false,
        'face_detected' => false,
        'message' => $e->getMessage(),
        'debug' => isset($command) ? array(
            'command' => $command,
            'stdout' => isset($stdout) ? $stdout : null,
            'stderr' => isset($stderr) ? $stderr : null
        ) : null
    );
    echo json_encode($error);
}

// Clear any buffered output
ob_end_clean();
exit;
