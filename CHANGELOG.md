# Changelog

All notable changes to this project are documented in this file.

## [Unreleased]

## [0.3.2] - 2026-11-18

### Fixed

- "View details" kept showing an older version and changelog for up to 12 hours after a new release (cached release information). The release is now revalidated with GitHub on every update check and every "View details" (conditional request: no API quota used when nothing changed), under a new cache key, so the stale cache is ignored right after updating.

### Changed

- Release: the `readme.txt` changelog is now generated from `CHANGELOG.md` by `npm version`, no longer written by hand.

## [0.3.1] - 2026-11-18

### Removed

- REST field `simple_popup_trigger_test_url`: the editor builds the trigger-test link from `simple_popup_preview_url` (with `addQueryArgs`).

### Changed

- Preview markup: a single `data-preview="1|trigger"` attribute replaces `data-preview` + `data-preview-mode`.
- `npm version` now runs `lint:js` and `lint:css` first; TypeScript pinned to 5 so ESLint runs again.
- Trigger-test banner: also says when the popup is open; the editor help text now explains both links, which sit side by side.

### Fixed

- Trigger-test banner: screen readers announced the countdown every second. Only step changes are announced now; the countdown is hidden from assistive technology.
- Trigger-test banner: the countdown followed `setInterval` and could lag behind the real delay in a background tab. It now uses the same clock as the trigger.
- "Close test" link: visible focus outline on the dark banner.
- Preview pages send no-cache headers, so a draft popup can't land in a page cache.

## [0.3.0] - 2026-11-18

### Added

- "Test the trigger" link (editor and admin list): opens the popup alone with its real trigger and delay (`?simple_popup_preview={ID}&simple_popup_mode=trigger`), without cookies or conversion storage, outside the pause between popups, with a bottom banner showing a countdown and what to do next. After closing, `gesture` and `exit` popups can be triggered again without reloading. New editor-only REST field `simple_popup_trigger_test_url`.
- French translations for the front-end script (banner texts).

### Changed

- The "Test" link (admin list) and "Test this popup" link (editor) are now called "Preview", with a one-line help in the editor: it still opens the popup immediately. In the editor, both links now sit after the summary sentence.

## [0.2.2] - 2026-11-17

### Added

- Updates from GitHub releases ("View details" and one-click updates in the Plugins screen) through the native Update URI mechanism; release workflow publishing `simple-popup.zip` on tag push.

## [0.2.1] - 2026-11-17

### Fixed

- Scheduler: `showModal()` throwing while opening a popup (another script already has the `<dialog>` open, or the node got detached) left `current` set forever, blocking every other popup on the page. `doOpen()`/`openPreview()` now catch the error and release the scheduler (no pause) before continuing.
- Editor conflict notice: two `exit`-trigger popups with different residual delays (delay is hidden for `exit`, ignored at runtime) were not flagged as a "same moment" conflict. The check for `exit` now ignores delay.
- Frequency value: `0` was accepted by the field and sanitization, described literally ("every 0 hours"/"0 min of inactivity"), but replaced by a different default (24h/30min) at runtime. Sanitization now enforces a minimum of 1; the editor field's `min` follows.
- Admin list "Test" row action was shown for any published popup regardless of capability, while the preview it links to requires `edit_post`. It now requires the same capability.
- "Diffusion plan" box rendered an empty `<a href="">` for a popup the current user can't edit (`get_edit_post_link()` returns null). It now falls back to plain text.
- `simple_popup_siblings` and `simple_popup_preview_url` REST fields ran their `get_posts()`/URL-building logic on every request, including anonymous `view`-context ones, even though the fields are editor-only. They now return early outside `edit` context.
- `watchConversion()`: an invalid conversion selector threw inside the `MutationObserver` callback on every subsequent mutation. It now disconnects the observer instead.

## [0.2.0] - 2026-11-16

### Changed

