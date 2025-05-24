document.addEventListener("DOMContentLoaded", () => {
  const video = document.getElementById("video");
  const canvas = document.getElementById("canvas");
  const overlay = document.getElementById("overlay");
  const startButton = document.getElementById("startButton");
  const captureButton = document.getElementById("captureButton");
  const status = document.getElementById("status");
  const attendanceStatus = document.getElementById("attendanceStatus");
  const attendanceRecords = document.getElementById("attendanceRecords");
  const ctx = canvas.getContext("2d");

  let stream = null;
  let isDetecting = false;
  let detectionInterval = null;
  let isFaceDetected = false;
  let lastFacePosition = null;
  let frameCount = 0;
  let skipFrames = 2; // Process every 3rd frame
  let faceSmoothingFactor = 0.3; // Smoothing factor for face position
  let lastRecognitionStatus = null;

  // Start camera
  startButton.addEventListener("click", async () => {
    try {
      stream = await navigator.mediaDevices.getUserMedia({
        video: {
          width: { ideal: 640 },
          height: { ideal: 480 },
          frameRate: { ideal: 30 },
        },
      });
      video.srcObject = stream;
      startButton.disabled = true;
      captureButton.disabled = false;
      status.textContent = "Status: Camera started successfully";
      startFaceDetection();
    } catch (err) {
      console.error("Error accessing camera:", err);
      status.textContent = "Status: Error accessing camera - " + err.message;
    }
  });

  // Start real-time face detection
  async function startFaceDetection() {
    if (!stream) return;

    // Ensure video is playing and has valid dimensions
    if (video.readyState !== video.HAVE_ENOUGH_DATA) {
      requestAnimationFrame(startFaceDetection);
      return;
    }

    // Set canvas dimensions to match video
    if (
      canvas.width !== video.videoWidth ||
      canvas.height !== video.videoHeight
    ) {
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
    }

    // Draw the current video frame
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    frameCount++;
    if (frameCount % skipFrames !== 0) {
      requestAnimationFrame(startFaceDetection);
      return;
    }

    // Ensure canvas has content before converting to data URL
    if (canvas.width === 0 || canvas.height === 0) {
      console.error("Canvas has invalid dimensions");
      requestAnimationFrame(startFaceDetection);
      return;
    }

    let imageData;
    try {
      imageData = canvas.toDataURL("image/jpeg", 0.8);
    } catch (error) {
      console.error("Error generating data URL:", error);
      requestAnimationFrame(startFaceDetection);
      return;
    }

    try {
      const formData = new FormData();
      let blob;
      try {
        blob = dataURLtoBlob(imageData);
      } catch (blobError) {
        console.error("Error converting image to blob:", blobError);
        status.textContent =
          "Status: Error processing image - Invalid image format";
        requestAnimationFrame(startFaceDetection);
        return;
      }
      formData.append("image", blob);

      const response = await fetch("process_attendance.php", {
        method: "POST",
        body: formData,
      });

      let result;
      const responseText = await response.text();
      try {
        result = JSON.parse(responseText);
      } catch (parseError) {
        console.error("Server response:", responseText);
        status.textContent =
          "Status: Error processing face - Invalid server response";
        requestAnimationFrame(startFaceDetection);
        return;
      }

      if (result.message !== lastRecognitionStatus) {
        lastRecognitionStatus = result.message;
        if (result.success) {
          status.textContent = `Status: ${result.message}`;
          if (result.face_location) {
            const { x, y, width, height } = result.face_location;

            if (lastFacePosition) {
              lastFacePosition = {
                x:
                  lastFacePosition.x +
                  (x - lastFacePosition.x) * faceSmoothingFactor,
                y:
                  lastFacePosition.y +
                  (y - lastFacePosition.y) * faceSmoothingFactor,
                width:
                  lastFacePosition.width +
                  (width - lastFacePosition.width) * faceSmoothingFactor,
                height:
                  lastFacePosition.height +
                  (height - lastFacePosition.height) * faceSmoothingFactor,
              };
            } else {
              lastFacePosition = { x, y, width, height };
            }

            // Draw face rectangle with different colors based on recognition status
            ctx.strokeStyle = result.name ? "#00ff00" : "#ff0000";
            ctx.lineWidth = 2;
            ctx.strokeRect(
              lastFacePosition.x,
              lastFacePosition.y,
              lastFacePosition.width,
              lastFacePosition.height
            );

            // Add face detection indicator
            ctx.fillStyle = result.name ? "#00ff00" : "#ff0000";
            ctx.font = "14px Arial";
            ctx.fillText(
              result.name ? "Face Recognized" : "Face Detected",
              lastFacePosition.x,
              lastFacePosition.y - 5
            );

            if (result.name) {
              ctx.fillStyle = "#00ff00";
              ctx.font = "16px Arial";
              ctx.fillText(
                `${result.name}${
                  result.confidence ? ` (${result.confidence.toFixed(1)}%)` : ""
                }`,
                lastFacePosition.x,
                lastFacePosition.y - 25
              );
            }

            isFaceDetected = true;
          } else {
            isFaceDetected = false;
            lastFacePosition = null;
          }

          if (result.records) {
            updateAttendanceList(result.records);
          }
        } else {
          status.textContent = `Status: ${result.message}`;
          isFaceDetected = false;
          lastFacePosition = null;
        }
      }
    } catch (err) {
      console.error("Error processing face:", err);
      status.textContent = "Status: Error processing face - " + err.message;
      isFaceDetected = false;
      lastFacePosition = null;
    }

    requestAnimationFrame(startFaceDetection);
  }

  // Stop face detection
  function stopFaceDetection() {
    if (detectionInterval) {
      clearInterval(detectionInterval);
      isDetecting = false;
    }
  }

  // Capture and process image
  captureButton.addEventListener("click", async () => {
    if (!isFaceDetected) {
      status.textContent =
        "Status: No face detected. Please position your face in the frame.";
      return;
    }

    const context = canvas.getContext("2d");
    context.drawImage(video, 0, 0, canvas.width, canvas.height);

    // Draw face rectangle on capture
    if (lastFacePosition) {
      context.strokeStyle = "#00ff00";
      context.lineWidth = 2;
      context.strokeRect(
        lastFacePosition.x,
        lastFacePosition.y,
        lastFacePosition.width,
        lastFacePosition.height
      );

      // Add face detection indicator
      context.fillStyle = "#00ff00";
      context.font = "14px Arial";
      context.fillText(
        "Face Detected",
        lastFacePosition.x,
        lastFacePosition.y - 5
      );
    }

    // Convert canvas to blob
    canvas.toBlob(
      async (blob) => {
        const formData = new FormData();
        formData.append("image", blob, "capture.jpg");

        try {
          const response = await fetch("process_attendance.php", {
            method: "POST",
            body: formData,
          });

          let result;
          const responseText = await response.text();
          try {
            result = JSON.parse(responseText);
          } catch (parseError) {
            console.error("Server response:", responseText);
            status.textContent =
              "Status: Error processing attendance - Invalid server response";
            return;
          }

          if (result.success) {
            status.textContent = `Status: ${result.message}`;
            updateAttendanceList(result.records);
          } else {
            status.textContent = `Status: ${result.message}`;
          }
        } catch (err) {
          console.error("Error processing attendance:", err);
          status.textContent =
            "Status: Error processing attendance - " + err.message;
        }
      },
      "image/jpeg",
      0.9
    ); // Higher quality for capture
  });

  // Update attendance list
  function updateAttendanceList(records) {
    attendanceRecords.innerHTML = records
      .map(
        (record) => `
            <div class="attendance-record">
                <span class="name">${record.name}</span>
                <span class="time">${record.time}</span>
            </div>
        `
      )
      .join("");
  }

  // Load initial attendance records
  async function loadAttendanceRecords() {
    try {
      const response = await fetch("get_attendance.php");
      const responseText = await response.text();
      let result;
      try {
        result = JSON.parse(responseText);
      } catch (parseError) {
        console.error("Server response:", responseText);
        status.textContent =
          "Status: Error loading attendance records - Invalid server response";
        return;
      }

      if (result.success) {
        updateAttendanceList(result.records);
      } else {
        status.textContent =
          "Status: Error loading attendance records - " + result.message;
      }
    } catch (err) {
      console.error("Error loading attendance records:", err);
      status.textContent =
        "Status: Error loading attendance records - " + err.message;
    }
  }

  // Load attendance records on page load
  loadAttendanceRecords();

  // Convert data URL to Blob
  function dataURLtoBlob(dataURL) {
    if (
      !dataURL ||
      typeof dataURL !== "string" ||
      !dataURL.startsWith("data:")
    ) {
      throw new Error("Invalid data URL format");
    }

    const arr = dataURL.split(",");
    const mimeMatch = arr[0].match(/:(.*?);/);
    if (!mimeMatch) {
      throw new Error("Invalid data URL mime type");
    }

    const mime = mimeMatch[1];
    const bstr = atob(arr[1]);
    let n = bstr.length;
    const u8arr = new Uint8Array(n);
    while (n--) {
      u8arr[n] = bstr.charCodeAt(n);
    }
    return new Blob([u8arr], { type: mime });
  }

  // Clean up when page is unloaded
  window.addEventListener("beforeunload", function () {
    if (stream) {
      stream.getTracks().forEach((track) => track.stop());
    }
  });
});
