<!-- Improved compatibility of back to top link: See: https://github.com/othneildrew/Best-README-Template/pull/73 -->
<a id="readme-top"></a>

<!-- PROJECT SHIELDS -->
[![WordPress][wordpress-shield]][wordpress-url]
[![PHP][php-shield]][php-url]
[![Stripe][stripe-shield]][stripe-url]
[![HelloAsso][helloasso-shield]][helloasso-url]
[![License: GPL v2+][license-shield]][license-url]

<!-- PROJECT LOGO -->
<br />
<div align="center">
  <a href="https://givoly.org/">
    <img src="logo.png" alt="Logo Givoly" width="96" height="96">
  </a>

  <p align="center">
    L'extension WordPress de dons pour associations : Stripe + HelloAsso, zéro abonnement, zéro commission, zéro frais caché.
    <br />
    <a href="https://givoly.org/"><strong>Voir le site de l'extension »</strong></a>
    <br />
    <br />
    Une extension développée gratuitement par l'association PLAID·ACT
    <a href="https://plaidact.org">
      <img src="https://plaidact.org/wp-content/uploads/2026/04/plaidact_noir_transparent-c2ae9e.svg" alt="PLAID·ACT" width="170">
    </a>
  </p>
</div>

<!-- TABLE OF CONTENTS -->
<details>
  <summary>Table des matières / Table of Contents</summary>
  <ol>
    <li><a href="#français">Français</a></li>
    <li><a href="#à-propos-du-projet">À propos du projet</a></li>
    <li><a href="#comment-ça-marche">Comment ça marche</a></li>
    <li><a href="#fonctionnalités">Fonctionnalités</a></li>
    <li><a href="#installation">Installation</a></li>
    <li><a href="#shortcodes">Shortcodes</a></li>
    <li><a href="#english">English</a></li>
    <li><a href="#about-the-project">About the Project</a></li>
    <li><a href="#how-it-works">How It Works</a></li>
    <li><a href="#license">License</a></li>
  </ol>
</details>

## Français

### À propos du projet

**Givoly** est une extension WordPress open source pour aider les associations à collecter des dons en ligne, suivre leurs campagnes et garder une base donateurs claire, directement depuis leur administration WordPress.

