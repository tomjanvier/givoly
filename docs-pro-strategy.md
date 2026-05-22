# Stratégie Free + Pro sur la même base de code (Givasso)

## Objectif
Permettre une version gratuite (WordPress.org) et une version payante (Givasso Pro) sans dupliquer tout le code.

## Modèle recommandé

### 1) Deux plugins séparés
- `givasso` (free): distribué sur WordPress.org.
- `givasso-pro` (payant): distribué depuis votre site.

Le plugin Pro **dépend** du plugin free et l’étend.

### 2) Tronc commun dans le plugin free
Garder dans le free:
- Entités/domain (`includes/Domain/*`)
- Repository et stockage (`includes/Repository/*`)
- Formulaire de don, campagnes, dashboards de base
- Passerelles gratuites (Stripe basic, HelloAsso)

### 3) Fonctionnalités Pro dans un plugin add-on
Exemples:
- Reçus fiscaux CERFA PDF
- Dons récurrents Stripe Subscriptions
- Exports CSV/FEC

Le plugin Pro ajoute des hooks/filters, pages admin et services supplémentaires.

## Intégration technique dans ce repo

### A. Introduire une couche de "Feature Flags"
Créer un service central, par exemple `Givasso\Core\Features`, avec:
- `is_pro_active()`
- `has_feature('recurring')`, `has_feature('cerfa')`, etc.

Le free ne doit jamais inclure la logique métier Pro; seulement des points d’extension.

### B. Ajouter des hooks d’extension dans le free
Exemples:
- `apply_filters('givasso_available_frequencies', ['once'])`
- `do_action('givasso_after_donation_saved', $donation_id)`
- `apply_filters('givasso_export_providers', [])`

Le Pro se branche sur ces hooks pour injecter ses fonctionnalités.

### C. Gating UI propre
Dans le free:
- Afficher les options Pro “grisées” + CTA
- Ne jamais casser le flux free
- Vérifier les permissions/capabilities même côté Pro

## Gestion licence (plugin Pro)

### 1) Vérification côté serveur
- Le plugin Pro contacte une API de licence (clé + domaine).
- Stocker un état local signé (cache) pour limiter les appels.

### 2) États à gérer
- active
- expired
- invalid
- revoked

### 3) Comportement recommandé
- Si licence invalide/expirée: désactiver uniquement les features Pro
- Ne pas bloquer ni supprimer les données existantes

## Structure de repo possible

### Option 1 (recommandée): monorepo
- `/plugin-free` (repo WordPress.org)
- `/plugin-pro`
- `/shared` (facultatif, si vous extrayez du code commun)

Avantage: CI unifiée, versioning coordonné.

### Option 2: même repo actuel + sous-dossier Pro
- `/` (free actuel)
- `/pro-addon/` (plugin Pro)

Simple pour démarrer, puis migration vers monorepo structuré plus tard.

## Checklist de mise en œuvre (ordre pragmatique)
1. Ajouter les hooks/filtres d’extension dans le free.
2. Créer le plugin `givasso-pro` minimal (bootstrap + vérif dépendance).
3. Implémenter une première feature Pro isolée (ex: export CSV).
4. Ajouter écran licence Pro (clé + statut).
5. Ajouter endpoints API licence + signatures.
6. Durcir sécurité (nonces, capabilities, rate limit, logs).
7. Ajouter tests d’intégration free/pro.

## Notes conformité WordPress.org
- Le plugin free peut mentionner une version Pro.
- Ne pas bloquer des fonctions free pour forcer la vente.
- Respecter les guidelines de télémétrie/consentement et transparence.

