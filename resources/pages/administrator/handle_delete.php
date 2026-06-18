<?php
require_once('../../../database/database_connection.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (isset($input['id'])) {
        $id = $input['id'];
        $name = "tbl" . $input['name'];

        try {
            // If deleting a student, also delete their face data and retrain
            if ($input['name'] === 'students') {
                $stmtSelect = $pdo->prepare("SELECT registrationNumber FROM tblstudents WHERE Id = :id");
                $stmtSelect->execute([':id' => $id]);
                $student = $stmtSelect->fetch(PDO::FETCH_ASSOC);

                if ($student) {
                    $regNum = $student['registrationNumber'];
                    $baseDir = realpath(__DIR__ . '/../../..');
                    
                    // Paths to delete
                    $studentDir = $baseDir . '/students/' . $regNum;
                    $validatedDir = $baseDir . '/validated_faces/student' . $regNum;
                    $labelDir = $baseDir . '/resources/labels/' . $regNum;

                    // Recursive delete helper
                    $deleteDirFunc = function ($dirPath) use (&$deleteDirFunc) {
                        if (!is_dir($dirPath)) {
                            return;
                        }
                        $files = array_diff(scandir($dirPath), array('.', '..'));
                        foreach ($files as $file) {
                            (is_dir("$dirPath/$file")) ? $deleteDirFunc("$dirPath/$file") : unlink("$dirPath/$file");
                        }
                        rmdir($dirPath);
                    };

                    // Delete the directories
                    $deleteDirFunc($studentDir);
                    $deleteDirFunc($validatedDir);
                    $deleteDirFunc($labelDir);

                    // Retrain models after deletion
                    $pythonScript = $baseDir . '/python/realtime_recognition.py';
                    if (file_exists($pythonScript)) {
                        $command = "python \"{$pythonScript}\" --train";
                        exec($command . " 2>&1", $output, $returnVar);
                    }
                }
            }

            $stmt = $pdo->prepare("DELETE FROM $name WHERE Id = :id");
            $stmt->execute([':id' => $id]);

            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'ID not provided.']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
}
?>
