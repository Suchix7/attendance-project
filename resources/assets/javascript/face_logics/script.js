// Consolidated Face Recognition and Geolocation script for Lecturer dashboard

let stream = null;
let isProcessing = false;
let isVerifying = false;            // true while a blink-liveness burst is running
let recognitionInterval = null;
let lastRecognitionTime = 0;
let lastRecognizedStudent = null;
let livenessFailStreak = 0;         // consecutive failed liveness checks
let livenessLockedUntil = 0;        // recognition paused until this timestamp

// Active-liveness (blink) capture settings
const LIVENESS_DURATION_MS = 4000;  // length of the blink-capture burst (blink twice)
const LIVENESS_INTERVAL_MS = 130;   // gap between captured frames (~30 frames)
const MAX_LIVENESS_FAILS = 3;       // consecutive failures before a lockout
const LIVENESS_LOCKOUT_MS = 12000;  // pause after repeated spoof failures (anti brute-force)
let userLocation = null;
window.currentAlgorithm = 'lbph';

// Helper to disable/enable all start buttons
function setStartButtonsDisabled(disabled) {
    const startButton = document.getElementById("startButton");
    const startButtonEigen = document.getElementById("startButtonEigen");
    if (startButton) {
        startButton.disabled = disabled;
        startButton.style.opacity = disabled ? '0.6' : '';
        startButton.style.cursor = disabled ? 'not-allowed' : '';
    }
    if (startButtonEigen) {
        startButtonEigen.disabled = disabled;
        startButtonEigen.style.opacity = disabled ? '0.6' : '';
        startButtonEigen.style.cursor = disabled ? 'not-allowed' : '';
    }
}

const RECOGNITION_COOLDOWN = 5000;
const CONFIDENCE_THRESHOLD = (window.ATTENDANCE_SETTINGS && typeof window.ATTENDANCE_SETTINGS.confidenceThreshold !== 'undefined') ? parseInt(window.ATTENDANCE_SETTINGS.confidenceThreshold) : 65;
const MAX_ALLOWED_DISTANCE = 0.1; // 100 meters in kilometers

// Define updateTable globally so it can be called from inline select elements
window.updateTable = function () {
    const courseSelect = document.getElementById("courseSelect");
    const unitSelect = document.getElementById("unitSelect");
    const venueSelect = document.getElementById("venueSelect");

    if (!courseSelect || !unitSelect || !venueSelect) return;

    const selectedCourseID = courseSelect.value;
    const selectedUnitCode = unitSelect.value;
    const selectedVenue = venueSelect.value;

    const xhr = new XMLHttpRequest();
    xhr.open("POST", "resources/pages/lecture/manageFolder.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                const container = document.getElementById("studentTableContainer");
                // Always render the table when the server returned markup — even a
                // "No records found" result comes back as valid table HTML. The old
                // code only rendered on status==="success", so an empty result (e.g.
                // no students in the active semester) showed nothing at all.
                if (container && typeof response.html === "string" && response.html.trim() !== "") {
                    container.innerHTML = response.html;
                    // Start face recognition if video stream is already running
                    if (stream) {
                        startFaceRecognition();
                    }
                } else if (response.status !== "success") {
                    console.error("Error:", response.message);
                    showMessage("Error updating table: " + (response.message || "Unknown error"), "error");
                }
            } catch (e) {
                console.error("Failed to parse response:", e);
            }
        }
    };
    xhr.send(
        "courseID=" + encodeURIComponent(selectedCourseID) +
        "&unitID=" + encodeURIComponent(selectedUnitCode) +
        "&venueID=" + encodeURIComponent(selectedVenue)
    );
};

