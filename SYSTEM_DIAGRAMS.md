# SAS Attendance System — Full Architecture & Algorithm Reference

---

## 1. Backend Database Schema

```sql
-- Core Tables (with runtime migrations applied via database_connection.php)

tbladmin          : Id, firstName, lastName, emailAddress, password(bcrypt)
tblfaculty        : Id, facultyName, facultyCode, dateRegistered
tbllecture        : Id, firstName, lastName, emailAddress, password(bcrypt), phoneNo, facultyCode
tblcourse         : Id, name, facultyID→tblfaculty, courseCode, dateCreated
tblunit           : Id, name, unitCode, courseID→tblcourse, dateCreated
tblvenue          : Id, className, facultyCode, currentStatus, capacity, classification, dateCreated
tblstudents       : Id, firstName, lastName, registrationNumber, email, faculty→tblfaculty,
                    courseCode→tblcourse, semesterID→tblsemester, studentImage, dateRegistered
tblattendance     : attendanceID, studentRegistrationNumber→tblstudents, course→tblcourse,
                    unit→tblunit, attendanceStatus, dateMarked, confidence(float)
tblsemester       : Id, facultyCode→tblfaculty, name, startDate, endDate, isActive, dateCreated
tblfacultycalendar: id, facultyCode→tblfaculty, semesterID→tblsemester, classDate, description
                    UNIQUE(facultyCode, semesterID, classDate)
tblalertstate     : id, studentRegistrationNumber, courseCode, unitCode,
                    lastAbsentAlertSent, consecutivePresentCount,
                    lastMomentumAlertSent, lastThresholdAlertSent
                    UNIQUE(studentRegistrationNumber, courseCode, unitCode)
tblsettings       : setting_key(PK), setting_value
                    Defaults: face_confidence_threshold=65, email_alerts_mode=auto,
                              attendance_threshold=75
tblnotices        : id, title, body, postedDate, postedBy
```

### Semester Isolation Rule
> Every query on `tblfacultycalendar` and `tblstudents` **must** include `semesterID`.
> Cross-semester queries are architecturally forbidden to prevent data pollution.

---

## 2. Component Diagram

```mermaid
graph TB
    subgraph Browser["Browser / Client"]
        LP[Landing Page]
        LG[Login Page]
        subgraph StudentPortal["Student Portal"]
            SH[Home Dashboard]
            SA[Attendance View]
            SN[Notices Board]
        end
        subgraph LecturerPortal["Lecturer Portal"]
            LH[Lecturer Home]
            LFR[Face Recognition UI]
            LVA[View Attendance]
            LVS[View Students]
            LDR[Download Records]
        end
        subgraph AdminPortal["Admin Portal"]
            AH[Admin Home]
            AFC[Faculty Calendar]
            AAR[Attendance Report]
            AEA[Email Alerts]
            AST[Settings]
        end
    end

    subgraph PHP["PHP Backend"]
        AUTH[login.php — Auth & Session]
        UA[update_attendance.php]
        RF[recognize_face.php]
        DF[detect_face.php]
        MF[manageFolder.php]
        ST[studentTable.php]
        AS[alert_service.php]
        AL[analytics_logic.php]
        NC[nepali_calendar.php]
        PF[php_functions.php]
    end

    subgraph Python["Python Engine"]
        RR[realtime_recognition.py]
        DD[detect_face.py]
        TM[train_model.py]
        AE[alert_emailer.py]
    end

    subgraph DB["MySQL Database"]
        TABLES[(All tbl* Tables)]
    end

    subgraph Models["Model Files"]
        LBPH[lbph_model.yml]
        EIGEN[eigen_model.yml]
        FISHER[fisher_model.yml]
        LABELS[labels.pkl]
        CASCADE[haarcascade_frontalface_default.xml]
    end

    Browser -->|HTTP/AJAX| PHP
    PHP -->|PDO| DB
    PHP -->|proc_open stdin/stdout| Python
    Python --> Models
```

---

## 3. Class Diagram

