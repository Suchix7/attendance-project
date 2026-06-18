<?php
// resources/pages/lecture/alert_service.php

if (!function_exists('evaluate_and_send_alerts')) {
    /**
     * Evaluates attendance state and sends rate-limited/suppressed alerts
     * to prevent alert fatigue or highlight positive momentum.
     *
     * @param PDO    $pdo       The PDO connection instance.
     * @param string $studentID Student registration number.
     * @param string $course    Course code.
     * @param string $unit      Unit code.
     * @param string $status    'Present' or 'Absent'.
     */
    function evaluate_and_send_alerts($pdo, $studentID, $course, $unit, $status) {
        try {
            // Check global email alerts setting
            $emailMode = get_setting($pdo, 'email_alerts_mode', 'auto');
            if ($emailMode === 'disabled') {
                return; // Emails turned off entirely
            }

            // 1. Fetch student details (name, email)
            $stmt = $pdo->prepare("SELECT firstName, lastName, email FROM tblstudents WHERE registrationNumber = :studentID");
            $stmt->execute([':studentID' => $studentID]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student || empty($student['email'])) {
                error_log("Alert Service: Student $studentID has no registered email. Skipping alerts.");
                return;
            }

            $studentName = trim($student['firstName'] . ' ' . $student['lastName']);
            $studentEmail = $student['email'];

            // 2. Fetch or initialize alert state for this student, course, and unit
            $stmt = $pdo->prepare("SELECT * FROM tblalertstate WHERE studentRegistrationNumber = :studentID AND courseCode = :course AND unitCode = :unit");
            $stmt->execute([
                ':studentID' => $studentID,
                ':course' => $course,
                ':unit' => $unit
            ]);
            $state = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$state) {
                // Initialize alert state
                $insertStmt = $pdo->prepare("INSERT INTO tblalertstate (studentRegistrationNumber, courseCode, unitCode, consecutivePresentCount) VALUES (:studentID, :course, :unit, 0)");
                $insertStmt->execute([
                    ':studentID' => $studentID,
                    ':course' => $course,
                    ':unit' => $unit
                ]);
                
                $state = [
                    'lastAbsentAlertSent' => null,
                    'consecutivePresentCount' => 0,
                    'lastMomentumAlertSent' => null
                ];
            }

            $today = date('Y-m-d H:i:s');
            $cooldown_seconds = 3 * 24 * 60 * 60; // 3-day rate limiting cooldown for absences

            if ($status === 'Absent') {
                // Reset consecutive present count
                $updateStmt = $pdo->prepare("UPDATE tblalertstate SET consecutivePresentCount = 0 WHERE studentRegistrationNumber = :studentID AND courseCode = :course AND unitCode = :unit");
                $updateStmt->execute([
                    ':studentID' => $studentID,
                    ':course' => $course,
                    ':unit' => $unit
                ]);

                // Check cooldown for absence notification
                $shouldSend = false;
                if ($emailMode === 'auto') {
                    if (empty($state['lastAbsentAlertSent'])) {
                        $shouldSend = true;
                    } else {
                        $lastSentTime = strtotime($state['lastAbsentAlertSent']);
                        if (time() - $lastSentTime > $cooldown_seconds) {
                            $shouldSend = true;
                        }
                    }
                }

                if ($shouldSend) {
                    $subject = "SAS Portal: Student Absence Notice";
                    $body = "Dear Parent/Guardian,\n\n" .
                            "We are writing to inform you that the student $studentName (Registration No: $studentID) was marked Absent today for the course $course (Unit: $unit).\n\n" .
                            "To prevent notification fatigue, absence email alerts are rate-limited to once every 3 days. Please follow up with the student accordingly.\n\n" .
                            "Best regards,\n" .
                            "SAS Portal Attendance System";

                    if (trigger_alert_emailer($studentEmail, $subject, $body)) {
                        $updateSentStmt = $pdo->prepare("UPDATE tblalertstate SET lastAbsentAlertSent = :today WHERE studentRegistrationNumber = :studentID AND courseCode = :course AND unitCode = :unit");
                        $updateSentStmt->execute([
                            ':today' => $today,
                            ':studentID' => $studentID,
                            ':course' => $course,
                            ':unit' => $unit
                        ]);
                    }
                }

            } else if ($status === 'Present') {
                // Increment consecutive present count
                $newStreak = $state['consecutivePresentCount'] + 1;
                $updateStmt = $pdo->prepare("UPDATE tblalertstate SET consecutivePresentCount = :streak WHERE studentRegistrationNumber = :studentID AND courseCode = :course AND unitCode = :unit");
                $updateStmt->execute([
                    ':streak' => $newStreak,
                    ':studentID' => $studentID,
                    ':course' => $course,
                    ':unit' => $unit
                ]);

                // Check for positive attendance momentum (milestones at multiples of 3)
                if ($newStreak > 0 && $newStreak % 3 === 0 && $emailMode === 'auto') {
                    $subject = "Congratulations on Your Attendance Momentum!";
                    $body = "Dear $studentName,\n\n" .
                            "Congratulations! You have successfully attended $newStreak consecutive sessions of $course (Unit: $unit)!\n\n" .
                            "We are thrilled to see your commitment and positive momentum. Keep up the excellent work!\n\n" .
                            "Best regards,\n" .
                            "SAS Portal Attendance System";

                    if (trigger_alert_emailer($studentEmail, $subject, $body)) {
                        $updateMomentumStmt = $pdo->prepare("UPDATE tblalertstate SET lastMomentumAlertSent = :today WHERE studentRegistrationNumber = :studentID AND courseCode = :course AND unitCode = :unit");
                        $updateMomentumStmt->execute([
                            ':today' => $today,
                            ':studentID' => $studentID,
                            ':course' => $course,
                            ':unit' => $unit
                        ]);
                    }
                }
            }

        } catch (Exception $e) {
            error_log("Alert Service Error: " . $e->getMessage());
        }
    }

    /**
     * Executes the Python email dispatcher passing mail configuration via stdin.
     */
    function trigger_alert_emailer($to, $subject, $body) {
        $baseDir = realpath(__DIR__ . '/../../..');
        $pythonScript = $baseDir . '/python/alert_emailer.py';

        if (!file_exists($pythonScript)) {
            error_log("Alert Service: Python script not found at $pythonScript");
            return false;
        }

        $mailData = json_encode([
            'to' => $to,
            'subject' => $subject,
            'body' => $body
        ]);

        $descriptors = [
            0 => ["pipe", "r"], // stdin
            1 => ["pipe", "w"], // stdout
            2 => ["pipe", "w"]  // stderr
        ];

        // Execute python script
        $command = "python \"" . $pythonScript . "\"";
        $process = proc_open($command, $descriptors, $pipes);

        if (is_resource($process)) {
            fwrite($pipes[0], $mailData);
            fclose($pipes[0]);

            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);

            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            $returnVal = proc_close($process);

            if ($returnVal !== 0) {
                error_log("Alert Service: Python emailer failed with exit code $returnVal. Stderr: $stderr");
                return false;
            }

            $response = json_decode($stdout, true);
            if ($response && isset($response['success']) && $response['success'] === true) {
                return true;
            } else {
                $msg = $response['message'] ?? 'Unknown error';
                error_log("Alert Service: Python emailer returned success=false. Message: $msg");
                return false;
            }
        }

        error_log("Alert Service: Failed to spawn process: $command");
        return false;
    }
}
?>
