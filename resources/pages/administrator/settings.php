<?php
// resources/pages/administrator/settings.php

require_once __DIR__ . '/../../pages/lecture/alert_service.php';
require_once __DIR__ . '/../../lib/nepali_calendar.php';
require_once __DIR__ . '/../../lib/analytics_logic.php';

$message = '';
$error = '';

// Handle Settings Update (Standard POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $threshold = filter_var($_POST['face_confidence_threshold'], FILTER_VALIDATE_INT);
    $email_mode = htmlspecialchars(trim($_POST['email_alerts_mode']));
    $attendance_threshold = filter_var($_POST['attendance_threshold'], FILTER_VALIDATE_INT);

    if ($threshold === false || $threshold < 0 || $threshold > 100) {
        $error = "Confidence threshold must be a number between 0 and 100.";
    } elseif ($attendance_threshold === false || $attendance_threshold < 0 || $attendance_threshold > 100) {
        $error = "Attendance threshold must be a number between 0 and 100.";
    } elseif (!in_array($email_mode, ['auto', 'manual', 'disabled'])) {
        $error = "Invalid email alerts mode selected.";
    } else {
        try {
            // Update confidence threshold
            $stmt = $pdo->prepare("INSERT INTO tblsettings (setting_key, setting_value) VALUES ('face_confidence_threshold', :val) ON DUPLICATE KEY UPDATE setting_value = :val");
            $stmt->execute([':val' => (string)$threshold]);

            // Update email alerts mode
            $stmt = $pdo->prepare("INSERT INTO tblsettings (setting_key, setting_value) VALUES ('email_alerts_mode', :val) ON DUPLICATE KEY UPDATE setting_value = :val");
            $stmt->execute([':val' => $email_mode]);

            // Update attendance threshold
            $stmt = $pdo->prepare("INSERT INTO tblsettings (setting_key, setting_value) VALUES ('attendance_threshold', :val) ON DUPLICATE KEY UPDATE setting_value = :val");
            $stmt->execute([':val' => (string)$attendance_threshold]);

            $message = "Settings updated successfully.";
        } catch (PDOException $e) {
            $error = "Error updating settings: " . $e->getMessage();
        }
    }
}