```mermaid
classDiagram
    class Student {
        +int Id
        +string registrationNumber
        +string firstName
        +string lastName
        +string email
        +string faculty
        +string courseCode
        +int semesterID
        +string studentImage
    }

    class Attendance {
        +int attendanceID
        +string studentRegistrationNumber
        +string course
        +string unit
        +string attendanceStatus
        +date dateMarked
        +float confidence
    }

    class Semester {
        +int Id
        +string facultyCode
        +string name
        +date startDate
        +date endDate
        +bool isActive
        +getActiveSemester()
        +getSemestersByFaculty()
    }

    class FacultyCalendar {
        +int id
        +string facultyCode
        +int semesterID
        +date classDate
        +is_scheduled_class_day()
    }

    class AlertState {
        +string studentRegistrationNumber
        +string courseCode
        +string unitCode
        +datetime lastAbsentAlertSent
        +int consecutivePresentCount
        +datetime lastThresholdAlertSent
        +evaluate_and_send_alerts()
    }

    class AlertService {
        +is_scheduled_class_day(pdo, course, date)
        +evaluate_and_send_alerts(pdo, studentID, course, unit, status)
        +trigger_alert_emailer(to, subject, body)
    }

    class AnalyticsLogic {
        +calculateAttendanceRisk(regNo, courseCode, semesterId)
        +isSemesterInGracePeriod(pdo, facultyCode, semesterId)
        +getLatestNotices(limit)
    }

    class FaceRecognizer {
        +train_models()
        +recognize_single_image(image_path, algo)
        +start_recognition()
        -lbph_model_path
        -eigen_model_path
        -fisher_model_path
        -labels_path
    }

    class AlertEmailer {
        +main()
        +log_email_locally(to, subject, body, error)
        -smtp_host
        -smtp_port
        -smtp_username
        -smtp_password
    }

    Student "1" --> "many" Attendance : has
    Student "many" --> "1" Semester : scoped by
    FacultyCalendar "many" --> "1" Semester : scoped by
    AlertState "1" --> "1" Student : tracks
    AlertService ..> AlertState : reads/writes
    AlertService ..> AlertEmailer : spawns
    AnalyticsLogic ..> FacultyCalendar : queries
    AnalyticsLogic ..> Attendance : queries
```

---

## 4. Sequence Diagram — Facial Recognition Attendance

```mermaid
sequenceDiagram
    participant B as Browser (JS)
    participant PHP as handle_attendance.php
    participant PY as realtime_recognition.py
    participant DB as MySQL

    B->>B: Capture webcam frame (canvas)
    B->>B: Verify GPS via Haversine (≤100m from venue)
    B->>PHP: POST frame image + course + unit + venue
    PHP->>DB: is_scheduled_class_day(course, today)
    DB-->>PHP: true / false
    alt Not a scheduled class day
        PHP-->>B: {error: "Not a class day"}
    else Valid class day
        PHP->>PHP: Save frame to temp file
        PHP->>PY: proc_open --algorithm lbph image_path
        PY->>PY: Load Haar Cascade → detect face
        PY->>PY: CLAHE + GaussianBlur preprocessing
        PY->>PY: Multi-sample generation (flips, rotations, shifts)
        PY->>PY: LBPH.predict() → best confidence distance
        PY->>PY: Eigenfaces.predict() → distance
        PY->>PY: Fisherfaces.predict() → distance
        PY-->>PHP: JSON {student_id, confidence%, algorithm}
        PHP->>DB: INSERT tblattendance (Present/Unknown)
        PHP->>DB: evaluate_and_send_alerts(studentID, course, unit, status)
        PHP-->>B: {success, studentID, confidence}
    end
```

---

## 5. Sequence Diagram — Manual Email Alert Dispatch

