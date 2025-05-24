<?php


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attendanceData = json_decode(file_get_contents("php://input"), true);
    if ($attendanceData) {
        try {
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

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="resources/images/logo/attnlg.png" rel="icon">
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
            <p style="font:80px; font-weight:400; color:blue; text-align:center; padding-top:2px;">Please select course,
                unit, and venue first. Before Launching Facial Recognition</p>
            <form class="lecture-options" id="selectForm">
                <select required name="course" id="courseSelect" onChange="updateTable()">
                    <option value="" selected>Select Course</option>
                    <?php
                    $courseNames = getCourseNames();
                    foreach ($courseNames as $course) {
                        echo '<option value="' . $course["courseCode"] . '">' . $course["name"] . '</option>';
                    }
                    ?>
                </select>

                <select required name="unit" id="unitSelect" onChange="updateTable()">
                    <option value="" selected>Select Unit</option>
                    <?php
                    $unitNames = getUnitNames();
                    foreach ($unitNames as $unit) {
                        echo '<option value="' . $unit["unitCode"] . '">' . $unit["name"] . '</option>';
                    }
                    ?>
                </select>

                <select required name="venue" id="venueSelect" onChange="updateTable()">
                    <option value="" selected>Select Venue</option>
                    <?php
                    $venueNames = getVenueNames();
                    foreach ($venueNames as $venue) {
                        echo '<option value="' . $venue["className"] . '">' . $venue["className"] . '</option>';
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
            </div>

            <div class="table-container">
                <div id="studentTableContainer"></div>
            </div>
        </div>
    </section>

    <?php js_asset(["active_link", 'face_logics/script']) ?>




</body>

</html>