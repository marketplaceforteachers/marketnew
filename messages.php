<?php
require_once __DIR__ . '/includes/bootstrap.php';
$me = require_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $threadId = (int) post('thread_id');
    $body = trim(post('body'));
    $stmt = db()->prepare('SELECT * FROM message_threads WHERE id = ?');
    $stmt->execute([$threadId]);
    $thread = $stmt->fetch();
    if ($thread && $body && ((int) $thread['buyer_id'] === (int) $me['id'] || (int) $thread['seller_id'] === (int) $me['id'])) {
        $recipientId = (int) $thread['buyer_id'] === (int) $me['id'] ? $thread['seller_id'] : $thread['buyer_id'];
        db()->prepare('INSERT INTO messages (thread_id, sender_id, recipient_id, listing_id, body) VALUES (?, ?, ?, ?, ?)')
            ->execute([$threadId, $me['id'], $recipientId, $thread['listing_id'], $body]);
    }
    redirect('/messages.php?thread=' . $threadId);
}

$stmt = db()->prepare(
    "SELECT t.id, t.listing_id, l.title AS listing_title,
            CASE WHEN t.buyer_id = ? THEN sellerUser.name ELSE buyerUser.name END AS counterpart_name,
            (SELECT body FROM messages m WHERE m.thread_id = t.id ORDER BY m.created_at DESC LIMIT 1) AS last_message
     FROM message_threads t
     JOIN users buyerUser ON buyerUser.id = t.buyer_id
     JOIN users sellerUser ON sellerUser.id = t.seller_id
     LEFT JOIN listings l ON l.id = t.listing_id
     WHERE t.buyer_id = ? OR t.seller_id = ?
     ORDER BY t.created_at DESC"
);
$stmt->execute([$me['id'], $me['id'], $me['id']]);
$threads = $stmt->fetchAll();

// $threads above is already scoped to the current user (buyer_id/seller_id = $me['id']) — only
// ever load messages for a thread that's actually in that list, never a raw ?thread= value
// straight from the URL, or any logged-in user could read any other pair's private conversation
// just by changing the number.
$myThreadIds = array_column($threads, 'id');
$requestedThreadId = (int) ($_GET['thread'] ?? 0);
$activeThreadId = in_array($requestedThreadId, $myThreadIds, true) ? $requestedThreadId : (int) ($threads[0]['id'] ?? 0);
$activeMessages = [];
if ($activeThreadId) {
    $stmt = db()->prepare('SELECT * FROM messages WHERE thread_id = ? ORDER BY created_at ASC');
    $stmt->execute([$activeThreadId]);
    $activeMessages = $stmt->fetchAll();
}

$page_title = 'Messages';
require __DIR__ . '/includes/layout_header.php';
?>
<div class="container py-8">
  <div class="grid layout-messages">
    <div>
      <h1 class="text-lg flex items-center gap-2"><?= icon('message') ?> Messages</h1>
      <div class="stack mt-3">
        <?php if (!$threads): ?><p class="text-sm text-muted">No conversations yet.</p><?php endif; ?>
        <?php foreach ($threads as $t): ?>
          <a href="/messages.php?thread=<?= $t['id'] ?>" class="card card-pad" style="<?= $activeThreadId == $t['id'] ? 'border-color:var(--royal-500);' : '' ?>">
            <p class="font-bold text-sm"><?= e($t['counterpart_name']) ?></p>
            <?php if ($t['listing_title']): ?><p class="text-xs text-muted"><?= e($t['listing_title']) ?></p><?php endif; ?>
            <?php if ($t['last_message']): ?><p class="text-xs text-muted" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($t['last_message']) ?></p><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card" style="display:flex;flex-direction:column;min-height:400px;">
      <div class="stack" style="flex:1;padding:1rem;overflow-y:auto;">
        <?php foreach ($activeMessages as $m): ?>
          <div class="card card-pad" style="max-width:75%;<?= (int) $m['sender_id'] === (int) $me['id'] ? 'margin-left:auto;background:var(--royal-600);color:#fff;' : '' ?>"><?= e($m['body']) ?></div>
        <?php endforeach; ?>
        <?php if (!$activeThreadId): ?><p class="text-sm text-muted">Select a conversation.</p><?php endif; ?>
      </div>
      <?php if ($activeThreadId): ?>
        <form method="post" class="flex gap-2" style="padding:.75rem;border-top:1px solid var(--slate-100);">
          <?= csrf_field() ?>
          <input type="hidden" name="thread_id" value="<?= $activeThreadId ?>">
          <input type="text" name="body" placeholder="Type a message…" required style="flex:1;">
          <button class="btn btn-primary"><?= icon('send') ?></button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
