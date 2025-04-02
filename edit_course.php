<?php
// Include database and navbar
require_once 'config.php';
include 'navbar.php';

// Ensure user is logged in and has permission
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 2])) {
    echo "<script>alert('Unauthorized access. Please log in.'); window.location.href='index.php';</script>";
    exit();
}

$loggedInUserId = $_SESSION['user_id'];
$loggedInRoleId = $_SESSION['role_id'];

// Ensure a valid `course_id` is passed
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>alert('Invalid course ID.'); window.location.href='manage_courses.php';</script>";
    exit();
}

$courseIdToEdit = intval($_GET['id']);

// Fetch course data
$query = $conn->prepare("SELECT * FROM courses WHERE id = ?");
$query->bind_param('i', $courseIdToEdit);
$query->execute();
$courseResult = $query->get_result();
$courseData = $courseResult->fetch_assoc();

if (!$courseData) {
    echo "<script>alert('Course not found.'); window.location.href='manage_courses.php';</script>";
    exit();
}

// Role-based permissions
if ($loggedInRoleId == 2 && $courseData['created_by'] != $loggedInUserId) {
    echo "<script>alert('Permission denied. Staff can only edit courses they created.'); window.location.href='manage_courses.php';</script>";
    exit();
}

// Define error variables
$courseNameError = $descriptionError = $categoryError = $validFromError = $validToError = $durationError = '';
$courseName = $courseData['course_name'];
$description = $courseData['description'];
$category = $courseData['category'];
$validFrom = $courseData['valid_from'];
$validTo = $courseData['valid_to'];
$duration = $courseData['duration'];

