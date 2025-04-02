<?php
require_once 'config.php';
include 'navbar.php';

// Assume the logged-in user's ID is stored in a session variable
$loggedInUserId = $_SESSION['user_id'];
$userId = isset($_GET['id']) ? intval($_GET['id']) : $loggedInUserId; // Defaults to logged-in user if no ID is provided

// Fetch user data from `users` table
$query = $conn->prepare("SELECT * FROM users WHERE id = ?");
$query->bind_param('i', $userId);
$query->execute();
$userResult = $query->get_result();
$userData = $userResult->fetch_assoc();

if (!$userData) {
    echo "<script>alert('User not found.'); window.location.href='dashboard.php';</script>";
    exit();
}

$firstName = $userData['first_name'];
$lastName = $userData['last_name'];

// Check permissions
$isEditingAnotherAdmin = ($userData['role_id'] == 1 && $_SESSION['role_id'] == 1 && $userId != $loggedInUserId);
if ($_SESSION['role_id'] == 1 && $userData['role_id'] != 1) {
    // Allow admins to edit non-admins
} elseif ($isEditingAnotherAdmin) {
    echo "<script>alert('Permission denied. Admins cannot edit other admins.'); window.location.href='dashboard.php';</script>";
    exit();
}

// Fetch role-specific data if applicable
$roleDetails = null;
if (in_array($userData['role_id'], [2, 3])) { // Staff or Trainer roles
    $roleQuery = $conn->prepare("SELECT * FROM role_details WHERE user_id = ?");
    $roleQuery->bind_param('i', $userId);
    $roleQuery->execute();
    $roleResult = $roleQuery->get_result();
    $roleDetails = $roleResult->fetch_assoc();
}

// Initialize error messages
$usernameError = $passwordError = $emailError = $phoneError = $icPassportError = '';

// Define missing error variables
$firstNameError = '';
$lastNameError = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = empty($_POST['first_name']) ? $userData['first_name'] : trim($_POST['first_name']);
    $lastName = empty($_POST['last_name']) ? $userData['last_name'] : trim($_POST['last_name']);
    $username = empty($_POST['username']) ? $userData['username'] : trim($_POST['username']);
    $password = empty($_POST['password']) ? null : $_POST['password']; // Null means no update
    $confirmPassword = $_POST['confirm_password'];
    $email = empty($_POST['email']) ? $userData['email'] : trim($_POST['email']);
    $phoneNumber = empty($_POST['phone_number']) ? $userData['phone_number'] : trim($_POST['phone_number']);

// Role-specific fields
$position = ($userData['role_id'] === 2 && !empty($_POST['position'])) ? trim($_POST['position']) : ($roleDetails ? $roleDetails['position'] : null);
$icNumber = ($userData['role_id'] === 3 && !empty($_POST['ic_number'])) ? trim($_POST['ic_number']) : ($roleDetails && is_numeric($roleDetails['ic_passport']) ? $roleDetails['ic_passport'] : null);
$passportNumber = ($userData['role_id'] === 3 && !empty($_POST['passport_number'])) ? trim($_POST['passport_number']) : ($roleDetails && !is_numeric($roleDetails['ic_passport']) ? $roleDetails['ic_passport'] : null);
$tttCertification = ($userData['role_id'] === 3 && !empty($_POST['ttt_certification'])) ? trim($_POST['ttt_certification']) : ($roleDetails ? $roleDetails['ttt_status'] : null);

    $valid = true;


    // Validate Username
    if (empty($username)) {
        $usernameError = 'Username is required.';
        $valid = false;
    } elseif (!preg_match('/^(?!.*[._-]{2})[a-zA-Z0-9._-]{3,30}$/', $username) || preg_match('/^[._-]|[._-]$/', $username)) {
        $usernameError = 'Username must be 3–30 characters long, contain only letters, numbers, ".", "_", or "-", and cannot start or end with a special character.';
        $valid = false;
    }

