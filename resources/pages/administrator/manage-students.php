<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

function debug_log($message)
{
    error_log(date('Y-m-d H:i:s') . " - " . $message . "\n", 3, __DIR__ . '/debug.log');
}

if (isset($_POST['addStudent'])) {
    try {
        debug_log("Starting student registration process");

        $firstName = $_POST['firstName'];
        $lastName = $_POST['lastName'];
        $email = $_POST['email'];
        $registrationNumber = $_POST['registrationNumber'];
        $courseCode = $_POST['course'];
        $faculty = $_POST['faculty'];
        $semesterID = isset($_POST['semester']) ? (int)$_POST['semester'] : 0;
        $dateRegistered = date("Y-m-d");

        if (!preg_match("/^[a-zA-Z]+$/", $firstName)) {
            throw new Exception("First name must contain letters only");
        }

        if (!preg_match("/^[a-zA-Z]+$/", $lastName)) {
            throw new Exception("Last name must contain letters only");
        }

        // ✅ Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email address");
        }


        debug_log("Student details: " . json_encode([
            'name' => "$firstName $lastName",
            'reg' => $registrationNumber
        ]));

        // Get base directory and required paths
        $baseDir = realpath(__DIR__ . '/../../..');
        $pythonScript = $baseDir . '/python/realtime_recognition.py';
        $modelsDir = $baseDir . '/models';
        $assetsDir = $baseDir . '/assets';
        $validatedFacesDir = $baseDir . '/validated_faces';
        $studentsDir = $baseDir . '/students';

        debug_log("Base directory: $baseDir");
        debug_log("Python script path: $pythonScript");

        // Ensure all required directories exist
        foreach ([$modelsDir, $assetsDir, $validatedFacesDir, $studentsDir] as $dir) {
            if (!file_exists($dir)) {
                debug_log("Creating directory: $dir");
                if (!mkdir($dir, 0777, true)) {
                    throw new Exception("Failed to create directory: $dir");
                }
                chmod($dir, 0777);
            }
        }

        // Create student directories
        $studentDir = "{$studentsDir}/{$registrationNumber}";
        $validatedDir = "{$validatedFacesDir}/student{$registrationNumber}";
        $labelDir = "resources/labels/{$registrationNumber}";

        foreach ([$studentDir, $validatedDir, $labelDir] as $dir) {
            if (!file_exists($dir)) {
                debug_log("Creating student directory: $dir");
                if (!mkdir($dir, 0777, true)) {
                    throw new Exception("Failed to create directory: $dir");
                }
                chmod($dir, 0777);
            }
        }

        $imageFileNames = []; // Array to hold image file names
        $imageCount = 0;

        // Process and save images
        for ($i = 1; $i <= 10; $i++) {
            if (isset($_POST["capturedImage$i"])) {
                debug_log("Processing image $i for student $registrationNumber");

                $base64Data = $_POST["capturedImage$i"];
                if (strpos($base64Data, 'base64,') !== false) {
                    $base64Data = explode(',', $base64Data)[1];
                }

                $imageData = base64_decode($base64Data);
                if (!$imageData) {
                    debug_log("Failed to decode image $i");
                    continue;
                }

                $fileName = "face_{$i}.jpg";

                // Save in students directory (original system)
                $studentPath = "{$studentDir}/{$fileName}";
                if (file_put_contents($studentPath, $imageData) === false) {
                    throw new Exception("Failed to save image to: $studentPath");
                }
                debug_log("Saved image to: $studentPath");

                // Save in validated_faces directory (new system)
                $validatedPath = "{$validatedDir}/{$fileName}";
                if (file_put_contents($validatedPath, $imageData) === false) {
                    throw new Exception("Failed to save image to: $validatedPath");
                }
                debug_log("Saved image to: $validatedPath");

                // Save in labels directory (for display)
                $labelPath = "{$labelDir}/{$fileName}";
                if (file_put_contents($labelPath, $imageData) === false) {
                    throw new Exception("Failed to save image to: $labelPath");
                }
                debug_log("Saved image to: $labelPath");

                $imageFileNames[] = $fileName;
                $imageCount++;
            }
        }

        if ($imageCount === 0) {
            throw new Exception("No valid images were captured");
        }

        // Create student info.json file
        $studentInfo = [
            'id' => $registrationNumber,
            'name' => $firstName . ' ' . $lastName,
            'email' => $email,
            'course' => $courseCode,
            'faculty' => $faculty,
            'semesterID' => $semesterID
        ];

        // Save info.json in both locations
        $infoJson = json_encode($studentInfo);
        $studentInfoPath = "{$studentDir}/info.json";
        $validatedInfoPath = "{$validatedDir}/info.json";

        if (file_put_contents($studentInfoPath, $infoJson) === false) {
            throw new Exception("Failed to save student info to: $studentInfoPath");
        }
        if (file_put_contents($validatedInfoPath, $infoJson) === false) {
            throw new Exception("Failed to save student info to: $validatedInfoPath");
        }
        debug_log("Saved student info files");

        // Check for cascade file
        $cascadeFile = $assetsDir . '/haarcascade_frontalface_default.xml';
        if (!file_exists($cascadeFile)) {
            // Download cascade file if it doesn't exist
            $cascadeUrl = 'https://raw.githubusercontent.com/opencv/opencv/master/data/haarcascades/haarcascade_frontalface_default.xml';
            $cascadeContent = file_get_contents($cascadeUrl);
            if ($cascadeContent === false) {
                throw new Exception("Failed to download cascade file");
            }
            if (file_put_contents($cascadeFile, $cascadeContent) === false) {
                throw new Exception("Failed to save cascade file");
            }
            debug_log("Downloaded cascade file to: $cascadeFile");
        }

        if (!file_exists($pythonScript)) {
            throw new Exception("Python script not found at: $pythonScript");
        }

        // Call the script with the train argument
        $command = "python \"{$pythonScript}\" --train";
        debug_log("Executing command: $command");

        $output = [];
        $returnVar = 0;
        exec($command . " 2>&1", $output, $returnVar);
        debug_log("Command output: " . print_r($output, true));
        debug_log("Return code: $returnVar");

        if ($returnVar !== 0) {
            throw new Exception("Face recognition training failed. Output: " . implode("\n", $output));
        }

        // Check for duplicate registration number
        $checkQuery = $pdo->prepare("SELECT COUNT(*) FROM tblstudents WHERE registrationNumber = :registrationNumber");
        $checkQuery->execute([':registrationNumber' => $registrationNumber]);
        $count = $checkQuery->fetchColumn();

        if ($count > 0) {
            throw new Exception("Student with the given Registration No: $registrationNumber already exists!");
        }

        // Insert new student
        $insertQuery = $pdo->prepare("
            INSERT INTO tblstudents 
            (firstName, lastName, email, registrationNumber, faculty, courseCode, semesterID, studentImage, dateRegistered) 
            VALUES 
            (:firstName, :lastName, :email, :registrationNumber, :faculty, :courseCode, :semesterID, :studentImage, :dateRegistered)
        ");

        $insertQuery->execute([
            ':firstName' => $firstName,
            ':lastName' => $lastName,
            ':email' => $email,
            ':registrationNumber' => $registrationNumber,
            ':faculty' => $faculty,
            ':courseCode' => $courseCode,
            ':semesterID' => $semesterID,
            ':studentImage' => json_encode($imageFileNames),
            ':dateRegistered' => $dateRegistered
        ]);

        $_SESSION['message'] = "Student: $registrationNumber added successfully!";
        debug_log("Student registration completed successfully");

    } catch (Exception $e) {
        debug_log("Error: " . $e->getMessage());
        $_SESSION['message'] = "Error: " . $e->getMessage();

        // Clean up files if there was an error
        if (isset($validatedDir) && file_exists($validatedDir)) {
            array_map('unlink', glob("{$validatedDir}/*.*"));
            rmdir($validatedDir);
        }
    }
}

