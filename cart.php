<?php
require_once __DIR__ . '/includes/bootstrap.php';
$page_title = 'Your Cart';
require __DIR__ . '/includes/layout_header.php';
?>
<div class="container-md py-8">
  <h1 class="text-xl">Your Cart</h1>
  <div id="cart-empty" class="text-center py-8 hidden">
    <p class="text-sm text-muted">Your cart is empty.</p>
    <a href="/browse.php" class="btn btn-primary mt-3">Browse Listings</a>
  </div>
  <div id="cart-content" class="hidden" style="margin-top:1.5rem;">
    <div class="grid layout-cart">
      <div id="cart-items" class="stack"></div>
      <div class="card card-pad" style="align-self:start;">
        <h2 class="text-lg">Order Summary</h2>
        <div class="flex justify-between mt-3 text-sm"><span class="text-muted">Subtotal</span><strong id="cart-subtotal"></strong></div>
        <div class="flex justify-between mt-2 text-sm"><span class="text-muted">Shipping</span><strong id="cart-shipping"></strong></div>
        <div class="flex justify-between mt-3 text-lg" style="border-top:1px solid var(--slate-100);padding-top:.6rem;"><strong>Total</strong><strong id="cart-total"></strong></div>
        <a href="/checkout.php" class="btn btn-primary w-full mt-4" style="justify-content:center;">Proceed to Checkout</a>
      </div>
    </div>
  </div>
</div>

<script>
function money(n) { return '$' + n.toFixed(2); }
function renderCart() {
  const items = cartGet();
  const empty = document.getElementById('cart-empty');
  const content = document.getElementById('cart-content');
  if (items.length === 0) {
    empty.classList.remove('hidden');
    content.classList.add('hidden');
    return;
  }
  empty.classList.add('hidden');
  content.classList.remove('hidden');

  const container = document.getElementById('cart-items');
  container.innerHTML = '';
  let subtotal = 0, shipping = 0;
  items.forEach(({ listing, quantity }) => {
    subtotal += listing.price * quantity;
    shipping += listing.shippingFee * quantity;
    const row = document.createElement('div');
    row.className = 'card card-pad flex gap-3';
    row.innerHTML = `
      <div style="width:80px;height:80px;background:var(--surface-2);border-radius:.5rem;flex-shrink:0;"></div>
      <div style="flex:1;">
        <p class="font-bold text-sm">${listing.title}</p>
        <p class="text-xs text-muted">${listing.categoryName || ''} &middot; ${listing.gradeLevel || ''}</p>
        <div class="flex items-center gap-2 mt-2">
          <button class="btn btn-outline" data-dec="${listing.id}">-</button>
          <span>${quantity}</span>
          <button class="btn btn-outline" data-inc="${listing.id}">+</button>
          <button class="link text-xs" data-remove="${listing.id}">Remove</button>
        </div>
      </div>
      <p class="font-bold">${money(listing.price * quantity)}</p>
    `;
    container.appendChild(row);
  });

  document.getElementById('cart-subtotal').textContent = money(subtotal);
  document.getElementById('cart-shipping').textContent = money(shipping);
  document.getElementById('cart-total').textContent = money(subtotal + shipping);

  container.querySelectorAll('[data-inc]').forEach((btn) => btn.addEventListener('click', () => {
    const items = cartGet();
    const item = items.find((i) => i.listing.id == btn.dataset.inc);
    cartSetQuantity(item.listing.id, item.quantity + 1);
    renderCart();
  }));
  container.querySelectorAll('[data-dec]').forEach((btn) => btn.addEventListener('click', () => {
    const items = cartGet();
    const item = items.find((i) => i.listing.id == btn.dataset.dec);
    cartSetQuantity(item.listing.id, item.quantity - 1);
    renderCart();
  }));
  container.querySelectorAll('[data-remove]').forEach((btn) => btn.addEventListener('click', () => {
    cartRemove(Number(btn.dataset.remove));
    renderCart();
  }));
}
document.addEventListener('DOMContentLoaded', renderCart);
</script>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
