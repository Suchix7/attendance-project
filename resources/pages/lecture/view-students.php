<?php

$courseCode = isset($_GET['course']) ? $_GET['course'] : '';
$unitCode = isset($_GET['unit']) ? $_GET['unit'] : '';

$studentRows = fetchStudentRecordsFromDatabase($courseCode, $unitCode);

$coursename = "";
if (!empty($courseCode)) {
    $coursename_query = "SELECT name FROM tblcourse WHERE courseCode = '$courseCode'";
    $result = fetch($coursename_query);
    foreach ($result as $row) {

        $coursename = $row['name'];
    }
}
$unitname = "";
if (!empty($unitCode)) {
    $unitname_query = "SELECT name FROM tblunit WHERE unitCode = '$unitCode'";
    $result = fetch($unitname_query);
    foreach ($result as $row) {

        $unitname = $row['name'];
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
    <title>lecture Dashboard</title>
    <link rel="stylesheet" href="resources/assets/css/styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.css" rel="stylesheet">
</head>



<body>
    <?php include 'includes/topbar.php'; ?>
    <section class="main">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main--content">
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
            </form>


            <div class="table-container">
                <div class="title">
                    <h2 class="section--title">Students List</h2>
                </div>
                <div class="table attendance-table" id="attendaceTable">
                    <table>
                        <thead>
                            <tr>
                                <th>Registration No</th>
                                <th>First Name</th>
                                <th>Last Name</th>
                                <th>Email</th>

                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $semesterId = 0;
                            if (!empty($courseCode)) {
                                $stmtFaculty = $pdo->prepare("SELECT f.facultyCode FROM tblcourse c JOIN tblfaculty f ON c.facultyID = f.Id WHERE c.courseCode = ?");
                                $stmtFaculty->execute([$courseCode]);
                                $facultyCode = $stmtFaculty->fetchColumn();
                                if ($facultyCode && function_exists('getActiveSemester')) {
                                    $activeSem = getActiveSemester($pdo, $facultyCode);
                                    if ($activeSem) {
                                        $semesterId = $activeSem['Id'];
                                    }
                                }
                            }

                            $query = "SELECT * FROM tblstudents WHERE courseCode = :courseCode";
                            if ($semesterId) {
                                $query .= " AND semesterID = :semesterID";
                            }
                            $query .= " ORDER BY firstName, lastName";

                            $stmt = $pdo->prepare($query);
                            $params = [':courseCode' => $courseCode];
                            if ($semesterId) {
                                $params[':semesterID'] = $semesterId;
                            }
                            $stmt->execute($params);
                            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            if ($result) {
                                foreach ($result as $row) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row['registrationNumber']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['firstName']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['lastName']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                                    echo "</tr>";
                                }
                                echo "</table>";
                            }
                            ?>

                        </tbody>
                    </table>

                </div>
            </div>

        </div>
        </div>
    </section>
    <div>
        <?php js_asset(["active_link", "min/js/filesaver", "min/js/xlsx"]) ?>
</body>


<script>
    function updateTable() {
        console.log("update noted");
        var courseSelect = document.getElementById("courseSelect");
        var unitSelect = document.getElementById("unitSelect");

        var selectedCourse = courseSelect.value;
        var selectedUnit = unitSelect.value;

        var url = "view-students";
        if (selectedCourse && selectedUnit) {
            url += "?course=" + encodeURIComponent(selectedCourse) + "&unit=" + encodeURIComponent(selectedUnit);
            window.location.href = url;
            console.log(url)
        }
    }
</script>

</html>