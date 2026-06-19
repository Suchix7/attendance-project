<?php
require_once "../../lib/php_functions.php";
require_once "../../../database/database_connection.php";
$response = array();

if (isset($_POST['courseID']) && isset($_POST['unitID']) && isset($_POST['venueID'])) {
    $courseID = $_POST['courseID'];
    $unitID = $_POST['unitID'];
    $venueID = $_POST['venueID'];

    // Resolve active semester ID
    $semesterId = 0;
    $stmtFaculty = $pdo->prepare("SELECT f.facultyCode FROM tblcourse c JOIN tblfaculty f ON c.facultyID = f.Id WHERE c.courseCode = ?");
    $stmtFaculty->execute([$courseID]);
    $facultyCode = $stmtFaculty->fetchColumn();
    if ($facultyCode && function_exists('getActiveSemester')) {
        $activeSem = getActiveSemester($pdo, $facultyCode);
        if ($activeSem) {
            $semesterId = $activeSem['Id'];
        }
    }

    $sql = "SELECT registrationNumber FROM tblStudents WHERE courseCode = :courseID";
    if ($semesterId) {
        $sql .= " AND semesterID = :semesterID";
    }
    $stmt = $pdo->prepare($sql);
    $params = [':courseID' => $courseID];
    if ($semesterId) {
        $params[':semesterID'] = $semesterId;
    }
    $stmt->execute($params);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($result) {
        $registrationNumbers = array();
        foreach ($result as $row) {
            $registrationNumbers[] = $row["registrationNumber"];
        }

        $response['status'] = 'success';
        $response['data'] = $registrationNumbers;
    } else {
        $response['status'] = 'error';
        $response['message'] = 'No records found';
    }

    ob_start();
    include './studentTable.php';
    $tableHTML = ob_get_clean();

    $response['html'] = $tableHTML;
} else {
    $response['status'] = 'error';
    $response['message'] = 'Invalid or missing parameters';
}


header('Content-Type: application/json');
echo json_encode($response);