Le projet est maintenu par [PLAID·ACT](https://plaidact.org), association à but non lucratif. L'objectif est simple : proposer un outil de collecte moderne, accessible et transparent, sans enfermer les associations dans une solution payante.

**Zéro, zéro frais côté Givoly :**

* **0 € d'abonnement** pour utiliser l'extension.
* **0 % de commission Givoly** sur les dons collectés.
* **0 frais caché** et aucun upsell volontairement bloquant.

Les seuls frais éventuels sont ceux des prestataires de paiement que vous choisissez d'activer, par exemple Stripe, ou les contributions/frais gérés selon le modèle HelloAsso.

<p align="right">(<a href="#readme-top">retour en haut</a>)</p>

### Comment ça marche

1. Installez et activez Givoly dans WordPress.
2. Configurez **Stripe**, **HelloAsso**, ou les deux depuis **Givoly > Réglages**.
3. Créez une campagne de collecte si vous souhaitez suivre un objectif, une description et une jauge de progression.
4. Ajoutez un formulaire de don sur une page avec un shortcode comme `[givoly_form]`.
5. Les donateurs choisissent un montant, renseignent leurs informations, puis paient via Stripe Checkout ou HelloAsso.
6. Les dons, donateurs, campagnes, exports CSV et emails de remerciement se pilotent depuis l'administration WordPress.

Givoly ne rajoute pas de tracking publicitaire, de pixel analytics ou de télémétrie. Les données restent dans le WordPress de l'association et ne sont transmises aux passerelles de paiement que lorsque c'est nécessaire au paiement.

<p align="right">(<a href="#readme-top">retour en haut</a>)</p>

### Fonctionnalités

* Formulaires de dons par shortcode.
* Paiements **Stripe** via Checkout Session.
* Paiements **HelloAsso** avec bouton dédié.
* Gestion des donateurs, historique et total donné.
* Campagnes avec objectifs, dates, descriptions et jauges de progression.
* Tableau de bord administrateur avec statistiques et derniers dons.
* Emails personnalisables : expéditeur, logo, couleur, sujet et message de remerciement.
* Envoi annuel d'emails récapitulatifs pour les reçus fiscaux.
* Export CSV des dons.
* Aucun tracking ajouté par l'extension.

### Built With

* [![WordPress][WordPress]][wordpress-url]
* [![PHP][PHP]][php-url]
* [![Stripe][Stripe]][stripe-url]
* [![HelloAsso][HelloAsso]][helloasso-url]

<p align="right">(<a href="#readme-top">retour en haut</a>)</p>

### Installation

1. Téléchargez l'archive ZIP de Givoly ou copiez ce dépôt dans le dossier `wp-content/plugins/givoly` de votre site WordPress.
2. Activez **Givoly** depuis l'écran **Extensions** de WordPress.
3. Allez dans **Givoly > Réglages**.
4. Configurez Stripe, HelloAsso, ou les deux passerelles.
5. Ajoutez un formulaire sur une page ou un article :
   ```text
   [givoly_form]
   ```

### Shortcodes

**Formulaire de don**

```text
[givoly_form]
```

Exemple avec campagne, thème et passerelle :

```text
[givoly_form campaign="urgence" theme="givoly" layout="card" gateway="helloasso"]
```

**Jauge de campagne**

```text
[givoly_total campaign="urgence" display="bar"]
```

**Campagne complète**

```text
[givoly_campaign campaign="urgence"]
```

<p align="right">(<a href="#readme-top">retour en haut</a>)</p>

---

## English

### About the Project

**Givoly** is an open-source WordPress donation plugin for nonprofits. It helps organizations collect online donations, manage donors, track fundraising campaigns, export data, and send thank-you or annual tax-summary emails from WordPress.

Givoly is maintained by [PLAID·ACT](https://plaidact.org), a nonprofit organization. The goal is to provide a modern, transparent fundraising tool that nonprofits can use without being locked into a paid platform.

**Zero, zero fees from Givoly:**

* **€0 subscription** to use the plugin.
* **0% Givoly commission** on collected donations.
* **No hidden fees** and no intentionally locked upsell features.

The only possible costs are the fees or contribution models of the payment providers you choose to enable, such as Stripe or HelloAsso.

<p align="right">(<a href="#readme-top">back to top</a>)</p>

### How It Works

1. Install and activate Givoly in WordPress.
2. Configure **Stripe**, **HelloAsso**, or both under **Givoly > Settings**.
3. Create a fundraising campaign if you want goals, descriptions, dates, and progress bars.
4. Add a donation form to a page with a shortcode such as `[givoly_form]`.
5. Donors choose an amount, enter their details, then pay through Stripe Checkout or HelloAsso.
6. Donations, donors, campaigns, CSV exports, and emails are managed directly in WordPress.

Givoly does not add advertising tracking, analytics pixels, or telemetry. Data stays in the nonprofit's WordPress database and is only sent to the selected payment gateway when needed for payment processing.

### Features

* Shortcode-based donation forms.
* **Stripe** payments through Checkout Session.
* **HelloAsso** payments with a dedicated button.
* Donor management with history and total donated.
* Fundraising campaigns with goals, dates, descriptions, and progress bars.
* Admin dashboard with statistics and recent donations.
* Customizable emails: sender, logo, color, subject, and thank-you message.
* Annual tax-summary email workflow.
* CSV donation exports.
* No tracking added by the plugin.

### Usage

```text
[givoly_form]
[givoly_total campaign="emergency" display="bar"]
[givoly_campaign campaign="emergency"]
```

For more information, visit [givoly.org](https://givoly.org/).

<p align="right">(<a href="#readme-top">back to top</a>)</p>

## License

Distributed under the GPL v2 or later license. See [`LICENSE`](LICENSE) for more information.

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- MARKDOWN LINKS & IMAGES -->
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
