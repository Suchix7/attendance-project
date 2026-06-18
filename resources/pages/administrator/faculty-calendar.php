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

    if ($action === 'get_dates') {
        try {
            $stmt = $pdo->prepare("SELECT classDate FROM tblfacultycalendar WHERE facultyCode = ?");
            $stmt->execute([$facultyCode]);
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
            $stmt = $pdo->prepare("SELECT id FROM tblfacultycalendar WHERE facultyCode = ? AND classDate = ?");
            $stmt->execute([$facultyCode, $date]);
            $exists = $stmt->fetchColumn();

            if ($exists) {
                // Delete
                $stmtDel = $pdo->prepare("DELETE FROM tblfacultycalendar WHERE facultyCode = ? AND classDate = ?");
                $stmtDel->execute([$facultyCode, $date]);
                echo json_encode(['success' => true, 'status' => 'removed', 'date' => $date]);
            } else {
                // Insert
                $stmtIns = $pdo->prepare("INSERT INTO tblfacultycalendar (facultyCode, classDate) VALUES (?, ?)");
                $stmtIns->execute([$facultyCode, $date]);
                echo json_encode(['success' => true, 'status' => 'added', 'date' => $date]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'bulk_weekdays') {
        $year = (int)($_POST['year'] ?? date('Y'));
        $month = (int)($_POST['month'] ?? date('n')); // 1-indexed

        if ($month < 1 || $month > 12 || $year < 2000) {
            echo json_encode(['success' => false, 'message' => 'Invalid month/year parameters.']);
            exit;
        }

        try {
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $added = 0;

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT IGNORE INTO tblfacultycalendar (facultyCode, classDate) VALUES (?, ?)");

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $dateStr = sprintf("%04d-%02d-%02d", $year, $month, $day);
                $dayOfWeek = date('N', strtotime($dateStr)); // 1 (Mon) - 7 (Sun)

                if ($dayOfWeek >= 1 && $dayOfWeek <= 5) { // Weekdays (Mon-Fri)
                    $stmt->execute([$facultyCode, $dateStr]);
                    $added++;
                }
            }
            $pdo->commit();

            // Fetch all dates to return updated list
            $stmtAll = $pdo->prepare("SELECT classDate FROM tblfacultycalendar WHERE facultyCode = ?");
            $stmtAll->execute([$facultyCode]);
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
    <title>Academic Calendar</title>
    <link rel="stylesheet" href="resources/assets/css/admin_styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.css" rel="stylesheet">
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
            gap: 8px;
            margin-top: 15px;
        }

        .calendar-day-header {
            font-weight: 600;
            color: #64748b;
            text-align: center;
            font-size: 0.88rem;
            padding: 8px 0;
            border-bottom: 2px solid #f1f5f9;
        }

        .calendar-day-cell {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            height: 80px;
            padding: 8px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .calendar-day-cell:hover {
            border-color: #cbd5e1;
            background: #f1f5f9;
            transform: translateY(-1px);
        }

        .calendar-day-number {
            font-weight: 700;
            font-size: 0.95rem;
            color: #475569;
        }

        .calendar-day-cell.inactive {
            background: #ffffff;
            border-color: #f1f5f9;
            color: #cbd5e1;
            cursor: default;
            pointer-events: none;
        }

        .calendar-day-cell.inactive .calendar-day-number {
            color: #cbd5e1;
        }

        /* Active Scheduled Class Day */
        .calendar-day-cell.active-day {
            background: #eff6ff;
            border-color: #3b82f6;
        }

        .calendar-day-cell.active-day .calendar-day-number {
            color: #1d4ed8;
        }

        .calendar-day-indicator {
            align-self: flex-end;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 2px 6px;
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

        .calendar-navigation {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .month-header {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 15px;
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
                        <select id="faculty_select" onchange="loadFacultyCalendar()">
                            <option value="">-- Choose a Faculty --</option>
                            <?php foreach ($faculties as $f): ?>
                                <option value="<?php echo htmlspecialchars($f['facultyCode']); ?>"><?php echo htmlspecialchars($f['facultyName']); ?> (<?php echo htmlspecialchars($f['facultyCode']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button class="btn-bulk" id="bulk_weekdays_btn" onclick="bulkSetWeekdays()" disabled>
                        <i class="ri-calendar-check-line"></i> Pre-populate Weekdays
                    </button>
                </div>

                <div id="calendar_wrapper" style="display: none;">
                    <div class="calendar-navigation">
                        <button class="nav-btn" onclick="navigateMonth(-1)"><i class="ri-arrow-left-s-line"></i></button>
                        <div class="month-header" id="month_title_label">June 2026</div>
                        <button class="nav-btn" onclick="navigateMonth(1)"><i class="ri-arrow-right-s-line"></i></button>
                    </div>

                    <p style="font-size: 0.82rem; color: #64748b; margin-bottom: 15px;">
                        <i class="ri-information-line"></i> Click on any day box below to toggle it as a scheduled class day for this faculty.
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
        let currentDate = new Date(); // Tracks the currently viewed month/year

        const monthNames = [
            "January", "February", "March", "April", "May", "June",
            "July", "August", "September", "October", "November", "December"
        ];

        function loadFacultyCalendar() {
            const select = document.getElementById('faculty_select');
            selectedFaculty = select.value;

            const calendarWrapper = document.getElementById('calendar_wrapper');
            const placeholder = document.getElementById('no_faculty_selected_view');
            const bulkBtn = document.getElementById('bulk_weekdays_btn');

            if (!selectedFaculty) {
                calendarWrapper.style.display = 'none';
                placeholder.style.display = 'block';
                bulkBtn.disabled = true;
                return;
            }

            calendarWrapper.style.display = 'block';
            placeholder.style.display = 'none';
            bulkBtn.disabled = false;

            fetchDates();
        }

        function fetchDates() {
            const formData = new FormData();
            formData.append('action', 'get_dates');
            formData.append('faculty_code', selectedFaculty);

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
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth(); // 0-indexed

            document.getElementById('month_title_label').textContent = `${monthNames[month]} ${year}`;

            const container = document.getElementById('calendar_days_container');
            container.innerHTML = '';

            const firstDayIndex = new Date(year, month, 1).getDay();
            const totalDays = new Date(year, month + 1, 0).getDate();
            const prevTotalDays = new Date(year, month, 0).getDate();

            // Render padding cells for previous month
            for (let i = firstDayIndex; i > 0; i--) {
                const cell = document.createElement('div');
                cell.className = 'calendar-day-cell inactive';
                cell.innerHTML = `<span class="calendar-day-number">${prevTotalDays - i + 1}</span>`;
                container.appendChild(cell);
            }

            // Render active day cells for current month
            for (let day = 1; day <= totalDays; day++) {
                const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                const isActive = scheduledDates.has(dateStr);

                const cell = document.createElement('div');
                cell.className = 'calendar-day-cell' + (isActive ? ' active-day' : '');
                cell.dataset.date = dateStr;
                cell.onclick = () => toggleDay(dateStr, cell);

                cell.innerHTML = `
                    <span class="calendar-day-number">${day}</span>
                    <span class="calendar-day-indicator"><i class="ri-check-line"></i> Class</span>
                `;
                container.appendChild(cell);
            }

            // Render padding cells for next month to complete the row
            const totalCells = firstDayIndex + totalDays;
            const remaining = (7 - (totalCells % 7)) % 7;
            for (let i = 1; i <= remaining; i++) {
                const cell = document.createElement('div');
                cell.className = 'calendar-day-cell inactive';
                cell.innerHTML = `<span class="calendar-day-number">${i}</span>`;
                container.appendChild(cell);
            }
        }

        function navigateMonth(direction) {
            currentDate.setMonth(currentDate.getMonth() + direction);
            renderCalendar();
        }

        function toggleDay(dateStr, cellElement) {
            const formData = new FormData();
            formData.append('action', 'toggle_date');
            formData.append('faculty_code', selectedFaculty);
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
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth() + 1; // 1-indexed

            if (!confirm(`Are you sure you want to pre-populate all Mon-Fri weekdays of ${monthNames[month-1]} ${year} as class days for this faculty?`)) {
                return;
            }

            const btn = document.getElementById('bulk_weekdays_btn');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="ri-loader-4-line" style="animation: spin 1s infinite linear;"></i> Working...';

            const formData = new FormData();
            formData.append('action', 'bulk_weekdays');
            formData.append('faculty_code', selectedFaculty);
            formData.append('year', year);
            formData.append('month', month);

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
