=== Givasso ===
Contributors: otmaaan91
Tags: donation, nonprofit, stripe, helloasso, tax receipt
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Donation forms for French nonprofits. HelloAsso & Stripe integration, donor management, campaign tracking. Free.

== Description ==

**Givasso** is the donation plugin built for **French nonprofits**. In minutes, set up an online donation form, manage donors, and run fundraising campaigns — all from your WordPress dashboard.

= Free features =

* **Donation form** via shortcode — 5 themes (Givasso, Classic, Ocean, Sunset, Minimal), 3 layouts (Card, Inline, Flat)
* **Stripe gateway** — secure card payments via Checkout Session
* **HelloAsso gateway** — the leading French nonprofit payment platform, zero commission
* **Donor management** — complete history, donation totals
* **Admin dashboard** — real-time statistics, latest donations
* **Fundraising campaigns** — goals, dates, progress gauges
* **Customizable emails** — logo, primary color, sender name

= Pro features (givasso.fr/pro) =

* **CERFA 2041-RD tax receipts** — PDF generated and emailed automatically (French legal requirement, CGI Art. 200)
* **Recurring donations** (monthly / yearly) via Stripe Subscriptions
* **CSV accounting export** — donations and donors in spreadsheet format
* **FEC DGFiP export** — French General Tax Administration format

= Why Givasso? =

* Built for **French nonprofits** — native HelloAsso support, French fiscal compliance out of the box
* **HelloAsso** integration — zero-commission platform used by 90,000+ French associations
* No external dependencies — no Composer, no JS bundler
* Clean, secure, auditable code — OWASP Top 10, nonces, capabilities, prepare()

= Shortcodes =

**Donation form**
`[givasso_form]`

Available attributes:

| Attribute | Default | Values |
|---|---|---|
| `theme` | `givasso` | `givasso`, `classic`, `ocean`, `sunset`, `minimal` |
| `layout` | `card` | `card`, `inline`, `flat` |
| `amounts` | `10,25,50,100` | comma-separated integers |
| `currency` | `EUR` | `EUR`, `USD`, `GBP`, `MAD`, `CHF` |
| `campaign` | _(empty)_ | campaign slug |
| `title` | `Faire un don` | free text |
| `button_text` | `Donner maintenant` | free text |
| `show_title` | `yes` | `yes`, `no` |
| `gateway` | _(admin setting)_ | `stripe`, `helloasso` |
| `frequency` | `once` | `once` |

**Campaign gauge**
`[givasso_total campaign="my-campaign" display="bar"]`

**Full campaign (gauge + form)**
`[givasso_campaign campaign="my-campaign"]`

= Compatibility =

* WordPress 6.0+
* PHP 8.1+
* Stripe API v3 (Checkout Session)
* HelloAsso API v5
* Browsers: Chrome, Firefox, Safari, Edge (last 2 versions)

= Privacy (GDPR) =

Givasso collects donor personal data (name, email) solely for recording donations and managing donors. Data is stored locally in the association's WordPress database and is never transmitted to third parties, except to payment gateways (Stripe, HelloAsso) for payment processing.

The association is the data controller under GDPR. Givasso collects no telemetry or usage data.

== External Services ==

This plugin connects to the following third-party services. Their use is subject to their respective terms and conditions.

= Stripe =
Used for processing card payments.
* Service: https://stripe.com
* Terms of use: https://stripe.com/legal
* Privacy policy: https://stripe.com/privacy
Data transmitted includes the donation amount and payment session information. No card data passes through your server.

= HelloAsso =
Used as an alternative payment gateway, with zero commission for nonprofits.
* Service: https://www.helloasso.com
* Terms of use: https://www.helloasso.com/cgu
* Privacy policy: https://www.helloasso.com/confidentialite
Data transmitted includes the donation amount and your organization slug.

== Installation ==

1. Download the plugin and unzip it to `/wp-content/plugins/givasso/`
2. Activate the plugin under **Plugins > Installed Plugins**
3. Go to **Givasso > Settings** and configure:
   - Your Stripe API keys (test then live mode)
   - Your association information (name, address, SIRET, RNA)
   - The redirect URL after payment
