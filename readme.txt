=== Givoly ===
Contributors: plaidact, tomjanvier, tomjvr
Tags: donation, nonprofit, stripe, helloasso, fundraising
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Givoly is a completely free donation form extension designed by and for nonprofit organizations, featuring payments via Stripe and HelloAsso, donor management, and campaign progress tracking.

== Description ==

**Givoly** is a completely free donation form extension designed by and for nonprofit organizations. It lets nonprofits collect donations through Stripe and/or HelloAsso, manage donors, track fundraising campaigns, and send customizable donor emails.

The plugin is maintained by **PLAID·ACT and its members**, with contributions from **Tom JANVIER**. PLAID·ACT is a nonprofit organization: https://plaidact.org

= Main features =

* **Donation forms with shortcodes**, including 5 visual themes (Givoly, Classic, Ocean, Sunset, Minimal) and 3 layouts (Card, Inline, Flat).
* **Stripe payments** through Checkout Sessions.
* **HelloAsso payments** with a dedicated payment button using the plugin's bundled HelloAsso logo asset, including one-time and recurring donation flows through the HelloAsso API.
* **Donor management** with donation history, total donated, and latest donation details.
* **Fundraising campaigns** with goals, dates, descriptions, and progress bars.
* **Admin dashboard** with statistics and latest donations.
* **Customizable emails**: sender, logo, color, subject, and thank-you message.
* **Annual fiscal receipt summary emails** from the Donors page, grouped by fiscal year.
* **CSV donation exports** with protection against formula injection.
* **No tracking added by the plugin**: no advertising pixels, analytics scripts, or telemetry.

= Shortcodes =

**Donation form**

`[givoly_form]`

Available attributes:

| Attribute | Default value | Possible values |
|---|---|---|
| `theme` | `givoly` | `givoly`, `classic`, `ocean`, `sunset`, `minimal` |
| `layout` | `card` | `card`, `inline`, `flat` |
| `amounts` | `10,25,50,100` | whole-number amounts separated by commas |
| `currency` | `EUR` | `EUR`, `USD`, `GBP`, `MAD`, `CHF` |
| `campaign` | empty | campaign slug |
| `title` | `Make a donation` | custom text |
| `button_text` | `Donate now` | custom text |
| `show_title` | `yes` | `yes`, `no` |
| `gateway` | admin setting | `stripe`, `helloasso` |
| `class` | empty | custom CSS class added to the form wrapper |

**Campaign total widget**

`[givoly_total campaign="my-campaign" display="bar"]`

**Full campaign block (progress bar + form)**

`[givoly_campaign campaign="my-campaign"]`

The campaign shortcode also accepts `show_title="no"`, `show_form`, `show_description`, `layout`, and `theme`.

= Annual fiscal receipt summaries =

From **Givoly > Donors**, an admin panel lets you select a fiscal year and send a summary email to each donor who has at least one completed donation during that period.

Before sending, complete the nonprofit details in **Givoly > Settings > Organization**: name, address, email, SIRET/RNA, and tax approval or ruling information. The email reminds donors of the total amount given, the number of donations, and the fiscal information configured by the organization.

= Quick setup =

1. Activate the plugin.
2. Go to **Givoly > Settings**.
3. Configure at least one payment gateway: Stripe, HelloAsso, or both.
4. Complete your organization information.
5. Customize emails if needed.
6. Add `[givoly_form]` to a page or post.

== Installation ==

1. Upload the `givoly` folder to `/wp-content/plugins/` or install the ZIP archive from the WordPress admin area.
2. Activate **Givoly** from the Plugins screen.
3. Configure your payment gateways in **Givoly > Settings**.
4. Publish a donation form with `[givoly_form]`.

== Frequently Asked Questions ==

= Who develops Givoly? =

Givoly is developed and maintained by **PLAID·ACT and its members**, with contributions from **Tom JANVIER**. PLAID·ACT is a nonprofit organization: https://plaidact.org

= Can I use only HelloAsso or only Stripe? =

Yes. Enable the gateway you want in **Givoly > Settings > General**. If both Stripe and HelloAsso are enabled, the form displays both payment options.

= Where can I change the email logo? =

Go to **Givoly > Settings > Email > Logo URL**. If no URL is provided, the organization name is displayed instead.

= Does the plugin generate fiscal receipt PDFs? =

No. The annual sending tool sends fiscal summary emails to donors. You can use these emails to prepare your fiscal receipt campaign and attach an official receipt if your process requires one.

= Are there paid features or upsells? =

No. This package is provided as a free plugin, with no upsell screens and no paid feature restrictions.

== Privacy (GDPR) ==

Givoly collects the personal data required to process and follow donations: name, email address, and optional donor profile fields. This data is stored in the nonprofit's WordPress database and is sent to the selected payment gateway only when required to process a payment, redirect a donor to checkout, handle a webhook, or process a refund.

Givoly does not add analytics scripts, advertising pixels, tracking cookies, or telemetry.

== External services ==

Givoly relies on third-party payment services only when a site administrator configures and enables the corresponding gateway, and when a donor uses that gateway or a webhook/refund action is processed. Site owners should review the terms and privacy policies of the services they enable.

**Stripe**

* Service: Stripe is a third-party payment processor used to create Checkout Sessions, receive Stripe webhook events, and process Stripe refunds from the plugin admin screens.
* Endpoints: `https://api.stripe.com/v1` and Stripe-hosted Checkout pages.
* Data sent and when: donation amount, currency, selected frequency (one-time or recurring), donor email, donor first name, donor last name, campaign metadata, return URLs, and the configured Stripe secret key are sent when a donor starts a Stripe payment. Webhook event data is received from Stripe after payment events. Refund requests send the related Stripe payment identifier when an administrator starts a refund.
* Terms of service: https://stripe.com/legal
* Privacy policy: https://stripe.com/privacy

**HelloAsso**

* Service: HelloAsso is a third-party payment and fundraising platform used to authenticate with the HelloAsso API, create checkout intents, redirect donors to HelloAsso payment pages, and verify HelloAsso webhook events. If the fallback custom HelloAsso URL option is configured, donors can also be redirected to that configured HelloAsso URL instead of using the API checkout intent.
* Endpoints: `https://api.helloasso.com`, `https://api.helloasso-sandbox.com`, `https://www.helloasso.com`, and `https://www.helloasso-sandbox.com`.
* Data sent and when: donation amount, selected frequency (one-time or recurring), donor email, donor first name, donor last name, campaign metadata, return URLs, organization slug, and the configured HelloAsso API credentials are sent when a donor starts a HelloAsso API payment. HelloAsso webhook event data is received after payment events. If the custom HelloAsso URL option is enabled for one-time donations, the donor is redirected to the URL configured by the site administrator instead of creating an API checkout intent for that one-time donation.
* Logo loading: the frontend HelloAsso payment button uses the bundled file `assets/logo-ha.svg`; it does not load the HelloAsso logo from a remote URL.
* Terms of service: https://www.helloasso.com/cgu-utilisateur
* Privacy policy: https://www.helloasso.com/confidentialite

== Changelog ==

= 1.0.0 =
* Renamed and rebranded the plugin as Givoly.
* Updated author and contributor information for PLAID·ACT and Tom JANVIER.
* Replaced the remote HelloAsso button logo with the bundled `assets/logo-ha.svg` file.
* Added annual fiscal receipt summary emails from the Donors page.
* Cleaned up documentation and unused residual elements.
* Kept donation forms, Stripe, HelloAsso, donors, campaigns, settings, emails, and CSV exports.
