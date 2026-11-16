# Changelog

All notable changes to this project are documented in this file.

## [0.1.0] - 2026-11-16

### Added

- `simple_popup` post type with a "Popup settings" editor sidebar panel (Active toggle, trigger, delay, frequency, frequency value, conversion selector).
- Front-end rendering of published and active popups as `<dialog>` elements in `wp_footer`, with the `simple_popup_should_display` filter.
- Public script: gesture/load/exit triggers, lazy image preloading, one popup per page view, per-popup cookie, conversion tracking via `MutationObserver` and `localStorage`, `wpcf7mailsent` support.
- Public stylesheet with `--simple-popup-*` CSS variables and `prefers-reduced-motion` support.
- Polylang compatibility: `simple_popup_translatable` filter, shared settings synchronized across language versions, "Languages" admin column and editor sidebar box.
- Admin list: state filter, per-language toggle links, notice when several popups are active in the same language.
- French translation (`languages/simple-popup-fr_FR.po/.mo` + JSON for the editor script).
