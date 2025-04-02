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

$daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

$role_id = $_SESSION['role_id'];
$user_id = $_SESSION['user_id'];

// Ensure only trainers can access this page
if ($role_id != 3) {
    echo "Access denied: Only trainers can access this page. Logged-in User ID: $user_id, Role ID: $role_id";
    exit();
}

// Filters for courses and venues
$courseFilter = isset($_GET['course_filter']) ? intval($_GET['course_filter']) : null;
$venueFilter = isset($_GET['venue_filter']) ? intval($_GET['venue_filter']) : null;

// Calculate the start and end dates for the week
$weekOffset = isset($_GET['week_offset']) ? intval($_GET['week_offset']) : 0;

$referenceDate = new DateTime('now', new DateTimeZone('Asia/Kuala_Lumpur'));

// Calculate start and end dates based on the week offset
$startDate = $referenceDate->modify(($weekOffset * 7) . ' days')->modify('monday this week')->format('Y-m-d');
$endDate = $referenceDate->modify('sunday this week')->format('Y-m-d');

error_log("DEBUG: Start Date: $startDate, End Date: $endDate, Week Offset: $weekOffset");

// Debugging: Log dates for verification
error_log("DEBUG: Start Date: $startDate, End Date: $endDate, Week Offset: $weekOffset");

// Query to fetch the trainer's schedule
$query = "
    SELECT ts.id, ts.session_date, ts.session_end_date, ts.session_time, ts.session_end_time, v.venue_name, c.course_name
    FROM training_sessions ts
    LEFT JOIN venues v ON ts.venue_id = v.id
    JOIN courses c ON ts.course_id = c.id
    WHERE ts.trainer_id = $user_id
    AND (
        (ts.session_date BETWEEN '$startDate' AND '$endDate') OR
        (ts.session_end_date BETWEEN '$startDate' AND '$endDate') OR
        ('$startDate' BETWEEN ts.session_date AND ts.session_end_date)
    )
";

// Apply filters if selected
if ($courseFilter) {
    $query .= " AND c.id = $courseFilter";
}
if ($venueFilter) {
    $query .= " AND v.id = $venueFilter";
}

// Add the ORDER BY clause at the end
$query .= " ORDER BY ts.session_date, ts.session_time ASC";

// Execute the query
$trainerSchedule = $conn->query($query);

// Check for query execution errors
if (!$trainerSchedule) {
    die("Query failed: " . $conn->error);
}

// Initialize variables for totals
$totalSessions = $trainerSchedule ? $trainerSchedule->num_rows : 0;
$totalHours = 0;
$venues = [];

// Process the schedule if sessions exist
if ($trainerSchedule && $trainerSchedule->num_rows > 0) {
    while ($session = $trainerSchedule->fetch_assoc()) {
        // Loop through all days within session range
        $currentDate = strtotime($session['session_date']);
        $sessionEndDate = isset($session['session_end_date']) ? strtotime($session['session_end_date']) : null;
        
        while ($currentDate <= $sessionEndDate) {
            $dayOfWeek = date('l', $currentDate); // Get day of the week
            $sessionsByDay[$dayOfWeek][] = [
                'id' => $session['id'], // Include the id key
                'course_name' => $session['course_name'],
                'venue_name' => $session['venue_name'],
                'session_time' => $session['session_time'],
                'session_end_time' => $session['session_end_time']
            ];            
            $currentDate = strtotime('+1 day', $currentDate); // Increment by 1 day
        }
    }
}

