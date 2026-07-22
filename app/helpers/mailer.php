<?php

declare(strict_types=1);

/**
 * Send an email via SMTP or fallback to simulated presentation mode.
 * 
 * Supports configurable environment variables:
 * SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_CRYPTO
 */
function sendSmtpEmail(string $toEmail, string $subject, string $bodyHtml, ?string $otpCode = null): bool
{
    $smtpHost = getenv('SMTP_HOST') ?: '';
    $smtpPort = (int) (getenv('SMTP_PORT') ?: 587);
    $smtpUser = getenv('SMTP_USER') ?: '';
    $smtpPass = getenv('SMTP_PASS') ?: '';
    $smtpCrypto = getenv('SMTP_CRYPTO') ?: 'tls';

    // In local development / presentation mode without active SMTP credentials,
    // we log and flash an on-screen Toast/Notice containing the 6-digit OTP code
    // so the student can demonstrate email authentication live during defense presentations!
    if (empty($smtpHost) || empty($smtpUser)) {
        if ($otpCode !== null && function_exists('setFlash')) {
            setFlash(
                'info',
                "📩 [SMTP Presentation Mode] Verification email sent to <strong>{$toEmail}</strong>. Verification Code: <strong style='font-size: 1.1rem; letter-spacing: 2px; color: #ffc107;'>{$otpCode}</strong>"
            );
        }
        return true;
    }

    // Standard Socket-level SMTP implementation
    try {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ]);

        $prefix = ($smtpCrypto === 'ssl') ? 'ssl://' : '';
        $socket = fsockopen($prefix . $smtpHost, $smtpPort, $errno, $errstr, 10, $context);

        if (!$socket) {
            error_log("SMTP Connection failed: {$errstr} ({$errno})");
            if ($otpCode !== null && function_exists('setFlash')) {
                setFlash('warning', "📩 [SMTP Simulator Notice] Generated Code: {$otpCode} (SMTP socket fallback)");
            }
            return true;
        }

        $read = function () use ($socket): string {
            $res = '';
            while ($line = fgets($socket, 515)) {
                $res .= $line;
                if (substr($line, 3, 1) === ' ') {
                    break;
                }
            }
            return $res;
        };

        $write = function (string $cmd) use ($socket): void {
            fputs($socket, $cmd . "\r\n");
        };

        $read();
        $write("EHLO " . gethostname());
        $read();

        if ($smtpCrypto === 'tls') {
            $write("STARTTLS");
            $read();
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $write("EHLO " . gethostname());
            $read();
        }

        if (!empty($smtpUser)) {
            $write("AUTH LOGIN");
            $read();
            $write(base64_encode($smtpUser));
            $read();
            $write(base64_encode($smtpPass));
            $read();
        }

        $from = getenv('SUPPORT_EMAIL') ?: 'noreply@emperorshotel.com';
        $write("MAIL FROM: <{$from}>");
        $read();
        $write("RCPT TO: <{$toEmail}>");
        $read();
        $write("DATA");
        $read();

        $headers = [
            "From: Emperor's Hotel Support <{$from}>",
            "To: <{$toEmail}>",
            "Subject: {$subject}",
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
        ];

        $message = implode("\r\n", $headers) . "\r\n\r\n" . $bodyHtml . "\r\n.";
        $write($message);
        $read();

        $write("QUIT");
        fclose($socket);

        return true;
    } catch (Throwable $e) {
        error_log("SMTP Error: " . $e->getMessage());
        if ($otpCode !== null && function_exists('setFlash')) {
            setFlash('info', "📩 [SMTP Simulator Fallback] Code for {$toEmail}: {$otpCode}");
        }
        return true;
    }
}
