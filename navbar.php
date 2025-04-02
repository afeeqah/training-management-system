<?php
session_start();

// Check if user is logged in
$role_id = isset($_SESSION['role_id']) ? $_SESSION['role_id'] : null;
$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';

// Define Navbar for each role
function getNavbar($role_id, $username) {
    $navbar = '<nav id="navbar-main">';
    
    // Add world logo
    $navbar .= '
        <div class="navbar-logo">
            <a href="https://i-world-technology.com/" target="_blank">
                <img src="images/iwt_black.png" alt="I-World Technology" height="40">
            </a>
        </div>
    ';
    
    // Navbar menu
    $navbar .= '<ul>';
    switch ($role_id) {
        case 1: // Admin
            $navbar .= '
                <li><a href="dashboard.php">Dashboard</a></li>
                <li>
                    <a href="#">Manage Users</a>
                    <ul>
                        <li><a href="manage_users.php?role=admin">Admins</a></li>
                        <li><a href="manage_users.php?role=staff">Staffs</a></li>
                        <li><a href="manage_users.php?role=trainer">Trainers</a></li>
                    </ul>
                </li>
                <li><a href="manage_courses.php">Manage Courses</a></li>
                <li><a href="manage_venues.php">Manage Venues</a></li>
                <li><a href="trainer_course_assignments.php">Trainer-Course Assignment</a></li>
                <li><a href="manage_sessions.php">Training Sessions</a></li>
                <li><a href="logout.php">Logout</a></li>
            ';
            break;

        case 2: // Staff
            $navbar .= '
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="manage_users.php?role=trainer">Manage Trainers</a></li>
                <li><a href="manage_courses.php">Manage Courses</a></li>
                <li><a href="manage_venues.php">Manage Venues</a></li>
                <li><a href="trainer_course_assignments.php">Trainer-Course Assignment</a></li>
                <li><a href="manage_sessions.php">Training Sessions</a></li>
                <li><a href="logout.php">Logout</a></li>
            ';
            break;

        case 3: // Trainer
            $navbar .= '
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="all_courses.php">All Courses</a></li>
                <li><a href="my_courses.php">My Courses</a></li>
                <li><a href="my_schedule.php">My Schedule</a></li>
                <li><a href="logout.php">Logout</a></li>
            ';
            break;            

        default:
            // No navbar for unlogged-in users
            $navbar .= '<li><a href="index.php">Return to Login</a></li>';
            break;
    }
    $navbar .= '</ul>';

    // Add Profile button with icon and username
    if ($role_id) {
        $navbar .= '
            <div class="navbar-profile">
                <a href="edit_profile.php" class="navbar-profile-link">
                    <img src="images/profile_icon.png" alt="Profile" height="30">
                    <span class="navbar-username">' . htmlspecialchars($username) . '</span>
                </a>
            </div>
        ';
    }

    $navbar .= '</nav>';
    return $navbar;
}

// Display Navbar
echo getNavbar($role_id, $username);
?>
