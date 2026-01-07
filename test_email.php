<?php
require 'vendor/autoload.php';
use Dotenv\Dotenv;
use PHPMailer\PHPMailer\PHPMailer;
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();
try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $_ENV['SMTP_HOST'] ?? 'smtp.mail.me.com';
    $mail->SMTPAuth = true;
    $mail->Username = $_ENV['SMTP_USERNAME'] ?? 'yiwen1333@icloud.com';
    $mail->Password = $_ENV['SMTP_PASSWORD'] ?? 'bpgt-uhtr-jdkx-lajg';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->setFrom($_ENV['SMTP_USERNAME'] ?? 'yiwen1333@icloud.com', 'Test Store');
    $mail->addAddress('yiwen1333@icloud.com');
    $mail->Subject = 'Test Email';
    $mail->Body = 'This is a test email from PHPMailer.';
    $mail->send();
    echo 'Email sent successfully';
} catch (Exception $e) {
    echo 'Email failed: ' . $e->getMessage();
}
?>