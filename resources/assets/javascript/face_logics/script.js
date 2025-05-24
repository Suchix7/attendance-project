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
      formData.append("detect_only", "true");

      // Send frame for face detection
      const response = await fetch("detect_face.php", {
        method: "POST",
        body: formData,
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      // Get the response text
      const responseText = await response.text();
      console.log("Server response:", responseText);

      // Try to parse as JSON
      let result;
      try {
        result = JSON.parse(responseText);
      } catch (e) {
        console.error("Error parsing response:", e);
        throw new Error("Failed to parse server response");
      }

      // Clear previous drawings
      overlayCtx.clearRect(0, 0, overlay.width, overlay.height);

      if (result.success && result.faces && result.faces.length > 0) {
        // Draw face rectangles for all detected faces
        result.faces.forEach((face) => {
          // Draw green rectangle
          overlayCtx.strokeStyle = "#00ff00";
          overlayCtx.lineWidth = 2;
          overlayCtx.strokeRect(face.x, face.y, face.width, face.height);

          // Draw label above the face
          overlayCtx.fillStyle = "#00ff00";
          overlayCtx.font = "16px Arial";
          overlayCtx.fillText("Face detected", face.x, face.y - 5);
        });

        // Show success message
        statusDiv.innerHTML =
          '<div class="success">Face detected! Looking good.</div>';
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
  }, 100);
}

document.getElementById("endAttendance").addEventListener("click", function () {
  // Stop video stream and recognition
  if (videoStream) {
    videoStream.getTracks().forEach((track) => track.stop());
    videoStream = null;
  }
  if (recognitionInterval) {
    clearInterval(recognitionInterval);
    recognitionInterval = null;
  }

  // Hide video container
  document.querySelector(".video-container").style.display = "none";

  // Collect attendance data
  const attendanceData = [];
  document
    .querySelectorAll("#studentTableContainer tr")
    .forEach((row, index) => {
      if (index === 0) return; // Skip header row
      const studentID = row.cells[0].innerText.trim();
      const course = row.cells[2].innerText.trim();
      const unit = row.cells[3].innerText.trim();
      const attendanceStatus = row.cells[5].innerText.trim();

      attendanceData.push({ studentID, course, unit, attendanceStatus });
    });

  // Send attendance data to server
  fetch("resources/pages/lecture/handle_attendance.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(attendanceData),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        showMessage("Attendance recorded successfully", "success");
        setTimeout(() => {
          location.reload();
        }, 2000);
      } else {
        showMessage("Error recording attendance: " + data.message, "error");
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      showMessage("Error recording attendance: " + error.message, "error");
    });
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
