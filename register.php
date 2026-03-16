<?php
require 'database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $firstName = $_POST['firstName'] ?? '';
    $lastName = $_POST['lastName'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = 'Resident'; // Default role for user page

    if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
        exit;
    }

    // Hash the password for security
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO users (firstName, lastName, email, role, password) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$firstName, $lastName, $email, $role, $hashedPassword]);
        
        echo json_encode(['status' => 'success', 'message' => 'Registration successful.']);
    } catch (\PDOException $e) {
        // Handle duplicate email error
        if ($e->getCode() == 23000) {
            echo json_encode(['status' => 'error', 'message' => 'Email is already registered.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }
}
?>