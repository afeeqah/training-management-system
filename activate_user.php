<?php
require_once 'config.php';

// Check if the user ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: manage_users.php?error=invalid_user');
    exit();
}

$userId = intval($_GET['id']);
$role = $_GET['role'] ?? null; // Get the role from the query string

// Prepare the activate query
$activateQuery = $conn->prepare("UPDATE users SET status = 1 WHERE id = ?");
$activateQuery->bind_param('i', $userId);

if ($activateQuery->execute()) {
    header("Location: manage_users.php?role=$role&success=activated");
} else {
    header("Location: manage_users.php?role=$role&error=activate_failed");
}
exit();
