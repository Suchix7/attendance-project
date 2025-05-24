<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Student - Face Attendance System</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="container">
        <header>
            <h1>Add New Student</h1>
            <nav>
                <a href="index.php" class="nav-link">Back to Attendance</a>
            </nav>
        </header>

        <main>
            <div class="student-form">
                <form id="studentForm" class="form">
                    <div class="form-group">
                        <label for="studentId">Student ID:</label>
                        <input type="text" id="studentId" name="studentId" required>
                    </div>
                    <div class="form-group">
                        <label for="studentName">Full Name:</label>
                        <input type="text" id="studentName" name="studentName" required>
                    </div>
                </form>
            </div>

            <div class="face-capture-section">
                <div class="video-container">
                    <video id="video" width="640" height="480" autoplay></video>
                    <canvas id="overlay" width="640" height="480" style="position: absolute; top: 0; left: 0;"></canvas>
                    <canvas id="canvas" width="640" height="480" style="display: none;"></canvas>
                </div>

                <div class="controls">
                    <button id="startButton" class="btn">Start Camera</button>
                    <button id="captureButton" class="btn" disabled>Capture Face</button>
                </div>

                <div class="status-container">
                    <div id="status" class="status">Status: Camera not started</div>
                    <div id="captureStatus" class="capture-status"></div>
                </div>

                <div class="captured-faces">
                    <h3>Captured Faces</h3>
                    <div id="faceList" class="face-list"></div>
                </div>

                <div class="submit-section">
                    <button id="submitButton" class="btn btn-primary" disabled>Add Student</button>
                </div>
            </div>
        </main>
    </div>

    <script src="add_student.js"></script>
</body>

</html>