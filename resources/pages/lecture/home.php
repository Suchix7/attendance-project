<?php
require_once __DIR__ . '/alert_service.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attendanceData = json_decode(file_get_contents("php://input"), true);
    if ($attendanceData) {
        try {
            // --- Calendar validation: check the first record's course ---
            $firstRecord = reset($attendanceData);
            $checkCourse  = $firstRecord['course'] ?? '';
            $checkDate    = date('Y-m-d');
            if ($checkCourse && !is_scheduled_class_day($pdo, $checkCourse, $checkDate)) {
                echo json_encode([
                    'success' => false,
                    'message' => "Today ($checkDate) is not a scheduled class day for this faculty. Attendance has not been recorded.",
                    'blocked_reason' => 'unscheduled_day'
                ]);
                exit;
            }

            $sql = "INSERT INTO tblattendance (studentRegistrationNumber, course, unit, attendanceStatus, dateMarked)  
                VALUES (:studentID, :course, :unit, :attendanceStatus, :date)";

            $stmt = $pdo->prepare($sql);

            foreach ($attendanceData as $data) {
                $studentID = $data['studentID'];
                $attendanceStatus = $data['attendanceStatus'];
                $course = $data['course'];
                $unit = $data['unit'];
                $date = date("Y-m-d");

                // Bind parameters and execute for each attendance record
                $stmt->execute([
                    ':studentID' => $studentID,
                    ':course' => $course,
                    ':unit' => $unit,
                    ':attendanceStatus' => $attendanceStatus,
                    ':date' => $date
                ]);
            }

            $_SESSION['message'] = "Attendance recorded successfully for all entries.";
        } catch (PDOException $e) {
            $_SESSION['message'] = "Error inserting attendance data: " . $e->getMessage();
        }
    } else {
        $_SESSION['message'] = "No attendance data received.";
    }
}

// Function to get venue coordinates
function getVenueCoordinates($venue)
{
    global $pdo;
    try {
        $sql = "SELECT latitude, longitude FROM tblvenue WHERE className = :venue";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['venue' => $venue]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="resources/images/logo/face logo.png" rel="icon">
    <title>Lecture Dashboard</title>
    <link rel="stylesheet" href="resources/assets/css/styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.css" rel="stylesheet">
    <style>
        .video-container {
            position: relative;
            width: 640px;
            height: 480px;
            margin: 20px auto;
            border: 2px solid #ccc;
            border-radius: 8px;
            overflow: hidden;
        }

        #video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        #overlay {
            position: absolute;
            top: 0;
            left: 0;
            pointer-events: none;
        }

        #recognitionStatus {
            margin-top: 10px;
            padding: 10px;
            border-radius: 4px;
            text-align: center;
        }

        .success {
            color: #4CAF50;
            background: #E8F5E9;
            padding: 10px;
            border-radius: 4px;
            margin: 5px 0;
        }

        .info {
            color: #2196F3;
            background: #E3F2FD;
            padding: 10px;
            border-radius: 4px;
            margin: 5px 0;
        }

        .error {
            color: #f44336;
            background: #FFEBEE;
            padding: 10px;
            border-radius: 4px;
            margin: 5px 0;
        }

        .attendance-button {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 20px 0;
        }

        #messageDiv {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 5px;
            z-index: 9999;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            font-weight: 500;
            min-width: 200px;
            text-align: center;
        }

        .attendance-status {
            font-weight: bold;
        }

        .delete {
            cursor: pointer;
            color: #dc3545;
        }

        .delete:hover {
            color: #c82333;
        }

        /* Add animation for messages */
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        #messageDiv {
            animation: slideIn 0.3s ease-out;
        }
    </style>
</head>


