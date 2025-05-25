var labels = [];
let detectedFaces = [];
let sendingData = false;
let lastErrorTime = 0; // Track when the last error message was shown

// Face recognition script using LBPH
let videoStream = null;
let isProcessing = false;
let recognitionInterval = null;
const video = document.getElementById("video");
const videoContainer = document.querySelector(".video-container");
const startButton = document.getElementById("startButton");
let webcamStarted = false;
const canvas = document.getElementById("canvas");
const overlay = document.getElementById("overlay");
const statusDiv = document.getElementById("recognitionStatus");

function updateTable() {
  var selectedCourseID = document.getElementById("courseSelect").value;
  var selectedUnitCode = document.getElementById("unitSelect").value;
  var selectedVenue = document.getElementById("venueSelect").value;
  var xhr = new XMLHttpRequest();
  xhr.open("POST", "resources/pages/lecture/manageFolder.php", true);
  xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

  xhr.onreadystatechange = function () {
    if (xhr.readyState === 4 && xhr.status === 200) {
      var response = JSON.parse(xhr.responseText);
      if (response.status === "success") {
        document.getElementById("studentTableContainer").innerHTML =
          response.html;
        // Start face recognition if video is already running
        if (videoStream) {
          startFaceRecognition();
        }
      } else {
        console.error("Error:", response.message);
        showMessage("Error updating table: " + response.message, "error");
      }
    }
  };
  xhr.send(
    "courseID=" +
      encodeURIComponent(selectedCourseID) +
      "&unitID=" +
      encodeURIComponent(selectedUnitCode) +
      "&venueID=" +
      encodeURIComponent(selectedVenue)
  );
}

function markAttendance(studentId, name, confidence) {
  document.querySelectorAll("#studentTableContainer tr").forEach((row) => {
    const registrationNumber = row.cells[0]?.innerText?.trim();
    if (registrationNumber === studentId) {
      row.cells[5].innerText = "present";
      showMessage(
        `Marked attendance for ${name} (${confidence.toFixed(1)}% confidence)`
      );
    }
  });
}

function showMessage(message, type = "info") {
  var messageDiv = document.getElementById("messageDiv");
  if (!messageDiv) {
    messageDiv = document.createElement("div");
    messageDiv.id = "messageDiv";
    messageDiv.style.position = "fixed";
    messageDiv.style.top = "20px";
    messageDiv.style.right = "20px";
    messageDiv.style.padding = "15px 20px";
    messageDiv.style.borderRadius = "5px";
    messageDiv.style.transition = "opacity 0.5s";
    messageDiv.style.zIndex = "9999";
    document.body.appendChild(messageDiv);
  }

  // Set styles based on message type
  switch (type) {
    case "success":
      messageDiv.style.backgroundColor = "#4CAF50";
      messageDiv.style.color = "white";
      break;
    case "error":
      messageDiv.style.backgroundColor = "#f44336";
      messageDiv.style.color = "white";
      break;
    default: // info
      messageDiv.style.backgroundColor = "#2196F3";
      messageDiv.style.color = "white";
  }

  messageDiv.style.display = "block";
  messageDiv.innerHTML = message;
  messageDiv.style.opacity = 1;

  setTimeout(function () {
    messageDiv.style.opacity = 0;
    setTimeout(() => (messageDiv.style.display = "none"), 500);
  }, 3000);
}

// Initialize face recognition when start button is clicked
startButton.addEventListener("click", async () => {
  const courseSelect = document.getElementById("courseSelect");
  const unitSelect = document.getElementById("unitSelect");
  const venueSelect = document.getElementById("venueSelect");

  if (!courseSelect.value || !unitSelect.value || !venueSelect.value) {
    showMessage("Please select course, unit, and venue first", "error");
    return;
  }

  try {
    const stream = await navigator.mediaDevices.getUserMedia({
      video: {
        width: { ideal: 640 },
        height: { ideal: 480 },
        facingMode: "user",
      },
    });

    videoStream = stream;
    video.srcObject = stream;
    video.onloadedmetadata = () => {
      videoContainer.style.display = "flex";
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      overlay.width = video.videoWidth;
      overlay.height = video.videoHeight;
      startFaceRecognition();
    };
  } catch (err) {
    console.error("Error accessing camera:", err);
    showMessage("Could not access camera. Please check permissions.", "error");
  }
});

