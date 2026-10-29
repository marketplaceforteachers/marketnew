<?php
$footer = $settings['footer'] ?? get_setting('footer');
$footerCategories = db()->query('SELECT name, slug FROM categories ORDER BY name LIMIT 5')->fetchAll();
?>
</main>

<footer class="site-footer">
  <?php $footerFeatures = visible('footerFeatures') ? array_filter($footer['features'], 'shown') : []; ?>
  <?php if ($footerFeatures): ?>
  <div class="footer-features">
    <div class="container">
      <div class="grid">
        <?php foreach ($footerFeatures as $f): ?>
          <div class="footer-feature-item">
            <span class="footer-feature-icon" style="background:<?= e($f['color']) ?>;"><?= icon($f['icon']) ?></span>
            <div>
              <h5><?= e($f['title']) ?></h5>
              <p><?= e($f['desc']) ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php
  $socialLinks = visible('footerSocial') ? array_filter($footer['socialLinks'], 'shown') : [];
  $helpLinks = visible('footerHelp') ? array_filter($footer['trustLinks'], 'shown') : [];
  $showAbout = visible('footerAbout') || visible('footerEmail') || $socialLinks;
  ?>
  <?php if ($showAbout || visible('footerCategories') || $helpLinks): ?>
  <div class="footer-main">
    <div class="container">
      <div class="grid">
        <?php if ($showAbout): ?>
        <div class="footer-col">
          <?= render_logo($branding, 'dark') ?>
          <?php if (visible('footerAbout') && $footer['description'] !== ''): ?>
            <p class="footer-desc"><?= e($footer['description']) ?></p>
          <?php endif; ?>
          <?php if (visible('footerEmail') && $footer['supportEmail'] !== ''): ?>
          <ul class="footer-contact">
            <li><?= icon('mail') ?> <a href="mailto:<?= e($footer['supportEmail']) ?>" style="display:inline;padding:0;color:var(--slate-300);"><?= e($footer['supportEmail']) ?></a></li>
          </ul>
          <?php endif; ?>
          <?php if ($socialLinks): ?>
          <p class="footer-social-label">Follow along</p>
          <div class="footer-social">
            <?php foreach ($socialLinks as $s): ?>
              <a href="<?= e($s['href']) ?>"><?= icon('share') ?> <?= e($s['label']) ?></a>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (visible('footerCategories') && $footerCategories): ?>
        <div class="footer-col">
          <h4>Shop by category</h4>
          <?php foreach ($footerCategories as $c): ?>
            <a href="/browse.php?category=<?= e($c['slug']) ?>"><?= e($c['name']) ?></a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($helpLinks): ?>
        <div class="footer-col">
          <h4>Help</h4>
          <?php foreach ($helpLinks as $l): ?>
            <a href="<?= e($l['href']) ?>"><?= e($l['label']) ?></a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if (visible('footerCopyright')): ?>
  <div class="footer-bottom">
    <div class="container">
      &copy; <?= date('Y') ?> <?= e($branding['siteName']) ?>. All rights reserved.
    </div>
  </div>
  <?php endif; ?>
</footer>

<script src="/assets/js/app.js?v=<?= @filemtime(__DIR__ . '/../assets/js/app.js') ?: '1' ?>"></script>
</body>
</html>