<body>

    <?php include 'includes/topbar.php'; ?>
    <section class="main">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main--content">
            <div id="messageDiv" class="messageDiv" style="display:none;"></div>
            
            <div class="cards" style="margin-bottom: 24px; display: flex; gap: 20px;">
                <div class="card card-1" style="flex: 1; padding: 15px; text-align: center;">
                    <strong>Venue Coordinates</strong>
                    <p id="venue-coordinates" style="color: var(--text-muted); margin-top: 5px;">
                        <?php
                        if (isset($_GET['venue']) && !empty($_GET['venue'])) {
                            $coords = getVenueCoordinates($_GET['venue']);
                            if ($coords) {
                                echo "Latitude: {$coords['latitude']}, Longitude: {$coords['longitude']}";
                            } else {
                                echo "Coordinates not available";
                            }
                        } else {
                            echo "Please select a venue";
                        }
                        ?>
                    </p>
                </div>
                <div class="card card-1" style="flex: 1; padding: 15px; text-align: center;">
                    <strong>Your Location</strong>
                    <p id="user-coordinates" style="color: var(--text-muted); margin-top: 5px;">Fetching your location...</p>
                </div>
                <div class="card card-1" style="flex: 1; padding: 15px; text-align: center;">
                    <strong>Distance to Venue</strong>
                    <p id="distance-display" style="color: var(--text-muted); margin-top: 5px;">Calculating distance...</p>
                </div>
            </div>

            <div id="status-message" class="error-main" style="display: none;"></div>
            
            <p style="text-align: center; color: var(--accent); font-weight: 500; font-size: 1.1rem; margin-bottom: 1.5rem;">
                Select course, unit, and venue before launching Facial Recognition
            </p>
            
            <form class="lecture-options" id="selectForm" style="display: flex; gap: 1rem; justify-content: center; max-width: 800px; margin: 0 auto 2rem;">
                <select required name="venue" id="venueSelect" onchange="this.form.submit()" style="max-width: 30%;">
                    <option value="" <?php echo !isset($_GET['venue']) ? 'selected' : ''; ?>>Select Venue</option>
                    <?php
                    $venueNames = getVenueNames();
                    foreach ($venueNames as $venue) {
                        $selected = (isset($_GET['venue']) && $_GET['venue'] === $venue["className"]) ? 'selected' : '';
                        echo '<option value="' . $venue["className"] . '" ' . $selected . '>' . $venue["className"] . '</option>';
                    }
                    ?>
                </select>

                <select required name="course" id="courseSelect" onChange="updateTable()" style="max-width: 30%;">
                    <option value="" <?php echo !isset($_GET['course']) ? 'selected' : ''; ?>>Select Course</option>
                    <?php
                    $courseNames = getCourseNames();
                    foreach ($courseNames as $course) {
                        $selected = (isset($_GET['course']) && $_GET['course'] === $course["courseCode"]) ? 'selected' : '';
                        echo '<option value="' . $course["courseCode"] . '" ' . $selected . '>' . $course["name"] . '</option>';
                    }
                    ?>
                </select>

                <select required name="unit" id="unitSelect" onChange="updateTable()">
                    <option value="" <?php echo !isset($_GET['unit']) ? 'selected' : ''; ?>>Select Unit</option>
                    <?php
                    $unitNames = getUnitNames();
                    foreach ($unitNames as $unit) {
                        $selected = (isset($_GET['unit']) && $_GET['unit'] === $unit["unitCode"]) ? 'selected' : '';
                        echo '<option value="' . $unit["unitCode"] . '" ' . $selected . '>' . $unit["name"] . '</option>';
                    }
                    ?>
                </select>
            </form>

            <div class="attendance-button">
                <button id="startButton" class="add">Launch Facial Recognition</button>
                <button id="endAttendance" class="add">END Attendance Taking</button>
            </div>

            <div class="video-container" style="display:none;">
                <video id="video" width="640" height="480" autoplay></video>
                <canvas id="canvas" width="640" height="480" style="position: absolute; opacity: 0;"></canvas>
                <canvas id="overlay" width="640" height="480"></canvas>
                <div id="recognitionStatus"></div>
                <div id="captureProgress"></div>
            </div>

            <div class="table-container">
                <div id="studentTableContainer"></div>
            </div>
        </div>
    </section>

    <script>
        window.ATTENDANCE_SETTINGS = {
            confidenceThreshold: <?php echo get_setting($pdo, 'face_confidence_threshold', 65); ?>,
            emailAlertsMode: '<?php echo get_setting($pdo, 'email_alerts_mode', 'auto'); ?>'
        };
    </script>
    <?php js_asset(["active_link", 'face_logics/script']) ?>




</body>

</html>