<?php
// Include database and navbar
require_once 'config.php';
include 'navbar.php';

// Ensure user is logged in and has the correct role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 2])) {
    echo "<script>alert('Access Denied!'); window.location.href='index.php';</script>";
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
    "SELECT COUNT(*) AS total FROM courses c
    WHERE c.course_name LIKE CONCAT('%', ?, '%')"
);
$countQuery->bind_param('s', $search);
$countQuery->execute();
$totalRecords = $countQuery->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $limit);

// Fetch courses with pagination and search
$query = $conn->prepare(
    "SELECT c.*, u.username AS created_by, r.role_name AS created_by_role,
    CASE 
        WHEN c.valid_to < NOW() THEN 'Expired'
        WHEN c.valid_to BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 'Expiring'
        WHEN NOW() BETWEEN c.valid_from AND c.valid_to THEN 'Active'
        WHEN c.valid_from > NOW() THEN 'Upcoming'
        ELSE 'Unknown'
    END AS status
    FROM courses c
    LEFT JOIN users u ON c.created_by = u.id
    LEFT JOIN roles r ON u.role_id = r.id
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
    <title>Manage Courses</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div id="manage-courses" class="container">
    <div class="header">
        <h1>Manage Courses</h1>
        <button class="create-user-btn" onclick="window.location.href='create_course.php'">
            <span class="icon">➕</span> Create Course
        </button>
    </div>

    <!-- Search Bar -->
    <div class="filter-search-container">
        <form method="GET" class="filter-search-form">
            <input type="text" name="search" placeholder="Search courses..." value="<?php echo htmlspecialchars($search); ?>" class="search-bar">
            <button type="submit" class="btn btn-primary">Search 🔍</button>
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
                <th>Status</th>
                <th>Created By</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($course = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                        <td><?php echo htmlspecialchars($course['description']); ?></td>
                        <td><?php echo htmlspecialchars($course['category']); ?></td>
                        <td><?php echo date('jS F Y', strtotime($course['valid_from'])); ?></td>
                        <td><?php echo date('jS F Y', strtotime($course['valid_to'])); ?></td>
                        <td>
                            <span class="status <?php echo strtolower($course['status']); ?>">
                                <?php echo htmlspecialchars($course['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php
                                echo $course['created_by'] 
                                    ? htmlspecialchars($course['created_by']) . ' (' . htmlspecialchars($course['created_by_role']) . ')'
                                    : 'N/A';
                            ?>
                        </td>
                        <td>
                        <div class="action-dropdown">
                            <button class="action-btn">⋮</button>
                            <div class="action-menu">
                                <a href="edit_course.php?id=<?php echo $course['id']; ?>">Edit</a>
                                <a href="delete_course.php?id=<?php echo $course['id']; ?>" onclick="return confirm('Are you sure?');">Delete</a>
                                <a href="course_details.php?id=<?php echo $course['id']; ?>">Details</a>
                            </div>
                        </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8">No courses found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?search=<?php echo urlencode($search); ?>&page=<?php echo $page - 1; ?>" class="btn">&laquo; Previous</a>
        <?php else: ?>
            <span class="btn btn-disabled">&laquo; Previous</span>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>" class="btn <?php echo ($i == $page) ? 'btn-active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <a href="?search=<?php echo urlencode($search); ?>&page=<?php echo $page + 1; ?>" class="btn">Next &raquo;</a>
        <?php else: ?>
            <span class="btn btn-disabled">Next &raquo;</span>
        <?php endif; ?>
    </div>
</div>
<script>
    document.querySelectorAll('.action-btn').forEach(btn => {
    btn.addEventListener('click', function (e) {
        e.stopPropagation(); // Prevent event bubbling
        const menu = this.nextElementSibling;
        document.querySelectorAll('.action-menu').forEach(m => {
            if (m !== menu) m.style.display = 'none'; // Close other dropdowns
        });
        menu.style.display = menu.style.display === 'block' ? 'none' : 'block'; // Toggle current
    });
});

document.addEventListener('click', () => {
    document.querySelectorAll('.action-menu').forEach(menu => menu.style.display = 'none'); // Close all dropdowns
});
</script>
<?php include 'footer.php'; ?>
</body>
</html>
