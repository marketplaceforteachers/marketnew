<?php
$footer = $settings['footer'] ?? get_setting('footer');
$footerCategories = db()->query('SELECT name, slug FROM categories ORDER BY name LIMIT 5')->fetchAll();
?>
</main>

<footer class="site-footer">
  <div class="footer-features">
    <div class="container">
      <div class="grid">
        <?php foreach ($footer['features'] as $f): ?>
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

  <div class="footer-main">
    <div class="container">
      <div class="grid">
        <div class="footer-col">
          <?= render_logo($branding, 'dark') ?>
          <p class="footer-desc"><?= e($footer['description']) ?></p>
          <ul class="footer-contact">
            <li><?= icon('map-pin') ?> <span><strong style="color:#fff;">HQ Address:</strong> <?= e($footer['address']) ?></span></li>
            <?php if (!empty($footer['phone'])): ?>
              <li><?= icon('phone') ?> <span><strong style="color:#fff;">Teacher Helpline:</strong> <?= e($footer['phone']) ?></span></li>
            <?php endif; ?>
            <li><?= icon('mail') ?> <a href="mailto:<?= e($footer['supportEmail']) ?>" style="display:inline;padding:0;color:var(--slate-300);"><?= e($footer['supportEmail']) ?></a></li>
          </ul>
          <p class="footer-social-label">Connect with our educator community</p>
          <div class="footer-social">
            <?php foreach ($footer['socialLinks'] as $s): ?>
              <a href="<?= e($s['href']) ?>"><?= icon('share') ?> <?= e($s['label']) ?></a>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="footer-col">
          <h4>Classroom Categories</h4>
          <?php foreach ($footerCategories as $c): ?>
            <a href="/browse.php?category=<?= e($c['slug']) ?>"><?= e($c['name']) ?></a>
          <?php endforeach; ?>
        </div>

        <div class="footer-col">
          <h4>Trust &amp; Educator Center</h4>
          <?php foreach ($footer['trustLinks'] as $l): ?>
            <a href="<?= e($l['href']) ?>"><?= e($l['label']) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="footer-bottom">
    <div class="container">
      &copy; <?= date('Y') ?> <?= e($branding['siteName']) ?>. All rights reserved.
    </div>
  </div>
</footer>

<script src="/assets/js/app.js"></script>
</body>
</html>
