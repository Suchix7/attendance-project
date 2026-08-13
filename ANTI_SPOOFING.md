# Anti-Spoofing Design (Presentation Attack Detection)

This system defends against **presentation attacks** — someone trying to mark
attendance using a **printed photo** or a **face shown on a phone/monitor
screen** instead of their real face. It uses a **hardcoded, multi-layer
(defense-in-depth) approach** — no machine-learning anti-spoof model, no GPU,
no external dependencies. Every layer runs in real time on CPU and its decision
rules are fully transparent and explainable.

The correct academic term for this is **Presentation Attack Detection (PAD)**,
defined in **ISO/IEC 30107**. The fake photo/phone is the **Presentation Attack
Instrument (PAI)**.

---

## The four layers

### Layer 1 — Active liveness: blink challenge-response (PRIMARY defense)
When a known face appears, the system does **not** mark attendance immediately.
It runs a ~4-second **challenge**: it prompts the person to **blink twice** and
records a short burst of frames. Using OpenCV's eye cascade, it looks for a real
**open → closed → open** eye transition.

- A **printed photo or a still image on a phone cannot blink** → rejected.
- Requires **two** blinks, not one, so a stray detection glitch can't pass.
- A "closed-eye" frame is only counted if the face is **sharp** — this rejects
  motion-blur glitches (from a shaken photo) that could otherwise look like a
  blink.
- After **3 failed attempts** the system **locks out for ~12 seconds**, so an
  attacker cannot brute-force it by holding a photo up until a glitch slips
  through.

This is a standard, recognised PAD technique: **active liveness /
challenge-response**. It defeats the most common attack — the still photo.

### Layer 2 — Multi-algorithm consensus
Recognition requires **2 of 3 independently-trained models (LBPH, Eigenfaces,
Fisherfaces) to agree** on the same student before a match is accepted.
A single model (LBPH) can occasionally match a *stranger* — such as a random
face on a phone — to an enrolled student. A wrong match rarely fools more than
one algorithm, so consensus removes almost all of those false accepts.

### Layer 3 — Passive texture analysis (single frame)
Each frame is screened for the physical signatures of a screen/print using three
classical computer-vision signals:
- **Moiré / high-frequency energy (FFT):** a phone/monitor's pixel grid beats
  against the webcam sensor and raises high-frequency energy.
- **Specular glare:** screens and glossy prints produce blown-out highlights.
- **Sharpness (Laplacian variance):** a photo-of-a-photo often loses fine detail.
Thresholds were **calibrated on 66 real enrolled face images (0 false
rejections)** so real faces always pass; frames outside that envelope are
rejected as a screen.

### Layer 4 — Geolocation gate (context)
Recognition only runs when the device is within **100 m of the venue**
(Haversine distance), so attendance cannot be marked remotely.

---

## How to describe it to an external examiner

> "Spoofing is handled with a layered Presentation Attack Detection design
> rather than one check. The primary layer is an **active liveness
> challenge-response**: the student is asked to blink, and attendance is only
> marked if a genuine eye-blink is detected across a live frame burst — a
> printed photo or a still phone image physically cannot blink. This is backed
> by **multi-algorithm consensus**, which requires two of three recognisers to
> agree so a stranger's photo isn't misidentified, and by **passive texture
> analysis** that detects screen artefacts like moiré and glare on each frame.
> All of it is lightweight, runs in real time on CPU, needs no training data,
> and every decision rule is explainable."

### Why hardcoded (a legitimate engineering justification)
- **Real-time on CPU** — no GPU, no heavy inference per frame.
- **No training data or model files** to collect, host, or maintain.
- **Explainable / transparent** — every rejection has a clear, inspectable
  reason (unlike a black-box CNN), which matters for auditing attendance.
- **Self-contained and offline** — no external services or dependencies.
- **Defense-in-depth** — no single point of failure; layers cover each other.

