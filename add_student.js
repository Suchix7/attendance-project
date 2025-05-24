document.addEventListener("DOMContentLoaded", () => {
  const video = document.getElementById("video");
  const canvas = document.getElementById("canvas");
  const overlay = document.getElementById("overlay");
  const startButton = document.getElementById("startButton");
  const captureButton = document.getElementById("captureButton");
  const submitButton = document.getElementById("submitButton");
  const status = document.getElementById("status");
  const captureStatus = document.getElementById("captureStatus");
  const faceList = document.getElementById("faceList");
  const studentForm = document.getElementById("studentForm");

  let stream = null;
  let isDetecting = false;
  let detectionInterval = null;
  let isFaceDetected = false;
  let lastFacePosition = null;
  let frameCount = 0;
  let capturedFaces = [];
  let lastCaptureTime = 0;
  const REQUIRED_FACES = 5;
  const FACE_QUALITY_THRESHOLD = 0.4; // Reduced threshold for face size
  const MIN_FACE_SIZE = 100;
  const AUTO_CAPTURE_INTERVAL = 2000; // 2 seconds between auto captures
  const STABLE_FACE_FRAMES = 10; // Number of frames face must be stable
  let stableFaceCount = 0;

  // Start camera
  startButton.addEventListener("click", async () => {
    try {
      stream = await navigator.mediaDevices.getUserMedia({
        video: {
          width: { ideal: 640 },
          height: { ideal: 480 },
          frameRate: { ideal: 30 },
          facingMode: "user",
        },
      });
      video.srcObject = stream;
      startButton.disabled = true;
      captureButton.disabled = false;
      status.textContent = "Status: Camera started";
      startFaceDetection();
    } catch (err) {
      console.error("Error accessing camera:", err);
      status.textContent = "Status: Error accessing camera";
    }
  });

  // Start real-time face detection
  async function startFaceDetection() {
    if (isDetecting) return;
    isDetecting = true;

    detectionInterval = setInterval(async () => {
      frameCount++;
      if (frameCount % 2 !== 0) return;

      const context = canvas.getContext("2d");
      context.drawImage(video, 0, 0, canvas.width, canvas.height);

      canvas.toBlob(
        async (blob) => {
          const formData = new FormData();
          formData.append("image", blob, "detection.jpg");

          try {
            const response = await fetch("detect_face.php", {
              method: "POST",
              body: formData,
            });

            const result = await response.json();

            if (result.success && result.faces && result.faces.length > 0) {
              const largestFace = result.faces.reduce((largest, current) => {
                const currentArea = current.width * current.height;
                const largestArea = largest.width * largest.height;
                return currentArea > largestArea ? current : largest;
              }, result.faces[0]);

              // Check face size
              const faceSize = Math.min(largestFace.width, largestFace.height);
              const frameSize = Math.min(canvas.width, canvas.height);
              const faceSizeRatio = faceSize / frameSize;

              if (lastFacePosition) {
                const smoothingFactor = 0.3;
                largestFace.x =
                  lastFacePosition.x +
                  (largestFace.x - lastFacePosition.x) * smoothingFactor;
                largestFace.y =
                  lastFacePosition.y +
                  (largestFace.y - lastFacePosition.y) * smoothingFactor;
                largestFace.width =
                  lastFacePosition.width +
                  (largestFace.width - lastFacePosition.width) *
                    smoothingFactor;
                largestFace.height =
                  lastFacePosition.height +
                  (largestFace.height - lastFacePosition.height) *
                    smoothingFactor;
              }
              lastFacePosition = { ...largestFace };

              const ctx = overlay.getContext("2d");
              ctx.clearRect(0, 0, overlay.width, overlay.height);

              // Draw face rectangle
              ctx.strokeStyle =
                faceSizeRatio >= FACE_QUALITY_THRESHOLD ? "#00ff00" : "#ff0000";
              ctx.lineWidth = 3;

              const scaleX = video.clientWidth / canvas.width;
              const scaleY = video.clientHeight / canvas.height;

              ctx.strokeRect(
                largestFace.x * scaleX,
                largestFace.y * scaleY,
                largestFace.width * scaleX,
                largestFace.height * scaleY
              );

              // Draw quality indicator
              ctx.fillStyle =
                faceSizeRatio >= FACE_QUALITY_THRESHOLD ? "#00ff00" : "#ff0000";
              ctx.font = "16px Arial";
              ctx.fillText(
                `Face Size: ${Math.round(faceSizeRatio * 100)}%`,
                10,
                30
              );

              isFaceDetected = true;
              captureButton.disabled = faceSizeRatio < FACE_QUALITY_THRESHOLD;
              status.textContent = `Status: Face detected - ${capturedFaces.length}/${REQUIRED_FACES} faces captured`;

              // Check if face is stable and meets quality threshold
              if (faceSizeRatio >= FACE_QUALITY_THRESHOLD) {
                stableFaceCount++;
                if (stableFaceCount >= STABLE_FACE_FRAMES) {
                  const currentTime = Date.now();
                  if (currentTime - lastCaptureTime >= AUTO_CAPTURE_INTERVAL) {
                    if (capturedFaces.length < REQUIRED_FACES) {
                      await captureFace();
                      lastCaptureTime = currentTime;
                    }
                  }
                }
              } else {
                stableFaceCount = 0;
              }
            } else {
              const ctx = overlay.getContext("2d");
              ctx.clearRect(0, 0, overlay.width, overlay.height);
              isFaceDetected = false;
              captureButton.disabled = true;
              status.textContent = "Status: No face detected";
              lastFacePosition = null;
              stableFaceCount = 0;
            }
          } catch (err) {
            console.error("Error detecting faces:", err);
          }
        },
        "image/jpeg",
        0.8
      );
    }, 50);
  }

  // Capture face function
  async function captureFace() {
    if (!isFaceDetected) {
      captureStatus.textContent =
        "Error: No face detected. Please position your face in the frame.";
      return;
    }

    const context = canvas.getContext("2d");
    context.drawImage(video, 0, 0, canvas.width, canvas.height);

    canvas.toBlob(
      async (blob) => {
        const formData = new FormData();
        formData.append("image", blob, "face.jpg");
        formData.append("faceNumber", capturedFaces.length + 1);

        try {
          const response = await fetch("save_face.php", {
            method: "POST",
            body: formData,
          });

          const result = await response.json();

          if (result.success) {
            capturedFaces.push(result.facePath);
            updateFaceList();
            captureStatus.textContent = `Face ${capturedFaces.length} captured successfully!`;

            if (capturedFaces.length >= REQUIRED_FACES) {
              captureButton.disabled = true;
              submitButton.disabled = false;
              status.textContent =
                "Status: All faces captured! You can now add the student.";
            }
          } else {
            captureStatus.textContent = "Error: " + result.message;
          }
        } catch (err) {
          console.error("Error saving face:", err);
          captureStatus.textContent = "Error saving face";
        }
      },
      "image/jpeg",
      0.9
    );
  }

  // Manual capture button
  captureButton.addEventListener("click", captureFace);

  // Update face list display
  function updateFaceList() {
    faceList.innerHTML = capturedFaces
      .map(
        (face, index) => `
            <div class="face-item">
                <img src="${face}" alt="Face ${
          index + 1
        }" class="face-thumbnail">
                <span>Face ${index + 1}</span>
            </div>
        `
      )
      .join("");
  }

  // Submit student registration
  submitButton.addEventListener("click", async () => {
    if (capturedFaces.length < REQUIRED_FACES) {
      captureStatus.textContent = "Please capture all required faces first.";
      return;
    }

    const formData = new FormData(studentForm);
    formData.append("faces", JSON.stringify(capturedFaces));

    try {
      const response = await fetch("register_student.php", {
        method: "POST",
        body: formData,
      });

      const result = await response.json();

      if (result.success) {
        captureStatus.textContent = "Student registered successfully!";
        setTimeout(() => {
          window.location.href = "index.php";
        }, 2000);
      } else {
        captureStatus.textContent = "Error: " + result.message;
      }
    } catch (err) {
      console.error("Error registering student:", err);
      captureStatus.textContent = "Error registering student";
    }
  });
});
