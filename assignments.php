<?php
require_once 'config.php';
include 'navbar.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 2])) {
    echo "<script>alert('Access Denied!'); window.location.href='index.php';</script>";
    exit();
}

// Handle form submission
$message = "";
$showModal = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedCourseIds = isset($_POST['courses']) ? $_POST['courses'] : [];
    $selectedTrainerIds = isset($_POST['trainers']) ? $_POST['trainers'] : [];
    $assignedBy = $_SESSION['user_id'];

    if (!empty($selectedCourseIds) && !empty($selectedTrainerIds)) {
        // Begin transaction
        $conn->begin_transaction();
        try {
            foreach ($selectedCourseIds as $courseId) {
                foreach ($selectedTrainerIds as $trainerId) {
                    // Fetch course name
                    $courseQuery = $conn->prepare("SELECT course_name FROM courses WHERE id = ?");
                    $courseQuery->bind_param('i', $courseId);
                    $courseQuery->execute();
                    $courseResult = $courseQuery->get_result();
                    $courseName = $courseResult->fetch_assoc()['course_name'];

                    // Fetch trainer username
                    $trainerQuery = $conn->prepare("SELECT username FROM users WHERE id = ?");
                    $trainerQuery->bind_param('i', $trainerId);
                    $trainerQuery->execute();
                    $trainerResult = $trainerQuery->get_result();
                    $trainerName = $trainerResult->fetch_assoc()['username'];

                    // Fetch assigned by role
                    $assignedByRoleQuery = $conn->prepare("SELECT role_name FROM roles WHERE id = (SELECT role_id FROM users WHERE id = ?)");
                    $assignedByRoleQuery->bind_param('i', $assignedBy);
                    $assignedByRoleQuery->execute();
                    $assignedByRoleResult = $assignedByRoleQuery->get_result();
                    $assignedByRole = $assignedByRoleResult->fetch_assoc()['role_name'];

                    // Check if the assignment already exists
                    $checkStmt = $conn->prepare(
                        "SELECT ca.course_id, c.course_name, ca.trainer_id, u.username 
                         FROM course_assignments ca
                         JOIN courses c ON ca.course_id = c.id
                         JOIN users u ON ca.trainer_id = u.id
                         WHERE ca.course_id = ? AND ca.trainer_id = ?"
                    );
                    $checkStmt->bind_param('ii', $courseId, $trainerId);
                    $checkStmt->execute();
                    $existingAssignment = $checkStmt->get_result();

                    if ($existingAssignment->num_rows > 0) {
                        $row = $existingAssignment->fetch_assoc();
                        $message = "The assignment for Trainer <strong><em>'{$row['username']}'</em></strong> and Course <strong><em>'{$row['course_name']}'</em></strong> already exists.";
                        $showModal = true;
                        throw new Exception($message);
                    }

                    // Insert new assignment
                    $stmt = $conn->prepare(
                        "INSERT INTO course_assignments (course_id, trainer_id, trainer_name, course_name, assigned_by, assigned_by_role, assigned_at) 
                         VALUES (?, ?, ?, ?, ?, ?, NOW())"
                    );
                    $stmt->bind_param('iissis', $courseId, $trainerId, $trainerName, $courseName, $assignedBy, $assignedByRole);
                    $stmt->execute();
                }
            }
            // Commit transaction
            $conn->commit();
            echo "<script>alert('Trainers successfully assigned to the selected courses!'); window.location.href='trainer_course_assignments.php';</script>";
        } catch (Exception $e) {
            $conn->rollback();
            $message = $e->getMessage();
            $showModal = true;
        }
    } else {
        $message = "Please select at least one course and one trainer.";
        $showModal = true;
    }
}

// Fetch all courses and trainers
$courses = $conn->query("SELECT id, course_name FROM courses");
$trainers = $conn->query("SELECT id, username FROM users WHERE role_id = 3");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Trainer-Course</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .error-message {
            color: red;
            font-size: 0.9em;
        }
        .modal {
            display: none;
        }
        .modal.show {
            display: block;
        }
    </style>
</head>
<body>
<div class="form-container" style="max-width: 900px;">
    <h1>Assign Trainer-Course</h1>
    <form method="POST" action="assignments.php">
        <!-- Courses Section -->
        <div class="form-container basic-info-container">
            <h2>Select Courses</h2>
            <div class="trainer-container">
                <input type="text" id="search-courses" placeholder="Search courses..." onkeyup="filterCourses()">
                <div class="trainer-list" id="course-list">
                    <?php while ($course = $courses->fetch_assoc()): ?>
                        <label class="trainer-item course-item">
                            <span><?php echo htmlspecialchars($course['course_name']); ?></span>
                            <input type="checkbox" name="courses[]" value="<?php echo $course['id']; ?>">
                        </label>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <!-- Trainers Section -->
        <div class="form-container role-info-container">
            <h2>Select Trainers</h2>
            <div class="trainer-container">
                <input type="text" id="search-trainers" placeholder="Search trainers..." onkeyup="filterTrainers()">
                <div class="trainer-list" id="trainer-list">
                    <?php while ($trainer = $trainers->fetch_assoc()): ?>
                        <label class="trainer-item trainer-item-class">
                            <span><?php echo htmlspecialchars($trainer['username']); ?></span>
                            <input type="checkbox" name="trainers[]" value="<?php echo $trainer['id']; ?>">
                        </label>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Submit</button>
            <a href="trainer_course_assignments.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<!-- Modal Popup -->
<div id="errorModal" class="modal <?php echo $showModal ? 'show' : ''; ?>">
    <div class="modal-content">
        <h2>Error</h2>
        <p><?php echo htmlspecialchars_decode($message); ?></p>
        <button onclick="closeModal()">OK</button>
    </div>
</div>

<script>
    function closeModal() {
        document.getElementById('errorModal').classList.remove('show');
    }

    function filterCourses() {
        const input = document.getElementById("search-courses").value.toLowerCase();
        const courses = document.getElementsByClassName("course-item");
        for (let course of courses) {
            const text = course.textContent || course.innerText;
            course.style.display = text.toLowerCase().includes(input) ? "" : "none";
        }
    }

    function filterTrainers() {
        const input = document.getElementById("search-trainers").value.toLowerCase();
        const trainers = document.getElementsByClassName("trainer-item-class");
        for (let trainer of trainers) {
            const text = trainer.textContent || trainer.innerText;
            trainer.style.display = text.toLowerCase().includes(input) ? "" : "none";
        }
    }
</script>
<?php include 'footer.php'; ?>
</body>
</html>
