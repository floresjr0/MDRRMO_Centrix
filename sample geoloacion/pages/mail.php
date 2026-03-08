<?php
require_once __DIR__ . '/config.php';

// Make sure PHPMailer is installed and autoloaded.
// Recommended: run `composer require phpmailer/phpmailer` and keep vendor/autoload.php in the project root.
//
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
//
require __DIR__ . '/../vendor/autoload.php';

/**
 * Sends an OTP email. Returns true on success, false on failure.
 * Wire this up to PHPMailer once the library is installed.
 */
function send_otp_email(string $toEmail, string $toName, string $otp): bool
{
    // Placeholder implementation: for now, write OTP to a log file so you can test flows
    $logLine = sprintf("[%s] OTP for %s (%s): %s\n", date('Y-m-d H:i:s'), $toName, $toEmail, $otp);
    file_put_contents(__DIR__ . '/otp_test.log', $logLine, FILE_APPEND);
    return true;

    
    // Uncomment and configure this block when PHPMailer is available.
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_SMTP_USERNAME;
        $mail->Password   = MAIL_SMTP_PASSWORD;
        $mail->SMTPSecure = MAIL_SMTP_SECURE;
        $mail->Port       = MAIL_SMTP_PORT;

        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'Your MDRRMO verification code';
        $mail->Body    = 'Your one-time verification code is: <strong>' . htmlspecialchars($otp) . '</strong>';

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('PHPMailer error: ' . $mail->ErrorInfo);
        return false;
    }
    
}