async function startFaceRecognition() {
  if (recognitionInterval) {
    clearInterval(recognitionInterval);
  }

  const context = canvas.getContext("2d");
  const overlayCtx = overlay.getContext("2d");
  let lastRecognizedStudent = null;
  let lastRecognitionTime = 0;
  const RECOGNITION_COOLDOWN = 5000; // 5 seconds between recognitions for the same student

  recognitionInterval = setInterval(async () => {
    if (isProcessing || !videoStream) return;
    isProcessing = true;

    try {
      // Clear previous drawings
      overlayCtx.clearRect(0, 0, overlay.width, overlay.height);

      // Draw current frame to canvas
      context.drawImage(video, 0, 0, canvas.width, canvas.height);

      // Convert canvas to blob
      const blob = await new Promise((resolve) =>
        canvas.toBlob(resolve, "image/jpeg", 0.95)
      );

      // Create form data
      const formData = new FormData();
      formData.append("image", blob);

      // Send frame for face recognition
      const response = await fetch("recognize_face.php", {
        method: "POST",
        body: formData,
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const result = await response.json();
      console.log("Recognition result:", JSON.stringify(result));

      // Clear previous drawings
      overlayCtx.clearRect(0, 0, overlay.width, overlay.height);

      if (result.success) {
        const face = result.face_location;
        const isRecognized =
          result.predicted_student_id !== "Unknown" && result.confidence > 30; // Adjust threshold as needed
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

        if (isRecognized) {
          const now = Date.now();
          // Only update attendance if it's a different student or enough time has passed
          if (
            lastRecognizedStudent !== result.predicted_student_id ||
            now - lastRecognitionTime > RECOGNITION_COOLDOWN
          ) {
            lastRecognizedStudent = result.predicted_student_id;
            lastRecognitionTime = now;

            // Find the student row using the data attribute
            const studentRow = document.querySelector(
              `tr[data-student-id="${result.predicted_student_id}"]`
            );
            if (studentRow) {
              const statusCell = studentRow.querySelector(".attendance-status");

              if (
                statusCell &&
                statusCell.textContent.trim().toLowerCase() !== "present"
              ) {
                // Get course and unit from select elements
                const courseCode =
                  document.getElementById("courseSelect").value;
                const unitCode = document.getElementById("unitSelect").value;

                // Update backend
                fetch('update_attendance.php', {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/json',
                  },
                  body: JSON.stringify({
                    studentID: result.predicted_student_id,
                    course: courseCode,
                    unit: unitCode,
                    attendanceStatus: 'Present'
                  })
                })
                .then(response => {
                  if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                  }
                  return response.json();
                })
                .then(data => {
                  if (data.success) {
                    // Update UI
                    statusCell.textContent = 'Present';
                    statusCell.className = 'attendance-status present';
                    showMessage(`Marked attendance for Student ${result.predicted_student_id} (${result.confidence.toFixed(1)}% confidence)`, "success");
                    statusDiv.innerHTML = '<div class="success">Attendance marked successfully!</div>';
                  } else {
                    throw new Error(data.message || 'Failed to update attendance');
                  }
                })
                .catch(error => {
                  console.error('Error updating attendance:', error);
                  showMessage("Error updating attendance: " + error.message, "error");
                });
              } else {
                console.log("Student already marked present");
              }
            } else {
              console.log(
                "Student not found in table:",
                result.predicted_student_id
              );
            }
          }
        }

        // Show status message
        if (!isRecognized) {
          statusDiv.innerHTML =
            '<div class="info">Face detected but not recognized. Please try again.</div>';
        }
      } else {
        // Show guidance message
        statusDiv.innerHTML =
          '<div class="info">No face detected. Please look directly at the camera.</div>';
      }
    } catch (error) {
      console.error("Error processing frame:", error);
      const now = Date.now();
      if (now - lastErrorTime > 5000) {
        // Only show error every 5 seconds
        showMessage("Error processing video frame: " + error.message, "error");
        lastErrorTime = now;
      }
      statusDiv.innerHTML =
        '<div class="error">Error processing video frame. Please try again.</div>';
    }

    isProcessing = false;
  }, 500); // Reduced from 100ms to 500ms to prevent too frequent updates
}

// End attendance button functionality
document.getElementById("endAttendance").addEventListener("click", async () => {
  try {
    // Stop video stream
    if (videoStream) {
      videoStream.getTracks().forEach((track) => track.stop());
      videoStream = null;
    }

    // Clear recognition interval
    if (recognitionInterval) {
      clearInterval(recognitionInterval);
      recognitionInterval = null;
    }

    // Reset UI elements
    videoContainer.style.display = "none";
    startButton.disabled = false;
    if (statusDiv) statusDiv.innerHTML = "";

    // Clear canvases
    const context = canvas.getContext("2d");
    const overlayCtx = overlay.getContext("2d");
    context.clearRect(0, 0, canvas.width, canvas.height);
    overlayCtx.clearRect(0, 0, overlay.width, overlay.height);

    // Reset processing flags
    isProcessing = false;
    webcamStarted = false;

    showMessage("Attendance taking ended", "info");
  } catch (error) {
    console.error("Error ending attendance:", error);
    // Don't show error message to user, just log it
  }
});

// Clean up when page is unloaded
window.addEventListener("beforeunload", () => {
  if (videoStream) {
    videoStream.getTracks().forEach((track) => track.stop());
  }
  if (recognitionInterval) {
    clearInterval(recognitionInterval);
  }
});
