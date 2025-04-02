<?php
require_once 'config.php';
include 'navbar.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 2])) { // Only Admin and Staff allowed
    echo "<script>alert('Access Denied!'); window.location.href='index.php';</script>";
    exit();
}

// Pagination settings
$limit = 10; // Number of records per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Search functionality
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch total records for pagination
$countQuery = $conn->prepare(
    "SELECT COUNT(DISTINCT c.course_name) AS total
    FROM course_assignments a
    JOIN courses c ON a.course_id = c.id
    JOIN users t ON a.trainer_id = t.id
    WHERE c.course_name LIKE CONCAT('%', ?, '%') OR t.username LIKE CONCAT('%', ?, '%')"
);
$countQuery->bind_param('ss', $searchQuery, $searchQuery);
$countQuery->execute();
$totalRecords = $countQuery->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $limit);

// Fetch grouped assignments with search and pagination
$query = $conn->prepare(
    "SELECT 
        c.course_name,
        GROUP_CONCAT(t.username SEPARATOR ', ') AS trainers,
        u.username AS assigned_by,
        r.role_name AS assigned_by_role,
        MAX(a.assigned_at) AS latest_assigned_at
    FROM course_assignments a
    JOIN courses c ON a.course_id = c.id
    JOIN users t ON a.trainer_id = t.id
    JOIN users u ON a.assigned_by = u.id
    JOIN roles r ON u.role_id = r.id
    WHERE c.course_name LIKE CONCAT('%', ?, '%') OR t.username LIKE CONCAT('%', ?, '%')
    GROUP BY c.course_name, u.username, r.role_name
    ORDER BY latest_assigned_at DESC
    LIMIT ? OFFSET ?"
);
$query->bind_param('ssii', $searchQuery, $searchQuery, $limit, $offset);
$query->execute();
$result = $query->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trainer-Course Assignments</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div id="manage-courses" class="container">
    <div class="header">
        <h1>Trainer-Course Assignments</h1>
        <button class="create-user-btn" onclick="window.location.href='assignments.php'">
            <span class="icon">➕</span> Assign Trainer-Course
        </button>
    </div>

    <!-- Search Bar -->
    <div class="filter-search-container">
        <form method="GET" class="filter-search-form">
            <input type="text" name="search" class="search-bar" placeholder="Search by Trainer or Course" value="<?php echo htmlspecialchars($searchQuery); ?>">
            <button type="submit" class="btn btn-primary">Search 🔍</button>
        </form>
    </div>

    <!-- Assignments Table -->
    <table class="table-modern">
        <thead>
            <tr>
                <th>Course Name</th>
                <th>Trainers</th>
                <th>Assigned By</th>
                <th>Last Assigned At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['course_name']); ?></td>
                        <td>
                            <div class="trainer-chips">
                                <?php
                                // Fetch trainer IDs and usernames
                                $trainers = explode(', ', $row['trainers']);
                                $trainerQuery = $conn->prepare("SELECT id, username FROM users WHERE username IN ('" . implode("','", $trainers) . "')");
                                $trainerQuery->execute();
                                $trainerResult = $trainerQuery->get_result();

                                while ($trainer = $trainerResult->fetch_assoc()): ?>
                                    <a href="user_details.php?id=<?php echo $trainer['id']; ?>" class="chip">
                                        <?php echo htmlspecialchars($trainer['username']); ?>
                                    </a>
                                <?php endwhile; ?>
                            </div>
                        </td>
                        <td>
                            <?php
                                echo htmlspecialchars($row['assigned_by']) . ' (' . htmlspecialchars($row['assigned_by_role']) . ')';
                            ?>
                        </td>
                        <td>
                            <?php 
                            $formattedDate = date('jS F Y, h:i A', strtotime($row['latest_assigned_at']));
                            echo str_replace(', ', '<br>', $formattedDate); 
                            ?>
                        </td>
                        <td>
                            <div class="action-dropdown">
                                <button class="action-btn">⋮</button>
                                <div class="action-menu">
                                    <a href="edit_assignment.php?course=<?php echo urlencode($row['course_name']); ?>">Edit</a>
                                    <a href="#" onclick="deleteAssignment('<?php echo urlencode($row['course_name']); ?>')">Delete</a>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5">No assignments found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?search=<?php echo urlencode($searchQuery); ?>&page=<?php echo $page - 1; ?>" class="btn">&laquo; Previous</a>
        <?php else: ?>
            <span class="btn btn-disabled">&laquo; Previous</span>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?search=<?php echo urlencode($searchQuery); ?>&page=<?php echo $i; ?>" class="btn <?php echo ($i == $page) ? 'btn-active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <a href="?search=<?php echo urlencode($searchQuery); ?>&page=<?php echo $page + 1; ?>" class="btn">Next &raquo;</a>
        <?php else: ?>
            <span class="btn btn-disabled">Next &raquo;</span>
        <?php endif; ?>
    </div>
</div>

<script>
    // Toggle dropdown visibility for action buttons
    document.querySelectorAll('.action-btn').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            const menu = this.nextElementSibling;
            document.querySelectorAll('.action-menu').forEach(m => {
                if (m !== menu) m.style.display = 'none';
            });
            menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
        });
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', () => {
        document.querySelectorAll('.action-menu').forEach(menu => menu.style.display = 'none');
    });

    // Delete assignment handler
    function deleteAssignment(courseName) {
        if (confirm('Are you sure you want to delete this assignment?')) {
            fetch('delete_assignment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ course_name: courseName }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    location.reload();
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the assignment.');
            });
        }
    }
</script>
<?php include 'footer.php'; ?>
</body>
</html>
