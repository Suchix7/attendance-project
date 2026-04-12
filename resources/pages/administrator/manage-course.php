<?php


if (isset($_POST["addCourse"])) {
    $courseName = htmlspecialchars(trim($_POST["courseName"])); // Escape and trim whitespace
    $courseCode = htmlspecialchars(trim($_POST["courseCode"]));
    $facultyID = filter_var($_POST["faculty"], FILTER_VALIDATE_INT);
    $dateRegistered = date("Y-m-d");

    if ($courseName && $courseCode && $facultyID) {
        $query = $pdo->prepare("SELECT * FROM tblcourse WHERE courseCode = :courseCode");
        $query->bindParam(':courseCode', $courseCode);
        $query->execute();

        if ($query->rowCount() > 0) {
            $_SESSION['message'] = "Course Already Exists";
        } else {
            $query = $pdo->prepare("INSERT INTO tblcourse (name, courseCode, facultyID, dateCreated) 
                                     VALUES (:name, :courseCode, :facultyID, :dateCreated)");
            $query->bindParam(':name', $courseName);
            $query->bindParam(':courseCode', $courseCode);
            $query->bindParam(':facultyID', $facultyID);
            $query->bindParam(':dateCreated', $dateRegistered);
            $query->execute();

            $_SESSION['message'] = "Course Inserted Successfully";
        }
    } else {
        $_SESSION['message'] = "Invalid input for course";
    }
}

if (isset($_POST["addUnit"])) {
    $unitName = htmlspecialchars(trim($_POST["unitName"]));
    $unitCode = htmlspecialchars(trim($_POST["unitCode"]));
    $courseID = filter_var($_POST["course"], FILTER_VALIDATE_INT);
    $dateRegistered = date("Y-m-d");

    if ($unitName && $unitCode && $courseID) {
        $query = $pdo->prepare("SELECT * FROM tblunit WHERE unitCode = :unitCode");
        $query->bindParam(':unitCode', $unitCode);
        $query->execute();

        if ($query->rowCount() > 0) {
            $_SESSION['message'] = "Unit Already Exists";
        } else {
            $query = $pdo->prepare("INSERT INTO tblunit (name, unitCode, courseID, dateCreated) 
                                     VALUES (:name, :unitCode, :courseID, :dateCreated)");
            $query->bindParam(':name', $unitName);
            $query->bindParam(':unitCode', $unitCode);
            $query->bindParam(':courseID', $courseID);
            $query->bindParam(':dateCreated', $dateRegistered);
            $query->execute();

            $_SESSION['message'] = "Unit Inserted Successfully";
        }
    } else {
        $_SESSION['message'] = "Invalid input for unit";
    }
}

if (isset($_POST["addFaculty"])) {
    $facultyName = htmlspecialchars(trim($_POST["facultyName"]));
    $facultyCode = htmlspecialchars(trim($_POST["facultyCode"]));
    $dateRegistered = date("Y-m-d");

    if ($facultyName && $facultyCode) {
        $query = $pdo->prepare("SELECT * FROM tblfaculty WHERE facultyCode = :facultyCode");
        $query->bindParam(':facultyCode', $facultyCode);
        $query->execute();

        if ($query->rowCount() > 0) {
            $_SESSION['message'] = "Faculty Already Exists";
        } else {
            $query = $pdo->prepare("INSERT INTO tblfaculty (facultyName, facultyCode, dateRegistered) 
                                     VALUES (:facultyName, :facultyCode, :dateRegistered)");
            $query->bindParam(':facultyName', $facultyName);
            $query->bindParam(':facultyCode', $facultyCode);
            $query->bindParam(':dateRegistered', $dateRegistered);
            $query->execute();

            $_SESSION['message'] = "Faculty Inserted Successfully";
        }
    } else {
        $_SESSION['message'] = "Invalid input for faculty";
    }
}

// Add edit handlers for Course, Unit, and Faculty
if (isset($_POST["editCourse"])) {
    $courseId = filter_var($_POST["courseId"], FILTER_VALIDATE_INT);
    $courseName = htmlspecialchars(trim($_POST["courseName"]));
    $courseCode = htmlspecialchars(trim($_POST["courseCode"]));
    $facultyID = filter_var($_POST["faculty"], FILTER_VALIDATE_INT);

    if ($courseId && $courseName && $courseCode && $facultyID) {
        $query = $pdo->prepare("UPDATE tblcourse SET name = :name, courseCode = :courseCode, facultyID = :facultyID 
                               WHERE Id = :id");
        $query->execute([
            ':name' => $courseName,
            ':courseCode' => $courseCode,
            ':facultyID' => $facultyID,
            ':id' => $courseId
        ]);
        $_SESSION['message'] = "Course Updated Successfully";
    }
}

if (isset($_POST["editUnit"])) {
    $unitId = filter_var($_POST["unitId"], FILTER_VALIDATE_INT);
    $unitName = htmlspecialchars(trim($_POST["unitName"]));
    $unitCode = htmlspecialchars(trim($_POST["unitCode"]));
    $courseID = filter_var($_POST["course"], FILTER_VALIDATE_INT);

    if ($unitId && $unitName && $unitCode && $courseID) {
        $query = $pdo->prepare("UPDATE tblunit SET name = :name, unitCode = :unitCode, courseID = :courseID 
                               WHERE Id = :id");
        $query->execute([
            ':name' => $unitName,
            ':unitCode' => $unitCode,
            ':courseID' => $courseID,
            ':id' => $unitId
        ]);
        $_SESSION['message'] = "Unit Updated Successfully";
    }
}

if (isset($_POST["editFaculty"])) {
    $facultyId = filter_var($_POST["facultyId"], FILTER_VALIDATE_INT);
    $facultyName = htmlspecialchars(trim($_POST["facultyName"]));
    $facultyCode = htmlspecialchars(trim($_POST["facultyCode"]));

    if ($facultyId && $facultyName && $facultyCode) {
        $query = $pdo->prepare("UPDATE tblfaculty SET facultyName = :facultyName, facultyCode = :facultyCode 
                               WHERE Id = :id");
        $query->execute([
            ':facultyName' => $facultyName,
            ':facultyCode' => $facultyCode,
            ':id' => $facultyId
        ]);
        $_SESSION['message'] = "Faculty Updated Successfully";
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
    <title>Dashboard</title>
    <link rel="stylesheet" href="resources/assets/css/admin_styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.css" rel="stylesheet">
</head>

<body>
    <?php include 'includes/topbar.php' ?>
    <section class="main">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main--content">
            <div id="overlay"></div>
            <div class="overview">
                <div class="title">
                    <h2 class="section--title">Overview</h2>
                    <select name="date" id="date" class="dropdown">
                        <option value="today">Today</option>
                        <option value="lastweek">Last Week</option>
                        <option value="lastmonth">Last Month</option>
                        <option value="lastyear">Last Year</option>
                        <option value="alltime">All Time</option>
                    </select>
                </div>
                <div class="cards">
                    <div id="addCourse" class="card card-1">

                        <div class="card--data">
                            <div class="card--content">
                                <button class="add"><i class="ri-add-line"></i>Add Course</button>
                                <h1><?php total_rows('tblcourse') ?> Courses</h1>
                            </div>
                            <i class="ri-user-2-line card--icon--lg"></i>
                        </div>

                    </div>
                    <div class="card card-1" id="addUnit">

                        <div class="card--data">
                            <div class="card--content">
                                <button class="add"><i class="ri-add-line"></i>Add Units</button>
                                <h1><?php total_rows('tblunit') ?> Units</h1>
                            </div>
                            <i class="ri-file-text-line card--icon--lg"></i>
                        </div>

                    </div>

                    <div class="card card-1" id="addFaculty">

                        <div class="card--data">
                            <div class="card--content">
                                <button class="add"><i class="ri-add-line"></i>Add Faculty</button>
                                <h1><?php total_rows("tblfaculty") ?> faculties </h1>
                            </div>
                            <i class="ri-user-line card--icon--lg"></i>
                        </div>

                    </div>
                </div>
            </div>

            <?php showMessage() ?>
            <div class="table-container">
                <div class="title">
                    <h2 class="section--title">Course</h2>
                </div>
                <div class="table">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Faculty</th>
                                <th>Total Units</th>
                                <th>Total Students</th>
                                <th>Date Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql = "SELECT 
                        c.name AS course_name,
                        c.courseCode AS course_code,
                        c.facultyID AS faculty,
                        f.facultyName AS faculty_name,
                        c.Id AS Id,
                        COUNT(u.Id) AS total_units,
                        COUNT(DISTINCT s.Id) AS total_students,
                        c.dateCreated AS date_created
                        FROM tblcourse c
                        LEFT JOIN tblunit u ON c.Id = u.courseID
                        LEFT JOIN tblstudents s ON c.courseCode = s.courseCode
                        LEFT JOIN tblfaculty f on c.facultyID=f.Id
                        GROUP BY c.Id";

                            $result = fetch($sql);

                            if ($result) {
                                foreach ($result as $row) {
                                    echo "<tr id='rowcourse{$row["Id"]}'>";
                                    echo "<td>" . $row["course_name"] . "</td>";
                                    echo "<td>" . $row["faculty_name"] . "</td>";
                                    echo "<td>" . $row["total_units"] . "</td>";
                                    echo "<td>" . $row["total_students"] . "</td>";
                                    echo "<td>" . $row["date_created"] . "</td>";
                                    echo "<td>
                                            <span>
                                                <i class='ri-edit-line edit' data-id='{$row["Id"]}' data-name='course' 
                                                   data-coursename='{$row["course_name"]}' data-coursecode='{$row["course_code"]}' 
                                                   data-faculty='{$row["faculty"]}'></i>
                                                <i class='ri-delete-bin-line delete' data-id='{$row["Id"]}' data-name='course'></i>
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
            <div class="table-container">
                <div class="title">
                    <h2 class="section--title">Unit</h2>
                </div>
                <div class="table">
                    <table>
                        <thead>
                            <tr>
                                <th>Unit Code</th>
                                <th>Name</th>
                                <th>Course</th>
                                <th>Total Student</th>
                                <th>Date Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql = "SELECT 
                            c.name AS course_name,
                            u.unitCode AS unit_code,
                            u.name AS unit_name, 
                            u.Id as Id,
                            u.courseID as courseID,
                            u.dateCreated AS date_created,
                            COUNT(s.Id) AS total_students
                            FROM tblunit u
                            LEFT JOIN tblcourse c ON u.courseID = c.Id
                            LEFT JOIN tblstudents s ON c.courseCode = s.courseCode
                            GROUP BY u.Id";
                            $result = fetch($sql);
                            if ($result) {
                                foreach ($result as $row) {
                                    echo "<tr id='rowunit{$row["Id"]}'>";
                                    echo "<td>" . $row["unit_code"] . "</td>";
                                    echo "<td>" . $row["unit_name"] . "</td>";
                                    echo "<td>" . $row["course_name"] . "</td>";
                                    echo "<td>" . $row["total_students"] . "</td>";
                                    echo "<td>" . $row["date_created"] . "</td>";
                                    echo "<td>
                                            <span>
                                                <i class='ri-edit-line edit' data-id='{$row["Id"]}' data-name='unit' 
                                                   data-unitname='{$row["unit_name"]}' data-unitcode='{$row["unit_code"]}' 
                                                   data-course='{$row["courseID"]}'></i>
                                                <i class='ri-delete-bin-line delete' data-id='{$row["Id"]}' data-name='unit'></i>
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
            <div class="table-container">
                <div class="title">
                    <h2 class="section--title">Faculty</h2>
                </div>
                <div class="table">
                    <table>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Total Courses</th>
                                <th>Total Students</th>
                                <th>Total Lectures</th>
                                <th>Date Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql = "SELECT 
                           f.facultyName AS faculty_name,
                           f.facultyCode AS faculty_code,
                           f.Id as Id,
                           f.dateRegistered AS date_created,
                           COUNT(DISTINCT c.Id) AS total_courses,
                           COUNT(DISTINCT s.Id) AS total_students,
                           COUNT(DISTINCT l.Id) AS total_lectures
                       FROM tblfaculty f
                       LEFT JOIN tblcourse c ON f.Id = c.facultyID
                       LEFT JOIN tblstudents s ON f.facultyCode = s.faculty
                       LEFT JOIN tbllecture l ON f.facultyCode = l.facultyCode
                       GROUP BY f.Id";

                            $result = fetch($sql);
                            if ($result) {
                                foreach ($result as $row) {
                                    echo "<tr id='rowfaculty{$row["Id"]}'>";
                                    echo "<td>" . $row["faculty_code"] . "</td>";
                                    echo "<td>" . $row["faculty_name"] . "</td>";
                                    echo "<td>" . $row["total_courses"] . "</td>";
                                    echo "<td>" . $row["total_students"] . "</td>";
                                    echo "<td>" . $row["total_lectures"] . "</td>";
                                    echo "<td>" . $row["date_created"] . "</td>";
                                    echo "<td>
                                            <span>
                                                <i class='ri-edit-line edit' data-id='{$row["Id"]}' data-name='faculty' 
                                                   data-facultyname='{$row["faculty_name"]}' data-facultycode='{$row["faculty_code"]}'></i>
                                                <i class='ri-delete-bin-line delete' data-id='{$row["Id"]}' data-name='faculty'></i>
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

        </div>
        <div class="formDiv" id="addCourseForm" style="display:none; ">

            <form method="POST" action="" name="addCourse" enctype="multipart/form-data">
                <div style="display:flex; justify-content:space-around;">
                    <div class="form-title">
                        <p>Add Course</p>
                    </div>
                    <div>
                        <span class="close">&times;</span>
                    </div>
                </div>

                <input type="text" name="courseName" placeholder="Course Name" required>
                <input type="text" name="courseCode" placeholder="Course Code" required>


                <select required name="faculty">
                    <option value="" selected>Select Faculty</option>
                    <?php
                    $facultyNames = getFacultyNames();
                    foreach ($facultyNames as $faculty) {
                        echo '<option value="' . $faculty["Id"] . '">' . $faculty["facultyName"] . '</option>';
                    }
                    ?>
                </select>

                <input type="submit" class="submit" value="Save Course" name="addCourse">
            </form>
        </div>

        <div class="formDiv" id="addUnitForm" style="display:none; ">
            <form method="POST" action="" name="addUnit" enctype="multipart/form-data">
                <div style="display:flex; justify-content:space-around;">
                    <div class="form-title">
                        <p>Add Unit</p>
                    </div>
                    <div>
                        <span class="close">&times;</span>
                    </div>
                </div>

                <input type="text" name="unitName" placeholder="Unit Name" required>
                <input type="text" name="unitCode" placeholder="Unit Code" required>

                <select required name="lecture">
                    <option value="" selected>Assign Lecture</option>
                    <?php
                    $lectureNames = getLectureNames();
                    foreach ($lectureNames as $lecture) {
                        echo '<option value="' . $lecture["Id"] . '">' . $lecture["firstName"] . ' ' . $lecture["lastName"] . '</option>';
                    }
                    ?>
                </select>
                <select required name="course">
                    <option value="" selected>Select Course</option>
                    <?php
                    $courseNames = getCourseNames();
                    foreach ($courseNames as $course) {
                        echo '<option value="' . $course["Id"] . '">' . $course["name"] . '</option>';
                    }
                    ?>
                </select>

                <input type="submit" class="submit" value="Save Unit" name="addUnit">
            </form>
        </div>

        <div class="formDiv" id="addFacultyForm" style="display:none; ">
            <form method="POST" action="" name="addFaculty" enctype="multipart/form-data">
                <div style="display:flex; justify-content:space-around;">
                    <div class="form-title">
                        <p>Add Faculty</p>
                    </div>
                    <div>
                        <span class="close">&times;</span>
                    </div>
                </div>
                <input type="text" name="facultyName" placeholder="Faculty Name" required>
                <input type="text" name="facultyCode" placeholder="Faculty Code" required>
                <input type="submit" class="submit" value="Save Faculty" name="addFaculty">
            </form>
        </div>

        <div class="formDiv" id="editCourseForm" style="display:none;">
            <form method="POST" action="" name="editCourse" enctype="multipart/form-data">
                <div style="display:flex; justify-content:space-around;">
                    <div class="form-title">
                        <p>Edit Course</p>
                    </div>
                    <div>
                        <span class="close">&times;</span>
                    </div>
                </div>
                <input type="hidden" name="courseId" id="editCourseId">
                <input type="text" name="courseName" id="editCourseName" placeholder="Course Name" required>
                <input type="text" name="courseCode" id="editCourseCode" placeholder="Course Code" required>
                <select required name="faculty" id="editCourseFaculty">
                    <option value="">Select Faculty</option>
                    <?php
                    $facultyNames = getFacultyNames();
                    foreach ($facultyNames as $faculty) {
                        echo '<option value="' . $faculty["Id"] . '">' . $faculty["facultyName"] . '</option>';
                    }
                    ?>
                </select>
                <input type="submit" class="submit" value="Update Course" name="editCourse">
            </form>
        </div>

        <div class="formDiv" id="editUnitForm" style="display:none;">
            <form method="POST" action="" name="editUnit" enctype="multipart/form-data">
                <div style="display:flex; justify-content:space-around;">
                    <div class="form-title">
                        <p>Edit Unit</p>
                    </div>
                    <div>
                        <span class="close">&times;</span>
                    </div>
                </div>
                <input type="hidden" name="unitId" id="editUnitId">
                <input type="text" name="unitName" id="editUnitName" placeholder="Unit Name" required>
                <input type="text" name="unitCode" id="editUnitCode" placeholder="Unit Code" required>
                <select required name="course" id="editUnitCourse">
                    <option value="">Select Course</option>
                    <?php
                    $courseNames = getCourseNames();
                    foreach ($courseNames as $course) {
                        echo '<option value="' . $course["Id"] . '">' . $course["name"] . '</option>';
                    }
                    ?>
                </select>
                <input type="submit" class="submit" value="Update Unit" name="editUnit">
            </form>
        </div>

        <div class="formDiv" id="editFacultyForm" style="display:none;">
            <form method="POST" action="" name="editFaculty" enctype="multipart/form-data">
                <div style="display:flex; justify-content:space-around;">
                    <div class="form-title">
                        <p>Edit Faculty</p>
                    </div>
                    <div>
                        <span class="close">&times;</span>
                    </div>
                </div>
                <input type="hidden" name="facultyId" id="editFacultyId">
                <input type="text" name="facultyName" id="editFacultyName" placeholder="Faculty Name" required>
                <input type="text" name="facultyCode" id="editFacultyCode" placeholder="Faculty Code" required>
                <input type="submit" class="submit" value="Update Faculty" name="editFaculty">
            </form>
        </div>

    </section>

    <?php js_asset(["delete_request", "addCourse", "active_link"]) ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Handle edit clicks
            document.querySelectorAll('.edit').forEach(function (editIcon) {
                editIcon.addEventListener('click', function () {
                    const id = this.getAttribute('data-id');
                    const type = this.getAttribute('data-name');

                    if (type === 'course') {
                        const form = document.getElementById('editCourseForm');
                        document.getElementById('editCourseId').value = id;
                        document.getElementById('editCourseName').value = this.getAttribute('data-coursename');
                        document.getElementById('editCourseCode').value = this.getAttribute('data-coursecode');
                        document.getElementById('editCourseFaculty').value = this.getAttribute('data-faculty');
                        form.style.display = 'block';
                        document.getElementById('overlay').style.display = 'block';
                    }
                    else if (type === 'unit') {
                        const form = document.getElementById('editUnitForm');
                        document.getElementById('editUnitId').value = id;
                        document.getElementById('editUnitName').value = this.getAttribute('data-unitname');
                        document.getElementById('editUnitCode').value = this.getAttribute('data-unitcode');
                        document.getElementById('editUnitCourse').value = this.getAttribute('data-course');
                        form.style.display = 'block';
                        document.getElementById('overlay').style.display = 'block';
                    }
                    else if (type === 'faculty') {
                        const form = document.getElementById('editFacultyForm');
                        document.getElementById('editFacultyId').value = id;
                        document.getElementById('editFacultyName').value = this.getAttribute('data-facultyname');
                        document.getElementById('editFacultyCode').value = this.getAttribute('data-facultycode');
                        form.style.display = 'block';
                        document.getElementById('overlay').style.display = 'block';
                    }
                });
            });

            // Close edit forms
            document.querySelectorAll('.close').forEach(function (closeBtn) {
                closeBtn.addEventListener('click', function () {
                    this.closest('.formDiv').style.display = 'none';
                    document.getElementById('overlay').style.display = 'none';
                });
            });
        });
    </script>


</body>

</html>