// Fetch assigned trainers
$assignedTrainers = [];
$trainerQuery = $conn->prepare("SELECT trainer_id FROM course_assignments WHERE course_id = ?");
$trainerQuery->bind_param('i', $courseIdToEdit);
$trainerQuery->execute();
$trainerResult = $trainerQuery->get_result();
while ($row = $trainerResult->fetch_assoc()) {
    $assignedTrainers[] = $row['trainer_id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $courseName = trim($_POST['course_name']);
    $description = trim($_POST['description']);
    $category = $_POST['category'] ?? '';
    $validFrom = $_POST['valid_from'] ?? '';
    $validTo = $_POST['valid_to'] ?? '';
    $duration = $_POST['duration'] ?? '';
    $selectedTrainers = $_POST['trainers'] ?? [];

    $valid = true;

    // Validation
    if (empty($courseName)) {
        $courseNameError = 'Course Name is required.';
        $valid = false;
    } elseif ($courseName !== $courseData['course_name']) {
        $checkQuery = $conn->prepare("SELECT id FROM courses WHERE course_name = ? AND id != ?");
        $checkQuery->bind_param('si', $courseName, $courseIdToEdit);
        $checkQuery->execute();
        if ($checkQuery->get_result()->num_rows > 0) {
            $courseNameError = 'Course Name already exists.';
            $valid = false;
        }
    }

    if (empty($description)) {
        $descriptionError = 'Description is required.';
        $valid = false;
    }

    if (empty($category)) {
        $categoryError = 'Category is required.';
        $valid = false;
    }

    if (empty($validFrom)) {
        $validFromError = 'Valid From date is required.';
        $valid = false;
    }

    if (empty($validTo)) {
        $validToError = 'Valid To date is required.';
        $valid = false;
    } elseif ($validFrom > $validTo) {
        $validToError = 'Valid To date must be after Valid From date.';
        $valid = false;
    }

    if (empty($duration)) {
        $durationError = 'Duration is required.';
        $valid = false;
    } elseif (!is_numeric($duration) || $duration <= 0) {
        $durationError = 'Duration must be a positive number.';
        $valid = false;
    }

    if ($valid) {
        try {
            // Update course details
            $updateQuery = $conn->prepare("UPDATE courses SET course_name = ?, description = ?, duration = ?, category = ?, valid_from = ?, valid_to = ? WHERE id = ?");
            $updateQuery->bind_param('ssisssi', $courseName, $description, $duration, $category, $validFrom, $validTo, $courseIdToEdit);
            $updateQuery->execute();

            // Update trainers assignment
            $conn->query("DELETE FROM course_assignments WHERE course_id = $courseIdToEdit");
            if (!empty($selectedTrainers)) {
                foreach ($selectedTrainers as $trainerId) {
                    $trainerQuery = $conn->prepare("INSERT INTO course_assignments (course_id, trainer_id, assigned_by, assigned_at) VALUES (?, ?, ?, NOW())");
                    $trainerQuery->bind_param('iii', $courseIdToEdit, $trainerId, $loggedInUserId);
                    $trainerQuery->execute();
                }
            }

            echo "<script>alert('Course updated successfully!'); window.location.href='manage_courses.php';</script>";
        } catch (mysqli_sql_exception $e) {
            echo "<script>alert('Error: {$e->getMessage()}');</script>";
        }
    }
}

// Fetch trainers for the dropdown
$trainersQuery = $conn->query("SELECT id, username FROM users WHERE role_id = 3");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Course</title>
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
    <h1>Edit Course</h1>
    <form action="" method="POST">
        <!-- Basic Course Information -->
        <div class="form-container basic-info-container">
            <h2>Course Information</h2>
            <div class="form-group-row">
                <div class="form-group">
                    <label for="course_name">Course Name:</label>
                    <input type="text" id="course_name" name="course_name" value="<?php echo htmlspecialchars($courseName); ?>" required>
                    <span class="error-message"><?php echo $courseNameError; ?></span>
                </div>
                <div class="form-group">
                    <label for="category">Category:</label>
                    <select id="category" name="category" required>
                        <option value="">Select Category</option>
                        <option value="IT" <?php echo $category === 'IT' ? 'selected' : ''; ?>>IT</option>
                        <option value="Non-IT" <?php echo $category === 'Non-IT' ? 'selected' : ''; ?>>Non-IT</option>
                        <option value="Safety & Health" <?php echo $category === 'Safety & Health' ? 'selected' : ''; ?>>Safety & Health</option>
                    </select>
                    <span class="error-message"><?php echo $categoryError; ?></span>
                </div>
            </div>
            <div class="form-group-row">
                <div class="form-group">
                    <label for="valid_from">Valid From:</label>
                    <input type="date" id="valid_from" name="valid_from" value="<?php echo htmlspecialchars($validFrom); ?>" required>
                    <span class="error-message"><?php echo $validFromError; ?></span>
                </div>
                <div class="form-group">
                    <label for="valid_to">Valid To:</label>
                    <input type="date" id="valid_to" name="valid_to" value="<?php echo htmlspecialchars($validTo); ?>" required>
                    <span class="error-message"><?php echo $validToError; ?></span>
                </div>
            </div>
        </div>

        <!-- Additional Information -->
        <div class="form-container role-info-container">
            <h2>Additional Information</h2>
            <div class="form-group">
                <label for="description">Description:</label>
                <textarea id="description" name="description" required><?php echo htmlspecialchars($description); ?></textarea>
                <span class="error-message"><?php echo $descriptionError; ?></span>
            </div>
            <div class="form-group">
                <label for="duration">Duration (Days):</label>
                <input type="number" id="duration" name="duration" value="<?php echo htmlspecialchars($duration); ?>" required>
                <span class="error-message"><?php echo $durationError; ?></span>
            </div>
        </div>

        <!-- Assign Trainers -->
        <div class="form-container assign-role-info-container">
            <h2>Assign Trainers (Optional)</h2>
            <div class="trainer-container">
                <input type="text" id="search-trainers" placeholder="Search trainers..." onkeyup="filterTrainers()">
                <div class="trainer-list" id="trainer-list">
                    <?php while ($trainer = $trainersQuery->fetch_assoc()): ?>
                        <label class="trainer-item">
                            <span><?php echo htmlspecialchars($trainer['username']); ?></span>
                            <input type="checkbox" name="trainers[]" value="<?php echo $trainer['id']; ?>" <?php echo in_array($trainer['id'], $assignedTrainers) ? 'checked' : ''; ?>>
                        </label>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="manage_courses.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
<script>
function filterTrainers() {
    const input = document.getElementById("search-trainers").value.toLowerCase();
    const trainers = document.getElementsByClassName("trainer-item");
    for (let trainer of trainers) {
        const text = trainer.textContent || trainer.innerText;
        trainer.style.display = text.toLowerCase().includes(input) ? "" : "none";
    }
}
</script>
<?php include 'footer.php'; ?>
</body>
</html>
