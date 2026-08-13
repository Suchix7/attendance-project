/**
 * Opens a full-screen camera overlay, shows a live feed,
 * auto-detects a face, and saves it into slot `slotIndex`.
 * Used for both initial capture and retake (click on photo).
 */
async function captureWithLivePreview(slotIndex, imageBox) {
  // Prevent double-open for the same slot
  if (imageBox.dataset.capturing === 'true') return;
  imageBox.dataset.capturing = 'true';

  const statusEl = document.getElementById('status_' + slotIndex);

  // ── Build overlay UI ───────────────────────────────────────────────────────
  const overlay = document.createElement('div');
  overlay.style.cssText = [
    'position:fixed;top:0;left:0;width:100%;height:100%;',
    'background:rgba(0,0,0,0.88);z-index:99999;',
    'display:flex;flex-direction:column;align-items:center;',
    'justify-content:center;gap:16px;'
  ].join('');

  const title = document.createElement('p');
  title.textContent = 'Photo ' + slotIndex + ' of 10 — Look straight at the camera';
  title.style.cssText = 'color:#fff;font-size:1.15rem;margin:0;font-weight:600;';

  const video = document.createElement('video');
  video.autoplay   = true;
  video.playsInline = true;
  video.muted      = true;
  video.style.cssText = [
    'width:420px;max-width:90vw;border-radius:14px;',
    'background:#111;object-fit:cover;',
    'box-shadow:0 8px 32px rgba(0,0,0,.6);',
    'transform:scaleX(-1);'
  ].join('');

  const statusMsg = document.createElement('p');
  statusMsg.textContent = 'Looking for face…';
  statusMsg.style.cssText = 'color:#e2e8f0;font-size:.95rem;margin:0;';

  const cancelBtn = document.createElement('button');
  cancelBtn.textContent = 'Cancel';
  cancelBtn.style.cssText = [
    'padding:8px 28px;border-radius:8px;border:none;',
    'background:#ef4444;color:#fff;cursor:pointer;',
    'font-size:.95rem;font-weight:600;'
  ].join('');

  overlay.appendChild(title);
  overlay.appendChild(video);
  overlay.appendChild(statusMsg);
  overlay.appendChild(cancelBtn);
  document.body.appendChild(overlay);

  // ── Camera + face detection ────────────────────────────────────────────────
  let camStream = null;
  let cancelled = false;

  function cleanup() {
    if (camStream) camStream.getTracks().forEach(function(t) { t.stop(); });
    if (document.body.contains(overlay)) document.body.removeChild(overlay);
    imageBox.dataset.capturing = 'false';
  }

  cancelBtn.addEventListener('click', function() {
    cancelled = true;
    cleanup();
    if (statusEl) statusEl.textContent = 'Cancelled — click photo to retry';
  });

  try {
    camStream = await navigator.mediaDevices.getUserMedia({ video: true });
    video.srcObject = camStream;
    await video.play();

    const canvas = document.createElement('canvas');
    const ctx    = canvas.getContext('2d');

    let faceDetected = false;
    let attempts     = 0;
    const maxAttempts = 40; // 20 seconds max

    while (!faceDetected && !cancelled && attempts < maxAttempts) {
      await new Promise(function(r) { setTimeout(r, 500); });
      if (cancelled) break;

      canvas.width  = video.videoWidth  || 640;
      canvas.height = video.videoHeight || 480;

      // Draw mirrored so face coords match what user sees
      ctx.save();
      ctx.translate(canvas.width, 0);
      ctx.scale(-1, 1);
      ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
      ctx.restore();

      var blob = await new Promise(function(r) { canvas.toBlob(r, 'image/jpeg', 0.85); });
      var fd   = new FormData();
      fd.append('image', blob);

      try {
        var resp   = await fetch('detect_face.php', { method: 'POST', body: fd });
        var result = await resp.json();

        if (result.success && result.faces && result.faces.length > 0) {
          var face = result.faces.reduce(function(a, b) {
            return (a.width * a.height > b.width * b.height) ? a : b;
          });
          var ratio = Math.min(face.width, face.height) / Math.min(canvas.width, canvas.height);

          if (ratio >= 0.2) {
            faceDetected = true;
            statusMsg.textContent = '✓ Face captured!';
            statusMsg.style.color = '#4ade80';

            // Save clean image before drawing rectangle
            var cleanImage = canvas.toDataURL('image/png');

            // Draw green rectangle for display
            ctx.strokeStyle = '#00ff00';
            ctx.lineWidth   = 3;
            ctx.strokeRect(face.x, face.y, face.width, face.height);
            var displayImage = canvas.toDataURL('image/png');

            var imgEl   = document.getElementById('image_' + slotIndex + '-captured-image');
            var inputEl = document.getElementById('image_' + slotIndex + '-captured-image-input');
            if (imgEl)   imgEl.src   = displayImage;
            if (inputEl) inputEl.value = cleanImage;
            if (statusEl) statusEl.textContent = 'Captured ✓ — click to retake';

            // Brief pause so user sees the green box
            await new Promise(function(r) { setTimeout(r, 700); });

          } else {
            statusMsg.textContent = 'Move closer to the camera…';
          }
        } else {
          statusMsg.textContent = 'Looking for face… (' + (attempts + 1) + '/' + maxAttempts + ')';
        }
      } catch (e) {
        console.error('Face detection error:', e);
      }

      attempts++;
    }

    if (!faceDetected && !cancelled) {
      if (statusEl) statusEl.textContent = 'No face detected — click photo to retry';
    }

  } catch (err) {
    console.error('Camera error:', err);
    if (statusEl) statusEl.textContent = 'Camera error — click photo to retry';
  } finally {
    cleanup();
  }

  // Report back to the sequential capture loop whether the user cancelled, so
  // it can stop instead of immediately opening the camera for the next slot.
  return cancelled;
}

