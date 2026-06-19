<?php

/**
 * Algorithm: Attendance Risk Assessment
 * 
 * Logic:
 * 1. Calculate percentage: (Classes Present / Total Classes) * 100
 * 2. Risk Level:
 *    - Safe: >= 85%
 *    - Warning: 75% - 84%
 *    - Critical: < 75%
 * 3. Forecast: If current trend continues, will they hit 75%?
 */

function calculateAttendanceRisk($registrationNumber, $courseCode = null, $semesterId = null) {
    global $pdo;
    
    // Find the faculty code for the student or course
    $facultyCode = null;
    if ($courseCode) {
        $stmtFaculty = $pdo->prepare("SELECT f.facultyCode FROM tblcourse c JOIN tblfaculty f ON c.facultyID = f.Id WHERE c.courseCode = ?");
        $stmtFaculty->execute([$courseCode]);
        $facultyCode = $stmtFaculty->fetchColumn();
    } else {
        $stmtStudFaculty = $pdo->prepare("SELECT faculty FROM tblstudents WHERE registrationNumber = ?");
        $stmtStudFaculty->execute([$registrationNumber]);
        $facultyCode = $stmtStudFaculty->fetchColumn();
    }

    if ($facultyCode && !$semesterId) {
        if (function_exists('getActiveSemester')) {
            $activeSem = getActiveSemester($pdo, $facultyCode);
            if ($activeSem) {
                $semesterId = $activeSem['Id'];
            }
        }
    }

    // Check if faculty calendar exists
    $hasCalendar = false;
    $calendarDates = [];
    if ($facultyCode) {
        if ($semesterId) {
            $stmtCal = $pdo->prepare("SELECT classDate FROM tblfacultycalendar WHERE facultyCode = ? AND semesterID = ? AND classDate <= CURDATE() ORDER BY classDate ASC");
            $stmtCal->execute([$facultyCode, $semesterId]);
        } else {
            $stmtCal = $pdo->prepare("SELECT classDate FROM tblfacultycalendar WHERE facultyCode = ? AND classDate <= CURDATE() ORDER BY classDate ASC");
            $stmtCal->execute([$facultyCode]);
        }
        $calendarDates = $stmtCal->fetchAll(PDO::FETCH_COLUMN);
        if (count($calendarDates) > 0) {
            $hasCalendar = true;
        }
    }

    if ($hasCalendar) {
        $totalClasses = count($calendarDates);
        
        $dateList = implode(',', array_map(function($d) { return "'$d'"; }, $calendarDates));
        $queryPresent = "SELECT COUNT(DISTINCT dateMarked) as present FROM tblattendance 
                         WHERE studentRegistrationNumber = :reg 
                         AND attendanceStatus = 'Present'
                         AND dateMarked IN ($dateList)";
        if ($courseCode) {
            $queryPresent .= " AND course = :course";
        }
        $stmtPresent = $pdo->prepare($queryPresent);
        $stmtPresent->bindParam(':reg', $registrationNumber);
        if ($courseCode) $stmtPresent->bindParam(':course', $courseCode);
        $stmtPresent->execute();
        $presentCount = $stmtPresent->fetch(PDO::FETCH_ASSOC)['present'];
    } else {
        // Fallback: Get total unique dates for the course/unit or overall
        $queryTotal = "SELECT COUNT(DISTINCT dateMarked) as total FROM tblattendance";
        if ($courseCode) {
            $queryTotal .= " WHERE course = :course";
        }
        $stmtTotal = $pdo->prepare($queryTotal);
        if ($courseCode) $stmtTotal->bindParam(':course', $courseCode);
        $stmtTotal->execute();
        $totalClasses = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'] ?: 1;

        // Get student's present count
        $queryPresent = "SELECT COUNT(*) as present FROM tblattendance 
                         WHERE studentRegistrationNumber = :reg 
                         AND attendanceStatus = 'Present'";
        if ($courseCode) {
            $queryPresent .= " AND course = :course";
        }
        $stmtPresent = $pdo->prepare($queryPresent);
        $stmtPresent->bindParam(':reg', $registrationNumber);
        if ($courseCode) $stmtPresent->bindParam(':course', $courseCode);
        $stmtPresent->execute();
        $presentCount = $stmtPresent->fetch(PDO::FETCH_ASSOC)['present'];
    }

    $percentage = ($presentCount / $totalClasses) * 100;
    
    $threshold = (int)get_setting($pdo, 'attendance_threshold', '75');
    $warningThreshold = $threshold + 10;
    
    $riskLevel = 'Safe';
    $riskColor = '#22c55e'; // Green
    
    if ($percentage < $threshold) {
        $riskLevel = 'Critical';
        $riskColor = '#ef4444'; // Red
    } elseif ($percentage < $warningThreshold) {
        $riskLevel = 'Warning';
        $riskColor = '#f59e0b'; // Amber
    }

    return [
        'percentage' => round($percentage, 1),
        'present' => $presentCount,
        'total' => $totalClasses,
        'level' => $riskLevel,
        'color' => $riskColor
    ];
}

function getLatestNotices($limit = 5) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM tblnotices ORDER BY postedDate DESC LIMIT :limit");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
