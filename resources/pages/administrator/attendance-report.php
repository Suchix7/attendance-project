<?php
require_once __DIR__ . '/../../lib/nepali_calendar.php';

// ── Filters from GET ─────────────────────────────────────────────
$selectedCourse   = isset($_GET['course'])   ? htmlspecialchars(trim($_GET['course']))   : '';
$selectedUnit     = isset($_GET['unit'])     ? htmlspecialchars(trim($_GET['unit']))     : '';
$selectedSemester = isset($_GET['semester']) ? (int)$_GET['semester']                   : 0;
$selectedFaculty  = isset($_GET['faculty'])  ? htmlspecialchars(trim($_GET['faculty']))  : '';

// ── Load filter options ───────────────────────────────────────────
$allCourses   = getCourseNames();   // [{courseCode, name, facultyCode?}]
$allUnits     = getUnitNames();     // [{unitCode, name}]
$allFaculties = $pdo->query("SELECT facultyCode, facultyName FROM tblfaculty ORDER BY facultyName")->fetchAll(PDO::FETCH_ASSOC);

$semesters = [];
if ($selectedFaculty) {
    $semesters = getSemestersByFaculty($pdo, $selectedFaculty);
}

// ── Calendar dates for selected semester / faculty ────────────────
$calendarDates = [];
if ($selectedFaculty) {
    if ($selectedSemester) {
        $stmtCal = $pdo->prepare("SELECT DISTINCT classDate FROM tblfacultycalendar WHERE facultyCode = ? AND semesterID = ? AND classDate <= CURDATE() ORDER BY classDate ASC");
        $stmtCal->execute([$selectedFaculty, $selectedSemester]);
    } else {
        $stmtCal = $pdo->prepare("SELECT DISTINCT classDate FROM tblfacultycalendar WHERE facultyCode = ? AND classDate <= CURDATE() ORDER BY classDate ASC");
        $stmtCal->execute([$selectedFaculty]);
    }
    $calendarDates = $stmtCal->fetchAll(PDO::FETCH_COLUMN);
}

// Fallback: use unique dates from attendance table when no calendar is configured
if (empty($calendarDates) && $selectedCourse && $selectedUnit) {
    $stmtFallback = $pdo->prepare("SELECT DISTINCT DATE(dateMarked) FROM tblattendance WHERE course = ? AND unit = ? ORDER BY dateMarked ASC");
    $stmtFallback->execute([$selectedCourse, $selectedUnit]);
    $calendarDates = $stmtFallback->fetchAll(PDO::FETCH_COLUMN);
}

// ── Students ──────────────────────────────────────────────────────
$students = [];
if ($selectedCourse) {
    $stmtS = $pdo->prepare("SELECT registrationNumber, firstName, lastName FROM tblstudents WHERE courseCode = ? ORDER BY firstName, lastName");
    $stmtS->execute([$selectedCourse]);
    $students = $stmtS->fetchAll(PDO::FETCH_ASSOC);
}

// ── Attendance map  [regNo][date] = 'Present'|'Absent' ───────────
$attendanceMap = [];
$summaryMap    = []; // [regNo] => [present, total, pct]

if ($students && $selectedCourse && $selectedUnit) {
    $regNumbers = array_column($students, 'registrationNumber');
    $inList = implode(',', array_fill(0, count($regNumbers), '?'));
    $params = array_merge($regNumbers, [$selectedCourse, $selectedUnit]);
    $stmtA = $pdo->prepare(
        "SELECT studentRegistrationNumber, DATE(dateMarked) as dm, attendanceStatus
         FROM tblattendance
         WHERE studentRegistrationNumber IN ($inList)
           AND course = ? AND unit = ?"
    );
    $stmtA->execute($params);
    foreach ($stmtA->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $attendanceMap[$row['studentRegistrationNumber']][$row['dm']] = $row['attendanceStatus'];
    }

    foreach ($students as $s) {
        $reg     = $s['registrationNumber'];
        $present = 0;
        $total   = count($calendarDates) ?: 1;
        foreach ($calendarDates as $d) {
            $status = $attendanceMap[$reg][$d] ?? 'Absent';
            if ($status === 'Present') $present++;
        }
        $summaryMap[$reg] = [
            'present' => $present,
            'total'   => count($calendarDates),
            'pct'     => count($calendarDates) > 0 ? round(($present / count($calendarDates)) * 100, 1) : 0
        ];
    }
}