// Validate Password
if (!empty($password)) {
    if (empty($confirmPassword)) {
        $passwordError = '<ul>
                            <li>Confirm Password is required.</li>
                          </ul>';
        $valid = false;
    } elseif ($password !== $confirmPassword) {
        $passwordError = '<ul>
                            <li>Passwords do not match.</li>
                          </ul>';
        $valid = false;
    } elseif (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[\W_]).{8,}$/', $password)) {
        $passwordError = '<ul>
                            <li>Password must be at least 8 characters long.</li>
                            <li>Include at least one uppercase letter.</li>
                            <li>Include at least one lowercase letter.</li>
                            <li>Include at least one number.</li>
                            <li>Include at least one special character.</li>
                          </ul>';
        $valid = false;
    }
}

    // Validate Email
    if (empty($email)) {
        $emailError = 'Email is required.';
        $valid = false;
    }

    // Validate Phone Number
    if (empty($phoneNumber)) {
        $phoneError = 'Phone number is required.';
        $valid = false;
    } elseif (!preg_match('/^\+?[0-9]*$/', $phoneNumber)) {
        $phoneError = 'Invalid phone number format.';
        $valid = false;
    }

    // IC/Passport Validation for Trainers
    if ($userData['role_id'] === 3) {
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
        // Update `users` table
        $passwordQuery = !empty($password) ? ", password = ?" : ""; // Include password update only if provided
        $updateQuery = $conn->prepare("UPDATE users SET username = ?, email = ?, phone_number = ?, first_name = ?, last_name = ? $passwordQuery WHERE id = ?");
        
        if (!empty($password)) {
            $updateQuery->bind_param('ssssssi', $username, $email, $phoneNumber, $firstName, $lastName, $password, $userId);
        } else {
            $updateQuery->bind_param('sssssi', $username, $email, $phoneNumber, $firstName, $lastName, $userId);
        }

        $updateQuery->execute();

        // Update role-specific data
        if ($userData['role_id'] === 2) { // Staff
            $roleUpdateQuery = $conn->prepare("UPDATE role_details SET position = ? WHERE user_id = ?");
            $roleUpdateQuery->bind_param('si', $position, $userId);
            $roleUpdateQuery->execute();
        } elseif ($userData['role_id'] === 3) { // Trainers
            $icOrPassport = !empty($icNumber) ? $icNumber : $passportNumber;
            $roleUpdateQuery = $conn->prepare("UPDATE role_details SET ic_passport = ?, ttt_status = ? WHERE user_id = ?");
            $roleUpdateQuery->bind_param('ssi', $icOrPassport, $tttCertification, $userId);
            $roleUpdateQuery->execute();
        }

        echo "<script>alert('Profile updated successfully!'); window.location.href = 'dashboard.php';</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .error-message {
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
                if (icField.value.length > 0) passportField.value = '';
            } else if (field === 'passport') {
                icField.disabled = passportField.value.length > 0;
                if (passportField.value.length > 0) icField.value = '';
            }
        }
    </script>
</head>
<body>
        <div class="form-container">
            <h1>Edit Profile</h1>
            <form action="" method="POST">
            <div class="form-group">
            <label for="first_name">First Name:</label>
            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($firstName); ?>">
            <span class="error-message"><?php echo $firstNameError; ?></span>
        </div>
        <div class="form-group">
            <label for="last_name">Last Name:</label>
            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($lastName); ?>">
            <span class="error-message"><?php echo $lastNameError; ?></span>
        </div>
        <div class="form-group">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($userData['username']); ?>" <?php echo $isEditingAnotherAdmin ? 'disabled' : ''; ?>>
            <span class="error-message"><?php echo $usernameError; ?></span>
        </div>
        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" placeholder="Enter new password (leave blank to keep current password)">
            <span class="error-message"><?php echo $passwordError; ?></span>
        </div>
        <div class="form-group">
            <label for="confirm_password">Confirm Password:</label>
            <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter new password">
        </div>
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($userData['email']); ?>" required>
            <span class="error-message"><?php echo $emailError; ?></span>
        </div>
        <div class="form-group">
            <label for="phone_number">Phone Number:</label>
            <input type="tel" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($userData['phone_number']); ?>" required>
            <span class="error-message"><?php echo $phoneError; ?></span>
        </div>
        <?php if ($userData['role_id'] === 2): ?>
            <div class="form-group">
                <label for="position">Position:</label>
                <select id="position" name="position" required>
                    <option value="">Select Position</option>
                    <option value="Position 1" <?php echo ($roleDetails['position'] === 'Position 1') ? 'selected' : ''; ?>>Position 1</option>
                    <option value="Position 2" <?php echo ($roleDetails['position'] === 'Position 2') ? 'selected' : ''; ?>>Position 2</option>
                    <option value="Position 3" <?php echo ($roleDetails['position'] === 'Position 3') ? 'selected' : ''; ?>>Position 3</option>
                </select>
            </div>
        <?php elseif ($userData['role_id'] === 3): ?>
            <div class="form-group">
                <label for="ic_number">IC Number:</label>
                <input 
                    type="text" 
                    id="ic_number" 
                    name="ic_number" 
                    value="<?php echo htmlspecialchars(is_numeric($roleDetails['ic_passport']) ? $roleDetails['ic_passport'] : ''); ?>" 
                    oninput="toggleICPassport('ic')" 
                    <?php echo !is_numeric($roleDetails['ic_passport']) ? 'disabled' : ''; ?>
                >
            </div>
            <div class="form-group">
                <label for="passport_number">Passport Number:</label>
                <input 
                    type="text" 
                    id="passport_number" 
                    name="passport_number" 
                    value="<?php echo htmlspecialchars(!is_numeric($roleDetails['ic_passport']) ? $roleDetails['ic_passport'] : ''); ?>" 
                    oninput="toggleICPassport('passport')" 
                    <?php echo is_numeric($roleDetails['ic_passport']) ? 'disabled' : ''; ?>
                >
                <span class="error-message"><?php echo $icPassportError; ?></span>
            </div>
            <div class="form-group">
                <label for="ttt_certification">TTT Certification:</label>
                <input type="text" id="ttt_certification" name="ttt_certification" value="<?php echo htmlspecialchars($roleDetails['ttt_status']); ?>" required>
            </div>
        <?php endif; ?>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
<?php include 'footer.php'; ?>
</body>
</html>
