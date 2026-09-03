<?php
require_once __DIR__ . '/settings.php';

function render_template(string $template, array $data): string
{
    return preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', function ($m) use ($data) {
        return (string) ($data[$m[1]] ?? '');
    }, $template);
}

/** Renders a template and sends it through whichever delivery method is configured (Resend, SMTP, or PHP mail()). Logs every attempt to email_logs. */
function send_transactional_email(string $templateKey, string $to, array $data = []): array
{
    $stmt = db()->prepare('SELECT subject, html_body FROM email_templates WHERE template_key = ?');
    $stmt->execute([$templateKey]);
    $template = $stmt->fetch();

    if (!$template) {
        log_email($templateKey, $to, 'failed');
        return ['status' => 'failed', 'error' => "No email template found for \"$templateKey\""];
    }

    $subject = render_template($template['subject'], $data);
    $bodyHtml = render_template($template['html_body'], $data);
    $html = wrap_email_letterhead($bodyHtml, $subject);
    $result = dispatch_email($to, $subject, $html);

    log_email($templateKey, $to, $result['status']);
    return $result;
}

/**
 * Wraps a template's raw message HTML in a branded letterhead: a colored header with the
 * two-tone wordmark, a white content card, and a muted footer — table-based layout with inline
 * styles throughout, since email clients don't reliably support flexbox/grid or <style> blocks.
 */
function wrap_email_letterhead(string $bodyHtml, string $preheader = ''): string
{
    $branding = get_setting('branding');
    $footer = get_setting('footer');
    $siteName = $branding['siteName'];
    $tagline = $branding['tagline'];
    $split = split_site_name($siteName);
    $hasComSuffix = (bool) preg_match('/\.com$/i', $siteName);
    $year = date('Y');
    $supportEmail = $footer['supportEmail'] ?? '';
    $address = $footer['address'] ?? '';

    $wordmark = '<span style="color:#0f172a;">' . e($split['first']) . '</span>'
        . ($split['rest'] ? '<span style="color:#d97706;">' . e($split['rest']) . '</span>' : '')
        . ($hasComSuffix ? '<span style="color:#0f172a;">.com</span>' : '');

    ob_start();
    ?>
<!doctype html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;"><?= e($preheader) ?></div>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 16px;">
    <tr><td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;">
        <tr><td style="background:#1e3a8a;border-radius:10px 10px 0 0;padding:22px 28px;text-align:center;">
          <span style="font-size:19px;font-weight:800;letter-spacing:-.01em;"><?= $wordmark ?></span><br>
          <span style="font-size:10px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#bfdbfe;"><?= e($tagline) ?></span>
        </td></tr>
        <tr><td style="background:#ffffff;padding:32px 28px;border-left:1px solid #e2e8f0;border-right:1px solid #e2e8f0;color:#1e293b;font-size:14px;line-height:1.65;">
          <?= $bodyHtml ?>
        </td></tr>
        <tr><td style="background:#f8fafc;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 10px 10px;padding:20px 28px;text-align:center;font-size:11px;color:#94a3b8;line-height:1.6;">
          &copy; <?= $year ?> <?= e($siteName) ?>. All rights reserved.<br>
          <?php if ($address): ?><?= e($address) ?><br><?php endif; ?>
          <?php if ($supportEmail): ?>Questions? <a href="mailto:<?= e($supportEmail) ?>" style="color:#2563eb;">Contact support</a><?php endif; ?>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
    <?php
    return ob_get_clean();
}

/** Sends a plain test message through the currently-configured delivery method, for the admin "Send test email" button. Not logged to email_logs since it's not a real template send. */
function send_test_email(string $to): array
{
    $siteName = get_setting('branding')['siteName'];
    $subject = "Test email from $siteName";
    $html = wrap_email_letterhead(
        "<p>This is a test email from your $siteName admin panel.</p><p>If you received this looking like a real branded email (not raw HTML), your email delivery — and the letterhead template — are both working.</p>",
        $subject
    );
    return dispatch_email($to, $subject, $html);
}

/** Routes a rendered email to the admin-configured delivery method. */
function dispatch_email(string $to, string $subject, string $html): array
{
    $method = get_setting('mail_delivery')['method'] ?? 'resend';
    return match ($method) {
        'smtp' => send_via_smtp($to, $subject, $html),
        'php_mail' => send_via_php_mail($to, $subject, $html),
        default => send_via_resend($to, $subject, $html),
    };
}

function send_via_resend(string $to, string $subject, string $html): array
{
    $config = get_resend_config();
    if (empty($config['apiKey'])) {
        return ['status' => 'failed', 'error' => 'Resend is not configured yet (Admin -> Email Template Studio).'];
    }

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $config['apiKey'],
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'from' => $config['fromName'] . ' <' . $config['fromEmail'] . '>',
            'to' => [$to],
            'subject' => $subject,
            'html' => $html,
        ]),
        CURLOPT_TIMEOUT => 20,
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['status' => 'failed', 'error' => "Connection error: $curlError"];
    }
    if ($status >= 400) {
        return ['status' => 'failed', 'error' => "Resend responded with $status"];
    }
    return ['status' => 'sent'];
}

