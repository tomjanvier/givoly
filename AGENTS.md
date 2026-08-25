# Consignes de développement — Givoly

## 1. Lecture obligatoire

Tout agent ou contributeur automatisé doit lire entièrement :

- `AGENTS.md` à la racine ;
- tout autre `AGENTS.md` présent dans les sous-répertoires concernés ;
- les fichiers de documentation directement liés à la tâche (`README.md`, `readme.txt`).

Cette lecture doit être effectuée avant toute analyse, modification, test, commit ou pull request.

## 2. Présentation du projet

Givoly est une extension WordPress gratuite, principalement destinée aux associations françaises.
Elle est développée et maintenue par PLAID·ACT. Elle n’ajoute ni abonnement, ni commission,
ni télémétrie, ni traçage des donateurs.

Fonctionnalités réellement présentes dans le dépôt :

- **Formulaires de dons** : shortcode `[givoly_form]`, cinq thèmes visuels (`classic`, `ocean`,
  `sunset`, `minimal`, `givoly`), trois dispositions (`card`, `inline`, `flat`), montants
  prédéfinis ou libres, multi-devises (EUR, USD, GBP, CHF, MAD) ;
- **Blocs Gutenberg natifs** : `givoly/form`, `givoly/campaign`, `givoly/total`, rendus côté
  serveur à partir des mêmes widgets que les shortcodes, sans étape de build ;
- **Stripe** : Checkout Session (don unique et abonnement mensuel), remboursements,
  portail client Stripe pour les dons récurrents ;
- **HelloAsso** : création d’intents de paiement, mode bac à sable/production,
  redirection optionnelle vers un lien « autres moyens de paiement » ;
- **Dons récurrents** : import des factures Stripe payées, gestion des abonnements
  (annulation à l’échéance), références de paiement récurrent sur les fiches donateurs ;
- **Webhooks** : endpoints REST signés pour Stripe et HelloAsso ;
- **Synchronisations WP-Cron** : rattrapage des paiements manqués côté Stripe (factures)
  et HelloAsso (paiements), avec chevauchement de fenêtre et idempotence ;
- **Espace donateur sans mot de passe** : `[givoly_donor_space]`, lien magique par email,
  historique des dons, téléchargement PDF, portail Stripe ;
- **Fiches donateurs** : numéro de donateur, coordonnées, édition administrateur ;
- **Reçus fiscaux PDF** : résumés annuels individuels ou par lot, gabarits personnalisables ;
- **File d’emails persistante** : envoi asynchrone via WP-Cron, relance en cas d’échec ;
- **Emails personnalisables** : remerciement, notification administrateur, reçu fiscal
  (sujet, corps, expéditeur, couleur, logo) ;
- **Dons manuels** : virement, chèque, espèces, avec date au choix et reçu optionnel ;
- **Campagnes** : objectifs, dates, descriptions, jauges de progression,
  shortcodes `[givoly_campaign]` et `[givoly_total]` ;
- **Tableau de bord** : page Givoly, widget tableau de bord WordPress, statistiques ;
- **Exports CSV** protégés contre l’injection de formules ;
- **Outils RGPD** : exporteur et effaceur de données personnelles intégrés aux écrans
  natifs de confidentialité WordPress ;
- **Migration Givasso** non destructive (voir section 10) ;
- **Traductions** : anglais en source, français empaqueté (`languages/givoly-fr_FR.po/.mo`),
  modèle POT pour GlotPress.

Champs associatifs français pris en charge : SIRET, RNA, agrément/fiscalité, adresse.

## 3. Stack technique réelle

