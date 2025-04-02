<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$to = "spongefifie@gmail.com";
$subject = "Test Email from XAMPP";
$message = "This is a test email sent using XAMPP and Gmail SMTP.";
$headers = "From: nurulafeeqah2811@gmail.com";

if (mail($to, $subject, $message, $headers)) {
    echo "Email sent successfully!";
} else {
    echo "Failed to send email.";
    error_log("Mail function failed.");
}
?>
