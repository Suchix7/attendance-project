# Attendance Management System: Technical Audit & Architecture Breakdown

This document provides a comprehensive technical audit, architectural analysis, and operational guide for the **Smart College Attendance & Alerts System**.

---

## 1. Project Overview

### Purpose
The **Smart College Attendance & Alerts System (SAS)** is an automated, web-based and computer-vision-driven academic utility. It automates attendance taking for university/college lectures, tracks attendance statistics, enforces course-specific thresholds, and proactively alerts parents or guardians regarding low attendance risk.

### Problem Solved
- **Inefficiency of Manual Roll Calls:** Reduces classroom time wasted on manual registers.
- **Proxy Attendance (Cheating):** Prevents student fraud by validating identities using facial recognition and geolocation distance limits (100m).
- **Exam Eligibility Disputes:** Dynamically calculates attendance rates relative to academic calendars, generating stateful warning emails to parents before a student is disqualified from exams.
- **Academic Term Isolation:** Solves the challenge of data cross-contamination between different academic terms (semesters) by partitioning calendars, student lists, and records by `semesterID`.

### Main Objectives
1. **Automated Facial Recognition:** Identify and log students in real-time or via image batch processing.
2. **Strict Geolocation Gating:** Validate that lectures are only launched and marked if the lecturer's location is within 100 meters of the venue.
3. **Automated & Manual Warnings:** Provide stateful, rate-limited email warnings for students falling below the minimum required threshold (default: 75%).
4. **Bilingual Academic Calendars:** Map Gregorian (AD) dates to Nepali Bikram Sambat (BS) formats for localized class scheduling and attendance logging.

---

## 2. Technology Stack & Library Analysis

The application leverages a hybrid **PHP + Python** stack, using standard databases and native browser APIs.

### Frontend
- **HTML5 & Vanilla CSS:** Modular structure styled using modern design systems (`styles.css`, `admin_styles.css`, `landing_styles.css`).
- **RemixIcons:** Embedded iconography for clean, modern dashboard graphics.
- **Vanilla JavaScript & AJAX:** Manages DOM manipulation, video canvas streaming, and non-blocking XMLHttpRequests/Fetch queries for location tracking and face recognition.
- **XLSX.js & FileSaver.js (minified):** Enables browser-side conversion of HTML matrices into binary Excel files (`.xlsx`) for downloading reports directly.

### Backend
- **PHP 8.x:** Core application driver managing routing, session authentication, CRUD dashboard operations, database transactions, and standard HTTP requests.
- **Python 3.10+:** AI processing and micro-utility execution (e.g. face detection, multi-model recognizer training, and email SMTP wrapper).

### Database
- **MySQL / MariaDB:** Relational database managed via **PHP Data Objects (PDO)**. Prepared statements are enforced globally to mitigate SQL Injection vectors.

### Imported Libraries & Rationale

#### Python Libraries
1. **`opencv-python` (`cv2`):** Handles video streaming, image decoding, face cropping, scaling, and training classical computer vision models.
2. **`numpy`:** Used for manipulating multi-dimensional image arrays, labels, and matrices during training.
3. **`dlib` & `face_recognition` (Optional / Fallback):** Reserved for deep-learning-based feature encoding if highly sensitive recognition is required.
4. **`smtplib` & `email`:** Handles SMTP connections, secure TLS sessions, and MIME text email preparation.
5. **`argparse`:** Parses command-line inputs passed from PHP (e.g., `--train`, `--algorithm`).
6. **`pickle`:** Serializes and deserializes the student ID-to-integer label lookup table (`labels.pkl`).

#### PHP Libraries / Submodules
1. **`nepali_calendar.php`:** Custom class executing calendar conversions between Bikram Sambat (BS) and Gregorian (AD) dates, including leap-year calculation and day counts per month.

---

## 3. Folder Structure Overview

