=== Givoly ===
Contributors: plaidact
Tags: donation, nonprofit, stripe, helloasso, fundraising
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Créez des formulaires de dons modernes pour associations, avec paiements Stripe et HelloAsso, gestion des donateurs, campagnes, exports et emails de reçus fiscaux annuels.

== Description ==

**Givoly** est un plugin WordPress pensé pour les associations qui souhaitent collecter des dons en ligne simplement, suivre leurs campagnes et garder une base donateurs propre.

Le plugin est maintenu par **PLAID·ACT**, association à but non lucratif : https://plaidact.org

= Fonctionnalités principales =

* **Formulaires de dons par shortcode** avec 5 thèmes (Givoly, Classic, Ocean, Sunset, Minimal) et 3 mises en page (Card, Inline, Flat).
* **Paiements Stripe** via Checkout Session.
* **Paiements HelloAsso** avec bouton dédié et logo officiel HelloAsso.
* **Gestion des donateurs** avec historique, total donné et dernier don.
* **Campagnes de collecte** avec objectifs, dates, descriptions et jauges de progression.
* **Tableau de bord administrateur** avec statistiques et derniers dons.
* **Emails personnalisables** : expéditeur, logo, couleur, sujet et message de remerciement.
* **Envoi annuel des reçus fiscaux** depuis la page Donateurs, par année fiscale.
* **Export CSV des dons** avec protection contre l'injection de formules.
* **Aucun tracking** : pas de pixel publicitaire, pas d'analytics ajouté par le plugin, pas de télémétrie.

= Shortcodes =

**Formulaire de don**

`[givoly_form]`

Attributs disponibles :

| Attribut | Valeur par défaut | Valeurs possibles |
|---|---|---|
| `theme` | `givoly` | `givoly`, `classic`, `ocean`, `sunset`, `minimal` |
| `layout` | `card` | `card`, `inline`, `flat` |
| `amounts` | `10,25,50,100` | montants entiers séparés par des virgules |
| `currency` | `EUR` | `EUR`, `USD`, `GBP`, `MAD`, `CHF` |
| `campaign` | vide | slug d'une campagne |
| `title` | `Faire un don` | texte libre |
| `button_text` | `Donner maintenant` | texte libre |
| `show_title` | `yes` | `yes`, `no` |
| `gateway` | réglage admin | `stripe`, `helloasso` |
| `class` | vide | classe CSS personnalisée à ajouter au bloc du formulaire |
| `css` | vide | variables CSS `--givoly-*` séparées par des points-virgules |

Exemple de personnalisation rapide du bloc :

`[givoly_form class="don-accueil" css="--givoly-form-max-width:640px;--givoly-form-padding:2.5rem;--givoly-form-shadow:none"]`

**Jauge de campagne**

`[givoly_total campaign="ma-campagne" display="bar"]`

**Campagne complète (jauge + formulaire)**

`[givoly_campaign campaign="ma-campagne"]`

Le shortcode de campagne accepte notamment `show_title="no"`, `show_form`, `show_description`, `layout` et `theme`.

= Reçus fiscaux annuels =

Depuis **Givoly > Donateurs**, un encart permet de choisir une année fiscale et d'envoyer un email récapitulatif à chaque donateur ayant au moins un don complété sur cette période.

Avant l'envoi, renseignez les informations de l'association dans **Givoly > Réglages > Association** : nom, adresse, email, SIRET/RNA et agrément ou rescrit fiscal. Le message envoyé rappelle le montant total donné, le nombre de dons et les informations fiscales configurées.

= Configuration rapide =

1. Activez le plugin.
2. Allez dans **Givoly > Réglages**.
3. Configurez au moins une passerelle : Stripe, HelloAsso, ou les deux.
4. Complétez les informations de l'association.
5. Personnalisez les emails si besoin.
6. Ajoutez `[givoly_form]` dans une page ou un article.

== Installation ==

1. Envoyez le dossier `givoly` dans `/wp-content/plugins/` ou installez l'archive ZIP depuis l'administration WordPress.
2. Activez **Givoly** depuis l'écran Extensions.
3. Configurez vos passerelles dans **Givoly > Réglages**.
4. Publiez un formulaire avec `[givoly_form]`.

== Frequently Asked Questions ==

= Qui développe Givoly ? =

Givoly est développé et maintenu par **PLAID·ACT**, association à but non lucratif : https://plaidact.org

= Peut-on utiliser uniquement HelloAsso ou uniquement Stripe ? =

Oui. Activez la passerelle souhaitée dans **Givoly > Réglages > Général**. Si Stripe et HelloAsso sont actifs, le formulaire affiche les deux options de paiement.

= Où modifier le logo des emails ? =

Dans **Givoly > Réglages > Email > URL du logo**. Si aucune URL n'est renseignée, le nom de l'association est affiché.

= Le plugin génère-t-il un PDF de reçu fiscal ? =

Non. L'outil d'envoi annuel envoie un email récapitulatif fiscal aux donateurs. Vous pouvez l'utiliser pour préparer votre campagne de reçus fiscaux et joindre un reçu officiel si votre procédure l'exige.

= Y a-t-il des fonctionnalités payantes ou des upsells ? =

Non. Ce paquet est fourni comme plugin gratuit, sans écran d'upsell ni fonctionnalité volontairement verrouillée.

== Privacy (GDPR) ==

Givoly collecte les données personnelles nécessaires au traitement et au suivi des dons : nom, email et champs de profil optionnels. Ces données sont stockées dans la base WordPress de l'association et transmises à la passerelle de paiement sélectionnée uniquement lorsque cela est nécessaire au paiement.

Givoly n'ajoute pas de scripts d'analytics, pixels publicitaires, cookies de suivi ou télémétrie.

== External services ==

Givoly contacte des services externes uniquement lorsqu'une passerelle est configurée et utilisée.

**Stripe**

* Endpoint : `https://api.stripe.com/v1`
* Utilisation : création de sessions Checkout, webhooks Stripe et remboursements Stripe.
* Données envoyées : montant, devise, email, prénom, nom, métadonnées de campagne, URLs de retour et clé secrète Stripe configurée.
* Conditions : https://stripe.com/legal
* Confidentialité : https://stripe.com/privacy

**HelloAsso**

* Endpoints : `https://api.helloasso.com`, `https://api.helloasso-sandbox.com`, `https://www.helloasso.com`, `https://www.helloasso-sandbox.com`
* Logo affiché dans le bouton : `https://api.helloasso.com/v5/img/logo-ha.svg`
* Utilisation : authentification HelloAsso, création d'intentions de paiement, redirection vers les pages HelloAsso et vérification des webhooks.
* Données envoyées : montant, email, prénom, nom, métadonnées de campagne, URLs de retour, slug d'organisation et identifiants API HelloAsso configurés.
* Conditions : https://www.helloasso.com/cgu-utilisateur
* Confidentialité : https://www.helloasso.com/confidentialite

== Changelog ==

= 1.0.0 =
* Renommage et rebranding du plugin en Givoly.
* Mise à jour des informations auteur pour PLAID·ACT.
* Ajout du logo officiel HelloAsso dans le bouton de paiement HelloAsso.
* Ajout de l'envoi annuel des reçus fiscaux par email depuis la page Donateurs.
* Nettoyage de la documentation et des éléments résiduels non utilisés.
* Conservation des formulaires de dons, Stripe, HelloAsso, donateurs, campagnes, réglages, emails et exports CSV.
