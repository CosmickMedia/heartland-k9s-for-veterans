# Cross-engine rendering check

Captured 2026-09-11 with `node tools/screenshot.mjs --engines=chromium,webkit,firefox --widths=390,1440 --routes=/,/program/,/contact/,/news/ --out=docs/reports/screenshots/cross-engine` against http://localhost:8093 (local blog fixtures present, so `/news/` shows the 14 fixture posts). Side-by-side triptychs (chromium | webkit | firefox, composed with sharp) are in `docs/reports/cross-engine/`; raw captures per engine in `docs/reports/screenshots/cross-engine/<engine>/`.

## Versions

| Component | Version |
|---|---|
| Playwright (`npx playwright --version`) | 1.63.0 |
| Chromium (`browser.version()`) | 153.0.8010.12 — `HeadlessChrome/153.0.8010.12` (playwright build chromium-1243) |
| WebKit (`browser.version()`) | 26.6 — `AppleWebKit/605.1.15 … Version/26.6 Safari/605.1.15` (playwright build webkit-2359) |
| Firefox (`browser.version()`) | 155.0 — `Gecko/20100101 Firefox/155.0` (playwright build firefox-1543) |
| Node / host | v22.18.0 · macOS 26.6.2 · deviceScaleFactor 1 |

## Full-page heights (px)

| Route | Width | Chromium | WebKit | Firefox | Δ |
|---|---|---|---|---|---|
| `/` | 390 | 6514 | 6513 | 6513 | 1 |
| `/` | 1440 | 3706 | 3706 | 3706 | 0 |
| `/program/` | 390 | 5833 | 5833 | 5833 | 0 |
| `/program/` | 1440 | 3832 | 3832 | 3832 | 0 |
| `/contact/` | 390 | 3441 | 3441 | 3441 | 0 |
| `/contact/` | 1440 | 1773 | 1773 | 1773 | 0 |
| `/news/` | 390 | 7944 | 7938 | 7938 | 6 |
| `/news/` | 1440 | 4815 | 4813 | 4813 | 2 |

## Triptychs

| File | What it shows |
|---|---|
| `home-{390,1440}-viewport.png`, `home-{390,1440}-full.png` | Home hero, header, CTAs, full page |
| `program-…`, `contact-…`, `news-…` (same pattern) | Program, Contact (form), News (fixture cards + pagination) |
| `detail-home-1440-header.png` | 1:1 crop of the header/nav |
| `detail-home-1440-hero-cta.png` | 1:1 crop of the hero heading, lede and CTA buttons |
| `detail-home-1440-badge.png`, `detail-home-1440-mid.png` | 1:1 crops of the two regions the row-diff flagged as engine-specific (text only) |
| `detail-contact-1440-form.png`, `detail-contact-1440-controls.png`, `detail-contact-1440-grip.png` | Contact form at page scale, 1:1 (inputs, select, textarea, submit) and the textarea resize grip at 4× |
| `detail-news-390-card.png`, `detail-news-390-bottom.png` | First news cards and the footer at 390 |

## Findings

Method: visual inspection of every triptych plus a per-row mean-absolute-difference pass over the full-page captures (alpha stripped — WebKit/Firefox write RGBA PNGs, Chromium RGB; sampled flat-colour pixels are byte-identical in all three: page `251,250,249`, navy `28,47,74`).

1. **Fonts — no difference.** Fraunces (display) and Inter (body/UI) are self-hosted and load in all three engines; no fallback font appears anywhere. Every row the diff flags is a line of text; zooming in (`detail-home-1440-hero-cta.png`, `detail-home-1440-badge.png`, `detail-home-1440-mid.png`) shows only rasteriser differences (WebKit's CoreText strokes read very slightly heavier, Firefox slightly lighter). No glyph, weight, size, wrap or line-break differs.
2. **Form controls — consistent.** On `/contact/` the text inputs, email input, select and textarea are styled identically (same background `#f8f7f5`-ish fill, 1px border, radius, padding, placeholder colour). The select is Bootstrap 5's `.form-select` (`appearance:none` + a data-URI SVG chevron), so no native arrow shows in any engine. The only native remnant is the textarea resize grip in the bottom-right corner, whose glyph is engine-drawn (`detail-contact-1440-grip.png`, 4× zoom: two thin diagonal strokes in Chromium, two heavier grey strokes in WebKit, a four-stroke hatched triangle in Firefox) — cosmetic. The submit button is identical in all three.
3. **Hero crop — identical.** The hero photograph's `object-fit`/`object-position` crop is pixel-for-pixel the same in all three engines at both 390 (subject centred, the veteran + dog visible under the heading) and 1440 (`home-*-viewport.png`); the dark overlay gradient and the "IRS-Recognized 501(c)(3) Nonprofit" pill are identical.
4. **Layout — identical.** Header, nav order, Donate button, mobile toggle, card grids, footer columns and pagination all match. The only geometric delta is on `/news/` (6px at 390, 2px at 1440) and `/` at 390 (1px): Chromium rounds the fractional line-height of the 4-line card excerpt up by 1px per card (`detail-news-390-card.png`: the date row sits at y=311 in Chromium vs y=310 in WebKit/Firefox), which accumulates down the page (`detail-news-390-bottom.png`: footer content identical, offset 6px). Sub-pixel rounding, no visible effect.
5. **Icons / emoji.** Lucide SVG icons (phone, mail, pin, calendar, arrow, heart in "Built with ♥") render identically — no engine falls back to an emoji font.
6. **Nothing to fix.** No engine-specific bug, missing font, unstyled control, overflow or clipped hero was found in any of the 24 captures.
