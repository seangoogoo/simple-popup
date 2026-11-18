# Simple Popup 0.3.0 : « Aperçu » et « Tester le déclenchement » — plan d'exécution

Rédigé le 2026-11-18, pour exécution dans une nouvelle session, dans le workspace `/Users/jensensiu/Sites/wp-multi`. Répondre au propriétaire en français simple et court. Code, commentaires, textes d'interface et commits en anglais ; traduction française fournie.

## Décision du propriétaire (2026-11-18)

Le lien actuel « Test this popup » ouvre la fenêtre **immédiatement**, sans tenir compte du déclenchement ni du délai. Il est placé juste au-dessus de la phrase de résumé (« Opens on the first gesture after 4 seconds… ») : l'utilisateur s'attend à tester ce comportement et ne le retrouve pas. **Option B validée** : deux actions distinctes.

| Action | Libellé (EN / FR) | Comportement |
|---|---|---|
| Aperçu | « Preview » / « Aperçu » | Comportement actuel : ouverture immédiate, seule, sans cookie ni conversion. Aide courte : « Opens right away, ignoring the trigger and delay. » / « S'ouvre tout de suite, sans tenir compte du déclenchement ni du délai. » |
| Test du déclenchement | « Test the trigger » / « Tester le déclenchement » | La fenêtre suit son **déclenchement et son délai réels** (`gesture`, `load`, `exit`), sans lire ni écrire son cookie ni la conversion, seule sur la page (pas d'autres fenêtres, pas de pause). Un **bandeau** sur la page explique quoi faire. |

## Existant (v0.2.2, à vérifier avant de coder)

- Lien d'aperçu : `Simple_Popup_Frontend::get_preview_url()` (`includes/class-simple-popup-frontend.php:33`), paramètre `?simple_popup_preview={ID}` (`PREVIEW_QUERY_VAR`), validation dans `get_preview_popup()` (ligne 55 : type, non à la corbeille, `edit_post`), rendu seul avec `data-preview="1"` (`render_popup( $popup, true )`, ligne 172).
- Script : `isPreview` (`src/public/index.js:181`) court-circuite cookie/conversion ; `init()` appelle `openPreview()` (ligne ~538) qui ouvre immédiatement hors planificateur.
- Éditeur : `ExternalLink` « Test this popup » (`src/editor/index.js:196`), visible si la fenêtre est publiée ; URL fournie par le champ REST `simple_popup_preview_url` (`includes/class-simple-popup-editor.php:69`, contexte `edit`).
- Liste : action de ligne « Test » (`includes/class-simple-popup-admin-list.php:505`), réservée à `edit_post`.

## Conception

1. **URL** : un seul paramètre, avec un mode : `?simple_popup_preview={ID}` = aperçu immédiat (inchangé, compatibilité) ; `?simple_popup_preview={ID}&simple_popup_mode=trigger` = test du déclenchement. `get_preview_url( $post_id, $mode = 'preview' )`. Même validation de droits pour les deux modes ; un mode inconnu = aperçu.
2. **Rendu** : `data-preview="1"` et `data-preview-mode="preview|trigger"` sur le `<dialog>`.
3. **Script** :
   - `preview` : `openPreview()` inchangé.
   - `trigger` : armer la fenêtre avec son déclencheur et son délai normaux (`armGesture` / `armLoad` / `armExit`), mais `isSeen()` faux, `markSeen()` / conversion sans effet (déjà garantis par `isPreview`), hors planificateur (pas de pause, aucune autre fenêtre puisque la page n'en contient qu'une). Après fermeture, la fenêtre peut se rouvrir au déclenchement suivant, pour tester plusieurs fois sans recharger (à confirmer pendant la recette ; si c'est déroutant, ne l'ouvrir qu'une fois et l'indiquer dans le bandeau).
   - `exit` sur un écran tactile ou sans pointeur fin : le bandeau l'indique (« Exit intent only works on a desktop computer with a mouse. »).
4. **Bandeau** (mode `trigger` seulement) : barre fixe en bas de page, discrète, au-dessus du contenu mais **sous** la fenêtre ouverte (top layer), texte en direct (`aria-live="polite"`) :
   - avant le délai : « Test mode: <phrase de résumé>. Waiting N s… » avec compte à rebours ;
   - après le délai : « Now: scroll, click or press a key » (`gesture`), « Opening… » (`load`), « Now: move the mouse out of the window, towards the top » (`exit`) ;
   - après ouverture/fermeture : « Popup closed. Trigger it again or close this test. » ;
   - un lien « Close test » qui retire les paramètres de l'URL.
   La phrase de résumé vient de `Simple_Popup_CPT::describe()` (rendue côté PHP dans un attribut ou un élément du bandeau, déjà traduite). Styles dans `src/public/style.scss`, variables CSS existantes, `prefers-reduced-motion` respecté.
5. **Éditeur** (`src/editor/index.js`) : remplacer le lien par deux liens externes côte à côte, « Preview » et « Test the trigger », avec l'aide d'une ligne sous « Preview ». Champ REST : ajouter `simple_popup_trigger_test_url` (même contrôle `edit` que `simple_popup_preview_url`), ou un champ unique renvoyant les deux URL. Placer les liens **après** la phrase de résumé des réglages communs, pour que le lien « Tester le déclenchement » suive la description de ce qu'il teste.
6. **Liste** : action de ligne « Test » remplacée par « Preview » et « Test the trigger » (mêmes droits).
7. **Documentation** : `README.md` (section Preview), `readme.txt`, `CHANGELOG.md` sous `[Unreleased]` (### Added / ### Changed), liste « Key features » de `includes/class-simple-popup-updater.php`.
8. **Traduction FR** : `wp i18n make-pot . languages/simple-popup.pot --domain=simple-popup --exclude=node_modules --skip-audit`, `msgmerge` dans `languages/simple-popup-fr_FR.po` (conserver l'en-tête), traduire, aucune entrée `fuzzy` ni obsolète, `msgfmt`, `wp i18n make-json languages --no-purge` puis ne garder que le JSON dont la source est `build/editor.js`, renommé `simple-popup-fr_FR-simple-popup-editor.json`. Si le bandeau a des textes en JS, enregistrer aussi les traductions du script public (`wp_set_script_translations` sur son handle) et produire son JSON de la même façon, nommé d'après ce handle.

## Tâches

1. Lire `README.md`, `CHANGELOG.md`, `AGENTS.md` de wp-multi ; `git status` propre, `git pull`.
2. Implémenter la conception (points 1 à 6) ; `npm run build` ; `php -l` sur tous les PHP.
3. Traductions et documentation (points 7-8).
4. **Recette dans le navigateur** sur `https://wp-multi.local` (prévenir le propriétaire avant d'ouvrir le navigateur ; fermer ce qu'on a ouvert à la fin). Créer des fenêtres `TEST …` (une par déclencheur : `gesture` 4 s, `load` 3 s, `exit`), puis vérifier :
   - « Aperçu » : ouverture immédiate, comme avant.
   - « Tester le déclenchement » `gesture` : rien avant 4 s ni sans geste ; ouverture au premier geste après 4 s ; bandeau correct à chaque étape.
   - `load` : ouverture à 3 s, sans geste.
   - `exit` : ouverture en sortant la souris par le haut ; message sur écran tactile (émulation mobile).
   - aucun cookie `simple_popup_*` ni clé `localStorage` écrits ; un visiteur non connecté avec l'URL de test voit la page normale.
   - textes en français dans une administration en français ; aucune erreur console venant de l'extension.
   - éditeur : deux liens après la phrase de résumé ; liste : deux actions de ligne.
   Supprimer les fenêtres `TEST …` à la fin.
5. Publication : `npm version minor` (0.2.2 → 0.3.0 ; le workflow publie la Release GitHub avec `simple-popup.zip` ; si le workflow ne démarre pas, supprimer puis repousser l'étiquette `v0.3.0`). Vérifier la Release (`gh release view v0.3.0`).
6. Rapport au propriétaire : ce qui a été fait, ce qui a été vérifié, captures si utiles.

Les copies de l'extension installées sur d'autres sites sont hors de ce plan : la passation du workspace indique comment les mettre à jour.
