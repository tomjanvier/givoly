<!-- Improved compatibility of back to top link: See: https://github.com/othneildrew/Best-README-Template/pull/73 -->
<a id="readme-top"></a>

[![WordPress][wordpress-shield]][wordpress-url]
[![PHP][php-shield]][php-url]
[![Stripe][stripe-shield]][stripe-url]
[![HelloAsso][helloasso-shield]][helloasso-url]
[![License: GPL v2+][license-shield]][license-url]

<br />
<div align="center">
  <a href="https://givoly.org/">
    <img src="logo.png" alt="Givoly logo" width="96" height="96">
  </a>

  <p align="center">
    Givoly is a completely free donation form extension designed by and for nonprofit organizations.
    <br />
    <a href="https://givoly.org/"><strong>Visit the plugin website »</strong></a>
    <br />
    <br />
    A free plugin developed by PLAID·ACT and its members, with contributions from Tom JANVIER.
  </p>
</div>

<details>
  <summary>Table of Contents</summary>
  <ol>
    <li><a href="#about-the-project">About the Project</a></li>
    <li><a href="#how-it-works">How It Works</a></li>
    <li><a href="#features">Features</a></li>
    <li><a href="#installation">Installation</a></li>
    <li><a href="#shortcodes">Shortcodes</a></li>
    <li><a href="#external-services">External Services</a></li>
    <li><a href="#license">License</a></li>
  </ol>
</details>

## About the Project

**Givoly** is a completely free donation form extension designed by and for nonprofit organizations. It supports payments through Stripe and HelloAsso, donor management, fundraising campaign tracking, and customizable donor emails.

Givoly is maintained by [PLAID·ACT](https://plaidact.org), a nonprofit organization, with contributions from Tom JANVIER. The goal is to provide a modern, transparent fundraising tool that nonprofits can use without being locked into a paid platform.

**Zero fees from Givoly:**

* **€0 subscription** to use the plugin.
* **0% Givoly commission** on collected donations.
* **No hidden fees** and no intentionally locked upsell features.

The only possible costs are the fees or contribution models of the payment providers you choose to enable, such as Stripe or HelloAsso.

<p align="right">(<a href="#readme-top">back to top</a>)</p>

## How It Works

1. Install and activate Givoly in WordPress.
2. Configure **Stripe**, **HelloAsso**, or both under **Givoly > Settings**.
3. Create a fundraising campaign if you want goals, descriptions, dates, and progress bars.
4. Add a donation form to a page with a shortcode such as `[givoly_form]`.
5. Donors choose an amount, enter their details, then pay through Stripe Checkout or HelloAsso.
6. Donations, donors, campaigns, CSV exports, and emails are managed directly in WordPress.

Givoly does not add advertising tracking, analytics pixels, or telemetry. Data stays in the nonprofit's WordPress database and is only sent to the selected payment gateway when needed for payment processing.

<p align="right">(<a href="#readme-top">back to top</a>)</p>

## Features

* Shortcode-based donation forms with multiple themes and layouts.
* **Stripe** payments through Checkout Sessions.
* **HelloAsso** payments with a dedicated button and a bundled logo asset.
* Donor management with donation history and total donated.
* Fundraising campaigns with goals, dates, descriptions, and progress bars.
* Admin dashboard with statistics and recent donations.
* Customizable emails: sender, logo, color, subject, and thank-you message.
* Annual fiscal receipt summary email workflow.
* CSV donation exports.
* No tracking added by the plugin.

### Built With

* [![WordPress][WordPress]][wordpress-url]
* [![PHP][PHP]][php-url]
* [![Stripe][Stripe]][stripe-url]
* [![HelloAsso][HelloAsso]][helloasso-url]

<p align="right">(<a href="#readme-top">back to top</a>)</p>

## Installation

1. Download the Givoly ZIP archive or copy this repository to your WordPress site's `wp-content/plugins/givoly` directory.
2. Activate **Givoly** from the WordPress **Plugins** screen.
3. Go to **Givoly > Settings**.
4. Configure Stripe, HelloAsso, or both gateways.
5. Add a form to a page or post:
   ```text
   [givoly_form]
   ```

## Shortcodes

**Donation form**

```text
[givoly_form]
```

Example with a campaign, theme, and gateway:

```text
[givoly_form campaign="emergency" theme="givoly" layout="card" gateway="helloasso"]
```

Example for quickly customizing the form wrapper with CSS variables:

```text
[givoly_form class="homepage-donation" css="--givoly-form-max-width:640px;--givoly-form-padding:2.5rem;--givoly-form-shadow:none"]
```

**Campaign total widget**

```text
[givoly_total campaign="emergency" display="bar"]
```

**Full campaign block**

```text
[givoly_campaign campaign="emergency"]
```

<p align="right">(<a href="#readme-top">back to top</a>)</p>

## External Services

Givoly relies on third-party payment services only when a site administrator configures and enables the corresponding gateway, and when a donor uses that gateway or a webhook/refund action is processed.

**Stripe** is used to create Checkout Sessions, receive Stripe webhook events, and process Stripe refunds. Donation amount, currency, donor email, donor first name, donor last name, campaign metadata, return URLs, and configured Stripe credentials are sent when needed for payment processing. Stripe terms: https://stripe.com/legal. Stripe privacy policy: https://stripe.com/privacy.

**HelloAsso** is used to authenticate with the HelloAsso API, create checkout intents, redirect donors to HelloAsso payment pages, and verify HelloAsso webhook events. Donation amount, donor email, donor first name, donor last name, campaign metadata, return URLs, organization slug, and configured HelloAsso API credentials are sent when needed for payment processing. The frontend HelloAsso button uses the bundled `assets/logo-ha.svg` file instead of loading a remote logo. HelloAsso terms: https://www.helloasso.com/cgu-utilisateur. HelloAsso privacy policy: https://www.helloasso.com/confidentialite.

<p align="right">(<a href="#readme-top">back to top</a>)</p>

## License

Distributed under the GPL v2 or later license. See [`LICENSE`](LICENSE) for more information.

<p align="right">(<a href="#readme-top">back to top</a>)</p>

[wordpress-shield]: https://img.shields.io/badge/WordPress-6.0%2B-21759B?style=for-the-badge&logo=wordpress&logoColor=white
[php-shield]: https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=for-the-badge&logo=php&logoColor=white
[stripe-shield]: https://img.shields.io/badge/Stripe-ready-635BFF?style=for-the-badge&logo=stripe&logoColor=white
[helloasso-shield]: https://img.shields.io/badge/HelloAsso-ready-00A3A5?style=for-the-badge
[license-shield]: https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg?style=for-the-badge
[wordpress-url]: https://wordpress.org/
[php-url]: https://www.php.net/
[stripe-url]: https://stripe.com/
[helloasso-url]: https://www.helloasso.com/
[license-url]: LICENSE
[WordPress]: https://img.shields.io/badge/WordPress-21759B?style=for-the-badge&logo=wordpress&logoColor=white
[PHP]: https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white
[Stripe]: https://img.shields.io/badge/Stripe-635BFF?style=for-the-badge&logo=stripe&logoColor=white
[HelloAsso]: https://img.shields.io/badge/HelloAsso-00A3A5?style=for-the-badge