### Be honest about the one limitation (this earns marks)
> "The known limitation of any passive single-frame method is a **high-quality
> video replay** — a video of the real student blinking on a phone could satisfy
> the blink check. This is a recognised hard problem in the field. My layered
> design mitigates it (consensus + texture checks + the venue gate), and the
> clear future-work path is a **CNN-based PAD model** (e.g. MiniFASNet) as an
> additional layer, which I designed the system to slot into without changing
> the rest of the pipeline."

Examiners reward a candidate who **knows the limits and the roadmap** far more
than one who over-claims.

---

## Likely examiner questions & answers

- **"Can a photo bypass it?"** — No; demo it. The photo can't blink, so it fails
  the challenge and, after repeats, locks out.
- **"What if the photo is moved/shaken to fake a blink?"** — Blur frames are
  rejected by the sharpness gate, and two blinks are required.
- **"Can a stranger be matched to a student?"** — Consensus requires 2 of 3
  models to agree; a single-model false match is dropped. Demonstrated on a real
  phone screenshot that LBPH alone mis-matched.
- **"Can a video replay bypass it?"** — Honestly, yes — that is the documented
  limitation and future-work (a CNN PAD model layer).
- **"Why not deep learning?"** — Real-time CPU constraint, no training data,
  explainability, offline/self-contained; the design leaves a clean hook to add
  a model later.
- **"How did you validate it?"** — Calibrated the passive thresholds on 66 real
  faces with **zero false rejections**, and tested against real printed/phone
  presentation attacks.

---

## Implementation / tuning reference

**Files**
- **`python/spoof_check.py`** — THE anti-spoofing module. All hardcoded spoof
  checks live here: the passive screen check (`detect_spoof`, `liveness_report`)
  and the active blink liveness (`detect_blink`), with every threshold at the
  top of the file. This is the file to open/show when explaining the spoofing
  defence.
- `python/realtime_recognition.py` — face recognition; imports `spoof_check`
  and adds the 2-of-3 consensus (`recognize_consensus_image`).
- `recognize_face.php` — per-frame recognition endpoint (uses `--consensus`).
- `liveness_check.php` — blink-burst endpoint (calls `verify_liveness`).
- `resources/assets/javascript/face_logics/script.js` — capture flow + blink UI.

**Tuning knobs (top of `python/spoof_check.py`)**
- `ANTISPOOF_ENABLED` (True) — passive texture gate on/off.
- `MOIRE_HF_RATIO_MAX` (0.19) / `SPECULAR_FRAC_MAX` (0.18) / `SHARPNESS_MIN` (50)
  — passive thresholds (calibrated to real faces).
- `SPOOF_SCORE_THRESHOLD` (2) — weighted flags needed to call a frame a spoof.
- `BLINK_MIN_BLINKS` (default 2) — blinks required.
- `BLINK_MIN_OPEN_FRAMES` (4) — clear eyes-open frames required.
- `CLOSED_MIN_SHARPNESS` (30) — min sharpness for a real closed-eye frame.

**Tuning knob (top of `python/realtime_recognition.py`)**
- `CONSENSUS_MIN_AGREE` (2) — how many of the 3 models must agree on a match.

**Tuning knobs (top of `script.js`)**
- `LIVENESS_DURATION_MS` (4000) — length of the blink capture window.
- `MAX_LIVENESS_FAILS` (3) / `LIVENESS_LOCKOUT_MS` (12000) — brute-force lockout.

**Calibration procedure**
1. Every check logs a line to `python/face_recognition.log`
   (`LIVENESS ...`, `LIVENESS-BURST ...`, `CONSENSUS ...`).
2. Present a few real faces and a few phone/print attacks.
3. Compare the logged numbers and adjust the thresholds above so real faces sit
   inside the accepted range and attacks sit outside.

**Diagnostics**
- `python python/realtime_recognition.py --liveness-check <img>` — passive metrics
  for one image.
- `python python/realtime_recognition.py <img> --consensus` — consensus result.
- `python python/realtime_recognition.py --verify-liveness <frames_dir>` — blink
  result for a folder of `frame_*.jpg`.
