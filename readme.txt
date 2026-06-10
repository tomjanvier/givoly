=== Givoly ===
Contributors: plaidact
Tags: donation, nonprofit, stripe, helloasso, fundraising
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Donation forms for nonprofits with Stripe and HelloAsso payments, donor management, and campaign tracking.

== Description ==

**Givoly** helps nonprofits publish donation forms, collect one-time online donations, manage donors, and follow fundraising campaigns from the WordPress dashboard.

Givoly is created and maintained by **PLAID·ACT**, a nonprofit association: https://plaidact.org

= Included features =

* **Donation form** via shortcode — 5 themes (Givoly, Classic, Ocean, Sunset, Minimal), 3 layouts (Card, Inline, Flat)
* **Stripe gateway** — secure card payments via Checkout Session
* **HelloAsso gateway** — payment option for French associations
* **Donor management** — donor list, donation history, donation totals
* **Admin dashboard** — donation statistics and latest donations
* **Fundraising campaigns** — goals, dates, progress gauges
* **Customizable emails** — logo URL, primary color, sender name, thank-you subject and body
* **CSV donation export** from the donations screen

= Shortcodes =

**Donation form**
`[givoly_form]`

Available attributes:

| Attribute | Default | Values |
|---|---|---|
| `theme` | `givoly` | `givoly`, `classic`, `ocean`, `sunset`, `minimal` |
| `layout` | `card` | `card`, `inline`, `flat` |
| `amounts` | `10,25,50,100` | comma-separated integers |
| `currency` | `EUR` | `EUR`, `USD`, `GBP`, `MAD`, `CHF` |
| `campaign` | _(empty)_ | campaign slug |
| `title` | `Faire un don` | free text |
| `button_text` | `Donner maintenant` | free text |
| `show_title` | `yes` | `yes`, `no` |
| `gateway` | _(admin setting)_ | `stripe`, `helloasso` |

**Campaign gauge**
`[givoly_total campaign="my-campaign" display="bar"]`

**Full campaign (gauge + form)**
`[givoly_campaign campaign="my-campaign"]`

= Logo and visual branding =

Givoly does not ship a bitmap brand logo. The admin header currently uses a heart Dashicon and a small emoji in the settings header.

To change the email logo shown to donors, go to **Givoly > Réglages > Email > URL du logo** and paste the URL of your association logo.

To change the plugin/admin visual mark in code:

* Main WordPress admin menu icon: `includes/Admin/AdminMenu.php` (`dashicons-heart`)
* Settings page header emoji: `includes/Admin/Pages/SettingsPage.php` (`givoly-header__logo`)
* Frontend colors and button shape: **Givoly > Réglages > Apparence**

= Compatibility =

* WordPress 6.0+
* PHP 8.1+
* Stripe API v3 (Checkout Session)
* HelloAsso API v5
* Browsers: Chrome, Firefox, Safari, Edge (last 2 versions)

= Privacy (GDPR) =

Givoly collects donor personal data (name, email, and optional profile fields) solely for recording donations and managing donors. Data is stored locally in the association's WordPress database and is transmitted to the selected payment gateway only when needed for payment processing.

== Installation ==

1. Upload the `givoly` folder to `/wp-content/plugins/` or install the ZIP from the WordPress admin.
2. Activate **Givoly** from the Plugins screen.
3. Go to **Givoly > Réglages** and configure Stripe and/or HelloAsso.
4. Add `[givoly_form]` to a page or post.

== Frequently Asked Questions ==

= Who creates Givoly? =

Givoly is created by **PLAID·ACT**, a nonprofit association. Website: https://plaidact.org

= Does Givoly include upsells? =

No. This package is cleaned for publication as a single free plugin and does not include upsell screens or locked-feature placeholders.

= Where do I change the logo? =

For donor emails, use **Givoly > Réglages > Email > URL du logo**. For the admin/plugin mark, edit the menu icon in `includes/Admin/AdminMenu.php` and the settings header mark in `includes/Admin/Pages/SettingsPage.php`.

= Can I use only HelloAsso or only Stripe? =

Yes. Configure the gateway you want to use and select it as the default gateway in **Givoly > Réglages > Général**.

== Changelog ==

= 1.0.0 =
* Renamed and rebranded the plugin to Givoly.
* Updated plugin author information to PLAID·ACT.
* Removed upsell placeholders and unused payment schedule/tax-document structures.
* Kept the donation form, Stripe, HelloAsso, donors, campaigns, settings, email customization, and exports ready for WordPress publication.
