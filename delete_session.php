<?php
require_once 'config.php';
session_start();

// Ensure user is logged in and has the correct role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 2])) {
    echo "<script>alert('Unauthorized access. Please log in.'); window.location.href='index.php';</script>";
    exit();
}

// Check if the `id` parameter is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>alert('Invalid request.'); window.location.href='manage_sessions.php';</script>";
    exit();
}

$sessionId = intval($_GET['id']);
$loggedInUserId = $_SESSION['user_id'];
$loggedInRoleId = $_SESSION['role_id'];

// Check if the session exists
$query = $conn->prepare("SELECT * FROM training_sessions WHERE id = ?");
$query->bind_param("i", $sessionId);
$query->execute();
$result = $query->get_result();

if ($result->num_rows === 0) {
    echo "<script>alert('Session not found.'); window.location.href='manage_sessions.php';</script>";
    exit();
}

$session = $result->fetch_assoc();

// Role-based permissions
if ($loggedInRoleId == 2) { // Staff
    // Staff can only delete sessions they created
    if ($session['created_by'] != $loggedInUserId) {
        echo "<script>alert('Permission denied. Staff can only delete sessions they created.'); window.location.href='manage_sessions.php';</script>";
        exit();
    }
}

// Perform deletion
try {
    $deleteQuery = $conn->prepare("DELETE FROM training_sessions WHERE id = ?");
    $deleteQuery->bind_param("i", $sessionId);
    $deleteQuery->execute();

    echo "<script>alert('Session deleted successfully.'); window.location.href='manage_sessions.php';</script>";
} catch (Exception $e) {
    echo "<script>alert('Error deleting session: {$e->getMessage()}'); window.location.href='manage_sessions.php';</script>";
}
?>
