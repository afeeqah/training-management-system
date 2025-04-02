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

// Fetch search input and filters
$search = $_GET['search'] ?? '';
$filterCourse = isset($_GET['filter_course']) ? $_GET['filter_course'] : '';
$filterStatus = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';
$filterVenue = isset($_GET['filter_venue']) ? $_GET['filter_venue'] : '';

// Initialize conditions
$conditions = [];

if (!empty($search)) {
    $conditions[] = "(c.course_name LIKE '%$search%' OR u.username LIKE '%$search%' OR v.venue_name LIKE '%$search%')";
}
if (!empty($filterCourse)) {
    $conditions[] = "ts.course_id = $filterCourse";
}
if (!empty($filterStatus)) {
    $conditions[] = "ts.status = '$filterStatus'";
}
if (!empty($filterVenue)) {
    $conditions[] = "ts.venue_id = $filterVenue";
}

// Fetch total records for pagination
$countQuery = "SELECT COUNT(DISTINCT ts.id) AS total FROM training_sessions ts
                JOIN courses c ON ts.course_id = c.id
                JOIN venues v ON ts.venue_id = v.id
                JOIN users u ON ts.trainer_id = u.id";

if (!empty($conditions)) {
    $countQuery .= " WHERE " . implode(' AND ', $conditions);
}

$countResult = $conn->query($countQuery);
$totalRecords = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $limit);

// Set Malaysia timezone
$conn->query("SET time_zone = '+08:00'");

// Update statuses dynamically using database time
$updateStatusQuery = "
    UPDATE training_sessions 
    SET status = CASE 
        WHEN CONCAT(session_end_date, ' ', session_end_time) <= NOW() THEN 'completed'
        WHEN CONCAT(session_date, ' ', session_time) <= NOW() 
             AND CONCAT(session_end_date, ' ', session_end_time) > NOW() THEN 'active'
        ELSE 'upcoming'
    END;";
$conn->query($updateStatusQuery);

// Fetch training sessions with pagination and filters
$sql = "SELECT ts.id, c.course_name, v.venue_name, 
        ts.session_date, ts.session_end_date, ts.session_time, ts.session_end_time, ts.status,
        GROUP_CONCAT(DISTINCT u.username SEPARATOR ', ') AS trainers,
        GROUP_CONCAT(DISTINCT u.id SEPARATOR ', ') AS trainer_ids,
        cr.username AS created_by_username, r.role_name AS created_by_role
        FROM training_sessions ts
        JOIN courses c ON ts.course_id = c.id
        JOIN venues v ON ts.venue_id = v.id
        JOIN users u ON ts.trainer_id = u.id
        JOIN users cr ON ts.created_by = cr.id
        JOIN roles r ON cr.role_id = r.id";

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
}
$sql .= " GROUP BY ts.id LIMIT $limit OFFSET $offset";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Training Sessions</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div id="manage-sessions" class="container">
    <div class="header">
        <h1>Manage Training Sessions</h1>
        <button class="create-user-btn" onclick="window.location.href='create_session.php'">
            <span class="icon">➕</span> Create New Session
        </button>
    </div>

    <!-- Combined Filter and Search Section -->
    <div class="filter-search-container">
        <form method="GET" class="filter-search-form">
            <!-- Search Bar -->
            <input type="text" name="search" placeholder="Search sessions..." value="<?php echo htmlspecialchars($search); ?>" class="search-bar">

            <!-- Filter by Course -->
            <select name="filter_course" id="filter-course">
                <option value="">All Courses</option>
                <?php
                $courseQuery = $conn->query("SELECT id, course_name FROM courses");
                while ($course = $courseQuery->fetch_assoc()):
                    echo "<option value='{$course['id']}'>{$course['course_name']}</option>";
                endwhile;
                ?>
            </select>

            <!-- Filter by Venue -->
            <select name="filter_venue" id="filter-venue">
                <option value="">All Venues</option>
                <?php
                $venueQuery = $conn->query("SELECT id, venue_name FROM venues");
                while ($venue = $venueQuery->fetch_assoc()):
                    echo "<option value='{$venue['id']}'>{$venue['venue_name']}</option>";
                endwhile;
                ?>
            </select>

            <!-- Filter by Status -->
            <select name="filter_status" id="filter-status">
                <option value="">All Statuses</option>
                <option value="upcoming">Upcoming</option>
                <option value="active">Active</option>
                <option value="completed">Completed</option>
            </select>

            <!-- Filter Button -->
            <button type="submit" class="btn btn-primary">Search 🔍</button>
        </form>
    </div>

    <!-- Training Sessions Table -->
    <?php if ($result->num_rows > 0): ?>
        <table class="table-modern">
            <thead>
                <tr>
                    <th>Course Name</th>
                    <th>Trainers</th>
                    <th>Venue</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['course_name']); ?></td>
                        <td>
                            <div class="trainer-chips">
                                <?php
                                $trainerNames = explode(',', $row['trainers']);
                                $trainerIds = explode(',', $row['trainer_ids']); // Make sure to fetch trainer_ids in the query
                                foreach ($trainerNames as $index => $trainerName):
                                ?>
                                    <a href="user_details.php?id=<?php echo htmlspecialchars(trim($trainerIds[$index])); ?>" class="chip">
                                        <?php echo htmlspecialchars(trim($trainerName)); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($row['venue_name']); ?></td>
                        <td><?php echo date('jS F Y', strtotime($row['session_date'])); ?></td>
                        <td><?php echo date('jS F Y', strtotime($row['session_end_date'])); ?></td>
                        <td><?php echo htmlspecialchars($row['session_time']); ?></td>
                        <td><?php echo htmlspecialchars($row['session_end_time']); ?></td>
                        <td>
                            <span class="status <?php echo strtolower($row['status']); ?>">
                                <?php echo ucfirst($row['status']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($row['created_by_username']) . " (" . htmlspecialchars($row['created_by_role']) . ")"; ?></td>
                        <td>
                            <div class="action-dropdown">
                                <button class="action-btn" onclick="toggleActionMenu(this)">⋮</button>
                                <div class="action-menu">
                                    <a href="edit_session.php?id=<?php echo $row['id']; ?>">Edit</a>
                                    <a href="delete_session.php?id=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure you want to delete this session?')">Delete</a>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No sessions found.</p>
    <?php endif; ?>
</div>

<script>
    function toggleActionMenu(button) {
        const menu = button.nextElementSibling;
        menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
    }

    // Close dropdown when clicking outside
    window.addEventListener('click', function (e) {
        document.querySelectorAll('.action-menu').forEach(menu => {
            if (!menu.contains(e.target) && !menu.previousElementSibling.contains(e.target)) {
                menu.style.display = 'none';
            }
        });
    });
</script>

</body>
<?php
include 'footer.php';
?>
</html>