| Élément | Détail vérifié dans le dépôt |
| --- | --- |
| PHP | 8.1 minimum (`Requires PHP: 8.1`). Typage strict des propriétés, arguments nommés, `match`, fonctions fléchées |
| WordPress | 6.0 minimum (`Requires at least: 6.0`), testé jusqu’à 7.0 (`Tested up to: 7.0`) |
| Base de données | MySQL/MariaDB via `$wpdb`, requêtes préparées systématiques, schéma créé par `dbDelta()` |
| JavaScript | Vanilla uniquement (`assets/js/`), aucune dépendance npm, aucun bundler |
| CSS | Feuilles natives (`assets/css/`), variables CSS custom pour les thèmes, aucune dépendance |
| AJAX | API admin-ajax avec nonces dédiés (`givoly_submit_donation`, `givoly_donor_space`) |
| REST API | Deux routes POST sous `givoly/v1` (webhooks Stripe et HelloAsso) |
| WP-Cron | File d’emails + deux synchronisations horaires (Stripe, HelloAsso) |
| Stripe | API HTTP v1 appelée avec `wp_remote_post()` / `wp_remote_get()`, signature webhook HMAC-SHA256 vérifiée manuellement |
| HelloAsso | API HTTP avec OAuth client credentials, modes bac à sable/production, vérification de signature ou d’IP |
| gettext | Fichiers `languages/givoly.pot`, `givoly-fr_FR.po`, `givoly-fr_FR.mo` maintenus à la main ; régénération possible avec les outils GNU gettext standards (`xgettext`, `msgmerge`, `msgcat`, `msgfmt`) |
| Tests / CI | Aucune suite PHPUnit ni pipeline CI présent dans le dépôt ; validation par analyse statique locale (section 12) |

Absences volontaires (ne pas en ajouter sans justification) : Composer, npm/bundler,
framework CSS/JS, SDK Stripe officiel.

## 4. Qualité du code

Le code doit être propre, lisible, maintenable, structuré, cohérent avec l’architecture
existante et raisonnablement optimisé.

Avant toute modification :

- inspecter les fichiers concernés ;
- rechercher les implémentations similaires dans le dépôt ;
- comprendre les conventions existantes (nommage, formatage, répartition des responsabilités) ;
- identifier les dépendances et effets secondaires ;
- limiter les changements au périmètre demandé ;
- préserver les données et fonctionnalités existantes.

Éviter :

- la duplication de logique ;
- les fonctions trop longues ;
- les abstractions inutiles ;
- les requêtes N+1 (utiliser par exemple `CampaignRepository::get_stats_batch()`
  pour les listes) ;
- les dépendances supplémentaires non justifiées ;
- les refactorisations hors périmètre ;
- les correctifs temporaires non documentés ;
- le code difficile à relire ou visiblement assemblé sans compréhension du projet.

Organisation attendue du nouveau code :

- une classe par responsabilité, dans le namespace adapté (section « Architecture » ci-dessous) ;
- les lectures/écritures SQL passent par les classes dédiées (`Repository`, processeurs),
  jamais dispersées sans raison ;
- les modules se déclarent dans `Plugin::boot()` — ajouter un module = une ligne.

### Architecture PHP et namespaces

Autoloader PSR-4 léger déclaré dans `givoly.php` : toute classe `Givoly\…` correspond au
fichier `includes/<chemin>/<Classe>.php`. Aucun autre mécanisme de chargement.

| Namespace | Rôle |
| --- | --- |
| `Givoly\Core` | Amorçage (`Plugin`), installation/migrations (`Installer`), compatibilité Givasso (`LegacyMigration`), assets, helpers (`Format`), RGPD (`Privacy`) |
| `Givoly\Admin` | Menu, actions `admin-post`, réglages (`Settings` est la source unique des options), statistiques, pages d’administration (`Admin/Pages/*`) |
| `Givoly\Ajax` | Contrôleur AJAX/REST (`AjaxHandler`), enregistrement partagé des paiements (`PaymentProcessor`) |
| `Givoly\Form` | Shortcodes, widgets campagne/total, configuration formulaire (`FormConfig`), blocs Gutenberg (`BlockRegistrar`) |
| `Givoly\Gateway` | Clients HTTP Stripe et HelloAsso, sans logique WordPress métier |
| `Givoly\Integration` | Synchronisations planifiées Stripe et HelloAsso |
| `Givoly\Mail` | File d’emails, rendu des emails, PDF et service de reçus fiscaux |
| `Givoly\Donor` | Espace donateur, référence donateur |
| `Givoly\Repository` | Accès aux données campagnes (point d’entrée unique de `givoly_campaigns`) |
| `Givoly\Security` | Limitation de débit (`RateLimiter`) |
| `Givoly\Domain\Entities` | Entités métier (`Campaign`) |

