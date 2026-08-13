<?php
function user()
{
    if (isset($_SESSION['user'])) {
        return (object) $_SESSION['user'];
    }
    return null;
}

function getFacultyNames()
{
    global $pdo;
    $sql = "SELECT * FROM tblfaculty";
    $stmt = $pdo->query($sql);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $facultyNames = array();
    if ($result) {
        foreach ($result as $row) {
            $facultyNames[] = $row;
        }
    }

    return $facultyNames;
}
function getLectureNames()
{
    global $pdo;
    $sql = "SELECT Id, firstName, lastName FROM tbllecture";
    $stmt = $pdo->query($sql);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $lectureNames = array();
    if ($result) {
        foreach ($result as $row) {
            $lectureNames[] = $row;
        }
    }

    return $lectureNames;
}
function getCourseNames()
{
    global $pdo;
    $sql = "SELECT * FROM tblcourse";
    $stmt = $pdo->query($sql);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $courseNames = array();
    if ($result) {
        foreach ($result as $row) {
            $courseNames[] = $row;
        }
    }

    return $courseNames;
}
function getVenueNames()
{
    $sql = "SELECT className FROM tblvenue";
    $result = fetch($sql);

    $venueNames = array();
    if ($result) {
        foreach ($result as $row) {
            $venueNames[] = $row;
        }
    }

    return $venueNames;
}
function getUnitNames()
{
    $sql = "SELECT unitCode,name FROM tblunit";
    $result = fetch($sql);

    $unitNames = array();
    if ($result) {
        foreach ($result as $row) {
            $unitNames[] = $row;
        }
    }

    return $unitNames;
}

function showMessage(): void
{
    if (!isset($_SESSION['message'])) {
        return;
    }

    $rawMessage = $_SESSION['message'];
    unset($_SESSION['message']);

    // Classify the message so the popup can style itself and pick a title.
    // Error messages are stored as "Error: <detail>"; anything else is a success.
    $isError = stripos($rawMessage, 'Error:') === 0;
    $isDuplicateFace = stripos($rawMessage, 'already registered') !== false;

    // Clean display text (drop the leading "Error:" prefix for a nicer headline).
    $text = preg_replace('/^\s*Error:\s*/i', '', $rawMessage);
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    if ($isDuplicateFace) {
        $variant = 'error';
        $icon    = 'fa-triangle-exclamation';
        $title   = 'Duplicate Face Detected';
    } elseif ($isError) {
        $variant = 'error';
        $icon    = 'fa-circle-exclamation';
        $title   = "Couldn't Save";
    } else {
        $variant = 'success';
        $icon    = 'fa-circle-check';
        $title   = 'Success';
    }

    // Every outcome is shown as a large centered popup so it can't be missed
    // (the old small bottom-right toast was easy to overlook after a save).
    echo "
    <div id='msgOverlay' class='dupFaceOverlay'>
        <div class='dupFacePopup {$variant}' role='alertdialog' aria-modal='true' aria-labelledby='msgTitle'>
            <div class='dupFaceIcon'><i class='fa-solid {$icon}'></i></div>
            <h2 id='msgTitle' class='dupFaceTitle'>{$title}</h2>
            <p class='dupFaceText'>{$text}</p>
            <button type='button' class='dupFaceBtn' onclick=\"var o=document.getElementById('msgOverlay'); if (o) o.remove();\">OK</button>
        </div>
    </div>";
}


function total_rows($tablename)
{
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM {$tablename}");
    $total_rows = $stmt->rowCount();
    echo $total_rows;
}

function fetch($sql)
{
    global $pdo;
    $stmt = $pdo->query($sql);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $result;
}


function fetchStudentRecordsFromDatabase($courseCode, $unitCode)
{
    $studentRows = array();

    $query = "SELECT * FROM tblattendance WHERE course = '$courseCode' AND unit = '$unitCode'";
    $result = fetch($query);

    if ($result) {
        foreach ($result as $row) {
            $studentRows[] = $row;
        }
    }

    return $studentRows;
}

function js_asset($links = [])
{
    if ($links) {
        foreach ($links as $link) {
            echo "<script src='resources/assets/javascript/{$link}.js'>
        </script>";
        }
    }
}
