<?php


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attendanceData = json_decode(file_get_contents("php://input"), true);
    if ($attendanceData) {
        try {
            $sql = "INSERT INTO tblattendance (studentRegistrationNumber, course, unit, attendanceStatus, dateMarked)  
                VALUES (:studentID, :course, :unit, :attendanceStatus, :date)";

            $stmt = $pdo->prepare($sql);

            foreach ($attendanceData as $data) {
                $studentID = $data['studentID'];
                $attendanceStatus = $data['attendanceStatus'];
                $course = $data['course'];
                $unit = $data['unit'];
                $date = date("Y-m-d");

                // Bind parameters and execute for each attendance record
                $stmt->execute([
                    ':studentID' => $studentID,
                    ':course' => $course,
                    ':unit' => $unit,
                    ':attendanceStatus' => $attendanceStatus,
                    ':date' => $date
                ]);
            }

            $_SESSION['message'] = "Attendance recorded successfully for all entries.";
        } catch (PDOException $e) {
            $_SESSION['message'] = "Error inserting attendance data: " . $e->getMessage();
        }
    } else {
        $_SESSION['message'] = "No attendance data received.";
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
    <title>Lecture Dashboard</title>
    <link rel="stylesheet" href="resources/assets/css/styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.css" rel="stylesheet">
    <style>
        .video-container {
            position: relative;
            width: 640px;
            height: 480px;
            margin: 20px auto;
            border: 2px solid #ccc;
            border-radius: 8px;
            overflow: hidden;
        }

        #video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        #overlay {
            position: absolute;
            top: 0;
            left: 0;
            pointer-events: none;
        }

        #recognitionStatus {
            margin-top: 10px;
            padding: 10px;
            border-radius: 4px;
            text-align: center;
        }

        .success {
            color: #4CAF50;
            background: #E8F5E9;
            padding: 10px;
            border-radius: 4px;
            margin: 5px 0;
        }

        .info {
            color: #2196F3;
            background: #E3F2FD;
            padding: 10px;
            border-radius: 4px;
            margin: 5px 0;
        }

        .error {
            color: #f44336;
            background: #FFEBEE;
            padding: 10px;
            border-radius: 4px;
            margin: 5px 0;
        }

        .attendance-button {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 20px 0;
        }

        #messageDiv {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 5px;
            z-index: 9999;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            font-weight: 500;
            min-width: 200px;
            text-align: center;
        }

        /* Add animation for messages */
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        #messageDiv {
            animation: slideIn 0.3s ease-out;
        }
    </style>
</head>


