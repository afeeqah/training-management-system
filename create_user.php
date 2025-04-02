<?php
require_once 'config.php';
include 'navbar.php';

// Check if the role parameter is set
$role = isset($_GET['role']) ? $_GET['role'] : null;

// Redirect if role is missing or invalid
if (!in_array($role, ['admin', 'staff', 'trainer'])) {
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

// Define error variables
$firstNameError = $lastNameError = $usernameError = $passwordError = $emailError = $phoneError = $icPassportError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $firstNameError = $lastNameError = ''; // Define error variables for First and Last Name
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    $email = trim($_POST['email']);
    $phoneNumber = trim($_POST['phone_number']);
    $icNumber = isset($_POST['ic_number']) ? trim($_POST['ic_number']) : '';
    $passportNumber = isset($_POST['passport_number']) ? trim($_POST['passport_number']) : '';
    $currentUser = $_SESSION['user_id']; // Replace with the logged-in user's ID
    $sendCredentials = isset($_POST['send_credentials']);
    error_log("Value of send_credentials: " . ($sendCredentials ? 'checked' : 'not checked'));
    
    // Role-specific fields
    $position = $role === 'staff' ? ($_POST['position'] ?? '-') : '-';
    $tttCertification = $role === 'trainer' ? ($_POST['ttt_certification'] ?? '-') : '-';
    $selectedCourses = $role === 'trainer' ? ($_POST['courses'] ?? []) : [];

    $valid = true;

    // Validate First Name
    if (empty($firstName)) {
        $firstNameError = 'First Name is required.';
        $valid = false;
    }

    // Validate Last Name
    if (empty($lastName)) {
        $lastNameError = 'Last Name is required.';
        $valid = false;
    }

    // Validate username
    if (empty($username)) {
        $usernameError = 'Username is required.';
        $valid = false;
    } else {
        // Check if the username already exists
        $checkQuery = $conn->prepare("SELECT id FROM users WHERE LOWER(username) = LOWER(?)");
        $checkQuery->bind_param('s', $username);
        $checkQuery->execute();
        if ($checkQuery->get_result()->num_rows > 0) {
            $usernameError = 'Username already exists. Please generate a new one.';
            $valid = false;
        } elseif (!preg_match('/^(?!.*[._-]{2})[a-zA-Z0-9._-]{3,30}$/', $username) || preg_match('/^[._-]|[._-]$/', $username)) {
            $usernameError = '<div class="validation-rules">
                                <ul>
                                    <li>Must be 3–30 characters long.</li>
                                    <li>Allowed characters: letters, numbers, ".", "_", or "-".</li>
                                    <li>Cannot have consecutive special characters.</li>
                                    <li>Cannot start or end with a special character.</li>
                                </ul>
                            </div>';
            $valid = false;
        }
    }


    // Validate password
    if (empty($password) || empty($confirmPassword)) {
        $passwordError = 'Password and Confirm Password are required.';
        $valid = false;
    } elseif ($password !== $confirmPassword) {
        $passwordError = 'Passwords do not match.';
        $valid = false;
    } elseif (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[\W_]).{8,}$/', $password)) {
        $passwordError = 'Password must:
                        <ul>
                            <li>Be at least 8 characters long.</li>
                            <li>Include at least one uppercase letter.</li>
                            <li>Include at least one lowercase letter.</li>
                            <li>Include at least one number.</li>
                            <li>Include at least one special character.</li>
                        </ul>';
        $valid = false;
    }

    // Validate email
    if (empty($email)) {
        $emailError = 'Email is required.';
        $valid = false;
    }

    // Validate phone number (optional)
    if (!empty($phoneNumber) && !preg_match('/^\+?[0-9]*$/', $phoneNumber)) {
        $phoneError = 'Invalid phone number format.';
        $valid = false;
    }

    // IC/Passport validation
    if ($role === 'trainer') {
        if (!empty($icNumber) && !empty($passportNumber)) {
            $icPassportError = 'Fill either IC or Passport, not both.';
            $valid = false;
        } elseif (empty($icNumber) && empty($passportNumber)) {
            $icPassportError = 'Either IC or Passport is required.';
            $valid = false;
        } elseif (!empty($icNumber)) {
            if (!preg_match('/^\d{8,12}$/', $icNumber)) {
                $icPassportError = 'IC must contain 8-12 digits.';
                $valid = false;
            }
        } elseif (!empty($passportNumber)) {
            if (!preg_match('/^[a-zA-Z0-9]{6,20}$/', $passportNumber)) {
                $icPassportError = 'Passport must contain 6-20 alphanumeric characters.';
                $valid = false;
            }
        }
    }

    if ($valid) {
        try {
            // Insert into users table
            $query = $conn->prepare("INSERT INTO users (username, password, email, phone_number, role_id, first_name, last_name, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $phoneNumber = !empty($phoneNumber) ? $phoneNumber : null; // Set to NULL if empty
            $query->bind_param('ssssissi', $username, $password, $email, $phoneNumber, $roleId, $firstName, $lastName, $currentUser);            
            $query->execute();

            $userId = $query->insert_id;

            // Insert into role_details table for staff or trainer
            $icOrPassport = !empty($icNumber) ? $icNumber : $passportNumber;
            $roleDetailsQuery = $conn->prepare("INSERT INTO role_details (user_id, username, ic_passport, ttt_status, position) VALUES (?, ?, ?, ?, ?)");
            $roleDetailsQuery->bind_param('issss', $userId, $username, $icOrPassport, $tttCertification, $position);
            $roleDetailsQuery->execute();

            // Insert into course_assignments table for trainer if courses are selected
            if ($role === 'trainer' && !empty($selectedCourses)) {
                foreach ($selectedCourses as $courseId) {
                    // Fetch course_name dynamically
                    $courseNameQuery = $conn->prepare("SELECT course_name FROM courses WHERE id = ?");
                    $courseNameQuery->bind_param('i', $courseId);
                    $courseNameQuery->execute();
                    $courseNameResult = $courseNameQuery->get_result()->fetch_assoc();
                    $courseName = $courseNameResult['course_name'] ?? null; // Default to null if course_name is not found

                    // Determine the role of the current user (Admin/Staff)
                    $assignedByRole = ($_SESSION['role_id'] == 1) ? 'Admin' : 'Staff';

                    // Prepare the insertion query for course_assignments
                    $assignQuery = $conn->prepare("
                        INSERT INTO course_assignments (trainer_id, course_id, assigned_by, course_name, trainer_name, assigned_by_role) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $assignQuery->bind_param('iiisss', $userId, $courseId, $currentUser, $courseName, $username, $assignedByRole);

                    // Execute the query and check for errors
                    if (!$assignQuery->execute()) {
                        error_log("Error inserting into course_assignments: " . $assignQuery->error);
                    }

                    // Clean up courseNameQuery for the next loop
                    $courseNameQuery->close();
                }
            }


            if ($sendCredentials && !empty($email)) {
                error_log("Email to be sent: $email");
                if (empty($email)) {
                    error_log("Email is empty.");
                }
            
                $subject = "Your Account Credentials";
                $message = "Hello $firstName $lastName,\n\n";
                $message .= "Your account for the I-World Technology Training Management System has been created successfully with the following details:\n\n";
                $message .= "Role: " . ucfirst(string: $role) . "\n"; // Add the user's role
                $message .= "Username: $username\n";
                $message .= "Password: $password\n\n";
                $message .= "Please change your credentials as soon as possible to your preference.\n\n";
                $message .= "Please keep this information secure.\n\n";
                $message .= "Thank you.";
                             
            
                $headers = "From: nurulafeeqah2811@gmail.com\r\n";
                $headers .= "Reply-To: nurulafeeqah2811@gmail.com\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            
                $mailResult = mail($email, $subject, $message, $headers);
            
                if ($mailResult) {
                    echo "<script>
                        alert('User created successfully and credentials sent to email!');
                        window.location.href = 'manage_users.php?role=$role';
                    </script>";
                    exit();
                } else {
                    echo "<script>
                        alert('User created successfully! However, the credentials could not be emailed.');
                        window.location.href = 'manage_users.php?role=$role';
                    </script>";
                    exit();
                }
            } else {
                echo "<script>
                    alert('User created successfully!');
                    window.location.href = 'manage_users.php?role=$role';
                </script>";
                exit();
            }
            

                                    } catch (mysqli_sql_exception $e) {
                                        echo "<script>alert('Error: {$e->getMessage()}');</script>";
                                    }
                                }
                            }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create <?php echo ucfirst($role); ?></title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .error-message, .validation-rules {
            color: red;
            font-size: 0.9em;
            margin-top: 5px;
        }
    </style>
    <script>
        function toggleICPassport(field) {
            const icField = document.getElementById('ic_number');
            const passportField = document.getElementById('passport_number');

            if (field === 'ic') {
                passportField.disabled = icField.value.length > 0;
            } else if (field === 'passport') {
                icField.disabled = passportField.value.length > 0;
            }
        }
    </script>
</head><br><br>
<body>
<div class="form-container" style="max-width: 900px;"> <!-- Enlarged width -->
    <h1>Create <?php echo ucfirst($role); ?></h1>
    <form action="" method="POST" onsubmit="return confirmCheckbox();">
        <!-- Basic Information Container -->
        <div class="form-container basic-info-container">
            <h2>Basic Information</h2>
            <div class="form-group-row">
                <div class="form-group">
                    <label for="first_name">First Name:</label>
                    <input type="text" id="first_name" name="first_name" placeholder="Enter first name" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                    <span class="error-message"><?php echo $firstNameError; ?></span>
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name:</label>
                    <input type="text" id="last_name" name="last_name" placeholder="Enter last name" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                    <span class="error-message"><?php echo $lastNameError; ?></span>
                </div>
            </div>
            <div class="form-group-row">
                <div class="form-group">
                    <label for="username">Username:
                        <button type="button" class="btn btn-secondary small" onclick="generateUsername()">Auto-Generate</button>
                    </label>
                    <input type="text" id="username" name="username" placeholder="Enter username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    <span class="error-message"><?php echo $usernameError; ?></span>
                </div>
                <div class="form-group">
                    <label for="password">Password:
                        <button type="button" class="btn btn-secondary small" onclick="generatePassword()">Auto-Generate</button>
                    </label>
                    <input type="password" id="password" name="password" placeholder="Enter password">
                    <span class="error-message"><?php echo $passwordError; ?></span>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter password">
                    <span class="error-message"><?php echo $passwordError; ?></span>
                </div>
            </div>
            <div class="form-group-row">
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" placeholder="Enter email address" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    <span class="error-message"><?php echo $emailError; ?></span>
                </div>
                <div class="form-group">
                    <label for="phone_number">Phone Number:</label>
                    <input type="tel" id="phone_number" name="phone_number" placeholder="Enter phone number" value="<?php echo htmlspecialchars($_POST['phone_number'] ?? ''); ?>">
                    <span class="error-message"><?php echo $phoneError; ?></span>
                </div>
            </div>
        </div>

        <!-- Role-Specific Information Container -->
        <?php if ($role === 'staff' || $role === 'trainer'): ?>
            <div class="form-container role-info-container">
                <h2>Role-Specific Information</h2>
                <?php if ($role === 'staff'): ?>
                    <div class="form-group">
                        <label for="position">Position:</label>
                        <select id="position" name="position" required>
                            <option value="" disabled selected>Select Position</option>
                            <option value="Position 1" <?php echo (isset($_POST['position']) && $_POST['position'] === 'Position 1') ? 'selected' : ''; ?>>Position 1</option>
                            <option value="Position 2" <?php echo (isset($_POST['position']) && $_POST['position'] === 'Position 2') ? 'selected' : ''; ?>>Position 2</option>
                        </select>
                    </div>
                <?php elseif ($role === 'trainer'): ?>
                    <div class="form-group-row">
                        <div class="form-group">
                            <label for="ic_number">IC Number:</label>
                            <input type="text" id="ic_number" name="ic_number" placeholder="Enter IC Number" value="<?php echo htmlspecialchars($_POST['ic_number'] ?? ''); ?>" oninput="toggleICPassport('ic')">
                        </div>
                        <div class="form-group">
                            <label for="passport_number">Passport Number:</label>
                            <input type="text" id="passport_number" name="passport_number" placeholder="Enter Passport Number" value="<?php echo htmlspecialchars($_POST['passport_number'] ?? ''); ?>" oninput="toggleICPassport('passport')">
                            <span class="error-message"><?php echo $icPassportError; ?></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="ttt_certification">TTT Certification:</label>
                        <input type="text" id="ttt_certification" name="ttt_certification" placeholder="Enter TTT Certification" value="<?php echo htmlspecialchars($_POST['ttt_certification'] ?? ''); ?>">
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <br>

        <!-- Send Credentials Checkbox -->
        <div class="form-group">
            <label for="send_credentials" class="trainer-item">
                <span>Send credentials to user via email</span>
                <input type="checkbox" id="send_credentials" name="send_credentials">
            </label>
        </div>

        <!-- Centered Form Actions -->
        <div class="form-actions" style="display: flex; justify-content: center; gap: 20px; margin-top: 20px;">
            <button type="submit" class="btn btn-primary">Create</button>
            <a href="manage_users.php?role=<?php echo $role; ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div><br><br>

<script>
    function filterCourses() {
        const input = document.getElementById("search-courses").value.toLowerCase();
        const courses = document.getElementsByClassName("trainer-item");
        for (let course of courses) {
            const text = course.textContent || course.innerText;
            course.style.display = text.toLowerCase().includes(input) ? "" : "none";
        }
    }

    function generateUsername() {
    const firstName = document.getElementById("first_name").value.trim().replace(/\s+/g, '');
    const lastName = document.getElementById("last_name").value.trim().replace(/\s+/g, '');
    const usernameField = document.getElementById("username");
    const randomNum = Math.floor(100 + Math.random() * 900);

    if (firstName && lastName) {
        const generatedUsername = `${firstName.toLowerCase()}.${lastName.toLowerCase()}${randomNum}`;
        usernameField.value = generatedUsername;
    } else {
        alert("Please enter both First Name and Last Name before generating the username.");
    }
}

function generatePassword() {
    const passwordField = document.getElementById("password");
    const confirmPasswordField = document.getElementById("confirm_password");
    const generatedPasswordDisplay = document.getElementById("generated-password-display");
    const generatedPasswordText = document.getElementById("generated-password");

    const uppercase = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    const lowercase = "abcdefghijklmnopqrstuvwxyz";
    const numbers = "0123456789";
    const special = "!@#$%^&*()";
    const allChars = uppercase + lowercase + numbers + special;

    let password = "";
    // Ensure at least one character from each required set
    password += uppercase[Math.floor(Math.random() * uppercase.length)];
    password += lowercase[Math.floor(Math.random() * lowercase.length)];
    password += numbers[Math.floor(Math.random() * numbers.length)];
    password += special[Math.floor(Math.random() * special.length)];

    // Fill the rest of the password with random characters
    for (let i = 4; i < 8; i++) {
        password += allChars[Math.floor(Math.random() * allChars.length)];
    }

    // Shuffle the password to ensure randomness
    password = password.split('').sort(() => Math.random() - 0.5).join('');

    // Set the password in both fields
    passwordField.value = password;
    confirmPasswordField.value = password;

    // Display the generated password
    generatedPasswordText.textContent = password;
    generatedPasswordDisplay.style.display = "block";
}

function confirmCheckbox() {
    const sendCredentials = document.getElementById('send_credentials');
    if (sendCredentials.checked) {
        alert('The "Send Credentials" checkbox is checked.');
    } else {
        alert('The "Send Credentials" checkbox is NOT checked.');
    }
    return true; // Allows the form to submit
}

</script>
<?php include 'footer.php'; ?>
</body>
</html>