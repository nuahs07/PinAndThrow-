<?php
session_start();
require 'database.php';
header('Content-Type: application/json');

$resident_ID = $_SESSION['user_ID'] ?? $_GET['resident_ID'] ?? null;

if (!$resident_ID) {
    echo json_encode(['status' => 'error', 'message' => 'User not authenticated.']);
    exit;
}

try {
    // Join Reports, Categories, and Locations to get the full picture
    $query = "
        SELECT 
            r.report_ID, r.description, r.imageUrl, r.status, r.timestamp,
            c.categoryName,
            l.latitude, l.longitude, l.locationName
        FROM reports r
        LEFT JOIN categories c ON r.category_id = c.category_id
        LEFT JOIN locations l ON r.report_ID = l.report_ID
        WHERE r.resident_ID = ?
        ORDER BY r.timestamp DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$resident_ID]);
    $reports = $stmt->fetchAll();

    echo json_encode(['status' => 'success', 'data' => $reports]);
    
} catch (\PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>