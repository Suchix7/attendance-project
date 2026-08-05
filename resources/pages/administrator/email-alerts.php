<?php
// resources/pages/administrator/email-alerts.php

require_once __DIR__ . '/../../pages/lecture/alert_service.php';
require_once __DIR__ . '/../../lib/analytics_logic.php';

// Handle AJAX: Fetch student detailed attendance breakdown
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_student_details') {
    header('Content-Type: application/json');
    while (ob_get_level()) {
        ob_end_clean();
    }

    $student_id = htmlspecialchars(trim($_POST['student_id'] ?? ''));
    $semester_id = isset($_POST['semester_id']) ? (int)$_POST['semester_id'] : 0;

    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'Missing student ID.']);
        exit;
    }

    try {
        // Fetch student details
        $stmt = $pdo->prepare("SELECT firstName, lastName, email, faculty FROM tblstudents WHERE registrationNumber = ?");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            echo json_encode(['success' => false, 'message' => 'Student not found.']);
            exit;
        }

        $studentName = trim($student['firstName'] . ' ' . $student['lastName']);
        $studentEmail = $student['email'];
        $facultyCode = $student['faculty'];

        if (!$semester_id) {
            // First check student's assigned semester
            $stmtStudentSem = $pdo->prepare("SELECT semesterID FROM tblstudents WHERE registrationNumber = ?");
            $stmtStudentSem->execute([$student_id]);
            $studentSemId = (int)$stmtStudentSem->fetchColumn();
            
            if ($studentSemId > 0) {
                $semester_id = $studentSemId;
            } else if ($facultyCode) {
                $activeSem = getActiveSemester($pdo, $facultyCode);
                if ($activeSem) {
                    $semester_id = $activeSem['Id'];
                }
            }
        }

        $semesterName = '';
        if ($semester_id) {
            $stmtSem = $pdo->prepare("SELECT name FROM tblsemester WHERE Id = ?");
            $stmtSem->execute([$semester_id]);
            $semesterName = $stmtSem->fetchColumn();
        }

        // Get student's overall attendance using risk analytics logic
        $risk = calculateAttendanceRisk($student_id, null, $semester_id);
        $threshold = (int)get_setting($pdo, 'attendance_threshold', '75');

        // Fetch all unique classes (course) this student has attendance records for
        $stmtClasses = $pdo->prepare("SELECT DISTINCT course FROM tblattendance WHERE studentRegistrationNumber = ?");
        $stmtClasses->execute([$student_id]);
        $classRows = $stmtClasses->fetchAll(PDO::FETCH_ASSOC);

        $classes = [];
        foreach ($classRows as $c) {
            $course = $c['course'];

            // Course Name
            $stmtCourse = $pdo->prepare("SELECT name FROM tblcourse WHERE courseCode = ?");
            $stmtCourse->execute([$course]);
            $courseName = $stmtCourse->fetchColumn() ?: $course;

            // Fetch calendar dates up to today for this faculty and semester
            // Scope calendar strictly to the student's semester — never mix semesters
            if ($semester_id) {
                $stmtCal = $pdo->prepare("SELECT classDate FROM tblfacultycalendar WHERE facultyCode = ? AND semesterID = ? AND classDate <= CURDATE() ORDER BY classDate ASC");
                $stmtCal->execute([$facultyCode, $semester_id]);
                $calendarDates = $stmtCal->fetchAll(PDO::FETCH_COLUMN);
            } else {
                $calendarDates = []; // no semester — fall through to attendance-table counts
            }

            if (count($calendarDates) > 0) {
                // Get present dates for this student in this course
                $stmtPresentDates = $pdo->prepare("SELECT DISTINCT dateMarked FROM tblattendance 
                                                  WHERE studentRegistrationNumber = :reg 
                                                  AND attendanceStatus = 'Present'
                                                  AND course = :course");
                $stmtPresentDates->execute([':reg' => $student_id, ':course' => $course]);
                $presentDates = $stmtPresentDates->fetchAll(PDO::FETCH_COLUMN);

                $absentDates = array_diff($calendarDates, $presentDates);
                usort($absentDates, function($a, $b) { return strcmp($b, $a); });
                $absentDates = array_values($absentDates); // Reset keys

                $present = count(array_intersect($calendarDates, $presentDates));
                $total = count($calendarDates);
            } else {
                // Fallback: Total class dates
                $stmtTotal = $pdo->prepare("SELECT COUNT(DISTINCT dateMarked) FROM tblattendance WHERE course = ?");
                $stmtTotal->execute([$course]);
                $total = (int)$stmtTotal->fetchColumn() ?: 1;

                // Present count
                $stmtPresent = $pdo->prepare("SELECT COUNT(*) FROM tblattendance WHERE studentRegistrationNumber = ? AND attendanceStatus = 'Present' AND course = ?");
                $stmtPresent->execute([$student_id, $course]);
                $present = (int)$stmtPresent->fetchColumn();

                // Absent Dates
                $stmtAbsences = $pdo->prepare("SELECT dateMarked FROM tblattendance WHERE studentRegistrationNumber = ? AND attendanceStatus = 'Absent' AND course = ? ORDER BY dateMarked DESC");
                $stmtAbsences->execute([$student_id, $course]);
                $absentDates = $stmtAbsences->fetchAll(PDO::FETCH_COLUMN);
            }

            $pct = $total > 0 ? round(($present / $total) * 100, 1) : 0;

            $classes[] = [
                'course' => $course,
                'courseName' => $courseName,
                'total' => $total,
                'present' => $present,
                'percentage' => $pct,
                'absentDates' => $absentDates,
                'isBelowThreshold' => ($pct < $threshold)
            ];
        }

        echo json_encode([
            'success' => true,
            'studentName' => $studentName,
            'studentEmail' => $studentEmail,
            'overallPercentage' => $risk['percentage'],
            'overallPresent' => $risk['present'],
            'overallTotal' => $risk['total'],
            'threshold' => $threshold,
            'semesterId' => $semester_id,
            'semesterName' => $semesterName,
            'classes' => $classes
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle AJAX: Dispatch warning email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_detailed_alert') {
    header('Content-Type: application/json');
    while (ob_get_level()) {
        ob_end_clean();
    }

    $student_id = htmlspecialchars(trim($_POST['student_id'] ?? ''));
    $subject = htmlspecialchars(trim($_POST['subject'] ?? ''));
    $body = trim($_POST['body'] ?? '');
    $selected_classes = $_POST['selected_classes'] ?? []; // Array of courseCode strings

    if (!$student_id || !$subject || !$body) {
        echo json_encode(['success' => false, 'message' => 'Missing student, subject, or email content.']);
        exit;
    }

    try {
        // Fetch student details
        $stmt = $pdo->prepare("SELECT email FROM tblstudents WHERE registrationNumber = ?");
        $stmt->execute([$student_id]);
        $studentEmail = $stmt->fetchColumn();

        if (!$studentEmail) {
            echo json_encode(['success' => false, 'message' => 'Student email not found.']);
            exit;
        }

        // Send email using python wrapper
        $sent = trigger_alert_emailer($studentEmail, $subject, $body);

        if ($sent) {
            $today = date('Y-m-d H:i:s');
            // Log states in database for each selected course
            foreach ($selected_classes as $course) {
                $stmt = $pdo->prepare("INSERT INTO tblalertstate (studentRegistrationNumber, courseCode, lastThresholdAlertSent) VALUES (:reg, :course, :today) ON DUPLICATE KEY UPDATE lastThresholdAlertSent = :today");
                $stmt->execute([
                    ':reg' => $student_id,
                    ':course' => $course,
                    ':today' => $today
                ]);
            }

            echo json_encode(['success' => true, 'message' => 'Warning email sent successfully!', 'date' => $today]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to dispatch email. Check SMTP settings.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
    exit;
}

// Retrieve Student List with Overall Attendance Risk
$selectedFaculty = isset($_GET['faculty']) ? htmlspecialchars(trim($_GET['faculty'])) : '';
$selectedSemesterId = isset($_GET['semester']) ? (int)$_GET['semester'] : 0;

// Only query if GET was explicitly submitted (filter applied)
$filterApplied = isset($_GET['faculty']) || isset($_GET['semester']);

$students = [];
$faculties = [];
try {
    $faculties = $pdo->query("SELECT * FROM tblfaculty ORDER BY facultyName")->fetchAll(PDO::FETCH_ASSOC);

    if ($filterApplied) {
        $sql = "SELECT s.registrationNumber, s.firstName, s.lastName, s.email, s.faculty, s.courseCode, c.name as courseName 
                FROM tblstudents s
                LEFT JOIN tblcourse c ON s.courseCode = c.courseCode";
        
        $where = [];
        $params = [];
        if ($selectedFaculty) {
            $where[] = "s.faculty = ?";
            $params[] = $selectedFaculty;
        }
        if ($selectedSemesterId) {
            $where[] = "s.semesterID = ?";
            $params[] = $selectedSemesterId;
        }
        if ($where) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY s.faculty, s.firstName, s.lastName";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rawStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rawStudents as $row) {
            $risk = calculateAttendanceRisk($row['registrationNumber'], null, $selectedSemesterId);
            $row['overall_pct'] = $risk['percentage'];
            $row['present'] = $risk['present'];
            $row['total'] = $risk['total'];
            $row['level'] = $risk['level'];
            $row['color'] = $risk['color'];

            // Fetch the most recent manual dispatch timestamp for this student
            $stmtAlert = $pdo->prepare(
                "SELECT GREATEST(
                    COALESCE(lastThresholdAlertSent, '1970-01-01'),
                    COALESCE(lastAbsentAlertSent,   '1970-01-01'),
                    COALESCE(lastMomentumAlertSent,  '1970-01-01')
                ) as lastSent
                FROM tblalertstate
                WHERE studentRegistrationNumber = ?
                ORDER BY lastSent DESC
                LIMIT 1"
            );
            $stmtAlert->execute([$row['registrationNumber']]);
            $alertRow = $stmtAlert->fetch(PDO::FETCH_ASSOC);
            $lastSent = ($alertRow && $alertRow['lastSent'] && $alertRow['lastSent'] !== '1970-01-01 00:00:00')
                ? $alertRow['lastSent'] : null;
            $row['last_email_sent'] = $lastSent;

            $students[] = $row;
        }
    }
} catch (PDOException $e) {
    $error = "Error fetching student directory: " . $e->getMessage();
}

$current_attendance_threshold = get_setting($pdo, 'attendance_threshold', '75');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="resources/images/logo/face logo.png" rel="icon">
    <title>Email Warning Dispatcher</title>
    <link rel="stylesheet" href="resources/assets/css/admin_styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.css" rel="stylesheet">
    <script src="resources/assets/javascript/nepali_calendar.js"></script>
    <style>
        .student-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-bottom: 30px;
        }

        .student-pct-bar {
            height: 8px;
            background-color: #f1f5f9;
            border-radius: 4px;
            overflow: hidden;
            width: 120px;
            margin-top: 4px;
        }

        .student-pct-fill {
            height: 100%;
            border-radius: 4px;
        }

        /* Modal Backdrop */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(6px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal-overlay.active {
            display: flex;
            opacity: 1;
        }

        /* Modal Container */
        .modal-container {
            background: #ffffff;
            border-radius: 16px;
            width: 820px;
            max-width: 95%;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            padding: 30px;
            position: relative;
            transform: translateY(20px);
            transition: transform 0.3s ease;
        }

        .modal-overlay.active .modal-container {
            transform: translateY(0);
        }

        .close-modal-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: #f1f5f9;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            transition: all 0.2s ease;
        }

        .close-modal-btn:hover {
            background: #e2e8f0;
            color: #1e293b;
        }

        .modal-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-top: 20px;
        }

        .classes-selector {
            border-right: 1px solid #e2e8f0;
            padding-right: 25px;
            max-height: 480px;
            overflow-y: auto;
        }

        .class-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 12px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .class-item:hover {
            border-color: #cbd5e1;
            background: #f1f5f9;
        }

        .class-item-header {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .class-item-checkbox {
            margin-top: 4px;
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        .class-info {
            flex-grow: 1;
        }

        .class-title {
            font-weight: 600;
            font-size: 0.9rem;
            color: #1e293b;
        }

        .class-code {
            font-size: 0.78rem;
            color: #64748b;
        }

        .class-pct-badge {
            font-size: 0.78rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
            display: inline-block;
        }

        .badge-safe {
            background-color: #f0f9eb;
            color: #67c23a;
            border: 1px solid #e1f3d8;
        }

        .badge-warning {
            background-color: #fdf6ec;
            color: #e6a23c;
            border: 1px solid #f5dab1;
        }

        .badge-critical {
            background-color: #fef0f0;
            color: #f56c6c;
            border: 1px solid #fde2e2;
        }

        .absences-toggle {
            font-size: 0.78rem;
            color: #409eff;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 4px;
            user-select: none;
            width: fit-content;
        }

        .absences-dates-list {
            display: none;
            font-size: 0.75rem;
            color: #64748b;
            background: #ffffff;
            border-radius: 6px;
            padding: 8px;
            border: 1px solid #e2e8f0;
            margin-top: 4px;
        }

        .absences-dates-list.active {
            display: block;
        }

        .preview-pane {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .preview-pane label {
            font-weight: 600;
            color: #475569;
            font-size: 0.88rem;
        }

        .preview-pane input, .preview-pane textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            font-size: 0.88rem;
            font-family: inherit;
            color: #334155;
            background-color: #f8fafc;
            transition: all 0.2s ease;
        }

        .preview-pane input:focus, .preview-pane textarea:focus {
            border-color: #409eff;
            background-color: #ffffff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(64, 158, 255, 0.15);
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 25px;
            border-top: 1px solid #e2e8f0;
            padding-top: 20px;
        }

        .btn-cancel {
            background-color: #f1f5f9;
            color: #475569;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-cancel:hover {
            background-color: #e2e8f0;
            color: #1e293b;
        }

        .btn-send {
            background-color: #409eff;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .btn-send:hover {
            background-color: #66b1ff;
        }

        .btn-send:disabled {
            background-color: #94a3b8;
            cursor: not-allowed;
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
                    <h2 class="section--title">Email Warning Dispatcher</h2>
                </div>
            </div>

            <!-- Student Directory Card -->
            <div class="student-card">
                <p style="color: #606266; font-size: 0.88rem; margin-bottom: 20px;">
                    Below is the list of all registered students with their current overall attendance. Click "Review & Send" to open a detailed class-by-class report and dispatch an exam eligibility warning.
                </p>

                <!-- Faculty and Semester Selection Form -->
                <form method="GET" action="" style="display: flex; gap: 15px; align-items: center; margin-bottom: 25px; flex-wrap: wrap; background: #f8fafc; padding: 15px; border-radius: 8px; border: 1.5px solid #cbd5e1;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <label for="filter_faculty" style="font-weight: 600; color: #475569; font-size: 0.88rem; white-space: nowrap;">Faculty:</label>
                        <select name="faculty" id="filter_faculty" onchange="document.getElementById('filter_semester').value='0'; this.form.submit()" style="padding: 8px 12px; border-radius: 6px; border: 1.5px solid #cbd5e1; background: white; font-size: 0.88rem; min-width: 160px; color: #1e293b;">
                            <option value="">-- All Faculties --</option>
                            <?php foreach ($faculties as $f): ?>
                                <option value="<?php echo htmlspecialchars($f['facultyCode']); ?>" <?php if ($selectedFaculty === $f['facultyCode']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($f['facultyName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <label for="filter_semester" style="font-weight: 600; color: #475569; font-size: 0.88rem; white-space: nowrap;">Semester:</label>
                        <select name="semester" id="filter_semester" onchange="this.form.submit()" style="padding: 8px 12px; border-radius: 6px; border: 1.5px solid #cbd5e1; background: white; font-size: 0.88rem; min-width: 220px; color: #1e293b;">
                            <option value="0">-- Active/Default Semester --</option>
                            <?php if (!empty($selectedFaculty)): ?>
                                <?php 
                                $sems = getSemestersByFaculty($pdo, $selectedFaculty);
                                foreach ($sems as $sem): ?>
                                    <option value="<?php echo $sem['Id']; ?>" <?php if ($selectedSemesterId == $sem['Id']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($sem['name']) . ($sem['isActive'] ? ' (Active)' : ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <?php if ($selectedFaculty || $selectedSemesterId): ?>
                        <a href="email-alerts" style="font-size: 0.88rem; color: #ef4444; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; font-weight: 600;"><i class="ri-refresh-line"></i> Clear Filters</a>
                    <?php endif; ?>
                </form>

                <div class="table-container" style="box-shadow: none; padding: 0;">
                    <div class="table">
                        <table style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Registration No</th>
                                    <th>Course</th>
                                    <th>Overall Attendance</th>
                                    <th>Risk Status</th>
                                    <th>Email Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$filterApplied): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 40px 20px;">
                                            <div style="display: flex; flex-direction: column; align-items: center; gap: 12px; color: #94a3b8;">
                                                <i class="ri-filter-3-line" style="font-size: 2.5rem; color: #cbd5e1;"></i>
                                                <div style="font-size: 1rem; font-weight: 600; color: #64748b;">Select a Faculty or Semester to load students</div>
                                                <div style="font-size: 0.85rem;">Use the filters above to search for students and review their attendance.</div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php elseif (!empty($students)): ?>
                                    <?php 
                                    $currentFaculty = null;
                                    foreach ($students as $row): 
                                        if ($currentFaculty !== $row['faculty']) {
                                            $currentFaculty = $row['faculty'];
                                            $facName = '';
                                            foreach ($faculties as $f) {
                                                if ($f['facultyCode'] === $currentFaculty) {
                                                    $facName = $f['facultyName'];
                                                    break;
                                                }
                                            }
                                            if (!$facName) $facName = $currentFaculty;
                                            echo "<tr style='background-color: #f8fafc; font-weight: 700; color: #475569;'><td colspan='7' style='padding: 12px 20px; font-size: 0.95rem; border-bottom: 2px solid #cbd5e1;'><i class='ri-graduation-cap-line'></i> Faculty of " . htmlspecialchars($facName) . "</td></tr>";
                                        }
                                    ?>
                                        <tr data-reg="<?php echo htmlspecialchars($row['registrationNumber']); ?>">
                                            <td>
                                                <div style="font-weight: 600;"><?php echo htmlspecialchars($row['firstName'] . ' ' . $row['lastName']); ?></div>
                                                <div style="font-size: 0.8rem; color: #909399;"><?php echo htmlspecialchars($row['email']); ?></div>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['registrationNumber']); ?></td>
                                            <td><?php echo htmlspecialchars($row['courseName'] ?: $row['courseCode']); ?></td>
                                            <td>
                                                <div style="font-weight: 600;"><?php echo $row['overall_pct']; ?>%</div>
                                                <div class="student-pct-bar">
                                                    <div class="student-pct-fill" style="width: <?php echo $row['overall_pct']; ?>%; background-color: <?php echo $row['color']; ?>;"></div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge" style="background-color: <?php echo $row['color']; ?>30; color: <?php echo $row['color']; ?>; border: 1px solid <?php echo $row['color']; ?>50;">
                                                    <?php echo $row['level']; ?>
                                                </span>
                                            </td>
                                            <td class="email-status-cell">
                                                <?php if ($row['last_email_sent']): ?>
                                                    <div style="display:flex; flex-direction:column; gap:3px;">
                                                        <span style="display:inline-flex; align-items:center; gap:5px; background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0; border-radius:5px; padding:3px 8px; font-size:0.78rem; font-weight:600; width:fit-content;">
                                                            <i class="ri-mail-check-line"></i> Dispatched
                                                        </span>
                                                        <span style="font-size:0.75rem; color:#64748b;">
                                                            <?php
                                                                $dt = new DateTime($row['last_email_sent']);
                                                                echo $dt->format('M j, Y g:i A');
                                                            ?>
                                                        </span>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="display:inline-flex; align-items:center; gap:5px; background:#f8fafc; color:#94a3b8; border:1px solid #e2e8f0; border-radius:5px; padding:3px 8px; font-size:0.78rem; font-weight:600;">
                                                        <i class="ri-mail-line"></i> Never Sent
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                              <button class="btn-dispatch" 
                                        onclick="openAlertModal('<?php echo $row['registrationNumber']; ?>')" 
                                        style="background-color: #2563eb; color: #ffffff; border: none; padding: 10px 20px; font-size: 14px; font-weight: 500; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: background-color 0.2s;">
                                    <i class="ri-mail-send-line"></i> Review & Send
                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; color: #909399; padding: 20px;">No students found matching the selected filters.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- Detailed Alert Dialog Modal -->
    <div class="modal-overlay" id="alert_modal_overlay">
        <div class="modal-container">
            <button class="close-modal-btn" onclick="closeAlertModal()">
                <i class="ri-close-line"></i>
            </button>
            <h3 style="font-size: 1.25rem; color: #1e293b; margin-bottom: 8px;">Review & Dispatch Warning</h3>
            <p style="font-size: 0.85rem; color: #64748b;" id="modal_student_subtitle">Loading student details...</p>
            
            <div id="modal_loading_spinner" style="text-align: center; padding: 50px 0;">
                <i class="ri-loader-4-line" style="font-size: 2rem; color: #409eff; animation: spin 1s infinite linear; display: inline-block;"></i>
                <p style="margin-top: 10px; color: #64748b;">Fetching attendance breakdown...</p>
            </div>

            <div id="modal_main_content" style="display: none;">
                <!-- Overall Attendance Progress Banner -->
                <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; margin: 20px 0; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <div style="font-size: 0.85rem; color: #64748b; font-weight: 600;">Aggregate Attendance</div>
                        <div style="font-size: 1.3rem; font-weight: 800; color: #1e293b;" id="modal_overall_pct_lbl">70%</div>
                    </div>
                    <div style="flex-grow: 1; margin: 0 30px;">
                        <div style="height: 10px; background-color: #cbd5e1; border-radius: 5px; overflow: hidden; width: 100%;">
                            <div id="modal_overall_pct_fill" style="height: 100%; border-radius: 5px; background-color: #ef4444; width: 70%;"></div>
                        </div>
                    </div>
                    <div>
                        <span id="modal_overall_level_badge" class="badge">Critical</span>
                    </div>
                </div>

                <div class="modal-grid">
                    <!-- Left: Classes Selector -->
                    <div class="classes-selector">
                        <h4 style="font-size: 0.95rem; color: #1e293b; margin-bottom: 12px; font-weight: 700;">Select Classes to Warn</h4>
                        <p style="font-size: 0.78rem; color: #64748b; margin-bottom: 15px;">Check the specific courses you want to highlight in the warning email.</p>
                        <div id="classes_checkbox_list">
                            <!-- Populated via JS -->
                        </div>
                    </div>

                    <!-- Right: Email Preview -->
                    <div class="preview-pane">
                        <div>
                            <label for="email_subject">Email Subject</label>
                            <input type="text" id="email_subject" value="CMS Portal: CRITICAL Attendance Warning">
                        </div>
                        <div>
                            <label for="email_body_preview">Email Warning Content</label>
                            <textarea id="email_body_preview" rows="14" style="resize: vertical;"></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn-cancel" onclick="closeAlertModal()">Cancel</button>
                    <button class="btn-send" id="btn_send_email" onclick="submitDetailedAlert()">
                        <i class="ri-send-plane-fill"></i> Send Warning Email
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php js_asset(["active_link"]) ?>

    <script>
        let currentStudentId = '';
        let currentStudentData = null;

        function openAlertModal(studentId) {
            currentStudentId = studentId;
            const overlay = document.getElementById('alert_modal_overlay');
            const spinner = document.getElementById('modal_loading_spinner');
            const mainContent = document.getElementById('modal_main_content');
            const subtitle = document.getElementById('modal_student_subtitle');

            subtitle.textContent = "Loading attendance records for " + studentId + "...";
            spinner.style.display = 'block';
            mainContent.style.display = 'none';
            overlay.classList.add('active');

            const filterSemester = document.getElementById('filter_semester') ? document.getElementById('filter_semester').value : '0';

            const formData = new FormData();
            formData.append('action', 'get_student_details');
            formData.append('student_id', studentId);
            formData.append('semester_id', filterSemester);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    currentStudentData = data;
                    subtitle.innerHTML = `Student: <strong>${data.studentName}</strong> | Email: <strong>${data.studentEmail}</strong> | Reg: <strong>${studentId}</strong>`;
                    
                    // Update overall statistics banner
                    const overallPctLbl = document.getElementById('modal_overall_pct_lbl');
                    const overallPctFill = document.getElementById('modal_overall_pct_fill');
                    const overallBadge = document.getElementById('modal_overall_level_badge');
                    
                    overallPctLbl.textContent = `${data.overallPercentage}% (${data.overallPresent}/${data.overallTotal} Classes)`;
                    overallPctFill.style.width = `${data.overallPercentage}%`;
                    
                    let badgeClass = 'badge-safe';
                    let badgeLabel = 'Safe';
                    let color = '#22c55e';
                    
                    const threshold = data.threshold;
                    if (data.overallPercentage < threshold) {
                        badgeClass = 'badge-critical';
                        badgeLabel = 'Critical';
                        color = '#ef4444';
                    } else if (data.overallPercentage < threshold + 10) {
                        badgeClass = 'badge-warning';
                        badgeLabel = 'Warning';
                        color = '#f59e0b';
                    }
                    
                    overallPctFill.style.backgroundColor = color;
                    overallBadge.className = `badge ${badgeClass}`;
                    overallBadge.textContent = badgeLabel;

                    // Build class selector list
                    const listContainer = document.getElementById('classes_checkbox_list');
                    listContainer.innerHTML = '';

                    if (data.classes.length === 0) {
                        listContainer.innerHTML = '<p style="font-size:0.85rem; color:#94a3b8; text-align:center; padding:20px;">No classes recorded for this student.</p>';
                    } else {
                        data.classes.forEach((c, index) => {
                            const pctBadgeClass = c.isBelowThreshold ? 'badge-critical' : (c.percentage < threshold + 10 ? 'badge-warning' : 'badge-safe');
                            const item = document.createElement('div');
                            item.className = 'class-item';
                            
                            // Checkbox status: auto check if it is below threshold (Critical)
                            const isChecked = c.isBelowThreshold ? 'checked' : '';
                            
                            // Absences display — convert Gregorian dates to Nepali BS
                            let absencesHtml = '';
                            if (c.absentDates && c.absentDates.length > 0) {
                                const formattedAbsentDates = c.absentDates.map(d => NepaliCalendar.formatNepaliDate(d, 'short'));
                                absencesHtml = `
                                    <div class="absences-toggle" onclick="toggleAbsenceList(${index})">
                                        <i class="ri-calendar-event-line"></i> View absences (${c.absentDates.length})
                                    </div>
                                    <div class="absences-dates-list" id="absences-list-${index}">
                                        <strong>Absent Dates (BS):</strong><br>
                                        ${formattedAbsentDates.join(', ')}
                                    </div>
                                `;
                            } else {
                                absencesHtml = `<div style="font-size:0.75rem; color:#64748b;"><i class="ri-check-checkbox-line" style="color:#22c55e;"></i> Full Attendance</div>`;
                            }

                            item.innerHTML = `
                                <div class="class-item-header">
                                    <input type="checkbox" class="class-item-checkbox class-checkbox" id="class_cb_${index}" data-index="${index}" ${isChecked} onchange="updatePreview()">
                                    <div class="class-info">
                                        <label for="class_cb_${index}" class="class-title" style="cursor:pointer;">${c.courseName}</label>
                                        <div class="class-code">${c.course}</div>
                                        <div style="margin-top:4px;">
                                            <span class="class-pct-badge ${pctBadgeClass}">Attendance: ${c.percentage}% (${c.present}/${c.total})</span>
                                        </div>
                                    </div>
                                </div>
                                ${absencesHtml}
                            `;
                            listContainer.appendChild(item);
                        });
                    }

                    spinner.style.display = 'none';
                    mainContent.style.display = 'block';
                    
                    // Initialize preview content
                    updatePreview();
                } else {
                    alert('Error: ' + data.message);
                    closeAlertModal();
                }
            })
            .catch(error => {
                console.error(error);
                alert('An error occurred loading student details.');
                closeAlertModal();
            });
        }

        function toggleAbsenceList(index) {
            const list = document.getElementById(`absences-list-${index}`);
            list.classList.toggle('active');
        }

        function updatePreview() {
            if (!currentStudentData) return;

            const name = currentStudentData.studentName;
            const threshold = currentStudentData.threshold;
            const overallPct = currentStudentData.overallPercentage;
            const overallPresent = currentStudentData.overallPresent;
            const overallTotal = currentStudentData.overallTotal;

            const selectedCheckboxes = document.querySelectorAll('.class-checkbox:checked');
            
            const semName = currentStudentData.semesterName ? 'for ' + currentStudentData.semesterName : '';
            let body = `Dear ${name},\n\n`;
            body += `This is an official warning notification regarding your academic class attendance progress ${semName}.\n\n`;
            body += `AGGREGATE ATTENDANCE REPORT:\n`;
            body += `----------------------------\n`;
            body += `Overall aggregate attendance: ${overallPct}% (${overallPresent}/${overallTotal} total classes attended).\n`;
            if (overallPct < threshold) {
                body += `⚠️ WARNING: Your overall attendance is currently below the required minimum threshold of ${threshold}%.\n`;
            }
            body += `\n`;

            if (selectedCheckboxes.length > 0) {
                body += `SPECIFIC CLASS WARNINGS:\n`;
                body += `-----------------------\n`;
                
                selectedCheckboxes.forEach(cb => {
                    const index = parseInt(cb.dataset.index);
                    const c = currentStudentData.classes[index];
                    
                    const label = c.isBelowThreshold ? '🚨 CRITICAL (Low Attendance)' : '⚠️ WARNING';
                    body += `• ${c.courseName} (${c.course})\n`;
                    body += `  Attendance: ${c.percentage}% (${c.present}/${c.total} sessions) - ${label}\n`;
                    
                    if (c.absentDates && c.absentDates.length > 0) {
                        const formattedDates = c.absentDates.map(d => NepaliCalendar.formatNepaliDate(d, 'short'));
                        body += `  Absent dates (BS): ${formattedDates.join(', ')}\n`;
                    }
                    body += `\n`;
                });

                body += `EXAM ELIGIBILITY WARNING:\n`;
                body += `------------------------\n`;
                body += `Please be warned that if your attendance in these courses remains below the required threshold of ${threshold}%, you will NOT be permitted to sit for the final examinations.\n\n`;
            }

            body += `Please prioritize attending all upcoming lectures to resolve these warnings immediately.\n\n`;
            body += `Best regards,\n`;
            body += `Academic Registry office\n`;
            body += `CMS Portal Attendance System`;

            document.getElementById('email_body_preview').value = body;

            // Set subject dynamically
            const subjectInput = document.getElementById('email_subject');
            if (selectedCheckboxes.length > 0) {
                subjectInput.value = `CMS Portal: CRITICAL Attendance Warning - ${name}`;
            } else {
                subjectInput.value = `CMS  Portal: Class Attendance Summary - ${name}`;
            }

            // Disable send button if no class selected and overall is not critical
            const btnSend = document.getElementById('btn_send_email');
            if (selectedCheckboxes.length === 0 && overallPct >= threshold) {
                btnSend.disabled = true;
                btnSend.title = "Select at least one class to warn, or student overall attendance must be below threshold.";
            } else {
                btnSend.disabled = false;
                btnSend.title = "";
            }
        }

        function closeAlertModal() {
            const overlay = document.getElementById('alert_modal_overlay');
            overlay.classList.remove('active');
            currentStudentId = '';
            currentStudentData = null;
        }

        function submitDetailedAlert() {
            const btnSend = document.getElementById('btn_send_email');
            const originalText = btnSend.innerHTML;
            btnSend.disabled = true;
            btnSend.innerHTML = '<i class="ri-loader-4-line" style="animation: spin 1s infinite linear;"></i> Sending...';

            const selectedClasses = [];
            document.querySelectorAll('.class-checkbox:checked').forEach(cb => {
                const index = parseInt(cb.dataset.index);
                const c = currentStudentData.classes[index];
                selectedClasses.push(c.course);
            });

            const subject = document.getElementById('email_subject').value;
            const body = document.getElementById('email_body_preview').value;

            const formData = new FormData();
            formData.append('action', 'send_detailed_alert');
            formData.append('student_id', currentStudentId);
            formData.append('subject', subject);
            formData.append('body', body);
            
            selectedClasses.forEach(cls => {
                formData.append('selected_classes[]', cls);
            });

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Format the dispatch date for display
                    const now = new Date(data.date ? data.date.replace(' ', 'T') : Date.now());
                    const formatted = now.toLocaleString('en-US', {
                        month: 'short', day: 'numeric', year: 'numeric',
                        hour: 'numeric', minute: '2-digit', hour12: true
                    });

                    // Live-update the Email Status cell in the table row
                    const row = document.querySelector(`tr[data-reg="${currentStudentId}"]`);
                    if (row) {
                        const statusCell = row.querySelector('.email-status-cell');
                        if (statusCell) {
                            statusCell.innerHTML = `
                                <div style="display:flex; flex-direction:column; gap:3px;">
                                    <span style="display:inline-flex; align-items:center; gap:5px; background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0; border-radius:5px; padding:3px 8px; font-size:0.78rem; font-weight:600; width:fit-content;">
                                        <i class="ri-mail-check-line"></i> Dispatched
                                    </span>
                                    <span style="font-size:0.75rem; color:#64748b;">${formatted}</span>
                                </div>`;
                        }
                    }

                    closeAlertModal();
                } else {
                    alert('Error: ' + data.message);
                    btnSend.disabled = false;
                    btnSend.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error(error);
                alert('An error occurred while dispatching the warning.');
                btnSend.disabled = false;
                btnSend.innerHTML = originalText;
            });
        }
    </script>
    <style>
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</body>

</html>
