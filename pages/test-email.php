<?php

// Load PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../assets/libs/phpmailer/src/Exception.php';
require '../assets/libs/phpmailer/src/PHPMailer.php';
require '../assets/libs/phpmailer/src/SMTP.php';

// ===============================
// SMTP CONFIGURATION
// ===============================
$smtpHost = "smtp.hostinger.com";
$smtpPort = 465;
$smtpUser = "admin@animal-bite-center.com";
$smtpPass = "Popoy4682...";

// ===============================
// FORM SUBMISSION
// ===============================
$status = "";
$error  = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $to      = trim($_POST['email_to'] ?? '');
    $subject = trim($_POST['subject'] ?? 'Test Email');
    $body    = trim($_POST['message'] ?? 'Test email from Hostinger SMTP');

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address!";
    } else {

        try {
            $mail = new PHPMailer(true);

            // Server settings
            $mail->isSMTP();
            $mail->Host       = $smtpHost;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpUser;
            $mail->Password   = $smtpPass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
            $mail->Port       = $smtpPort;

            // Recipients
            $mail->setFrom($smtpUser, "Animal Bite Center");
            $mail->addAddress($to);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = nl2br($body);

            $mail->send();
            $status = "Email sent successfully to: <strong>$to</strong>";

        } catch (Exception $e) {
            $error = "Mailer Error: " . $mail->ErrorInfo;
        }
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Test Email</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 20px; border-radius: 8px; max-width: 500px; margin: auto; }
        input, textarea { width: 100%; padding: 10px; margin-bottom: 12px; border: 1px solid #ccc; border-radius: 4px; }
        button { padding: 10px 20px; background: #4CAF50; border: none; color: white; border-radius: 4px; cursor: pointer; }
        button:hover { background: #45a049; }
        .success { background: #d1fae5; padding: 10px; color: #065f46; border-radius: 4px; margin-bottom: 10px; }
        .error { background: #fee2e2; padding: 10px; color: #991b1b; border-radius: 4px; margin-bottom: 10px; }
    </style>
</head>
<body>

<div class="container">
    <h2>Test Email Sending</h2>

    <?php if ($status): ?>
        <div class="success"><?= $status ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>Email To:</label>
        <input type="email" name="email_to" placeholder="recipient@example.com" required>

        <label>Subject:</label>
        <input type="text" name="subject" value="Test Email">

        <label>Message:</label>
        <textarea name="message" rows="4">This is a test email from Hostinger SMTP.</textarea>

        <button type="submit">Send Email</button>
    </form>
</div>

</body>
</html>
