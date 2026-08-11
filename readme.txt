=== Givoly ===
Contributors: plaidact, tomjanvier
Tags: donation, nonprofit, stripe, helloasso, fundraising
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

French-first donation forms for nonprofits with Stripe, HelloAsso, donor management, campaigns, and receipt emails.

== Description ==

**Givoly is primarily designed for French nonprofits.** It provides a clear, no-subscription way to collect donations in WordPress, while remaining useful to organizations that use Stripe or HelloAsso elsewhere.

**More information:** visit [givoly.org](https://givoly.org/).

Built and maintained by [PLAID·ACT](https://plaidact.org), Givoly is free software: it adds no plugin commission, no premium upsell, and no donor tracking or telemetry.

= What Givoly includes =

* Donation forms with `[givoly_form]`, five visual themes, and card, inline, or flat layouts.
* Stripe Checkout and HelloAsso payments, with signed webhooks and WP-Cron reconciliation for missed payments.
* Recurring Stripe donation handling, including paid invoice imports, subscription management, and refunds from the donations screen.
* Donor records, a secure passwordless donor area via `[givoly_donor_space]`, donation history, and PDF downloads.
* Manual donations for bank transfers, cheques, and cash.
* Fundraising campaigns with goals, dates, descriptions, and progress bars.
* Customizable emails, queued delivery, annual receipt summaries, and individual or batch PDF receipt sending.
* CSV exports protected against spreadsheet formula injection.
* French nonprofit fields including SIRET, RNA, and tax approval information.

= Quick start =

1. Activate Givoly.
2. Go to **Givoly > Settings** and configure Stripe, HelloAsso, or both.
3. Enter your organization information and email settings.
4. Add `[givoly_form]` to a page or post.

= Shortcodes =

**Donation form**

`[givoly_form]`

Useful attributes: `theme`, `layout`, `amounts`, `currency`, `campaign`, `title`, `button_text`, `show_title`, `gateway`, and `class`.

Example: `[givoly_form campaign="emergency" theme="givoly" layout="card" gateway="helloasso"]`

**Donor area**

`[givoly_donor_space]`

Donors request a one-time access link by email. They can review completed donations, download eligible PDF receipts, and use Stripe's secure customer portal for a recurring donation.

**Campaign widgets**

`[givoly_total campaign="emergency" display="bar"]`

`[givoly_campaign campaign="emergency"]`

= Styling donation forms =

Use WordPress's native Additional CSS editor: **Appearance > Customize > Additional CSS**, or **Appearance > Editor > Styles** on block themes. Givoly keeps structured controls for colors, corners, and button style, but does not store or execute arbitrary CSS.

== Installation ==

1. Upload the `givoly` folder to `/wp-content/plugins/`, or install the ZIP from **Plugins > Add New > Upload Plugin**.
2. Activate **Givoly**.
3. Configure at least one payment gateway in **Givoly > Settings**.
4. Publish a page containing `[givoly_form]`.

== Frequently Asked Questions ==

= Is Givoly only for French organizations? =

Givoly is French-first: its built-in nonprofit and fiscal fields support French organizations, including SIRET and RNA identifiers. Any organization using Stripe or HelloAsso can use the donation forms.

= Does Givoly charge a commission? =

No. Givoly adds no subscription or commission. Stripe and HelloAsso may apply their own terms or fees.

= Can I use Stripe or HelloAsso alone? =

Yes. Enable either gateway, or both, in **Givoly > Settings > General**.

= Can donors manage recurring Stripe donations? =

Yes. Add `[givoly_donor_space]` to a page. The donor receives a secure, passwordless link and can open Stripe's customer portal to update or cancel an eligible subscription.

= Does the PDF replace an official tax receipt? =

No. Givoly can prepare a configurable PDF summary, but your organization remains responsible for issuing legally valid tax receipts.

= How do I migrate from Givasso? =

Deactivate Givasso before activating Givoly. Givoly copies compatible settings and data into `givoly_*` tables on first load, keeps the original Givasso tables as a safety copy, and supports the legacy shortcodes during the transition.

== Privacy ==

Givoly stores donation and donor data in the site's WordPress database. It does not add analytics scripts, advertising pixels, tracking cookies, telemetry, or remote assets unrelated to payment processing. UTM parameters are used only on optional Givoly and PLAID·ACT support or branding links, not on donation or donor links.

Site owners are responsible for informing donors about their own privacy practices and the payment services they enable.

== External services ==

= Stripe =

Stripe is used only when enabled by the administrator and chosen for a payment, webhook, refund, subscription portal, or reconciliation. Givoly sends the donation amount, currency, donor contact details, campaign metadata, return URLs, and the relevant payment identifier as required for those actions.

* Service: https://stripe.com/
* Terms: https://stripe.com/legal
* Privacy: https://stripe.com/privacy

= HelloAsso =

HelloAsso is used only when enabled by the administrator and chosen for a payment, webhook, or synchronization. Givoly sends the donation amount, donor contact details, campaign metadata, return URLs, organization slug, and configured API credentials as required for those actions. The HelloAsso logo used by the form is bundled with the plugin.

* Service: https://www.helloasso.com/
* Terms: https://www.helloasso.com/cgu-utilisateur
* Privacy: https://www.helloasso.com/confidentialite

== Changelog ==

= 1.4.0 =
* Added HelloAsso v5 synchronization and Stripe recurring-payment reconciliation.
* Added donor space, manual donations, recurring donation administration, and queued PDF receipt delivery.
* Prepared English source strings, French bundled translations, and the GlotPress translation template.
* Added UTM attribution to optional Givoly and PLAID·ACT support and branding links.

= 1.3.0 =
* Added non-destructive Givasso migration and compatibility aliases.

= 1.2.0 =
* Added the Givoly dashboard, WordPress dashboard widget, and optional nonprofit support information.

= 1.1.0 =
* Added a persistent email queue and batch fiscal receipt sending.

= 1.0.1 =
* Removed the plugin-owned arbitrary CSS setting and directed styling to WordPress's native CSS editor.

= 1.0.0 =
* Renamed and rebranded the plugin as Givoly.
