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
(`pdo_mysql`, `curl`, `openssl`, `gd` — all standard, `gd` powers profile-photo and blog-image
uploads). PHP 8.0+ recommended.

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

## Blog + AI auto-writer

**Admin → Blog** is a small CMS (`blog_posts` table) with public pages at `/blog.php` (listing)
and `/blog-post.php?slug=...` (post) — full SEO: Article + BreadcrumbList structured data, sitemap
inclusion, per-post meta tags. Write posts manually there any time.

Optionally, an AI auto-writer can draft posts for you: it searches free, keyless Google News RSS
for education topics, sends a headline to Claude via the Anthropic API to draft a post, and saves
it as a **draft** — never published automatically. Set it up in **Admin → Blog**: paste an
Anthropic API key, enable it, and either click "Generate a Draft Now" or point a cron job at
`cron/generate_blog_post.php?key=YOUR_CRON_SECRET` (same secret/pattern as the email drip cron
below) on whatever schedule you want drafts (daily/weekly). Every draft still needs a human to
review and click Publish in Admin → Blog before it's public.

Post content is stored as plain markdown-lite text (`##`/`###` headings, `-` bullets, `**bold**`,
`*italic*`, `[text](url)`, `![alt](url)` images), not raw HTML — rendered through a small
allowlist converter (`render_blog_markdown()` in `includes/helpers.php`) rather than trusted as
HTML, since AI-drafted posts are built from external news text an attacker could plant
prompt-injection content in.

## Profile photos and image uploads

Sellers and buyers can add a profile photo from **My Account** (optional); blog posts can use an
uploaded cover image and inline content images from **Admin → Blog**, instead of only pasting an
image URL. Every upload goes through `includes/uploads.php`, which never trusts the uploaded bytes
or filename — it decodes the file with GD and re-encodes it to a fresh JPEG (rejecting anything
that isn't a genuinely valid JPEG/PNG/WebP) before writing it to `uploads/avatars/` or
`uploads/blog/` under a random filename. Those two folders are `.gitignore`d (only their
`.htaccess`, which blocks execution of anything in them, and a `.gitkeep` are tracked), so
uploaded content lives only on the live server and is never overwritten by a deploy.

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
  `blog_ai.php` (news search + Claude draft generation), `layout_header.php` / `layout_footer.php`
  / `admin_layout_header.php` / `admin_layout_footer.php` (shared chrome)
- `assets/` — hand-written CSS (`style.css`) and vanilla JS (`app.js`, cart + a few dynamic forms)
- `db/` — `schema.sql` (20 tables), `seed.sql` (demo data)
- Root `*.php` — public pages (mirrors the feature set 1:1: browse, listing detail, post-listing,
  cart, checkout, wishlists, campaigns, messages, disputes, seller dashboard, blog, etc.)
- `admin/*.php` — all 19 admin modules
- `api/ajax/*.php` — small JSON endpoints for the client-side bits that need them (cart-adjacent
  actions stay in localStorage; checkout/donation payment steps, Stripe Connect onboarding)
- `api/webhooks/stripe.php` — Stripe webhook receiver with hand-rolled signature verification
- `cron/` — `send_drips.php` (email drip campaigns), `generate_blog_post.php` (AI blog draft)