```mermaid
sequenceDiagram
    participant A as Admin Browser
    participant PHP as email-alerts.php
    participant AL as analytics_logic.php
    participant PY as alert_emailer.py
    participant SMTP as SMTP Server

    A->>PHP: GET email-alerts.php?faculty=X&semester=Y
    PHP->>DB: SELECT students WHERE faculty=X AND semesterID=Y
    PHP->>AL: calculateAttendanceRisk(regNo, null, semesterID)
    AL-->>PHP: {percentage, level, color}
    PHP-->>A: Render student table with risk badges

    A->>PHP: POST action=get_student_details (student_id, semester_id)
    PHP->>DB: SELECT calendar dates (scoped to semesterID)
    PHP->>DB: SELECT attendance records for student
    PHP-->>A: JSON {classes[], absentDates[], overallPct}

    A->>A: Select classes to warn, edit email body
    A->>PHP: POST action=send_detailed_alert (student_id, subject, body)
    PHP->>PY: proc_open alert_emailer.py (JSON via stdin)
    alt SMTP configured
        PY->>SMTP: SMTP_SSL / STARTTLS connect + sendmail
        SMTP-->>PY: OK
        PY-->>PHP: {success:true}
    else No SMTP config
        PY->>PY: log_email_locally → temp/sent_emails.log
        PY-->>PHP: {success:true, logged_locally:true}
    end
    PHP->>DB: UPDATE tblalertstate.lastThresholdAlertSent = NOW()
    PHP-->>A: {success:true, message:"Warning email sent"}
```

---

## 6. State Diagram — Student Attendance Status

```mermaid
stateDiagram-v2
    [*] --> GracePeriod : Semester starts (day 0)

    GracePeriod : Grace Period\n(first 30 days OR < 5 classes)
    GracePeriod --> Safe : Grace period ends\npct >= 85%
    GracePeriod --> Warning : Grace period ends\n75% <= pct < 85%
    GracePeriod --> Critical : Grace period ends\npct < 75%

    Safe : Safe\n(>= 85% attendance)
    Safe --> Warning : pct drops to [75–85%)
    Safe --> Critical : pct drops below 75%

    Warning : Warning\n([75–85%) attendance)
    Warning --> Safe : pct rises >= 85%
    Warning --> Critical : pct drops below 75%

    Critical : Critical\n(< 75% attendance)
    Critical --> Warning : pct rises to [75–85%)
    Critical --> Safe : pct rises >= 85%
    Critical --> [*] : Semester ends / Exam barred
```

---

## 7. State Diagram — Alert Email State Machine

```mermaid
stateDiagram-v2
    [*] --> Idle : New student enrolled

    Idle --> AbsentCooldown : Student marked Absent\n(auto mode)
    AbsentCooldown : Absent Alert Sent\n3-day cooldown active
    AbsentCooldown --> Idle : 3 days elapsed

    Idle --> ThresholdAlert : pct < threshold\nstudent Absent\nno grace period
    ThresholdAlert : Threshold Warning Sent\nlastThresholdAlertSent = NOW()
    ThresholdAlert --> Idle : Next absence event\nafter cooldown

    Idle --> MomentumAlert : consecutivePresentCount\nreaches multiple of 3
    MomentumAlert : Positive Momentum\nEmail Sent
    MomentumAlert --> Idle : Reset on next Absent

    Idle --> Suppressed : emailMode = disabled\nOR grace period active
    Suppressed --> Idle : Settings changed / grace ends
```

---

## 8. Activity Diagram — Full Attendance Session

