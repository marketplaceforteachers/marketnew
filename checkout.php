<?php
require_once __DIR__ . '/includes/bootstrap.php';
$me = require_auth();
$page_title = 'Checkout';
require __DIR__ . '/includes/layout_header.php';
?>
<div class="container-sm py-8">
  <h1 class="text-xl">Checkout</h1>

  <ol class="flex gap-2 mt-4 text-xs" id="step-indicator" style="flex-wrap:wrap;"></ol>

  <div id="checkout-empty" class="text-center py-8 hidden">
    <p class="text-sm text-muted">Your cart is empty — nothing to check out.</p>
  </div>

  <div id="checkout-paid" class="text-center py-10 hidden">
    <p style="font-size:2rem;">✅</p>
    <h2 class="text-xl mt-3">Order placed!</h2>
    <p class="text-sm text-muted mt-2" id="paid-message"></p>
    <a href="/index.php" class="btn btn-primary mt-4">Back to Marketplace</a>
  </div>

  <div id="checkout-wizard" class="card card-pad mt-4 hidden">
    <div data-step="0">
      <div class="field"><label>Full name</label><input type="text" id="f-name" value="<?= e($me['name']) ?>"></div>
      <div class="field"><label>Email</label><input type="email" id="f-email" value="<?= e($me['email']) ?>"></div>
      <div class="field"><label>School district (optional)</label><input type="text" id="f-district" value="<?= e($me['district'] ?? '') ?>"></div>
    </div>

    <div data-step="1" class="hidden">
      <div class="flex gap-3">
        <button type="button" id="ship-mode-carrier" class="btn btn-outline w-full">Ship to address</button>
        <button type="button" id="ship-mode-pickup" class="btn btn-outline w-full">Local school pickup</button>
      </div>
      <div class="field mt-3" id="address-field"><label>Shipping address</label><textarea id="f-address" rows="3"></textarea></div>
    </div>

    <div data-step="2" class="hidden">
      <div class="field"><label>Delivery notes for the seller (optional)</label><textarea id="f-notes" rows="3"></textarea></div>
    </div>

    <div data-step="3" class="hidden">
      <div id="gateway-options" class="grid grid-3"></div>
      <p id="payment-error" class="flash flash-error mt-3 hidden"></p>
      <div id="payment-panel" class="mt-3"></div>
    </div>

    <div data-step="4" class="hidden">
      <div id="review-summary" class="stack"></div>
    </div>

    <div class="flex justify-between mt-5">
      <button type="button" id="btn-back" class="btn btn-outline">Back</button>
      <button type="button" id="btn-continue" class="btn btn-primary">Continue</button>
    </div>
  </div>
</div>

<script src="https://js.stripe.com/v3/"></script>
<script src="https://www.paypal.com/sdk/js?client-id=sb&currency=USD" id="paypal-sdk-placeholder"></script>
<script>
const STEPS = ['Contact & District', 'Shipping', 'Delivery Notes', 'Payment', 'Review & Confirm'];
let step = 0;
let settings = null;
let orderId = null;
let gateway = null;
let pickup = false;
let stripeClientSecret = null;
let stripeElements = null;

const indicator = document.getElementById('step-indicator');
STEPS.forEach((label, i) => {
  const li = document.createElement('li');
  li.className = 'flex items-center gap-1';
  li.innerHTML = `<span id="dot-${i}" style="width:22px;height:22px;border-radius:999px;background:var(--surface-2);color:var(--slate-500);display:flex;align-items:center;justify-content:center;font-weight:700;">${i + 1}</span> <span id="label-${i}" style="color:var(--slate-500);">${label}</span>`;
  indicator.appendChild(li);
});

function updateIndicator() {
  STEPS.forEach((_, i) => {
    document.getElementById(`dot-${i}`).style.background = i <= step ? 'var(--royal-600)' : 'var(--surface-2)';
    document.getElementById(`dot-${i}`).style.color = i <= step ? '#fff' : 'var(--slate-500)';
    document.getElementById(`label-${i}`).style.color = i <= step ? 'var(--slate-900)' : 'var(--slate-500)';
  });
  document.querySelectorAll('[data-step]').forEach((el) => {
    el.classList.toggle('hidden', Number(el.dataset.step) !== step);
  });
  document.getElementById('btn-back').style.visibility = step === 0 ? 'hidden' : 'visible';
  document.getElementById('btn-continue').classList.toggle('hidden', step === 3 || step === 4);
}

document.getElementById('ship-mode-carrier').addEventListener('click', () => { pickup = false; document.getElementById('address-field').classList.remove('hidden'); });
document.getElementById('ship-mode-pickup').addEventListener('click', () => { pickup = true; document.getElementById('address-field').classList.add('hidden'); });

document.getElementById('btn-back').addEventListener('click', () => { if (step > 0) { step--; updateIndicator(); } });
document.getElementById('btn-continue').addEventListener('click', () => {
  if (step === 2) { renderPaymentOptions(); }
  step++;
  updateIndicator();
});

async function api(path, body) {
  const res = await fetch(path, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body || {}) });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Request failed');
  return data;
}

