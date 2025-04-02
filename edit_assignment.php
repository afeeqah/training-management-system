<?php
require_once 'config.php';
include 'navbar.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 2])) { // Only Admin and Staff allowed
    echo "<script>alert('Access Denied!'); window.location.href='index.php';</script>";
    exit();
}

// Get course name from the query parameter
if (!isset($_GET['course']) || empty($_GET['course'])) {
    echo "<script>alert('Invalid course specified.'); window.location.href='trainer_course_assignments.php';</script>";
    exit();
}

$courseName = $_GET['course'];

// Fetch the course ID and validate the course exists
$courseQuery = $conn->prepare("SELECT id FROM courses WHERE course_name = ?");
$courseQuery->bind_param('s', $courseName);
$courseQuery->execute();
$courseResult = $courseQuery->get_result();

if ($courseResult->num_rows === 0) {
    echo "<script>alert('Course not found.'); window.location.href='trainer_course_assignments.php';</script>";
    exit();
}

$course = $courseResult->fetch_assoc();
$courseId = $course['id'];

// Fetch assigned trainers with their names
$assignedTrainersQuery = $conn->prepare("
    SELECT t.id, t.username
    FROM course_assignments ca
    JOIN users t ON ca.trainer_id = t.id
    WHERE ca.course_id = ?
");
$assignedTrainersQuery->bind_param('i', $courseId);
$assignedTrainersQuery->execute();
$assignedTrainersResult = $assignedTrainersQuery->get_result();
$assignedTrainers = [];
while ($row = $assignedTrainersResult->fetch_assoc()) {
    $assignedTrainers[$row['id']] = $row['username'];
}

// Fetch all trainers
$allTrainersQuery = $conn->query("SELECT id, username FROM users WHERE role_id = 3");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedTrainerIds = isset($_POST['trainers']) ? $_POST['trainers'] : [];
    $assignedBy = $_SESSION['user_id'];

    // Fetch the role name of the user performing the assignment
    $roleQuery = $conn->prepare("SELECT role_name FROM roles WHERE id = ?");
    $roleQuery->bind_param('i', $_SESSION['role_id']);
    $roleQuery->execute();
    $roleResult = $roleQuery->get_result();
    $assignedByRole = $roleResult->fetch_assoc()['role_name'];

    // Begin transaction
    $conn->begin_transaction();

    try {
        // Delete existing assignments for the course
        $deleteQuery = $conn->prepare("DELETE FROM course_assignments WHERE course_id = ?");
        $deleteQuery->bind_param('i', $courseId);
        $deleteQuery->execute();

        // Insert new assignments
        $insertQuery = $conn->prepare("
            INSERT INTO course_assignments (course_id, trainer_id, assigned_by, assigned_by_role, trainer_name, course_name, assigned_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        foreach ($selectedTrainerIds as $trainerId) {
            // Fetch the trainer's name
            $trainerNameQuery = $conn->prepare("SELECT username FROM users WHERE id = ?");
            $trainerNameQuery->bind_param('i', $trainerId);
            $trainerNameQuery->execute();
            $trainerNameResult = $trainerNameQuery->get_result();
            $trainerName = $trainerNameResult->fetch_assoc()['username'];

            $insertQuery->bind_param(
                'iiisss',
                $courseId,
                $trainerId,
                $assignedBy,
                $assignedByRole,
                $trainerName,
                $courseName
            );
            $insertQuery->execute();
        }

        // Commit transaction
        $conn->commit();
        echo "<script>alert('Assignments updated successfully!'); window.location.href='trainer_course_assignments.php';</script>";
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Failed to update assignments: " . $e->getMessage() . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Trainer-Course Assignment</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .error-message {
            color: red;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
<div class="form-container" style="max-width: 900px;">
    <h1>Edit Assignment for "<?php echo htmlspecialchars($courseName); ?>"</h1>
    <form method="POST" action="edit_assignment.php?course=<?php echo urlencode($courseName); ?>">
        <div class="form-container role-info-container">
            <h2>Assign Trainers</h2>
            <div class="trainer-container">
                <input type="text" id="search-trainers" placeholder="Search trainers..." onkeyup="filterTrainers()">
                <div class="trainer-list" id="trainer-list">
                    <?php while ($trainer = $allTrainersQuery->fetch_assoc()): ?>
                        <label class="trainer-item">
                            <span><?php echo htmlspecialchars($trainer['username']); ?></span>    
                            <input type="checkbox" name="trainers[]" value="<?php echo $trainer['id']; ?>" <?php echo in_array($trainer['id'], array_keys($assignedTrainers)) ? 'checked' : ''; ?>>
                        </label>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="trainer_course_assignments.php" class="btn btn-secondary">Cancel</a>
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
