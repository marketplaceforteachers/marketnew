<?php
require_once __DIR__ . '/includes/bootstrap.php';
$page_title = 'How It Works';
require __DIR__ . '/includes/layout_header.php';

$steps = [
    ['icon' => 'search', 'title' => 'Browse or post', 'body' => 'Search classroom listings by category, grade, and condition — or post your own surplus items free, with zero listing fees.'],
    ['icon' => 'message', 'title' => 'Message the seller', 'body' => 'Ask questions or make an offer directly through the built-in messenger before you buy.'],
    ['icon' => 'credit-card', 'title' => 'Checkout securely', 'body' => 'Pay by card, PayPal, or submit a school purchase order — funds are held until delivery is confirmed.'],
    ['icon' => 'truck', 'title' => 'Ship or pick up', 'body' => 'Choose contact-free local school pickup or standard/media mail shipping, whichever the seller offers.'],
    ['icon' => 'shield', 'title' => '100% Buyer Protection', 'body' => "If an item never arrives or isn't as described, file a dispute — our team reviews evidence from both sides within 72 hours."],
    ['icon' => 'star', 'title' => 'Leave a review', 'body' => 'Rate your experience to help other educators shop with confidence.'],
];
?>
<div class="container-md py-10">
  <h1 class="text-2xl">How MarketplaceForTeachers.com Works</h1>
  <p class="text-sm text-muted mt-2">A peer-to-peer marketplace built exclusively for USA teachers, schools, and classroom donors.</p>

  <div class="stack mt-6">
    <?php foreach ($steps as $i => $s): ?>
      <div class="flex gap-3">
        <span style="flex-shrink:0;width:2.5rem;height:2.5rem;border-radius:999px;background:var(--royal-100);color:var(--royal-700);display:flex;align-items:center;justify-content:center;"><?= icon($s['icon']) ?></span>
        <div>
          <p class="font-bold"><?= $i + 1 ?>. <?= e($s['title']) ?></p>
          <p class="text-sm text-muted mt-1"><?= e($s['body']) ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="flex gap-3 mt-6">
    <a href="/browse.php" class="btn btn-primary">Browse Listings</a>
    <a href="/post-listing.php" class="btn btn-outline">Post a Listing</a>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
