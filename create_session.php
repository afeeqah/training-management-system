<?php
require_once 'config.php';
include 'navbar.php';

// Ensure user is logged in and has the correct role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 2])) {
    echo "<script>alert('Access Denied!'); window.location.href='index.php';</script>";
    exit();
}

// Define error variables
// Define error variables and default values
$sessionDateError = $sessionEndDateError = $sessionTimeError = $sessionEndTimeError = '';
$sessionDate = $sessionEndDate = '';
$sessionTime = '09:00:00'; // Default start time
$sessionEndTime = '17:00:00'; // Default end time
$selectedCourse = $selectedVenue = $selectedTrainer = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedCourse = $_POST['course'] ?? '';
    $selectedVenue = $_POST['venue'] ?? '';
    $selectedTrainer = $_POST['trainer'] ?? '';
    $sessionDate = $_POST['session_date'] ?? '';
    $sessionEndDate = $_POST['session_end_date'] ?? '';
    $sessionTime = $_POST['session_time'] ?? '';
    $sessionEndTime = $_POST['session_end_time'] ?? '';
    $createdBy = $_SESSION['user_id'];

    $valid = true;

    // Check if trainer is assigned to the selected course
    $assignmentCheckQuery = $conn->prepare("SELECT * FROM course_assignments WHERE course_id = ? AND trainer_id = ?");
    $assignmentCheckQuery->bind_param("ii", $selectedCourse, $selectedTrainer);
    $assignmentCheckQuery->execute();
    $assignmentResult = $assignmentCheckQuery->get_result();

    if ($assignmentResult->num_rows === 0) {
        $trainerError = 'The selected trainer is not assigned to this course.';
        $valid = false;
    }

    // Validation
    if (empty($selectedCourse)) {
        $courseError = 'A course must be selected.';
        $valid = false;
    }

    if (empty($selectedVenue)) {
        $venueError = 'A venue must be selected.';
        $valid = false;
    }

    if (empty($selectedTrainer)) {
        $trainerError = 'A trainer must be selected.';
        $valid = false;
    }

    if (empty($sessionDate)) {
        $sessionDateError = 'Session Date is required.';
        $valid = false;
    }

    if (empty($sessionEndDate)) {
        $sessionEndDateError = 'Session End Date is required.';
        $valid = false;
    } elseif ($sessionDate > $sessionEndDate) {
        $sessionEndDateError = 'End Date must be after Start Date.';
        $valid = false;
    }

    if (empty($sessionTime)) {
        $sessionTimeError = 'Start Time is required.';
        $valid = false;
    } elseif (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $sessionTime)) {
        $sessionTimeError = 'Invalid time format. Please use HH:MM:SS.';
        $valid = false;
    }
    
    if (empty($sessionEndTime)) {
        $sessionEndTimeError = 'End Time is required.';
        $valid = false;
    } elseif (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $sessionEndTime)) {
        $sessionEndTimeError = 'Invalid time format. Please use HH:MM:SS.';
        $valid = false;
    } elseif ($sessionTime >= $sessionEndTime) {
        $sessionEndTimeError = 'End Time must be after Start Time.';
        $valid = false;
    }    
    
    // Check if the trainer is already booked for another session during the specified date and time range
    $overlapCheckQuery = $conn->prepare("
    SELECT * FROM training_sessions 
    WHERE trainer_id = ? 
    AND (
        (session_date <= ? AND session_end_date >= ?) OR
        (session_date <= ? AND session_end_date >= ?)
    )
    AND (
        (session_time <= ? AND session_end_time > ?) OR
        (session_time < ? AND session_end_time >= ?)
    )
    ");
    $overlapCheckQuery->bind_param(
        "issssssss",
        $selectedTrainer,
        $sessionEndDate, // End of the new session
        $sessionDate,    // Start of the new session
        $sessionEndDate, // End of the new session
        $sessionDate,    // Start of the new session
        $sessionEndTime, // End time of the new session
        $sessionTime,    // Start time of the new session
        $sessionEndTime, // End time of the new session
        $sessionTime     // Start time of the new session
    );
        
    $overlapCheckQuery->execute();
    $overlapResult = $overlapCheckQuery->get_result();

    if ($overlapResult->num_rows > 0) {
    $trainerError = 'The selected trainer is already booked for another session during this time range.';
    $valid = false;
    }

    if ($valid) {
        try {
            // Determine the status based on the session dates and current time
            $currentDateTime = date('Y-m-d H:i:s');
            $sessionStartDateTime = $sessionDate . ' ' . $sessionTime;
            $sessionEndDateTime = $sessionEndDate . ' ' . $sessionEndTime;
    
            if ($sessionEndDateTime < $currentDateTime) {
                $status = 'completed';
            } elseif ($sessionStartDateTime > $currentDateTime) {
                $status = 'upcoming';
            } else {
                $status = 'active';
            }
    
            // Automatically fetch venue name for the session
            $venueNameQuery = $conn->query("SELECT venue_name FROM venues WHERE id = $selectedVenue");
            $venueNameRow = $venueNameQuery->fetch_assoc();
            $venueName = $venueNameRow['venue_name'] ?? null;
    
            // Insert session into the database
            $query = $conn->prepare(
                "INSERT INTO training_sessions (course_id, venue_id, trainer_id, session_date, session_end_date, session_time, session_end_time, status, created_by, venue_name) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $query->bind_param('iiisssssis', $selectedCourse, $selectedVenue, $selectedTrainer, $sessionDate, $sessionEndDate, $sessionTime, $sessionEndTime, $status, $createdBy, $venueName);
            $query->execute();
    
            echo "<script>alert('Training session created successfully!'); window.location.href='manage_sessions.php';</script>";
        } catch (mysqli_sql_exception $e) {
            echo "<script>alert('Error: {$e->getMessage()}');</script>";
        }
    }    
}

// Fetch data for dropdowns
$courses = $conn->query("SELECT id, course_name FROM courses");
$venues = $conn->query("SELECT id, venue_name FROM venues");
$trainers = $conn->query("SELECT id, username FROM users WHERE role_id = 3");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Training Session</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .error-message {
            color: red;
            font-size: 0.9em;
            margin-top: 5px;
        }
        .scrollable-dropdown {
            width: 100%;
            max-height: 200px;
            overflow-y: auto;
            display: block;
            margin-top: 5px;
        }
    </style>
</head>
<body>
<div class="form-container" style="max-width: 900px;"> <!-- Enlarged width -->
    <h1>Create Training Session</h1>
    <div class="inner-container">
        <form action="" method="POST"> <!-- Start of the form -->
        <div class="form-container selection-container">
                <div class="form-group">
                    <label for="course">Select Course:</label>
                    <div class="dropdown-container">
                        <input type="text" id="search-course" class="dropdown-search" placeholder="Search courses..." onclick="toggleDropdown('course-options')">
                        <div class="dropdown-options" id="course-options">
                            <?php while ($course = $courses->fetch_assoc()): ?>
                                <div class="dropdown-option" onclick="selectOption('course', '<?php echo $course['id']; ?>', '<?php echo htmlspecialchars($course['course_name']); ?>')">
                                    <?php echo htmlspecialchars($course['course_name']); ?>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <input type="hidden" name="course" id="course-input" value="<?php echo htmlspecialchars($selectedCourse); ?>" required>
                    <span class="error-message"><?php echo $courseError ?? ''; ?></span>
                </div>

                <div class="form-group">
                    <label for="venue">Select Venue:</label>
                    <div class="dropdown-container">
                        <input type="text" id="search-venue" class="dropdown-search" placeholder="Search venues..." onclick="toggleDropdown('venue-options')">
                        <div class="dropdown-options" id="venue-options">
                            <?php while ($venue = $venues->fetch_assoc()): ?>
                                <div class="dropdown-option" onclick="selectOption('venue', '<?php echo $venue['id']; ?>', '<?php echo htmlspecialchars($venue['venue_name']); ?>')">
                                    <?php echo htmlspecialchars($venue['venue_name']); ?>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <input type="hidden" name="venue" id="venue-input" value="<?php echo htmlspecialchars($selectedVenue); ?>" required>
                    <span class="error-message"><?php echo $venueError ?? ''; ?></span>
                </div>

                <div class="form-group">
                    <label for="trainer">Select Trainer:</label>
                    <div class="dropdown-container">
                        <input type="text" id="search-trainer" class="dropdown-search" placeholder="Search trainers..." onclick="toggleDropdown('trainer-options')">
                        <div class="dropdown-options" id="trainer-options">
                            <?php while ($trainer = $trainers->fetch_assoc()): ?>
                                <div class="dropdown-option" onclick="selectOption('trainer', '<?php echo $trainer['id']; ?>', '<?php echo htmlspecialchars($trainer['username']); ?>')">
                                    <?php echo htmlspecialchars($trainer['username']); ?>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <input type="hidden" name="trainer" id="trainer-input" value="<?php echo htmlspecialchars($selectedTrainer); ?>" required>
                    <span class="error-message"><?php echo $trainerError ?? ''; ?></span>
                </div>
            </div>
            
            <div class="form-container session-timing-container">
                <h2>Session Timing</h2>
                <div class="form-group-row">
                    <div class="form-group">
                        <label for="session_date">Session Date:</label>
                        <input type="date" name="session_date" id="session_date" value="<?php echo htmlspecialchars($sessionDate); ?>" required>
                        <span class="error-message"><?php echo $sessionDateError; ?></span>
                    </div>
                    <div class="form-group">
                        <label for="session_end_date">Session End Date:</label>
                        <input type="date" name="session_end_date" id="session_end_date" value="<?php echo htmlspecialchars($sessionEndDate); ?>" required>
                        <span class="error-message"><?php echo $sessionEndDateError; ?></span>
                    </div>
                </div>
                <div class="form-group-row">
                    <div class="form-group">
                        <label for="session_time">Start Time:</label>
                        <input type="time" name="session_time" id="session_time" value="<?php echo htmlspecialchars($sessionTime); ?>" step="1" required>
                        <span class="error-message"><?php echo $sessionTimeError; ?></span>
                    </div>
                    <div class="form-group">
                        <label for="session_end_time">End Time:</label>
                        <input type="time" name="session_end_time" id="session_end_time" value="<?php echo htmlspecialchars($sessionEndTime); ?>" step="1" required>
                        <span class="error-message"><?php echo $sessionEndTimeError; ?></span>
                    </div>
                </div>
            </div>
            <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Create</button>
                    <a href="manage_sessions.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<script>
function filterItems(optionClass, searchInputId) {
    const input = document.getElementById(searchInputId).value.toLowerCase();
    const options = document.getElementsByClassName(optionClass);

    for (let option of options) {
        const text = option.textContent || option.innerText;
        option.style.display = text.toLowerCase().includes(input) ? "" : "none";
    }
}

function toggleDropdown(dropdownId) {
    const dropdown = document.getElementById(dropdownId);
    dropdown.classList.toggle('visible');
}

function selectOption(fieldName, value, text) {
    const searchInput = document.getElementById(`search-${fieldName}`);
    const hiddenInput = document.getElementById(`${fieldName}-input`);
    const dropdown = document.getElementById(`${fieldName}-options`);

    // Set the selected text in the visible input
    searchInput.value = text;

    // Set the selected value in the hidden input (used for form submission)
    hiddenInput.value = value;

    // Confirm the hidden input is set (debugging)
    console.log(`${fieldName} selected:`, value);

    // Close the dropdown
    dropdown.classList.remove('visible');
}

// Filter dropdown options based on search input
document.addEventListener('input', function (event) {
    const inputId = event.target.id;
    if (inputId.startsWith('search-')) {
        const field = inputId.replace('search-', '');
        const searchValue = event.target.value.toLowerCase();
        const options = document.querySelectorAll(`#${field}-options .dropdown-option`);
        options.forEach(option => {
            if (option.textContent.toLowerCase().includes(searchValue)) {
                option.style.display = 'block';
            } else {
                option.style.display = 'none';
            }
        });
    }
});

// Close dropdown if clicked outside
document.addEventListener('click', function (event) {
    const dropdownContainers = document.querySelectorAll('.dropdown-container');
    dropdownContainers.forEach(container => {
        if (!container.contains(event.target)) {
            container.querySelector('.dropdown-options').classList.remove('visible');
        }
    });
});

const timeInputs = document.querySelectorAll('input[type="time"]');
timeInputs.forEach(input => {
    input.addEventListener('change', () => {
        const timeRegex = /^\d{2}:\d{2}:\d{2}$/; // Matches HH:MM:SS
        if (!timeRegex.test(input.value)) {
            alert('Please enter a valid time in HH:MM:SS format.');
        }
    });
});

document.querySelector('form').addEventListener('submit', (e) => {
    if (!document.getElementById('course-input').value) {
        e.preventDefault();
        alert('Please select a course.');
    } else if (!document.getElementById('venue-input').value) {
        e.preventDefault();
        alert('Please select a venue.');
    } else if (!document.getElementById('trainer-input').value) {
        e.preventDefault();
        alert('Please select a trainer.');
    }
});


</script>
<?php include 'footer.php'; ?>
</body>
</html>
