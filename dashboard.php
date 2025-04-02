<?php
require_once 'config.php';
include 'navbar.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('You are not logged in.'); window.location.href='index.php';</script>";
    exit();
}

// Set timezone to Malaysia
date_default_timezone_set('Asia/Kuala_Lumpur');

$role_id = $_SESSION['role_id'];
$user_id = $_SESSION['user_id'];

$conn->query("
    UPDATE training_sessions 
    SET status = CASE 
        WHEN CONCAT(session_end_date, ' ', session_end_time) <= NOW() THEN 'completed'
        WHEN CONCAT(session_date, ' ', session_time) <= NOW() 
             AND CONCAT(session_end_date, ' ', session_end_time) > NOW() THEN 'active'
        ELSE 'upcoming'
    END
");

// Fetch active venues dynamically and set 'active' status
$activeVenuesQuery = $conn->query("
    SELECT DISTINCT v.venue_name 
    FROM training_sessions ts
    JOIN venues v ON ts.venue_id = v.id
    WHERE ts.status = 'active'
      AND CURDATE() BETWEEN ts.session_date AND ts.session_end_date
");

$activeVenues = [];
while ($venue = $activeVenuesQuery->fetch_assoc()) {
    $activeVenues[] = $venue['venue_name']; // Collect active venue names
}

// Define months array
$allMonths = [
    'January', 'February', 'March', 'April', 'May', 'June', 
    'July', 'August', 'September', 'October', 'November', 'December'
];

// Get the selected year or default to the current year
$selectedYear = isset($_GET['year']) ? $_GET['year'] : date('Y');
$trainerInsightYear = isset($_GET['trainer-insight-year']) ? $_GET['trainer-insight-year'] : date('Y');
$courseStatusYear = isset($_GET['course-status-year']) ? $_GET['course-status-year'] : date('Y');
$venueUsageYear = isset($_GET['venue-usage-year']) ? $_GET['venue-usage-year'] : date('Y');

// Fetch available years for filtering
$availableYearsQuery = $conn->query("
    SELECT DISTINCT YEAR(created_at) AS year FROM `users`
    UNION
    SELECT DISTINCT YEAR(created_at) AS year FROM `courses`
    UNION
    SELECT DISTINCT YEAR(session_date) AS year FROM `training_sessions`
    UNION
    SELECT DISTINCT YEAR(assigned_at) AS year FROM `course_assignments`
    ORDER BY year DESC
");
$availableYears = [];
while ($row = $availableYearsQuery->fetch_assoc()) {
    $availableYears[] = $row['year'];
}

// Helper function to map query results to months
function mapDataToMonths($queryResult, $monthField) {
    global $allMonths;
    if (!$queryResult || $queryResult->num_rows === 0) {
        return array_fill_keys($allMonths, 0); // Return an empty dataset with 0 values
    }

    $mappedData = array_fill_keys($allMonths, 0);
    while ($row = $queryResult->fetch_assoc()) {
        $monthName = date('F', strtotime($row[$monthField] . '-01'));
        if (isset($mappedData[$monthName])) {
            $mappedData[$monthName] = $row['total'];
        }
    }
    return $mappedData;
}

if ($role_id == 1 || $role_id == 2) { // Admin and Staff
    // Common Queries for Admin and Staff
    $totalAdmins = $conn->query("SELECT COUNT(*) AS total FROM `users` WHERE `role_id` = 1")->fetch_assoc()['total'];
    $totalStaff = $conn->query("SELECT COUNT(*) AS total FROM `users` WHERE `role_id` = 2")->fetch_assoc()['total'];
    $totalTrainers = $conn->query("SELECT COUNT(*) AS total FROM `users` WHERE `role_id` = 3")->fetch_assoc()['total'];
    $totalCoursesQuery = $conn->query("SELECT COUNT(*) AS total FROM `courses`");
    $totalCourses = $totalCoursesQuery->fetch_assoc()['total'] ?? 0;
    $totalAssignments = $conn->query("SELECT COUNT(*) AS total FROM `course_assignments`")->fetch_assoc()['total'];
    $totalTrainingSessions = $conn->query("
    SELECT COUNT(*) AS total
    FROM `training_sessions`
    WHERE `status` = 'active'
    ")->fetch_assoc()['total'];

    if ($role_id == 1) { // Admin-specific Queries
        $userRegistrations = $conn->query("
            SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS total
            FROM `users`
            WHERE YEAR(created_at) = $selectedYear
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ");
        $userCounts = mapDataToMonths($userRegistrations, 'month');

        $courseCreation = $conn->query("
            SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS total
            FROM `courses`
            WHERE YEAR(created_at) = $selectedYear
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ");
        $courseCounts = mapDataToMonths($courseCreation, 'month');

        $trainingSessions = $conn->query("
            SELECT DATE_FORMAT(session_date, '%Y-%m') AS month, COUNT(*) AS total
            FROM `training_sessions`
            WHERE YEAR(session_date) = $selectedYear
            GROUP BY DATE_FORMAT(session_date, '%Y-%m')
        ");
        $sessionCounts = mapDataToMonths($trainingSessions, 'month');

        $courseAssignments = $conn->query("
            SELECT DATE_FORMAT(assigned_at, '%Y-%m') AS month, COUNT(*) AS total
            FROM `course_assignments`
            WHERE YEAR(assigned_at) = $selectedYear
            GROUP BY DATE_FORMAT(assigned_at, '%Y-%m')
        ");
        $assignmentCounts = mapDataToMonths($courseAssignments, 'month');

        // Admin-specific Recent Activities
        $recentActivities = $conn->query("
        SELECT 'User Created' AS activity, 
               CONCAT(created_by.username, ' (', roles.role_name, ') created a ', 
               CASE 
                   WHEN created.role_id = 3 THEN 'trainer' 
                   WHEN created.role_id = 2 THEN 'staff' 
                   WHEN created.role_id = 1 THEN 'admin' 
                   ELSE 'user' 
               END, ' <b><i>', created.username, '</i></b>') AS details, 
               created.created_at AS timestamp
        FROM `users` created
        LEFT JOIN `users` created_by ON created.created_by = created_by.id
        LEFT JOIN `roles` ON created_by.role_id = roles.id
        UNION ALL
        SELECT 'Course Created' AS activity, 
               CONCAT(created_by.username, ' (', roles.role_name, ') created a course <b><i>', 
               c.course_name, '</i></b>') AS details, 
               c.created_at AS timestamp
        FROM `courses` c
        LEFT JOIN `users` created_by ON c.created_by = created_by.id
        LEFT JOIN `roles` ON created_by.role_id = roles.id
        UNION ALL
        SELECT 'Assignment Made' AS activity, 
               CONCAT(assigned_by.username, ' (', roles.role_name, ') assigned trainer <b><i>', 
               trainer.username, '</i></b> to course <b><i>', course.course_name, '</i></b>') AS details, 
               ca.assigned_at AS timestamp
        FROM `course_assignments` ca
        LEFT JOIN `users` assigned_by ON ca.assigned_by = assigned_by.id
        LEFT JOIN `users` trainer ON ca.trainer_id = trainer.id
        LEFT JOIN `courses` course ON ca.course_id = course.id
        LEFT JOIN `roles` ON assigned_by.role_id = roles.id
        ORDER BY timestamp DESC
        LIMIT 10
    ");     
    }
    
    // Staff Dashboard Queries
    if ($role_id == 2) {
        // Staff-specific Recent Activities
        $recentActivities = $conn->query("
            SELECT 'User Created' AS activity, 
                CONCAT('You created a ', 
                CASE 
                    WHEN created.role_id = 3 THEN 'trainer' 
                    ELSE 'user' 
                END, ' <b><i>', created.username, '</i></b>') AS details, 
                created.created_at AS timestamp
            FROM `users` created
            WHERE created.created_by = $user_id
            ORDER BY created.created_at DESC
            LIMIT 10
        ");

        // Trainer Insight Data (For Staff)
        $query = "
            SELECT COUNT(DISTINCT u.id) AS active_count 
            FROM users u
            JOIN training_sessions ts ON u.id = ts.trainer_id
            WHERE u.role_id = 3 AND u.status = 1
        ";    
    $activeTrainersQuery = $conn->query($query);
    error_log("Trainer Insight Query: " . $query);

    
        $inactiveTrainersQuery = $conn->query("
            SELECT COUNT(*) AS inactive_count 
            FROM users u
            JOIN training_sessions ts ON u.id = ts.trainer_id
            WHERE u.role_id = 3 AND u.status = 0 AND YEAR(ts.session_date) = $trainerInsightYear
        ");
      
        $activeTrainers = $activeTrainersQuery->fetch_assoc()['active_count'] ?? 0;
        $inactiveTrainers = $inactiveTrainersQuery->fetch_assoc()['inactive_count'] ?? 0;
        
        if ($activeTrainersQuery->num_rows === 0) {
            echo "Trainer Insight Query returned no results. Check if session_date matches selected year.";
        } else {
            while ($row = $activeTrainersQuery->fetch_assoc()) {
                echo "Trainer ID: {$row['id']} - Active in Year: $trainerInsightYear <br>";
            }
        }        

        // Fetch course status from `courses` table for Staff Dashboard
        $courseStatusQuery = $conn->query("
            SELECT 
                SUM(CASE WHEN c.valid_from > NOW() THEN 1 ELSE 0 END) AS upcoming,
                SUM(CASE WHEN c.valid_to BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS expiring,
                SUM(CASE WHEN c.valid_to < NOW() THEN 1 ELSE 0 END) AS expired,
                SUM(CASE WHEN NOW() BETWEEN c.valid_from AND c.valid_to THEN 1 ELSE 0 END) AS active
            FROM courses c
        ");

        $courseStatusData = $courseStatusQuery->fetch_assoc();

        // Venue Usage Data (For Staff)
        $venueUsageQuery = $conn->query("
            SELECT v.venue_name, COUNT(ts.id) AS usage_count
            FROM training_sessions ts
            JOIN venues v ON ts.venue_id = v.id
            WHERE YEAR(ts.session_date) = $venueUsageYear
            GROUP BY v.venue_name
        ");
        $venueUsageData = $venueUsageQuery->fetch_all(MYSQLI_ASSOC);
    }
}
?>

<style>
    .dashboard-row {
        display: flex;
        justify-content: space-between;
        gap: 20px;
        margin-top: 20px;
    }

    .widget {
        background-color: #ffffff;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .planner-widget {
        max-width: 25%; /* Adjust width for planner */
    }

    .planner-widget h4 {
        margin-top: 20px;
        font-size: 16px;
        color: #555;
        border-top: 1px solid #ddd;
        padding-top: 10px;
    }

    .analytics-widget,
    .popularity-widget {
        max-width: 35%; /* Adjust width for charts */
    }

    .widget h3 {
        margin-bottom: 10px;
        font-size: 18px;
        color: #333;
        text-align: center;
    }

    .planner-events .planner-event {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 10px;
        background: #f9f9f9;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        margin-bottom: 10px;
    }

    .planner-event {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 10px;
        background: #f9f9f9;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .planner-date {
        text-align: center;
        font-size: 18px;
        font-weight: bold;
        color: #333;
        width: 50px;
    }

    .planner-date span {
        display: block;
        font-size: 14px;
        font-weight: normal;
        color: #666;
    }

    .course-name {
        font-size: 16px;
        font-weight: 500;
        color: #333;
    }

    .time {
        font-size: 14px;
        color: #888;
    }

    .analytics-widget canvas,
    .popularity-widget canvas {
        width: 100% !important;
        height: auto !important;
        max-height: 250px;
    }
    
    .filter-year {
        display: flex;
        justify-content: center; /* Center align the form horizontally */
        align-items: center; /* Center align the form vertically (if needed) */
        margin-top: 10px; /* Adjust the spacing as necessary */
    }

    .filter-year form {
        text-align: center;
    }
</style>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
    // Log all GET parameters to the console
    console.log("Current GET parameters:", window.location.search);
    </script>

</head>
<body>
    <div id="dashboard-main">
        <h1>Dashboard</h1>
        <?php if ($role_id == 1): ?>
            <div id="admin-dashboard">
            <div class="overview-cards">
                <!-- 1. Current Venues in Use -->
                <div class="card">
                    <h3>Current Venues in Use</h3>
                    <div class="labs-container">
                        <?php
                        $labs = [4, 7, 8, 9, 10, 11]; // Replace with your lab numbers or IDs
                        foreach ($labs as $lab) {
                            $isActive = in_array("Lab $lab", $activeVenues) ? "active" : "";
                            echo "<div class='lab $isActive' data-active='" . ($isActive ? "true" : "false") . "'>$lab</div>";
                        }
                        ?>
                    </div>
                </div>

                <!-- 2. Active Training Sessions -->
                <div class="card">
                    <h3>Active Training Sessions</h3>
                    <p><?php echo $totalTrainingSessions; ?></p>
                </div>

                <!-- 3. Upcoming Training Sessions -->
                <div class="card">
                    <h3>Upcoming Training Sessions</h3>
                    <p>
                        <?php 
                        $upcomingSessionsQuery = $conn->query("
                            SELECT COUNT(*) AS total 
                            FROM `training_sessions`
                            WHERE `status` = 'upcoming' AND `session_date` > CURDATE()
                        ");
                        $upcomingSessions = $upcomingSessionsQuery->fetch_assoc()['total'] ?? 0;
                        echo $upcomingSessions;
                        ?>
                    </p>
                </div>

                <!-- 4. Completed Training Sessions -->
                <div class="card">
                    <h3>Completed Training Sessions</h3>
                    <p>
                        <?php
                            // Fetch completed training sessions count
                            $completedSessionsQuery = $conn->query("
                                SELECT COUNT(*) AS completed_count
                                FROM `training_sessions`
                                WHERE `status` = 'completed'
                            ");
                            $completedSessions = $completedSessionsQuery->fetch_assoc()['completed_count'] ?? 0;
                                        echo $completedSessions;
                        ?>
                    </p>
                </div>

                <!-- 5. Courses Expiring Soon -->
                <div class="card">
                    <h3>Courses Expiring Soon</h3>
                    <p>
                        <?php 
                        $expiringCoursesQuery = $conn->query("
                            SELECT COUNT(*) AS expiring_count
                            FROM `courses`
                            WHERE `valid_to` BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                        ");
                        $expiringCourses = $expiringCoursesQuery->fetch_assoc()['expiring_count'] ?? 0;
                        echo $expiringCourses;
                        ?>
                    </p>
                </div>

                <!-- 6. Total Assignments -->
                <div class="card">
                    <h3>Total Assignments</h3>
                    <p><?php echo $totalAssignments; ?></p>
                </div>

                <!-- 7. Total Users -->
                <div class="card">
                    <h3>Total Users</h3>
                    <p><?php echo $totalAdmins + $totalStaff + $totalTrainers; ?></p>
                </div>

                <!-- 8. Total Courses -->
                <div class="card">
                    <h3>Total Courses</h3>
                    <p><?php echo $totalCourses; ?></p>
                </div>
            </div>

            <div class="chart-container">
                <h2>System Metrics for <?php echo $selectedYear; ?></h2>
                <div class="filter-year">
                    <form method="GET" action="dashboard.php">
                        <label for="year">Select Year:</label>
                        <select name="year" id="year" onchange="this.form.submit()">
                            <?php foreach ($availableYears as $year): ?>
                                <option value="<?php echo $year; ?>" <?php echo ($year == $selectedYear) ? 'selected' : ''; ?>>
                                    <?php echo $year; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <canvas id="metrics-chart"></canvas>
            </div>
            
            <div class="recent-activities">
                <h2>Recent Activities</h2>
                <div class="notification-container">
                    <?php while ($activity = $recentActivities->fetch_assoc()): ?>
                        <div class="notification-card">
                            <div class="notification-header">
                                <span class="activity-type">
                                    <?php echo isset($activity['activity']) ? htmlspecialchars($activity['activity']) : 'Unknown Activity'; ?>
                                </span>
                                <span class="activity-timestamp">
                                    <?php echo isset($activity['timestamp']) ? date('jS F Y, h:i A', strtotime($activity['timestamp'])) : 'Unknown Time'; ?>
                                </span>
                            </div>
                            <div class="notification-details">
                                <p><?php echo isset($activity['details']) ? $activity['details'] : 'No Details Available'; ?></p>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            </div>
            
            <!-- Staff Dasboard html --------------------------------------------------------->
            <?php elseif ($role_id == 2): ?>
            <div id="staff-dashboard">
                <div class="overview-cards">
                    <!-- Current Venues in Use -->
                    <div class="card">
                        <h2>Current Venues in Use</h2>
                        <div class="labs-container">
                            <?php
                            $labs = [4, 7, 8, 9, 10, 11]; // Define your lab numbers
                            foreach ($labs as $lab) {
                                $isActive = in_array("Lab $lab", $activeVenues) ? "active" : "";
                                echo "<div class='lab $isActive' data-active='" . ($isActive ? "true" : "false") . "'>$lab</div>";
                            }
                            ?>
                        </div>
                    </div>

                    <!-- Active Training Sessions -->
                    <div class="card">
                        <h2>Active Training Sessions</h2>
                        <p><?php echo $totalTrainingSessions; ?></p>
                    </div>

                    <!-- Upcoming Training Sessions -->
                    <div class="card">
                        <h2>Upcoming Training Sessions</h2>
                        <p>
                            <?php 
                            $upcomingSessionsQuery = $conn->query("
                                SELECT COUNT(*) AS total 
                                FROM `training_sessions`
                                WHERE `status` = 'upcoming' AND `session_date` > CURDATE()
                            ");
                            $upcomingSessions = $upcomingSessionsQuery->fetch_assoc()['total'] ?? 0;
                            echo $upcomingSessions;
                            ?>
                        </p>
                    </div>

                    <!-- Completed Training Sessions -->
                    <div class="card">
                        <h2>Completed Training Sessions</h2>
                        <p>
                            <?php
                            $completedSessionsQuery = $conn->query("
                                SELECT COUNT(*) AS completed_count
                                FROM `training_sessions`
                                WHERE `status` = 'completed'
                            ");
                            $completedSessions = $completedSessionsQuery->fetch_assoc()['completed_count'] ?? 0;
                            echo $completedSessions;
                            ?>
                        </p>
                    </div>

                    <!-- Courses Expiring Soon -->
                    <div class="card">
                        <h2>Courses Expiring Soon</h2>
                        <p>
                            <?php 
                            $expiringCoursesQuery = $conn->query("
                                SELECT COUNT(*) AS expiring_count
                                FROM `courses`
                                WHERE `valid_to` BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                            ");
                            $expiringCourses = $expiringCoursesQuery->fetch_assoc()['expiring_count'];
                            echo $expiringCourses;
                            ?>
                        </p>
                    </div>

                    <!-- Total Trainers -->
                    <div class="card">
                        <h2>Total Trainers</h2>
                        <p><?php echo $totalTrainers; ?></p>
                    </div>
                </div>

                <div class="dashboard-row">
                    <div class="chart-card">
                        <h2>Trainer Insight</h2>
                        <canvas id="trainerStatusChart"></canvas>
                    </div>

                    <div class="chart-card">
                        <h2>Course Status</h2>
                        <canvas id="courseStatusChart"></canvas>
                    </div>

                    <div class="chart-card">
                        <h2>Venue Usage</h2>
                        <div class="filter-year">
                        <form method="GET" action="dashboard.php">
                            <label for="venue-usage-year">Select Year:</label>
                            <select name="venue-usage-year" id="venue-usage-year" onchange="this.form.submit()">
                                <?php foreach ($availableYears as $year): ?>
                                    <option value="<?php echo $year; ?>" <?php echo ($year == $venueUsageYear) ? 'selected' : ''; ?>>
                                        <?php echo $year; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="trainer-insight-year" value="<?php echo $trainerInsightYear; ?>">
                            <input type="hidden" name="course-status-year" value="<?php echo $courseStatusYear; ?>">
                        </form>
                            </div>
                        <div id="venue-heatmap"></div>
                    </div>
                </div>

                <!-- Recent Activities for Staff -->
                <div class="recent-activities">
                    <h2>Recent Activities</h2>
                    <div class="notification-container">
                        <?php if ($recentActivities->num_rows > 0): ?>
                            <?php while ($activity = $recentActivities->fetch_assoc()): ?>
                                <div class="notification-card">
                                    <div class="notification-header">
                                        <span class="activity-type">
                                            <?php echo isset($activity['activity']) ? htmlspecialchars($activity['activity']) : 'Unknown Activity'; ?>
                                        </span>
                                        <span class="activity-timestamp">
                                            <?php echo isset($activity['timestamp']) ? date('jS F Y, h:i A', strtotime($activity['timestamp'])) : 'Unknown Time'; ?>
                                        </span>
                                    </div>
                                    <div class="notification-details">
                                        <p><?php echo isset($activity['details']) ? $activity['details'] : 'No Details Available'; ?></p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p>You haven't made any activities yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Trainer Dashboard php------------------------------------------------------------------------------------------------------>
            <?php elseif ($role_id == 3): ?>
                <?php
                // Trainer-specific dashboard code
                // Fetch the total number of courses assigned to the trainer
                $totalAssignedCourses = $conn->query(
                    "SELECT COUNT(*) AS total FROM course_assignments WHERE trainer_id = $user_id"
                )->fetch_assoc()['total'];

                // Fetch the total number of training sessions the trainer is assigned to
                $totalTrainingSessions = $conn->query(
                    "SELECT COUNT(*) AS total FROM training_sessions WHERE trainer_id = $user_id"
                )->fetch_assoc()['total'];

                // Fetch upcoming training sessions for the trainer
                $upcomingSessions = $conn->query(
                    "SELECT ts.session_date, ts.session_end_date, c.course_name, v.venue_name
                    FROM training_sessions ts
                    JOIN courses c ON ts.course_id = c.id
                    JOIN venues v ON ts.venue_id = v.id
                    WHERE ts.trainer_id = $user_id AND ts.session_date >= CURDATE()
                    ORDER BY ts.session_date ASC
                    LIMIT 10"
                );

                // Fetch recent activities for the trainer
                $recentActivities = $conn->query(
                    "SELECT 'Training Session Conducted' AS activity, 
                            CONCAT('Conducted session for course: ', c.course_name, ' at ', v.venue_name) AS details, 
                            ts.session_date AS timestamp
                    FROM training_sessions ts
                    JOIN courses c ON ts.course_id = c.id
                    JOIN venues v ON ts.venue_id = v.id
                    WHERE ts.trainer_id = $user_id
                    ORDER BY ts.session_date DESC
                    LIMIT 10"
                );
                ?>
                <?php
                // Fetch monthly session count for the trainer
                $monthlySessionsQuery = $conn->query("
                    SELECT DATE_FORMAT(session_date, '%Y-%m') AS month, COUNT(*) AS total
                    FROM `training_sessions`
                    WHERE `trainer_id` = $user_id AND YEAR(session_date) = $selectedYear
                    GROUP BY DATE_FORMAT(session_date, '%Y-%m')
                ");            
                $monthlySessions = [];
                while ($row = $monthlySessionsQuery->fetch_assoc()) {
                    $monthlySessions[$row['month']] = $row['total'];
                }
                ?>

                <?php
                // Fetch most popular courses
                $coursePopularityQuery = $conn->query("
                    SELECT c.course_name, COUNT(ts.id) AS total_sessions
                    FROM `training_sessions` ts
                    JOIN courses c ON ts.course_id = c.id
                    WHERE ts.trainer_id = $user_id
                    GROUP BY c.course_name
                    ORDER BY total_sessions DESC
                    LIMIT 5
                ");
                $popularCourses = [];
                while ($row = $coursePopularityQuery->fetch_assoc()) {
                    $popularCourses[$row['course_name']] = $row['total_sessions'];
                }
                ?>

                <?php
                // Fetch sessions for the next 7 days
                $upcomingWeekQuery = $conn->query("
                    SELECT ts.session_date, ts.session_end_date, ts.session_time, ts.session_end_time, c.course_name, v.venue_name
                    FROM `training_sessions` ts
                    JOIN courses c ON ts.course_id = c.id
                    JOIN venues v ON ts.venue_id = v.id
                    WHERE ts.trainer_id = $user_id 
                    AND (
                        (ts.session_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)) OR
                        (ts.session_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)) OR
                        (CURDATE() BETWEEN ts.session_date AND ts.session_end_date)
                    )
                    ORDER BY ts.session_date ASC, ts.session_time ASC
                    LIMIT 2
                ");
                $upcomingWeekSessions = [];
                while ($row = $upcomingWeekQuery->fetch_assoc()) {
                    $upcomingWeekSessions[] = $row;
                }
                ?>

                <!-- Trainer Dashboard html--------------------------------------------------------------------------------------------------------------->
                <div id="trainer-dashboard">
                    <div class="overview-cards">
                        <!-- Total Courses Assigned -->
                        <div class="card">
                            <h2>Total Courses Assigned</h2>
                            <p>
                                <?php 
                                echo $totalAssignedCourses; 
                                ?>
                            </p>
                        </div>

                        <!-- Active Training Sessions -->
                        <div class="card">
                            <h2>Active Training Sessions</h2>
                            <p>
                                <?php 
                                $activeTrainerSessionsQuery = $conn->query("
                                    SELECT COUNT(*) AS active_sessions
                                    FROM `training_sessions`
                                    WHERE `trainer_id` = $user_id 
                                    AND `status` = 'active'
                                    AND CURDATE() BETWEEN `session_date` AND `session_end_date`
                                ");
                                $activeTrainerSessions = $activeTrainerSessionsQuery->fetch_assoc()['active_sessions'] ?? 0;
                                echo $activeTrainerSessions;
                                ?>
                            </p>
                        </div>

                        <!-- Upcoming Training Sessions -->
                        <div class="card">
                            <h2>Upcoming Training Sessions</h2>
                            <p>
                                <?php 
                                $upcomingTrainerSessionsQuery = $conn->query("
                                    SELECT COUNT(*) AS upcoming_sessions
                                    FROM `training_sessions`
                                    WHERE `trainer_id` = $user_id 
                                    AND `status` = 'upcoming'
                                    AND `session_date` > CURDATE()
                                ");
                                $upcomingTrainerSessions = $upcomingTrainerSessionsQuery->fetch_assoc()['upcoming_sessions'] ?? 0;
                                echo $upcomingTrainerSessions;
                                ?>
                            </p>
                        </div>

                        <!-- Completed Training Sessions -->
                        <div class="card">
                            <h2>Completed Training Sessions</h2>
                            <p>
                                <?php 
                                $completedTrainerSessionsQuery = $conn->query("
                                    SELECT COUNT(*) AS completed_sessions
                                    FROM `training_sessions`
                                    WHERE `trainer_id` = $user_id 
                                    AND `status` = 'completed'
                                ");
                                $completedTrainerSessions = $completedTrainerSessionsQuery->fetch_assoc()['completed_sessions'] ?? 0;
                                echo $completedTrainerSessions;
                                ?>
                            </p>
                        </div>
                    </div>

                    <!-- Analytics Container -->
                    <div class="dashboard-row">
                        <!-- Upcoming Week -->
                        <div class="widget planner-widget">
                            <h3>Upcoming Week</h3>
                            <div class="planner-events">
                                <?php if (!empty($upcomingWeekSessions)): ?>
                                    <?php foreach ($upcomingWeekSessions as $session): ?>
                                        <div class="planner-event">
                                            <div class="planner-date">
                                                <?php echo date('D', strtotime($session['session_date'])); ?>
                                                <span><?php echo date('d', strtotime($session['session_date'])); ?></span>
                                            </div>
                                            <div class="planner-details">
                                                <p class="course-name"><?php echo htmlspecialchars($session['course_name']); ?></p>
                                                <p class="time"><?php echo date('h:i A', strtotime($session['session_time'])); ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p>No events this week</p>
                                <?php endif; ?>

                                <!-- Add Recent Activities (Excluded for Trainers) -->
                                <?php if ($role_id != 3): ?>
                                    <h4>Recent Activities</h4>
                                    <?php if ($recentActivities->num_rows > 0): ?>
                                        <?php while ($activity = $recentActivities->fetch_assoc()): ?>
                                            <div class="planner-event">
                                                <div class="planner-date">
                                                    <?php echo date('D', strtotime($activity['timestamp'])); ?>
                                                    <span><?php echo date('d', strtotime($activity['timestamp'])); ?></span>
                                                </div>
                                                <div class="planner-details">
                                                    <p class="course-name"><?php echo htmlspecialchars($activity['activity']); ?></p>
                                                    <p class="time"><?php echo htmlspecialchars($activity['details']); ?></p>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <p>No recent activities</p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Training Summary -->
                        <div class="widget analytics-widget">
                            <h3>Training Summary</h3>
                            <div class="filter-year">
                                <form method="GET" action="dashboard.php">
                                    <label for="year">Select Year:</label>
                                    <select name="year" id="year" onchange="this.form.submit()">
                                        <?php foreach ($availableYears as $year): ?>
                                            <option value="<?php echo $year; ?>" <?php echo ($year == $selectedYear) ? 'selected' : ''; ?>>
                                                <?php echo $year; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </div>
                            <canvas id="sessionsByMonthChart"></canvas>
                        </div>


                        <!-- Course Popularity -->
                        <div class="widget popularity-widget">
                            <h3>Course Popularity</h3>
                            <canvas id="coursePopularityChart"></canvas>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <script>
        <?php if ($role_id == 1): ?>
        const metricsData = {
            labels: <?php echo json_encode(array_keys($userCounts)); ?>,
            datasets: [
                { 
                    label: 'User Registrations', 
                    data: <?php echo json_encode(array_values($userCounts)); ?>, 
                    borderColor: 'rgba(75, 192, 192, 1)', 
                    backgroundColor: 'rgba(75, 192, 192, 0.2)', 
                    tension: 0.4 
                },
                { 
                    label: 'Course Creation', 
                    data: <?php echo json_encode(array_values($courseCounts)); ?>, 
                    borderColor: 'rgba(153, 102, 255, 1)', 
                    backgroundColor: 'rgba(153, 102, 255, 0.2)', 
                    tension: 0.4 
                },
                { 
                    label: 'Trainer-Course Assignments', 
                    data: <?php echo json_encode(array_values($assignmentCounts)); ?>, 
                    borderColor: 'rgba(255, 206, 86, 1)', 
                    backgroundColor: 'rgba(255, 206, 86, 0.2)', 
                    tension: 0.4 
                },
                { 
                    label: 'Training Sessions', 
                    data: <?php echo json_encode(array_values($sessionCounts)); ?>, 
                    borderColor: 'rgba(54, 162, 235, 1)', 
                    backgroundColor: 'rgba(54, 162, 235, 0.2)', 
                    tension: 0.4 
                }
            ]
        };

        const config = {
            type: 'line',
            data: metricsData,
            options: { 
                responsive: true, 
                plugins: { legend: { position: 'top' } }, 
                scales: { y: { beginAtZero: true } } 
            }
        };

        new Chart(document.getElementById('metrics-chart'), config);
        
        <?php elseif ($role_id == 3): ?>
        document.addEventListener('DOMContentLoaded', () => {
            // Sessions by Month Chart
            const sessionsByMonthCtx = document.getElementById('sessionsByMonthChart').getContext('2d');
            new Chart(sessionsByMonthCtx, {
                type: 'bar',
                data: {
                    labels: <?php
                                echo json_encode(
                                    array_map(function($month) {
                                        return date('F', strtotime($month . '-01'));
                                    }, array_keys($monthlySessions))
                                );
                            ?>,
                    datasets: [{
                        label: 'Sessions',
                        data: <?php echo json_encode(array_values($monthlySessions)); ?>,
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        });

        document.addEventListener('DOMContentLoaded', () => {
            const coursePopularityCtx = document.getElementById('coursePopularityChart').getContext('2d');
            new Chart(coursePopularityCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_keys($popularCourses)); ?>,
                    datasets: [{
                        label: 'Sessions',
                        data: <?php echo json_encode(array_values($popularCourses)); ?>,
                        backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'],
                    }]
                }
            });
        });

        <?php elseif ($role_id == 2): ?>
        document.addEventListener('DOMContentLoaded', () => {
            console.log("Trainer Insight Data:", {
            active: <?php echo json_encode($activeTrainers); ?>,
            inactive: <?php echo json_encode($inactiveTrainers); ?>
        });
                        // Trainer Status Chart
                        const trainerStatusCtx = document.getElementById('trainerStatusChart').getContext('2d');
                        new Chart(trainerStatusCtx, {
                            type: 'bar',
                            data: {
                                labels: ['Active Trainers', 'Inactive Trainers'],
                                datasets: [{
                                    label: 'Trainer Count', // ✅ This was missing or undefined
                                    data: [
                                        <?php echo $conn->query("SELECT COUNT(*) FROM `users` WHERE `role_id` = 3 AND `status` = 1")->fetch_row()[0]; ?>,
                                        <?php echo $conn->query("SELECT COUNT(*) FROM `users` WHERE `role_id` = 3 AND `status` = 0")->fetch_row()[0]; ?>
                                    ],
                                    backgroundColor: ['#36A2EB', '#FF6384']
                                }]
                            },
                            options: { responsive: true }
                        });
                    });

                    document.addEventListener('DOMContentLoaded', () => {
                        console.log("Course Status Data:", <?php echo json_encode($courseStatusData); ?>);

                        const courseStatusCtx = document.getElementById('courseStatusChart').getContext('2d');
                        new Chart(courseStatusCtx, {
                            type: 'pie',
                            data: {
                                labels: ['Upcoming', 'Expiring', 'Expired', 'Active'],
                                datasets: [{
                                    data: [
                                        <?php echo json_encode($courseStatusData['upcoming']); ?>,
                                        <?php echo json_encode($courseStatusData['expiring']); ?>,
                                        <?php echo json_encode($courseStatusData['expired']); ?>,
                                        <?php echo json_encode($courseStatusData['active']); ?>
                                    ],
                                    backgroundColor: ['#FFCE56', '#FF6384', '#36A2EB', '#4BC0C0']
                                }]
                            },
                            options: { responsive: true }
                        });
                    });

                document.addEventListener('DOMContentLoaded', () => {
                    // Venue Heatmap
                    const venueData = <?php echo json_encode($venueUsageData); ?>;
                        const heatmap = document.getElementById('venue-heatmap');
                        heatmap.innerHTML = '';

                        venueData.forEach(venue => {
                            const div = document.createElement('div');
                            div.innerText = `${venue.venue_name}: ${venue.usage_count} sessions`;
                            div.style.padding = '10px';
                            div.style.backgroundColor = `rgba(54, 162, 235, ${venue.usage_count / 10})`;
                            div.style.margin = '5px';
                            div.style.borderRadius = '5px';
                            heatmap.appendChild(div);
                        });
                    });
            <?php endif; ?>
        </script>
    <?php include 'footer.php'; ?>
</body>
</html>
