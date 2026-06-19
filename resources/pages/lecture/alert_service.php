<?php
// resources/pages/lecture/alert_service.php

require_once __DIR__ . '/../../lib/nepali_calendar.php';

/**
 * Checks whether a given date is an officially scheduled class day
 * for the faculty that owns the given course.
 *
 * If the faculty has NO calendar entries at all we treat every day as
 * valid (graceful fallback so the system still works before a calendar
 * is configured).
 *
 * @param PDO    $pdo    Active database connection.
 * @param string $course Course code (used to look up the faculty).
 * @param string $date   Date string in Y-m-d format (defaults to today).
 * @return bool  true  → allowed to mark attendance
 *               false → NOT a scheduled class day; attendance should be blocked/flagged
 */
if (!function_exists('is_scheduled_class_day')) {
    function is_scheduled_class_day($pdo, $course, $date = null) {
        if ($date === null) {
            $date = date('Y-m-d');
        }

        try {
            // Resolve the faculty that owns this course
            $stmtFaculty = $pdo->prepare(
                "SELECT f.facultyCode
                 FROM tblcourse c
                 JOIN tblfaculty f ON c.facultyID = f.Id
                 WHERE c.courseCode = ?"
            );
            $stmtFaculty->execute([$course]);
            $facultyCode = $stmtFaculty->fetchColumn();

            if (!$facultyCode) {
                return true; // no faculty mapping → no restriction
            }

            // Get active semester for this faculty
            $semesterId = 0;
            if (function_exists('getActiveSemester')) {
                $activeSem = getActiveSemester($pdo, $facultyCode);
                if ($activeSem) {
                    $semesterId = $activeSem['Id'];
                }
            }

            // If no semester is scoped we cannot isolate this semester's calendar.
            // Fail-open: allow the attendance marking rather than blocking with mixed data.
            if (!$semesterId) {
                return true;
            }

            // Check whether ANY calendar entries exist for this faculty + semester
            $stmtCount = $pdo->prepare(
                "SELECT COUNT(*) FROM tblfacultycalendar WHERE facultyCode = ? AND semesterID = ?"
            );
            $stmtCount->execute([$facultyCode, $semesterId]);
            $totalEntries = (int) $stmtCount->fetchColumn();

            if ($totalEntries === 0) {
                return true; // calendar not set up yet for this semester → no restriction
            }

            // Check whether this specific date is in this semester's schedule
            $stmtDate = $pdo->prepare(
                "SELECT COUNT(*) FROM tblfacultycalendar
                 WHERE facultyCode = ? AND semesterID = ? AND classDate = ?"
            );
            $stmtDate->execute([$facultyCode, $semesterId, $date]);

            return (int) $stmtDate->fetchColumn() > 0;
        } catch (Exception $e) {
            error_log("is_scheduled_class_day error: " . $e->getMessage());
            return true; // fail-open so a DB error never blocks all marking
        }
    }
}

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

            // 1. Fetch student details (name, email, semesterID)
            $stmt = $pdo->prepare("SELECT firstName, lastName, email, faculty, semesterID FROM tblstudents WHERE registrationNumber = :studentID");
            $stmt->execute([':studentID' => $studentID]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student || empty($student['email'])) {
                error_log("Alert Service: Student $studentID has no registered email. Skipping alerts.");
                return;
            }

            $studentName = trim($student['firstName'] . ' ' . $student['lastName']);
            $studentEmail = $student['email'];
            $facultyCode = $student['faculty'];

            // Get active semester or student's assigned semester
            $semesterId = 0;
            $semesterName = '';
            $activeSem = null;
            $studentSemesterId = (int)($student['semesterID'] ?? 0);

            if ($studentSemesterId > 0) {
                $stmtSem = $pdo->prepare("SELECT * FROM tblsemester WHERE Id = ?");
                $stmtSem->execute([$studentSemesterId]);
                $activeSem = $stmtSem->fetch(PDO::FETCH_ASSOC) ?: null;
                if ($activeSem) {
                    $semesterId = $activeSem['Id'];
                    $semesterName = $activeSem['name'];
                }
            }

            // Fallback to faculty active semester if no student-specific semester
            if (!$semesterId && $facultyCode && function_exists('getActiveSemester')) {
                $activeSem = getActiveSemester($pdo, $facultyCode);
                if ($activeSem) {
                    $semesterId = $activeSem['Id'];
                    $semesterName = $activeSem['name'];
                }
            }

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
                    'lastMomentumAlertSent' => null,
                    'lastThresholdAlertSent' => null
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
                    $todayNepali = formatNepaliDate(date('Y-m-d'));
                    $semStr = $semesterName ? " for " . $semesterName : "";
                    $body = "Dear Parent/Guardian,\n\n" .
                            "We are writing to inform you that the student $studentName (Registration No: $studentID) was marked Absent on $todayNepali (BS) for the course $course (Unit: $unit)$semStr.\n\n" .
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

            // 3. Check if cumulative attendance for this course and unit falls below threshold
            $threshold = (int)get_setting($pdo, 'attendance_threshold', '75');

            // Fetch calendar dates scoped strictly to the resolved semester.
            // Never query without semesterID — it would merge dates from all semesters.
            if ($facultyCode && $semesterId) {
                $stmtCal = $pdo->prepare("SELECT classDate FROM tblfacultycalendar WHERE facultyCode = ? AND semesterID = ? AND classDate <= CURDATE() ORDER BY classDate ASC");
                $stmtCal->execute([$facultyCode, $semesterId]);
                $calendarDates = $stmtCal->fetchAll(PDO::FETCH_COLUMN);
            } else {
                $calendarDates = []; // no semester — fall through to attendance-table counts
            }

            if (count($calendarDates) > 0) {
                // Get present dates for this student in this course/unit
                $stmtPresentDates = $pdo->prepare("SELECT DISTINCT dateMarked FROM tblattendance 
                                                  WHERE studentRegistrationNumber = :reg 
                                                  AND attendanceStatus = 'Present'
                                                  AND course = :course 
                                                  AND unit = :unit");
                $stmtPresentDates->execute([':reg' => $studentID, ':course' => $course, ':unit' => $unit]);
                $presentDates = $stmtPresentDates->fetchAll(PDO::FETCH_COLUMN);

                $presentCount = count(array_intersect($calendarDates, $presentDates));
                $totalClasses = count($calendarDates);
            } else {
                // Fallback: Get total distinct dates marked for this course and unit
                $stmtTotal = $pdo->prepare("SELECT COUNT(DISTINCT dateMarked) as total FROM tblattendance WHERE course = :course AND unit = :unit");
                $stmtTotal->execute([':course' => $course, ':unit' => $unit]);
                $totalClasses = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'] ?: 1;

                // Get student's present count for this course and unit
                $stmtPresent = $pdo->prepare("SELECT COUNT(*) as present FROM tblattendance 
                                             WHERE studentRegistrationNumber = :reg 
                                             AND attendanceStatus = 'Present'
                                             AND course = :course
                                             AND unit = :unit");
                $stmtPresent->execute([
                    ':reg' => $studentID,
                    ':course' => $course,
                    ':unit' => $unit
                ]);
                $presentCount = $stmtPresent->fetch(PDO::FETCH_ASSOC)['present'];
            }

            $percentage = ($presentCount / $totalClasses) * 100;

            // Clear threshold warning state once the student recovers above threshold.
            if ($percentage >= $threshold && !empty($state['lastThresholdAlertSent'])) {
                $resetThresholdStmt = $pdo->prepare("UPDATE tblalertstate SET lastThresholdAlertSent = NULL WHERE studentRegistrationNumber = :studentID AND courseCode = :course AND unitCode = :unit");
                $resetThresholdStmt->execute([
                    ':studentID' => $studentID,
                    ':course' => $course,
                    ':unit' => $unit
                ]);
                $state['lastThresholdAlertSent'] = null;
            }

            // First month/grace period suppression — uses shared helper from analytics_logic.php
            $isFirstMonth = isSemesterInGracePeriod($pdo, $facultyCode, $semesterId);

            // Only send the critical threshold warning on an absence event.
            // If the student attends today, do not send the warning immediately.
            if ($isFirstMonth) {
                error_log("Alert Service: Suppressing CRITICAL Attendance Warning for $studentID on $course ($unit) - first month/grace period active.");
            } else if ($status === 'Absent' && $percentage < $threshold && $emailMode === 'auto') {
                $shouldSendThreshold = false;
                if (empty($state['lastThresholdAlertSent'])) {
                    $shouldSendThreshold = true;
                } else {
                    $lastSentTime = strtotime($state['lastThresholdAlertSent']);
                    $cooldown_threshold = 3 * 24 * 60 * 60; // 3-day cooldown
                    if (time() - $lastSentTime > $cooldown_threshold) {
                        $shouldSendThreshold = true;
                    }
                }

                if ($shouldSendThreshold) {
                    $subject = "SAS Portal: CRITICAL Attendance Warning";
                    $percentFormatted = round($percentage, 1);
                    $semStr = $semesterName ? " for " . $semesterName : "";
                    $body = "Dear $studentName,\n\n" .
                            "This is an automated warning regarding your attendance in the course $course (Unit: $unit)$semStr.\n\n" .
                            "Your current attendance for this class is $percentFormatted%, which is below the required minimum threshold of $threshold%.\n\n" .
                            "Please be warned that if your attendance remains below this threshold, you will NOT be eligible to sit for exams for this unit.\n\n" .
                            "Please make sure to attend all upcoming classes to improve your attendance percentage.\n\n" .
                            "Best regards,\n" .
                            "SAS Portal Attendance System";

                    if (trigger_alert_emailer($studentEmail, $subject, $body)) {
                        $updateThresholdStmt = $pdo->prepare("UPDATE tblalertstate SET lastThresholdAlertSent = :today WHERE studentRegistrationNumber = :studentID AND courseCode = :course AND unitCode = :unit");
                        $updateThresholdStmt->execute([
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
