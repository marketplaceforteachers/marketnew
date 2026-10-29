<?php
require_once __DIR__ . '/includes/bootstrap.php';
$page_title = 'How It Works';
require __DIR__ . '/includes/layout_header.php';

$steps = [
    ['icon' => 'search', 'title' => 'Look around, or list your own', 'body' => "Search by subject, grade and condition. Got extras? Posting a listing is free and takes a couple of minutes."],
    ['icon' => 'message', 'title' => 'Ask the seller anything', 'body' => "Message them before you buy: measurements, missing pieces, whether they'll hold it until Friday."],
    ['icon' => 'credit-card', 'title' => 'Pay the way that works for you', 'body' => 'Use a card, PayPal or a school purchase order. Sellers are paid once the order goes through.'],
    ['icon' => 'truck', 'title' => 'Ship it or meet at school', 'body' => 'Each seller picks shipping, local pickup, or both. Pickup is usually the easiest way to move bigger items.'],
    ['icon' => 'shield', 'title' => "If something goes wrong, we'll help", 'body' => "If an item never shows up or isn't what was described, open a dispute. We look at what both sides send us and make a call."],
    ['icon' => 'star', 'title' => 'Leave a review', 'body' => 'A quick rating helps the next teacher know who they are buying from.'],
];
?>
<div class="container-md py-10">
  <span class="section-eyebrow">How it works</span>
  <h1 class="section-title mt-1">Buying and selling, <em class="serif-accent">teacher to teacher</em></h1>
  <p class="text-sm text-muted mt-2">Here's what to expect, start to finish.</p>

  <div class="stack mt-6">
    <?php foreach ($steps as $i => $s): ?>
      <div class="flex gap-3">
        <span style="flex-shrink:0;width:2.5rem;height:2.5rem;border-radius:999px;background:var(--royal-100);color:var(--royal-700);display:flex;align-items:center;justify-content:center;"><?= icon($s['icon']) ?></span>
        <div>
          <p class="font-bold"><?= e($s['title']) ?></p>
          <p class="text-sm text-muted mt-1"><?= e($s['body']) ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="flex gap-3 mt-6">
    <a href="/browse.php" class="btn btn-primary">Browse listings</a>
    <a href="/post-listing.php" class="btn btn-outline">List something</a>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
