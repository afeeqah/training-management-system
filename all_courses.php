<?php
require_once 'config.php';
include 'navbar.php';

// Ensure the user is logged in and is a trainer
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 3) {
    echo "<script>alert('Access denied.'); window.location.href='index.php';</script>";
    exit();
}

// Pagination settings
$limit = 10; // Number of records per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Fetch search input
$search = $_GET['search'] ?? '';

// Fetch total records for pagination
$countQuery = $conn->prepare(
    "SELECT COUNT(*) AS total FROM courses
     WHERE course_name LIKE CONCAT('%', ?, '%')"
);
$countQuery->bind_param('s', $search);
$countQuery->execute();
$totalRecords = $countQuery->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $limit);

// Fetch courses with pagination and search
$query = $conn->prepare(
    "SELECT c.*, u.username AS created_by
     FROM courses c
     LEFT JOIN users u ON c.created_by = u.id
     WHERE c.course_name LIKE CONCAT('%', ?, '%')
     LIMIT ? OFFSET ?"
);
$query->bind_param('sii', $search, $limit, $offset);
$query->execute();
$result = $query->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Courses</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div id="view-all-courses" class="container">
    <div class="header">
        <h1>Total Courses</h1>
    </div>

    <!-- Search Bar -->
    <div class="search-bar-container">
        <form method="GET" action="all_courses.php" class="search-form">
            <input type="text" name="search" placeholder="Search courses..." value="<?php echo htmlspecialchars($search); ?>" class="search-bar">
            <button type="submit" class="search-btn">Search</button>
        </form>
    </div>

    <!-- Courses Table -->
    <table class="table-modern">
        <thead>
            <tr>
                <th>Course Name</th>
                <th>Description</th>
                <th>Category</th>
                <th>Valid From</th>
                <th>Valid To</th>
                <th>Created By</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($course = $result->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <a href="course_details.php?id=<?php echo $course['id']; ?>">
                                <?php echo htmlspecialchars($course['course_name']); ?>
                            </a>
                        </td>
                        <td><?php echo htmlspecialchars($course['description']); ?></td>
                        <td><?php echo htmlspecialchars($course['category']); ?></td>
                        <td><?php echo date('jS F Y', strtotime($course['valid_from'])); ?></td>
                        <td><?php echo date('jS F Y', strtotime($course['valid_to'])); ?></td>
                        <td><?php echo htmlspecialchars($course['created_by'] ?? 'N/A'); ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">No courses found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?search=<?php echo urlencode($search); ?>&page=<?php echo $page - 1; ?>" class="btn">Previous</a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>" class="btn <?php echo ($i == $page) ? 'btn-active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <a href="?search=<?php echo urlencode($search); ?>&page=<?php echo $page + 1; ?>" class="btn">Next</a>
        <?php endif; ?>
    </div>
</div>
<?php include 'footer.php'; ?>
</body>
</html>