```mermaid
flowchart TD
    A([Lecturer opens portal]) --> B[Select Course / Unit / Venue]
    B --> C{GPS check:\ndistance to venue ≤ 100m?}
    C -- No --> D[Show: Too far from venue\nFace recognition disabled]
    C -- Yes --> E[Enable Start Session button]
    E --> F[Load student list via manageFolder.php]
    F --> G[Start webcam stream]
    G --> H[Capture frame every N seconds]
    H --> I{Is today a\nscheduled class day?}
    I -- No --> J[Block marking\nReturn error to UI]
    I -- Yes --> K[Send frame to handle_attendance.php]
    K --> L[Python: Haar Cascade face detection]
    L --> M{Face detected?}
    M -- No --> N[Skip frame\nRetry next cycle]
    M -- Yes --> O[CLAHE equalization\nGaussian blur]
    O --> P[Generate multi-samples\nflips, rotations, shifts]
    P --> Q[LBPH predict → distance d1]
    P --> R[Eigenfaces predict → distance d2]
    P --> S[Fisherfaces predict → distance d3]
    Q & R & S --> T[Select best confidence\nlowest distance wins]
    T --> U{Confidence\nwithin threshold?}
    U -- No --> V[Mark: Unknown\nFlag in UI]
    U -- Yes --> W[Identify student_id from labels.pkl]
    W --> X[INSERT tblattendance status=Present]
    X --> Y[evaluate_and_send_alerts]
    Y --> Z{emailMode = auto?}
    Z -- No --> AA[Skip email]
    Z -- Yes --> AB{Grace period\nactive?}
    AB -- Yes --> AC[Suppress alert]
    AB -- No --> AD{pct < threshold\nAND absent?}
    AD -- Yes --> AE{3-day cooldown\npassed?}
    AE -- No --> AF[Skip alert]
    AE -- Yes --> AG[Dispatch threshold\nwarning email via Python]
    AG --> AH[Update tblalertstate]
    AH --> AA
    AA --> AI([Session ends / Lecturer stops])
```

---

## 9. Activity Diagram — Auto Alert Evaluation

```mermaid
flowchart TD
    S([update_attendance.php calls\nevaluate_and_send_alerts]) --> A[Read emailMode from tblsettings]
    A --> B{emailMode = disabled?}
    B -- Yes --> END([Return — no action])
    B -- No --> C[Fetch student: name, email, semesterID]
    C --> D{Email found?}
    D -- No --> END
    D -- Yes --> E[Resolve semesterID → tblsemester]
    E --> F[Fetch or CREATE tblalertstate row]
    F --> G{status = Absent?}

    G -- Yes --> H[Reset consecutivePresentCount = 0]
    H --> I[Check isSemesterInGracePeriod]
    I --> J{Grace active?}
    J -- Yes --> END
    J -- No --> K{lastAbsentAlertSent\nwithin 3 days?}
    K -- Yes --> L[Skip absent alert]
    K -- No --> M[Compose absent email body]
    M --> N[trigger_alert_emailer via Python]
    N --> O[UPDATE lastAbsentAlertSent]

    G -- No (Present) --> P[Increment consecutivePresentCount + 1]
    P --> Q{count % 3 == 0?}
    Q -- No --> END
    Q -- Yes --> R[Compose momentum\ncongratulation email]
    R --> S2[trigger_alert_emailer via Python]
    S2 --> T[UPDATE lastMomentumAlertSent]
    T --> END
    O --> END
    L --> END
```

---

## 10. Algorithm: Haar Cascade Face Detection

### Purpose
Haar Cascade is the **entry gate** of the recognition pipeline. It locates the bounding box of the face region within a frame before any recognition algorithm runs.

### How It Works

```
Input Frame (BGR)
       │
       ▼
Convert to Grayscale
       │
       ▼
Sliding Window scan across image at multiple scales
Each window tests against 6,000+ Haar-like features:

  Feature Types:
  ┌──┬──┐   ┌──────┐   ┌───┬───┐
  │██│  │   │      │   │███│   │
  │██│  │   │██████│   │███│   │
  └──┴──┘   └──────┘   └───┴───┘
  Edge       Line       Four-rect

       │
       ▼
Cascade of boosted classifiers (AdaBoost stages):
  Stage 1 — fast reject (2 features) → reject 50% of windows
  Stage 2 — stricter (10 features)   → reject more
  ...
  Stage N — all features pass         → face confirmed
       │
       ▼
Non-Maximum Suppression (merge overlapping boxes)
       │
       ▼
Output: [(x, y, w, h), ...] bounding boxes
```

### Parameters Used in This Project
```python
face_cascade.detectMultiScale(
    gray,
    scaleFactor  = 1.1,   # scale image by 10% each pass
    minNeighbors = 5,      # require 5 overlapping detections
    minSize      = (40, 40),
    maxSize      = (600, 600)
)
```

