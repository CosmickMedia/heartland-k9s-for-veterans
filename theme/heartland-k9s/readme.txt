=== Heartland Canines for Veterans ===
Contributors: heartlandk9s
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.3.1
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
* inc/assets.php         – compiled CSS (core + template bundles)/JS, font + LCP preloads, head trimming
* inc/icons.php          – hk9_icon() inline SVG from assets/dist/icons.svg
* inc/template-tags.php  – hk9_image(), hk9_button(), hk9_the_hero(), hk9_pagination()…
* inc/menus.php          – primary/footer menu rendering + reference fallbacks
* inc/sections.php       – hk9_render_sections() + plugin-independent accessors
* inc/blocks.php         – block styles (Card, Navy band, Statistic, Lead, Crimson bar, Testimonial, Checklist, Navy button…), editor tokens, offline editor, "Heartland" pattern category
* patterns/*.php         – block patterns (core blocks + the theme block styles): text + image, feature cards, CTA band, FAQ, buttons, testimonial, stats, contact details, section heading
* assets/img/            – placeholder-4x3.svg used by the image patterns until a photo is picked
* inc/compat.php         – SEO meta only when no SEO plugin is active
* inc/plugin-notice.php  – admin notice when the companion plugin is missing
* page-templates/*.php   – section templates (Template Name headers)
* template-parts/sections/<type>.php – one part per section type

== Build ==

    npm install
    npm run build:css   # tools/build-css.mjs → assets/dist/theme.css (core), forms/content/blog/pages/records.css, editor.css
    npm run build:js    # tools/build-js.mjs  → assets/dist/theme.js
    npm run build:icons # lucide + brand sprite → assets/dist/icons.svg + icons.json
    npm run build:fonts # Fraunces/Inter woff2 → assets/fonts/ + assets/src/scss/_fonts.scss

Compiled files in assets/dist/ are committed so the theme installs without Node.

== screenshot.png ==

1200×900 PNG of the imported Home page (top of the viewport, no admin bar), captured
with Playwright/Chromium at a 1200×900 viewport and palette-compressed (~230 kB).
Re-capture after a visual change: load http://localhost:8093/ logged out at 1200×900,
wait for fonts + images, `page.screenshot({ fullPage: false })`, then save it as
screenshot.png in the theme root. Do not ship a placeholder image.

== Third-party licences ==

* Bootstrap 5.3.8 — MIT (https://github.com/twbs/bootstrap/blob/main/LICENSE)
* Fraunces, Inter — SIL Open Font License 1.1 (assets/fonts/OFL-*.txt)
* lucide icons — ISC (docs/licenses/LICENSE-lucide.txt)
* Simple Icons brand glyphs (facebook, instagram, youtube, linkedin, x-social, tiktok) — CC0 1.0
  (docs/licenses/LICENSE-simple-icons.txt); embedded in tools/build-icons.mjs
* assets/img/placeholder-4x3.svg — theme artwork built from the lucide "paw-print" glyph (ISC)

== Changelog ==

= 1.2.0 =
* Default page template is the starting point for new pages: hero band (title + excerpt, or the
  Hero (band) panel: eyebrow / heading / intro / pattern) that can be unticked under Page sections
  for a plain start (title inside the content card), the block content card, and an optional
  Call to action band (hidden by default). The block editor shows a default-template guidance notice.
* Block patterns in a "Heartland" category (patterns/*.php, core blocks only): Text + image (left /
  right), Three feature cards, Call to action band, FAQ (details), Two buttons row, Quote /
  testimonial, Stats row, Contact details block (pre-filled from Settings → Contact), Section
  heading with crimson divider. Matching block styles in inc/blocks.php with CSS shared by
  content.css and editor.css (Card, Card (muted), Navy band, Tinted band, Statistic, Lead,
  Crimson bar, Thin rule, Testimonial, Checklist, Navy button); core buttons render as the
  theme's 56 px CTA buttons; Details summaries get the crimson chevron.
* Footer credit: "Built with ♥ for our veterans by Cosmick Media." — footer.credit +
  footer.credit_by_label / footer.credit_by_url settings (plain 12 px bar text, underline on hover).
* Social links: TikTok (contact.tiktok) with the Simple Icons glyph; every social icon renders only
  when its URL is set.
* Settings completeness: search placeholder (blog.search_placeholder); 404 / empty-search helpful
  links from the new "Helpful links (404 & search)" menu location (reference fallback when
  unassigned); BarKode contact fallback uses the site title; Settings → Footer links to
  Appearance → Menus for the column links.

= 1.1.0 =
* Companion release for plugin 1.1.x (existing-site migration mode, content-only payload).

= 1.0.2 =
* Performance: the frontend CSS is a 47 kB core (theme.css) plus per-template bundles
  (forms, content, blog, pages, records) enqueued from a template map in inc/assets.php
  (filter `hk9/theme/style_bundles`); unused Bootstrap layers (buttons, type, grid,
  transitions, validation, pagination, utilities API, palette root variables) dropped.
* Font preloads use the exact @font-face URL (no `?v=` query), so each woff2 downloads once.
* The LCP image (image hero, About split-card photo, blog hero) is preloaded with the
  same srcset/sizes as its <img> (filter `hk9/theme/lcp_image`).
* Header/footer logo: new `hk9-logo-sm` (140×160) size, created on demand for existing
  logos, with `sizes` derived from the configured logo height.
* Accessibility: 2 px keyboard focus ring — `--hk9-focus-ring` token (navy on light
  surfaces, white inside hero/navy/footer); buttons use a currentColor outline + halo.
* Donate "Ways to give": a PayPal item follows Settings → Destinations → PayPal hosted
  button ID (hidden when empty, href rebuilt from the id).

= 1.0.1 =
* Record listings prime featured images / meta (no per-card queries); auto listings are
  capped (filter hk9/theme/rec_query_limit) and the plugin-less events fallback is bounded in SQL.
* Footer social icons come from the sprite (Simple Icons brand glyphs, X as `x-social`).
* Plugin-less section defaults are translatable and use the links.* settings for CTAs.
* Record hero fragments pass through wp_kses; BarKode "do not separate" notice is static text.
* Dead FAQ accordion script and no-op lazy-loading filter removed; primary menu query deduped.

= 1.0.0 =
* Initial release.
