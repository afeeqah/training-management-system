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

// Ensure a valid `venue_id` is passed
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>alert('Invalid request.'); window.location.href='manage_venues.php';</script>";
    exit();
}

$venue_id_to_delete = intval($_GET['id']);

// Fetch details of the venue to be deleted
$query = $conn->prepare("SELECT * FROM venues WHERE id = ?");
$query->bind_param("i", $venue_id_to_delete);
$query->execute();
$result = $query->get_result();

if ($result->num_rows === 0) {
    echo "<script>alert('Venue not found.'); window.location.href='manage_venues.php';</script>";
    exit();
}

$venue_to_delete = $result->fetch_assoc();

// Role-based permissions
if ($logged_in_role_id == 2) { // Staff
    // Staff can only delete venues they created
    if ($venue_to_delete['created_by'] != $logged_in_user_id) {
        echo "<script>alert('Permission denied. Staff can only delete venues they created.'); window.location.href='manage_venues.php';</script>";
        exit();
    }
} elseif ($logged_in_role_id == 1) { // Admin
    // Admin has full access, no additional checks required
}

// Perform deletion
try {
    // Delete the venue
    $deleteVenueQuery = $conn->prepare("DELETE FROM venues WHERE id = ?");
    $deleteVenueQuery->bind_param("i", $venue_id_to_delete);
    $deleteVenueQuery->execute();

    echo "<script>alert('Venue deleted successfully.'); window.location.href='manage_venues.php';</script>";
} catch (Exception $e) {
    error_log('Error deleting venue: ' . $e->getMessage());
    echo "<script>alert('An error occurred while deleting the venue. Please try again later.'); window.location.href='manage_venues.php';</script>";
    }
?>
