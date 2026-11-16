=== Simple Popup ===
Contributors: seangoogoo
Tags: popup, modal, dialog, polylang, newsletter
Requires at least: 6.6
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.1.4
License: MIT
License URI: https://opensource.org/licenses/MIT

Editor-managed popup windows, with per-popup display rules, cookies and Polylang support.

== Description ==

Simple Popup adds a "Popups" content type to the block editor. Each popup is a regular post made of blocks: write it like a page, set a few display rules in the sidebar, publish it, and it is rendered on every page of the site inside a native `<dialog>` element.

There is no page targeting and no scheduling in this version: a popup is shown everywhere while it is published and marked "Active". Exactly one popup opens per page view; the first trigger to fire wins.

= Features =

* First gesture, page load, or exit-intent triggers.
* Once per visit, every N hours, or once-ever display frequency.
* Optional conversion selector to stop showing a popup to a visitor once they convert.
* Contact Form 7 success events inside a popup always count as a conversion.
* Lazy images inside a popup preload before it opens.
* No front-end dependencies, no jQuery.
* Polylang compatible: shared settings, per-language "Active" state and content.
* Centralized admin list with a per-language toggle column.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin through the "Plugins" screen.
3. Create a popup under "Popups", write its content, set its settings, mark it Active, and publish it.

== Frequently Asked Questions ==

= Can I target specific pages? =

Not in this version: a published and active popup is shown on every page.

= Can two popups be active at once? =

Technically yes, but only one popup opens per page view (the first one to trigger). The admin list warns when several popups are active in the same language.

== Changelog ==

= 0.1.5 =
* Fix: the popup stays fixed in the viewport (it scrolled with the page when a theme or the plugin styles overrode the dialog `position`).
* Fix: page scrolling is locked while a popup is open.
* Fix: the dialog itself is focused on opening, so no focus ring appears on the close button unless the visitor uses the keyboard.

= 0.1.4 =
* Admin list: when all languages are listed, each row's "Languages" column only shows its own language state.

= 0.1.3 =
* Fix: toggling a popup from the admin list returns to the same list view (filters and language kept) instead of the previously visited page.

= 0.1.2 =
* Fix: run shortcodes in the popup content (`do_shortcode()` after `do_blocks()`); the Shortcode block does not execute them on its own outside `the_content`.

= 0.1.1 =
* Fix: the front-end popup query now explicitly filters by the current Polylang language instead of relying on automatic `pre_get_posts` filtering, which doesn't apply to this secondary query. No fallback to another language.
* Fix: load the bundled `.mo` translations (`load_plugin_textdomain()`) so front-end strings (e.g. the close button's "Close" label) are translated.

= 0.1.0 =
* Initial release.
