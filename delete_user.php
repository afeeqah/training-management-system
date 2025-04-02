<?php
session_start();
require_once 'config.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id'])) {
    echo "<script>alert('Unauthorized access. Please log in.'); window.location.href='index.php';</script>";
    exit();
}

// Get the logged-in user's details
$logged_in_user_id = $_SESSION['user_id'];
$logged_in_role_id = $_SESSION['role_id'];

// Ensure valid `user_id` and `role` are passed
if (!isset($_GET['id']) || empty($_GET['id']) || !isset($_GET['role']) || empty($_GET['role'])) {
    echo "<script>alert('Invalid request.'); window.location.href='dashboard.php';</script>";
    exit();
}

$user_id_to_delete = intval($_GET['id']);
$role = htmlspecialchars($_GET['role']);

// Fetch details of the user to be deleted
$query = $conn->prepare("SELECT * FROM users WHERE id = ?");
$query->bind_param("i", $user_id_to_delete);
$query->execute();
$result = $query->get_result();

if ($result->num_rows === 0) {
    echo "<script>alert('User not found.'); window.location.href='manage_users.php?role={$role}';</script>";
    exit();
}

$user_to_delete = $result->fetch_assoc();

// Role-based permissions
if ($logged_in_role_id == 2) { // Staff
    // Staff can only delete trainers or users they created
    if ($user_to_delete['role_id'] != 3 && $user_to_delete['created_by'] != $logged_in_user_id) {
        echo "<script>alert('Permission denied. Staff can only delete trainers or users they created.'); window.location.href='manage_users.php?role={$role}';</script>";
        exit();
    }
} elseif ($logged_in_role_id == 1) { // Admin
    // Admin cannot delete other admins
    if ($user_to_delete['role_id'] == 1) {
        echo "<script>alert('Permission denied. Admins cannot delete other admins.'); window.location.href='manage_users.php?role={$role}';</script>";
        exit();
    }
}

// Perform deletion
$conn->begin_transaction();

try {
    // Delete from `role_details` if applicable
    if ($user_to_delete['role_id'] == 2 || $user_to_delete['role_id'] == 3) {
        $roleDetailsQuery = $conn->prepare("DELETE FROM role_details WHERE user_id = ?");
        $roleDetailsQuery->bind_param("i", $user_id_to_delete);
        $roleDetailsQuery->execute();
    }

    // Delete from `users`
    $deleteUserQuery = $conn->prepare("DELETE FROM users WHERE id = ?");
    $deleteUserQuery->bind_param("i", $user_id_to_delete);
    $deleteUserQuery->execute();

    $conn->commit();

    echo "<script>alert('User deleted successfully.'); window.location.href='manage_users.php?role={$role}';</script>";
} catch (Exception $e) {
    $conn->rollback();
    echo "<script>alert('Error deleting user: {$e->getMessage()}'); window.location.href='manage_users.php?role={$role}';</script>";
}
?>
