<?php
// Temporary SMTP diagnostic endpoint – remove after debugging
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$user = requireAuth(); // must be logged in

header('Content-Type: text/plain; charset=UTF-8');

echo "=== BeSix Board SMTP Diagnostics ===\n\n";
echo "vendor/autoload.php exists: " . (file_exists(__DIR__ . '/../vendor/autoload.php') ? 'YES' : 'NO') . "\n";
echo "SMTP_HOST: " . (defined('SMTP_HOST') ? SMTP_HOST : '(not set)') . "\n";
echo "SMTP_PORT: " . (defined('SMTP_PORT') ? SMTP_PORT : '(not set)') . "\n";
echo "SMTP_USER: " . (defined('SMTP_USER') ? SMTP_USER : '(not set)') . "\n";
echo "SMTP_PASS: " . (defined('SMTP_PASS') ? (strlen(SMTP_PASS) > 0 ? '(set, ' . strlen(SMTP_PASS) . ' chars)' : '(empty)') : '(not set)') . "\n";
echo "MAIL_FROM: " . (defined('MAIL_FROM') ? MAIL_FROM : '(not set)') . "\n";
echo "\n";

$testTo = $_GET['to'] ?? $user['email'];
echo "Sending test email to: $testTo\n\n";

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    echo "ERROR: vendor/autoload.php not found — PHPMailer not installed on server!\n";
    echo "Make sure CI ran composer install and uploaded vendor/ via FTP.\n";
    exit;
}

require_once $autoload;
$mail = new \PHPMailer\PHPMailer\PHPMailer(true);
$output = [];
$mail->SMTPDebug  = 4;
$mail->Debugoutput = function($str, $level) use (&$output) {
    $output[] = "[{$level}] " . trim($str);
};

try {
    // Use isMail() (local MTA) — same as production sendMail()
    $mail->isMail();
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom(defined('MAIL_FROM') ? MAIL_FROM : 'noreply@besix.cz', 'BeSix Board');
    $mail->addAddress($testTo);
    $mail->isHTML(false);
    $mail->Subject = 'BeSix Board – SMTP test';
    $mail->Body    = 'Tento email potvrzuje, že SMTP funguje správně.';
    $mail->send();
    echo "SUCCESS: Email sent!\n\n";
} catch (\Exception $e) {
    echo "FAILED: " . $mail->ErrorInfo . "\n\n";
}

echo "=== SMTP Debug Log ===\n";
echo implode("\n", $output) . "\n";
