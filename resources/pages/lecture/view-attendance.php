<?php

$courseCode = isset($_GET['course']) ? $_GET['course'] : '';
$unitCode = isset($_GET['unit']) ? $_GET['unit'] : '';

$studentRows = fetchStudentRecordsFromDatabase($courseCode, $unitCode);

$coursename = "";
if (!empty($courseCode)) {
    $coursename_query = "SELECT name FROM tblcourse WHERE courseCode = '$courseCode'";
    $result = fetch($coursename_query);
    foreach ($result as $row) {

        $coursename = $row['name'];
    }
}
$unitname = "";
if (!empty($unitCode)) {
    $unitname_query = "SELECT name FROM tblunit WHERE unitCode = '$unitCode'";
    $result = fetch($unitname_query);
    foreach ($result as $row) {

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
    <link href="resources/images/logo/attnlg.png" rel="icon">
    <title>lecture Dashboard</title>
    <link rel="stylesheet" href="resources/assets/css/styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.css" rel="stylesheet">
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
                onclick="exportTableToExcel('attendaceTable', '<?php echo $unitCode ?>_on_<?php echo date('Y-m-d'); ?>','<?php echo $coursename ?>', '<?php echo $unitname ?>')">Export
                Attendance As Excel</button>

            <div class="table-container">
                <div class="title">
                    <h2 class="section--title">Mark Attendance</h2>
                    <div class="attendance-controls">
                        <button class="add" id="startCamera"><i class="ri-camera-line"></i>Start Camera</button>
                        <button class="add" id="markManual"><i class="ri-edit-line"></i>Mark Manually</button>
                    </div>
                </div>

                <!-- Face Recognition Camera Interface -->
                <div id="cameraInterface" style="display: none; text-align: center; margin: 20px;">
                    <video id="video" width="640" height="480" autoplay style="border: 2px solid #ccc;"></video>
                    <canvas id="canvas" width="640" height="480" style="display: none;"></canvas>
                    <div style="margin-top: 10px;">
                        <button class="btn-submit" id="captureBtn">Capture</button>
                        <button class="btn-cancel" id="stopCamera">Stop Camera</button>
                    </div>
                    <div id="recognitionResult" style="margin-top: 10px; padding: 10px;"></div>
                </div>

                <!-- Manual Attendance Table -->
                <div id="manualAttendance">
                    <div class="table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Registration No</th>
                                    <th>Name</th>
                                    <th>Course</th>
                                    <th>Unit</th>
                                    <th>Status</th>
                                    <th>Confidence</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sql = "SELECT a.*, s.firstName, s.lastName 
                                       FROM tblattendance a 
                                       JOIN tblstudents s ON a.studentRegistrationNumber = s.registrationNumber 
                                       ORDER BY a.dateMarked DESC";
                                $result = fetch($sql);
                                if ($result) {
                                    foreach ($result as $row) {
                                        echo "<tr>";
                                        echo "<td>" . $row["studentRegistrationNumber"] . "</td>";
                                        echo "<td>" . $row["firstName"] . " " . $row["lastName"] . "</td>";
                                        echo "<td>" . $row["course"] . "</td>";
                                        echo "<td>" . $row["unit"] . "</td>";
                                        echo "<td>" . $row["attendanceStatus"] . "</td>";
                                        echo "<td>" . (isset($row["confidence"]) ? number_format($row["confidence"], 1) . "%" : "Manual") . "</td>";
                                        echo "<td>" . $row["dateMarked"] . "</td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='7'>No records found</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </section>
    <div>
</body>
<?php js_asset(['min/js/filesaver', 'min/js/xlsx', 'active_link']) ?>



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

    let mediaStream = null;

    document.getElementById('startCamera').addEventListener('click', async () => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: true });
            mediaStream = stream;
            const video = document.getElementById('video');
            video.srcObject = stream;
            document.getElementById('cameraInterface').style.display = 'block';
            document.getElementById('manualAttendance').style.display = 'none';
        } catch (err) {
            console.error('Error accessing camera:', err);
            alert('Could not access camera. Please check permissions.');
        }
    });

    document.getElementById('stopCamera').addEventListener('click', () => {
        if (mediaStream) {
            mediaStream.getTracks().forEach(track => track.stop());
            document.getElementById('video').srcObject = null;
            document.getElementById('cameraInterface').style.display = 'none';
            document.getElementById('manualAttendance').style.display = 'block';
        }
    });

    document.getElementById('markManual').addEventListener('click', () => {
        document.getElementById('cameraInterface').style.display = 'none';
        document.getElementById('manualAttendance').style.display = 'block';
        if (mediaStream) {
            mediaStream.getTracks().forEach(track => track.stop());
            document.getElementById('video').srcObject = null;
        }
    });

    document.getElementById('captureBtn').addEventListener('click', () => {
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const context = canvas.getContext('2d');
        const resultDiv = document.getElementById('recognitionResult');

        // Get current course and unit
        const courseSelect = document.querySelector('select[name="course"]');
        const unitSelect = document.querySelector('select[name="unit"]');

        if (!courseSelect.value || !unitSelect.value) {
            resultDiv.innerHTML = '<div class="error">Please select a course and unit first</div>';
            return;
        }

        // Show processing message
        resultDiv.innerHTML = '<div class="info">Processing face recognition...</div>';

        // Draw video frame to canvas
        context.drawImage(video, 0, 0, canvas.width, canvas.height);

        // Get image data
        canvas.toBlob((blob) => {
            const formData = new FormData();
            formData.append('image', blob);
            formData.append('course', courseSelect.value);
            formData.append('unit', unitSelect.value);

            // Send to server for face recognition
            fetch('handle_attendance.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = `
                    <div class="success">
                        <p>${data.message}</p>
                        <p>Student ID: ${data.student_id}</p>
                        <p>Name: ${data.name}</p>
                        <p>Confidence: ${data.confidence.toFixed(1)}%</p>
                    </div>`;
                    // Refresh attendance table after 2 seconds
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    resultDiv.innerHTML = `<div class="error">${data.message}</div>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultDiv.innerHTML = '<div class="error">Error processing face recognition</div>';
            });
        }, 'image/jpeg', 0.8);
    });
</script>

<style>
    .attendance-controls {
        display: flex;
        gap: 10px;
    }

    .info {
        color: #2196F3;
        background: #E3F2FD;
        padding: 10px;
        border-radius: 4px;
        margin: 10px 0;
    }

    .success {
        color: green;
        background: #e8f5e9;
        padding: 10px;
        border-radius: 4px;
        margin: 10px 0;
    }

    .error {
        color: red;
        background: #ffebee;
        padding: 10px;
        border-radius: 4px;
        margin: 10px 0;
    }

    #cameraInterface {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .btn-submit,
    .btn-cancel {
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        margin: 0 5px;
    }

    .btn-submit {
        background: #4CAF50;
        color: white;
    }

    .btn-cancel {
        background: #f44336;
        color: white;
    }
</style>

</html>