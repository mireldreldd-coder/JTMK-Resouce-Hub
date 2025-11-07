<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

$mail = new PHPMailer(true);

try {
    // ===== SERVER SETTINGS =====
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';          // Gmail SMTP server
    $mail->SMTPAuth   = true;
    $mail->Username   = 'jtmkreshub@gmail.com';    // Your Gmail address
    $mail->Password   = 'kubmydkxdretsbis';        // <-- App password (no spaces!)
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Optional for debugging
    $mail->SMTPDebug  = 2; // 0 = off, 2 = detailed debug
    $mail->Debugoutput = 'html';

    // ===== RECIPIENTS =====
    $mail->setFrom('jtmkreshub@gmail.com', 'System Notification');
    $mail->addAddress('17DDT23F1112@student.psis.edu.my', 'Test Recipient');

    // ===== CONTENT =====
    $mail->isHTML(true);
    $mail->Subject = '✅ PHPMailer Gmail SMTP Test';
    $mail->Body    = '<h2>It works!</h2><p>This is a test email sent using <b>Gmail SMTP + PHPMailer</b>.</p>';
    $mail->AltBody = 'It works! This is a plain text version of the email.';

    // ===== SEND =====
    $mail->send();
    echo '✅ Email has been sent successfully!';
} catch (Exception $e) {
    echo "❌ Email could not be sent. Error: {$mail->ErrorInfo}";
}
