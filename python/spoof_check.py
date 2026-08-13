"""
spoof_check.py  --  Hardcoded Presentation Attack Detection (anti-spoofing)
===========================================================================

All the anti-spoofing logic for the attendance system lives here, kept
separate from the face-recognition code so it is easy to read, tune and
defend. There is NO machine-learning model — every check is an explainable,
hardcoded computer-vision rule that runs in real time on the CPU.

Two independent checks are provided:

  1. PASSIVE  (single frame)  -> detect_spoof()
     Looks for the physical signatures of a phone/monitor screen or a printed
     photo in one image: moire (FFT high-frequency energy), specular glare and
     loss of sharpness. Thresholds were calibrated on 66 real enrolled faces
     (0 false rejections).

  2. ACTIVE   (frame burst)   -> detect_blink()
     A challenge-response liveness test: a real person is asked to blink, and
     we require a genuine open -> closed -> open eye transition across a short
     burst of frames. A printed photo or a still image on a phone cannot blink.

realtime_recognition.py imports this module; it does not import back, so there
is a clean one-way dependency (recognition depends on spoofing, not vice versa).
"""

import os
import logging
from pathlib import Path

import cv2
import numpy as np


# =============================================================================
# PASSIVE anti-spoof (single frame): moire / glare / sharpness
# =============================================================================
# Tuned so real webcam faces always pass; screens/prints that fall OUTSIDE the
# measured real-face envelope are flagged. Retune against real spoof attempts
# using the "LIVENESS ..." lines logged in python/face_recognition.log.
ANTISPOOF_ENABLED = True    # master switch for the single-frame gate
MOIRE_HF_RATIO_MAX = 0.19   # high-freq energy ratio; screen pixel-grid/moire push it UP (real max ~0.155)
SPECULAR_FRAC_MAX  = 0.18   # fraction of blown-out glare pixels; screen glare pushes it UP (real max ~0.145)
SHARPNESS_MIN      = 50.0   # Laplacian variance; a photo-of-a-photo lowers it (real min ~57.7)
SPOOF_SCORE_THRESHOLD = 2   # weighted flags needed to declare a spoof (moire alone = weight 2)


def _liveness_metrics(gray_face, color_face):
    """Return (sharpness, high-frequency ratio, specular fraction) for a face ROI."""
    fu = cv2.resize(gray_face, (128, 128))
    sharpness = float(cv2.Laplacian(fu, cv2.CV_64F).var())
    f = fu.astype(np.float32)
    win = np.outer(np.hanning(128), np.hanning(128)).astype(np.float32)
    mag = np.abs(np.fft.fftshift(np.fft.fft2(f * win)))
    total = float(mag.sum()) + 1e-6
    Y, X = np.ogrid[:128, :128]
    r = np.sqrt((X - 64) ** 2 + (Y - 64) ** 2)
    hf_ratio = float(mag[r > 40].sum()) / total          # energy outside the low-freq disk
    hsv = cv2.cvtColor(color_face, cv2.COLOR_BGR2HSV)
    v = hsv[:, :, 2]
    s = hsv[:, :, 1]
    specular = float(np.mean((v > 235) & (s < 40)))       # near-white, low-saturation glare
    return sharpness, hf_ratio, specular


def detect_spoof(gray_face, color_face):
    """Decide if a face ROI looks like a photo/phone-screen replay.
    Returns (is_spoof, reason, metrics_string)."""
    if gray_face is None or gray_face.size == 0:
        return False, 'no_face', 'sharpness=0 hf_ratio=0 specular=0 score=0'
    sharpness, hf_ratio, specular = _liveness_metrics(gray_face, color_face)
    score = 0
    reasons = []
    if hf_ratio > MOIRE_HF_RATIO_MAX:      # strongest screen-specific signal
        score += 2
        reasons.append(f'moire(hf={hf_ratio:.3f})')
    if specular > SPECULAR_FRAC_MAX:
        score += 1
        reasons.append(f'glare(spec={specular:.3f})')
    if sharpness < SHARPNESS_MIN:
        score += 1
        reasons.append(f'flat(sharp={sharpness:.1f})')
    metrics = f'sharpness={sharpness:.1f} hf_ratio={hf_ratio:.3f} specular={specular:.3f} score={score}'
    return score >= SPOOF_SCORE_THRESHOLD, (', '.join(reasons) or 'ok'), metrics


