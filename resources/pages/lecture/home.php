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

// Function to get venue coordinates
function getVenueCoordinates($venue)
{
    global $pdo;
    try {
        $sql = "SELECT latitude, longitude FROM tblvenue WHERE className = :venue";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['venue' => $venue]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
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

        .attendance-status {
            font-weight: bold;
        }

        .delete {
            cursor: pointer;
            color: #dc3545;
        }

        .delete:hover {
            color: #c82333;
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
            
            <div class="cards" style="margin-bottom: 24px; display: flex; gap: 20px;">
                <div class="card card-1" style="flex: 1; padding: 15px; text-align: center;">
                    <strong>Venue Coordinates</strong>
                    <p id="venue-coordinates" style="color: var(--text-muted); margin-top: 5px;">
                        <?php
                        if (isset($_GET['venue']) && !empty($_GET['venue'])) {
                            $coords = getVenueCoordinates($_GET['venue']);
                            if ($coords) {
                                echo "Latitude: {$coords['latitude']}, Longitude: {$coords['longitude']}";
                            } else {
                                echo "Coordinates not available";
                            }
                        } else {
                            echo "Please select a venue";
                        }
                        ?>
                    </p>
                </div>
                <div class="card card-1" style="flex: 1; padding: 15px; text-align: center;">
                    <strong>Your Location</strong>
                    <p id="user-coordinates" style="color: var(--text-muted); margin-top: 5px;">Fetching your location...</p>
                </div>
                <div class="card card-1" style="flex: 1; padding: 15px; text-align: center;">
                    <strong>Distance to Venue</strong>
                    <p id="distance-display" style="color: var(--text-muted); margin-top: 5px;">Calculating distance...</p>
                </div>
            </div>

            <div id="status-message" class="error-main" style="display: none;"></div>
            
            <p style="text-align: center; color: var(--accent); font-weight: 500; font-size: 1.1rem; margin-bottom: 1.5rem;">
                Select course, unit, and venue before launching Facial Recognition
            </p>
            
            <form class="lecture-options" id="selectForm" style="display: flex; gap: 1rem; justify-content: center; max-width: 800px; margin: 0 auto 2rem;">
                <select required name="venue" id="venueSelect" onchange="this.form.submit()" style="max-width: 30%;">
                    <option value="" <?php echo !isset($_GET['venue']) ? 'selected' : ''; ?>>Select Venue</option>
                    <?php
                    $venueNames = getVenueNames();
                    foreach ($venueNames as $venue) {
                        $selected = (isset($_GET['venue']) && $_GET['venue'] === $venue["className"]) ? 'selected' : '';
                        echo '<option value="' . $venue["className"] . '" ' . $selected . '>' . $venue["className"] . '</option>';
                    }
                    ?>
                </select>

                <select required name="course" id="courseSelect" onChange="updateTable()" style="max-width: 30%;">
                    <option value="" <?php echo !isset($_GET['course']) ? 'selected' : ''; ?>>Select Course</option>
                    <?php
                    $courseNames = getCourseNames();
                    foreach ($courseNames as $course) {
                        $selected = (isset($_GET['course']) && $_GET['course'] === $course["courseCode"]) ? 'selected' : '';
                        echo '<option value="' . $course["courseCode"] . '" ' . $selected . '>' . $course["name"] . '</option>';
                    }
                    ?>
                </select>

                <select required name="unit" id="unitSelect" onChange="updateTable()">
                    <option value="" <?php echo !isset($_GET['unit']) ? 'selected' : ''; ?>>Select Unit</option>
                    <?php
                    $unitNames = getUnitNames();
                    foreach ($unitNames as $unit) {
                        $selected = (isset($_GET['unit']) && $_GET['unit'] === $unit["unitCode"]) ? 'selected' : '';
                        echo '<option value="' . $unit["unitCode"] . '" ' . $selected . '>' . $unit["name"] . '</option>';
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

            // Auto-update table if fields are already selected (on page load)
            if (courseSelect.value && unitSelect.value && venueSelect.value) {
                updateTable();
            }

            let stream = null;
            let isProcessing = false;
            let recognitionInterval = null;
            let lastRecognitionTime = 0;
            let lastRecognizedStudent = null;
            let userLocation = null;
            const RECOGNITION_COOLDOWN = 5000;
            const CONFIDENCE_THRESHOLD = 65;
            const MAX_ALLOWED_DISTANCE = 0.1;

            // Helper function for logging with timestamp
            function logWithTime(message, type = 'info') {
                const timestamp = new Date().toLocaleTimeString();
                const logMessage = `[${timestamp}] ${message}`;
                console[type](logMessage);

                // Update recognition status if it's an error
                if (type === 'error' && recognitionStatus) {
                    recognitionStatus.innerHTML = `<div class="error">${message}</div>`;
                }
            }

            // Function to start face recognition
            function startFaceRecognition() {
                if (recognitionInterval) {
                    clearInterval(recognitionInterval);
                }

                recognitionInterval = setInterval(async () => {
                    if (!isProcessing && stream) {
                        await processFrame();
                    }
                }, 500);

                logWithTime("Face recognition started");
            }

            // Function to calculate distance between two points using Haversine formula
            function calculateDistance(lat1, lon1, lat2, lon2) {
                const R = 6371; // Earth's radius in kilometers
                const dLat = (lat2 - lat1) * Math.PI / 180;
                const dLon = (lon2 - lon1) * Math.PI / 180;
                const a =
                    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                    Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                    Math.sin(dLon / 2) * Math.sin(dLon / 2);
                const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
                return R * c; // Distance in kilometers
            }

            // Function to check if user is at the venue
            async function checkUserLocation(venueLatitude, venueLongitude) {
                return new Promise((resolve, reject) => {
                    if ("geolocation" in navigator) {
                        navigator.geolocation.getCurrentPosition(
                            function (position) {
                                const userLat = position.coords.latitude;
                                const userLng = position.coords.longitude;
                                userLocation = { lat: userLat, lng: userLng };

                                const distance = calculateDistance(
                                    userLat, userLng,
                                    parseFloat(venueLatitude),
                                    parseFloat(venueLongitude)
                                );

                                const locationInfo = {
                                    isNearVenue: distance <= MAX_ALLOWED_DISTANCE,
                                    distance: distance,
                                    userCoordinates: userLocation
                                };

                                resolve(locationInfo);
                            },
                            function (error) {
                                reject(error);
                            }
                        );
                    } else {
                        reject(new Error("Geolocation is not supported by your browser."));
                    }
                });
            }

            // Function to update button state based on distance
            function updateButtonState(distanceInfo) {
                const statusMessage = document.getElementById('status-message');

                if (!distanceInfo.isWithinRange) {
                    startButton.disabled = true;
                    startButton.style.backgroundColor = '#ccc';
                    startButton.style.cursor = 'not-allowed';
                    statusMessage.style.display = 'block';
                    statusMessage.textContent = `Cannot launch facial recognition - You are ${distanceInfo.distanceText} away from the venue (Maximum allowed: 100m)`;
                } else {
                    startButton.disabled = false;
                    startButton.style.backgroundColor = '';
                    startButton.style.cursor = 'pointer';
                    statusMessage.style.display = 'none';
                }
            }

            // Modified updateDistanceDisplay function
            function updateDistanceDisplay() {
                const venueCoordinatesText = document.getElementById('venue-coordinates').textContent;
                const matches = venueCoordinatesText.match(/Latitude: ([-\d.]+), Longitude: ([-\d.]+)/);
                const distanceDisplay = document.getElementById('distance-display');

                if (!matches || !userLocation) {
                    distanceDisplay.textContent = "Waiting for coordinates...";
                    return;
                }

                const venueLatitude = parseFloat(matches[1]);
                const venueLongitude = parseFloat(matches[2]);

                const distance = calculateDistance(
                    userLocation.lat,
                    userLocation.lng,
                    venueLatitude,
                    venueLongitude
                );

                const distanceInfo = {
                    distance: distance,
                    isWithinRange: distance <= MAX_ALLOWED_DISTANCE,
                    distanceText: distance <= 1 ?
                        `${(distance * 1000).toFixed(0)} meters` :
                        `${distance.toFixed(2)} kilometers`
                };

                distanceDisplay.textContent = distanceInfo.distanceText;
                updateButtonState(distanceInfo);
            }

            // Modified getUserLocation function
            function getUserLocation() {
                const userCoordinatesElement = document.getElementById('user-coordinates');

                if ("geolocation" in navigator) {
                    navigator.geolocation.watchPosition(
                        function (position) {
                            const latitude = position.coords.latitude;
                            const longitude = position.coords.longitude;
                            userLocation = { lat: latitude, lng: longitude };
                            userCoordinatesElement.textContent = `Latitude: ${latitude.toFixed(6)}, Longitude: ${longitude.toFixed(6)}`;
                            updateDistanceDisplay();
                        },
                        function (error) {
                            switch (error.code) {
                                case error.PERMISSION_DENIED:
                                    userCoordinatesElement.textContent = "Location access denied. Please enable location services.";
                                    break;
                                case error.POSITION_UNAVAILABLE:
                                    userCoordinatesElement.textContent = "Location information unavailable.";
                                    break;
                                case error.TIMEOUT:
                                    userCoordinatesElement.textContent = "Location request timed out.";
                                    break;
                                default:
                                    userCoordinatesElement.textContent = "An unknown error occurred.";
                            }
                            startButton.disabled = true;
                            startButton.style.backgroundColor = '#ccc';
                            startButton.style.cursor = 'not-allowed';
                            document.getElementById('status-message').style.display = 'block';
                            document.getElementById('status-message').textContent = "Cannot launch facial recognition - Location services are required";
                        }
                    );
                } else {
                    userCoordinatesElement.textContent = "Geolocation is not supported by your browser.";
                    startButton.disabled = true;
                    startButton.style.backgroundColor = '#ccc';
                    startButton.style.cursor = 'not-allowed';
                    document.getElementById('status-message').style.display = 'block';
                    document.getElementById('status-message').textContent = "Cannot launch facial recognition - Your browser doesn't support location services";
                }
            }

            // Call getUserLocation when page loads
            getUserLocation();

            // Add venue change event listener to update distance
            venueSelect.addEventListener('change', () => {
                setTimeout(updateDistanceDisplay, 500); // Short delay to ensure venue coordinates are updated
            });

            // Function to stop camera and clean up
            function stopCamera() {
                try {
                    if (stream) {
                        const tracks = stream.getTracks();
                        tracks.forEach(track => {
                            track.stop();
                            track.enabled = false;
                        });
                        stream = null;
                    }
                    if (video.srcObject) {
                        const oldTracks = video.srcObject.getTracks();
                        oldTracks.forEach(track => {
                            track.stop();
                            track.enabled = false;
                        });
                        video.srcObject = null;
                    }
                    video.pause();
                    videoContainer.style.display = "none";
                    if (recognitionInterval) {
                        clearInterval(recognitionInterval);
                        recognitionInterval = null;
                    }
                    startButton.disabled = false;
                    isProcessing = false;
                } catch (error) {
                    console.error("Error in stopCamera:", error);
                }
            }

            // Function to check if user is within allowed distance
            async function checkDistanceToVenue() {
                try {
                    // First check if we have current user location
                    if (!userLocation) {
                        throw new Error("Unable to determine your location. Please ensure location services are enabled.");
                    }

                    // Get venue coordinates from the display
                    const venueCoordinatesText = document.getElementById('venue-coordinates').textContent;
                    const matches = venueCoordinatesText.match(/Latitude: ([-\d.]+), Longitude: ([-\d.]+)/);

                    if (!matches) {
                        throw new Error("Venue coordinates not available. Please select a valid venue.");
                    }

                    const venueLatitude = parseFloat(matches[1]);
                    const venueLongitude = parseFloat(matches[2]);

                    // Calculate current distance
                    const distance = calculateDistance(
                        userLocation.lat,
                        userLocation.lng,
                        venueLatitude,
                        venueLongitude
                    );

                    // Return distance information
                    return {
                        distance: distance,
                        isWithinRange: distance <= MAX_ALLOWED_DISTANCE,
                        distanceText: distance <= 1 ?
                            `${(distance * 1000).toFixed(0)} meters` :
                            `${distance.toFixed(2)} kilometers`
                    };
                } catch (error) {
                    throw error;
                }
            }

            // Start camera and face recognition
            startButton.addEventListener("click", async () => {
                if (!courseSelect.value || !unitSelect.value || !venueSelect.value) {
                    document.getElementById('status-message').style.display = 'block';
                    document.getElementById('status-message').textContent = "Please select course, unit, and venue first";
                    return;
                }

                // Distance check before launching
                try {
                    const distanceInfo = await checkDistanceToVenue();
                    if (!distanceInfo.isWithinRange) {
                        document.getElementById('status-message').style.display = 'block';
                        document.getElementById('status-message').textContent = `Cannot launch facial recognition - You are ${distanceInfo.distanceText} away from the venue (Maximum allowed: 100m)`;
                        return;
                    }
                } catch (distError) {
                    document.getElementById('status-message').style.display = 'block';
                    document.getElementById('status-message').textContent = "Error checking location: " + distError.message;
                    return;
                }

                try {
                    stopCamera();
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

                    videoContainer.style.display = "block";
                    startButton.disabled = true;
                    startFaceRecognition();
                    document.getElementById('status-message').style.display = 'none';
                    logWithTime("Camera started successfully");
                } catch (error) {
                    stopCamera();
                    logWithTime("Error: " + error.message, "error");
                    document.getElementById('status-message').style.display = 'block';
                    document.getElementById('status-message').textContent = "Error accessing camera: " + error.message;
                }
            });

            // End attendance taking
            endButton.addEventListener("click", () => {
                stopCamera();
                logWithTime("Attendance taking ended");
                document.getElementById('status-message').style.display = 'none';
            });

            // Add event listener for page unload to ensure camera is stopped
            window.addEventListener('beforeunload', () => {
                stopCamera();
            });

            // Add event listener for visibility change to stop camera when page is hidden
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    stopCamera();
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
                            venue: venueSelect.value,
                            latitude: userLocation ? userLocation.lat : null,
                            longitude: userLocation ? userLocation.lng : null,
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

            // Modify the showMessage function to make it more visible and persistent for errors
            function showMessage(message, type) {
                const messageDiv = document.getElementById('messageDiv');
                messageDiv.className = type;
                messageDiv.textContent = message;
                messageDiv.style.display = "block";

                // Style updates for better visibility
                messageDiv.style.position = "fixed";
                messageDiv.style.top = "20px";
                messageDiv.style.right = "20px";
                messageDiv.style.padding = "15px 20px";
                messageDiv.style.borderRadius = "5px";
                messageDiv.style.zIndex = "9999";
                messageDiv.style.maxWidth = "400px";
                messageDiv.style.boxShadow = "0 4px 6px rgba(0, 0, 0, 0.1)";

                // Set colors based on message type
                if (type === "error") {
                    messageDiv.style.backgroundColor = "#f8d7da";
                    messageDiv.style.color = "#721c24";
                    messageDiv.style.border = "1px solid #f5c6cb";
                    // For errors, show message longer
                    setTimeout(() => {
                        messageDiv.style.display = "none";
                    }, 5000); // 5 seconds for errors
                } else {
                    messageDiv.style.backgroundColor = "#d4edda";
                    messageDiv.style.color = "#155724";
                    messageDiv.style.border = "1px solid #c3e6cb";
                    // For success messages, shorter display time
                    setTimeout(() => {
                        messageDiv.style.display = "none";
                    }, 3000); // 3 seconds for success messages
                }
            }

            // Add the confirmMarkAbsent function
            window.confirmMarkAbsent = function (element, studentId, course, unit) {
                if (confirm('Are you sure you want to mark this student as absent?')) {
                    // Get the row
                    const row = element.closest('tr');
                    const statusCell = row.querySelector('.attendance-status');

                    // Send request to update database
                    fetch('resources/pages/lecture/update_attendance.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            studentID: studentId,
                            course: course,
                            unit: unit,
                            attendanceStatus: 'Absent',
                            date: new Date().toISOString().split('T')[0]
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Update the status cell
                                statusCell.textContent = 'Absent';
                                statusCell.style.color = '#dc3545';
                                showMessage('Attendance updated successfully', 'success');
                            } else {
                                showMessage('Failed to update attendance: ' + data.message, 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showMessage('An error occurred while updating attendance', 'error');
                        });
                }
            };
        });
    </script>




</body>

</html>