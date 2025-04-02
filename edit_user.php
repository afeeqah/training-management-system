<?php
require_once 'config.php';
include 'navbar.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id'])) {
    echo "<script>alert('Unauthorized access. Please log in.'); window.location.href='index.php';</script>";
    exit();
}

$loggedInUserId = $_SESSION['user_id'];
$loggedInRoleId = $_SESSION['role_id'];

// Ensure a valid `user_id` is passed
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>alert('Invalid user ID.'); window.location.href='manage_users.php?role=staff';</script>";
    exit();
}

$userIdToEdit = intval($_GET['id']);

// Fetch user data
$query = $conn->prepare("SELECT * FROM users WHERE id = ?");
$query->bind_param('i', $userIdToEdit);
$query->execute();
$userResult = $query->get_result();
$userData = $userResult->fetch_assoc();

if (!$userData) {
    echo "<script>alert('User not found.'); window.location.href='manage_users.php?role=staff';</script>";
    exit();
}

// Fetch the role from `role_id`
$roleMapping = [
    1 => 'admin',
    2 => 'staff',
    3 => 'trainer',
];
$role = $roleMapping[$userData['role_id']] ?? null;

if (!$role) {
    echo "<script>alert('Invalid role specified.'); window.location.href='manage_users.php?role=staff';</script>";
    exit();
}

// Fetch role-specific data if applicable
$roleDetails = null;
if (in_array($userData['role_id'], [2, 3])) {
    $roleQuery = $conn->prepare("SELECT * FROM role_details WHERE user_id = ?");
    $roleQuery->bind_param('i', $userIdToEdit);
    $roleQuery->execute();
    $roleResult = $roleQuery->get_result();
    $roleDetails = $roleResult->fetch_assoc();
}

// Initialize error variables
$usernameError = $passwordError = $emailError = $phoneError = $icPassportError = '';
$validationRules = '';
$courses = []; // To store assigned courses for trainer