/*
// Handle Manual Email Dispatch (AJAX POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_manual_alert') {
    header('Content-Type: application/json');
    while (ob_get_level()) {
        ob_end_clean();
    }

    $student_id = htmlspecialchars(trim($_POST['student_id'] ?? ''));
    $course = htmlspecialchars(trim($_POST['course'] ?? ''));
    $unit = htmlspecialchars(trim($_POST['unit'] ?? ''));

    if (!$student_id || !$course || !$unit) {
        echo json_encode(['success' => false, 'message' => 'Missing student, course, or unit information.']);
        exit;
    }

    try {
        // Fetch student details
        $stmt = $pdo->prepare("SELECT firstName, lastName, email FROM tblstudents WHERE registrationNumber = ?");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student || empty($student['email'])) {
            echo json_encode(['success' => false, 'message' => 'Student email not found.']);
            exit;
        }

        $studentName = trim($student['firstName'] . ' ' . $student['lastName']);
        $studentEmail = $student['email'];

        // Fetch faculty code for the course
        $stmtFaculty = $pdo->prepare("SELECT f.facultyCode FROM tblcourse c JOIN tblfaculty f ON c.facultyID = f.Id WHERE c.courseCode = ?");
        $stmtFaculty->execute([$course]);
        $facultyCode = $stmtFaculty->fetchColumn();

        // Resolve active semester or fallback
        $semesterId = 0;
        $semesterName = '';
        if (function_exists('getActiveSemester')) {
            $activeSem = getActiveSemester($pdo, $facultyCode);
            if ($activeSem) {
                $semesterId = $activeSem['Id'];
                $semesterName = $activeSem['name'];
            }
        }

        // Fetch calendar dates scoped strictly to the resolved semester.
        // Never query without semesterID — it would merge all semesters' dates.
        if ($semesterId) {
            $stmtCal = $pdo->prepare("SELECT classDate FROM tblfacultycalendar WHERE facultyCode = ? AND semesterID = ? AND classDate <= CURDATE() ORDER BY classDate ASC");
            $stmtCal->execute([$facultyCode, $semesterId]);
            $calendarDates = $stmtCal->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $calendarDates = []; // no semester resolved — fall through to attendance-table counts
        }

        if (count($calendarDates) > 0) {
            // Get present dates for this student in this course/unit
            $stmtPresentDates = $pdo->prepare("SELECT DISTINCT dateMarked FROM tblattendance 
                                              WHERE studentRegistrationNumber = :reg 
                                              AND attendanceStatus = 'Present'
                                              AND course = :course 
                                              AND unit = :unit");
            $stmtPresentDates->execute([':reg' => $student_id, ':course' => $course, ':unit' => $unit]);
            $presentCount = count(array_intersect($calendarDates, $stmtPresentDates->fetchAll(PDO::FETCH_COLUMN)));
            $totalClasses = count($calendarDates);
        } else {
            // Fallback: Calculate attendance percentage for this class (course & unit)
            $stmtTotal = $pdo->prepare("SELECT COUNT(DISTINCT dateMarked) as total FROM tblattendance WHERE course = :course AND unit = :unit");
            $stmtTotal->execute([':course' => $course, ':unit' => $unit]);
            $totalClasses = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'] ?: 1;

            $stmtPresent = $pdo->prepare("SELECT COUNT(*) as present FROM tblattendance 
                             WHERE studentRegistrationNumber = :reg 
                             AND attendanceStatus = 'Present'
                             AND course = :course
                             AND unit = :unit");
            $stmtPresent->execute([':reg' => $student_id, ':course' => $course, ':unit' => $unit]);
            $presentCount = $stmtPresent->fetch(PDO::FETCH_ASSOC)['present'];
        }

        $percentage = ($presentCount / $totalClasses) * 100;
        $threshold = (int)get_setting($pdo, 'attendance_threshold', '75');
        
        $is_below_threshold = ($percentage < $threshold);
        $percentFormatted = round($percentage, 1);

        $semStr = $semesterName ? " for " . $semesterName : "";
        if ($is_below_threshold) {
            $subject = "SAS Portal: CRITICAL Attendance Warning";
            $body = "Dear Parent/Guardian,\n\n" .
                    "We are writing to inform you that the student $studentName (Registration No: $student_id) was marked Absent for the course $course (Unit: $unit)$semStr.\n\n" .
                    "Additionally, their cumulative attendance for this class has fallen to $percentFormatted%, which is below the minimum required threshold of $threshold%.\n\n" .
                    "Please be warned that if their attendance remains below this threshold, they will NOT be eligible to sit for exams for this unit.\n\n" .
                    "Please follow up with the student accordingly.\n\n" .
                    "Best regards,\n" .
                    "SAS Portal Attendance System";
        } else {
            $subject = "SAS Portal: Student Absence Notice";
            $body = "Dear Parent/Guardian,\n\n" .
                    "We are writing to inform you that the student $studentName (Registration No: $student_id) was marked Absent for the course $course (Unit: $unit)$semStr.\n\n" .
                    "Please follow up with the student accordingly.\n\n" .
                    "Best regards,\n" .
                    "SAS Portal Attendance System";
        }

        // Dispatch email using trigger_alert_emailer from alert_service
        $sent = trigger_alert_emailer($studentEmail, $subject, $body);

        if ($sent) {
            $today = date('Y-m-d H:i:s');
            // Update tblalertstate
            if ($is_below_threshold) {
                $stmt = $pdo->prepare("INSERT INTO tblalertstate (studentRegistrationNumber, courseCode, unitCode, lastAbsentAlertSent, lastThresholdAlertSent) VALUES (:student_id, :course, :unit, :today, :today) ON DUPLICATE KEY UPDATE lastAbsentAlertSent = :today, lastThresholdAlertSent = :today");
            } else {
                $stmt = $pdo->prepare("INSERT INTO tblalertstate (studentRegistrationNumber, courseCode, unitCode, lastAbsentAlertSent) VALUES (:student_id, :course, :unit, :today) ON DUPLICATE KEY UPDATE lastAbsentAlertSent = :today");
            }
            $stmt->execute([
                ':student_id' => $student_id,
                ':course' => $course,
                ':unit' => $unit,
                ':today' => $today
            ]);

            echo json_encode(['success' => true, 'message' => 'Email sent and logged successfully.', 'date' => $today]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to dispatch email. Check SMTP config or sent_emails.log.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
    exit;
}
*/

// Retrieve current configurations
$current_threshold = get_setting($pdo, 'face_confidence_threshold', '65');
$current_email_mode = get_setting($pdo, 'email_alerts_mode', 'auto');
$current_attendance_threshold = get_setting($pdo, 'attendance_threshold', '75');

