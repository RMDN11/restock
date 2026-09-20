<?php
declare(strict_types=1);

/**
 * Send a payment verification email using the hosting mail transport.
 *
 * The application continues the successful payment verification even when
 * the mail transport is unavailable. The return value lets the caller
 * distinguish those cases for logging/flash messaging.
 */
function restockSendPaymentVerifiedEmail(
    string $recipient,
    string $recipientName,
    string $packageName,
    string $amount,
    string $startsAt,
    string $endsAt,
    int $storeCount
): bool {
    $recipient = trim($recipient);

    if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $safeName = htmlspecialchars($recipientName, ENT_QUOTES, 'UTF-8');
    $safePackage = htmlspecialchars($packageName, ENT_QUOTES, 'UTF-8');
    $safeAmount = htmlspecialchars($amount, ENT_QUOTES, 'UTF-8');
    $safeStartsAt = htmlspecialchars($startsAt, ENT_QUOTES, 'UTF-8');
    $safeEndsAt = htmlspecialchars($endsAt, ENT_QUOTES, 'UTF-8');

    $subject = 'Pembayaran RESTOCK berhasil diverifikasi';
    $sender = 'no-reply@restock.reqra.my.id';

    $body = <<<HTML
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$subject}</title>
</head>
<body style="margin:0;background:#f5f5f5;color:#171717;font-family:Arial,sans-serif;">
<div style="max-width:600px;margin:0 auto;padding:32px 16px;">
    <div style="background:#ffffff;border:1px solid #e5e5e5;border-radius:24px;padding:32px;">
        <div style="font-size:12px;letter-spacing:.16em;text-transform:uppercase;color:#16a34a;font-weight:700;">RESTOCK</div>
        <h1 style="font-size:26px;line-height:1.25;margin:12px 0 8px;">Pembayaran berhasil diverifikasi</h1>
        <p style="font-size:15px;line-height:1.7;color:#525252;margin:0 0 24px;">
            Halo {$safeName}, pembayaran kamu sudah diverifikasi oleh tim RESTOCK dan subscription telah diaktifkan.
        </p>

        <div style="background:#f5f5f5;border-radius:16px;padding:18px;">
            <p style="margin:0 0 8px;font-size:12px;color:#737373;">PAKET</p>
            <p style="margin:0;font-size:18px;font-weight:700;">{$safePackage}</p>
            <p style="margin:14px 0 0;font-size:12px;color:#737373;">PEMBAYARAN</p>
            <p style="margin:4px 0 0;font-size:17px;font-weight:700;">{$safeAmount}</p>
            <p style="margin:14px 0 0;font-size:12px;color:#737373;">KAPASITAS</p>
            <p style="margin:4px 0 0;font-size:15px;font-weight:600;">{$storeCount} toko</p>
        </div>

        <div style="margin-top:20px;font-size:14px;line-height:1.8;color:#525252;">
            <strong>Periode subscription</strong><br>
            {$safeStartsAt} sampai {$safeEndsAt}
        </div>

        <p style="margin:24px 0 0;font-size:13px;line-height:1.7;color:#737373;">
            Email ini dikirim otomatis setelah pembayaran diverifikasi. Simpan email ini sebagai konfirmasi pembayaran.
        </p>
    </div>
</div>
</body>
</html>
HTML;

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: RESTOCK <' . $sender . '>',
        'Reply-To: ' . $sender,
        'X-Mailer: RESTOCK',
    ];

    return mail(
        $recipient,
        '=?UTF-8?B?' . base64_encode($subject) . '?=',
        $body,
        implode("\r\n", $headers)
    );
}
