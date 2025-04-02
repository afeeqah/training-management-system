<?php
require_once 'config.php';

// Check if the user ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: manage_users.php?error=invalid_user');
    exit();
}

$userId = intval($_GET['id']);
$role = $_GET['role'] ?? null; // Get the role from the query string

// Prepare the deactivate query
$deactivateQuery = $conn->prepare("UPDATE users SET status = 0 WHERE id = ?");
$deactivateQuery->bind_param('i', $userId);

if ($deactivateQuery->execute()) {
    header("Location: manage_users.php?role=$role&success=deactivated");
} else {
    header("Location: manage_users.php?role=$role&error=deactivate_failed");
}
exit();