- Several popups can be active in the same language. The "one popup per page view, first trigger wins" rule is replaced by a page-level scheduler: never two popups open at the same time; after a popup closes, the next one waits a configurable pause; a waiting `load` popup opens when the pause ends, a `gesture` popup at the next gesture, an `exit` popup at the next exit intent. Ties are broken by the native "Order" attribute (`menu_order`), then the most recent.
- Admin list: the Trigger, Delay and Frequency columns are replaced by a plain-language "When" column; new "Order" column. The "several popups active" notice is replaced by a diffusion plan box (active popups per language, in the order a visitor meets them, with the pause rule).

### Added

- `Popups > Settings` page: pause between two popups (`simple_popup_gap`, seconds, default 30).
- "Order" page attribute, shared by all language versions.
- Editor panel: one-sentence summary, conflict notices (same moment as another active popup, or within the pause), "Test this popup" link.
- Preview: `?simple_popup_preview={ID}` renders only that popup and opens it immediately, without cookies or conversion storage, for users who can edit it. "Test" row action in the admin list.

## [0.1.6] - 2026-11-16

### Added

- Closing animation (fade and slide down, popup and backdrop), for the close button, a backdrop click and the Escape key. Skipped when the visitor prefers reduced motion.

## [0.1.5] - 2026-11-16

### Fixed

- The popup stays fixed in the viewport: the public stylesheet set `position: relative` on the dialog, overriding the native fixed positioning of modal dialogs, so the popup scrolled with the page.
- Page scrolling is locked while a popup is open (`html:has(dialog.simple-popup[open])`), and scroll chaining from the popup is contained.
- The dialog itself (`tabindex="-1"`) receives focus on opening instead of the close button, so no focus ring appears unless the visitor navigates with the keyboard.

## [0.1.4] - 2026-11-16

### Changed

- Admin list, "Languages" column: when all languages are listed (one row per language version), each row only shows its own language state, so toggling visibly changes one row only. The full per-language overview remains when the list is filtered to one language.
- Plugin author restored to Jensen SIU (0.1.3 changed it by mistake).

## [0.1.3] - 2026-11-16

### Fixed

- Toggling a popup from the admin list now returns to the same list view (filters and language kept). The return URL was taken from the HTTP referer, i.e. the page visited before the list.

## [0.1.2] - 2026-11-16

### Fixed

- Run shortcodes in the popup content (`do_shortcode()` after `do_blocks()`): the Shortcode block does not execute them on its own outside `the_content`, so a newsletter form shortcode rendered as plain text.

## [0.1.1] - 2026-11-16

### Fixed

- Front-end popup query (`get_displayable_popups()`) now explicitly filters by `pll_current_language()` when the post type is translated, instead of relying on Polylang's automatic `pre_get_posts` filtering (which doesn't apply to this secondary `WP_Query`). No fallback to another language. Fixes a French-only popup also rendering on the English/Spanish language domains.
- Load the bundled `.mo` translations with `load_plugin_textdomain()`, so front-end strings (e.g. the close button's "Close"/"Fermer" label) are translated. The editor script's JSON translations were already wired correctly via `wp_set_script_translations()`.

## [0.1.0] - 2026-11-16

### Added

- `simple_popup` post type with a "Popup settings" editor sidebar panel (Active toggle, trigger, delay, frequency, frequency value, conversion selector).
- Front-end rendering of published and active popups as `<dialog>` elements in `wp_footer`, with the `simple_popup_should_display` filter.
- Public script: gesture/load/exit triggers, lazy image preloading, one popup per page view, per-popup cookie, conversion tracking via `MutationObserver` and `localStorage`, `wpcf7mailsent` support.
- Public stylesheet with `--simple-popup-*` CSS variables and `prefers-reduced-motion` support.
- Polylang compatibility: `simple_popup_translatable` filter, shared settings synchronized across language versions, "Languages" admin column and editor sidebar box.
- Admin list: state filter, per-language toggle links, notice when several popups are active in the same language.
- French translation (`languages/simple-popup-fr_FR.po/.mo` + JSON for the editor script).
