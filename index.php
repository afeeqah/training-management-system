<?php
// Include the configuration file
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>I-World Training Management System</title>
    <link rel="stylesheet" href="styling.css">
</head>
<body>
    <div class="container">
        <!-- Left Section: Login Form -->
        <div class="left-section">
            <div class="logo">
                <img src="images/iwt_black.png" alt="I-World Logo">
            </div>
            <h1>Welcome to the I-World Training Management System</h1>
            <p>Manage training logistics effectively and streamline your workflows.</p>
            <form action="login.php" method="POST">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Enter your username" required>
                
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
                
                <button type="submit">Login</button>
            </form>
            <footer>
                &copy; <?php echo date("Y"); ?> I-World Technology. All rights reserved.
            </footer>
        </div>

        <!-- Right Section -->
        <div class="right-section"></div>
    </div>
</body>
</html>