// Add edit handler for students
if (isset($_POST['editStudent'])) {
    try {
        debug_log("Starting student update process");

        $studentId = filter_var($_POST['studentId'], FILTER_VALIDATE_INT);
        $firstName = $_POST['firstName'];
        $lastName = $_POST['lastName'];
        $email = $_POST['email'];
        $registrationNumber = $_POST['registrationNumber'];
        $courseCode = $_POST['course'];
        $faculty = $_POST['faculty'];
        $semesterID = isset($_POST['semester']) ? (int)$_POST['semester'] : 0;


    if (!preg_match("/^[a-zA-Z]+$/", $firstName)) {
    throw new Exception("First name must contain letters only");
}
if (!preg_match("/^[a-zA-Z]+$/", $lastName)) {
    throw new Exception("Last name must contain letters only");
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    throw new Exception("Invalid email address");
}

        debug_log("Student update details: " . json_encode([
            'id' => $studentId,
            'name' => "$firstName $lastName",
            'reg' => $registrationNumber
        ]));

        // Check if registration number exists for other students
        $checkQuery = $pdo->prepare("SELECT COUNT(*) FROM tblstudents WHERE registrationNumber = :registrationNumber AND Id != :id");
        $checkQuery->execute([
            ':registrationNumber' => $registrationNumber,
            ':id' => $studentId
        ]);

        if ($checkQuery->fetchColumn() > 0) {
            throw new Exception("Another student with this registration number already exists!");
        }

        // Update student information
        $updateQuery = $pdo->prepare("
            UPDATE tblstudents SET 
            firstName = :firstName,
            lastName = :lastName,
            email = :email,
            registrationNumber = :registrationNumber,
            faculty = :faculty,
            courseCode = :courseCode,
            semesterID = :semesterID
            WHERE Id = :id
        ");

        $updateQuery->execute([
            ':firstName' => $firstName,
            ':lastName' => $lastName,
            ':email' => $email,
            ':registrationNumber' => $registrationNumber,
            ':faculty' => $faculty,
            ':courseCode' => $courseCode,
            ':semesterID' => $semesterID,
            ':id' => $studentId
        ]);

        // Update student info.json files
        $baseDir = realpath(__DIR__ . '/../../..');
        $studentsDir = $baseDir . '/students';
        $validatedFacesDir = $baseDir . '/validated_faces';

        $studentInfo = [
            'id' => $registrationNumber,
            'name' => $firstName . ' ' . $lastName,
            'email' => $email,
            'course' => $courseCode,
            'faculty' => $faculty,
            'semesterID' => $semesterID
        ];

        $infoJson = json_encode($studentInfo);

        // Update info.json in both directories
        $studentDir = "{$studentsDir}/{$registrationNumber}";
        $validatedDir = "{$validatedFacesDir}/student{$registrationNumber}";

        if (file_exists($studentDir)) {
            file_put_contents("{$studentDir}/info.json", $infoJson);
        }
        if (file_exists($validatedDir)) {
            file_put_contents("{$validatedDir}/info.json", $infoJson);
        }

        $_SESSION['message'] = "Student information updated successfully!";
        debug_log("Student update completed successfully");

    } catch (Exception $e) {
        debug_log("Error updating student: " . $e->getMessage());
        $_SESSION['message'] = "Error: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="resources/images/logo/face logo.png" rel="icon">
    <title>AMS - Dashboard</title>
    <link rel="stylesheet" href="resources/assets/css/admin_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.css" rel="stylesheet">
</head>

<body>
    <?php include 'includes/topbar.php'; ?>

    <section class=main>

        <?php include "Includes/sidebar.php"; ?>

        <div class="main--content">
            <div id="overlay"></div>
            <?php showMessage(); ?>
            <div class="table-container">

                <div class="title" id="showButton">
                    <h2 class="section--title">Students</h2>
                    <button class="add"><i class="ri-add-line"></i>Add Student</button>
                </div>

                <div class="table">
                    <table>
                        <thead                            <tr>
                                <th>Registration No</th>
                                <th>Name</th>
                                <th>Faculty</th>
                                <th>Course</th>
                                <th>Semester</th>
                                <th>Email</th>
                                <th>Settings</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql = "SELECT s.*, sem.name as semesterName FROM tblstudents s LEFT JOIN tblsemester sem ON s.semesterID = sem.Id";
                            $result = fetch($sql);
                            if ($result) {
                                foreach ($result as $row) {
                                    echo "<tr id='rowstudents{$row["Id"]}'>";
                                    echo "<td>" . $row["registrationNumber"] . "</td>";
                                    echo "<td>" . $row["firstName"] . " " . $row["lastName"] . "</td>";
                                    echo "<td>" . $row["faculty"] . "</td>";
                                    echo "<td>" . $row["courseCode"] . "</td>";
                                    echo "<td>" . ($row["semesterName"] ?: 'Not Assigned') . "</td>";
                                    echo "<td>" . $row["email"] . "</td>";
                                    echo "<td>
                                            <span>
                                                <i class='ri-edit-line edit' 
                                                   data-id='{$row["Id"]}' 
                                                   data-firstname='{$row["firstName"]}'
                                                   data-lastname='{$row["lastName"]}'
                                                   data-email='{$row["email"]}'
                                                   data-regno='{$row["registrationNumber"]}'
                                                   data-faculty='{$row["faculty"]}'
                                                   data-course='{$row["courseCode"]}'
                                                   data-semester='{$row["semesterID"]}'></i>
                                                <i class='ri-delete-bin-line delete' data-id='{$row["Id"]}' data-name='students'></i>
                                            </span>
                                          </td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='6'>No records found</td></tr>";
                            }

                            ?>

                        </tbody>
                    </table>
                </div>

            </div>
            <div class="formDiv" id="form" style="display:none;">

                <form method="post">
                    <div style="display:flex; justify-content:space-around;">
                        <div class="form-title">
                            <p>Add Student</p>
                        </div>
                        <div>
                            <span class="close">&times;</span>
                        </div>
                    </div>
                    <div>
                        <div>
                            <input type="text" name="firstName" placeholder="First Name"required>
                            <input type="text" name="lastName" " placeholder=" Last Name"required>
                            <input type="email" name="email" placeholder="Email Address">
                            <input type="text" required id="registrationNumber" name="registrationNumber"
                                placeholder="Registration Number"> <br>
                            <p id="error" style="color: red; display: none;">Invalid characters in registration number.</p>
                            <select required name="faculty" id="facultySelect" onchange="filterSemesters('facultySelect', 'semesterSelect')">
                                <option value="" selected>Select Faculty</option>
                                <?php
                                $facultyNames = getFacultyNames();
                                foreach ($facultyNames as $faculty) {
                                    echo '<option value="' . $faculty["facultyCode"] . '">' . $faculty["facultyName"] . '</option>';
                                }
                                ?>
                            </select> <br />
 
                            <select required name="course">
                                <option value="" selected>Select Course</option>
                                <?php
                                $courseNames = getCourseNames();
                                foreach ($courseNames as $course) {
                                    echo '<option value="' . $course["courseCode"] . '">' . $course["name"] . '</option>';
                                }
                                ?>
                            </select> <br />

                            <select required name="semester" id="semesterSelect">
                                <option value="" selected>Select Semester</option>
                                <?php
                                if (function_exists('getAllSemesters')) {
                                    $semesters = getAllSemesters($pdo);
                                    foreach ($semesters as $sem) {
                                        echo '<option value="' . $sem['Id'] . '">' . htmlspecialchars($sem['name']) . ' (' . htmlspecialchars($sem['facultyName'] ?: $sem['facultyCode']) . ')' . '</option>';
                                    }
                                }
                                ?>
                            </select>t>
                        </div>
                        <div>
                            <div class="form-title-image">
                                <p>Take Student Pictures
                                <p>
                            </div>
                            <div id="open_camera" class="image-box" onclick="takeMultipleImages()">
                                <img src="resources/images/default.png" alt="Default Image">
                            </div>
                            <div id="multiple-images">



                            </div>


                        </div>
                    </div>

                    <input type="submit" class="btn-submit" value="Save Student" name="addStudent" />


                </form>
            </div>

            <!-- Add Edit Form -->
            <div class="formDiv" id="editForm" style="display:none; height: 500px;">
                <form method="post">
                    <div style="display:flex; justify-content:space-around;">
                        <div class="form-title">
                            <p>Edit Student</p>
                        </div>
                        <div>
                            <span class="close">&times;</span>
                        </div>
                    </div>
                    <div>
                        <input type="hidden" name="studentId" id="editStudentId">
                        <input type="text" name="firstName" id="editFirstName" placeholder="First Name" required>
                        <input type="text" name="lastName" id="editLastName" placeholder="Last Name" required>
                        <input type="email" name="email" id="editEmail" placeholder="Email Address" required>
                        <input type="text" required id="editRegistrationNumber" name="registrationNumber"
                            placeholder="Registration Number">
                        <p id="editError" style="color: red; display: none;">Invalid characters in registration number.
                        </p>                        <select required name="faculty" id="editFaculty" onchange="filterSemesters('editFaculty', 'editSemester')">
                            <option value="">Select Faculty</option>
                            <?php
                            $facultyNames = getFacultyNames();
                            foreach ($facultyNames as $faculty) {
                                echo '<option value="' . $faculty["facultyCode"] . '">' . $faculty["facultyName"] . '</option>';
                            }
                            ?>
                        </select>
 
                        <select required name="course" id="editCourse">
                            <option value="">Select Course</option>
                            <?php
                            $courseNames = getCourseNames();
                            foreach ($courseNames as $course) {
                                echo '<option value="' . $course["courseCode"] . '">' . $course["name"] . '</option>';
                            }
                            ?>
                        </select>

                        <select required name="semester" id="editSemester">
                            <option value="">Select Semester</option>
                            <?php
                            if (function_exists('getAllSemesters')) {
                                $semesters = getAllSemesters($pdo);
                                foreach ($semesters as $sem) {
                                    echo '<option value="' . $sem['Id'] . '">' . htmlspecialchars($sem['name']) . ' (' . htmlspecialchars($sem['facultyName'] ?: $sem['facultyCode']) . ')' . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <input type="submit" class="btn-submit" value="Update Student" name="editStudent">
                </form>
            </div>

    </section>



    <?php js_asset(["admin_functions", "delete_request", "script", "active_link"]) ?>

    <script>
        const registrationNumberInput = document.getElementById('registrationNumber');
        const errorMessage = document.getElementById('error');

        const invalidCharacters = /[\\/:*?"<>|]/g;

        registrationNumberInput.addEventListener('input', () => {
            const originalValue = registrationNumberInput.value;

            const sanitizedValue = originalValue.replace(invalidCharacters, '');

            if (originalValue !== sanitizedValue) {
                errorMessage.style.display = 'inline';
                errorMessage.textContent = 'Invalid characters removed.';
            } else {
                errorMessage.style.display = 'none';
            }

            registrationNumberInput.value = sanitizedValue;
        });

        const allSemesters = <?php echo json_encode(function_exists('getAllSemesters') ? getAllSemesters($pdo) : []); ?>;
        
        function filterSemesters(facultySelectId, semesterSelectId, selectedSemId = '') {
            const facultyVal = document.getElementById(facultySelectId).value;
            const semSelect = document.getElementById(semesterSelectId);
            semSelect.innerHTML = '<option value="">Select Semester</option>';
            
            allSemesters.forEach(sem => {
                if (!facultyVal || sem.facultyCode === facultyVal) {
                    const opt = document.createElement('option');
                    opt.value = sem.Id;
                    opt.textContent = `${sem.name} (${sem.facultyName || sem.facultyCode})`;
                    if (String(sem.Id) === String(selectedSemId)) {
                        opt.selected = true;
                    }
                    semSelect.appendChild(opt);
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            // Initial filter for Add Student form
            filterSemesters('facultySelect', 'semesterSelect');

            const editForm = document.getElementById('editForm');
            const overlay = document.getElementById('overlay');
            const closeButtons = document.querySelectorAll('.close');

            // Handle edit button clicks
            document.querySelectorAll('.edit').forEach(function (editIcon) {
                editIcon.addEventListener('click', function () {
                    const id = this.getAttribute('data-id');

                    // Populate the edit form
                    document.getElementById('editStudentId').value = id;
                    document.getElementById('editFirstName').value = this.getAttribute('data-firstname');
                    document.getElementById('editLastName').value = this.getAttribute('data-lastname');
                    document.getElementById('editEmail').value = this.getAttribute('data-email');
                    document.getElementById('editRegistrationNumber').value = this.getAttribute('data-regno');
                    const faculty = this.getAttribute('data-faculty');
                    document.getElementById('editFaculty').value = faculty;
                    document.getElementById('editCourse').value = this.getAttribute('data-course');

                    // Filter and select the semester in Edit Student form
                    const semId = this.getAttribute('data-semester');
                    filterSemesters('editFaculty', 'editSemester', semId);

                    // Show the edit form
                    editForm.style.display = 'block';
                    overlay.style.display = 'block';
                });
            });

            // Close form functionality
            closeButtons.forEach(function (closeButton) {
                closeButton.addEventListener('click', function () {
                    editForm.style.display = 'none';
                    overlay.style.display = 'none';
                });
            });

            // Close form when clicking overlay
            overlay.addEventListener('click', function () {
                editForm.style.display = 'none';
                this.style.display = 'none';
            });

            // Registration number validation for edit form
            const editRegistrationNumberInput = document.getElementById('editRegistrationNumber');
            const editErrorMessage = document.getElementById('editError');
            const invalidCharacters = /[\\/:*?"<>|]/g;

            editRegistrationNumberInput.addEventListener('input', () => {
                const originalValue = editRegistrationNumberInput.value;
                const sanitizedValue = originalValue.replace(invalidCharacters, '');

                if (originalValue !== sanitizedValue) {
                    editErrorMessage.style.display = 'inline';
                    editErrorMessage.textContent = 'Invalid characters removed.';
                } else {
                    editErrorMessage.style.display = 'none';
                }

                editRegistrationNumberInput.value = sanitizedValue;
            });
        });
    </script>
</body>

</html>