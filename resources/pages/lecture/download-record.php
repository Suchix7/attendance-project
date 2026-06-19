<?php

require_once __DIR__ . '/../../lib/nepali_calendar.php';

$courseCode = isset($_GET['course']) ? $_GET['course'] : '';
$unitCode = isset($_GET['unit']) ? $_GET['unit'] : '';

$studentRows = fetchStudentRecordsFromDatabase($courseCode, $unitCode);

$coursename = "";
if (!empty($courseCode)) {
    $coursename_query = "SELECT name FROM tblcourse WHERE courseCode = '$courseCode'";
    $result = fetch($coursename_query);


    if ($result) {
        foreach ($result as $row) {
            $coursename = $row['name'];
        }
    }
}
$unitname = "";
if (!empty($unitCode)) {
    $unitname_query = "SELECT name FROM tblunit WHERE unitCode = '$unitCode'";
    $result = fetch($unitname_query);
    if ($result) {
        foreach ($result as $row)
            $unitname = $row['name'];
    }
}


?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="resources/images/logo/face logo.png" rel="icon">
    <title>lecture Dashboard</title>
    <link rel="stylesheet" href="resources/assets/css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css" rel="stylesheet">
</head>



<body>
    <?php include 'includes/topbar.php'; ?>
    <section class="main">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main--content">
            <form class="lecture-options" id="selectForm">
                <select required name="course" id="courseSelect" onChange="updateTable()">
                    <option value="" selected>Select Course</option>
                    <?php
                    $courseNames = getCourseNames();
                    foreach ($courseNames as $course) {
                        echo '<option value="' . $course["courseCode"] . '">' . $course["name"] . '</option>';
                    }
                    ?>
                </select>

                <select required name="unit" id="unitSelect" onChange="updateTable()">
                    <option value="" selected>Select Unit</option>
                    <?php
                    $unitNames = getUnitNames();
                    foreach ($unitNames as $unit) {
                        echo '<option value="' . $unit["unitCode"] . '">' . $unit["name"] . '</option>';
                    }
                    ?>
                </select>
            </form>

            <button class="add"
                onclick="exportTableToExcel('attendaceTable', '<?php echo $unitCode . '_on_' . formatNepaliDate(date('Y-m-d'), 'numeric'); ?>','<?php echo $coursename ?>', '<?php echo $unitname ?>')">Export
                Attendance As Excel</button>

            <div class="table-container">
                <div class="title">
                    <h2 class="section--title">Attendance Preview</h2>
                </div>
                <div class="table attendance-table" id="attendaceTable">
                    <table>
                        <?php
                        // Get faculty code for the course
                        $facultyCode = null;
                        if (!empty($courseCode)) {
                            $stmtFaculty = $pdo->prepare("SELECT f.facultyCode FROM tblcourse c JOIN tblfaculty f ON c.facultyID = f.Id WHERE c.courseCode = ?");
                            $stmtFaculty->execute([$courseCode]);
                            $facultyCode = $stmtFaculty->fetchColumn();
                        }

                        // Resolve active semester ID
                        $semesterId = 0;
                        if ($facultyCode && function_exists('getActiveSemester')) {
                            $activeSem = getActiveSemester($pdo, $facultyCode);
                            if ($activeSem) {
                                $semesterId = $activeSem['Id'];
                            }
                        }

                        // Get distinct dates within active semester's calendar (or fallback to tblattendance if none set)
                        $calendarDates = [];
                        if ($facultyCode && $semesterId) {
                            $stmtCal = $pdo->prepare("SELECT DISTINCT classDate FROM tblfacultycalendar WHERE facultyCode = ? AND semesterID = ? AND classDate <= CURDATE() ORDER BY classDate ASC");
                            $stmtCal->execute([$facultyCode, $semesterId]);
                            $calendarDates = $stmtCal->fetchAll(PDO::FETCH_COLUMN);
                        }

                        if (empty($calendarDates) && $courseCode && $unitCode) {
                            $distinctDatesQuery = "SELECT DISTINCT dateMarked FROM tblattendance WHERE course = :courseCode AND unit = :unitCode ORDER BY dateMarked ASC";
                            $stmtDates = $pdo->prepare($distinctDatesQuery);
                            $stmtDates->execute([':courseCode' => $courseCode, ':unitCode' => $unitCode]);
                            $calendarDates = $stmtDates->fetchAll(PDO::FETCH_COLUMN);
                        }

                        // Build thead with Nepali date headers
                        echo '<thead><tr>';
                        echo '<th>Registration No</th>';
                        echo '<th>Student Name</th>';
                        
                        foreach ($calendarDates as $dateVal) {
                            $npDate = formatNepaliDate($dateVal, 'short');
                            echo '<th>' . htmlspecialchars($npDate) . '</th>';
                        }
                        echo '</tr></thead>';
                        
                        // Get students in course filtered by active semester
                        if ($semesterId) {
                            $studentsQuery = "SELECT registrationNumber, firstName, lastName FROM tblstudents WHERE courseCode = :courseCode AND semesterID = :semesterID ORDER BY firstName, lastName";
                            $stmtStudents = $pdo->prepare($studentsQuery);
                            $stmtStudents->execute([':courseCode' => $courseCode, ':semesterID' => $semesterId]);
                        } else {
                            $studentsQuery = "SELECT registrationNumber, firstName, lastName FROM tblstudents WHERE courseCode = :courseCode ORDER BY firstName, lastName";
                            $stmtStudents = $pdo->prepare($studentsQuery);
                            $stmtStudents->execute([':courseCode' => $courseCode]);
                        }
                        $studentRows = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);

                        echo '<tbody>';
                        foreach ($studentRows as $row) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['registrationNumber']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['firstName'] . " " . $row['lastName']) . "</td>";

                            foreach ($calendarDates as $date) {
                                $attendanceQuery = "SELECT attendanceStatus FROM tblattendance 
                                                    WHERE studentRegistrationNumber = :sReg 
                                                    AND dateMarked = :date 
                                                    AND course = :courseCode 
                                                    AND unit = :unitCode";
                                $stmtAtt = $pdo->prepare($attendanceQuery);
                                $stmtAtt->execute([
                                    ':sReg'       => $row['registrationNumber'],
                                    ':date'       => $date,
                                    ':courseCode' => $courseCode,
                                    ':unitCode'   => $unitCode
                                ]);
                                $attResult = $stmtAtt->fetch(PDO::FETCH_ASSOC);
                                echo "<td>" . ($attResult ? htmlspecialchars($attResult['attendanceStatus']) : 'Absent') . "</td>";
                            }
                            echo "</tr>";
                        }
                        echo '</tbody>';
                        ?>

                    </table>



                </div>
            </div>

        </div>
        </div>
    </section>
    <div>
