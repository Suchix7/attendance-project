<?php
header('Content-Type: application/json');

// Check if image was uploaded
if (!isset($_FILES['image']) || !isset($_POST['faceNumber'])) {
    echo json_encode(['success' => false, 'message' => 'No image or face number provided']);
    exit;
}

// Create faces directory if it doesn't exist
$facesDir = 'faces';
if (!file_exists($facesDir)) {
    mkdir($facesDir, 0777, true);
}

// Create a unique directory for this session
$sessionDir = $facesDir . '/temp_' . session_id();
if (!file_exists($sessionDir)) {
    mkdir($sessionDir, 0777, true);
}

// Save the uploaded image
$uploadedFile = $_FILES['image']['tmp_name'];
$faceNumber = $_POST['faceNumber'];
$imagePath = $sessionDir . '/face_' . $faceNumber . '.jpg';

if (move_uploaded_file($uploadedFile, $imagePath)) {
    echo json_encode([
        'success' => true,
        'facePath' => $imagePath
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save face image'
    ]);
}