// Retrieve recent absences for the dispatcher
$absences = [];
try {
    $sql = "SELECT 
                a.attendanceID,
                a.studentRegistrationNumber,
                a.course,
                a.unit,
                a.dateMarked,
                s.firstName,
                s.lastName,
                s.email,
                als.lastAbsentAlertSent,
                (SELECT COUNT(DISTINCT a2.dateMarked) FROM tblattendance a2 WHERE a2.course = a.course AND a2.unit = a.unit) as total_classes,
                (SELECT COUNT(*) FROM tblattendance a3 WHERE a3.studentRegistrationNumber = a.studentRegistrationNumber AND a3.attendanceStatus = 'Present' AND a3.course = a.course AND a3.unit = a.unit) as present_classes
            FROM tblattendance a
            INNER JOIN tblstudents s ON a.studentRegistrationNumber = s.registrationNumber
            LEFT JOIN tblalertstate als ON a.studentRegistrationNumber = als.studentRegistrationNumber 
                AND a.course = als.courseCode 
                AND a.unit = als.unitCode
            WHERE a.attendanceStatus = 'Absent'
            ORDER BY a.dateMarked DESC
            LIMIT 25";
    $stmt = $pdo->query($sql);
    $absences = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Ignore database query failure quietly
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="resources/images/logo/face logo.png" rel="icon">
    <title>System Settings</title>
    <link rel="stylesheet" href="resources/assets/css/admin_styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.css" rel="stylesheet">
    <script src="resources/assets/javascript/nepali_calendar.js"></script>
    <style>
        .settings-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-bottom: 30px;
        }

        .settings-card h3 {
            font-size: 1.25rem;
            color: #333333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 2px solid #f0f2f5;
            padding-bottom: 10px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .setting-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .setting-field label {
            font-weight: 600;
            color: #555555;
            font-size: 0.9rem;
        }

        .setting-field input, .setting-field select {
            padding: 12px 16px;
            border: 1.5px solid #dcdfe6;
            border-radius: 8px;
            font-size: 0.95rem;
            color: #333333;
            background-color: #fafbfc;
            transition: all 0.3s ease;
        }

        .setting-field input:focus, .setting-field select:focus {
            border-color: #409eff;
            background-color: #ffffff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(64, 158, 255, 0.1);
        }

        .alert-info-text {
            font-size: 0.82rem;
            color: #8c939d;
            margin-top: 4px;
            line-height: 1.4;
        }

        .save-settings-btn {
            background-color: #409eff;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .save-settings-btn:hover {
            background-color: #66b1ff;
            transform: translateY(-1px);
        }

        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge-pending {
            background-color: #fef0f0;
            color: #f56c6c;
            border: 1px solid #fde2e2;
        }

        .badge-sent {
            background-color: #f0f9eb;
            color: #67c23a;
            border: 1px solid #e1f3d8;
        }

        .btn-dispatch {
            background-color: #e6a23c;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .btn-dispatch:hover {
            background-color: #ebb563;
        }

        .btn-dispatch:disabled {
            background-color: #c8c9cc;
            cursor: not-allowed;
        }

        .toast {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .toast-success {
            background-color: #f0f9eb;
            color: #67c23a;
            border: 1px solid #e1f3d8;
        }

        .toast-error {
            background-color: #fef0f0;
            color: #f56c6c;
            border: 1px solid #fde2e2;
        }
    </style>
</head>

<body>
    <?php include 'includes/topbar.php'; ?>
    <section class="main">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main--content">
            
            <div class="overview">
                <div class="title">
                    <h2 class="section--title">System Configuration</h2>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="toast toast-success">
                    <i class="ri-checkbox-circle-fill"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="toast toast-error">
                    <i class="ri-error-warning-fill"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Configuration Card -->
            <div class="settings-card">
                <h3><i class="ri-settings-4-line"></i> Global Parameters</h3>
                <form method="POST" action="">
                    <div class="form-grid">
                        <div class="setting-field">
                            <label for="face_confidence_threshold">Face Recognition Confidence Threshold (%)</label>
                            <input type="number" name="face_confidence_threshold" id="face_confidence_threshold" min="0" max="100" value="<?php echo htmlspecialchars($current_threshold); ?>" required>
                            <p class="alert-info-text">Minimum matching confidence score (0-100) required to recognize a student's face. Defaults to 65%.</p>
                        </div>
                        <div class="setting-field">
                            <label for="email_alerts_mode">Email Notification Mode</label>
                            <select name="email_alerts_mode" id="email_alerts_mode" required>
                                <option value="auto" <?php echo $current_email_mode === 'auto' ? 'selected' : ''; ?>>Auto (Stateful Alert Suppression)</option>
                                <option value="manual" <?php echo $current_email_mode === 'manual' ? 'selected' : ''; ?>>Manual (Dispatch via Dashboard)</option>
                                <option value="disabled" <?php echo $current_email_mode === 'disabled' ? 'selected' : ''; ?>>Disabled (No Emails Sent)</option>
                            </select>
                            <p class="alert-info-text">Select how email alerts are handled. Auto uses 3-day suppressive rules; Manual lets you review and send below; Disabled stops all alerts.</p>
                        </div>
                        <div class="setting-field">
                            <label for="attendance_threshold">Minimum Attendance Threshold (%)</label>
                            <input type="number" name="attendance_threshold" id="attendance_threshold" min="0" max="100" value="<?php echo htmlspecialchars($current_attendance_threshold); ?>" required>
                            <p class="alert-info-text">Minimum attendance percentage required for students to be eligible for exams. Defaults to 75%.</p>
                        </div>
                    </div>
                    <button type="submit" name="save_settings" class="save-settings-btn">
                        <i class="ri-save-line"></i> Save Configurations
                    </button>
                </form>
            </div>

            <!--
            Manual Email Dispatcher (temporarily disabled)
            <div class="settings-card">
                <h3><i class="ri-mail-send-line"></i> Manual Email Dispatcher</h3>
                <p style="color: #606266; font-size: 0.88rem; margin-bottom: 20px;">
                    The manual email dispatcher has been disabled for now. This section is retained in the file for future reactivation.
                </p>
            </div>
            -->

        </div>
    </section>

    <?php js_asset(["active_link"]) ?>

    <!-- Manual dispatcher UI and JavaScript have been commented out while this feature is disabled. -->
</body>

</html>
