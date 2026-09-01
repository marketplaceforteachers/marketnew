<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/resend.php';
$me = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) post('id');
    $status = post('status');
    $notes = trim(post('reviewer_notes'));

    $stmt = db()->prepare('SELECT user_id FROM teacher_verifications WHERE id = ?');
    $stmt->execute([$id]);
    if ($row = $stmt->fetch()) {
        db()->prepare('UPDATE teacher_verifications SET status = ?, reviewer_notes = ? WHERE id = ?')->execute([$status, $notes, $id]);
        if ($status === 'approved') {
            db()->prepare('UPDATE users SET is_verified = 1 WHERE id = ?')->execute([$row['user_id']]);
        }
        $stmt = db()->prepare('SELECT name, email FROM users WHERE id = ?');
        $stmt->execute([$row['user_id']]);
        if ($teacher = $stmt->fetch()) {
            send_transactional_email($status === 'approved' ? 'verification_approved' : 'verification_rejected', $teacher['email'], ['teacher_name' => $teacher['name'], 'reason' => $notes]);
        }
        log_admin_action($me['id'], "verification.$status", 'teacher_verifications', $id);
        flash('success', 'Verification updated.');
    }
    redirect('/admin/verifications.php');
}

$requests = db()->query(
    "SELECT v.*, u.name AS teacher_name, u.email AS teacher_email FROM teacher_verifications v JOIN users u ON u.id = v.user_id ORDER BY v.submitted_at DESC"
)->fetchAll();

$page_title = 'Teacher Verification';
require __DIR__ . '/../includes/admin_layout_header.php';
?>
<h1 class="text-xl">Teacher Verification Review</h1>
<p class="text-sm text-muted mt-1">Approving sends the verified badge; rejecting sends automated feedback.</p>
<div class="stack mt-4">
  <?php if (!$requests): ?><p class="text-sm text-muted">No verification requests.</p><?php endif; ?>
  <?php foreach ($requests as $r): ?>
    <div class="card card-pad">
      <div class="flex justify-between items-center" style="flex-wrap:wrap;gap:.5rem;">
        <div>
          <p class="font-bold"><?= e($r['teacher_name']) ?></p>
          <p class="text-xs text-muted"><?= e($r['teacher_email']) ?></p>
          <?php if ($r['school_email']): ?><p class="text-xs text-muted">School email: <?= e($r['school_email']) ?></p><?php endif; ?>
          <?php if ($r['document_url']): ?><a href="<?= e($r['document_url']) ?>" target="_blank" class="link text-xs">View document</a><?php endif; ?>
        </div>
        <span class="status-badge status-<?= e($r['status']) ?>"><?= e($r['status']) ?></span>
      </div>
      <?php if (in_array($r['status'], ['pending', 'under_review'], true)): ?>
        <form method="post" class="mt-3">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>">
          <input type="text" name="reviewer_notes" placeholder="Reviewer notes (sent to teacher if rejected)" style="width:100%;">
          <div class="flex gap-2 mt-2">
            <button type="submit" name="status" value="approved" class="btn" style="background:var(--emerald-600);color:#fff;">Approve</button>
            <button type="submit" name="status" value="rejected" class="btn" style="background:var(--red-600);color:#fff;">Reject</button>
          </div>
        </form>
      <?php elseif ($r['reviewer_notes']): ?>
        <p class="text-xs text-muted mt-2">Notes: <?= e($r['reviewer_notes']) ?></p>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/../includes/admin_layout_footer.php'; ?>