```
attendance-project2/
├── .htaccess                   # Handles routing and URL rewrites (e.g., hiding .php extensions)
├── index.php                   # Entry point routing users to the landing page or dashboard
├── requirements.txt            # Python dependencies (opencv-python, numpy, etc.)
├── detect_face.php             # PHP bridge for single face registration checks
├── recognize_face.php          # PHP bridge that executes Python recognition during live logging
├── update_attendance.php       # Updates tblattendance table and triggers stateful email checks
│
├── database/                   # Database Migrations & Schemas
│   ├── attendance-db.sql       # Base MySQL relational schema
│   └── database_connection.php # PDO connection singleton and automated schema update manager
│
├── models/                     # Trained Recognition Files
│   ├── lbph_model.yml          # Trained Local Binary Patterns Histograms recognizer
│   ├── eigen_model.yml         # Trained Eigenfaces recognizer
│   ├── fisher_model.yml        # Trained Fisherfaces recognizer (requires >= 2 student labels)
│   └── labels.pkl              # Pickled student ID lookup table
│
├── python/                     # Core Python Engine
│   ├── detect_face.py          # Detects face bounding boxes on uploaded frames
│   ├── realtime_recognition.py # Main prediction script executing single/multi-algorithm scoring
│   ├── train_model.py          # Script triggering full model retraining
│   ├── alert_emailer.py        # Mail dispatcher reading JSON and executing SMTP or logging to file
│   └── email_config.json       # SMTP server and login parameters
│
├── resources/                  # Frontend Assets & Pages
│   ├── assets/
│   │   ├── css/                # Custom CSS files (styles.css, admin_styles.css)
│   │   └── javascript/         # JS files (nepali_calendar.js, admin_functions.js)
│   ├── lib/
│   │   ├── php_functions.php   # General database queries (getCourseNames, fetch, etc.)
│   │   ├── nepali_calendar.php # Gregorian-to-Nepali conversion logic
│   │   └── analytics_logic.php # Attendance risk algorithms and Grace Period calculations
│   └── pages/
│       ├── login.php           # Role-based secure portal entry
│       ├── landing.php         # Public college promotional page
│       ├── administrator/      # Administrative Dashboards & Reports
│       ├── lecture/            # Lecturer Portal & Geolocation Checks
│       └── student/            # Student Analytics & Announcement Boards
│
├── students/                   # Validated Face Folders
│   └── [registrationNumber]/   # Folders containing cropped registration faces and info.json
├── temp/                       # Temporary system storage
│   └── sent_emails.log         # Fallback log for outgoing emails if SMTP is offline
└── uploads/                    # Stores uploaded files and temporary image buffers
```

---

## 4. Relational Database Architecture

```
                                +-------------------+
                                |    tblsemester    |
                                +-------------------+
                                | Id (PK)           |
                                | name              |
                                | facultyCode (FK)  |
                                | startDate         |
                                | endDate           |
                                | isActive          |
                                +-------------------+
                                          |
                        +-----------------+-----------------+
                        |                                   |
              +-------------------+               +--------------------+
              |    tblstudents    |               | tblfacultycalendar |
              +-------------------+               +--------------------+
              | Id (PK)           |               | id (PK)            |
              | regNo (UK)        |               | facultyCode (FK)   |
              | firstName         |               | semesterID (FK)    |
              | lastName          |               | classDate          |
              | faculty (FK)      |               +--------------------+
              | courseCode (FK)   |
              | email             |
              | semesterID (FK)   |
              +-------------------+
                        |
              +-------------------+
              |   tblattendance   |
              +-------------------+
              | attendanceID (PK) |
              | regNo (FK)        |
              | status            |
              | dateMarked        |
              | course (FK)       |
              | unit (FK)         |
              +-------------------+
```

### Key Database Tables
- **`tblstudents`**: Student registration details scoped by `semesterID`, linked to courses and faculties.
- **`tblattendance`**: Individual student presence logging per course, unit, and date.
- **`tblfacultycalendar`**: Academic calendar scheduled class dates, partition-scoped by both `facultyCode` and `semesterID`.
- **`tblsemester`**: Defines individual semesters, date ranges, and active status flag.
- **`tblcourse` & `tblunit`**: Course syllabus and modular subdivisions.
- **`tblvenue`**: Lecture halls, their physical capacity, and GPS coordinates (latitude/longitude).
- **`tblsettings`**: Stores configuration variables like `face_confidence_threshold` and `attendance_threshold`.
- **`tblalertstate`**: Tracks communication state to coordinate rate limits and prevent duplicate warning dispatches.

