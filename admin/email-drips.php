<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/drips.php';
$me = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('action');

    if ($action === 'create_drip') {
        $name = trim(post('name'));
        $trigger = post('trigger_event');
        if ($name && array_key_exists($trigger, DRIP_TRIGGERS)) {
            db()->prepare('INSERT INTO email_drips (name, trigger_event) VALUES (?, ?)')->execute([$name, $trigger]);
            log_admin_action($me['id'], 'drip.create', 'email_drips', db()->lastInsertId());
            flash('success', 'Drip campaign created — now add steps to it.');
        }
        redirect('/admin/email-drips.php');
    } elseif ($action === 'toggle_enabled') {
        $id = (int) post('id');
        db()->prepare('UPDATE email_drips SET is_enabled = NOT is_enabled WHERE id = ?')->execute([$id]);
        log_admin_action($me['id'], 'drip.toggle', 'email_drips', $id);
        redirect('/admin/email-drips.php');
    } elseif ($action === 'delete_drip') {
        $id = (int) post('id');
        db()->prepare('DELETE FROM email_drips WHERE id = ?')->execute([$id]);
        log_admin_action($me['id'], 'drip.delete', 'email_drips', $id);
        flash('success', 'Drip campaign deleted.');
        redirect('/admin/email-drips.php');
    } elseif ($action === 'save_steps') {
        $dripId = (int) post('drip_id');
        $delays = $_POST['step_delay'] ?? [];
        $keys = $_POST['step_template'] ?? [];
        db()->prepare('DELETE FROM email_drip_steps WHERE drip_id = ?')->execute([$dripId]);
        $insert = db()->prepare('INSERT INTO email_drip_steps (drip_id, step_order, delay_hours, template_key) VALUES (?, ?, ?, ?)');
        $order = 1;
        foreach ($delays as $i => $delay) {
            $key = trim($keys[$i] ?? '');
            $delay = trim((string) $delay);
            if ($key === '' || $delay === '') {
                continue;
            }
            $insert->execute([$dripId, $order, (int) $delay, $key]);
            $order++;
        }
        log_admin_action($me['id'], 'drip.steps_update', 'email_drips', $dripId);
        flash('success', 'Steps saved.');
        redirect('/admin/email-drips.php');
    } elseif ($action === 'run_now') {
        $sent = process_due_drip_steps();
        flash('success', "Ran now — sent $sent due email(s).");
        redirect('/admin/email-drips.php');
    }
}

$drips = get_all_drips();
$templateKeys = array_column(db()->query('SELECT DISTINCT template_key FROM email_templates ORDER BY template_key')->fetchAll(), 'template_key');
$cronSecret = get_cron_secret();
$cronUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'yoursite.com') . '/cron/send_drips.php?key=' . $cronSecret;

$page_title = 'Email Drip Campaigns';
require __DIR__ . '/../includes/admin_layout_header.php';
?>
<h1 class="text-xl flex items-center gap-2"><?= icon('send') ?> Email Drip Campaigns</h1>
<p class="text-sm text-muted mt-1">Automated multi-step email sequences that fire on a delay after a trigger event — welcome series, listing nudges, review requests.</p>

<div class="card card-pad mt-4">
  <h2 class="text-lg">Cron setup (required for drips to actually send)</h2>
  <p class="text-sm text-muted mt-1">Add a cron job on your host that hits this URL periodically (hourly is plenty). On cPanel: Cron Jobs -> add a job running <code>curl -s "<?= e($cronUrl) ?>"</code>, or run <code>php cron/send_drips.php</code> directly if your host allows CLI cron.</p>
  <div class="field mt-2"><input type="text" readonly value="<?= e($cronUrl) ?>" onclick="this.select()" style="font-family:monospace;font-size:.78rem;"></div>
  <form method="post" class="mt-2"><?= csrf_field() ?><input type="hidden" name="action" value="run_now">
    <button class="btn btn-outline"><?= icon('history') ?> Run due emails now (for testing)</button>
  </form>
</div>

