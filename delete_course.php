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

// Ensure a valid `course_id` is passed
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>alert('Invalid request.'); window.location.href='manage_courses.php';</script>";
    exit();
}

$course_id_to_delete = intval($_GET['id']);

// Fetch details of the course to be deleted
$query = $conn->prepare("SELECT * FROM courses WHERE id = ?");
$query->bind_param("i", $course_id_to_delete);
$query->execute();
$result = $query->get_result();

if ($result->num_rows === 0) {
    echo "<script>alert('Course not found.'); window.location.href='manage_courses.php';</script>";
    exit();
}

$course_to_delete = $result->fetch_assoc();

// Role-based permissions
if ($logged_in_role_id == 2) { // Staff
    // Staff can only delete courses they created
    if ($course_to_delete['created_by'] != $logged_in_user_id) {
        echo "<script>alert('Permission denied. Staff can only delete courses they created.'); window.location.href='manage_courses.php';</script>";
        exit();
    }
} elseif ($logged_in_role_id == 1) { // Admin
    // Admin has full access, no additional checks required
}

// Perform deletion
$conn->begin_transaction();

try {
    // Delete related records from `course_assignments`
    $deleteAssignmentsQuery = $conn->prepare("DELETE FROM course_assignments WHERE course_id = ?");
    $deleteAssignmentsQuery->bind_param("i", $course_id_to_delete);
    $deleteAssignmentsQuery->execute();

    // Delete the course
    $deleteCourseQuery = $conn->prepare("DELETE FROM courses WHERE id = ?");
    $deleteCourseQuery->bind_param("i", $course_id_to_delete);
    $deleteCourseQuery->execute();

    $conn->commit();

    echo "<script>alert('Course deleted successfully.'); window.location.href='manage_courses.php';</script>";
} catch (Exception $e) {
    $conn->rollback();
    echo "<script>alert('Error deleting course: {$e->getMessage()}'); window.location.href='manage_courses.php';</script>";
}
?>