if ($role === 'trainer') {
    // Fetch assigned courses
    $courseQuery = $conn->prepare("SELECT course_id FROM course_assignments WHERE trainer_id = ?");
    $courseQuery->bind_param('i', $userIdToEdit);
    $courseQuery->execute();
    $courseResult = $courseQuery->get_result();
    while ($course = $courseResult->fetch_assoc()) {
        $courses[] = $course['course_id'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $email = trim($_POST['email']);
    $phoneNumber = trim($_POST['phone_number']);
    $icNumber = $_POST['ic_number'] ?? '';
    $passportNumber = $_POST['passport_number'] ?? '';
    $position = $role === 'staff' ? ($_POST['position'] ?? '-') : '-';
    $tttCertification = $role === 'trainer' ? ($_POST['ttt_certification'] ?? '-') : '-';
    $selectedCourses = $role === 'trainer' ? ($_POST['courses'] ?? []) : [];
    $sendCredentials = isset($_POST['send_credentials']);

    $valid = true;

    // Validate username
    if (empty($username)) {
        $usernameError = 'Username is required.';
        $valid = false;
    } elseif (!preg_match('/^(?!.*[._-]{2})[a-zA-Z0-9._-]{3,30}$/', $username) || preg_match('/^[._-]|[._-]$/', $username) || preg_match('/\s/', $username)) {
        $validationRules = '<ul>
            <li>Must be 3–30 characters long.</li>
            <li>Allowed characters: letters, numbers, ".", "_", or "-".</li>
            <li>Cannot have consecutive special characters.</li>
            <li>Cannot start or end with a special character.</li>
            <li>Cannot contain spaces.</li>
        </ul>';
        $usernameError = '';
        $valid = false;
    } else {
        $checkQuery = $conn->prepare("SELECT id FROM users WHERE LOWER(username) = LOWER(?) AND id != ?");
        $checkQuery->bind_param('si', $username, $userIdToEdit);
        $checkQuery->execute();
        if ($checkQuery->get_result()->num_rows > 0) {
            $usernameError = 'Username already exists.';
            $valid = false;
        }
    }

    // Validate password
    if (!empty($password)) {
        if ($password !== $confirmPassword) {
            $passwordError = 'Passwords do not match.';
            $valid = false;
        } elseif (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[\W_]).{8,}$/', $password)) {
            $passwordError = 'Password must contain at least 8 characters, one uppercase letter, one lowercase letter, one number, and one special character.';
            $valid = false;
        }
    }

    // Validate email
    if (empty($email)) {
        $emailError = 'Email is required.';
        $valid = false;
    }

    // Validate phone number
    if (empty($phoneNumber)) {
        $phoneError = 'Phone number is required.';
        $valid = false;
    } elseif (!preg_match('/^\+?[0-9]*$/', $phoneNumber)) {
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
        } elseif (!empty($icNumber) && !preg_match('/^\d{8,12}$/', $icNumber)) {
            $icPassportError = 'IC must contain 8-12 digits.';
            $valid = false;
        } elseif (!empty($passportNumber) && !preg_match('/^[a-zA-Z0-9]{6,20}$/', $passportNumber)) {
            $icPassportError = 'Passport must contain 6-20 alphanumeric characters.';
            $valid = false;
        }
    }

    if ($valid) {
        try {
            // Update `users` table
            $updateQuery = $conn->prepare("UPDATE users SET username = ?, password = IF(? = '', password, ?), email = ?, phone_number = ? WHERE id = ?");
            $updateQuery->bind_param('sssssi', $username, $password, $password, $email, $phoneNumber, $userIdToEdit);
            $updateQuery->execute();

            // Update `role_details` table for staff or trainer
            $icOrPassport = !empty($icNumber) ? $icNumber : $passportNumber;
            if ($role === 'staff') {
                $roleUpdateQuery = $conn->prepare("UPDATE role_details SET position = ? WHERE user_id = ?");
                $roleUpdateQuery->bind_param('si', $position, $userIdToEdit);
                $roleUpdateQuery->execute();
            } elseif ($role === 'trainer') {
                $roleUpdateQuery = $conn->prepare("UPDATE role_details SET ic_passport = ?, ttt_status = ? WHERE user_id = ?");
                $roleUpdateQuery->bind_param('ssi', $icOrPassport, $tttCertification, $userIdToEdit);
                $roleUpdateQuery->execute();

                // Update course assignments
                $conn->query("DELETE FROM course_assignments WHERE trainer_id = $userIdToEdit");
                foreach ($selectedCourses as $courseId) {
                    $assignQuery = $conn->prepare("INSERT INTO course_assignments (trainer_id, course_id, assigned_by) VALUES (?, ?, ?)");
                    $assignQuery->bind_param('iii', $userIdToEdit, $courseId, $loggedInUserId);
                    $assignQuery->execute();
                }
            }

            // Send updated credentials if checkbox is checked
            if ($sendCredentials && !empty($email)) {
                $subject = "Your Updated Account Credentials";
                $message = "Hello {$userData['first_name']} {$userData['last_name']},\n\n";
                $message .= "Your account for the I-World Technology Training Management System has been updated successfully with the following details:\n\n";
                $message .= "Role: " . ucfirst($role) . "\n";
                $message .= "Username: {$username}\n";
                if (!empty($password)) {
                    $message .= "Password: {$password}\n";
                }
                $message .= "\nPlease keep this information secure.\n\nThank you.";

                $headers = "From: noreply@iworld.com\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

                if (!mail($email, $subject, $message, $headers)) {
                    echo "<script>alert('User updated successfully! However, the credentials could not be emailed.');</script>";
                }
            }

            echo "<script>alert('User updated successfully!'); window.location.href='manage_users.php?role={$role}';</script>";
            exit();
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
    <title>Edit <?php echo ucfirst($role); ?></title>
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

        function filterCourses() {
            const input = document.getElementById("search-courses").value.toLowerCase();
            const courses = document.getElementsByClassName("trainer-item");
            for (let course of courses) {
                const text = course.textContent || course.innerText;
                course.style.display = text.toLowerCase().includes(input) ? "" : "none";
            }
        }

        function generateUsername() {
            const firstName = "<?php echo addslashes($userData['first_name'] ?? ''); ?>";
            const lastName = "<?php echo addslashes($userData['last_name'] ?? ''); ?>";
            const usernameField = document.getElementById("username");
            const randomNum = Math.floor(100 + Math.random() * 900);

            if (firstName && lastName) {
                const generatedUsername = `${firstName.toLowerCase()}.${lastName.toLowerCase()}${randomNum}`;
                usernameField.value = generatedUsername;
            } else {
                alert("Please ensure the first and last names are available to generate a username.");
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
    </script>
</head>
<body><br>
<div class="form-container" style="max-width: 900px;">
    <h1>Edit <?php echo ucfirst($role); ?></h1>
    <form action="" method="POST">
        <!-- Basic Information Container -->
        <div class="form-container basic-info-container">
                    <h2>Basic Information</h2>
                    <div class="form-group-row">
            <div class="form-group">
                <label for="first_name">First Name:</label>
                <input type="text" id="first_name" name="first_name" placeholder="Enter first name" value="<?php echo htmlspecialchars($_POST['first_name'] ?? $userData['first_name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="last_name">Last Name:</label>
                <input type="text" id="last_name" name="last_name" placeholder="Enter last name" value="<?php echo htmlspecialchars($_POST['last_name'] ?? $userData['last_name']); ?>" required>
            </div>
        </div>
        <div class="form-group-row">
            <div class="form-group">
                <label for="username">Username:
                    <button type="button" class="btn btn-secondary small" onclick="generateUsername()">Auto-Generate</button>
                </label>
                <input type="text" id="username" name="username" placeholder="Enter username" value="<?php echo htmlspecialchars($_POST['username'] ?? $userData['username']); ?>" required>
                <span class="error-message"><?php echo $usernameError; ?></span>
            </div>
            <div class="form-group">
                <label for="password">Password:
                    <button type="button" class="btn btn-secondary small" onclick="generatePassword()">Auto-Generate</button>
                </label>
                <input type="password" id="password" name="password" placeholder="Enter new password (leave blank to keep current password)">
                <span class="error-message"><?php echo $passwordError; ?></span>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter new password">
            </div>
        </div>
            <div class="form-group-row">
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" placeholder="Enter email address" value="<?php echo htmlspecialchars($_POST['email'] ?? $userData['email']); ?>" required>
                    <span class="error-message"><?php echo $emailError; ?></span>
                </div>
                <div class="form-group">
                    <label for="phone_number">Phone Number:</label>
                    <input type="tel" id="phone_number" name="phone_number" placeholder="Enter phone number" value="<?php echo htmlspecialchars($_POST['phone_number'] ?? $userData['phone_number']); ?>" required>
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
                        <select id="position" name="position">
                            <option value="" disabled selected>Select Position</option>
                            <option value="Position 1" <?php echo ($roleDetails['position'] === 'Position 1') ? 'selected' : ''; ?>>Position 1</option>
                            <option value="Position 2" <?php echo ($roleDetails['position'] === 'Position 2') ? 'selected' : ''; ?>>Position 2</option>
                        </select>
                    </div>
                <?php elseif ($role === 'trainer'): ?>
                    <div class="form-group-row">
                        <div class="form-group">
                            <label for="ic_number">IC Number:</label>
                            <input type="text" id="ic_number" name="ic_number" placeholder="Enter IC Number" value="<?php echo htmlspecialchars($_POST['ic_number'] ?? (!empty($roleDetails['ic_passport']) && is_numeric($roleDetails['ic_passport']) ? $roleDetails['ic_passport'] : '')); ?>" oninput="toggleICPassport('ic')">
                        </div>
                        <div class="form-group">
                            <label for="passport_number">Passport Number:</label>
                            <input type="text" id="passport_number" name="passport_number" placeholder="Enter Passport Number" value="<?php echo htmlspecialchars($_POST['passport_number'] ?? (!empty($roleDetails['ic_passport']) && !is_numeric($roleDetails['ic_passport']) ? $roleDetails['ic_passport'] : '')); ?>" oninput="toggleICPassport('passport')">
                            <span class="error-message"><?php echo $icPassportError; ?></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="ttt_certification">TTT Certification:</label>
                        <input type="text" id="ttt_certification" name="ttt_certification" placeholder="Enter TTT Certification" value="<?php echo htmlspecialchars($_POST['ttt_certification'] ?? $roleDetails['ttt_status']); ?>">
                    </div>
                <?php endif; ?>
            </div><br>
        <?php endif; ?>

        <!-- Send Credentials Checkbox -->
        <div class="form-group">
            <label for="send_credentials" class="trainer-item">
                <span>Send updated credentials to user via email</span>
                <input type="checkbox" id="send_credentials" name="send_credentials">
            </label>
        </div>

        <div class="form-actions" style="display: flex; justify-content: center; gap: 20px; margin-top: 20px;">
        <?php if ($userData['status'] == 1): // Check if the user is active ?>
            <a href="deactivate_user.php?id=<?php echo $userIdToEdit; ?>&role=<?php echo urlencode($role); ?>" 
            style="color: white; background-color: red; padding: 10px 15px; text-decoration: none; border-radius: 5px;">
            Deactivate
            </a>
        <?php else: ?>
            <a href="activate_user.php?id=<?php echo $userIdToEdit; ?>&role=<?php echo urlencode($role); ?>" 
            style="color: white; background-color: green; padding: 10px 15px; text-decoration: none; border-radius: 5px;">
            Activate
            </a>
        <?php endif; ?>
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="manage_users.php?role=<?php echo htmlspecialchars($role); ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div><br><br>
<?php include 'footer.php'; ?>
</body>
</html>
