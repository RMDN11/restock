<?php
/**
 * Minimal SMTP client for the RE-STOCK password recovery mail.
 * Uses implicit TLS on port 465, matching cPanel's recommended settings.
 */

function restock_mail_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config/mail.php';
    }
    return $config;
}

function restock_smtp_read($socket): string
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (preg_match('/^\d{3} /', $line)) {
            break;
        }
    }
    return $response;
}

function restock_smtp_expect($socket, array $codes): string
{
    $response = restock_smtp_read($socket);
    $code = (int) substr(trim($response), 0, 3);
    if (!in_array($code, $codes, true)) {
        throw new RuntimeException('SMTP server response tidak sesuai: ' . trim($response));
    }
    return $response;
}

function restock_smtp_command($socket, string $command, array $codes): string
{
    if (fwrite($socket, $command . "\r\n") === false) {
        throw new RuntimeException('Gagal mengirim perintah SMTP.');
    }
    return restock_smtp_expect($socket, $codes);
}

function restock_smtp_normalize_body(string $body): string
{
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $body = str_replace("\n", "\r\n", $body);
    // SMTP dot-stuffing: lines beginning with a dot must receive another dot.
    $body = preg_replace('/(?m)^\./', '..', $body);
    return $body;
}

function restock_mail_subject(string $subject): string
{
    if (function_exists('mb_encode_mimeheader')) {
        return mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n");
    }
    return $subject;
}

function sendPasswordResetEmail(string $to, string $name, string $resetLink): bool
{
    $config = restock_mail_config();
    $host = (string) ($config['host'] ?? '');
    $port = (int) ($config['port'] ?? 465);
    $username = (string) ($config['username'] ?? '');
    $password = (string) ($config['password'] ?? '');
    $fromEmail = (string) ($config['from_email'] ?? $username);
    $fromName = (string) ($config['from_name'] ?? 'RE-STOCK');
    $timeout = max(5, (int) ($config['timeout'] ?? 20));

    if ($host === '' || $username === '' || $password === '' || $password === 'GANTI_DENGAN_PASSWORD_EMAIL_NO_REPLY') {
        throw new RuntimeException('Konfigurasi SMTP belum lengkap.');
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Alamat email SMTP tidak valid.');
    }

    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client(
        'ssl://' . $host . ':' . $port,
        $errno,
        $errstr,
        $timeout,
        STREAM_CLIENT_CONNECT
    );

    if ($socket === false) {
        throw new RuntimeException('Koneksi SMTP gagal: ' . ($errstr !== '' ? $errstr : 'error ' . $errno));
    }

    stream_set_timeout($socket, $timeout);

    try {
        restock_smtp_expect($socket, [220]);

        $ehloHost = $_SERVER['HTTP_HOST'] ?? 'restock.reqra.my.id';
        if (!preg_match('/^[A-Za-z0-9.-]+$/', $ehloHost)) {
            $ehloHost = 'restock.reqra.my.id';
        }
        restock_smtp_command($socket, 'EHLO ' . $ehloHost, [250]);
        restock_smtp_command($socket, 'AUTH LOGIN', [334]);
        restock_smtp_command($socket, base64_encode($username), [334]);
        restock_smtp_command($socket, base64_encode($password), [235]);
        restock_smtp_command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
        restock_smtp_command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        restock_smtp_command($socket, 'DATA', [354]);

        $safeName = trim(preg_replace('/[\r\n]+/', ' ', $name));
        $headers = [
            'From: ' . $fromName . ' <' . $fromEmail . '>',
            'To: <' . $to . '>',
            'Subject: ' . restock_mail_subject('Reset Password RE-STOCK'),
            'Date: ' . date(DATE_RFC2822),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@reqra.my.id>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        $body = "Halo {$safeName},\n\n"
            . "Kami menerima permintaan untuk mengatur ulang password akun RE-STOCK kamu.\n\n"
            . "Gunakan tautan berikut dalam 30 menit:\n"
            . $resetLink . "\n\n"
            . "Jika kamu tidak meminta reset password, abaikan email ini.\n\n"
            . "RE-STOCK by REQRA\n";

        $message = implode("\r\n", $headers) . "\r\n\r\n" . restock_smtp_normalize_body($body) . "\r\n.";
        if (fwrite($socket, $message . "\r\n") === false) {
            throw new RuntimeException('Gagal mengirim isi email SMTP.');
        }
        restock_smtp_expect($socket, [250]);
        restock_smtp_command($socket, 'QUIT', [221]);
        return true;
    } finally {
        fclose($socket);
    }
}
