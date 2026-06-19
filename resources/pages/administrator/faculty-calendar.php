<?php
// resources/pages/administrator/faculty-calendar.php

require_once __DIR__ . '/../../../database/database_connection.php';

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    while (ob_get_level()) {
        ob_end_clean();
    }

    $action = $_POST['action'];
    $facultyCode = htmlspecialchars(trim($_POST['faculty_code'] ?? ''));

    if (!$facultyCode) {
        echo json_encode(['success' => false, 'message' => 'Missing faculty code.']);
        exit;
    }

    if ($action === 'get_semesters') {
        try {
            $stmt = $pdo->prepare("SELECT * FROM tblsemester WHERE facultyCode = ? ORDER BY startDate DESC");
            $stmt->execute([$facultyCode]);
            $semesters = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'semesters' => $semesters]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    $semesterID = (int)($_POST['semester_id'] ?? 0);
    if (!$semesterID) {
        echo json_encode(['success' => false, 'message' => 'Missing semester ID.']);
        exit;
    }

    if ($action === 'get_dates') {
        try {
            $stmt = $pdo->prepare("SELECT classDate FROM tblfacultycalendar WHERE facultyCode = ? AND semesterID = ?");
            $stmt->execute([$facultyCode, $semesterID]);
            $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode(['success' => true, 'dates' => $dates]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'toggle_date') {
        $date = htmlspecialchars(trim($_POST['date'] ?? ''));
        if (!$date || !strtotime($date)) {
            echo json_encode(['success' => false, 'message' => 'Invalid date.']);
            exit;
        }

        try {
            // Check if date exists
            $stmt = $pdo->prepare("SELECT id FROM tblfacultycalendar WHERE facultyCode = ? AND semesterID = ? AND classDate = ?");
            $stmt->execute([$facultyCode, $semesterID, $date]);
            $exists = $stmt->fetchColumn();

            if ($exists) {
                // Delete
                $stmtDel = $pdo->prepare("DELETE FROM tblfacultycalendar WHERE facultyCode = ? AND semesterID = ? AND classDate = ?");
                $stmtDel->execute([$facultyCode, $semesterID, $date]);
                echo json_encode(['success' => true, 'status' => 'removed', 'date' => $date]);
            } else {
                // Insert
                $stmtIns = $pdo->prepare("INSERT INTO tblfacultycalendar (facultyCode, semesterID, classDate) VALUES (?, ?, ?)");
                $stmtIns->execute([$facultyCode, $semesterID, $date]);
                echo json_encode(['success' => true, 'status' => 'added', 'date' => $date]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'bulk_weekdays') {
        // Accepts BS year/month, converts all days to Gregorian for storage
        require_once __DIR__ . '/../../lib/nepali_calendar.php';

        $np_year  = (int)($_POST['np_year']  ?? 0);
        $np_month = (int)($_POST['np_month'] ?? 0); // 1-indexed BS month

        if ($np_month < 1 || $np_month > 12 || $np_year < 2000) {
            echo json_encode(['success' => false, 'message' => 'Invalid Nepali month/year parameters.']);
            exit;
        }

        try {
            // Get semester details
            $stmtSem = $pdo->prepare("SELECT * FROM tblsemester WHERE Id = ?");
            $stmtSem->execute([$semesterID]);
            $semester = $stmtSem->fetch(PDO::FETCH_ASSOC);
            if (!$semester) {
                echo json_encode(['success' => false, 'message' => 'Semester not found.']);
                exit;
            }

            $daysInNpMonth = nepali_days_in_month($np_year, $np_month);
            $added = 0;

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT IGNORE INTO tblfacultycalendar (facultyCode, semesterID, classDate) VALUES (?, ?, ?)");

            for ($np_day = 1; $np_day <= $daysInNpMonth; $np_day++) {
                $greg = nepaliToGregorian($np_year, $np_month, $np_day);
                if (!$greg) continue;
                $dateStr = sprintf("%04d-%02d-%02d", $greg['year'], $greg['month'], $greg['day']);
                
                // Restrict to semester start/end date range
                if ($dateStr < $semester['startDate'] || $dateStr > $semester['endDate']) {
                    continue;
                }

                $dayOfWeek = date('N', strtotime($dateStr)); // 1 (Mon) - 7 (Sun)

                if ($dayOfWeek >= 1 && $dayOfWeek <= 5) { // Weekdays (Mon-Fri)
                    $stmt->execute([$facultyCode, $semesterID, $dateStr]);
                    $added++;
                }
            }
            $pdo->commit();

            // Fetch all dates to return updated list
            $stmtAll = $pdo->prepare("SELECT classDate FROM tblfacultycalendar WHERE facultyCode = ? AND semesterID = ?");
            $stmtAll->execute([$facultyCode, $semesterID]);
            $dates = $stmtAll->fetchAll(PDO::FETCH_COLUMN);

            echo json_encode(['success' => true, 'dates' => $dates, 'added_count' => $added]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// Fetch faculties list for the select element
$faculties = [];
try {
    $stmt = $pdo->query("SELECT * FROM tblfaculty ORDER BY facultyName");
    $faculties = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error loading faculties: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="resources/images/logo/face logo.png" rel="icon">
    <title>Academic Calendar (Nepali BS)</title>
    <link rel="stylesheet" href="resources/assets/css/admin_styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.css" rel="stylesheet">
    <script src="resources/assets/javascript/nepali_calendar.js"></script>
    <style>
        .calendar-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-bottom: 30px;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 6px;
            margin-top: 15px;
        }

        .calendar-day-header {
            font-weight: 600;
            color: #64748b;
            text-align: center;
            font-size: 0.82rem;
            padding: 8px 0;
            border-bottom: 2px solid #f1f5f9;
        }

        .calendar-day-cell {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            height: 90px;
            padding: 7px 8px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .calendar-day-cell:hover {
            border-color: #6366f1;
            background: #f5f3ff;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(99,102,241,0.12);
        }

        /* top row: BS number + English number */
        .cell-top-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        /* Big Nepali BS number */
        .calendar-day-np {
            font-weight: 800;
            font-size: 1.35rem;
            color: #1e293b;
            line-height: 1;
        }

        /* Small English AD number */
        .calendar-day-en {
            font-size: 0.7rem;
            font-weight: 600;
            color: #94a3b8;
            background: #f1f5f9;
            border-radius: 4px;
            padding: 1px 5px;
            line-height: 1.5;
        }

        /* Today highlight */
        .calendar-day-cell.today-cell {
            border-color: #f59e0b;
            background: #fffbeb;
        }
        .calendar-day-cell.today-cell .calendar-day-np {
            color: #b45309;
        }
        .calendar-day-cell.today-cell .calendar-day-en {
            background: #fef3c7;
            color: #92400e;
        }

        .calendar-day-cell.inactive {
            background: #ffffff;
            border-color: #f1f5f9;
            color: #cbd5e1;
            cursor: default;
            pointer-events: none;
        }

        .calendar-day-cell.inactive .calendar-day-np {
            color: #e2e8f0;
        }
        .calendar-day-cell.inactive .calendar-day-en {
            color: #e2e8f0;
            background: transparent;
        }

        /* Active Scheduled Class Day */
        .calendar-day-cell.active-day {
            background: #eff6ff;
            border-color: #3b82f6;
        }

        .calendar-day-cell.active-day .calendar-day-np {
            color: #1d4ed8;
        }
        .calendar-day-cell.active-day .calendar-day-en {
            background: #dbeafe;
            color: #1e40af;
        }

        /* Active + today */
        .calendar-day-cell.active-day.today-cell {
            background: #e0f2fe;
            border-color: #0ea5e9;
        }
        .calendar-day-cell.active-day.today-cell .calendar-day-np {
            color: #0c4a6e;
        }

        .calendar-day-indicator {
            align-self: flex-start;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 2px 5px;
            border-radius: 4px;
            background: #3b82f6;
            color: white;
            display: none;
            align-items: center;
            gap: 2px;
        }

        .calendar-day-cell.active-day .calendar-day-indicator {
            display: inline-flex;
        }

        /* Month header dual-line */
        .month-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
        }
        .month-header-bs {
            font-size: 1.25rem;
            font-weight: 800;
            color: #1e293b;
        }
        .month-header-en {
            font-size: 0.78rem;
            font-weight: 500;
            color: #64748b;
        }

        .calendar-navigation {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .nav-btn {
            background: #f1f5f9;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #475569;
            transition: all 0.2s ease;
        }

        .nav-btn:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        .control-panel {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            gap: 20px;
            flex-wrap: wrap;
        }

        .faculty-selector {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-grow: 1;
            max-width: 450px;
        }

        .faculty-selector select {
            flex-grow: 1;
            padding: 10px 14px;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            font-size: 0.92rem;
            background-color: #f8fafc;
            color: #1e293b;
        }

        .btn-bulk {
            background-color: #10b981;
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            font-size: 0.9rem;
        }

        .btn-bulk:hover {
            background-color: #059669;
        }

        .btn-bulk:disabled {
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
                    <h2 class="section--title">Academic Calendar Schedule</h2>
                </div>
            </div>

            <!-- Main Calendar View -->
            <div class="calendar-card">
                <div class="control-panel">
                    <div class="faculty-selector">
                        <label for="faculty_select" style="font-weight:600; color:#475569; white-space:nowrap;">Select Faculty:</label>
                        <select id="faculty_select" onchange="loadSemesters()">
                            <option value="">-- Choose a Faculty --</option>
                            <?php foreach ($faculties as $f): ?>
                                <option value="<?php echo htmlspecialchars($f['facultyCode']); ?>"><?php echo htmlspecialchars($f['facultyName']); ?> (<?php echo htmlspecialchars($f['facultyCode']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="faculty-selector" id="semester_selector_wrapper" style="display: none;">
                        <label for="semester_select" style="font-weight:600; color:#475569; white-space:nowrap;">Select Semester:</label>
                        <select id="semester_select" onchange="loadFacultyCalendar()">
                            <option value="">-- Choose a Semester --</option>
                        </select>
                    </div>

                    <button class="btn-bulk" id="bulk_weekdays_btn" onclick="bulkSetWeekdays()" disabled>
                        <i class="ri-calendar-check-line"></i> Pre-populate Weekdays
                    </button>
                </div>

                <div id="calendar_wrapper" style="display: none;">
                    <div class="calendar-navigation">
                        <button class="nav-btn" onclick="navigateMonth(-1)"><i class="ri-arrow-left-s-line"></i></button>
                        <div class="month-header" id="month_title_label">
                            <div class="month-header-bs" id="month_title_bs">Ashadh 2083 BS</div>
                            <div class="month-header-en" id="month_title_en">June – July 2026</div>
                        </div>
                        <button class="nav-btn" onclick="navigateMonth(1)"><i class="ri-arrow-right-s-line"></i></button>
                    </div>

                    <p style="font-size: 0.82rem; color: #64748b; margin-bottom: 15px;">
                        <i class="ri-information-line"></i> Click on any day box below to toggle it as a scheduled class day for this faculty. Dates shown in Nepali Bikram Sambat (BS) calendar.
                    </p>

                    <div class="calendar-grid" id="calendar_grid_header">
                        <div class="calendar-day-header">Sun</div>
                        <div class="calendar-day-header">Mon</div>
                        <div class="calendar-day-header">Tue</div>
                        <div class="calendar-day-header">Wed</div>
                        <div class="calendar-day-header">Thu</div>
                        <div class="calendar-day-header">Fri</div>
                        <div class="calendar-day-header">Sat</div>
                    </div>
                    
                    <div class="calendar-grid" id="calendar_days_container">
                        <!-- Loaded dynamically -->
                    </div>
                </div>

                <div id="no_faculty_selected_view" style="text-align: center; padding: 60px 0; color: #94a3b8;">
                    <i class="ri-calendar-todo-line" style="font-size: 3.5rem; display: block; margin-bottom: 15px;"></i>
                    <p style="font-weight: 500;">Please select a faculty above to view and edit scheduled school days.</p>
                </div>
            </div>

        </div>
    </section>

    <?php js_asset(["active_link"]) ?>

    <script>
        let selectedFaculty = '';
        let scheduledDates = new Set();
        let semesters = [];
        let selectedSemester = null;

        // Current Nepali BS month/year being viewed
        let currentNpDate = NepaliCalendar.todayNepali();

        function loadSemesters() {
            const select = document.getElementById('faculty_select');
            selectedFaculty = select.value;

            const semWrapper = document.getElementById('semester_selector_wrapper');
            const semSelect = document.getElementById('semester_select');
            const calendarWrapper = document.getElementById('calendar_wrapper');
            const placeholder = document.getElementById('no_faculty_selected_view');
            const bulkBtn = document.getElementById('bulk_weekdays_btn');

            semSelect.innerHTML = '<option value="">-- Choose a Semester --</option>';
            selectedSemester = null;
            calendarWrapper.style.display = 'none';
            placeholder.style.display = 'block';
            bulkBtn.disabled = true;

            if (!selectedFaculty) {
                semWrapper.style.display = 'none';
                return;
            }

            const formData = new FormData();
            formData.append('action', 'get_semesters');
            formData.append('faculty_code', selectedFaculty);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    semesters = data.semesters;
                    if (semesters.length === 0) {
                        semSelect.innerHTML = '<option value="">No semesters found</option>';
                        semWrapper.style.display = 'block';
                        return;
                    }
                    semesters.forEach(sem => {
                        const opt = document.createElement('option');
                        opt.value = sem.Id;
                        const activeLabel = sem.isActive === '1' ? ' (Active)' : '';
                        opt.textContent = `${sem.name}${activeLabel}`;
                        semSelect.appendChild(opt);
                    });
                    semWrapper.style.display = 'block';

                    // Auto select the active semester if present
                    const activeSem = semesters.find(sem => sem.isActive === '1');
                    if (activeSem) {
                        semSelect.value = activeSem.Id;
                        loadFacultyCalendar();
                    }
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => {
                console.error(err);
                alert('Failed to retrieve semesters.');
            });
        }

        function loadFacultyCalendar() {
            const semSelect = document.getElementById('semester_select');
            const semId = semSelect.value;

            const calendarWrapper = document.getElementById('calendar_wrapper');
            const placeholder = document.getElementById('no_faculty_selected_view');
            const bulkBtn = document.getElementById('bulk_weekdays_btn');

            if (!semId) {
                selectedSemester = null;
                calendarWrapper.style.display = 'none';
                placeholder.style.display = 'block';
                bulkBtn.disabled = true;
                return;
            }

            selectedSemester = semesters.find(sem => sem.Id == semId);
            if (!selectedSemester) return;

            calendarWrapper.style.display = 'block';
            placeholder.style.display = 'none';
            bulkBtn.disabled = false;

            // Set current viewed month: show from today if within semester range, else fallback to start date
            const now = new Date();
            const y = now.getFullYear();
            const m = String(now.getMonth() + 1).padStart(2, '0');
            const d = String(now.getDate()).padStart(2, '0');
            const todayStr = `${y}-${m}-${d}`;

            if (todayStr >= selectedSemester.startDate && todayStr <= selectedSemester.endDate) {
                currentNpDate = NepaliCalendar.todayNepali();
            } else {
                const startNp = NepaliCalendar.gregorianToNepali(selectedSemester.startDate);
                if (startNp) {
                    currentNpDate = startNp;
                }
            }

            fetchDates();
        }

        function fetchDates() {
            if (!selectedSemester) return;
            const formData = new FormData();
            formData.append('action', 'get_dates');
            formData.append('faculty_code', selectedFaculty);
            formData.append('semester_id', selectedSemester.Id);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    scheduledDates = new Set(data.dates);
                    renderCalendar();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => {
                console.error(err);
                alert('Failed to retrieve calendar dates.');
            });
        }

        function renderCalendar() {
            const npYear  = currentNpDate.year;
            const npMonth = currentNpDate.month;

            // --- Update month header: BS name + English date range ---
            document.getElementById('month_title_bs').textContent =
                `${NepaliCalendar.getMonthName(npMonth)} ${npYear} BS`;

            // Find first & last Gregorian dates of this BS month to show English range
            const firstGregDate = NepaliCalendar.nepaliToGregorian(npYear, npMonth, 1);
            const lastDay       = NepaliCalendar.daysInNepaliMonth(npYear, npMonth);
            const lastGregDate  = NepaliCalendar.nepaliToGregorian(npYear, npMonth, lastDay);
            const engMonths = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            if (firstGregDate && lastGregDate) {
                const startLabel = `${engMonths[firstGregDate.getMonth()]} ${firstGregDate.getFullYear()}`;
                const endLabel   = `${engMonths[lastGregDate.getMonth()]} ${lastGregDate.getFullYear()}`;
                document.getElementById('month_title_en').textContent =
                    startLabel === endLabel ? startLabel : `${startLabel} – ${endLabel}`;
            }

            // --- Today in BS for highlighting ---
            const todayNp = NepaliCalendar.todayNepali();

            const container = document.getElementById('calendar_days_container');
            container.innerHTML = '';

            const firstDayIndex = NepaliCalendar.firstDayOfNepaliMonth(npYear, npMonth);
            const totalDays     = NepaliCalendar.daysInNepaliMonth(npYear, npMonth);

            // Prev month days for padding
            let prevNpYear  = npYear;
            let prevNpMonth = npMonth - 1;
            if (prevNpMonth < 1) { prevNpMonth = 12; prevNpYear--; }
            const prevTotalDays = prevNpYear >= 2000 ? NepaliCalendar.daysInNepaliMonth(prevNpYear, prevNpMonth) : 30;

            // Render padding cells for previous month (show prev BS + EN)
            for (let i = firstDayIndex; i > 0; i--) {
                const prevNpDay = prevTotalDays - i + 1;
                const prevGregDate = NepaliCalendar.nepaliToGregorian(prevNpYear, prevNpMonth, prevNpDay);
                const prevEnDay   = prevGregDate ? prevGregDate.getDate() : '';
                const cell = document.createElement('div');
                cell.className = 'calendar-day-cell inactive';
                cell.innerHTML = `
                    <div class="cell-top-row">
                        <span class="calendar-day-np">${prevNpDay}</span>
                        <span class="calendar-day-en">${prevEnDay}</span>
                    </div>
                `;
                container.appendChild(cell);
            }

            // Render active day cells for current Nepali month
            for (let npDay = 1; npDay <= totalDays; npDay++) {
                // Convert this BS day to Gregorian for DB lookup + English display
                const gregDate    = NepaliCalendar.nepaliToGregorian(npYear, npMonth, npDay);
                const gregDateStr = NepaliCalendar.nepaliToGregorianStr(npYear, npMonth, npDay);
                const enDay       = gregDate ? gregDate.getDate() : '';
                const isActive    = gregDateStr ? scheduledDates.has(gregDateStr) : false;

                // Check if date falls outside semester start/end
                let isOutsideSemester = false;
                if (selectedSemester) {
                    if (gregDateStr < selectedSemester.startDate || gregDateStr > selectedSemester.endDate) {
                        isOutsideSemester = true;
                    }
                }

                // Check if today
                const isToday = todayNp &&
                    todayNp.year === npYear &&
                    todayNp.month === npMonth &&
                    todayNp.day === npDay;

                const cell = document.createElement('div');
                let cls = 'calendar-day-cell';
                if (isActive)  cls += ' active-day';
                if (isToday)   cls += ' today-cell';
                if (isOutsideSemester) cls += ' inactive';
                cell.className = cls;

                if (gregDateStr && !isOutsideSemester) {
                    cell.dataset.date = gregDateStr;
                    cell.onclick = () => toggleDay(gregDateStr, cell);
                }

                cell.innerHTML = `
                    <div class="cell-top-row">
                        <span class="calendar-day-np">${npDay}</span>
                        <span class="calendar-day-en">${enDay}</span>
                    </div>
                    <span class="calendar-day-indicator"><i class="ri-check-line"></i> Class</span>
                `;
                container.appendChild(cell);
            }

            // Render padding cells for next month
            const totalCells = firstDayIndex + totalDays;
            const remaining = (7 - (totalCells % 7)) % 7;
            let nextNpMonth = npMonth + 1;
            let nextNpYear  = npYear;
            if (nextNpMonth > 12) { nextNpMonth = 1; nextNpYear++; }
            for (let i = 1; i <= remaining; i++) {
                const nextGregDate = NepaliCalendar.nepaliToGregorian(nextNpYear, nextNpMonth, i);
                const nextEnDay    = nextGregDate ? nextGregDate.getDate() : '';
                const cell = document.createElement('div');
                cell.className = 'calendar-day-cell inactive';
                cell.innerHTML = `
                    <div class="cell-top-row">
                        <span class="calendar-day-np">${i}</span>
                        <span class="calendar-day-en">${nextEnDay}</span>
                    </div>
                `;
                container.appendChild(cell);
            }
        }

        function navigateMonth(direction) {
            // Navigate in Nepali BS months
            let { year, month, day } = currentNpDate;
            month += direction;
            if (month > 12) { month = 1; year++; }
            if (month < 1)  { month = 12; year--; }
            currentNpDate = { year, month, day };
            renderCalendar();
        }

        function toggleDay(dateStr, cellElement) {
            if (!selectedSemester) return;
            // dateStr is Gregorian (Y-m-d) for the server
            const formData = new FormData();
            formData.append('action', 'toggle_date');
            formData.append('faculty_code', selectedFaculty);
            formData.append('semester_id', selectedSemester.Id);
            formData.append('date', dateStr);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (data.status === 'added') {
                        scheduledDates.add(dateStr);
                        cellElement.classList.add('active-day');
                    } else {
                        scheduledDates.delete(dateStr);
                        cellElement.classList.remove('active-day');
                    }
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => {
                console.error(err);
                alert('Connection error. Failed to toggle date.');
            });
        }

        function bulkSetWeekdays() {
            if (!selectedSemester) return;
            const npYear  = currentNpDate.year;
            const npMonth = currentNpDate.month;
            const npMonthName = NepaliCalendar.getMonthName(npMonth);

            if (!confirm(`Are you sure you want to pre-populate all Mon-Fri weekdays of ${npMonthName} ${npYear} BS (within the semester range) as class days for this faculty?`)) {
                return;
            }

            const btn = document.getElementById('bulk_weekdays_btn');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="ri-loader-4-line" style="animation: spin 1s infinite linear;"></i> Working...';

            const formData = new FormData();
            formData.append('action', 'bulk_weekdays');
            formData.append('faculty_code', selectedFaculty);
            formData.append('semester_id', selectedSemester.Id);
            formData.append('np_year',  npYear);
            formData.append('np_month', npMonth);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    scheduledDates = new Set(data.dates);
                    renderCalendar();
                    alert(`Successfully added ${data.added_count} weekday sessions!`);
                } else {
                    alert('Error: ' + data.message);
                }
                btn.disabled = false;
                btn.innerHTML = originalText;
            })
            .catch(err => {
                console.error(err);
                alert('Connection error. Bulk update failed.');
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        }
    </script>
</body>

</html>
