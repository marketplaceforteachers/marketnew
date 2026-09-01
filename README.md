# MarketplaceForTeachers.com — PHP Edition

A zero-build-step, shared-hosting-deployable version of the marketplace: plain PHP + MySQL, no
Composer, no Node, no framework. Everything (Stripe, PayPal, Resend) talks to its API over raw
`curl`, and Stripe.js / the PayPal JS SDK load straight from their own CDNs — nothing to install.

## Deploying to shared hosting (cPanel-style)

1. **Upload this whole `php-app/` folder** to your host via FTP or the cPanel File Manager —
   typically into `public_html/` (or a subfolder if you want it at a sub-path).
2. **Create a MySQL database** in your host's control panel (most give you a name/user/password
   directly, or a "MySQL Databases" tool to create one).
3. **Visit `https://yourdomain.com/install.php`** in a browser. Enter the database host, port,
   name, username, and password your host gave you, and your site's URL. It creates the tables,
   seeds demo data, and writes `config.php` for you.
4. **Delete or rename `install.php`** once installed (it re-checks for `config.php` on every load,
   so it's not exploitable once set up, but it's good hygiene to remove it).
5. Log in as `admin@example.com` / `AdminPass123!` (shown again at the end of setup) and change the
   password from **Admin → User Manager**, or just create your own admin account and demote/delete
   the demo one.

No `.env` file, no `npm run build`, no PHP extensions beyond the defaults most hosts already have
(`pdo_mysql`, `curl`, `openssl` — all standard). PHP 8.0+ recommended.

## Turning on real payments and email

Nothing works with fake credentials — go to **Admin → Payment Gateways** and paste in:
- A Stripe secret + publishable key (and webhook signing secret, pointed at
  `https://yourdomain.com/api/webhooks/stripe.php`, if you want async payment confirmation —
  synchronous confirmation on the checkout page works without it too).
- PayPal client ID + secret (sandbox or live).
- School PO needs no external key — just toggle it on and set the buyer-facing instructions.

Then **Admin → Email Template Studio** for a Resend API key and verified from-address — every
transactional email (welcome, order confirmation, shipping, verification, dispute resolution,
donation receipt) is editable there with `{{token}}` placeholders.

Sellers connect their own payouts from **Seller Dashboard → Connect Payouts** (Stripe Connect
Express onboarding).

## Customizing the site

**Admin → Branding & Homepage** edits the hero copy, promo card, trust-bar stats, accent color, and
footer — live immediately, no redeploy. **Admin → Site Feature Toggles** controls the seasonal-hub
pill bar and the free-surplus banner. **Admin → Category Manager** controls the categories used
across browse/post-listing.

## Local development

```
php -S localhost:8080
```

from inside `php-app/`, pointed at a local MySQL/MariaDB instance (run `install.php` the same way
you would in production).

## Structure

- `install.php` — first-run setup wizard
- `includes/` — `db.php` (PDO), `auth.php` (sessions), `settings.php` (site_settings /
  payment_gateway_configs / integration_configs), `stripe.php` / `paypal.php` / `resend.php` (raw
  cURL API wrappers), `orders.php` (payment finalization + fee-split payouts + refunds),
  `layout_header.php` / `layout_footer.php` / `admin_layout_header.php` /
  `admin_layout_footer.php` (shared chrome)
- `assets/` — hand-written CSS (`style.css`) and vanilla JS (`app.js`, cart + a few dynamic forms)
- `db/` — `schema.sql` (19 tables), `seed.sql` (demo data)
- Root `*.php` — public pages (mirrors the feature set 1:1: browse, listing detail, post-listing,
  cart, checkout, wishlists, campaigns, messages, disputes, seller dashboard, etc.)
- `admin/*.php` — all 18 admin modules
- `api/ajax/*.php` — small JSON endpoints for the client-side bits that need them (cart-adjacent
  actions stay in localStorage; checkout/donation payment steps, Stripe Connect onboarding)
- `api/webhooks/stripe.php` — Stripe webhook receiver with hand-rolled signature verification
