<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = require_admin();

const DEFAULT_TEMPLATES = [
    'welcome' => 'Welcome to {{site_name}}!',
    'order_confirmation' => 'Order #{{order_id}} confirmed',
    'item_sold' => 'You sold an item!',
    'shipping_confirmation' => 'Your order has shipped',
    'verification_approved' => "You're verified!",
    'verification_rejected' => 'Verification update',
    'dispute_resolution' => 'Dispute #{{dispute_id}} resolved',
    'password_reset' => 'Reset your {{site_name}} password',
    'donation_receipt' => 'Thank you for your donation!',
    'drip_teacher_getting_started' => 'Ready to post your first listing, {{teacher_name}}?',
    'drip_teacher_checkin' => 'A few tips to help your listings sell',
    'drip_buyer_welcome_tips' => 'Welcome! Here\'s how to find great deals',
    'drip_buyer_reengagement' => 'Still looking for classroom supplies?',
    'drip_listing_tips' => '3 tips to help your listing sell faster',
    'drip_review_request' => 'How did your order go?',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (post('form') === 'mail_method') {
        $method = post('method');
        if (in_array($method, ['resend', 'smtp', 'php_mail'], true)) {
            set_setting('mail_delivery', ['method' => $method]);
            log_admin_action($me['id'], 'settings.update', 'site_settings', 'mail_delivery');
            flash('success', 'Delivery method updated.');
        }
        redirect('/admin/email-templates.php');
    } elseif (post('form') === 'resend') {
        set_resend_config([
            'apiKey' => str_contains(post('apiKey'), '•') ? get_resend_config()['apiKey'] : trim(post('apiKey')),
            'fromEmail' => trim(post('fromEmail')),
            'fromName' => trim(post('fromName')),
        ]);
        log_admin_action($me['id'], 'integration.update', 'integration_configs', 'resend');
        flash('success', 'Resend settings saved.');
        redirect('/admin/email-templates.php');
    } elseif (post('form') === 'smtp') {
        $currentPassword = get_smtp_config()['password'];
        set_smtp_config([
            'host' => trim(post('host')),
            'port' => (int) post('port', 587),
            'encryption' => in_array(post('encryption'), ['tls', 'ssl', 'none'], true) ? post('encryption') : 'tls',
            'username' => trim(post('username')),
            'password' => str_contains(post('password'), '•') ? $currentPassword : post('password'),
            'fromEmail' => trim(post('fromEmail')),
            'fromName' => trim(post('fromName')),
        ]);
        log_admin_action($me['id'], 'integration.update', 'integration_configs', 'smtp');
        flash('success', 'SMTP settings saved.');
        redirect('/admin/email-templates.php');
    } elseif (post('form') === 'test_email') {
        $result = send_test_email(trim(post('testRecipient')));
        flash($result['status'] === 'sent' ? 'success' : 'error', $result['status'] === 'sent' ? 'Test email sent.' : 'Test email failed: ' . ($result['error'] ?? 'unknown error'));
        redirect('/admin/email-templates.php');
    } elseif (post('form') === 'template') {
        $key = post('templateKey');
        db()->prepare(
            'INSERT INTO email_templates (template_key, subject, html_body) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE subject = VALUES(subject), html_body = VALUES(html_body)'
        )->execute([$key, trim(post('subject')), post('htmlBody')]);
        log_admin_action($me['id'], 'email_template.upsert', 'email_templates', $key);
        flash('success', 'Template saved.');
        redirect('/admin/email-templates.php?tpl=' . urlencode(post('templateKey', '')));
    }
}

$resend = get_resend_config();
$smtp = get_smtp_config();
$mailMethod = get_setting('mail_delivery')['method'] ?? 'resend';
$stmt = db()->query('SELECT template_key, subject, html_body FROM email_templates');
$templates = [];
foreach ($stmt->fetchAll() as $row) {
    $templates[$row['template_key']] = $row;
}
$activeKey = $_GET['tpl'] ?? array_key_first(DEFAULT_TEMPLATES);
if (!isset($templates[$activeKey])) {
    $templates[$activeKey] = ['template_key' => $activeKey, 'subject' => DEFAULT_TEMPLATES[$activeKey] ?? '', 'html_body' => ''];
}
$active = $templates[$activeKey];
$logs = db()->query('SELECT * FROM email_logs ORDER BY id DESC LIMIT 100')->fetchAll();

$page_title = 'Email Template Studio';
require __DIR__ . '/../includes/admin_layout_header.php';
?>
<h1 class="text-xl flex items-center gap-2"><?= icon('message') ?> Email Template Studio</h1>
<p class="text-sm text-muted mt-1">Choose how outgoing mail is delivered, and manage the HTML/token content of every transactional and drip email.</p>