$threshold = (int)get_setting($pdo, 'attendance_threshold', '75');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Attendance Report — SAS Admin</title>
    <meta name="description" content="View and export a semester-specific attendance matrix for all students by course and unit.">
    <link href="resources/images/logo/face logo.png" rel="icon">
    <link rel="stylesheet" href="resources/assets/css/admin_styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* ── Page-level typography ─────────────────────────── */
        body { font-family: 'Inter', sans-serif; }

        /* ── Filter bar ────────────────────────────────────── */
        .report-filters {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            padding: 20px 24px;
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            align-items: flex-end;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,.04);
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
            min-width: 170px;
        }
        .filter-group label {
            font-size: .78rem;
            font-weight: 600;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .filter-group select {
            padding: 9px 12px;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            background: #f8fafc;
            font-size: .88rem;
            color: #1e293b;
            cursor: pointer;
            transition: border-color .2s;
        }
        .filter-group select:focus {
            outline: none;
            border-color: #6366f1;
            background: #ffffff;
        }
        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .btn-apply {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: .88rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: opacity .2s;
        }
        .btn-apply:hover { opacity: .88; }

        .btn-export {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: .88rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: opacity .2s;
        }
        .btn-export:hover { opacity: .88; }

        .btn-clear {
            background: #f1f5f9;
            color: #475569;
            border: none;
            padding: 10px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: .88rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background .2s;
        }
        .btn-clear:hover { background: #e2e8f0; }

        /* ── Summary banner ────────────────────────────────── */
        .summary-banner {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .summary-pill {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 50px;
            padding: 8px 18px;
            font-size: .82rem;
            font-weight: 600;
            color: #475569;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 1px 4px rgba(0,0,0,.05);
        }
        .summary-pill .pill-val { color: #1e293b; font-size: 1rem; }

        /* ── Matrix table wrapper ──────────────────────────── */
        .matrix-wrapper {
            background: #ffffff;
            border-radius: 14px;
            border: 1.5px solid #e2e8f0;
            box-shadow: 0 4px 20px rgba(0,0,0,.05);
            overflow: hidden;
        }
        .matrix-header {
            padding: 20px 24px 14px;
            border-bottom: 1.5px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        .matrix-header h2 {
            font-size: 1.05rem;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .matrix-header h2 i { color: #6366f1; }

        /* ── Scrollable table ──────────────────────────────── */
        .table-scroll {
            overflow-x: auto;
            max-height: 66vh;
            overflow-y: auto;
        }
        .matrix-table {
            border-collapse: collapse;
            width: 100%;
            min-width: 600px;
        }
        .matrix-table thead tr th {
            position: sticky;
            top: 0;
            background: #f8fafc;
            font-size: .72rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: .05em;
            padding: 10px 12px;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
            text-align: center;
        }
        .matrix-table thead tr th.col-name,
        .matrix-table thead tr th.col-reg,
        .matrix-table thead tr th.col-summary {
            position: sticky;
            text-align: left;
        }
        .matrix-table thead tr th.col-name { left: 0; z-index: 3; min-width: 180px; }
        .matrix-table thead tr th.col-reg  { z-index: 2; min-width: 130px; }
        .matrix-table tbody tr td {
            padding: 9px 12px;
            border-bottom: 1px solid #f1f5f9;
            font-size: .83rem;
            color: #334155;
            text-align: center;
            white-space: nowrap;
        }
        .matrix-table tbody tr td.col-name,
        .matrix-table tbody tr td.col-reg {
            text-align: left;
        }
        .matrix-table tbody tr td.col-name {
            position: sticky;
            left: 0;
            background: #ffffff;
            font-weight: 600;
            color: #1e293b;
            min-width: 180px;
            z-index: 1;
            border-right: 1.5px solid #e2e8f0;
        }
        .matrix-table tbody tr:hover td { background: #f8fafc; }
        .matrix-table tbody tr:hover td.col-name { background: #f8fafc; }

        /* ── Attendance cell badges ─────────────────────────── */
        .cell-p {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            font-weight: 700;
            font-size: .78rem;
            background: #dcfce7;
            color: #16a34a;
        }
        .cell-a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            font-weight: 700;
            font-size: .78rem;
            background: #fee2e2;
            color: #dc2626;
        }
        .cell-na {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            font-size: .78rem;
            background: #f1f5f9;
            color: #94a3b8;
        }

        /* ── Summary column ────────────────────────────────── */
        .pct-bar-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 130px;
        }
        .pct-bar-bg {
            flex-grow: 1;
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            overflow: hidden;
        }
        .pct-bar-fill {
            height: 100%;
            border-radius: 3px;
        }
        .pct-label {
            font-size: .8rem;
            font-weight: 700;
            white-space: nowrap;
            min-width: 38px;
        }

        /* ── Risk badge ─────────────────────────────────────── */
        .risk-badge {
            display: inline-block;
            padding: 2px 9px;
            border-radius: 50px;
            font-size: .72rem;
            font-weight: 700;
        }

        /* ── Empty / Placeholder ───────────────────────────── */
        .placeholder-card {
            padding: 60px 20px;
            text-align: center;
            color: #94a3b8;
        }
        .placeholder-card i {
            font-size: 3rem;
            display: block;
            margin-bottom: 12px;
            color: #cbd5e1;
        }
        .placeholder-card p { font-size: .95rem; }

        /* ── Spinner animation (for future AJAX) ───────────── */
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>

<body>
<?php include 'includes/topbar.php'; ?>
<section class="main">
    <?php include 'includes/sidebar.php'; ?>

    <div class="main--content">

        <!-- Page title -->
        <div class="overview" style="margin-bottom:20px;">
            <div class="title">
                <h2 class="section--title"><i class="ri-table-line" style="color:#6366f1;"></i> Attendance Report</h2>
                <p style="color:#64748b;font-size:.88rem;margin-top:4px;">
                    Select a course, unit, and semester to view the full attendance matrix and export as Excel.
                </p>
            </div>
        </div>

        <!-- ── Filter bar ────────────────────────────────────────── -->
        <form method="GET" action="" class="report-filters" id="reportForm">
            <div class="filter-group">
                <label for="rf_faculty">Faculty</label>
                <select name="faculty" id="rf_faculty" onchange="
                    document.getElementById('rf_semester').value='';
                    document.getElementById('rf_course').value='';
                    document.getElementById('reportForm').submit();">
                    <option value="">-- All Faculties --</option>
                    <?php foreach ($allFaculties as $f): ?>
                        <option value="<?php echo htmlspecialchars($f['facultyCode']); ?>"
                            <?php if ($selectedFaculty === $f['facultyCode']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($f['facultyName']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="rf_semester">Semester</label>
                <select name="semester" id="rf_semester" onchange="document.getElementById('reportForm').submit();">
                    <option value="">-- Active / All --</option>
                    <?php foreach ($semesters as $sem): ?>
                        <option value="<?php echo $sem['Id']; ?>"
                            <?php if ($selectedSemester == $sem['Id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($sem['name']) . ($sem['isActive'] ? ' (Active)' : ''); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="rf_course">Course</label>
                <select name="course" id="rf_course" onchange="document.getElementById('reportForm').submit();">
                    <option value="">-- Select Course --</option>
                    <?php foreach ($allCourses as $c): ?>
                        <option value="<?php echo htmlspecialchars($c['courseCode']); ?>"
                            <?php if ($selectedCourse === $c['courseCode']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($c['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="rf_unit">Unit</label>
                <select name="unit" id="rf_unit" onchange="document.getElementById('reportForm').submit();">
                    <option value="">-- Select Unit --</option>
                    <?php foreach ($allUnits as $u): ?>
                        <option value="<?php echo htmlspecialchars($u['unitCode']); ?>"
                            <?php if ($selectedUnit === $u['unitCode']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($u['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-actions">
                <?php if ($selectedCourse && $selectedUnit): ?>
                    <button type="button" class="btn-export" onclick="exportToExcel()">
                        <i class="ri-file-excel-2-line"></i> Export Excel
                    </button>
                <?php endif; ?>
                <?php if ($selectedFaculty || $selectedCourse || $selectedUnit || $selectedSemester): ?>
                    <a href="attendance-report" class="btn-clear">
                        <i class="ri-refresh-line"></i> Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <!-- ── Summary pills ─────────────────────────────────────── -->
        <?php if ($selectedCourse && $selectedUnit && !empty($students)): ?>
        <div class="summary-banner">
            <div class="summary-pill">
                <i class="ri-user-line"></i>
                Students: <span class="pill-val"><?php echo count($students); ?></span>
            </div>
            <div class="summary-pill">
                <i class="ri-calendar-check-line"></i>
                Class Days: <span class="pill-val"><?php echo count($calendarDates); ?></span>
            </div>
            <?php
            $totalPresent = array_sum(array_column($summaryMap, 'present'));
            $totalPossible = count($students) * count($calendarDates);
            $overallPct = $totalPossible > 0 ? round(($totalPresent / $totalPossible) * 100, 1) : 0;
            ?>
            <div class="summary-pill">
                <i class="ri-bar-chart-line"></i>
                Avg. Attendance: <span class="pill-val"><?php echo $overallPct; ?>%</span>
            </div>
            <?php
            $belowThreshold = count(array_filter($summaryMap, fn($s) => $s['pct'] < $threshold));
            ?>
            <div class="summary-pill" style="border-color:#fee2e2;">
                <i class="ri-alarm-warning-line" style="color:#ef4444;"></i>
                Below <?php echo $threshold; ?>%:
                <span class="pill-val" style="color:#ef4444;"><?php echo $belowThreshold; ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── Matrix table ──────────────────────────────────────── -->
        <div class="matrix-wrapper">
            <div class="matrix-header">
                <h2><i class="ri-table-line"></i> Attendance Matrix</h2>
                <?php if (!empty($calendarDates)): ?>
                    <span style="font-size:.8rem;color:#94a3b8;">
                        <i class="ri-information-line"></i>
                        P = Present &nbsp;|&nbsp; A = Absent &nbsp;|&nbsp; — = No Data
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($selectedCourse && $selectedUnit && !empty($students) && !empty($calendarDates)): ?>
            <div class="table-scroll">
                <table class="matrix-table" id="attendanceMatrix">
                    <thead>
                        <tr>
                            <th class="col-name">Student</th>
                            <th class="col-reg">Reg. No</th>
                            <?php foreach ($calendarDates as $d): ?>
                                <th title="<?php echo $d; ?>"><?php echo formatNepaliDate($d, 'short'); ?></th>
                            <?php endforeach; ?>
                            <th class="col-summary" style="min-width:180px;">Summary</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($students as $s):
                        $reg   = $s['registrationNumber'];
                        $sum   = $summaryMap[$reg] ?? ['present'=>0,'total'=>0,'pct'=>0];
                        $pct   = $sum['pct'];

                        if ($pct >= $threshold + 10)      { $barColor='#22c55e'; $badge='Safe';     $badgeBg='#dcfce7'; $badgeFg='#16a34a'; }
                        elseif ($pct >= $threshold)        { $barColor='#f59e0b'; $badge='Warning';  $badgeBg='#fef3c7'; $badgeFg='#b45309'; }
                        else                               { $barColor='#ef4444'; $badge='Critical'; $badgeBg='#fee2e2'; $badgeFg='#dc2626'; }
                    ?>
                        <tr>
                            <td class="col-name">
                                <?php echo htmlspecialchars($s['firstName'] . ' ' . $s['lastName']); ?>
                            </td>
                            <td class="col-reg" style="color:#64748b;font-size:.8rem;">
                                <?php echo htmlspecialchars($reg); ?>
                            </td>
                            <?php foreach ($calendarDates as $d):
                                $status = $attendanceMap[$reg][$d] ?? null;
                            ?>
                                <td>
                                    <?php if ($status === 'Present'): ?>
                                        <span class="cell-p" title="Present on <?php echo $d; ?>">P</span>
                                    <?php elseif ($status === 'Absent'): ?>
                                        <span class="cell-a" title="Absent on <?php echo $d; ?>">A</span>
                                    <?php else: ?>
                                        <span class="cell-na" title="No record for <?php echo $d; ?>">—</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <td>
                                <div class="pct-bar-wrap">
                                    <div class="pct-bar-bg">
                                        <div class="pct-bar-fill" style="width:<?php echo $pct; ?>%;background:<?php echo $barColor; ?>;"></div>
                                    </div>
                                    <span class="pct-label" style="color:<?php echo $barColor; ?>;">
                                        <?php echo $pct; ?>%
                                    </span>
                                    <span class="risk-badge" style="background:<?php echo $badgeBg; ?>;color:<?php echo $badgeFg; ?>;">
                                        <?php echo $badge; ?>
                                    </span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php elseif ($selectedCourse && $selectedUnit && !empty($students) && empty($calendarDates)): ?>
                <div class="placeholder-card">
                    <i class="ri-calendar-close-line"></i>
                    <p>No scheduled class days found for the selected semester.<br>
                       Please configure the faculty calendar first, or select a different semester.</p>
                </div>

            <?php elseif ($selectedCourse && $selectedUnit && empty($students)): ?>
                <div class="placeholder-card">
                    <i class="ri-user-unfollow-line"></i>
                    <p>No students are registered in the selected course.</p>
                </div>

            <?php else: ?>
                <div class="placeholder-card">
                    <i class="ri-filter-3-line"></i>
                    <p>Select a <strong>Faculty</strong>, <strong>Course</strong>, and <strong>Unit</strong> above to load the attendance matrix.</p>
                </div>
            <?php endif; ?>
        </div>
        <!-- end matrix-wrapper -->

    </div><!-- end main--content -->
</section>

<?php js_asset(['min/js/filesaver', 'min/js/xlsx', 'active_link']); ?>

<script>
function exportToExcel() {
    const table = document.getElementById('attendanceMatrix');
    if (!table) { alert('No data to export.'); return; }

    // Clone table so we can manipulate values for Excel (plain text instead of HTML spans)
    const clone = table.cloneNode(true);

    // Replace P/A/— spans with plain text
    clone.querySelectorAll('.cell-p').forEach(el => el.textContent = 'P');
    clone.querySelectorAll('.cell-a').forEach(el => el.textContent = 'A');
    clone.querySelectorAll('.cell-na').forEach(el => el.textContent = '—');

    // Replace progress bar cell with plain pct text
    clone.querySelectorAll('.pct-bar-wrap').forEach(el => {
        const pctLabel = el.querySelector('.pct-label');
        const riskBadge = el.querySelector('.risk-badge');
        const txt = (pctLabel ? pctLabel.textContent.trim() : '') + ' ' + (riskBadge ? riskBadge.textContent.trim() : '');
        el.textContent = txt;
    });

    const wb = XLSX.utils.table_to_book(clone, { sheet: 'Attendance' });

    // Style header row
    const ws = wb.Sheets['Attendance'];
    const wbout = XLSX.write(wb, { bookType: 'xlsx', bookSST: true, type: 'binary' });

    function s2ab(s) {
        const buf = new ArrayBuffer(s.length);
        const view = new Uint8Array(buf);
        for (let i = 0; i < s.length; i++) view[i] = s.charCodeAt(i) & 0xFF;
        return buf;
    }

    const filename = 'Attendance_Report_<?php echo $selectedCourse ? $selectedCourse : "All"; ?>_<?php echo date("Y-m-d"); ?>.xlsx';
    saveAs(new Blob([s2ab(wbout)], { type: 'application/octet-stream' }), filename);
}
</script>
</body>
</html>
