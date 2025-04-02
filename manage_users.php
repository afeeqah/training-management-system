<?php
require_once 'config.php';
include 'navbar.php';

if (isset($_GET['success'])) {
    $message = '';
    if ($_GET['success'] === 'activated') {
        $message = 'User has been activated successfully.';
    } elseif ($_GET['success'] === 'deactivated') {
        $message = 'User has been deactivated successfully.';
    }

    if ($message) {
        echo "<script>alert('$message');</script>";
    }
}

if (isset($_GET['error'])) {
    $error = '';
    if ($_GET['error'] === 'invalid_user') {
        $error = 'Invalid user specified.';
    } elseif ($_GET['error'] === 'activate_failed') {
        $error = 'Failed to activate the user.';
    } elseif ($_GET['error'] === 'deactivate_failed') {
        $error = 'Failed to deactivate the user.';
    }

    if ($error) {
        echo "<script>alert('$error');</script>";
    }
}

// Check if the role parameter is set
$role = isset($_GET['role']) ? strtolower($_GET['role']) : null; // Convert role to lowercase

// Redirect if role is missing or invalid
$validRoles = ['admin', 'staff', 'trainer'];
if (!$role || !in_array($role, $validRoles)) {
    echo "<script>alert('Invalid role specified.'); window.location.href='dashboard.php';</script>";
    exit();
}

// Map roles to role IDs
$roleMapping = [
    'admin' => 1,
    'staff' => 2,
    'trainer' => 3,
];

$roleId = $roleMapping[$role]; // Get the corresponding role_id

// Pagination settings
$limit = 10; // Number of records per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Search functionality
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';

// Fetch total records
$countQuery = $conn->prepare("
    SELECT COUNT(*) AS total 
    FROM users u 
    LEFT JOIN role_details r ON u.id = r.user_id
    WHERE u.role_id = ? AND (
        u.username LIKE CONCAT('%', ?, '%') OR 
        u.email LIKE CONCAT('%', ?, '%') OR 
        u.phone_number LIKE CONCAT('%', ?, '%') OR
        r.ic_passport LIKE CONCAT('%', ?, '%')
    )
");
$countQuery->bind_param('issss', $roleId, $searchQuery, $searchQuery, $searchQuery, $searchQuery);
$countQuery->execute();
$totalRecords = $countQuery->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $limit);

// Fetch users with pagination
$query = $conn->prepare("
    SELECT 
    u.id, 
    u.username, 
    u.email, 
    u.phone_number, 
    r.ic_passport, 
    r.ttt_status AS ttt_certification, 
    r.position, 
    c.username AS created_by,
    (CASE 
         WHEN c.role_id = 1 THEN 'Admin' 
         WHEN c.role_id = 2 THEN 'Staff' 
         ELSE 'Unknown' 
    END) AS creator_role,
    u.status
FROM users u
LEFT JOIN role_details r ON u.id = r.user_id
LEFT JOIN users c ON u.created_by = c.id
WHERE u.role_id = ? AND (
    u.username LIKE CONCAT('%', ?, '%') OR 
    u.email LIKE CONCAT('%', ?, '%') OR 
    u.phone_number LIKE CONCAT('%', ?, '%') OR
    r.ic_passport LIKE CONCAT('%', ?, '%')
    )
LIMIT ? OFFSET ?
");
$query->bind_param('issssii', $roleId, $searchQuery, $searchQuery, $searchQuery, $searchQuery, $limit, $offset);
$query->execute();
$result = $query->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div id="manage-users" class="container">
    <div class="header">
        <h1>Manage <?php echo ucfirst($role); ?></h1>
        <button class="create-user-btn" onclick="window.location.href='create_user.php?role=<?php echo $role; ?>'">
            <span class="icon">➕</span> Create User
        </button>
    </div>
    <div class="filter-search-container">
        <form method="GET" class="filter-search-form">
            <input type="hidden" name="role" value="<?php echo $role; ?>">
            <input type="text" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($searchQuery); ?>" class="search-bar">
            <button type="submit" class="btn btn-primary">Search 🔍</button>
        </form>
    </div>
    <table class="table-modern">
        <thead>
            <tr>
                <th>Account Name</th>
                <th>Type</th>
                <th>Phone Number</th>
                <th>Email</th>
                <th>Created By</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['username']; ?></td>
                    <td><?php echo ($role === 'trainers') ? 'Trainer' : ucfirst($role); ?></td>
                    <td><?php echo $row['phone_number']; ?></td>
                    <td><?php echo $row['email']; ?></td>
                    <td><?php echo htmlspecialchars($row['created_by'] . ' (' . $row['creator_role'] . ')'); ?></td>
                    <td>
                        <span class="status <?php echo $row['status'] == 1 ? 'active' : 'inactive'; ?>">
                            <?php echo $row['status'] == 1 ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td>
                        <div class="action-dropdown">
                            <button class="action-btn">⋮</button>
                            <div class="action-menu">
                                <a href="edit_user.php?id=<?php echo $row['id']; ?>">Edit</a>
                                <a href="delete_user.php?id=<?php echo $row['id']; ?>&role=<?php echo $role; ?>" onclick="return confirm('Are you sure?');">Delete</a>
                                <a href="user_details.php?id=<?php echo $row['id']; ?>">Details</a>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?role=<?php echo $role; ?>&search=<?php echo urlencode($searchQuery); ?>&page=<?php echo $page - 1; ?>" class="btn">« Previous</a>
        <?php else: ?>
            <span class="btn btn-disabled">« Previous</span>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?role=<?php echo $role; ?>&search=<?php echo urlencode($searchQuery); ?>&page=<?php echo $i; ?>" 
               class="btn <?php echo ($i == $page) ? 'btn-active' : ''; ?>">
               <?php echo $i; ?>
            </a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <a href="?role=<?php echo $role; ?>&search=<?php echo urlencode($searchQuery); ?>&page=<?php echo $page + 1; ?>" class="btn">Next »</a>
        <?php else: ?>
            <span class="btn btn-disabled">Next »</span>
        <?php endif; ?>
    </div>
</div>
<script>
    const actionBtns = document.querySelectorAll('.action-btn');
    actionBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            const menu = btn.nextElementSibling;
            menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
            e.stopPropagation();
        });
    });

    document.addEventListener('click', () => {
        document.querySelectorAll('.action-menu').forEach(menu => menu.style.display = 'none');
    });
</script>
</body>
</html>