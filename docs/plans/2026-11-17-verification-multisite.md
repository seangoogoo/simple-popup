# Simple Popup : vérification en environnement multisite — feuille de route

Rédigée le 2026-11-17 pour l'agent qui travaille dans le workspace `wp-multi`. Répondre au propriétaire en français simple et court.

## Contexte

- Extension : **Simple Popup 0.2.2**, dépôt `https://github.com/seangoogoo/simple-popup` (branche `main`, étiquettes `v0.1.0` à `v0.2.2`, Release GitHub `v0.2.2` avec `simple-popup.zip`).
- **Dossier de référence (clone Git) : `wp-multi/wp-content/plugins/simple-popup`**, activé sur tout le réseau. Le module de mise à jour (`includes/class-simple-popup-updater.php`) y est désactivé, car le dossier contient `.git`.
- Fonctionnement à connaître avant de commencer : `README.md` (règles « jamais deux fenêtres en même temps », pause entre deux, ordre, Plan de diffusion, aperçu `?simple_popup_preview=ID`, filtres) et `CHANGELOG.md`.
- wp-multi : WordPress 7.1.4 multisite en mode sous-dossiers (`SUBDOMAIN_INSTALL=false`), mais chaque site a son propre domaine :

| blog_id | Adresse |
|---|---|
| 1 | https://wp-multi.local/ |
| 2 | https://wp-multi-gb.local/ |
| 3 | https://wp-multi-es.local/ |

- Polylang n'est pas installé : la liste « Popups » affiche une colonne Active/Inactive au lieu de la colonne Langues.
- Lire `wp-multi/AGENTS.md` (conventions, ne jamais modifier le cœur).

## Règles

- **Ne rien modifier dans le code de l'extension sans accord du propriétaire.** Les constats et les corrections proposées vont dans le rapport (section « Rapport » ci-dessous). Si une correction est validée : branche dédiée, entrée `[Unreleased]` dans `CHANGELOG.md`, `npm run build`, commit en anglais (`fix: …`), puis publication par `npm version patch` (déclenche la Release GitHub ; si le workflow ne démarre pas, repousser l'étiquette).
- Données de test : créer des fenêtres dont le titre commence par `TEST ` et les supprimer définitivement à la fin. Ne pas toucher aux autres contenus des sites.
- Créer un site, changer le mode du réseau ou installer une extension : **demander l'accord du propriétaire avant.**
- Navigateur : prévenir le propriétaire avant de l'ouvrir ; fermer à la fin les onglets et les processus lancés pour la tâche, et seulement ceux-là.
- Préférer les preuves WP-CLI (`wp --url=<site> …`) et curl ; le navigateur seulement pour le comportement à l'écran (ouverture, cookies).

## Vérifications

### 1. Isolation des données par site

1. Créer sur le site 1 deux fenêtres publiées et actives (`TEST A`, déclenchement au chargement, 2 s ; `TEST B`, au chargement, 5 s) et sur le site 2 une fenêtre (`TEST C`, au chargement, 2 s). Noter les ID : comme chaque site a sa propre table de posts, des ID identiques entre sites sont possibles (les provoquer si besoin).
2. Liste « Popups » (`/wp-admin/edit.php?post_type=simple_popup`) de chaque site : seules les fenêtres du site apparaissent ; le Plan de diffusion ne cite que celles-ci.
3. Page d'accueil de chaque site (curl) : seuls les `<dialog id="simple-popup-…">` du site sont présents.
4. Éditeur d'une fenêtre du site 1 : les alertes de conflit ne citent que des fenêtres du site 1 (données REST `simple_popup_siblings`).
5. Aperçu `?simple_popup_preview=<ID>` : sur le bon site, la fenêtre s'ouvre ; avec un ID d'un autre site, rien ne s'affiche (pas de fuite entre sites).

### 2. Réglage de pause par site

1. `Popups > Réglages` : régler la pause à 5 s sur le site 1, laisser 30 s (défaut) sur le site 2.
2. Vérifier par `wp --url=… option get simple_popup_gap` et par la configuration injectée dans la page (`window.simplePopupConfig`) que chaque site garde sa valeur.
3. Vérifier qui peut voir la page Réglages : un administrateur de site (non super-administrateur) doit pouvoir la régler pour son site. Constat seulement si un tel compte n'existe pas (ne pas en créer sans accord).

### 3. Cookies des visiteurs

Chaque fenêtre écrit un cookie `simple_popup_{ID}` (chemin `/`) et l'éventuelle conversion dans `localStorage` (`simple_popup_converted_{ID}`).

1. Sur wp-multi, les sites ont des domaines différents : vérifier dans le navigateur qu'une fenêtre vue sur le site 1 n'empêche pas l'affichage d'une fenêtre de même ID sur le site 2.
2. **Cas à risque, à analyser :** un réseau en sous-dossiers sur un seul domaine (`exemple.org/site2/`). Les cookies (`path=/`) et le `localStorage` (par origine) seraient alors partagés entre sites : deux fenêtres de même ID sur deux sites se masqueraient l'une l'autre. Le démontrer, soit sur un site de test en sous-dossier (avec accord du propriétaire), soit par l'analyse du code (`src/public/index.js`). Proposer une correction, par exemple préfixer les clés par l'identifiant du site (`simple_popup_{blog_id}_{ID}`) en gardant la compatibilité des cookies existants sur un site simple.

### 4. Mises à jour depuis GitHub, extension activée sur le réseau

Le clone Git désactive le module : il faut une copie installée depuis le zip.

1. Avec accord du propriétaire : déplacer temporairement le clone hors de `wp-content/plugins` (par exemple `wp-multi/_dev/simple-popup`), puis installer la Release : `wp plugin install https://github.com/seangoogoo/simple-popup/releases/download/v0.2.2/simple-popup.zip --activate-network`.
2. Administration réseau → Extensions : le lien « Afficher les détails » est présent et la fenêtre de détails s'ouvre (description, journal des modifications).
3. Simuler une version plus ancienne : passer l'en-tête `Version:` de la copie installée à `0.2.1`, supprimer le transitoire `simple_popup_latest_release` (`wp site option` / `wp transient delete --network`), recharger « Tableau de bord → Mises à jour » (Vérifier à nouveau). La mise à jour vers 0.2.2 doit être proposée au niveau du réseau, puis s'installer sans erreur ; l'extension reste activée sur le réseau.
4. Remettre en place : supprimer la copie installée, remettre le clone Git à sa place, `wp plugin activate simple-popup --network`, vérifier `git status` propre.

### 5. Activation et désactivation sur le réseau

1. Désactiver puis réactiver sur le réseau : aucune erreur, les fenêtres et réglages de chaque site sont conservés.
2. Activer l'extension sur un seul site (après désactivation réseau) : elle ne fonctionne que sur ce site ; revenir ensuite à l'activation réseau.

## Fin de tâche

- Supprimer définitivement les fenêtres `TEST …`, remettre `simple_popup_gap` par défaut sur chaque site (`wp --url=… option delete simple_popup_gap`), vérifier que le clone Git est en place et propre.
- Fermer le navigateur et les processus lancés pour la tâche.

## Rapport

Ajouter à la fin de ce fichier une section « Résultats (date) » : pour chaque vérification, OK ou écart avec la preuve (commande, sortie, capture), puis les corrections proposées avec leur priorité. Ne pas publier de version sans accord du propriétaire.