// Count unique venues
$uniqueVenues = count(array_unique($venues));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trainer Schedule</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Schedule Page Styling */
        .schedule-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 20px;
        }
        /* Adjust the overall calendar container height */
        .week-view {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            width: 100%;
            max-width: 1200px;
            height: 350px; /* Reduced height */
            box-sizing: border-box; /* Include padding and border in height */
        }
        .schedule-container h1 {
            color: black; /* Blue text color */
            font-size: 2rem; /* Slightly larger font size */
            font-weight: bold;
            text-align: center; /* Center align text */
            margin: 20px 0; /* Space above and below */
            text-transform: uppercase; /* Optional: Make text uppercase */
            letter-spacing: 1.2px; /* Spaced-out letters */
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2); /* Subtle text shadow */
        }
        /* Style each day column */
        .day-column {
            display: flex;
            flex-direction: column;
            border: 1px solid #ccc;
            border-radius: 5px;
            background: #f9f9f9;
            padding: 0;
            text-align: center;
            overflow: hidden; /* Ensure content stays inside */
        }
        /* Separate container for day names */
        .day-column .day-header {
            background:rgb(18, 50, 86); /* Dark blue background */
            color: #fff; /* White text */
            font-size: 1rem;
            font-weight: bold;
            padding: 10px;
            text-align: center;
            border-bottom: 1px solid #ccc; /* Optional: Add a subtle border for separation */
        }
        /* Session container */
        .day-column .sessions {
            flex: 1; /* Take up remaining space */
            display: flex;
            flex-direction: column;
            justify-content: center; /* Center content vertically */
            align-items: center;
            gap: 10px; /* Add spacing between sessions */
            padding: 10px;
            overflow-y: auto; /* Scroll if content overflows */
        }
        .day-column h3 {
            margin: 0 0 10px;
            font-size: 1.1rem;
        }
        /* Style for each session */
        .session {
            width: 90%; /* Maintain consistent width */
            padding: 10px;
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 5px;
            text-align: left;
            min-height: 80px; /* Fixed height for consistency */
            box-sizing: border-box; /* Include padding in height */
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .session:hover {
            background: #e3f2fd; /* Disable hover effects */
        }
        .session p {
            margin: 5px 0;
            font-size: 0.9rem;
        }
        .session span {
            font-weight: bold;
        }
        /* Color-coded sessions */
        .session[data-time="morning"] {
            background: #d4edda;
            border-color: #28a745;
        }

        .session[data-time="afternoon"] {
            background: #fff3cd;
            border-color: #ffc107;
        }

        .session[data-time="evening"] {
            background: #f8d7da;
            border-color: #dc3545;
        }

        /* Style for "No sessions" message */
        .no-sessions {
            font-size: 0.9rem;
            color: #666;
        }
        /* Style for sessions that have passed */
        .past-session {
            background: #fff3cd; /* Yellow background */
            border-color: #ffc107; /* Yellow border */
        }

        /* Style for active sessions (default) */
        .active-session {
            background: #d4edda; /* Green background */
            border-color: #28a745; /* Green border */
        }
        /* Pagination and week-view alignment */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
            height: 50px; /* Consistent height for buttons */
            box-sizing: border-box;
        }

        .pagination a {
            display: inline-block;
            padding: 10px 20px;
            font-size: 16px;
            text-align: center;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s ease;
            background-color: #0056b3;
            color: #fff;
        }

        .pagination a:hover {
            background-color: #004085;
        }

        /* Style for the filter container */
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            gap: 15px;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            background: #f8f9fa; /* Light gray background */
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); /* Subtle shadow */
        }

        /* Style for dropdowns */
        .filter-form select {
            padding: 10px;
            font-size: 16px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #fff;
            color: #333;
            min-width: 150px;
            outline: none;
            transition: all 0.3s ease;
        }

        .filter-form select:focus {
            border-color: #007bff; /* Blue border on focus */
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
        }

        /* Updated style for the filter button to match "This Week" */
        .filter-form .btn-primary {
            padding: 10px 20px;
            font-size: 16px;
            color: #fff;
            background-color: #0056b3; /* Match the "This Week" button color */
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-form .btn-primary:hover {
            background-color: #004085; /* Darker shade on hover */
        }

        /* Responsive Design: Stack dropdowns vertically on small screens */
        @media (max-width: 768px) {
            .filter-form {
                flex-direction: column;
                gap: 10px;
            }

            .filter-form select, 
            .filter-form .btn-primary {
                width: 100%; /* Full width for small screens */
            }
        }
    </style>
</head>
<body>
    <div class="schedule-container">        
    <h1>Week of 
        <?php echo !empty($startDate) ? date('d M Y', strtotime($startDate)) : 'N/A'; ?> - 
        <?php echo date('d M Y', strtotime($endDate)); // Always use the global $endDate ?>
    </h1>
        <form method="GET" class="filter-form">
            <select name="course_filter">
                <option value="">All Courses</option>
                <?php
                $coursesQuery = $conn->query("SELECT DISTINCT c.id, c.course_name FROM training_sessions ts JOIN courses c ON ts.course_id = c.id WHERE ts.trainer_id = $user_id");
                while ($course = $coursesQuery->fetch_assoc()): ?>
                    <option value="<?php echo $course['id']; ?>"><?php echo $course['course_name']; ?></option>
                <?php endwhile; ?>
            </select>
            <select name="venue_filter">
                <option value="">All Venues</option>
                <?php
                $venuesQuery = $conn->query("SELECT DISTINCT v.id, v.venue_name FROM training_sessions ts JOIN venues v ON ts.venue_id = v.id WHERE ts.trainer_id = $user_id");
                while ($venue = $venuesQuery->fetch_assoc()): ?>
                    <option value="<?php echo $venue['id']; ?>"><?php echo $venue['venue_name']; ?></option>
                <?php endwhile; ?>
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
        </form>

        <div class="week-view">
            <?php
            // Ensure $daysOfWeek is defined
            $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

            // Re-execute the query for fetching the sessions
            $trainerSchedule = $conn->query($query);

            // Check if the query executed correctly
            if ($trainerSchedule && $trainerSchedule->num_rows > 0) {
                // Rebuild sessionsByDay with fresh data
                $sessionsByDay = array_fill_keys($daysOfWeek, []);

                while ($session = $trainerSchedule->fetch_assoc()) {
                    // Calculate the day(s) of the week covered by the session's date range
                    $currentDate = strtotime($session['session_date']);
                    $sessionEndDate = isset($session['session_end_date']) ? strtotime($session['session_end_date']) : null;
                    
                    while ($currentDate <= $sessionEndDate) {
                        $dayOfWeek = date('l', $currentDate); // Get the day name (e.g., "Monday")
                        $sessionsByDay[$dayOfWeek][] = [
                            'id' => $session['id'], // Include the id key
                            'course_name' => $session['course_name'],
                            'venue_name' => $session['venue_name'],
                            'session_time' => $session['session_time'],
                            'session_end_time' => $session['session_end_time']
                        ];                        
                        $currentDate = strtotime('+1 day', $currentDate); // Increment date by 1 day
                    }
                }
            } else {
                // Ensure $sessionsByDay exists even if no sessions found
                $sessionsByDay = array_fill_keys($daysOfWeek, []);
            }

            // Render the week view
            foreach ($daysOfWeek as $day) {
                echo "<div class='day-column'>";
                echo "<div class='day-header'>$day</div>"; // Add day name container
                echo "<div class='sessions'>"; // Start session container
                if (!empty($sessionsByDay[$day])) {
                    foreach ($sessionsByDay[$day] as $session) {
                        // Ensure current date and time are correctly set
                        $currentDateTime = date('Y-m-d H:i:s'); // Current date and time

                        // Construct the full session end date and time
                        if (!empty($session['session_end_date']) && !empty($session['session_end_time'])) {
                            $sessionEndDateTime = $session['session_end_date'] . ' ' . $session['session_end_time'];
                        } else {
                            $sessionEndDateTime = null; // If missing, mark as null
                        }

                        // Debugging: Log current and session end times
                        error_log("DEBUG: Current DateTime: $currentDateTime");
                        error_log("DEBUG: Session End DateTime: $sessionEndDateTime");

                        // Determine the session class
                        if ($sessionEndDateTime && strtotime($sessionEndDateTime) < strtotime($currentDateTime)) {
                            $sessionClass = 'past-session'; // Yellow for past sessions
                        } else {
                            $sessionClass = 'active-session'; // Default green
                        }

                        $timeOfDay = "morning";
                        if (strtotime($session['session_time']) >= strtotime('12:00:00') && strtotime($session['session_time']) < strtotime('18:00:00')) {
                            $timeOfDay = "afternoon";
                        } elseif (strtotime($session['session_time']) >= strtotime('18:00:00')) {
                            $timeOfDay = "evening";
                        }
                    
                        echo "<div class='session $sessionClass' data-time='{$timeOfDay}'>";
                        echo "<p><span>Course:</span> {$session['course_name']}</p>";
                        echo "<p><span>Time:</span> " . date('h:i A', strtotime($session['session_time'])) . " - " . date('h:i A', strtotime($session['session_end_time'])) . "</p>";
                        echo "<p><span>Venue:</span> {$session['venue_name']}</p>";
                        echo "</div>";
                    }                    
                } else {
                    echo "<p class='no-sessions'>No sessions</p>";
                }
                echo "</div>"; // Close session container
                echo "</div>"; // Close day column
            }
            ?>
        </div>

        <div class="pagination">
            <a href="my_schedule.php?week_offset=<?php echo $weekOffset - 1; ?>&course_filter=<?php echo htmlspecialchars($courseFilter); ?>&venue_filter=<?php echo htmlspecialchars($venueFilter); ?>" class="btn"><< Previous</a>
            <a href="my_schedule.php?week_offset=0&course_filter=<?php echo htmlspecialchars($courseFilter); ?>&venue_filter=<?php echo htmlspecialchars($venueFilter); ?>" class="btn btn-active">This Week</a>
            <a href="my_schedule.php?week_offset=<?php echo $weekOffset + 1; ?>&course_filter=<?php echo htmlspecialchars($courseFilter); ?>&venue_filter=<?php echo htmlspecialchars($venueFilter); ?>" class="btn">Next >></a>
        </div>

    </div>
    <?php include 'footer.php'; ?>
</body>
</html>