<?php

function smtpReadResponse($socket)
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    return $response;
}

function smtpCommand($socket, $command, $expectedCodes)
{
    if ($command !== '') {
        fwrite($socket, $command . "\r\n");
    }
    $response = smtpReadResponse($socket);
    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException('Gmail SMTP error: ' . trim($response));
    }
    return $response;
}

function sendGmailOtp($recipient, $otp)
{
    require_once __DIR__ . '/mail_config.php';

    if (($mailConfig['password'] ?? '') === '' || str_contains($mailConfig['password'], 'PASTE_')) {
        throw new RuntimeException('Hindi pa naka-configure ang Gmail App Password.');
    }

    $socket = @fsockopen(
        'ssl://' . $mailConfig['host'],
        $mailConfig['port'],
        $errorNumber,
        $errorMessage,
        15
    );
    if (!$socket) {
        throw new RuntimeException('Hindi makakonekta sa Gmail SMTP.');
    }

    smtpReadResponse($socket);
    smtpCommand($socket, 'EHLO localhost', [250]);
    smtpCommand($socket, 'AUTH LOGIN', [334]);
    smtpCommand($socket, base64_encode($mailConfig['username']), [334]);
    smtpCommand($socket, base64_encode($mailConfig['password']), [235]);
    smtpCommand($socket, 'MAIL FROM:<' . $mailConfig['username'] . '>', [250]);
    smtpCommand($socket, 'RCPT TO:<' . $recipient . '>', [250, 251]);
    smtpCommand($socket, 'DATA', [354]);

    $subject = 'Adelina 3 HOA Password Reset OTP';
    $body = "Your Adelina 3 HOA password reset code is: {$otp}\n\n"
        . "This code expires in 10 minutes. Do not share this code with anyone.";
    $headers = [
        'From: ' . $mailConfig['from_name'] . ' <' . $mailConfig['username'] . '>',
        'To: <' . $recipient . '>',
        'Subject: ' . $subject,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
    ];
    fwrite($socket, implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.\r\n");
    smtpReadResponse($socket);
    smtpCommand($socket, 'QUIT', [221]);
    fclose($socket);
}