</body>


<?php js_asset(["min/js/filesaver", "min/js/xlsx", "active_link"]) ?>
<script>
    function updateTable() {
        var courseSelect = document.getElementById("courseSelect");
        var unitSelect = document.getElementById("unitSelect");

        var selectedCourse = courseSelect.value;
        var selectedUnit = unitSelect.value;

        var url = "download-record";
        if (selectedCourse && selectedUnit) {
            url += "?course=" + encodeURIComponent(selectedCourse) + "&unit=" + encodeURIComponent(selectedUnit);
            window.location.href = url;

        }
    }

    function exportTableToExcel(tableId, filename = '', courseCode = '', unitCode = '') {
        var table = document.getElementById(tableId);
        var currentDate = new Date();
        var formattedDate = currentDate.toLocaleDateString(); // Format the date as needed

        var headerContent = '<p style="font-weight:700;"> Attendance for : ' + courseCode + ' Unit name : ' + unitCode + ' On: ' + formattedDate + '</p>';
        var tbody = document.createElement('tbody');
        var additionalRow = tbody.insertRow(0);
        var additionalCell = additionalRow.insertCell(0);
        additionalCell.innerHTML = headerContent;
        table.insertBefore(tbody, table.firstChild);
        var wb = XLSX.utils.table_to_book(table, {
            sheet: "Attendance"
        });
        var wbout = XLSX.write(wb, {
            bookType: 'xlsx',
            bookSST: true,
            type: 'binary'
        });
        var blob = new Blob([s2ab(wbout)], {
            type: 'application/octet-stream'
        });
        if (!filename.toLowerCase().endsWith('.xlsx')) {
            filename += '.xlsx';
        }

        saveAs(blob, filename);
    }

    function s2ab(s) {
        var buf = new ArrayBuffer(s.length);
        var view = new Uint8Array(buf);
        for (var i = 0; i < s.length; i++) view[i] = s.charCodeAt(i) & 0xFF;
        return buf;
    }
</script>

</html>