<body>

    <?php include 'includes/topbar.php'; ?>
    <section class="main">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main--content">
            <div id="messageDiv" class="messageDiv" style="display:none;"></div>
            <p style="font:80px; font-weight:400; color:blue; text-align:center; padding-top:2px;">Please select course,
                unit, and venue first. Before Launching Facial Recognition</p>
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

                <select required name="venue" id="venueSelect" onChange="updateTable()">
                    <option value="" selected>Select Venue</option>
                    <?php
                    $venueNames = getVenueNames();
                    foreach ($venueNames as $venue) {
                        echo '<option value="' . $venue["className"] . '">' . $venue["className"] . '</option>';
                    }
                    ?>
                </select>
            </form>

            <div class="attendance-button">
                <button id="startButton" class="add">Launch Facial Recognition</button>
                <button id="endAttendance" class="add">END Attendance Taking</button>
            </div>

            <div class="video-container" style="display:none;">
                <video id="video" width="640" height="480" autoplay></video>
                <canvas id="canvas" width="640" height="480" style="position: absolute; opacity: 0;"></canvas>
                <canvas id="overlay" width="640" height="480"></canvas>
                <div id="recognitionStatus"></div>
                <div id="captureProgress"></div>
            </div>

            <div class="table-container">
                <div id="studentTableContainer"></div>
            </div>
        </div>
    </section>

    <?php js_asset(["active_link", 'face_logics/script']) ?>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const video = document.getElementById("video");
            const canvas = document.getElementById("canvas");
            const overlay = document.getElementById("overlay");
            const startButton = document.getElementById("startButton");
            const endButton = document.getElementById("endAttendance");
            const videoContainer = document.querySelector(".video-container");
            const recognitionStatus = document.getElementById("recognitionStatus");
            const captureProgress = document.getElementById("captureProgress");
            const courseSelect = document.getElementById("courseSelect");
            const unitSelect = document.getElementById("unitSelect");
            const venueSelect = document.getElementById("venueSelect");

            let stream = null;
            let isProcessing = false;
            let recognitionInterval = null;
            let lastRecognitionTime = 0;
            let lastRecognizedStudent = null;
            const RECOGNITION_COOLDOWN = 5000; // 5 seconds between recognition attempts
            const CONFIDENCE_THRESHOLD = 50; // Minimum confidence threshold for recognition

            // Helper function for logging with timestamp
            function logWithTime(message, type = 'info') {
                const timestamp = new Date().toLocaleTimeString();
                const logMessage = `[${timestamp}] ${message}`;
                console[type](logMessage);

                // Also update the UI status
                if (type === 'error') {
                    recognitionStatus.innerHTML = `<div class="error">${message}</div>`;
                }
            }

            // Start camera and face recognition
            startButton.addEventListener("click", async () => {
                // Check if course, unit, and venue are selected
                if (!courseSelect.value || !unitSelect.value || !venueSelect.value) {
                    logWithTime("Missing required selections: course, unit, or venue", "error");
                    showMessage("Please select course, unit, and venue first", "error");
                    return;
                }

                try {
                    logWithTime("Starting camera...");
                    stream = await navigator.mediaDevices.getUserMedia({
                        video: {
                            width: { ideal: 640 },
                            height: { ideal: 480 },
                            frameRate: { ideal: 30 }
                        }
                    });
                    video.srcObject = stream;
                    await video.play();

                    logWithTime("Camera started successfully");
                    videoContainer.style.display = "block";
                    startButton.disabled = true;
                    startFaceRecognition();
                    showMessage("Face recognition started", "success");
                } catch (err) {
                    logWithTime("Camera error: " + err.message, "error");
                    showMessage("Error accessing camera: " + err.message, "error");
                }
            });

            // End attendance taking
            endButton.addEventListener("click", () => {
                logWithTime("Ending attendance session...");
                try {
                    if (stream) {
                        stream.getTracks().forEach(track => {
                            track.stop();
                            logWithTime("Camera stream stopped");
                        });
                        stream = null;
                    }
                    if (recognitionInterval) {
                        clearInterval(recognitionInterval);
                        recognitionInterval = null;
                        logWithTime("Recognition interval cleared");
                    }
                    videoContainer.style.display = "none";
                    startButton.disabled = false;
                    recognitionStatus.innerHTML = "";
                    captureProgress.innerHTML = "";
                    showMessage("Attendance taking ended", "info");
                    logWithTime("Attendance session ended successfully");

                    // Clear the canvas and overlay
                    const context = canvas.getContext("2d");
                    const overlayCtx = overlay.getContext("2d");
                    context.clearRect(0, 0, canvas.width, canvas.height);
                    overlayCtx.clearRect(0, 0, overlay.width, overlay.height);

                    // Reset recognition state
                    lastRecognizedStudent = null;
                    lastRecognitionTime = 0;
                    isProcessing = false; // Reset processing flag
                } catch (error) {
                    logWithTime("Error ending attendance session: " + error.message, "error");
                    // Don't show error message for clean-up operations
                    console.error(error);
                }
            });

            async function updateAttendanceStatus(studentId, course, unit) {
                try {
                    const response = await fetch('update_attendance.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            studentID: studentId,
                            course: course,
                            unit: unit,
                            attendanceStatus: 'Present',
                            date: new Date().toISOString().split('T')[0]
                        })
                    });

                    if (!response.ok) {
                        throw new Error('Failed to update attendance');
                    }

                    const result = await response.json();
                    if (result.success) {
                        logWithTime(`Attendance updated for Student ${studentId}: Present`);
                        // Update the table row if it exists
                        const row = document.querySelector(`tr[data-student-id="${studentId}"]`);
                        if (row) {
                            const statusCell = row.querySelector('.attendance-status');
                            if (statusCell) {
                                statusCell.textContent = 'Present';
                                statusCell.className = 'attendance-status present';
                            }
                        }
                        return true;
                    } else {
                        throw new Error(result.message || 'Failed to update attendance');
                    }
                } catch (error) {
                    logWithTime(`Error updating attendance: ${error.message}`, 'error');
                    return false;
                }
            }

            async function processFrame() {
                if (isProcessing || !stream) return false;

                const now = Date.now();
                if (now - lastRecognitionTime < RECOGNITION_COOLDOWN) return false;

                isProcessing = true;
                const context = canvas.getContext("2d");
                const overlayCtx = overlay.getContext("2d");

                try {
                    // Clear previous drawings
                    overlayCtx.clearRect(0, 0, overlay.width, overlay.height);

                    // Draw current frame to canvas
                    context.drawImage(video, 0, 0, canvas.width, canvas.height);

                    // Convert canvas to blob
                    const blob = await new Promise(resolve => canvas.toBlob(resolve, "image/jpeg", 0.95));
                    logWithTime("Frame captured, size: " + Math.round(blob.size / 1024) + "KB");

                    // Create form data
                    const formData = new FormData();
                    formData.append("image", blob);

                    // Send frame for face recognition
                    logWithTime("Sending frame for recognition...");
                    const response = await fetch("recognize_face.php", {
                        method: "POST",
                        body: formData
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    // Check if stream is still active before processing response
                    if (!stream) {
                        return false;
                    }

                    const result = await response.json();
                    logWithTime("Recognition result: " + JSON.stringify(result));

                    // Check again if stream is active before drawing
                    if (!stream) {
                        return false;
                    }

                    // Clear previous drawings
                    overlayCtx.clearRect(0, 0, overlay.width, overlay.height);

                    if (result.success) {
                        const face = result.face_location;
                        const isRecognized = result.predicted_student_id !== "Unknown" && result.confidence >= CONFIDENCE_THRESHOLD;
                        const color = isRecognized ? "#00ff00" : "#ff0000";

                        // Draw face rectangle
                        overlayCtx.strokeStyle = color;
                        overlayCtx.lineWidth = 2;
                        overlayCtx.strokeRect(face.x, face.y, face.width, face.height);

                        // Draw recognition result
                        overlayCtx.fillStyle = color;
                        overlayCtx.font = "16px Arial";
                        overlayCtx.fillText(
                            `${result.predicted_student_id} (${result.confidence.toFixed(1)}%)`,
                            face.x,
                            face.y - 10
                        );

                        // Update recognition status
                        const statusMessage = isRecognized ?
                            `Student ${result.predicted_student_id} recognized with ${result.confidence.toFixed(1)}% confidence` :
                            `Unknown face detected (${result.confidence.toFixed(1)}% confidence)`;
                        logWithTime(statusMessage);
                        recognitionStatus.innerHTML = `<div class="${isRecognized ? "success" : "info"}">${statusMessage}</div>`;

                        // If face was recognized with good confidence and it's a different student
                        if (isRecognized && lastRecognizedStudent !== result.predicted_student_id) {
                            lastRecognitionTime = now;
                            lastRecognizedStudent = result.predicted_student_id;
                            logWithTime(`Updating attendance for Student ${result.predicted_student_id}`);

                            // Update attendance status
                            const success = await updateAttendanceStatus(
                                result.predicted_student_id,
                                courseSelect.value,
                                unitSelect.value
                            );

                            if (success) {
                                showMessage(`Attendance marked for Student ${result.predicted_student_id}!`, "success");
                            }
                        }
                    } else if (result.message !== "No face detected") { // Don't show error for no face detected
                        logWithTime("Recognition failed: " + result.message, "warn");
                        recognitionStatus.innerHTML = `<div class="info">${result.message}</div>`;
                    }

                    return true;
                } catch (error) {
                    // Only log errors if the stream is still active
                    if (stream) {
                        logWithTime("Error processing frame: " + error.message, "error");
                        // Don't show error in status for clean-up related errors
                        if (error.message !== "Failed to fetch" && error.name !== "AbortError") {
                            recognitionStatus.innerHTML = `<div class="error">Error processing video frame: ${error.message}</div>`;
                        }
                    }
                    return false;
                } finally {
                    isProcessing = false;
                }
            }

            async function startFaceRecognition() {
                logWithTime("Starting face recognition...");
                if (recognitionInterval) {
                    clearInterval(recognitionInterval);
                }

                recognitionInterval = setInterval(async () => {
                    await processFrame();
                }, 500); // Process every 500ms
            }

            // Helper function to show messages
            function showMessage(message, type) {
                logWithTime(message, type === "error" ? "error" : "info");
                const messageDiv = document.getElementById("messageDiv");
                messageDiv.className = type;
                messageDiv.textContent = message;
                messageDiv.style.display = "block";

                // Hide message after 3 seconds
                setTimeout(() => {
                    messageDiv.style.display = "none";
                }, 3000);
            }
        });
    </script>




</body>

</html>