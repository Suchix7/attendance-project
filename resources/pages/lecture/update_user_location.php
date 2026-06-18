<?php
header('Content-Type: application/json');
session_start();

if (isset($_POST['user_lat']) && isset($_POST['user_lng'])) {
    $latitude = $_POST['user_lat'];
    $longitude = $_POST['user_lng'];

    // Here you can save the coordinates to a session or database if needed
    $_SESSION['user_latitude'] = $latitude;
    $_SESSION['user_longitude'] = $longitude;

    echo json_encode([
        'success' => true,
        'message' => 'Location updated successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Coordinates not provided'
    ]);
}
?>