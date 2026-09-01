// MarketplaceForTeachers.com — plain vanilla JS, no bundler, no framework.

const CART_KEY = 'mft_cart_v1';

function cartGet() {
  try {
    return JSON.parse(localStorage.getItem(CART_KEY) || '[]');
  } catch (e) {
    return [];
  }
}

function cartSave(items) {
  localStorage.setItem(CART_KEY, JSON.stringify(items));
  updateCartBadge();
}

function cartAdd(listing) {
  const items = cartGet();
  const existing = items.find((i) => i.listing.id === listing.id);
  if (existing) {
    existing.quantity += 1;
  } else {
    items.push({ listing, quantity: 1 });
  }
  cartSave(items);
}

function cartSetQuantity(listingId, quantity) {
  let items = cartGet();
  if (quantity < 1) {
    items = items.filter((i) => i.listing.id !== listingId);
  } else {
    items = items.map((i) => (i.listing.id === listingId ? { ...i, quantity } : i));
  }
  cartSave(items);
}

function cartRemove(listingId) {
  cartSetQuantity(listingId, 0);
}

function cartClear() {
  cartSave([]);
}

function cartCount() {
  return cartGet().reduce((sum, i) => sum + i.quantity, 0);
}

function updateCartBadge() {
  const badge = document.getElementById('cart-badge');
  if (!badge) return;
  const count = cartCount();
  if (count > 0) {
    badge.textContent = String(count);
    badge.style.display = 'flex';
  } else {
    badge.style.display = 'none';
  }
}

// Recently viewed listings — client-side only (localStorage), same pattern as the cart.
const RECENTLY_VIEWED_KEY = 'mft_recently_viewed_v1';
const RECENTLY_VIEWED_MAX = 8;

function trackRecentlyViewed(listing) {
  try {
    let items = JSON.parse(localStorage.getItem(RECENTLY_VIEWED_KEY) || '[]');
    items = items.filter((i) => i.id !== listing.id);
    items.unshift(listing);
    items = items.slice(0, RECENTLY_VIEWED_MAX);
    localStorage.setItem(RECENTLY_VIEWED_KEY, JSON.stringify(items));
  } catch (e) {
    // localStorage unavailable — recently-viewed is a nice-to-have, fail silently.
  }
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = String(str == null ? '' : str);
  return div.innerHTML;
}

function renderRecentlyViewed() {
  const section = document.getElementById('recently-viewed');
  const list = document.getElementById('recently-viewed-list');
  if (!section || !list) return;
  let items = [];
  try {
    items = JSON.parse(localStorage.getItem(RECENTLY_VIEWED_KEY) || '[]');
  } catch (e) {
    return;
  }
  if (!items.length) return;

  list.innerHTML = items.map((item) => `
    <a href="/listing.php?slug=${encodeURIComponent(item.slug)}" class="card listing-card">
      <span class="listing-img">
        ${item.image ? `<img src="${escapeHtml(item.image)}" alt="${escapeHtml(item.title)}">` : ''}
      </span>
      <span class="listing-body">
        <span class="font-bold text-sm" style="color:var(--text);">${escapeHtml(item.title)}</span>
        <span class="price">${item.price == 0 ? 'Free' : '$' + Number(item.price).toFixed(2)}</span>
      </span>
    </a>
  `).join('');
  section.classList.remove('hidden');
}

document.addEventListener('DOMContentLoaded', () => {
  updateCartBadge();

  // Close the account dropdown when clicking outside it.
  document.addEventListener('click', (e) => {
    const menu = document.getElementById('account-dropdown');
    const toggle = e.target.closest('.avatar-btn');
    if (menu && !menu.contains(e.target) && !toggle) {
      menu.classList.add('hidden');
    }
  });

  // Add-to-cart buttons: <button data-add-to-cart='{"id":1,"title":"...", ...}'>
  document.querySelectorAll('[data-add-to-cart]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const listing = JSON.parse(btn.getAttribute('data-add-to-cart'));
      cartAdd(listing);
      btn.textContent = 'Added!';
      setTimeout(() => (btn.textContent = 'Add to Cart'), 1000);
    });
  });

  // Dynamic "add another row" buttons for forms (photo URLs, wishlist items).
  document.querySelectorAll('[data-add-row]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const templateId = btn.getAttribute('data-add-row');
      const template = document.getElementById(templateId);
      const container = document.getElementById(templateId + '-list');
      if (template && container) {
        const clone = template.content.cloneNode(true);
        container.appendChild(clone);
      }
    });
  });

  document.addEventListener('click', (e) => {
    const removeBtn = e.target.closest('[data-remove-row]');
    if (removeBtn) {
      removeBtn.closest('.dynamic-row')?.remove();
    }
  });

  initHeroSlideshow();
  renderRecentlyViewed();
});

// Hero image slideshow — auto-crossfades, pauses on hover/focus, dots are clickable,
// and respects prefers-reduced-motion by disabling auto-advance (dots still work).
function initHeroSlideshow() {
  const hero = document.getElementById('hero-slideshow');
  if (!hero) return;
  const slides = hero.querySelectorAll('.hero-slide');
  const dots = hero.querySelectorAll('.hero-dot');
  if (slides.length < 2) return;

  const interval = parseInt(hero.getAttribute('data-interval'), 10) || 5500;
  const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  let current = 0;
  let timer = null;

  function show(index) {
    slides[current].classList.remove('active');
    dots[current]?.classList.remove('active');
    current = (index + slides.length) % slides.length;
    slides[current].classList.add('active');
    dots[current]?.classList.add('active');
  }

  function start() {
    if (reduceMotion) return;
    stop();
    timer = setInterval(() => show(current + 1), interval);
  }

  function stop() {
    if (timer) clearInterval(timer);
    timer = null;
  }

  dots.forEach((dot) => {
    dot.addEventListener('click', () => {
      show(parseInt(dot.getAttribute('data-slide'), 10));
      start();
    });
  });

  hero.addEventListener('mouseenter', stop);
  hero.addEventListener('mouseleave', start);
  hero.addEventListener('focusin', stop);
  hero.addEventListener('focusout', start);

  start();
}
