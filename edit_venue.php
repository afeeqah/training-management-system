<?php
require_once 'config.php';
include 'navbar.php';

// Ensure user is logged in and has the correct role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 2])) {
    echo "<script>alert('Access Denied!'); window.location.href='index.php';</script>";
    exit();
}

// Check if the venue ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<script>alert('Invalid Venue ID!'); window.location.href='manage_venues.php';</script>";
    exit();
}

$venueId = intval($_GET['id']);

// Fetch venue details
$query = $conn->prepare("SELECT venue_name, location_details FROM venues WHERE id = ?");
$query->bind_param('i', $venueId);
$query->execute();
$result = $query->get_result();
$venue = $result->fetch_assoc();

if (!$venue) {
    echo "<script>alert('Venue not found!'); window.location.href='manage_venues.php';</script>";
    exit();
}

// Define error variables
$venueNameError = $locationDetailsError = '';
$venueName = $venue['venue_name'];
$locationDetails = $venue['location_details'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $venueName = trim($_POST['venue_name']);
    $locationDetails = trim($_POST['location_details'] ?? '');

    $valid = true;

    // Validation
    if (empty($venueName)) {
        $venueNameError = 'Venue Name is required.';
        $valid = false;
    } else {
        $checkQuery = $conn->prepare("SELECT id FROM venues WHERE venue_name = ? AND id != ?");
        $checkQuery->bind_param('si', $venueName, $venueId);
        $checkQuery->execute();
        if ($checkQuery->get_result()->num_rows > 0) {
            $venueNameError = 'Venue Name already exists.';
            $valid = false;
        }
    }

    if (empty($locationDetails)) {
        $locationDetailsError = 'Location Details are required.';
        $valid = false;
    }

    if ($valid) {
        try {
            // Update venue in database
            $updateQuery = $conn->prepare(
                "UPDATE venues SET venue_name = ?, location_details = ? WHERE id = ?"
            );
            $updateQuery->bind_param('ssi', $venueName, $locationDetails, $venueId);
            $updateQuery->execute();

            echo "<script>alert('Venue updated successfully!'); window.location.href='manage_venues.php';</script>";
        } catch (mysqli_sql_exception $e) {
            error_log('Error updating venue: ' . $e->getMessage());
            echo "<script>alert('An error occurred while updating the venue. Please try again later.');</script>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Venue</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .error-message {
            color: red;
            font-size: 0.9em;
            margin-top: 5px;
        }
    </style>
</head>
<body>
<div class="form-container" style="max-width: 900px;">
    <h1>Edit Venue</h1>
    <form action="" method="POST">
        <!-- Venue Information -->
        <div class="form-container basic-info-container">
            <h2>Venue Information</h2>
            <div class="form-group-row">
                <div class="form-group">
                    <label for="venue_name">Venue Name:</label>
                    <select id="venue_name" name="venue_name" required>
                        <option value="">Select a venue</option>
                        <?php
                        $validVenues = ['Lab 4', 'Lab 7', 'Lab 8', 'Lab 9', 'Lab 10', 'Lab 11'];
                        foreach ($validVenues as $venue) {
                            echo '<option value="' . htmlspecialchars($venue) . '"' . ($venue === $venueName ? ' selected' : '') . '>' . htmlspecialchars($venue) . '</option>';
                        }
                        ?>
                    </select>
                    <span class="error-message"><?php echo $venueNameError; ?></span>
                </div>
            </div>
            <div class="form-group">
                <label for="location_details">Location Details:</label>
                <textarea id="location_details" name="location_details" placeholder="Enter location details" required><?php echo htmlspecialchars($locationDetails); ?></textarea>
                <span class="error-message"><?php echo $locationDetailsError; ?></span>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Update</button>
            <a href="manage_venues.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
<?php include 'footer.php'; ?>
</body>
</html>