<form method="post" class="card card-pad mt-4">
  <?= csrf_field() ?><input type="hidden" name="action" value="create_drip">
  <h2 class="text-lg">New drip campaign</h2>
  <div class="grid grid-2 mt-2">
    <input type="text" name="name" placeholder="Campaign name" required>
    <select name="trigger_event">
      <?php foreach (DRIP_TRIGGERS as $key => $label): ?>
        <option value="<?= e($key) ?>"><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button class="btn btn-primary mt-2"><?= icon('plus') ?> Create Campaign</button>
</form>

<datalist id="template-keys">
  <?php foreach ($templateKeys as $k): ?><option value="<?= e($k) ?>"><?php endforeach; ?>
</datalist>

<?php foreach ($drips as $drip): $steps = get_drip_steps((int) $drip['id']); ?>
<div class="card card-pad mt-4">
  <div class="flex justify-between items-center" style="flex-wrap:wrap;gap:.5rem;">
    <div>
      <h2 class="text-lg"><?= e($drip['name']) ?></h2>
      <p class="text-xs text-muted mt-1"><?= e(DRIP_TRIGGERS[$drip['trigger_event']] ?? $drip['trigger_event']) ?> &middot; <?= (int) $drip['step_count'] ?> step<?= (int) $drip['step_count'] === 1 ? '' : 's' ?> &middot; <?= (int) $drip['active_count'] ?> active enrollment<?= (int) $drip['active_count'] === 1 ? '' : 's' ?> &middot; <?= (int) $drip['total_enrolled'] ?> total enrolled</p>
    </div>
    <div class="flex gap-2">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_enabled"><input type="hidden" name="id" value="<?= $drip['id'] ?>">
        <button type="submit" class="status-badge <?= $drip['is_enabled'] ? 'status-active' : 'status-inactive' ?>" style="border:none;cursor:pointer;"><?= $drip['is_enabled'] ? 'Enabled' : 'Disabled' ?></button>
      </form>
      <form method="post" onsubmit="return confirm('Delete this campaign and all its enrollments?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_drip"><input type="hidden" name="id" value="<?= $drip['id'] ?>">
        <button type="submit" style="background:none;border:none;cursor:pointer;color:var(--slate-500);"><?= icon('trash') ?></button>
      </form>
    </div>
  </div>

  <form method="post" class="mt-3">
    <?= csrf_field() ?><input type="hidden" name="action" value="save_steps"><input type="hidden" name="drip_id" value="<?= $drip['id'] ?>">
    <div id="steps-<?= $drip['id'] ?>-list" class="stack">
      <?php foreach ($steps as $step): ?>
        <div class="dynamic-row flex gap-2">
          <input type="number" name="step_delay[]" min="0" value="<?= (int) $step['delay_hours'] ?>" style="width:6rem;" title="Hours after trigger">
          <span class="text-xs text-muted" style="align-self:center;">hrs after &rarr;</span>
          <input type="text" name="step_template[]" list="template-keys" value="<?= e($step['template_key']) ?>" placeholder="template_key" style="flex:1;">
          <button type="button" data-remove-row class="btn btn-outline"><?= icon('trash') ?></button>
        </div>
      <?php endforeach; ?>
    </div>
    <template id="steps-<?= $drip['id'] ?>">
      <div class="dynamic-row flex gap-2 mt-2">
        <input type="number" name="step_delay[]" min="0" value="24" style="width:6rem;" title="Hours after trigger">
        <span class="text-xs text-muted" style="align-self:center;">hrs after &rarr;</span>
        <input type="text" name="step_template[]" list="template-keys" placeholder="template_key" style="flex:1;">
        <button type="button" data-remove-row class="btn btn-outline"><?= icon('trash') ?></button>
      </div>
    </template>
    <button type="button" class="link text-xs mt-2" data-add-row="steps-<?= $drip['id'] ?>"><?= icon('plus') ?> Add step</button>
    <div><button class="btn btn-primary mt-3">Save Steps</button></div>
  </form>
</div>
<?php endforeach; ?>

<?php if (!$drips): ?>
  <p class="text-sm text-muted mt-4">No drip campaigns yet — create one above.</p>
<?php endif; ?>
<?php require __DIR__ . '/../includes/admin_layout_footer.php'; ?>
