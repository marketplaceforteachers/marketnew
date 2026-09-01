<?php
require_once __DIR__ . '/includes/bootstrap.php';
$me = require_role('teacher', 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    db()->prepare('INSERT INTO teacher_verifications (user_id, school_email, document_url, status) VALUES (?, ?, ?, \'pending\')')
        ->execute([$me['id'], trim(post('school_email')) ?: null, trim(post('document_url')) ?: null]);
    flash('success', 'Submitted for review.');
    redirect('/seller-verification.php');
}

$stmt = db()->prepare('SELECT * FROM teacher_verifications WHERE user_id = ? ORDER BY submitted_at DESC');
$stmt->execute([$me['id']]);
$requests = $stmt->fetchAll();

$page_title = 'Educator Verification';
require __DIR__ . '/includes/layout_header.php';
?>
<div class="container-sm py-8">
  <h1 class="text-xl flex items-center gap-2"><?= icon('badge-check') ?> Educator Verification</h1>
  <p class="text-sm text-muted mt-1">Verify with your school (.k12/.edu) email or a district ID document to earn the Verified Educator badge.</p>

  <form method="post" class="card card-pad mt-4">
    <?= csrf_field() ?>
    <div class="field"><input type="email" name="school_email" placeholder="School email (name@yourdistrict.k12.us)"></div>
    <div class="field"><input type="url" name="document_url" placeholder="Or a document URL (district ID, employment letter)"></div>
    <button class="btn btn-primary">Submit for review</button>
  </form>

  <div class="stack mt-4">
    <?php foreach ($requests as $r): ?>
      <div class="card card-pad">
        <span class="status-badge status-<?= e($r['status']) ?>"><?= e($r['status']) ?></span>
        <?php if ($r['reviewer_notes']): ?><p class="text-xs text-muted mt-1"><?= e($r['reviewer_notes']) ?></p><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
