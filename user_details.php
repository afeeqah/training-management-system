<?php
require_once 'config.php';
include 'navbar.php';

// Check if the user ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>alert('Invalid user ID.'); window.location.href='dashboard.php';</script>";
    exit();
}

$userId = intval($_GET['id']);

// Fetch user details
$userQuery = $conn->prepare("
    SELECT u.*, r.role_name, c.username AS created_by_username 
    FROM users u 
    LEFT JOIN roles r ON u.role_id = r.id
    LEFT JOIN users c ON u.created_by = c.id
    WHERE u.id = ?
");
$userQuery->bind_param('i', $userId);
$userQuery->execute();
$userResult = $userQuery->get_result();

if ($userResult->num_rows === 0) {
    echo "<script>alert('User not found.'); window.location.href='manage_users.php';</script>";
    exit();
}

$user = $userResult->fetch_assoc();

// Fetch role-specific details
$roleDetails = [];
if ($user['role_id'] == 3) { // Trainers
    $detailsQuery = $conn->prepare("SELECT rd.ic_passport, rd.ttt_status FROM role_details rd WHERE rd.user_id = ?");
    $detailsQuery->bind_param('i', $userId);
    $detailsQuery->execute();
    $roleDetails = $detailsQuery->get_result()->fetch_assoc();

    // Updated query to fetch course names
    $coursesQuery = $conn->prepare("
        SELECT c.id AS course_id, c.course_name, c.description, c.valid_from, c.valid_to 
        FROM course_assignments ca
        JOIN courses c ON ca.course_id = c.id
        WHERE ca.trainer_id = ?
    ");
    $coursesQuery->bind_param('i', $userId);
    $coursesQuery->execute();
    $assignedCourses = $coursesQuery->get_result();
} elseif ($user['role_id'] == 2) { // Staff
    $detailsQuery = $conn->prepare("SELECT rd.position FROM role_details rd WHERE rd.user_id = ?");
    $detailsQuery->bind_param('i', $userId);
    $detailsQuery->execute();
    $roleDetails = $detailsQuery->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div id="user-details-container">
    <h2 class="user-details-title">User Details</h2>

    <?php if ($user['role_id'] == 1): ?>
        <!-- Admin: Single centered container -->
        <div id="admin-details" class="user-details-center">
            <div class="user-details-card">
                <h3 class="card-title">Basic Information</h3>
                <p><span class="detail-label">Username:</span> <?php echo htmlspecialchars($user['username']); ?></p>
                <p><span class="detail-label">Email:</span> <?php echo htmlspecialchars($user['email']); ?></p>
                <p><span class="detail-label">Phone Number:</span> <?php echo htmlspecialchars($user['phone_number']); ?></p>
                <p><span class="detail-label">Role:</span> <?php echo htmlspecialchars($user['role_name']); ?></p>
                <p><span class="detail-label">Created By:</span> <?php echo htmlspecialchars($user['created_by_username']); ?></p>
                <p><span class="detail-label">Date of Creation:</span> 
                    <?php echo date('jS F Y', strtotime($user['created_at'])); ?>
                </p>
            </div>
        </div>
    <?php elseif ($user['role_id'] == 2): ?>
        <!-- Staff: Two containers next to each other -->
        <div id="staff-details" class="user-details-grid">
            <div class="user-details-card">
                <h3 class="card-title">Basic Information</h3>
                <p><span class="detail-label">Username:</span> <?php echo htmlspecialchars($user['username']); ?></p>
                <p><span class="detail-label">Email:</span> <?php echo htmlspecialchars($user['email']); ?></p>
                <p><span class="detail-label">Phone Number:</span> <?php echo htmlspecialchars($user['phone_number']); ?></p>
                <p><span class="detail-label">Role:</span> <?php echo htmlspecialchars($user['role_name']); ?></p>
                <p><span class="detail-label">Created By:</span> <?php echo htmlspecialchars($user['created_by_username']); ?></p>
                <p><span class="detail-label">Date of Creation:</span> <?php echo htmlspecialchars($user['created_at']); ?></p>
            </div>
            <div class="user-details-card">
                <h3 class="card-title">Role-Specific Information</h3>
                <p><span class="detail-label">Position:</span> <?php echo htmlspecialchars($roleDetails['position']); ?></p>
            </div>
        </div>
    <?php elseif ($user['role_id'] == 3): ?>
        <!-- Trainer: Two containers side-by-side + Table -->
        <div id="trainer-details">
            <div class="user-details-grid">
                <div class="user-details-card">
                    <h3 class="card-title">Basic Information</h3>
                    <p><span class="detail-label">Username:</span> <?php echo htmlspecialchars($user['username']); ?></p>
                    <p><span class="detail-label">Email:</span> <?php echo htmlspecialchars($user['email']); ?></p>
                    <p><span class="detail-label">Phone Number:</span> <?php echo htmlspecialchars($user['phone_number']); ?></p>
                    <p><span class="detail-label">Role:</span> <?php echo htmlspecialchars($user['role_name']); ?></p>
                    <p><span class="detail-label">Created By:</span> <?php echo htmlspecialchars($user['created_by_username']); ?></p>
                    <p><span class="detail-label">Date of Creation:</span> <?php echo htmlspecialchars($user['created_at']); ?></p>
                </div>
                <div class="user-details-card">
                    <h3 class="card-title">Role-Specific Information</h3>
                    <p><span class="detail-label">IC/Passport Number:</span> <?php echo htmlspecialchars($roleDetails['ic_passport']); ?></p>
                    <p><span class="detail-label">TTT Certification:</span> <?php echo htmlspecialchars($roleDetails['ttt_status']); ?></p>
                </div>
            </div>
            <div class="user-details-table">
                <h3 class="card-title">Assigned Courses</h3>
                <?php if ($assignedCourses->num_rows > 0): ?>
                    <table class="assigned-courses-table">
                        <thead>
                            <tr>
                                <th>Course Name</th>
                                <th>Description</th>
                                <th>Valid From</th>
                                <th>Valid To</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($course = $assignedCourses->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <a href="course_details.php?id=<?php echo $course['course_id']; ?>">
                                            <?php echo htmlspecialchars($course['course_name']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($course['description']); ?></td>
                                    <td><?php echo date('jS F Y', strtotime($course['valid_from'])); ?></td>
                                    <td><?php echo date('jS F Y', strtotime($course['valid_to'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No courses assigned to this trainer.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    <div class="actions" style="display: flex; justify-content: center; gap: 20px; margin-top: 20px;">
        <?php 
        // Determine the role of the user for redirection purposes
        $roleMapping = [
            1 => 'admin',
            2 => 'staff',
            3 => 'trainer',
        ];
        $role = $roleMapping[$user['role_id']] ?? null; 
        ?>
        <?php if ($user['status'] == 1): // Check if the user is active ?>
            <a href="deactivate_user.php?id=<?php echo $userId; ?>&role=<?php echo urlencode($role); ?>" 
            onclick="return confirm('Are you sure you want to deactivate this user?');" 
            style="color: white; background-color: red; padding: 10px 15px; text-decoration: none; border-radius: 5px;">
            Deactivate
            </a>
        <?php else: ?>
            <a href="activate_user.php?id=<?php echo $userId; ?>&role=<?php echo urlencode($role); ?>" 
            onclick="return confirm('Are you sure you want to activate this user?');" 
            style="color: white; background-color: green; padding: 10px 15px; text-decoration: none; border-radius: 5px;">
            Activate
            </a>
        <?php endif; ?>
        <a href="edit_user.php?id=<?php echo $userId; ?>" 
        style="color: white; background-color: blue; padding: 10px 15px; text-decoration: none; border-radius: 5px;">
        Edit
        </a>
        <a href="<?php echo isset($_SERVER['HTTP_REFERER']) ? htmlspecialchars($_SERVER['HTTP_REFERER']) : 'dashboard.php'; ?>" 
        style="color: white; background-color: gray; padding: 10px 15px; text-decoration: none; border-radius: 5px;">
        Back
        </a>
    </div>
</div>
</body>
</html>