### Integration Point
- `python/detect_face.py` — used during student registration to verify face exists
- `python/realtime_recognition.py` — used before LBPH/Eigen/Fisher prediction

---

## 11. Algorithm: LBPH — Local Binary Patterns Histograms

### Purpose
LBPH is the **primary recognizer**. It encodes local texture patterns around each pixel into a compact histogram, making it robust to illumination variation.

### How It Works

```
Preprocessed Face ROI (96×96 grayscale)
       │
       ▼
For each pixel p at (x, y):
  Compare p with its 8 circular neighbours at radius R=1
  Threshold: neighbour >= p → 1, else → 0
  Concatenate 8 bits clockwise → 8-bit LBP code (0–255)

  Example:
       6 5 4              1 0 0
       7 p 3    →  LBP =  0   1  → binary = 10011110 = 158
       0 1 2              1 1 1

       │
       ▼
Divide face into grid cells (grid_x=8, grid_y=8 → 64 cells)
For each cell: compute histogram of LBP codes (256 bins)
       │
       ▼
Concatenate all 64 histograms → feature vector (64 × 256 = 16,384 dims)
       │
       ▼
Recognition: Chi-Square distance between probe histogram and each trained histogram
  d(H1,H2) = Σ [(H1_i - H2_i)² / (H1_i + H2_i)]
       │
       ▼
Nearest neighbour: student with lowest distance wins
Threshold: distance < 90 → recognized, else → Unknown
```

### Parameters Used
```python
cv2.face.LBPHFaceRecognizer_create(
    radius    = 1,
    neighbors = 8,
    grid_x    = 8,
    grid_y    = 8,
    threshold = 150
)
```

### Confidence Mapping
```
Raw Distance → Display Confidence %
[40  → 90]   maps to [100% → 60%]
> 90         → 0% (Unknown)
```

### Why LBPH for This Project
- Tolerates illumination changes (classroom lighting varies)
- Incremental: new students can be added without full retrain
- Low memory footprint vs deep learning models

---

## 12. Algorithm: Eigenfaces (PCA-based Recognition)

### Purpose
Eigenfaces is the **secondary recognizer**. It captures the most significant global variance patterns (eigenfaces) and projects face images into a lower-dimensional eigenspace for comparison.

### How It Works

```
Training Set: N face images, each flattened to vector of size D (96×96=9216)
       │
       ▼
Compute mean face μ = (1/N) Σ face_i
       │
       ▼
Subtract mean: A_i = face_i − μ   (zero-centred matrix)
       │
       ▼
Covariance matrix: C = A^T × A   (D×D, solved via trick for efficiency)
       │
       ▼
Compute eigenvectors (principal components = eigenfaces)
Keep top K components explaining most variance (K << D)
       │
       ▼
Project each training face into K-dimensional eigenspace:
  w_i = [eigenface_1·A_i, eigenface_2·A_i, ..., eigenface_K·A_i]
       │
       ▼
Recognition: project probe face → w_probe
Nearest neighbour: find training face with min distance to w_probe
Threshold: distance < 3500 → recognized, else → Unknown
```

### Confidence Mapping
```
Raw Distance → Display Confidence %
[1000 → 3500] maps to [100% → 60%]
> 3500        → 0% (Unknown)
```

### Role in Multi-Algorithm Scoring
All three algorithms (LBPH, Eigenfaces, Fisherfaces) run on the same preprocessed samples. The algorithm with the **lowest confidence distance** wins for that frame — implemented via `best_confidence` selection loop in `recognize_single_image()`.

---

## 13. Location Gating — Haversine Distance

### Purpose
Prevent lecturers from marking attendance remotely. The system computes the **Haversine great-circle distance** between the lecturer's GPS position and the registered venue coordinates. If the distance exceeds 100 metres, face recognition is locked.

### Formula

