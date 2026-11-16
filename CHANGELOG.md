# Changelog

All notable changes to this project are documented in this file.

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
