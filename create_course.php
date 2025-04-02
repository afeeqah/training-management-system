<?php
require_once 'config.php';
include 'navbar.php';

// Ensure user is logged in and has the correct role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 2])) {
    echo "<script>alert('Access Denied!'); window.location.href='index.php';</script>";
    exit();
}

// Define error variables
$courseNameError = $descriptionError = $categoryError = $validFromError = $validToError = $durationError = '';
$courseName = $description = $category = $validFrom = $validTo = $duration = '';
$selectedTrainers = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $courseName = trim($_POST['course_name']);
    $description = trim($_POST['description']);
    $category = $_POST['category'] ?? '';
    $validFrom = $_POST['valid_from'] ?? '';
    $validTo = $_POST['valid_to'] ?? '';
    $duration = $_POST['duration'] ?? '';
    $selectedTrainers = $_POST['trainers'] ?? [];
    $createdBy = $_SESSION['user_id'];

    $valid = true;

    // Validation
    if (empty($courseName)) {
        $courseNameError = 'Course Name is required.';
        $valid = false;
    } else {
        $checkQuery = $conn->prepare("SELECT id FROM courses WHERE course_name = ?");
        $checkQuery->bind_param('s', $courseName);
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
            // Insert course into database
            $query = $conn->prepare("INSERT INTO courses (course_name, description, duration, category, valid_from, valid_to, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $query->bind_param('ssisssi', $courseName, $description, $duration, $category, $validFrom, $validTo, $createdBy);
            $query->execute();

            $courseId = $query->insert_id;

            // Assign trainers to the course if any are selected
            if (!empty($selectedTrainers)) {
                foreach ($selectedTrainers as $trainerId) {
                    $trainerQuery = $conn->prepare("INSERT INTO course_assignments (course_id, trainer_id, assigned_by, assigned_at) VALUES (?, ?, ?, NOW())");
                    $trainerQuery->bind_param('iii', $courseId, $trainerId, $createdBy);
                    $trainerQuery->execute();
                }
            }

            echo "<script>alert('Course created successfully!'); window.location.href='manage_courses.php';</script>";
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
    <title>Create Course</title>
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
    <h1>Create Course</h1>
    <form action="" method="POST">
        <!-- Basic Course Information -->
        <div class="form-container basic-info-container">
            <h2>Course Information</h2>
            <div class="form-group-row">
                <div class="form-group">
                    <label for="course_name">Course Name:</label>
                    <input type="text" id="course_name" name="course_name" placeholder="Enter course name" value="<?php echo htmlspecialchars($courseName); ?>" required>
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

        <!-- Course Description and Duration -->
        <div class="form-container role-info-container">
            <h2>Additional Information</h2>
            <div class="form-group">
                <label for="description">Description:</label>
                <textarea id="description" name="description" placeholder="Enter course description" required><?php echo htmlspecialchars($description); ?></textarea>
                <span class="error-message"><?php echo $descriptionError; ?></span>
            </div>
            <div class="form-group">
                <label for="duration">Duration (Days):</label>
                <input type="number" id="duration" name="duration" placeholder="Enter course duration in days" value="<?php echo htmlspecialchars($duration); ?>" required>
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
                            <input type="checkbox" name="trainers[]" value="<?php echo $trainer['id']; ?>">
                        </label>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create</button>
            <a href="manage_courses.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div><br><br>
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