function send_via_php_mail(string $to, string $subject, string $html): array
{
    $config = get_smtp_config(); // fromEmail/fromName are shared between smtp and php_mail
    $fromEmail = $config['fromEmail'] ?: 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $fromName = $config['fromName'] ?: get_setting('branding')['siteName'];

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= 'From: ' . mail_encode_header($fromName) . " <$fromEmail>\r\n";

    $sent = @mail($to, mail_encode_header($subject), $html, $headers);
    if (!$sent) {
        return ['status' => 'failed', 'error' => "PHP mail() returned failure — check your host's mail() configuration."];
    }
    return ['status' => 'sent'];
}

/** A dependency-free SMTP client speaking the raw protocol over a socket — no PHPMailer, works on any host with outbound socket access. */
function send_via_smtp(string $to, string $subject, string $html): array
{
    $config = get_smtp_config();
    if (empty($config['host'])) {
        return ['status' => 'failed', 'error' => 'SMTP is not configured yet (Admin -> Email Template Studio).'];
    }

    $host = $config['host'];
    $port = (int) ($config['port'] ?: 587);
    $encryption = $config['encryption'] ?: 'tls';
    $fromEmail = $config['fromEmail'] ?: $config['username'];
    $fromName = $config['fromName'] ?: get_setting('branding')['siteName'];

    $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $socket = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        return ['status' => 'failed', 'error' => "Could not connect to $host:$port — $errstr ($errno)"];
    }
    stream_set_timeout($socket, 15);

    try {
        smtp_expect($socket, 220, 'connect');
        $localHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
        smtp_command($socket, "EHLO $localHost", 250, 'EHLO');

        if ($encryption === 'tls') {
            smtp_command($socket, 'STARTTLS', 220, 'STARTTLS');
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception('TLS negotiation failed.');
            }
            smtp_command($socket, "EHLO $localHost", 250, 'EHLO after STARTTLS');
        }

        if (!empty($config['username'])) {
            smtp_command($socket, 'AUTH LOGIN', 334, 'AUTH LOGIN');
            smtp_command($socket, base64_encode($config['username']), 334, 'username');
            smtp_command($socket, base64_encode($config['password']), 235, 'password');
        }

        smtp_command($socket, "MAIL FROM:<$fromEmail>", 250, 'MAIL FROM');
        smtp_command($socket, "RCPT TO:<$to>", [250, 251], 'RCPT TO');
        smtp_command($socket, 'DATA', 354, 'DATA');

        $headers = [];
        $headers[] = 'From: ' . mail_encode_header($fromName) . " <$fromEmail>";
        $headers[] = "To: <$to>";
        $headers[] = 'Subject: ' . mail_encode_header($subject);
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . preg_replace('/[^a-z0-9.-]/i', '', $localHost) . '>';
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';

        $body = preg_replace('/\r\n|\r|\n/', "\r\n", $html);
        $body = preg_replace('/^\./m', '..', $body); // dot-stuffing per RFC 5321
        $message = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.\r\n";
        fwrite($socket, $message);
        smtp_read_response($socket, 250, 'message body');

        fwrite($socket, "QUIT\r\n");
        fclose($socket);
        return ['status' => 'sent'];
    } catch (Exception $e) {
        if (is_resource($socket)) {
            fclose($socket);
        }
        return ['status' => 'failed', 'error' => $e->getMessage()];
    }
}

function smtp_command($socket, string $command, $expectCode, string $label): string
{
    fwrite($socket, $command . "\r\n");
    return smtp_read_response($socket, $expectCode, $label);
}

function smtp_expect($socket, $expectCode, string $label): string
{
    return smtp_read_response($socket, $expectCode, $label);
}

function smtp_read_response($socket, $expectCode, string $label): string
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        // Multi-line responses use "250-text"; the final line uses "250 text".
        if (preg_match('/^\d{3} /', $line)) {
            break;
        }
    }
    if ($response === '') {
        throw new Exception("No response from server during $label (possible timeout).");
    }
    $code = (int) substr($response, 0, 3);
    $expected = is_array($expectCode) ? $expectCode : [$expectCode];
    if (!in_array($code, $expected, true)) {
        throw new Exception("SMTP error during $label: " . trim($response));
    }
    return $response;
}

function mail_encode_header(string $value): string
{
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function log_email(string $templateKey, string $recipient, string $status): void
{
    $stmt = db()->prepare(
        'INSERT INTO email_logs (template_key, recipient, status, sent_at) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$templateKey, $recipient, $status, $status === 'sent' ? date('Y-m-d H:i:s') : null]);
}
