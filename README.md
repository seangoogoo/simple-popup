# Simple Popup

A lightweight, generic WordPress plugin for editor-managed popup windows.

## Description

Simple Popup adds a "Popups" content type to the block editor. Each popup is a regular post made of blocks: write it like a page, set a few display rules in the sidebar, publish it, and it is rendered on every page of the site inside a native `<dialog>` element.

There is no page targeting and no scheduling in this version: a popup is shown everywhere while it is published and marked "Active". Several popups can be active at the same time, each with its own trigger, delay and frequency (see [Several active popups](#several-active-popups)).

## Features

- **Block editor content**: a popup's body is rendered with `do_blocks()` then `do_shortcode()`, so shortcodes (e.g. a newsletter form) and any block work as expected.
- **Triggers**: first gesture (scroll, click, key press) after a delay, page load after a delay, or exit intent (desktop only).
- **Frequency**: once per visit (sliding cookie expiration), at most once every *N* hours, or only once ever.
- **Conversion tracking**: an optional CSS selector that, once it appears inside the popup (e.g. a success message), prevents the popup from ever being shown again to that visitor. The `wpcf7mailsent` event (Contact Form 7) inside the popup is always treated as a conversion.
- **Lazy image handling**: `loading="lazy"` images inside a popup are forced to load when the popup is about to open, with a 1.5 s cap so the popup isn't stuck waiting.
- **No dependencies on the front end**: the public script has no dependencies and no jQuery.
- **Polylang compatible**: each language version is a separate post, linked as a translation. The "Active" state and the content are specific to each language version; the trigger, delay, frequency and conversion selector are shared and kept in sync automatically.
- **Centralized admin list**: `Popups` screen with a state filter, a plain-language "When" column, an "Order" column and, when Polylang is active, a "Languages" column showing each language's state with one-click toggle and translation links. When all languages are listed, each row only shows its own language.
- **Diffusion plan**: a box at the top of the list shows, per language, the active popups in the order a visitor meets them on a page.
- **Preview**: a "Test" link (list and editor) opens the popup alone on the home page, immediately, without reading or writing its cookie (logged-in editors only).

## Settings

Each popup has the following settings, available in the "Popup settings" panel of the block editor:

| Setting | Values | Default |
|---|---|---|
| Active | boolean | `false` |
| Trigger | `gesture`, `load`, `exit` | `gesture` |
| Delay | seconds | `4` |
| Frequency | `visit`, `hours`, `once` | `visit` |
| Frequency value | minutes (`visit`) or hours (`hours`) | `30` |
| Conversion selector | CSS selector, optional | *(empty)* |

A popup is only displayed on the front end if it is both **published** and **Active**. The "Order" (native page attribute, shared by all languages) breaks ties between popups.

## Several active popups

Each active popup follows its own trigger, delay, frequency and cookie. On a page, two rules apply:

1. **Never two popups at the same time.**
2. **A pause between two popups**: after a popup closes, the next one may open only after *N* seconds (`Popups > Settings`, default 30, `0` = no pause).

A popup whose moment arrives while another one is open, or during the pause, waits: a `load` popup opens as soon as the pause ends, a `gesture` popup at the first gesture after it, an `exit` popup at the next exit intent after it. When several popups are ready at the same moment, the lowest "Order" opens first (then the most recent). Delays are counted from the page load: changing page starts them again.

In the editor, the "Popup settings" panel summarizes the popup in one sentence and warns when another active popup of the same language opens at the same moment, or within the pause.

## Filters

- `simple_popup_should_display( bool $display, WP_Post $popup ): bool` — runs for every published and active popup before it is rendered in `wp_footer`. Return `false` to exclude a popup in a given context (e.g. a specific page template).
- `simple_popup_translatable( bool $translatable ): bool` — defaults to `true`. Return `false` to prevent the plugin from registering `simple_popup` as a Polylang-translatable post type.

## Preview

`?simple_popup_preview={ID}` on the home page (of the popup's language with Polylang) renders only that popup and opens it immediately, even if it is inactive or a draft, without cookies or conversion storage. It requires a logged-in user who can edit the popup; for anyone else, the page renders normally.

## CSS variables

The public stylesheet is intentionally minimal and meant to be overridden by the theme:

```css
dialog.simple-popup {
  --simple-popup-max-width: 480px;
  --simple-popup-radius: 12px;
  --simple-popup-backdrop: rgba(0, 0, 0, 0.5);
  --simple-popup-padding: 24px;
}
```

The actual look of a popup's content is expected to come from the blocks themselves (native block settings first, custom block CSS second).

## Polylang compatibility

If Polylang is active, Simple Popup registers `simple_popup` as a translatable post type (filterable with `simple_popup_translatable`). Each language is a distinct post. On the front end, only the popup for the current language is considered — there is no fallback to another language. In the editor, the "Popup settings" panel shows the "Active" toggle for the open version, then the shared settings ("Shared by all languages"), then a "Languages" box linking to the other versions (or to create them).

Without Polylang (or any multilingual plugin), every active popup is a candidate for display; the admin list falls back to a single Active/Inactive toggle column.

## Installation

1. Copy the plugin folder to `wp-content/plugins/simple-popup` (the repository already ships a built `build/` directory, so no build step is required to use it).
2. Activate "Simple Popup" from the Plugins screen.
3. Create a popup under "Popups", write its content with blocks, set its trigger/frequency in the sidebar, mark it Active, and publish it.

## Development

```sh
npm install
npm run build
```

`npm run start` runs the webpack watcher during development. The built `build/` directory is versioned in this repository so the plugin can be installed without a build step.

## License

MIT — see [LICENSE](LICENSE).