/**
 * Creates all 10 image boxes immediately (so click handlers exist right away),
 * then auto-captures each one sequentially via the live camera overlay.
 */
const takeMultipleImages = async function() {
  document.getElementById('open_camera').style.display = 'none';

  var container = document.getElementById('multiple-images');
  container.innerHTML = ''; // reset on re-open

  // Step 1: Create all 10 boxes NOW so clicks work immediately
  for (var i = 1; i <= 10; i++) {
    var imageBox = document.createElement('div');
    imageBox.classList.add('image-box');
    imageBox.id           = 'imagebox_' + i;
    imageBox.style.cursor = 'pointer';
    imageBox.title        = 'Click to retake this photo';

    var img   = document.createElement('img');
    img.id    = 'image_' + i + '-captured-image';
    img.src   = 'resources/images/default.png';
    img.alt   = 'Photo ' + i;
    img.style = 'width:100%;height:100%;object-fit:cover;';

    var editIcon = document.createElement('div');
    editIcon.classList.add('edit-icon');
    editIcon.innerHTML = '<i class="fas fa-redo"></i><span class="retake-label">Retake</span>';

    var input  = document.createElement('input');
    input.type = 'hidden';
    input.id   = 'image_' + i + '-captured-image-input';
    input.name = 'capturedImage' + i;

    var status = document.createElement('div');
    status.id  = 'status_' + i;
    status.classList.add('capture-status');
    status.textContent = 'Waiting…';

    imageBox.appendChild(img);
    imageBox.appendChild(editIcon);
    imageBox.appendChild(input);
    imageBox.appendChild(status);
    container.appendChild(imageBox);

    // ← THIS is the retake handler: click any photo → retake it
    (function(idx, box) {
      box.addEventListener('click', function() {
        captureWithLivePreview(idx, box);
      });
    })(i, imageBox);
  }

  // Step 2: Auto-capture each slot sequentially. If the user cancels, stop the
  // whole sequence instead of opening the camera again for the next slot.
  for (var j = 1; j <= 10; j++) {
    var box = document.getElementById('imagebox_' + j);
    var wasCancelled = await captureWithLivePreview(j, box);
    if (wasCancelled) break;
  }
};

// Backwards-compat shim (not used by new flow but kept for safety)
function openCamera(buttonId) {
  var idx = parseInt(buttonId.replace('image_', ''), 10);
  var box = document.getElementById('imagebox_' + idx);
  if (box) captureWithLivePreview(idx, box);
}

// hide and display form