function renderPaymentOptions() {
  const container = document.getElementById('gateway-options');
  container.innerHTML = '';
  const options = [
    { id: 'stripe', label: 'Card (Stripe)', enabled: settings.payments.stripe.enabled },
    { id: 'paypal', label: 'PayPal', enabled: settings.payments.paypal.enabled },
    { id: 'school_po', label: 'School Purchase Order', enabled: settings.payments.school_po.enabled },
  ];
  options.forEach((opt) => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-outline';
    btn.textContent = opt.label + (opt.enabled ? '' : ' (Not configured)');
    btn.disabled = !opt.enabled;
    btn.addEventListener('click', () => selectGateway(opt.id));
    container.appendChild(btn);
  });
}

async function ensureOrder() {
  if (orderId) return orderId;
  const items = cartGet().map((i) => ({ listingId: i.listing.id, quantity: i.quantity }));
  const address = pickup ? 'Local pickup' : document.getElementById('f-address').value;
  const notes = document.getElementById('f-notes').value;
  const shippingAddress = notes ? `${address} — Notes: ${notes}` : address;
  const order = await api('/api/ajax/create_order.php', { items, shippingAddress, paymentGateway: gateway });
  orderId = order.id;
  return orderId;
}

async function selectGateway(id) {
  gateway = id;
  const panel = document.getElementById('payment-panel');
  const errorEl = document.getElementById('payment-error');
  errorEl.classList.add('hidden');
  panel.innerHTML = '<p class="text-sm text-muted">Preparing your order…</p>';
  try {
    await ensureOrder();
  } catch (e) {
    errorEl.textContent = e.message; errorEl.classList.remove('hidden'); panel.innerHTML = ''; return;
  }

  if (id === 'stripe') renderStripePanel(panel, errorEl);
  else if (id === 'paypal') renderPaypalPanel(panel, errorEl);
  else renderSchoolPoPanel(panel, errorEl);
}

async function renderStripePanel(panel, errorEl) {
  try {
    const { clientSecret } = await api('/api/ajax/stripe_intent.php', { orderId });
    const stripe = Stripe(settings.payments.stripe.publishableKey);
    stripeElements = stripe.elements({ clientSecret });
    panel.innerHTML = '<div id="stripe-payment-element"></div><button id="stripe-pay-btn" class="btn btn-primary w-full mt-3" style="justify-content:center;">Pay now</button>';
    stripeElements.create('payment').mount('#stripe-payment-element');
    document.getElementById('stripe-pay-btn').addEventListener('click', async () => {
      const result = await stripe.confirmPayment({ elements: stripeElements, redirect: 'if_required' });
      if (result.error) { errorEl.textContent = result.error.message; errorEl.classList.remove('hidden'); return; }
      if (result.paymentIntent) await api('/api/ajax/stripe_confirm.php', { paymentIntentId: result.paymentIntent.id });
      showPaid();
    });
  } catch (e) {
    errorEl.textContent = e.message; errorEl.classList.remove('hidden');
  }
}

function renderPaypalPanel(panel, errorEl) {
  panel.innerHTML = '<div id="paypal-buttons"></div>';
  const script = document.createElement('script');
  script.src = `https://www.paypal.com/sdk/js?client-id=${encodeURIComponent(settings.payments.paypal.clientId)}&currency=USD`;
  script.onload = () => {
    paypal.Buttons({
      createOrder: async () => (await api('/api/ajax/paypal_order.php', { orderId })).paypalOrderId,
      onApprove: async (data) => {
        try { await api('/api/ajax/paypal_capture.php', { orderId, paypalOrderId: data.orderID }); showPaid(); }
        catch (e) { errorEl.textContent = e.message; errorEl.classList.remove('hidden'); }
      },
      onError: () => { errorEl.textContent = 'PayPal checkout failed'; errorEl.classList.remove('hidden'); },
    }).render('#paypal-buttons');
  };
  document.body.appendChild(script);
}

function renderSchoolPoPanel(panel, errorEl) {
  panel.innerHTML = `
    <div class="field"><label>Purchase order number</label><input type="text" id="po-number"></div>
    <div class="field"><label>District</label><input type="text" id="po-district"></div>
    <button id="po-submit" class="btn btn-primary w-full" style="justify-content:center;">Submit PO — we'll invoice your district</button>
  `;
  document.getElementById('po-submit').addEventListener('click', async () => {
    try {
      await api('/api/ajax/school_po.php', { orderId, poNumber: document.getElementById('po-number').value, district: document.getElementById('po-district').value });
      showPaid();
    } catch (e) { errorEl.textContent = e.message; errorEl.classList.remove('hidden'); }
  });
}

function showPaid() {
  cartClear();
  document.getElementById('checkout-wizard').classList.add('hidden');
  document.getElementById('checkout-paid').classList.remove('hidden');
  document.getElementById('paid-message').textContent = `Order #${orderId} is confirmed.`;
}

document.addEventListener('DOMContentLoaded', async () => {
  if (cartGet().length === 0) {
    document.getElementById('checkout-empty').classList.remove('hidden');
    return;
  }
  document.getElementById('checkout-wizard').classList.remove('hidden');
  settings = await (await fetch('/api/ajax/public_settings.php')).json();
  updateIndicator();
});
</script>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
