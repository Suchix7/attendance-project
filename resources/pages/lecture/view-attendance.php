<?php

require_once __DIR__ . '/../../lib/nepali_calendar.php';

$courseCode = isset($_GET['course']) ? $_GET['course'] : '';
$unitCode = isset($_GET['unit']) ? $_GET['unit'] : '';
$today = date('Y-m-d');

// Get course name
$coursename = "";
if (!empty($courseCode)) {
    try {
        $coursename_query = "SELECT name FROM tblcourse WHERE courseCode = :courseCode";
        $stmt = $pdo->prepare($coursename_query);
        $stmt->execute([':courseCode' => $courseCode]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $coursename = $result['name'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching course name: " . $e->getMessage());
    }
}

// Get unit name
$unitname = "";
if (!empty($unitCode)) {
    try {
        $unitname_query = "SELECT name FROM tblunit WHERE unitCode = :unitCode";
        $stmt = $pdo->prepare($unitname_query);
        $stmt->execute([':unitCode' => $unitCode]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $unitname = $result['name'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching unit name: " . $e->getMessage());
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
                        echo '<option value="' . htmlspecialchars($course["courseCode"]) . '"' .
                            ($courseCode == $course["courseCode"] ? ' selected' : '') . '>' .
                            htmlspecialchars($course["name"]) . '</option>';
                    }
                    ?>
                </select>

                <select required name="unit" id="unitSelect" onChange="updateTable()">
                    <option value="" selected>Select Unit</option>
                    <?php
                    $unitNames = getUnitNames();
                    foreach ($unitNames as $unit) {
                        echo '<option value="' . htmlspecialchars($unit["unitCode"]) . '"' .
                            ($unitCode == $unit["unitCode"] ? ' selected' : '') . '>' .
                            htmlspecialchars($unit["name"]) . '</option>';
                    }
                    ?>
                </select>
            </form>


            <div class="table-container">
                <div class="title">
                    <h2 class="section--title">Mark Attendance</h2>
                    <div class="attendance-controls">
                        <button class="add" id="markManual"><i class="ri-edit-line"></i>Mark Manually</button>
                        <button class="add" id="saveManual" style="display: none; background-color: #28a745; border-color: #28a745;"><i class="ri-save-line"></i>Save Changes</button>
                        <button class="add" id="cancelManual" style="display: none; background-color: #dc3545; border-color: #dc3545;"><i class="ri-close-line"></i>Cancel</button>
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
    <th>Date</th>
    <th class="action-col" style="display:none;">Action</th>
  </tr>
</thead>

                            <tbody>
                                <?php
                                if ($courseCode && $unitCode) {
                                    try {
                                        $sql = "
  SELECT 
    s.registrationNumber,
    s.firstName,
    s.lastName,
    COALESCE(a.attendanceStatus, 'Absent') AS attendanceStatus,
    COALESCE(a.confidence, NULL) AS confidence,
    COALESCE(a.dateMarked, :today) AS dateMarked
  FROM tblstudents s
  LEFT JOIN tblattendance a 
    ON s.registrationNumber = a.studentRegistrationNumber
    AND a.course = :courseCode
    AND a.unit = :unitCode
    AND DATE(a.dateMarked) = :today
  WHERE s.courseCode = :courseCode
  ORDER BY s.registrationNumber ASC
";


                                        $stmt = $pdo->prepare($sql);
                                        $stmt->execute([
                                            ':today' => $today,
                                            ':courseCode' => $courseCode,
                                            ':unitCode' => $unitCode
                                        ]);

                                        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                        if ($result) {
  foreach ($result as $row) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row["registrationNumber"]) . "</td>";
    echo "<td>" . htmlspecialchars($row["firstName"] . " " . $row["lastName"]) . "</td>";
    echo "<td>" . htmlspecialchars($courseCode) . "</td>";
    echo "<td>" . htmlspecialchars($unitCode) . "</td>";
    echo "<td class='status-cell'>" . htmlspecialchars($row["attendanceStatus"]) . "</td>";
    echo "<td>" . htmlspecialchars(formatNepaliDate($row["dateMarked"])) . "</td>";
    echo "<td class='action-col' style='display:none;'>"
      . "<select class='manual-status' data-id='" . htmlspecialchars($row["registrationNumber"]) . "'>"
      . "<option value=''>Change Status</option>"
      . "<option value='Present'" . ($row["attendanceStatus"] == "Present" ? " selected" : "") . ">Present</option>"
      . "<option value='Absent'" . ($row["attendanceStatus"] == "Absent" ? " selected" : "") . ">Absent</option>"
      . "</select>"
      . "</td>";
    echo "</tr>";
  }
}
 else {
                                            echo "<tr><td colspan='8'>No attendance records found for today for this course and unit.</td></tr>";
                                        }
                                    } catch (PDOException $e) {
                                        error_log("Error fetching attendance records: " . $e->getMessage());
                                        echo "<tr><td colspan='8'>Error retrieving attendance records. Please try again later.</td></tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='8'>Please select both course and unit to view today's attendance.</td></tr>";
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

<script>
    // Show/hide Action column and enable manual marking
    const markManualBtn = document.getElementById('markManual');
    const saveManualBtn = document.getElementById('saveManual');
    const cancelManualBtn = document.getElementById('cancelManual');
    const actionCols = document.querySelectorAll('.action-col');
    
    // Store original states and local changes
    let originalStates = {};
    let localChanges = {};

    markManualBtn.addEventListener('click', () => {
        actionCols.forEach(col => col.style.display = 'table-cell');
        markManualBtn.style.display = 'none';
        saveManualBtn.style.display = 'inline-block';
        cancelManualBtn.style.display = 'inline-block';
        
        // Save original status for cancellation
        document.querySelectorAll('.manual-status').forEach(select => {
            const studentId = select.getAttribute('data-id');
            const row = select.closest('tr');
            const statusCell = row.querySelector('.status-cell');
            originalStates[studentId] = {
                statusText: statusCell.textContent,
                selectValue: select.value
            };
        });
        localChanges = {};
    });

    cancelManualBtn.addEventListener('click', () => {
        actionCols.forEach(col => col.style.display = 'none');
        markManualBtn.style.display = 'inline-block';
        saveManualBtn.style.display = 'none';
        cancelManualBtn.style.display = 'none';
        
        // Restore original values
        document.querySelectorAll('.manual-status').forEach(select => {
            const studentId = select.getAttribute('data-id');
            const orig = originalStates[studentId];
            if (orig) {
                select.value = orig.selectValue;
                const row = select.closest('tr');
                const statusCell = row.querySelector('.status-cell');
                statusCell.textContent = orig.statusText;
                statusCell.style.color = '';
                statusCell.style.fontWeight = '';
            }
        });
        localChanges = {};
    });

    // Handle local status change (before saving)
    document.addEventListener('change', function (e) {
        if (!e.target.matches('.manual-status')) return;

        const select = e.target;
        const studentId = select.getAttribute('data-id');
        const status = select.value;
        const row = select.closest('tr');
        const statusCell = row.querySelector('.status-cell');

        if (!status) return;

        // Update local tracking
        localChanges[studentId] = status;
        
        // Show unsaved change in status cell
        statusCell.textContent = status + ' (Unsaved)';
        statusCell.style.color = '#ff9800'; // Amber/Orange to indicate unsaved
        statusCell.style.fontWeight = 'bold';
    });

    // Handle Batch Save
    saveManualBtn.addEventListener('click', () => {
        const studentIds = Object.keys(localChanges);
        if (studentIds.length === 0) {
            alert('No status changes to save.');
            return;
        }

        const course = document.getElementById('courseSelect').value;
        const unit = document.getElementById('unitSelect').value;

        if (!course || !unit) {
            alert('Please select course and unit first.');
            return;
        }

        const records = studentIds.map(studentId => ({
            student_id: studentId,
            status: localChanges[studentId],
            course: course,
            unit: unit
        }));

        saveManualBtn.disabled = true;
        saveManualBtn.textContent = 'Saving...';

        fetch('resources/pages/lecture/mark_manual_attendance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ records: records })
        })
            .then(res => {
                if (!res.ok) {
                    throw new Error('Server returned error status ' + res.status);
                }
                return res.json();
            })
            .then(data => {
                if (data.success) {
                    alert('Attendance saved successfully');
                    location.reload(); // Reload to show updated database data
                } else {
                    throw new Error(data.message || 'Failed to save attendance');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving attendance: ' + error.message);
                saveManualBtn.disabled = false;
                saveManualBtn.innerHTML = '<i class="ri-save-line"></i>Save Changes';
            });
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
        color: #4CAF50;
        background: #E8F5E9;
        padding: 10px;
        border-radius: 4px;
        margin: 10px 0;
        animation: fadeIn 0.3s ease-in;
    }

    .error {
        color: #F44336;
        background: #FFEBEE;
        padding: 10px;
        border-radius: 4px;
        margin: 10px 0;
    }

    .loading {
        color: #2196F3;
        font-style: italic;
    }

    .status-cell {
        font-weight: 500;
    }

    .status-cell.present {
        color: #4CAF50;
    }

    .status-cell.absent {
        color: #F44336;
    }

    #cameraInterface {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .manual-status {
        padding: 4px 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        background: white;
        cursor: pointer;
    }

    .manual-status:hover {
        border-color: #2196F3;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>

<script>
    function updateTable() {
        var courseSelect = document.getElementById("courseSelect");
        var unitSelect = document.getElementById("unitSelect");
        var selectedCourse = courseSelect.value;
        var selectedUnit = unitSelect.value;
        if (selectedCourse && selectedUnit) {
            window.location.href = window.location.pathname + "?course=" + encodeURIComponent(selectedCourse) + "&unit=" + encodeURIComponent(selectedUnit);
        }
    }
</script>

</html>