```
Given:
  Lecturer: (lat1, lon1)
  Venue:    (lat2, lon2)

Δlat = lat2 − lat1  (in radians)
Δlon = lon2 − lon1  (in radians)

a = sin²(Δlat/2) + cos(lat1) × cos(lat2) × sin²(Δlon/2)

c = 2 × atan2(√a, √(1−a))

distance = R × c     where R = 6,371,000 metres (Earth radius)
```

### Implementation in JavaScript
```javascript
function haversineDistance(lat1, lon1, lat2, lon2) {
    const R = 6371000; // metres
    const toRad = deg => deg * Math.PI / 180;
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    const a = Math.sin(dLat/2)**2
            + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2))
            * Math.sin(dLon/2)**2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

// Gate check
if (haversineDistance(userLat, userLon, venueLat, venueLon) > 100) {
    showError("You must be within 100m of the venue to start attendance.");
    disableFaceRecognition();
}
```

> **Note:** Euclidean distance is NOT used for location. Euclidean distance (√(Δx²+Δy²)) only applies to flat 2D planes — Earth's curvature makes it inaccurate for geographic coordinates. Haversine accounts for the spherical Earth.

---

## 14. Alert System — Complete Architecture

### Three Alert Types

| Type | Trigger | Cooldown | Suppressed When |
|------|---------|----------|-----------------|
| Absent Alert | Student marked Absent | 3 days | Grace period OR manual mode |
| Threshold Warning | pct < 75% AND Absent | 3 days | Grace period OR manual mode |
| Momentum Praise | consecutivePresent % 3 == 0 | None | Disabled mode |

### Grace Period Logic
```
isSemesterInGracePeriod(pdo, facultyCode, semesterId):
  1. Fetch tblsemester.startDate WHERE Id = semesterId
  2. If (today − startDate) <= 30 days → IS grace period
  3. Else count calendar days held so far (semesterID scoped)
  4. If class days held < 5 → IS grace period
  5. Else → NOT grace period
```

### Email Dispatch Pipeline
```
PHP evaluate_and_send_alerts()
        │
        ▼
trigger_alert_emailer($to, $subject, $body)
        │
        ▼
proc_open("python alert_emailer.py")
  stdin  ← JSON: {to, subject, body}
        │
        ▼
alert_emailer.py reads email_config.json
        │
     ┌──┴──────────────────┐
     │ SMTP configured?     │
  Yes│                      │No
     ▼                      ▼
SMTP_SSL / STARTTLS    log_email_locally()
connect + sendmail     → temp/sent_emails.log
     │                      │
     └──────────┬────────────┘
                ▼
        stdout ← JSON: {success, message}
                │
                ▼
        PHP reads return code
        Updates tblalertstate timestamps
```

### Manual Admin Dispatch (email-alerts.php)
```
1. Admin selects Faculty + Semester filter
2. PHP renders student table with risk levels (calculateAttendanceRisk)
3. Admin clicks "Review & Send" → AJAX POST action=get_student_details
4. PHP returns: class breakdown, absent dates (scoped to semesterID)
5. JS renders class checkboxes, builds email preview using NepaliCalendar.formatNepaliDate()
6. Admin sends → AJAX POST action=send_detailed_alert
7. PHP calls trigger_alert_emailer() → Python SMTP
8. PHP logs to tblalertstate.lastThresholdAlertSent
```

---

## 15. Training Pipeline — Model Rebuild

```
python realtime_recognition.py --train
        │
        ▼
Scan /students/[registrationNumber]/ for face_*.jpg
        │
        ▼
For each image:
  Haar Cascade → detect face bounding box
  Crop face → resize to 96×96
  CLAHE equalization
  Gaussian blur (3×3)
  Data augmentation:
    + horizontal flip
    + rotations: −5°, +5°
        │
        ▼
Train 3 recognizers on augmented dataset:
  LBPH → models/lbph_model.yml
  Eigenfaces → models/eigen_model.yml
  Fisherfaces → models/fisher_model.yml  (requires >= 2 students)
        │
        ▼
Pickle student_id→label map → models/labels.pkl
```

---

*Generated: 2026-07-20 | SAS Attendance System v2 | attendance-project2*
