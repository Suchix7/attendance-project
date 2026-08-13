<?php
// Active-liveness endpoint: receives a short burst of webcam frames, checks for
// a real blink (a photo/phone-screen still cannot blink) and, if live,
// recognises the person. Mirrors recognize_face.php conventions.
header('Content-Type: application/json');

function logMessage($message)
{
    $logFile = __DIR__ . '/face_recognition.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

$frameDir = null;
try {
    if (!isset($_FILES['frames'])) {
        throw new Exception('No frames received');
    }

    $algo = isset($_POST['algorithm']) ? $_POST['algorithm'] : 'lbph';
    if (!in_array($algo, ['lbph', 'eigen', 'fisher'])) {
        $algo = 'lbph';
    }

    // Unique temp folder for this burst.
    $frameDir = __DIR__ . '/temp/burst_' . uniqid();
    if (!file_exists($frameDir) && !mkdir($frameDir, 0777, true)) {
        throw new Exception('Failed to create temp folder');
    }

    // Save each uploaded frame as frame_0.jpg, frame_1.jpg, ...
    $names = $_FILES['frames']['name'];
    $tmp = $_FILES['frames']['tmp_name'];
    $count = is_array($names) ? count($names) : 0;
    $saved = 0;
    for ($i = 0; $i < $count; $i++) {
        if (!is_uploaded_file($tmp[$i])) {
            continue;
        }
        $dest = "{$frameDir}/frame_{$saved}.jpg";
        if (move_uploaded_file($tmp[$i], $dest)) {
            $saved++;
        }
    }
    if ($saved === 0) {
        throw new Exception('No valid frames saved');
    }

    // Run the blink-liveness + recognition check.
    $pythonScript = __DIR__ . '/python/realtime_recognition.py';
    $command = "python \"$pythonScript\" --verify-liveness \"$frameDir\" --algorithm " . escapeshellarg($algo);
    logMessage("Executing liveness command ($saved frames): $command");

    $output = [];
    $returnCode = 0;
    exec($command . " 2>&1", $output, $returnCode);
    logMessage("Liveness output: " . print_r($output, true));

    if ($returnCode !== 0) {
        throw new Exception('Liveness script failed: ' . implode("\n", $output));
    }

    $result = json_decode(end($output), true);
    if (!is_array($result)) {
        throw new Exception('Failed to parse liveness result');
    }
    if (empty($result['success'])) {
        throw new Exception($result['message'] ?? 'Liveness check failed');
    }

    echo json_encode([
        'success' => true,
        'live' => !empty($result['live']),
        'blinks' => $result['blinks'] ?? 0,
        'predicted_student_id' => $result['student_id'] ?? 'Unknown',
        'confidence' => $result['confidence'] ?? 0,
        'algorithm' => $algo
    ]);

} catch (Exception $e) {
    logMessage("Liveness error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    // Remove the temp burst folder and its frames.
    if ($frameDir && is_dir($frameDir)) {
        array_map('unlink', glob("{$frameDir}/*.*"));
        @rmdir($frameDir);
    }
}
?>
