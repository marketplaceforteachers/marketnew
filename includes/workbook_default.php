<?php
/**
 * Starting content for Admin -> Workbook: a plain-language guide to running the site. Admins
 * can edit any chapter from the Workbook page; edits are saved to site_settings and this file
 * is only used until then (or after "Restore the original chapter").
 *
 * Bodies use the same markdown-lite as the blog: ## / ### headings, "- " bullets, **bold**,
 * *italic*, and [text](https://...) links. A blank line starts a new paragraph or list.
 */
function workbook_default_chapters(): array
{
    return [
        [
            'id' => 'start',
            'title' => 'Start here',
            'summary' => 'Signing in, finding your way around, and the first things to do.',
            'body' => <<<'MD'
## Signing in

The admin sign-in page is **/admin/login.php** (for example, marketplaceforteachers.com/admin/login.php). Bookmark it. Only accounts with the admin role can sign in there; teachers and buyers use the normal Log In page.

If you forget your password, use "Forgot your password?" on the sign-in page and we'll email you a reset link.

## Do these first

- Change the demo admin password (admin@example.com / AdminPass123!) or delete that account once you have your own admin account. Go to **User Manager**.
- Set up email so signup confirmations go out. See the *Email* chapter.
- Add your Stripe and PayPal keys so people can pay. See the *Payments* chapter.
- Check the homepage wording in **Branding & Homepage**.

## Where things are

- **Executive Dashboard**: today's numbers at a glance.
- **Listing Manager / Order Manager / User Manager**: day-to-day running of the shop.
- **Teacher Verification**: approve or reject Verified Educator requests.
- **Dispute Arbitration**: problems between buyers and sellers.
- **Seller Payouts**: send sellers their money after a sale.
- **Email Template Studio**: email settings, the wording of every email, and a log of every email sent.
- **Show & Hide**: switch any part of the site (menu links, header buttons, homepage sections, footer pieces) on or off.
- **Branding & Homepage / Blog / Category Manager**: the words and pictures the public sees.
- **Workbook**: this guide. Edit any chapter so it matches how *you* run things.
MD,
        ],
        [
            'id' => 'deploy',
            'title' => 'Putting updates live',
            'summary' => 'How a change on GitHub reaches marketplaceforteachers.com.',
            'body' => <<<'MD'
New code is saved on GitHub first. The live site only changes when you deploy it from cPanel.

## Every time there's an update

- Log in to cPanel and open **Git Version Control**.
- Find the **marketnew** repository and click **Manage**, then the **Pull or Deploy** tab.
- Click **Update from Remote**, then **Deploy HEAD Commit**.
- Sign in to the admin and open **/admin/migrate.php**. Click the button to run it. This brings the database up to date. It is safe to run any time; it skips anything already done.
- Open the homepage in a private/incognito window and check it looks right.

## Good to know

- Deploying never touches uploaded photos or your config.php file.
- If a page goes blank after an update, running the migration (step 4) fixes it most of the time.
MD,
        ],
        [
            'id' => 'email',
            'title' => 'Email',
            'summary' => 'Getting signup confirmations, receipts and other emails delivered.',
            'body' => <<<'MD'
The site sends signup confirmations, password resets, order receipts, shipping notices and more. Settings live in **Email Template Studio**.

## Setting up Resend (recommended)

- Create an account at [resend.com](https://resend.com).
- In Resend, go to **Domains**, add marketplaceforteachers.com, and add the DNS records it shows you in cPanel's **Zone Editor**. Wait until Resend says the domain is verified.
- In Resend, create an **API key**.
- In Email Template Studio, choose Resend, paste the API key, and set the "from" address to something on your domain, like hello@marketplaceforteachers.com.
- Click **Send test email** and check your inbox.

## When emails aren't arriving

- Look at the email log at the bottom of Email Template Studio. Every attempt is listed with the reason it failed.
- "domain is not verified": finish the DNS step in Resend.
- "not configured yet": the API key is missing.
- Emails sent but not received: ask the person to check spam, and make sure the "from" address uses your verified domain.
- No log entries at all: run **/admin/migrate.php**.

You can also use your host's email through **SMTP** instead of Resend; the same page has those settings.
MD,
        ],
        [
            'id' => 'payments',
            'title' => 'Payments and payouts',
            'summary' => 'Stripe, PayPal, school purchase orders, and paying sellers.',
            'body' => <<<'MD'
## How money moves

Buyers pay the marketplace. The site keeps the platform fee (set in **Payment Gateways**) and records what each seller is owed. You then send each seller their share from **Seller Payouts**.

## Stripe

- In Stripe, copy your **publishable key** and **secret key** into **Payment Gateways**.
- For webhooks, point Stripe at https://marketplaceforteachers.com/api/webhooks/stripe.php and paste the signing secret into Payment Gateways.
- Turn on **Stripe Connect** so sellers can be paid. When Stripe asks you to confirm your integration choices ("buyers will purchase from you", "sellers paid out individually", "onboarding hosted by Stripe", "Express Dashboard"), those match how this site works, so click **Continue**.
- Sellers connect their own bank account from **Seller Dashboard → Connect Payouts**.

## Sending payouts

- Open **Seller Payouts**. Each sale creates a row marked pending.
- Click **Send** to transfer the money to the seller's Stripe account. The button is greyed out until the seller has connected Stripe.
- Our habit: send payouts once the buyer has the item, or after a few days with no complaint.

## PayPal and school purchase orders

- PayPal: paste the client ID and secret in Payment Gateways. Use "sandbox" while testing.
- School PO: turn it on and write the instructions schools should follow. You invoice the school yourself.

## Refunds and chargebacks

The marketplace is responsible for refunds and chargebacks, so hold payouts if an order looks wrong. Refund from **Order Manager** or while settling a dispute.
MD,
        ],
        [
            'id' => 'verification',
            'title' => 'Teacher verification',
            'summary' => 'Who gets the Verified Educator badge and how to review requests.',
            'body' => <<<'MD'
Anyone can buy and sell. Teachers who prove they work at a school get the **Verified Educator** badge, which shows on their listings and helps buyers trust them.

## Reviewing a request

- Open **Teacher Verification**.
- A school email (.edu, .k12, or a district domain) is usually enough.
- For an uploaded document (staff ID, employment letter, teaching license), check the name matches the account and the school is real.
- Approve, or reject with a short, kind note explaining what's missing. The teacher is emailed either way.

## Privacy

Never ask for or keep anything about students. If a document shows student information, reject it and ask for a different one.
MD,
        ],
        [
            'id' => 'disputes',
            'title' => 'Disputes and problem orders',
            'summary' => 'What to do when a buyer and seller disagree.',
            'body' => <<<'MD'
## Handling a dispute

- Open **Dispute Arbitration** and read what the buyer wrote.
- Message the seller through **Messaging Oversight** and ask for their side, plus any tracking number or photos.
- Give both sides a couple of days to reply.
- Decide: refund the buyer (in full or in part) or close the dispute in the seller's favor. Write a short note explaining why. The person who opened the dispute is emailed the decision; let the other person know through Messaging Oversight.

## Rules of thumb

- Item never arrived and no tracking: refund the buyer.
- Item arrived but very different from the photos: refund, and ask the buyer to send it back if it's worth the postage.
- Buyer changed their mind: not a dispute; suggest they relist the item.
- Hold the seller's payout until the dispute is settled.
MD,
        ],
        [
            'id' => 'listings',
            'title' => 'Listings, users and safety',
            'summary' => 'Keeping the marketplace friendly and safe.',
            'body' => <<<'MD'
## Not allowed on the site

- Anything recalled, broken in an unsafe way, or not safe around children.
- Chemicals, medicines, weapons, or anything illegal.
- Copied or pirated materials, such as photocopied workbooks or cracked software.
- Anything that shows students' faces, names or work.

## What to do

- Remove a listing from **Listing Manager**, then message the seller to say why.
- Check **Safety & Risk Monitor** every few days. It shows the dispute rate, lots of accounts from one email domain, and cancelled orders, which can all be early signs of trouble.
- Ban an account from **User Manager** if someone keeps breaking the rules or tries to scam people. Banned users can't log in.
- **Reviews Moderation**: remove reviews that are abusive or clearly fake, not ones that are simply negative.
MD,
        ],
        [
            'id' => 'content',
            'title' => 'Homepage, blog and wording',
            'summary' => 'Changing what visitors see.',
            'body' => <<<'MD'
## Homepage

Edit the headline, intro, badge chips and footer in **Branding & Homepage**. Changes show straight away.

- Wrap a few words of a headline in *asterisks* to show them in the gold italic accent style.
- Keep claims true. Don't add ratings, awards or numbers we can't prove.

## Writing tips

- Write the way you'd talk to a teacher at school: short sentences, everyday words.
- Say what someone can do, not how amazing the site is.

## Blog

- Write posts in **Blog**. The AI writer can draft a post, but it's always saved as a draft. Read it, fix anything wrong, then publish.

## Categories and seasonal pills

Add or rename categories in **Category Manager**. Turn the seasonal pills, the "Free stuff only" link, and any other part of the site on or off in **Show & Hide**.
MD,
        ],
        [
            'id' => 'routine',
            'title' => 'Weekly routine',
            'summary' => 'A short checklist to keep things running smoothly.',
            'body' => <<<'MD'
## Every day (10 minutes)

- Glance at the **Executive Dashboard**.
- Answer new **disputes** and verification requests.
- Check the email log in **Email Template Studio** for failures.

## Every week

- Send pending **Seller Payouts** for orders that arrived safely.
- Look through **Safety & Risk Monitor** and new listings.
- Publish or delete blog drafts.

## Every month

- Look over **Financial & Tax Reporting**.
- Review the **Admin Audit Log** to see who changed what.
- Re-read this workbook and update anything that's changed.
MD,
        ],
    ];
}