<div class="card card-pad mt-4">
  <h2 class="text-lg">Email delivery method</h2>
  <p class="text-sm text-muted mt-1">Pick whichever fits your host: Resend needs an API account, SMTP works with any mailbox (Gmail, your domain's cPanel email, SendGrid, Mailgun, etc.), and PHP mail() needs no setup but has the weakest deliverability.</p>
  <form method="post" class="flex gap-2 mt-3" style="flex-wrap:wrap;">
    <?= csrf_field() ?><input type="hidden" name="form" value="mail_method">
    <?php foreach (['resend' => 'Resend (API)', 'smtp' => 'SMTP', 'php_mail' => "PHP mail()"] as $val => $label): ?>
      <button type="submit" name="method" value="<?= $val ?>" class="btn <?= $mailMethod === $val ? 'btn-primary' : 'btn-outline' ?>"><?= e($label) ?></button>
    <?php endforeach; ?>
  </form>

  <?php if ($mailMethod === 'resend'): ?>
    <form method="post" class="mt-4" style="border-top:1px solid var(--border);padding-top:1rem;">
      <?= csrf_field() ?><input type="hidden" name="form" value="resend">
      <p class="text-xs" style="color:<?= $resend['apiKey'] ? 'var(--emerald-600)' : 'var(--slate-400)' ?>;font-weight:700;"><?= $resend['apiKey'] ? 'API key on file' : 'Not configured yet' ?></p>
      <div class="grid grid-3 mt-2">
        <input type="text" name="apiKey" placeholder="Resend API key" value="<?= e($resend['apiKey'] ? mask_secret($resend['apiKey']) : '') ?>">
        <input type="text" name="fromEmail" placeholder="From email" value="<?= e($resend['fromEmail']) ?>">
        <input type="text" name="fromName" placeholder="From name" value="<?= e($resend['fromName']) ?>">
      </div>
      <button class="btn btn-primary mt-2">Save Resend Settings</button>
    </form>
  <?php elseif ($mailMethod === 'smtp'): ?>
    <form method="post" class="mt-4" style="border-top:1px solid var(--border);padding-top:1rem;">
      <?= csrf_field() ?><input type="hidden" name="form" value="smtp">
      <p class="text-xs" style="color:<?= $smtp['host'] ? 'var(--emerald-600)' : 'var(--slate-400)' ?>;font-weight:700;"><?= $smtp['host'] ? 'SMTP host on file' : 'Not configured yet' ?></p>
      <div class="grid grid-3 mt-2">
        <input type="text" name="host" placeholder="smtp.yourhost.com" value="<?= e($smtp['host']) ?>">
        <input type="number" name="port" placeholder="587" value="<?= e((string) $smtp['port']) ?>">
        <select name="encryption">
          <option value="tls" <?= $smtp['encryption'] === 'tls' ? 'selected' : '' ?>>STARTTLS (587)</option>
          <option value="ssl" <?= $smtp['encryption'] === 'ssl' ? 'selected' : '' ?>>SSL / implicit TLS (465)</option>
          <option value="none" <?= $smtp['encryption'] === 'none' ? 'selected' : '' ?>>None (25)</option>
        </select>
        <input type="text" name="username" placeholder="SMTP username" value="<?= e($smtp['username']) ?>">
        <input type="text" name="password" placeholder="SMTP password" value="<?= e($smtp['password'] ? mask_secret($smtp['password']) : '') ?>">
        <input type="text" name="fromEmail" placeholder="From email" value="<?= e($smtp['fromEmail']) ?>">
        <input type="text" name="fromName" placeholder="From name" value="<?= e($smtp['fromName']) ?>">
      </div>
      <button class="btn btn-primary mt-2">Save SMTP Settings</button>
    </form>
  <?php else: ?>
    <p class="text-sm mt-4" style="border-top:1px solid var(--border);padding-top:1rem;">No setup needed — this uses your server's built-in <code>mail()</code> function. Deliverability depends entirely on your host; many shared hosts land in spam without SPF/DKIM configured for your domain.</p>
  <?php endif; ?>

  <form method="post" class="flex gap-2 mt-4" style="border-top:1px solid var(--border);padding-top:1rem;flex-wrap:wrap;align-items:center;">
    <?= csrf_field() ?><input type="hidden" name="form" value="test_email">
    <input type="email" name="testRecipient" placeholder="you@example.com" required style="max-width:16rem;">
    <button class="btn btn-outline"><?= icon('send') ?> Send Test Email</button>
  </form>
</div>

<div class="card card-pad mt-4">
  <div class="flex gap-2" style="flex-wrap:wrap;">
    <?php foreach (DEFAULT_TEMPLATES as $key => $defaultSubject): ?>
      <a href="/admin/email-templates.php?tpl=<?= e($key) ?>" class="chip <?= $activeKey === $key ? 'chip-royal' : 'chip-slate' ?>"><?= e($key) ?></a>
    <?php endforeach; ?>
  </div>
  <form method="post" class="mt-4">
    <?= csrf_field() ?><input type="hidden" name="form" value="template"><input type="hidden" name="templateKey" value="<?= e($activeKey) ?>">
    <div class="field"><input type="text" name="subject" value="<?= e($active['subject']) ?>"></div>
    <div class="field"><textarea name="htmlBody" rows="8" style="font-family:monospace;font-size:.8rem;"><?= e($active['html_body']) ?></textarea></div>
    <p class="text-xs text-muted">Use tokens like <code>{{teacher_name}}</code>, <code>{{order_id}}</code>, <code>{{tracking_url}}</code>.</p>
    <button class="btn btn-primary mt-2">Save Template</button>
  </form>
</div>

<h2 class="text-lg mt-6">Recent sends</h2>
<div class="table-wrap mt-2">
  <table>
    <thead><tr><th>Template</th><th>Recipient</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach ($logs as $l): ?>
        <tr><td><?= e($l['template_key']) ?></td><td class="text-muted"><?= e($l['recipient']) ?></td><td class="text-muted"><?= e($l['status']) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/admin_layout_footer.php'; ?>