4. Add the shortcode `[givasso_form]` to any page
5. Test with a Stripe test card (`4242 4242 4242 4242`)

= Stripe webhook setup =

To record donations in the database, configure a Stripe webhook pointing to:
`https://your-site.com/wp-json/givasso/v1/webhook`

Events to enable: `checkout.session.completed`, `charge.refunded`

= HelloAsso setup =

1. Create an OAuth2 application at api.helloasso.com
2. Enter your `client_id`, `client_secret` and organization slug in Givasso settings
3. Configure the HelloAsso webhook pointing to: `https://your-site.com/wp-json/givasso/v1/helloasso-webhook`

== Frequently Asked Questions ==

= Is the plugin free? =

Yes. The free version includes donation forms, Stripe, HelloAsso, donor management, and fundraising campaigns. CERFA tax receipts, recurring donations, and accounting exports are available in Givasso Pro at givasso.fr/pro.

= Does HelloAsso charge commissions? =

No. HelloAsso is free for French nonprofits. Donors may optionally tip HelloAsso during payment.

= Can I have multiple forms on the same page? =

Yes. Each shortcode generates an independent form with unique identifiers.

= Is the data secure? =

All SQL queries use `$wpdb->prepare()`. Output is escaped (`esc_html`, `esc_attr`, `esc_url`). Forms are protected by CSRF nonces. Webhooks are verified by HMAC-SHA256 signature.

= Can I customize the form design? =

Yes. Choose from 5 built-in themes or override templates in your theme by copying the `templates/` files to `your-theme/givasso/`. The `flat` layout (no card, transparent background) integrates seamlessly into any design. The form automatically inherits the theme typography.

= Does it work with WooCommerce? =

Givasso is independent of WooCommerce. Both plugins can coexist without conflict.

== Screenshots ==

1. Donation form — Classic theme, Card layout
2. Donation form — Ocean theme, Inline layout
3. Campaign gauge with progress and integrated form
4. Admin dashboard — statistics and latest donations
5. Campaign management — list and creation form
6. Settings — Stripe, HelloAsso and association configuration

== Changelog ==

= 1.0.0 =
* Added: `flat` layout — card-less form with transparent background
* Added: automatic theme typography inheritance (`font-family: inherit`)
* Improved: `[givasso_campaign]` — integrated form automatically uses `flat` layout
* Improved: dynamic CTA button — displays "Donate XX €" based on selected amount
* Improved: "100% secure payment" badge colored by gateway (purple Stripe, red HelloAsso)
* Fixed: `gateway` and `frequency` shortcode attributes silently ignored
* Fixed: default theme corrected to `givasso` (was `classic` in ShortcodeManager)
* Added: Stripe refund from admin + `charge.refunded` webhook
* Added: rate limiting 5 req/min/IP (dual backend: object cache or transients)

= 0.7.0 =
* Added: fundraising campaigns with goals, dates and progress gauges
* Added: `[givasso_campaign]` and `[givasso_total display="bar"]` shortcodes
* Added: customizable emails (logo, primary color, sender name)
* Added: real-time email preview in admin (sandboxed iframe)

= 0.6.0 =
* Improved: HelloAsso gateway — refund webhook handling
* Added: Pro features available at givasso.fr/pro

= 0.5.0 =
* Improved: Stripe and HelloAsso webhook stability
* Improved: enhanced idempotency on duplicate payments

= 0.4.0 =
* Added: HelloAsso gateway (OAuth2, checkout intent, HMAC webhook)
* Added: HelloAsso settings in admin
* Added: `gateway` shortcode attribute (`stripe`, `helloasso`)
* Fixed: WordPress user-agent blocked by Cloudflare — replaced with `Givasso/1.0`

= 0.1.0 =
* Initial release
* Donation form (shortcode, 4 themes, 2 layouts)
* Stripe gateway — Checkout Session + webhook
* Admin dashboard
* MySQL tables: donors, donations

== Upgrade Notice ==

= 1.0.0 =
First stable release. Automatic database update (DB_VERSION 1.4). No existing data is deleted.