def liveness_report(image_path):
    """Diagnostic: run the passive check on one image and return the raw metrics."""
    base_dir = Path(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
    cascade_path = base_dir / 'assets' / 'haarcascade_frontalface_default.xml'
    face_cascade = cv2.CascadeClassifier(str(cascade_path))
    img = cv2.imread(image_path)
    if img is None:
        return {'success': False, 'message': 'Failed to load image'}
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    faces = face_cascade.detectMultiScale(gray, 1.1, 5, minSize=(40, 40))
    if len(faces) == 0:
        return {'success': False, 'message': 'No face detected'}
    x, y, w, h = max(faces, key=lambda fc: fc[2] * fc[3])
    is_spoof, reason, metrics = detect_spoof(gray[y:y + h, x:x + w], img[y:y + h, x:x + w])
    return {'success': True, 'is_spoof': is_spoof, 'reason': reason, 'metrics': metrics}


# =============================================================================
# ACTIVE liveness (frame burst): blink challenge-response
# =============================================================================
# A printed photo or a still image on a phone screen physically cannot blink.
# Over a short burst of frames we watch the eyes and require open -> closed ->
# open transitions. Uses the eye cascade bundled with OpenCV (no download).
BLINK_MIN_OPEN_FRAMES = 3    # frames where both eyes are clearly detected
BLINK_MIN_BLINKS = 1         # required open->closed->open transitions (blink once)
CLOSED_MIN_SHARPNESS = 30.0  # a real closed-eye frame is still sharp; blur dropouts are not
                             # (open frames measured ~75, motion-blur dropouts ~5-8)


def _load_eye_cascade(base_dir):
    """Eye cascade from OpenCV's bundled data, falling back to assets/."""
    candidates = [Path(cv2.data.haarcascades) / 'haarcascade_eye.xml',
                  base_dir / 'assets' / 'haarcascade_eye.xml']
    for c in candidates:
        if c.exists():
            cc = cv2.CascadeClassifier(str(c))
            if not cc.empty():
                return cc
    return None


def _eye_state(gray_face, eye_cascade):
    """Return the number of eyes detected in the upper half of a face ROI.
    Restricting to the upper half avoids nostril/mouth false positives."""
    h, w = gray_face.shape[:2]
    if h < 40 or w < 40:
        return 0
    upper = gray_face[0:int(h * 0.6), :]
    upper = cv2.equalizeHist(upper)
    eyes = eye_cascade.detectMultiScale(
        upper, scaleFactor=1.1, minNeighbors=6,
        minSize=(max(15, w // 8), max(15, h // 10)))
    return len(eyes)


def detect_blink(frames_dir):
    """Analyse a burst of frames (frame_*.jpg in frames_dir) for a real blink.

    This performs NO face recognition -- it only decides liveness and reports
    the clearest eyes-open frame so the caller can recognise the person.

    Returns a dict:
        success, live, blinks, frames_total, frames_face, frames_open,
        frames_closed, best_open_path (str or None)
    """
    try:
        base_dir = Path(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
        cascade_path = base_dir / 'assets' / 'haarcascade_frontalface_default.xml'
        frames_dir = Path(frames_dir)
        if not frames_dir.is_dir():
            return {'success': False, 'message': f'Frames directory not found: {frames_dir}'}
        if not cascade_path.exists():
            return {'success': False, 'message': 'Face cascade not found'}

        face_cascade = cv2.CascadeClassifier(str(cascade_path))
        eye_cascade = _load_eye_cascade(base_dir)
        if eye_cascade is None:
            return {'success': False, 'message': 'Eye cascade not available'}

        frames = sorted(frames_dir.glob('frame_*.jpg'),
                        key=lambda p: int(''.join(filter(str.isdigit, p.stem)) or 0))
        if not frames:
            return {'success': False, 'message': 'No frames received'}

        states = []              # 'open' (>=2 eyes) | 'closed' (0 eyes, sharp) | 'ambiguous' | 'noface'
        frames_face = 0
        best_open = None         # (face_area, path) of the clearest eyes-open frame
        for fp in frames:
            img = cv2.imread(str(fp))
            if img is None:
                continue
            gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
            faces = face_cascade.detectMultiScale(gray, 1.1, 5, minSize=(60, 60))
            if len(faces) == 0:
                states.append('noface')
                continue
            frames_face += 1
            x, y, w, h = max(faces, key=lambda f: f[2] * f[3])
            face_gray = gray[y:y + h, x:x + w]
            n_eyes = _eye_state(face_gray, eye_cascade)
            if n_eyes >= 2:
                states.append('open')
                area = w * h
                if best_open is None or area > best_open[0]:
                    best_open = (area, str(fp))
            else:
                # 0 OR 1 eyes visible -> eyes (partly) closing. During a real blink
                # the cascade often still catches one eye for a frame, so we treat
                # "fewer than two eyes" as a blink frame. Count it ONLY when the face
                # itself is sharp; a blurred/motion frame that merely lost the eyes is
                # not a blink.
                fr = cv2.resize(face_gray, (128, 128))
                sharp = float(cv2.Laplacian(fr, cv2.CV_64F).var())
                states.append('closed' if sharp >= CLOSED_MIN_SHARPNESS else 'ambiguous')

        # Count blinks as open -> closed transitions (the eyelid coming down after
        # the eyes were clearly open). We deliberately do NOT require the eyes to
        # reopen within the burst: a real blink's closed phase is brief and often
        # lands on the last captured frame, which used to make blinks read as 0.
        # A static photo keeps both eyes detected in every frame, so it never
        # produces a closed frame and stays not-live.
        seq = [s for s in states if s in ('open', 'closed')]
        blinks = 0
        seen_open = False
        in_close = False   # inside a closed run that already counted
        for s in seq:
            if s == 'open':
                seen_open = True
                in_close = False
            elif s == 'closed' and seen_open and not in_close:
                blinks += 1     # an eyelid-down event after the eyes were open
                in_close = True

        frames_open = states.count('open')
        is_live = (frames_open >= BLINK_MIN_OPEN_FRAMES) and (blinks >= BLINK_MIN_BLINKS)

        logging.info(f"LIVENESS-BURST frames={len(frames)} face={frames_face} "
                     f"open={frames_open} closed={states.count('closed')} blinks={blinks} "
                     f"live={is_live}")

        return {
            'success': True,
            'live': bool(is_live),
            'blinks': blinks,
            'frames_total': len(frames),
            'frames_face': frames_face,
            'frames_open': frames_open,
            'frames_closed': states.count('closed'),
            'best_open_path': best_open[1] if best_open else None
        }

    except Exception as e:
        logging.error(f"Blink detection error: {str(e)}")
        return {'success': False, 'message': str(e)}
