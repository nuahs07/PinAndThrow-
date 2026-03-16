<?php
session_start();
require 'database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ensure the user is logged in (or pass resident_ID via POST if testing via Postman)
    $resident_ID = $_SESSION['user_ID'] ?? $_POST['resident_ID'] ?? null;
    
    if (!$resident_ID) {
        echo json_encode(['status' => 'error', 'message' => 'User not authenticated.']);
        exit;
    }

    $category_id = $_POST['category_id'] ?? null;
    $description = $_POST['description'] ?? '';
    $imageUrl = $_POST['imageUrl'] ?? ''; // Assuming image upload is handled separately and URL is passed
    
    // Location details
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;
    $locationName = $_POST['locationName'] ?? '';

    try {
        $pdo->beginTransaction();

        // 1. Insert into reports table
        $stmtReport = $pdo->prepare("INSERT INTO reports (resident_ID, category_id, description, imageUrl, status) VALUES (?, ?, ?, ?, 'Pending')");
        $stmtReport->execute([$resident_ID, $category_id, $description, $imageUrl]);
        
        $report_ID = $pdo->lastInsertId();

        // 2. Insert into locations table
        $stmtLocation = $pdo->prepare("INSERT INTO locations (report_ID, latitude, longitude, locationName) VALUES (?, ?, ?, ?)");
        $stmtLocation->execute([$report_ID, $latitude, $longitude, $locationName]);

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Report submitted successfully.', 'report_ID' => $report_ID]);
        
    } catch (\PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Failed to submit report: ' . $e->getMessage()]);
    }
}
?>