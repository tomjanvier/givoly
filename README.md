<p align="center">
  <a href="https://givoly.org/">
    <img src="logo.png" alt="Givoly" width="104" height="104">
  </a>
</p>

<h1 align="center">Givoly</h1>

<p align="center"><strong>French-first donation forms for WordPress nonprofits.</strong></p>

<p align="center">
  Stripe · HelloAsso · Donors · Campaigns · Receipt emails
  <br>
  <a href="#english">English</a> · <a href="#français">Français</a> · <a href="https://givoly.org/">givoly.org</a> · <a href="https://plaidact.org/">PLAID·ACT</a>
</p>

> **Givoly is primarily designed for French nonprofits.** It includes French nonprofit fields such as SIRET and RNA, while remaining suitable for organizations using Stripe or HelloAsso anywhere.

---

## English

Givoly is a free, nonprofit WordPress donation plugin maintained by [PLAID·ACT](https://plaidact.org). It helps organizations collect donations without a plugin subscription or plugin commission.

| What you need | What Givoly provides |
| --- | --- |
| Receive donations | Stripe Checkout and HelloAsso payment flows |
| Build trust | Secure webhooks, no plugin tracking, no remote marketing assets |
| Know your supporters | Donor records, donation history, recurring-payment references |
| Run a campaign | Goals, dates, descriptions, progress bars, and public forms |
| Stay organized | CSV exports, manual donations, queued emails, receipt summaries |

### Start in four steps

1. Activate Givoly in WordPress.
2. Configure **Stripe**, **HelloAsso**, or both under **Givoly > Settings**.
3. Complete your organization and email information.
4. Add a form to a page:

```text
[givoly_form]
```

### Shortcodes

| Shortcode | Purpose |
| --- | --- |
| `[givoly_form]` | Displays a donation form. Supports `theme`, `layout`, `amounts`, `currency`, `campaign`, `gateway`, and more. |
| `[givoly_donor_space]` | Passwordless donor area: history, eligible PDF downloads, and Stripe subscription portal. |
| `[givoly_campaign campaign="emergency"]` | Campaign description, progress, and donation form. |
| `[givoly_total campaign="emergency" display="bar"]` | Compact campaign total or progress bar. |

### Privacy and external services

Givoly stores donation data in the site's WordPress database. It does not add telemetry, analytics pixels, tracking cookies, referral parameters, or unrelated remote assets.

Payment data is sent only to the gateways enabled by the administrator and used for the donation:

* **Stripe** — [terms](https://stripe.com/legal) · [privacy](https://stripe.com/privacy)
* **HelloAsso** — [terms](https://www.helloasso.com/cgu-utilisateur) · [privacy](https://www.helloasso.com/confidentialite)

For form-specific styling, use WordPress's native **Additional CSS** editor. Givoly does not store or execute arbitrary custom CSS.

---

## Français

Givoly est une extension WordPress gratuite et associative, maintenue par [PLAID·ACT](https://plaidact.org). Elle permet aux associations de recevoir des dons en ligne, sans abonnement imposé ni commission ajoutée par l’extension.

| Votre besoin | Ce que fait Givoly |
| --- | --- |
| Recevoir des dons | Paiement via Stripe Checkout et HelloAsso |
| Rassurer les donateurs | Webhooks sécurisés, aucun suivi ajouté par l’extension |
| Suivre les soutiens | Fiches donateurs, historique, références de dons récurrents |
| Lancer une collecte | Objectifs, dates, descriptions, jauges et formulaires publics |
| Gagner du temps | Exports CSV, dons manuels, emails en file, reçus et PDF |

### Démarrage rapide

1. Activez Givoly dans WordPress.
2. Configurez **Stripe**, **HelloAsso** ou les deux dans **Givoly > Réglages**.
3. Renseignez les informations de votre association et vos emails.
4. Ajoutez le shortcode suivant dans une page :

```text
[givoly_form]
```

### Espaces et formulaires

* `[givoly_form]` affiche un formulaire de don personnalisable.
* `[givoly_donor_space]` crée un espace donateur sans mot de passe : historique, téléchargements PDF et gestion Stripe.
* `[givoly_campaign campaign="urgence"]` affiche une collecte complète.
* `[givoly_total campaign="urgence" display="bar"]` affiche un total ou une jauge compacte.

### Données et confidentialité

Les données de dons sont enregistrées dans la base WordPress de l’association. Givoly n’ajoute ni pixels publicitaires, ni cookies de suivi, ni télémétrie, ni paramètres de tracking dans les liens.

Stripe et HelloAsso ne sont contactés que lorsqu’ils sont activés et utilisés pour un paiement, un webhook, un remboursement ou une synchronisation.

Pour modifier le style d’un formulaire, utilisez le module **CSS additionnel** natif de WordPress. Givoly ne stocke ni n’exécute de CSS arbitraire.

---

## License · Licence

Givoly is distributed under the [GPL-2.0-or-later](LICENSE) license.