### Organisation des dossiers

```
givoly.php            Bootstrap : constantes, alias GIVASSO_*, autoloader, hooks d’activation
uninstall.php         Suppression définitive (tables, options, transients, cron)
includes/             Code source PHP orienté objet (voir namespaces ci-dessus)
templates/            Gabarits PHP : formulaires (card/inline/partials), campagne, onglets réglages
assets/css/           Feuilles de style frontend, espace donateur, administration
assets/js/            Scripts vanilla frontend, espace donateur, administration, blocs
languages/            givoly.pot, givoly-fr_FR.po, givoly-fr_FR.mo
logo.png              Logo empaqueté (aucun asset distant)
readme.txt            Fiche WordPress.org
```

Les templates sont surchargeables par le thème actif
(`{theme}/givoly/campaign/campaign.php` par exemple).

## 5. Commentaires

Tous les commentaires ajoutés dans le code doivent être rédigés en français.

Les commentaires doivent expliquer :

- les décisions métier ;
- les contraintes WordPress ;
- les raisons d’un traitement particulier ;
- les mécanismes de sécurité ;
- les compatibilités historiques ;
- les risques évités ;
- les raisons d’une optimisation.

Ne commente pas chaque ligne évidente. Les commentaires doivent améliorer la
compréhension sans alourdir le code. Les annotations `phpcs:ignore` restent en anglais
(convention de l’outil) et doivent toujours être accompagnées, si nécessaire, d’un
commentaire français expliquant la raison.

## 6. Neutralité du dépôt

Ne jamais ajouter dans le code, les commentaires, les fichiers générés, la documentation,
les commits ou les pull requests :

- le nom d’un assistant conversationnel ;
- le nom d’un fournisseur de modèles ;
- le nom d’un outil de génération de code ;
- le nom ou la version d’un modèle ;
- une mention indiquant que le code a été généré automatiquement ;
- une signature promotionnelle ou personnelle sans rapport avec le projet.

Les commits et pull requests décrivent uniquement les changements techniques,
fonctionnels et documentaires, en anglais court à l’impératif, comme l’historique existant
(« Add donor space and payment synchronization features »).

## 7. Internationalisation

Respecter le système gettext existant :

- text domain : `givoly` ;
- chaînes visibles source en anglais naturel ;
- traductions françaises dans `languages/givoly-fr_FR.po` et `.mo` empaqueté ;
- utilisation de `__()`, `_e()`, `esc_html__()`, `esc_html_e()`, `esc_attr__()`,
  `esc_attr_e()` selon le contexte ;
- échappement des variables avant affichage (`esc_html()`, `esc_attr()`, `esc_url()`) ;
- conservation des fichiers POT, PO et MO — le fichier `.mo` compilé fait partie des
  fichiers nécessaires au déploiement ;
- compatibilité GlotPress : ne pas reformuler massivement les chaînes existantes,
  conserver les commentaires `translators:` ;
- nommage correct des fichiers (`givoly-fr_FR.po`) ;
- encodage UTF-8 partout ;
- les chaînes JavaScript traduisibles utilisent `wp.i18n.__` avec `wp_set_script_translations`.

Ne pas supprimer les traductions existantes sans raison.

Après ajout ou modification de chaînes : régénérer le POT (sources PHP puis JS),
fusionner dans le PO, compléter les traductions françaises manquantes, recompiler le MO
et valider avec `msgfmt --check`.

## 8. Sécurité WordPress

Respecter les pratiques natives de sécurité WordPress.

