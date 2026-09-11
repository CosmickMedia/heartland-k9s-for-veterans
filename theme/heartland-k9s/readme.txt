=== Heartland Canines for Veterans ===
Contributors: heartlandk9s
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Production theme for Heartland Canines for Veterans, built on Bootstrap 5.3.8 with
self-hosted Fraunces + Inter and an inline lucide icon sprite. Pairs with the
"Heartland K9s Core" companion plugin (page sections, records, forms, importer).

== Structure ==

* functions.php          – bootstraps inc/ modules only
* inc/defaults.php       – theme-side setting defaults (mirrors the plugin schema)
* inc/options.php        – hk9_theme_option(), :root token overrides from settings
* inc/section-defaults.php – reference Home defaults used when the plugin is absent
* inc/setup.php          – supports, menus, image sizes
* inc/assets.php         – compiled CSS/JS, font preloads, head trimming
* inc/icons.php          – hk9_icon() inline SVG from assets/dist/icons.svg
* inc/template-tags.php  – hk9_image(), hk9_button(), hk9_the_hero(), hk9_pagination()…
* inc/menus.php          – primary/footer menu rendering + reference fallbacks
* inc/sections.php       – hk9_render_sections() + plugin-independent accessors
* inc/blocks.php         – block styles, editor tokens, offline editor
* inc/compat.php         – SEO meta only when no SEO plugin is active
* inc/plugin-notice.php  – admin notice when the companion plugin is missing
* page-templates/*.php   – section templates (Template Name headers)
* template-parts/sections/<type>.php – one part per section type

== Build ==

    npm install
    npm run build:css   # tools/build-css.mjs → assets/dist/theme.css + editor.css
    npm run build:js    # tools/build-js.mjs  → assets/dist/theme.js
    npm run build:icons # lucide sprite       → assets/dist/icons.svg + icons.json
    npm run build:fonts # Fraunces/Inter woff2 → assets/fonts/ + assets/src/scss/_fonts.scss

Compiled files in assets/dist/ are committed so the theme installs without Node.

== screenshot.png ==

TODO (packaging phase): capture the real Home page at 1200×900 with the reference
logo and imported content (`node tools/screenshot.mjs --theme-screenshot`) and save it
as screenshot.png in the theme root. Do not ship a placeholder image.

== Third-party licences ==

* Bootstrap 5.3.8 — MIT (https://github.com/twbs/bootstrap/blob/main/LICENSE)
* Fraunces, Inter — SIL Open Font License 1.1 (assets/fonts/OFL-*.txt)
* lucide icons — ISC (docs/licenses/LICENSE-lucide.txt)
