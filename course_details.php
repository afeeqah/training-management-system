<?php
require_once 'config.php';
include 'navbar.php';

// Check if the course ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>alert('Invalid course ID.'); window.location.href='manage_courses.php';</script>";
    exit();
}

$courseId = intval($_GET['id']);

// Fetch course details
$courseQuery = $conn->prepare("
    SELECT 
        c.*, 
        u.username AS created_by_username 
    FROM courses c
    LEFT JOIN users u ON c.created_by = u.id
    WHERE c.id = ?
");
$courseQuery->bind_param('i', $courseId);
$courseQuery->execute();
$courseResult = $courseQuery->get_result();

if ($courseResult->num_rows === 0) {
    echo "<script>alert('Course not found.'); window.location.href='manage_courses.php';</script>";
    exit();
}

$course = $courseResult->fetch_assoc();

// Fetch trainers assigned to this course
$trainersQuery = $conn->prepare("
    SELECT 
        ca.trainer_id,
        u.username AS trainer_username,
        u.email, 
        u.phone_number 
    FROM course_assignments ca
    JOIN users u ON ca.trainer_id = u.id
    WHERE ca.course_id = ?
");
$trainersQuery->bind_param('i', $courseId);
$trainersQuery->execute();
$assignedTrainers = $trainersQuery->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Details</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div id="course-details-container">
    <h2 class="course-details-title">Course Details</h2>

    <!-- Course Information Section -->
    <div class="course-details-flex">
        <!-- Left Block -->
        <div class="course-left-block">
            <div class="course-info-item">
                <span class="detail-label">Course Name:</span> <?php echo htmlspecialchars($course['course_name']); ?>
            </div>
            <div class="course-info-item">
                <span class="detail-label">Duration (days):</span> <?php echo htmlspecialchars($course['duration']); ?>
            </div>
            <div class="course-info-item">
                <span class="detail-label">Category:</span> <?php echo htmlspecialchars($course['category']); ?>
            </div>
            <div class="course-info-item">
                <span class="detail-label">Valid From:</span> <?php echo htmlspecialchars($course['valid_from']); ?> 
                <span class="detail-label">To:</span> <?php echo htmlspecialchars($course['valid_to']); ?>
            </div>
            <div class="course-info-item">
                <span class="detail-label">Created By:</span> <?php echo htmlspecialchars($course['created_by_username']); ?>
            </div>
            <div class="course-info-item">
                <span class="detail-label">Date of Creation:</span> <?php echo htmlspecialchars($course['created_at']); ?>
            </div>
        </div>
        <!-- Right Block -->
        <div class="course-right-block">
            <div class="course-info-item">
                <span class="detail-label">Description:</span> <?php echo htmlspecialchars($course['description']); ?>
            </div>
        </div>
    </div>


    <!-- Assigned Trainers Section -->
    <div class="trainer-table">
        <h3 class="card-title">Assigned Trainers</h3>
        <?php if ($assignedTrainers->num_rows > 0): ?>
            <table class="assigned-courses-table">
                <thead>
                    <tr>
                        <th>Trainer Name</th>
                        <th>Email</th>
                        <th>Phone Number</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($trainer = $assignedTrainers->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <a href="user_details.php?id=<?php echo $trainer['trainer_id']; ?>">
                                    <?php echo htmlspecialchars($trainer['trainer_username']); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($trainer['email']); ?></td>
                            <td><?php echo htmlspecialchars($trainer['phone_number']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No trainers assigned to this course.</p>
        <?php endif; ?>
    </div>

    <!-- Actions Section -->
    <div class="actions">
        <?php if (isset($_SESSION['role_id']) && in_array($_SESSION['role_id'], [1, 2])): ?>
            <a href="edit_course.php?id=<?php echo $courseId; ?>" class="btn btn-primary">Edit</a>
        <?php endif; ?>
        <a href="manage_courses.php" class="btn btn-secondary">Back</a>
    </div>
</div>
</body>
</html>
