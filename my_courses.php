<?php
require_once 'config.php';
include 'navbar.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 3) {
    echo "<script>alert('Access denied.'); window.location.href='index.php';</script>";
    exit();
}

// Set timezone to Malaysia
date_default_timezone_set('Asia/Kuala_Lumpur');

// Fetch trainer-specific information
$user_id = $_SESSION['user_id'];

// Fetch courses assigned to the logged-in trainer
$query = $conn->prepare(
    "SELECT c.id, c.course_name, c.description, ca.assigned_at, u.username AS created_by
    FROM courses c
    JOIN course_assignments ca ON c.id = ca.course_id
    JOIN users u ON c.created_by = u.id
    WHERE ca.trainer_id = ?"
);
$query->bind_param("i", $user_id);
$query->execute();
$result = $query->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <h1>Assigned Courses</h1>

        <?php if ($result->num_rows > 0): ?>
        <!-- Assigned Courses Table -->
        <table class="table-modern">
            <thead>
                <tr>
                    <th>Course Name</th>
                    <th>Description</th>
                    <th>Created By</th>
                    <th>Assigned At</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <a href="course_details.php?id=<?php echo $row['id']; ?>">
                                    <?php echo htmlspecialchars($row['course_name']); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($row['description']); ?></td>
                            <td><?php echo htmlspecialchars($row['created_by']); ?></td>
                            <td><?php echo date('jS F Y, g:i A', strtotime($row['assigned_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4">No courses have been assigned to you yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p>No courses have been assigned to you yet.</p>
        <?php endif; ?>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>
