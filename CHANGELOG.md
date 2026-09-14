# Changelog

Theme and plugin share one version number and are released together; each GitHub release carries `heartland-k9s.zip` and `heartland-k9s-core.zip`, which the sites' update checkers install.

## v1.3.1 — 2026-09-14

Thank You page template (post-application confirmation): band hero, next-steps card with numbered steps, Medical History Form download (type/size from the file), return address and help strip from Settings, While-You-Wait cards, optional CTA — all editable in Page sections; the migrated page ships noindex and re-imports in place from the lite payload. Block editor: the Meta Boxes pane (section panels) opens by default. Importer: a missing meta key and an empty value no longer count as an editor conflict (false conflicts after re-saving records); rollback uses the same rule. 8 new lucide icons. Test fixtures updated for the 1.3.0 WebP output.

## v1.3.0 — 2026-09-13

- SEO: per-page 'Search & social' fields (title, description, social image, noindex, canonical), Open Graph/Twitter cards with 1200×630 images, canonical + rel prev/next, sitemap lastmod, visible breadcrumbs, and JSON-LD structured data (NGO organization with EIN/501(c)(3)/address/DonateAction, WebSite, WebPage, BreadcrumbList, Article, Event with timezone-correct dates, FAQPage, team Person list). Automatically defers to Slim SEO / Yoast / Rank Math / AIOSEO / SEOPress and merges only the extra nodes.
- Performance: WebP sub-sizes for theme image sizes, inline critical CSS, deferred italic font, responsive mobile hero with matching preload, gallery tile sizes, Gravity Forms assets only on form pages — mobile Lighthouse 94–100, home LCP 2.25 s.
- URLs: author-archive redirects (/author/admin/, /author/hk9director/ → /), URL coverage report for all 371 crawled/sitemapped live URLs, url-coverage simulation suite.

## v1.2.2 — 2026-09-13

- Theme: hk9_theme_updater() helper for forced update checks (Dashboard → Updates picks releases up automatically; this is for WP-CLI/manual checks).
- Packages rebuilt from the 1.2.1 release state; no functional changes to the site.

## v1.2.1 — 2026-09-13

- Gravity Forms: the Contact and Initial Application Inquiry forms are created automatically in Gravity Forms (fields, notifications, confirmations) and selected by default; Gravity output styled to match the reference form; Settings → Forms 'Create/Update' button and wp hk9 gravity commands.
- Footer developer credit setting (Built with ♥ for our veterans by Cosmick Media).
- Default page template: hideable hero, optional CTA band, ten Heartland block patterns and block styles for building new pages.
- Settings completeness: TikTok, search placeholder, helpful-links menu location, footer column notes.
- Importer: pre-flight warns about the Classic Editor plugin; memory raised for REST steps.
- Docs: live-site readiness check, post-launch update workflow.

## v1.2.0 — 2026-09-13

First production release of the Heartland Canines for Veterans theme and companion plugin.

- Theme: Bootstrap 5.3.8, self-hosted Fraunces/Inter, full template hierarchy, 19 page templates, block patterns, GitHub self-updates.
- Plugin: content types, page-section fields with revisions/preview, Heartland Settings, built-in / Gravity Forms / shortcode form providers, legacy redirects, existing-site migration importer (adoption mode + content-only payload), GitHub self-updates.

