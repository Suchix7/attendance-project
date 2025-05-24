//add capture student image
async function captureImage(video) {
  const canvas = document.createElement("canvas");
  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;
  const context = canvas.getContext("2d");

  // Draw current frame to canvas
  context.drawImage(video, 0, 0, canvas.width, canvas.height);

  // Convert canvas to blob for face detection
  const blob = await new Promise((resolve) =>
    canvas.toBlob(resolve, "image/jpeg", 0.8)
  );
  const formData = new FormData();
  formData.append("image", blob);

  try {
    // Send to server for face detection
    const response = await fetch("detect_face.php", {
      method: "POST",
      body: formData,
    });

    const result = await response.json();

    if (result.success && result.faces && result.faces.length > 0) {
      // Get the largest face
      const largestFace = result.faces.reduce((largest, current) => {
        const currentArea = current.width * current.height;
        const largestArea = largest.width * largest.height;
        return currentArea > largestArea ? current : largest;
      }, result.faces[0]);

      // Check face quality
      const faceSize = Math.min(largestFace.width, largestFace.height);
      const frameSize = Math.min(canvas.width, canvas.height);
      const faceSizeRatio = faceSize / frameSize;

      if (faceSizeRatio >= 0.2) {
        // Face must be at least 20% of frame
        // Draw face rectangle
        context.strokeStyle = "#00ff00";
        context.lineWidth = 2;
        context.strokeRect(
          largestFace.x,
          largestFace.y,
          largestFace.width,
          largestFace.height
        );

        // Return the image with face rectangle
        return canvas.toDataURL("image/png");
      } else {
        throw new Error("Face too small - Please move closer");
      }
    } else {
      throw new Error("No face detected - Please look at the camera");
    }
  } catch (error) {
    console.error("Face detection error:", error);
    throw error;
  }
}

function openCamera(buttonId) {
  navigator.mediaDevices
    .getUserMedia({ video: true })
    .then((stream) => {
      const video = document.createElement("video");
      video.srcObject = stream;
      document.body.appendChild(video);

      video.play();

      setTimeout(async () => {
        try {
          const capturedImage = await captureImage(video);
          const imgElement = document.getElementById(
            buttonId + "-captured-image"
          );
          imgElement.src = capturedImage;
          const hiddenInput = document.getElementById(
            buttonId + "-captured-image-input"
          );
          hiddenInput.value = capturedImage;
          document.getElementById(
            buttonId.replace("image_", "status_")
          ).textContent = "Face captured successfully!";
        } catch (error) {
          document.getElementById(
            buttonId.replace("image_", "status_")
          ).textContent = error.message;
        } finally {
          stream.getTracks().forEach((track) => track.stop());
          document.body.removeChild(video);
        }
      }, 500);
    })
    .catch((error) => {
      console.error("Error accessing webcam:", error);
      document.getElementById(
        buttonId.replace("image_", "status_")
      ).textContent = "Camera error - Please try again";
    });
}

const takeMultipleImages = async () => {
  document.getElementById("open_camera").style.display = "none";

  const images = document.getElementById("multiple-images");

  for (let i = 1; i <= 10; i++) {
    // Create the image box element
    const imageBox = document.createElement("div");
    imageBox.classList.add("image-box");

    const imgElement = document.createElement("img");
    imgElement.id = `image_${i}-captured-image`;

    const editIcon = document.createElement("div");
    editIcon.classList.add("edit-icon");

    const icon = document.createElement("i");
    icon.classList.add("fas", "fa-camera");
    icon.setAttribute("onclick", `openCamera("image_"+${i})`);

    const hiddenInput = document.createElement("input");
    hiddenInput.type = "hidden";
    hiddenInput.id = `image_${i}-captured-image-input`;
    hiddenInput.name = `capturedImage${i}`;

    const statusText = document.createElement("div");
    statusText.id = `status_${i}`;
    statusText.classList.add("capture-status");
    statusText.textContent = "Waiting for face...";

    editIcon.appendChild(icon);
    imageBox.appendChild(imgElement);
    imageBox.appendChild(editIcon);
    imageBox.appendChild(hiddenInput);
    imageBox.appendChild(statusText);
    images.appendChild(imageBox);
    await captureImageWithDelay(i);
  }
};

const captureImageWithDelay = async (i) => {
  try {
    // Get camera stream
    const stream = await navigator.mediaDevices.getUserMedia({ video: true });
    const video = document.createElement("video");
    video.srcObject = stream;
    document.body.appendChild(video);
    await video.play();

    // Create canvas for face detection
    const canvas = document.createElement("canvas");
    canvas.width = video.videoWidth || 640;
    canvas.height = video.videoHeight || 480;
    const context = canvas.getContext("2d");

    // Wait for face detection
    let faceDetected = false;
    let attempts = 0;
    const maxAttempts = 30; // 15 seconds (500ms * 30)

    while (!faceDetected && attempts < maxAttempts) {
      context.drawImage(video, 0, 0, canvas.width, canvas.height);

      // Convert canvas to blob and check for face
      const blob = await new Promise((resolve) =>
        canvas.toBlob(resolve, "image/jpeg", 0.8)
      );
      const formData = new FormData();
      formData.append("image", blob);

      try {
        const response = await fetch("detect_face.php", {
          method: "POST",
          body: formData,
        });
        const result = await response.json();

        if (result.success && result.faces && result.faces.length > 0) {
          // Get the largest face
          const largestFace = result.faces.reduce((largest, current) => {
            const currentArea = current.width * current.height;
            const largestArea = largest.width * largest.height;
            return currentArea > largestArea ? current : largest;
          }, result.faces[0]);

          // Check face quality
          const faceSize = Math.min(largestFace.width, largestFace.height);
          const frameSize = Math.min(canvas.width, canvas.height);
          const faceSizeRatio = faceSize / frameSize;

          if (faceSizeRatio >= 0.2) {
            // Face must be at least 20% of frame
            faceDetected = true;
            document.getElementById(`status_${i}`).textContent =
              "Face detected!";

            // Draw face rectangle
            context.strokeStyle = "#00ff00";
            context.lineWidth = 2;
            context.strokeRect(
              largestFace.x,
              largestFace.y,
              largestFace.width,
              largestFace.height
            );

            // Capture the image with detected face
            const capturedImage = canvas.toDataURL("image/png");
            const imgElement = document.getElementById(
              `image_${i}-captured-image`
            );
            imgElement.src = capturedImage;
            const hiddenInput = document.getElementById(
              `image_${i}-captured-image-input`
            );
            hiddenInput.value = capturedImage;
          } else {
            document.getElementById(`status_${i}`).textContent =
              "Face too small - move closer";
          }
        } else {
          document.getElementById(`status_${i}`).textContent =
            "Looking for face...";
        }
      } catch (error) {
        console.error("Face detection error:", error);
      }

      if (!faceDetected) {
        await new Promise((resolve) => setTimeout(resolve, 500));
        attempts++;
      }
    }

    // Stop the video stream and remove the video element
    stream.getTracks().forEach((track) => track.stop());
    document.body.removeChild(video);

    if (!faceDetected) {
      document.getElementById(`status_${i}`).textContent =
        "No face detected - Click to retry";
    }
  } catch (err) {
    console.error("Error accessing camera: ", err);
    document.getElementById(`status_${i}`).textContent =
      "Camera error - Click to retry";
  }
};

//hide and display form
