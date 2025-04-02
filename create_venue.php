<?php
require_once 'config.php';
include 'navbar.php';

// Ensure user is logged in and has the correct role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 2])) {
    echo "<script>alert('Access Denied!'); window.location.href='index.php';</script>";
    exit();
}

// Define error variables
$venueNameError = $locationDetailsError = '';
$venueName = $locationDetails = '';
$createdBy = $_SESSION['user_id'];

// Predefined venue options
$validVenues = ['Lab 4', 'Lab 7', 'Lab 8', 'Lab 9', 'Lab 10', 'Lab 11'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $venueName = trim($_POST['venue_name']);
    $locationDetails = trim($_POST['location_details'] ?? '');

    $valid = true;

    // Validation for venue name
    if (empty($venueName)) {
        $venueNameError = 'Venue Name is required.';
        $valid = false;
    } elseif (!in_array($venueName, $validVenues)) {
        $venueNameError = 'Invalid Venue Name selected.';
        $valid = false;
    }

    if (empty($locationDetails)) {
        $locationDetailsError = 'Location Details are required.';
        $valid = false;
    }

    if ($valid) {
        try {
            // Insert venue into database
            $query = $conn->prepare(
                "INSERT INTO venues (venue_name, location_details, created_by) VALUES (?, ?, ?)"
            );
            $query->bind_param('ssi', $venueName, $locationDetails, $createdBy);
            $query->execute();

            echo "<script>alert('Venue created successfully!'); window.location.href='manage_venues.php';</script>";
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() === 1062) { // Error code 1062 is for duplicate entry
                echo "<script>alert('Venue already exists! Please choose a different venue.');</script>";
            } else {
                error_log('Error creating venue: ' . $e->getMessage());
                echo "<script>alert('An error occurred while creating the venue. Please try again later.');</script>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Venue</title>
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
    <h1>Create Venue</h1>
    <form action="" method="POST">
        <!-- Venue Information -->
        <div class="form-container basic-info-container">
            <h2>Venue Information</h2>
            <div class="form-group-row">
                <div class="form-group">
                    <label for="venue_name">Venue Name:</label>
                    <select id="venue_name" name="venue_name" required>
                        <option value="">Select a venue</option>
                        <?php foreach ($validVenues as $venue): ?>
                            <option value="<?php echo $venue; ?>" <?php echo ($venue === $venueName) ? 'selected' : ''; ?>>
                                <?php echo $venue; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="error-message"><?php echo $venueNameError; ?></span>
                </div>
                <div class="form-group">
                    <label for="location_details">Location Details:</label>
                    <textarea id="location_details" name="location_details" placeholder="Enter location details" required><?php echo htmlspecialchars($locationDetails); ?></textarea>
                    <span class="error-message"><?php echo $locationDetailsError; ?></span>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create</button>
            <a href="manage_venues.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
<?php include 'footer.php'; ?>
</body>
</html>