Pour les données entrantes, dans cet ordre :

1. `wp_unslash()` ;
2. validation du type attendu ;
3. nettoyage avec la fonction adaptée ;
4. échappement au moment de l’affichage.

Fonctions de référence utilisées dans le dépôt : `sanitize_text_field()`,
`sanitize_textarea_field()`, `sanitize_email()`, `sanitize_key()`, `sanitize_title()`,
`absint()`, `wp_kses_post()`, `esc_html()`, `esc_attr()`, `esc_url()`,
`wp_json_encode()`, `check_admin_referer()`, `check_ajax_referer()`,
`wp_verify_nonce()`, `current_user_can()`, `wp_validate_redirect()`, `$wpdb->prepare()`.

Vérifier systématiquement :

- les permissions (`manage_options` pour l’administration) ;
- les nonces (actions admin-post nominatives type `givoly_refund_donation_<id>` ;
  téléchargement de reçu lié à la session donateur) ;
- les redirections (`wp_safe_redirect()` + `wp_validate_redirect()`) ;
- les webhooks (signature vérifiée avant tout traitement, rejet des rejouements :
  Stripe refuse les événements de plus de cinq minutes) ;
- les actions AJAX (nonces dédiés + `RateLimiter::is_allowed()` sur le checkout
  et la demande d’accès donateur) ;
- les endpoints REST (`permission_callback` explicite, ici `__return_true` car la
  sécurité repose exclusivement sur la vérification de signature — le documenter si
  un nouvel endpoint est ajouté) ;
- les exports CSV (préfixage `'` des valeurs commençant par `=+-@`) ;
- les données personnelles (outils RGPD natifs, anonymisation irréversible) ;
- les secrets API (jamais en clair dans les logs : passer par
  `Format::redact_secrets()` avant tout `error_log()`) ;
- l’idempotence des paiements (clé unique `(gateway, gateway_transaction_id)` +
  contrôle préalable par `gateway_refund_ref` + capture de l’erreur doublon).

Ne jamais stocker ou exécuter du PHP, JavaScript ou CSS arbitraire fourni par
l’utilisateur. Pour la personnalisation CSS du formulaire, utiliser l’éditeur natif
« CSS additionnel » de WordPress — le plugin ne stocke aucun CSS.

## 9. Paiements et intégrations externes

Les intégrations Stripe et HelloAsso doivent :

- utiliser les API HTTP natives de WordPress (`wp_remote_post()`, `wp_remote_get()`)
  sans SDK ni dépendance Composer ;
- vérifier le code de réponse HTTP et décoder le corps JSON avec gestion d’erreur ;
- éviter l’exposition des clés secrètes (jamais dans les URLs, jamais dans les logs) ;
- gérer les erreurs proprement (exceptions `RuntimeException` attrapées aux frontières :
  AJAX renvoie une erreur JSON générique, webhooks renvoient 400/500) ;
- éviter les doublons grâce à l’idempotence (section 8) ;
- conserver les identifiants de transaction (`gateway_transaction_id`,
  `gateway_refund_ref`) et les identifiants Stripe client/abonnement sur la fiche donateur ;
- documenter les données transmises (montant, devise, coordonnées donateur, métadonnées
  campagne, URLs de retour) dans readme.txt, section External services ;
- ne contacter les services externes que lorsqu’ils sont activés et nécessaires
  (aucun appel au chargement d’une page publique).

Les webhooks doivent être vérifiés avant tout traitement métier :

- Stripe : HMAC-SHA256 sur `timestamp.payload` avec le secret webhook, fenêtre anti-rejeu ;
- HelloAsso : signature dédiée si configurée, sinon liste blanche d’IP selon le mode.

Tout événement entrant doit rester traitable plusieurs fois sans effet de bord
(la même notification peut arriver deux fois).

## 10. Conservation des données et compatibilité Givasso

Les données historiques doivent être conservées. Givoly succède à Givasso : la
compatibilité doit rester non destructive.

Pour toute migration :

