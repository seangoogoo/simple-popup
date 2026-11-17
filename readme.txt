=== Simple Popup ===
Contributors: seangoogoo
Tags: popup, modal, dialog, polylang, newsletter
Requires at least: 6.6
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.2.2
License: MIT
License URI: https://opensource.org/licenses/MIT

Editor-managed popup windows, with per-popup display rules, cookies and Polylang support.

== Description ==

Simple Popup adds a "Popups" content type to the block editor. Each popup is a regular post made of blocks: write it like a page, set a few display rules in the sidebar, publish it, and it is rendered on every page of the site inside a native `<dialog>` element.

There is no page targeting and no scheduling in this version: a popup is shown everywhere while it is published and marked "Active". Several popups can be active at once: never two open at the same time, with a configurable pause between two.

= Features =

* First gesture, page load, or exit-intent triggers.
* Once per visit, every N hours, or once-ever display frequency.
* Optional conversion selector to stop showing a popup to a visitor once they convert.
* Contact Form 7 success events inside a popup always count as a conversion.
* Lazy images inside a popup preload before it opens.
* No front-end dependencies, no jQuery.
* Polylang compatible: shared settings, per-language "Active" state and content.
* Centralized admin list with a per-language toggle column, a plain-language "When" column and an "Order" column.
* Diffusion plan: the active popups of each language, in the order a visitor meets them.
* "Test" link: preview a popup alone, immediately, without cookies.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin through the "Plugins" screen.
3. Create a popup under "Popups", write its content, set its settings, mark it Active, and publish it.

Installing the plugin via a downloaded `simple-popup.zip` ([GitHub Releases](https://github.com/seangoogoo/simple-popup/releases)) enables updates and "View details" on the Plugins page, through the native Update URI mechanism. Dashboard → Updates → Check again checks for a new release immediately.

== Frequently Asked Questions ==

= Can I target specific pages? =

Not in this version: a published and active popup is shown on every page.

= Can two popups be active at once? =

Yes. Each one follows its own trigger, delay and frequency. On a page, never two popups are open at the same time, and the next one waits a configurable pause (Popups > Settings, default 30 seconds) after the previous one closes. Ties are broken by the "Order" attribute. The diffusion plan at the top of the list shows the resulting order.

== Changelog ==

= 0.2.1 =
* Fix: the scheduler could stay stuck forever if `showModal()` threw while opening a popup (e.g. already-open `<dialog>`, detached node); it now releases immediately and moves on to the next popup.
* Fix: the editor's "same moment" conflict notice between two exit-intent popups now ignores their (hidden, unused) delay.
* Fix: frequency value can no longer be set to 0, which was described literally ("every 0 hours") but silently replaced by a different default at runtime; minimum is now 1.
* Fix: the admin list "Test" row action is now only shown when the current user can actually use the preview it links to.
* Fix: the "Diffusion plan" box no longer renders an empty link when the current user can't edit a listed popup.
* Fix: the `simple_popup_siblings`/`simple_popup_preview_url` REST fields no longer run their extra query on anonymous/`view` requests.
* Fix: an invalid conversion selector no longer throws on every DOM mutation inside an open popup; the observer now disconnects instead.

= 0.2.0 =
* Several active popups per language: never two at the same time, configurable pause between two, ties broken by "Order".
* Admin list: "When" sentence and "Order" columns, diffusion plan, "Test" row action; settings page.
* Editor: one-sentence summary, conflict notices, "Test this popup" link.
* Preview URL (`?simple_popup_preview={ID}`) for logged-in editors.

= 0.1.6 =
* Closing animation for the close button, a backdrop click and the Escape key (skipped with reduced motion).

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
