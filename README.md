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

## Notes

- **Shared settings sync**: the trigger, delay, frequency, frequency value and conversion selector are kept in sync across language versions only when one of them actually changes (`updated_post_meta`) or a translation is saved from the editor (`save_post`). A translation linked through another path (e.g. a migration script calling `pll_save_post_translations()` directly) stays out of sync with the "Shared by all languages" settings shown in the editor until one of the shared settings is next changed on any version.
- **REST API exposure**: `simple_popup` is `show_in_rest`, so a published popup's content and settings (trigger, delay, frequency, conversion selector) are readable by anyone through `/wp/v2/simple_popup`, like any other public post type. Drafts and other non-public statuses are not exposed (`read_post`). The `simple_popup_siblings`, `simple_popup_translations` and `simple_popup_preview_url` REST fields are editor-only (`context: edit`) and not returned on anonymous requests.
- **Cookies are per language version**: each language is a distinct post with its own ID and its own `simple_popup_{ID}` cookie. A visitor who switches language sees what looks like the "same" popup again, because it is a different post as far as the cookie is concerned. This is consistent with the "Active" state and content being per-language.

## Installation

### For site users

1. Download `simple-popup.zip` from the [GitHub Releases page](https://github.com/seangoogoo/simple-popup/releases).
2. In your WordPress admin, go to Plugins → Add New Plugin → Upload Plugin, select the zip file, then click Install Now.
3. Activate "Simple Popup".
4. Create a popup under "Popups", write its content with blocks, set its trigger/frequency in the sidebar, mark it Active, and publish it.

Once installed this way, updates appear on the Plugins page like any other plugin (Dashboard → Updates → Check again to check immediately). "View details" on the Plugins page shows the full changelog; release information is cached for 12 hours (1 hour after a failed check) unless "Check again" is used.

### For development

Installing the plugin from a `git clone` disables the update checker (a `.git` folder in the plugin directory means dashboard updates never overwrite a development clone), so use this method only to work on the code, not on a production site.

```sh
cd wp-content/plugins
git clone https://github.com/seangoogoo/simple-popup.git
cd simple-popup
npm install
npm run build
```

`npm run start` runs the webpack watcher during development. The built `build/` directory is versioned in this repository, so a `git clone` can also be used without a build step; run a production build before committing.

Other available scripts: `npm run lint:js`, `npm run lint:css`, `npm run format`, `npm run plugin-zip`.

## Releasing

1. While working, add entries under `## [Unreleased]` in `CHANGELOG.md` (Added / Changed / Fixed / Removed) and commit them with the code.
2. On a clean `main`, run `npm version patch` (fixes) or `npm version minor` (features):
   - `preversion` refuses to release when `[Unreleased]` has no entry;
   - `version` copies the version to the plugin header, the `SIMPLE_POPUP_VERSION` constant and `readme.txt`, dates the changelog section, rebuilds, and adds the files to the release commit;
   - `postversion` pushes the commit and the `vX.Y.Z` tag.
3. The GitHub workflow `.github/workflows/release.yml` builds `simple-popup.zip` and publishes the GitHub release, with the changelog section as release notes.

If the push fails, run `git push --follow-tags` manually. If the version step fails, run `git checkout -- .` before retrying.

Sites running the plugin see the update in the Plugins page (Dashboard → Updates → Check again shows it immediately). Update checks are disabled in git working copies (a `.git` folder in the plugin directory), so a dashboard update never overwrites a development clone.

## License

MIT — see [LICENSE](LICENSE).