---

## 5. Core System Mechanisms & Pipelines

### A. Academic Semester Isolation
To prevent data contamination (e.g., calculating present percentages using dates from a previous term):
- **Resolving the Term:** The backend calls `getActiveSemester($pdo, $facultyCode)` which queries `tblsemester` where `isActive = 1`.
- **Query Scoping:** Every attendance query, calendar rendering, and analytics report filters by the active `semesterID`.
- **Calendar Pivoting:** In `download-record.php` and `attendance-report.php`, class columns are generated dynamically using `classDate` from `tblfacultycalendar` matching the selected semester. If no dates are configured, the page falls back to distinct dates in `tblattendance` for that course/unit.

### B. Facial Recognition Pipeline
1. **Grayscale Conversion & Preprocessing:** 
   - Captured frames are converted to grayscale.
   - **CLAHE (Contrast Limited Adaptive Histogram Equalization):** Normalizes illumination differences across face frames.
   - **Gaussian Blur:** Eliminates high-frequency noise that can distort local binary patterns.
2. **Training Augmentation:**
   - To make the LBPH recognizer resilient to lighting, tilt, and positioning, `train_models` performs **augmentation** by generating translated (+/- 4px shifts), scaled (+/- 5%), and rotated (+/- 5 degrees) variants of each registered face image.
3. **Multi-Algorithm Scoring:**
   - **LBPH (Local Binary Patterns Histograms):** Best for texture analysis and small lighting shifts.
   - **Eigenfaces:** Principle Component Analysis (PCA) tracking broad geometric changes.
   - **Fisherfaces:** Linear Discriminant Analysis (LDA) maximizing between-class variance.
   - Recognition scores (distances) are normalized to scale percentages ($100\%$ to $60\%$) and verified against the `face_confidence_threshold` configured in `tblsettings` (default: 65%).

### C. Geolocation Gating
- When launching attendance, the browser retrieves the lecturer's GPS coordinates (`navigator.geolocation`).
- The coordinates are matched against the venue's stored coordinates (`tblvenue`).
- The **Haversine Formula** is calculated on the server to compute the distance in meters. If the distance exceeds 100 meters, face recognition is disabled, preventing remote attendance fraud.

### D. Stateful Email Alert System
- **Auto Mode:**
  - Evaluates student attendance records on an absence event.
  - Enforces a **3-day cooldown** for standard absent warnings using `tblalertstate.lastAbsentAlertSent`.
  - Enforces a **Grace Period threshold** (`isSemesterInGracePeriod`): suppress alerts during the first 30 days or first 5 class sessions.
- **Manual Mode:**
  - Administrative control panel (`email-alerts.php`) displays student lists.
  - Clicking "Review & Send" triggers an AJAX query to fetch detailed attendance statistics and a list of absent dates.
  - The administrator selects which course units to mention in the email using check-boxes.
  - The Javascript builder compiles a structured markdown preview of the warning and executes a Python SMTP sub-process (`alert_emailer.py`).
- **SMTP Fallback Log:**
  - If SMTP server configuration is missing, `alert_emailer.py` silently captures the email and appends it to `temp/sent_emails.log` to prevent system failures during offline testing.

---

## 6. Verification and Maintenance Guide

### Model Training
When registering new students or updating student images, rebuild the classifier using the command line:
```powershell
python python/realtime_recognition.py --train
```
This script will scan the `/students` directories, extract valid faces, perform augmentation, and overwrite the model weights in `models/`.

### Logging & Diagnostics
Check the following logs for diagnostics:
- **`face_recognition.log`:** Captures Python computer vision errors and training progress.
- **`temp/sent_emails.log`:** Stores all emails dispatched when SMTP configuration is omitted.
- **`database/database_connection.php` logs:** Any errors during DB schema migration will be written here.
