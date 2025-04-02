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
    "SELECT COUNT(*) AS total FROM venues v
    WHERE v.venue_name IN ('Lab 4', 'Lab 7', 'Lab 8', 'Lab 9', 'Lab 10', 'Lab 11')
    AND v.venue_name LIKE CONCAT('%', ?, '%')"
);
$countQuery->bind_param('s', $search);
$countQuery->execute();
$totalRecords = $countQuery->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $limit);

// Fetch venues with pagination and search
$query = $conn->prepare(
    "SELECT v.*, u.username AS created_by, r.role_name AS created_by_role
    FROM venues v
    LEFT JOIN users u ON v.created_by = u.id
    LEFT JOIN roles r ON u.role_id = r.id
    WHERE v.venue_name IN ('Lab 4', 'Lab 7', 'Lab 8', 'Lab 9', 'Lab 10', 'Lab 11')
    AND v.venue_name LIKE CONCAT('%', ?, '%')
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
    <title>Manage Venues</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div id="manage-venues" class="container">
    <div class="header">
        <h1>Manage Venues</h1>
        <button class="create-user-btn" onclick="window.location.href='create_venue.php';">
            <span class="icon">➕</span> Create Venue
        </button>
    </div>

    <!-- Search Bar -->
    <div class="filter-search-container">
        <form method="GET" class="filter-search-form">
            <!-- Search Bar -->
            <input type="text" name="search" placeholder="Search venues..." value="<?php echo htmlspecialchars($search); ?>" class="search-bar">

            <!-- Search Button -->
            <button type="submit" class="btn btn-primary">Search 🔍</button>
        </form>
    </div>

    <!-- Venues Table -->
    <table class="table-modern">
        <thead>
            <tr>
                <th>Venue Name</th>
                <th>Location Details</th>
                <th>Created By</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($venue = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($venue['venue_name']); ?></td>
                        <td><?php echo htmlspecialchars($venue['location_details']); ?></td>
                        <td>
                            <?php
                                echo $venue['created_by']
                                    ? htmlspecialchars($venue['created_by']) . ' (' . htmlspecialchars($venue['created_by_role']) . ')'
                                    : 'N/A';
                            ?>
                        </td>
                        <td>
                            <?php 
                            $formattedDate = date('jS F Y, h:i A', strtotime($venue['created_at']));
                            echo str_replace(', ', '<br>', $formattedDate); 
                            ?>
                        </td>
                        <td>
                            <div class="action-dropdown">
                                <button class="action-btn">⋮</button>
                                <div class="action-menu">
                                    <a href="edit_venue.php?id=<?php echo $venue['id']; ?>">Edit</a>
                                    <a href="#" onclick="deleteVenue(<?php echo $venue['id']; ?>)">Delete</a>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5">No venues found.</td></tr>
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
    // Toggle the dropdown for actions
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

    // Close dropdown when clicking outside
    document.addEventListener('click', () => {
        document.querySelectorAll('.action-menu').forEach(menu => menu.style.display = 'none');
    });

    function deleteVenue(venueId) {
        if (!confirm('Are you sure you want to delete this venue?')) {
            return;
        }

        fetch('delete_venue.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({ venue_id: venueId }),
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
            alert('An error occurred while deleting the venue.');
        });
    }
</script>
<?php include 'footer.php'; ?>
</body>
</html>
