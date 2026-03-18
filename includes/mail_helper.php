<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

function sendMail($to, $subject, $body, $isHtml = true) {
    global $conn;
    $mail = new PHPMailer(true);
    $status = 'failed';
    $errorMessage = null;

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        // Recipients
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to);

        // Content
        $mail->isHTML($isHtml);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        $status = 'success';
        return true;
    } catch (Exception $e) {
        $status = 'failed';
        $errorMessage = $mail->ErrorInfo;
        error_log("Message could not be sent. Mailer Error: {$errorMessage}");
        return false;
    } finally {
        // Log to database
        try {
            $stmt = $conn->prepare("INSERT INTO tbl_mail_log (recipient, subject, body, status, error_message) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $to, $subject, $body, $status, $errorMessage);
            $stmt->execute();
            $stmt->close();

        } catch (Exception $logEx) {
            error_log("Mail logging failed: " . $logEx->getMessage());
        }
    }
}