- ne jamais supprimer les données existantes ;
- ne jamais vider une valeur existante à cause d’un champ vide ;
- préserver les IDs ;
- préserver les dates ;
- préserver les statuts ;
- préserver les emails ;
- préserver les références de paiement ;
- préserver les identifiants Stripe et HelloAsso ;
- conserver les anciennes options lorsque cela est nécessaire
  (fallback `givasso_<option>` dans `Settings::get_compat_option()`) ;
- conserver les tables historiques Givasso (`{prefix}givasso_*`) comme copie de
  sécurité — elles ne sont supprimées que lors d’une désinstallation explicite ;
- rendre les migrations idempotentes (vérification `INFORMATION_SCHEMA` avant chaque
  `ALTER`, exécution cumulative depuis `Installer::maybe_upgrade()`) ;
- versionner les migrations (option `givoly_db_version`, actuellement `2.1`) ;
- vérifier les erreurs avant de marquer une migration comme terminée.

Mécanismes de compatibilité existants — à préserver :

- constantes aliasées `GIVASSO_VERSION`, `GIVASSO_PLUGIN_FILE/DIR/URL/BASENAME` ;
- shortcodes historiques `givasso`, `givasso_form`, `givasso_total`, `givasso_campaign` ;
- `LegacyMigration::run()` copie les tables/options Givasso vers `givoly_*` une seule fois
  (option `givoly_legacy_migration_version`), sans toucher aux originaux ;
- colonnes historiques conservées pour rétrocompatibilité : `donor_message` porte le slug
  de campagne sur les anciens dons, le message libre vit dans `donor_notes`.

Les constantes, options, tables et identifiants historiques ne doivent pas être
renommés ou supprimés sans migration explicite.

## 11. WordPress.org et publication SVN

Le plugin doit respecter les règles de publication WordPress.org. Avant toute release,
vérifier la cohérence :

- champ `Version` de `givoly.php` ;
- constante `GIVOLY_VERSION` de `givoly.php` ;
- `Stable tag` de `readme.txt` ;
- ces trois valeurs doivent être identiques, et un tag SVN `/tags/<version>/` doit
  exister pour toute version stable — `Stable tag` ne doit jamais pointer vers un tag
  inexistant ;
- contributeurs correspondant à de vrais comptes WordPress.org (`plaidact`, `tomjanvier`) ;
- description courte de moins de 150 caractères ;
- sections `== External services ==` à jour dès qu’une donnée transite vers Stripe
  ou HelloAsso, section `== Privacy ==` exacte ;
- text domain `givoly` et fichiers de traduction présents ;
- absence de CSS, JavaScript ou PHP arbitraire ;
- absence de télémétrie cachée, de pixels, de cookies de suivi ou d’assets distants
  non liés au paiement (le logo HelloAsso est empaqueté localement) ;
- structure standard du `readme.txt` (Description, Installation, FAQ, Changelog).

Fichiers générés ou compilés nécessaires dans l’arbre publié :

- `languages/givoly-fr_FR.mo` (compilé depuis le PO, requis pour le français empaqueté) ;
- tous les autres fichiers sont sources directs (aucune étape de build).

## 12. Commandes de validation

Avant de déclarer une tâche terminée, exécuter depuis la racine du dépôt, si les outils
sont installés sur la machine :

```bash
git diff --check

find . -name '*.php' -print0 | xargs -0 php -l

find assets -name '*.js' -print0 | xargs -0 node --check

msgfmt --check -o /dev/null languages/givoly-fr_FR.po
```

Compléments utiles après modification des chaînes traduisibles :

```bash
msgmerge --update languages/givoly-fr_FR.po languages/givoly.pot
msgfmt --output-file=languages/givoly-fr_FR.mo languages/givoly-fr_FR.po
```

Toute commande doit passer sans erreur avant un commit. En cas d’outil absent
(par exemple Node.js), le signaler explicitement dans le résumé plutôt que de
passer la vérification sous silence.