// Define confirmMarkAbsent globally for the action buttons in the table
window.confirmMarkAbsent = function (element, studentId, course, unit) {
    if (confirm('Are you sure you want to mark this student as absent?')) {
        const row = element.closest('tr');
        const statusCell = row.querySelector('.attendance-status');

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

// Helper function to display messages
function showMessage(message, type = "info") {
    let messageDiv = document.getElementById('messageDiv');
    if (!messageDiv) {
        messageDiv = document.createElement('div');
        messageDiv.id = 'messageDiv';
        document.body.appendChild(messageDiv);
    }
    messageDiv.className = type;
    messageDiv.textContent = message;
    messageDiv.style.display = "block";

    // Style updates for high visibility
    messageDiv.style.position = "fixed";
    messageDiv.style.top = "20px";
    messageDiv.style.right = "20px";
    messageDiv.style.padding = "15px 20px";
    messageDiv.style.borderRadius = "5px";
    messageDiv.style.zIndex = "9999";
    messageDiv.style.maxWidth = "400px";
    messageDiv.style.boxShadow = "0 4px 6px rgba(0, 0, 0, 0.1)";

    if (type === "error") {
        messageDiv.style.backgroundColor = "#f8d7da";
        messageDiv.style.color = "#721c24";
        messageDiv.style.border = "1px solid #f5c6cb";
        setTimeout(() => {
            messageDiv.style.display = "none";
        }, 5000);
    } else if (type === "success") {
        messageDiv.style.backgroundColor = "#d4edda";
        messageDiv.style.color = "#155724";
        messageDiv.style.border = "1px solid #c3e6cb";
        setTimeout(() => {
            messageDiv.style.display = "none";
        }, 3000);
    } else {
        messageDiv.style.backgroundColor = "#d1ecf1";
        messageDiv.style.color = "#0c5460";
        messageDiv.style.border = "1px solid #bee5eb";
        setTimeout(() => {
            messageDiv.style.display = "none";
        }, 3000);
    }
}

// Log message helper
function logWithTime(message, type = 'info') {
    const timestamp = new Date().toLocaleTimeString();
    const logMessage = `[${timestamp}] ${message}`;
    console[type](logMessage);

    const recognitionStatus = document.getElementById("recognitionStatus");
    if (type === 'error' && recognitionStatus) {
        recognitionStatus.innerHTML = `<div class="error">${message}</div>`;
    }
}

// Haversine formula to calculate distance in km
function calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371; // Earth's radius in km
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a =
        Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
        Math.sin(dLon / 2) * Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c;
}

// Stop camera and clean up video resources
function stopCamera() {
    try {
        const video = document.getElementById("video");
        const videoContainer = document.querySelector(".video-container");

        if (stream) {
            const tracks = stream.getTracks();
            tracks.forEach(track => {
                track.stop();
                track.enabled = false;
            });
            stream = null;
        }
        if (video && video.srcObject) {
            const oldTracks = video.srcObject.getTracks();
            oldTracks.forEach(track => {
                track.stop();
                track.enabled = false;
            });
            video.srcObject = null;
        }
        if (video) {
            video.pause();
        }
        if (videoContainer) {
            videoContainer.style.display = "none";
        }
        if (recognitionInterval) {
            clearInterval(recognitionInterval);
            recognitionInterval = null;
        }
        // Reset recognition state so re-launching works correctly
        lastRecognizedStudent = null;
        lastRecognitionTime = 0;
        setStartButtonsDisabled(false);
        isProcessing = false;
        isVerifying = false;
        livenessFailStreak = 0;
        livenessLockedUntil = 0;
    } catch (error) {
        console.error("Error in stopCamera:", error);
    }
}

// Check distance to selected venue
function checkDistanceToVenue() {
    if (!userLocation) {
        throw new Error("Unable to determine your location. Please ensure location services are enabled.");
    }

    const venueCoordinatesText = document.getElementById('venue-coordinates').textContent;
    const matches = venueCoordinatesText.match(/Latitude: ([-\d.]+), Longitude: ([-\d.]+)/);

    if (!matches) {
        throw new Error("Venue coordinates not available. Please select a valid venue.");
    }

    const venueLatitude = parseFloat(matches[1]);
    const venueLongitude = parseFloat(matches[2]);

    const distance = calculateDistance(
        userLocation.lat,
        userLocation.lng,
        venueLatitude,
        venueLongitude
    );

    return {
        distance: distance,
        isWithinRange: distance <= MAX_ALLOWED_DISTANCE,
        distanceText: distance <= 1 ?
            `${(distance * 1000).toFixed(0)} meters` :
            `${distance.toFixed(2)} kilometers`
    };
}

// Start face recognition polling interval
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

// Update attendance record on server
async function updateAttendanceStatus(studentId, course, unit) {
    try {
        const venueSelect = document.getElementById("venueSelect");
        const response = await fetch('update_attendance.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                studentID: studentId,
                course: course,
                unit: unit,
                venue: venueSelect ? venueSelect.value : null,
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
            // Immediately update DOM for instant feedback
            const row = document.querySelector(`tr[data-student-id="${studentId}"]`);
            if (row) {
                const statusCell = row.querySelector('.attendance-status');
                if (statusCell) {
                    statusCell.textContent = 'Present';
                    statusCell.className = 'attendance-status present';
                }
            }
            // Refresh the full table from server to reflect actual DB state
            setTimeout(() => {
                if (typeof updateTable === 'function') {
                    updateTable();
                }
            }, 500);
            return true;
        } else {
            throw new Error(result.message || 'Failed to update attendance');
        }
    } catch (error) {
        logWithTime(`Error updating attendance: ${error.message}`, 'error');
        return false;
    }
}

// Capture a short burst of frames from the live video for the blink check
async function captureLivenessBurst(video, canvas, onTick) {
    const ctx = canvas.getContext("2d");
    const frames = [];
    const start = Date.now();
    let lastSec = -1;
    while (Date.now() - start < LIVENESS_DURATION_MS) {
        if (!stream) break;
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        const blob = await new Promise(r => canvas.toBlob(r, "image/jpeg", 0.8));
        if (blob) frames.push(blob);
        const secLeft = Math.ceil((LIVENESS_DURATION_MS - (Date.now() - start)) / 1000);
        if (onTick && secLeft !== lastSec) { onTick(secLeft); lastSec = secLeft; }
        await new Promise(r => setTimeout(r, LIVENESS_INTERVAL_MS));
    }
    return frames;
}

// Verify the person is live (blinks) before marking attendance. A photo or a
// still image on a phone screen cannot blink, so it never gets marked.
async function verifyLivenessThenMark(studentId, course, unit) {
    if (isVerifying) return;
    isVerifying = true;
    const video = document.getElementById("video");
    const canvas = document.getElementById("canvas");
    const recognitionStatus = document.getElementById("recognitionStatus");
    // Keep the green recognition box on screen during the blink challenge so the
    // user can see their face is still being tracked while marking. (It was drawn
    // in processFrame just before this runs.) The "Please BLINK…" status below
    // makes it clear the box is intentionally held while verifying.
    try {
        const frames = await captureLivenessBurst(video, canvas, (secLeft) => {
            if (recognitionStatus) {
                recognitionStatus.innerHTML =
                    `<div class="info">Please BLINK to confirm attendance for Student ${studentId}... (${secLeft}s)</div>`;
            }
        });
        if (!stream) return;
        if (!frames.length) throw new Error("No frames captured");

        const fd = new FormData();
        frames.forEach((b, i) => fd.append("frames[]", b, `frame_${i}.jpg`));
        fd.append("algorithm", window.currentAlgorithm || 'lbph');

        logWithTime(`Sending ${frames.length} frames for blink-liveness check...`);
        const resp = await fetch("liveness_check.php", { method: "POST", body: fd });
        const res = await resp.json();
        logWithTime("Liveness result: " + JSON.stringify(res));
        if (!stream) return;

        if (res.success && res.live && res.predicted_student_id !== "Unknown") {
            livenessFailStreak = 0;
            lastRecognizedStudent = res.predicted_student_id;
            lastRecognitionTime = Date.now();
            const ok = await updateAttendanceStatus(res.predicted_student_id, course, unit);
            if (ok) {
                showMessage(`Attendance marked for Student ${res.predicted_student_id} (liveness verified)`, "success");
            }
        } else {
            // Not live / not recognised -> do NOT mark.
            lastRecognizedStudent = null;
            livenessFailStreak++;
            const baseMsg = !res.success ? (res.message || "Liveness check error")
                : (res.live ? "Face not recognised during live check — please try again"
                    : "Liveness check failed: please blink naturally (a photo/phone can't be used)");
            if (livenessFailStreak >= MAX_LIVENESS_FAILS) {
                // Too many failures in a row -> lock out briefly so a photo can't
                // be brute-forced by holding it up until a stray blink registers.
                livenessLockedUntil = Date.now() + LIVENESS_LOCKOUT_MS;
                livenessFailStreak = 0;
                const lockMsg = "Repeated liveness failures — a photo or phone cannot be used. Paused briefly.";
                if (recognitionStatus) recognitionStatus.innerHTML = `<div class="error">${lockMsg}</div>`;
                showMessage(lockMsg, "error");
            } else {
                lastRecognitionTime = Date.now() - (RECOGNITION_COOLDOWN - 1500);
                if (recognitionStatus) recognitionStatus.innerHTML = `<div class="error">${baseMsg}</div>`;
                showMessage(baseMsg, "error");
            }
        }
    } catch (error) {
        logWithTime("Liveness error: " + error.message, "error");
        lastRecognizedStudent = null;
        lastRecognitionTime = Date.now() - (RECOGNITION_COOLDOWN - 1500);
    } finally {
        isVerifying = false;
    }
}

// Process frame from camera stream
async function processFrame() {
    const video = document.getElementById("video");
    const canvas = document.getElementById("canvas");
    const overlay = document.getElementById("overlay");
    const recognitionStatus = document.getElementById("recognitionStatus");
    const courseSelect = document.getElementById("courseSelect");
    const unitSelect = document.getElementById("unitSelect");

    if (!video || !canvas || !overlay || !recognitionStatus || !courseSelect || !unitSelect) return false;
    if (isProcessing || isVerifying || !stream) return false;

    const now = Date.now();
    if (now < livenessLockedUntil) return false;   // brief pause after repeated spoof failures
    if (now - lastRecognitionTime < RECOGNITION_COOLDOWN) return false;

    isProcessing = true;
    const context = canvas.getContext("2d");
    const overlayCtx = overlay.getContext("2d");

    try {
        overlayCtx.clearRect(0, 0, overlay.width, overlay.height);
        context.drawImage(video, 0, 0, canvas.width, canvas.height);

        const blob = await new Promise(resolve => canvas.toBlob(resolve, "image/jpeg", 0.95));
        logWithTime("Frame captured, size: " + Math.round(blob.size / 1024) + "KB");

        const formData = new FormData();
        formData.append("image", blob);
        formData.append("algorithm", window.currentAlgorithm || 'lbph');

        logWithTime(`Sending frame for recognition using ${(window.currentAlgorithm || 'lbph').toUpperCase()}...`);
        const response = await fetch("recognize_face.php", {
            method: "POST",
            body: formData
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        if (!stream) return false;

        const result = await response.json();
        logWithTime("Recognition result: " + JSON.stringify(result));

        if (!stream) return false;

        overlayCtx.clearRect(0, 0, overlay.width, overlay.height);

        if (result.success) {
            const face = result.face_location;
            const isRecognized = result.predicted_student_id !== "Unknown" && result.confidence >= CONFIDENCE_THRESHOLD;
            const color = isRecognized ? "#00ff00" : "#ff0000";

            overlayCtx.strokeStyle = color;
            overlayCtx.lineWidth = 2;
            overlayCtx.strokeRect(face.x, face.y, face.width, face.height);

            overlayCtx.fillStyle = color;
            overlayCtx.font = "16px Arial";
            overlayCtx.fillText(
                `${result.predicted_student_id} (${result.confidence.toFixed(1)}%) [${(window.currentAlgorithm || 'lbph').toUpperCase()}]`,
                face.x,
                face.y - 10
            );

            const statusMessage = isRecognized ?
                `Student ${result.predicted_student_id} recognized using ${(window.currentAlgorithm || 'lbph').toUpperCase()} with ${result.confidence.toFixed(1)}% confidence` :
                `Unknown face detected using ${(window.currentAlgorithm || 'lbph').toUpperCase()} (${result.confidence.toFixed(1)}% confidence)`;
            logWithTime(statusMessage);
            recognitionStatus.innerHTML = `<div class="${isRecognized ? "success" : "info"}">${statusMessage}</div>`;

            if (isRecognized) {
                const cooldownExpired = (now - lastRecognitionTime) >= RECOGNITION_COOLDOWN;
                const newStudent = lastRecognizedStudent !== result.predicted_student_id;
                if (!isVerifying && (newStudent || cooldownExpired)) {
                    // Throttle re-entry, then run the blink-liveness challenge.
                    // Attendance is marked only if a real blink is detected.
                    lastRecognitionTime = now;
                    logWithTime(`Face recognised (${result.predicted_student_id}) — running blink-liveness check`);
                    await verifyLivenessThenMark(
                        result.predicted_student_id,
                        courseSelect.value,
                        unitSelect.value
                    );
                }
            }
        } else if (result.message !== "No face detected") {
            logWithTime("Recognition failed: " + result.message, "warn");
            recognitionStatus.innerHTML = `<div class="info">${result.message}</div>`;
        }

        return true;
    } catch (error) {
        if (stream) {
            logWithTime("Error processing frame: " + error.message, "error");
            if (error.message !== "Failed to fetch" && error.name !== "AbortError") {
                recognitionStatus.innerHTML = `<div class="error">Error processing video frame: ${error.message}</div>`;
            }
        }
        return false;
    } finally {
        isProcessing = false;
    }
}

// DOM elements initialization and binding
document.addEventListener("DOMContentLoaded", () => {
    const video = document.getElementById("video");
    const canvas = document.getElementById("canvas");
    const overlay = document.getElementById("overlay");
    const startButton = document.getElementById("startButton");
    const startButtonEigen = document.getElementById("startButtonEigen");
    const endButton = document.getElementById("endAttendance");
    const videoContainer = document.querySelector(".video-container");
    const courseSelect = document.getElementById("courseSelect");
    const unitSelect = document.getElementById("unitSelect");
    const venueSelect = document.getElementById("venueSelect");

    if (!video || !canvas || !overlay || !startButton || !endButton) return;

    // Auto-update table if fields are already selected
    if (courseSelect && unitSelect && venueSelect && courseSelect.value && unitSelect.value && venueSelect.value) {
        updateTable();
    }

    function updateButtonState(distanceInfo) {
        const statusMessage = document.getElementById('status-message');
        if (!statusMessage) return;

        if (!distanceInfo.isWithinRange) {
            startButton.disabled = true;
            startButton.style.backgroundColor = '#ccc';
            startButton.style.cursor = 'not-allowed';
            if (startButtonEigen) {
                startButtonEigen.disabled = true;
                startButtonEigen.style.backgroundColor = '#ccc';
                startButtonEigen.style.cursor = 'not-allowed';
            }
            statusMessage.style.display = 'block';
            statusMessage.textContent = `Cannot launch facial recognition - You are ${distanceInfo.distanceText} away from the venue (Maximum allowed: 100m)`;
        } else {
            startButton.disabled = false;
            startButton.style.backgroundColor = '';
            startButton.style.cursor = 'pointer';
            if (startButtonEigen) {
                startButtonEigen.disabled = false;
                startButtonEigen.style.backgroundColor = '';
                startButtonEigen.style.cursor = 'pointer';
            }
            statusMessage.style.display = 'none';
        }
    }

    function updateDistanceDisplay() {
        const venueCoordinates = document.getElementById('venue-coordinates');
        const distanceDisplay = document.getElementById('distance-display');
        if (!venueCoordinates || !distanceDisplay) return;

        const venueCoordinatesText = venueCoordinates.textContent;
        const matches = venueCoordinatesText.match(/Latitude: ([-\d.]+), Longitude: ([-\d.]+)/);

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

    function getUserLocation() {
        const userCoordinatesElement = document.getElementById('user-coordinates');
        if (!userCoordinatesElement) return;

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
                    if (startButtonEigen) {
                        startButtonEigen.disabled = true;
                        startButtonEigen.style.backgroundColor = '#ccc';
                        startButtonEigen.style.cursor = 'not-allowed';
                    }
                    const statusMessage = document.getElementById('status-message');
                    if (statusMessage) {
                        statusMessage.style.display = 'block';
                        statusMessage.textContent = "Cannot launch facial recognition - Location services are required";
                    }
                }
            );
        } else {
            userCoordinatesElement.textContent = "Geolocation is not supported by your browser.";
            startButton.disabled = true;
            startButton.style.backgroundColor = '#ccc';
            startButton.style.cursor = 'not-allowed';
            if (startButtonEigen) {
                startButtonEigen.disabled = true;
                startButtonEigen.style.backgroundColor = '#ccc';
                startButtonEigen.style.cursor = 'not-allowed';
            }
            const statusMessage = document.getElementById('status-message');
            if (statusMessage) {
                statusMessage.style.display = 'block';
                statusMessage.textContent = "Cannot launch facial recognition - Your browser doesn't support location services";
            }
        }
    }

    // Call getUserLocation immediately
    getUserLocation();

    if (venueSelect) {
        venueSelect.addEventListener('change', () => {
            setTimeout(updateDistanceDisplay, 500);
        });
    }

    // Helper function to commonise launching logic
    async function launchFacialRecognition() {
        if (!courseSelect.value || !unitSelect.value || !venueSelect.value) {
            const statusMessage = document.getElementById('status-message');
            if (statusMessage) {
                statusMessage.style.display = 'block';
                statusMessage.textContent = "Please select course, unit, and venue first";
            }
            return;
        }

        try {
            const distanceInfo = checkDistanceToVenue();
            if (!distanceInfo.isWithinRange) {
                const statusMessage = document.getElementById('status-message');
                if (statusMessage) {
                    statusMessage.style.display = 'block';
                    statusMessage.textContent = `Cannot launch facial recognition - You are ${distanceInfo.distanceText} away from the venue (Maximum allowed: 100m)`;
                }
                return;
            }
        } catch (distError) {
            const statusMessage = document.getElementById('status-message');
            if (statusMessage) {
                statusMessage.style.display = 'block';
                statusMessage.textContent = "Error checking location: " + distError.message;
            }
            return;
        }

        try {
            stopCamera();
            logWithTime(`Starting camera for recognition (${window.currentAlgorithm.toUpperCase()})...`);
            stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    width: { ideal: 640 },
                    height: { ideal: 480 },
                    frameRate: { ideal: 30 }
                }
            });

            video.srcObject = stream;
            await video.play();

            if (videoContainer) {
                videoContainer.style.display = "block";
            }

            // Adjust overlay dimensions to match video stream
            video.onloadedmetadata = () => {
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                overlay.width = video.videoWidth;
                overlay.height = video.videoHeight;
            };

            setStartButtonsDisabled(true);
            startFaceRecognition();
            const statusMessage = document.getElementById('status-message');
            if (statusMessage) {
                statusMessage.style.display = 'none';
            }
            logWithTime(`Camera started successfully with ${window.currentAlgorithm.toUpperCase()}`);
        } catch (error) {
            stopCamera();
            logWithTime("Error: " + error.message, "error");
            const statusMessage = document.getElementById('status-message');
            if (statusMessage) {
                statusMessage.style.display = 'block';
                statusMessage.textContent = "Error accessing camera: " + error.message;
            }
        }
    }

    // Start recognition triggers
    startButton.addEventListener("click", async () => {
        window.currentAlgorithm = 'lbph';
        await launchFacialRecognition();
    });

    if (startButtonEigen) {
        startButtonEigen.addEventListener("click", async () => {
            window.currentAlgorithm = 'eigen';
            await launchFacialRecognition();
        });
    }

    // End attendance taking
    endButton.addEventListener("click", () => {
        stopCamera();
        logWithTime("Attendance taking ended");
        const statusMessage = document.getElementById('status-message');
        if (statusMessage) {
            statusMessage.style.display = 'none';
        }
    });

    // Stop camera on page unload or visibility change
    window.addEventListener('beforeunload', stopCamera);
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopCamera();
        }
    });
});
