# Visual comparison — reference vs WordPress theme (wave 3)

Scope: the eight reference routes (`/`, `/about/`, `/program/`, `/veterans/`, `/get-involved/`, `/barkode/`, `/stories/`, `/contact/`) rendered by `theme/heartland-k9s` against the **real imported content** (30 pages, all records, 250 media, menus, settings produced by the importer from `payload/`). Reference = the Vite/Tailwind build captured in `discovery/ref/` and measured in `discovery/measure/results.json` (+ `interact.json`).

## 1. Method

1. **Capture** — `node tools/screenshot.mjs --widths=390,1440 --menu --out=docs/reports/screenshots/wp` (full page + viewport, Chromium, animations disabled, fonts awaited) and `--widths=768,1023,1024,1920 --routes=/` for the breakpoint checks. Output: `docs/reports/screenshots/wp/chromium/<route>-<width>[-viewport].png`, `home-390-menu-open.png`.
2. **Pixel diff** — `node tools/compare.mjs` → `docs/reports/diffs/report.md` + `<route>-<width>-diff.png` (top-aligned, threshold 0.12). Because every route is taller than the reference for content reasons (section 4), a second, *region-aligned* diff was computed for the parts whose content is identical: the header band (0–81 px), the hero (reference hero height) and the footer bottom bar (last 113 px, bottom-aligned). See section 3.
3. **Side-by-side composites** — reference (left) and WordPress (right) scaled to the same width, inspected route by route; a representative set is committed under `docs/reports/screenshots/side-by-side/`.
4. **Measurement** — a Playwright script ran the same kind of `getBoundingClientRect()` / `getComputedStyle()` probes as `discovery/measure/page-measure.js` against the theme (header, hero, every `<section>`, headings/paragraphs, cards, grids, testimonial, BarKode tile, buttons, badges, overlap cards, timeline, footer, form controls, image `loading`/`fetchpriority`/`sizes`, horizontal overflow, console errors, external requests) at 1440 and 390 for all routes and at 1024/1023/768/1920 for the home page, and compared value by value with `results.json`. Hover/focus/menu states were checked against `discovery/measure/interact.json`.
5. **Fix → rebuild → re-capture** (`npm run build:css && npm run build:js`) until only content-driven differences remained.
6. **Health** — axe-core (`tools/axe.mjs`, all 16 URLs × 390/1440), `tools/network-audit.mjs` (16 URLs: hosts, failed requests, console), `wp-content/debug.log` inspected after the runs (WP_DEBUG + WP_DEBUG_LOG on).

## 2. What changed in this wave (theme only)

| Area | Before | After (matches reference) |
|---|---|---|
| Veterans hero stars pattern (`_layout.scss`) | White stars at 10 % — clearly visible | Reference draws the pattern with `currentColor`, which on the stars band is the page **foreground** (#151c28): near-invisible dark stars on navy. Patterns are now CSS masks coloured by token (`currentColor` for the grid, `--hk9-fg` for stars). |
| BarKode tile caption (`_sections.scss`) | Inter 24/32 | Fraunces 24/32 700, letter-spacing −0.6 px, mb 8 (reference `h3.text-2xl.font-serif`). |
| Footer tagline (`_footer.scss`, `_tokens.scss`, `inc/options.php`) | Crimson on navy (2.26:1), 16 px above | `pt-2` wrapper spacing reproduced (24 px above). Colour is now the accessible tint `--hk9-secondary-on-primary` (#dd7788, 4.55:1) — see section 6; recomputed server-side when the palette settings change. |
| Footer social links (`footer.php`, `inc/icons.php`) | Bare text label ("Facebook") because lucide 1.44 has no brand glyphs; an X link would have rendered the *close* icon | Brand glyphs (facebook, instagram, youtube, linkedin — the last lucide shapes that shipped them, ISC) added as theme-level symbols; `aria-label`ed icon links in a 32 px row; X falls back to a text label. Legal menu got its own class so the bottom bar keeps the reference height (49 px). |
| CTA band buttons (`cta_band.php`, `_pages-reference.scss`) | Every primary button carried a 20 px arrow; single buttons stretched full-width below 640 px | Only the navy band's primary button has the (16 px) arrow, exactly as the reference (program "Review 5 Questions & Apply" 285.08 px wide); about/barkode/stories primaries are plain (about "Make a Donation" 177.98 px). Only two-button rows stretch below 640 px (`--multi`), a single button stays inline and centred (veterans "Read ADA FAQs" 139.44 px at 390, program 285 px). |
| Trailing button icons (`hk9_button()`, `_buttons.scss`) | gap-2 only | `.hk9-btn__icon` adds the reference `ml-2` → "Read More Stories" 188.86 px, "Start Application" 209.59 px (were 8 px narrower). |
| Home testimonial (`testimonial.php`) | Read story meta without the `hk9_` prefix (source `story` never overrode the manual copy) | Uses `hk9_pages_text()` → `hk9_quote` / `hk9_veteran_name` / `hk9_branch` / `hk9_canine_name` (plugin accessor when active). |
| Images (`hk9_image()`, `hk9_logo_img()`, `legacy.php`, `featured.php`, cards) | Legacy/featured images `loading=eager` without `fetchpriority`; header logo got core's automatic `fetchpriority=high`; `sizes` on team/story/partner images off by up to 50 % | Hero / legacy / featured (LCP candidates) `eager` + `fetchpriority=high`; header logo eager only; everything below the fold lazy. `sizes` measured per grid (team 496/320 px, story 496 px, partner logos `calc(50vw − 56px)` / 160 px). Core's `sizes="auto, …"` prefix is dropped when the theme passes an explicit `sizes` (see section 5). |
| Home hero entrance animation (`_hero.scss`) | Slide + fade from opacity 0 (`.7s cubic-bezier`) | Reference `animate-in slide-in-from-bottom-6 duration-700 delay-150/300/500 fill-mode-both` is a pure slide with the default `ease` timing (no `fade-in`); the theme now slides only, so the hero copy is readable from the first frame and axe no longer catches a transient contrast failure on the buttons. |
| Gallery captions (`_pages-migrated.scss`) | Core `overflow:auto` caption became an unfocusable scroll region on narrow tiles (axe serious on `/photos/` @390) | Clamped to 4 lines (`overflow:hidden`), full text stays in the DOM. |
| Partner logo tiles (`hk9_pages_partner_logo_item()`) | `alt` + identical sr-only name (axe image-redundant-alt) | `alt` only. |
| `theme/heartland-k9s/screenshot.png` | missing | 1200×900 capture of the imported home page (Playwright, 1200×900 viewport, logged out, palette PNG ≈ 230 kB). |

## 3. compare.mjs numbers

`docs/reports/diffs/report.md` (generated after the final rebuild):

| Route | Width | Ref height | WP height | Δ height | % differing (full canvas) | % differing (overlap) |
|---|---|---|---|---|---|---|
| home | 390 | 5421 | 6514 | +1093 | 42.23 % | 30.61 % |
| home | 1440 | 3521 | 3706 | +185 | 16.31 % | 11.92 % |
| about | 390 | 4096 | 4716 | +620 | 23.59 % | 12.04 % |
| about | 1440 | 2358 | 2595 | +237 | 20.32 % | 12.31 % |
| program | 390 | 4581 | 5833 | +1252 | 40.85 % | 24.73 % |
| program | 1440 | 3153 | 3832 | +679 | 30.90 % | 18.61 % |
| veterans | 390 | 4280 | 5543 | +1263 | 43.61 % | 27.03 % |
| veterans | 1440 | 2670 | 3152 | +482 | 35.55 % | 23.92 % |
| get-involved | 390 | 3728 | 5392 | +1664 | 53.12 % | 34.43 % |
| get-involved | 1440 | 2042 | 2706 | +664 | 41.55 % | 26.10 % |
| barkode | 390 | 4641 | 5595 | +954 | 33.75 % | 20.16 % |
| barkode | 1440 | 2951 | 3251 | +300 | 19.41 % | 11.22 % |
| stories | 390 | 3818 | 5401 | +1583 | 64.15 % | 49.46 % |
| stories | 1440 | 2238 | 3227 | +989 | 46.06 % | 39.34 % |
| contact | 390 | 2891 | 3397 | +506 | 23.24 % | 9.82 % |
| contact | 1440 | 1616 | 1729 | +113 | 7.73 % | 1.28 % |

These top-aligned numbers are dominated by the height differences listed in section 4 (longer live copy, five program steps, extra footer links…): as soon as one block is taller everything below it is offset and counts as "different". The **region-aligned** diff of the parts whose content is identical shows the actual rendering fidelity:

| Route | Width | Header (0–81 px) | Hero band | Footer bottom bar |
|---|---|---|---|---|
| home | 1440 / 390 | 0.86 % / 2.36 % | 0.40 % / 0.16 % | 0.36 % / 2.98 % |
| about | 1440 / 390 | 0.86 % / 2.36 % | 2.45 % (overlap card copy) / 0.00 % | 0.36 % / 2.98 % |
| program | 1440 / 390 | 0.86 % / 2.36 % | 0.02 % / 0.01 % | 0.36 % / 2.98 % |
| veterans | 1440 / 390 | 0.86 % / 2.36 % | 0.00 % / 0.07 % | 0.36 % / 2.98 % |
| get-involved | 1440 / 390 | 0.85 % / 2.36 % | 0.03 % / 0.05 % | 0.36 % / 2.98 % |
| barkode | 1440 / 390 | 0.85 % / 2.36 % | 3.43 % / 8.51 % (different eyebrow + paragraph copy) | 0.36 % / 2.98 % |
| stories | 1440 / 390 | 0.85 % / 2.36 % | 5.58 % / 13.75 % (featured card copy/photo) | 0.36 % / 2.98 % |
| contact | 1440 / 390 | 1.00 % / 2.36 % | 0.00 % / 0.00 % | 0.36 % / 2.98 % |

The residual header % is the logo raster (the live 263×300 PNG vs the reference's `heartland-k9s-logo.png`, same artwork) and the active-link colour; the 390 footer-bar residual is the legal "Privacy Policy" link the reference does not have.

## 4. Per-route results (1440 / 390)

Heights are `document.documentElement.scrollHeight`. "Matched" lists values measured identical to `results.json` (±0.5 px unless stated). "Remaining differences" are all content-driven unless marked **deviation**.

| Route | Width | Ref → WP height | Matched (measured) | Remaining differences (reason) |
|---|---|---|---|---|
| **home** | 1440 | 3521 → 3706 | Header 81 (inner 80, logo 56×64 @112,8; wordmark 18/22.5 + 10/15 0.5 px; links 14/500 fg@80 at x 621.69…1143.28 gap 24; divider ml/pl 16; Donate 115.94×38 @1212.06, radius 2). Hero 765 (85vh, min 600), badge 288.47×30 @254.5 white/10 blur, h1 72/90 451.2×180 @316.5 with `<br class="hk9-md-br">` after "Never", p 20/28 500 white/90 672 wide mb 40, CTAs 239.63×56 + 185.86×56 gap 16 (primary crimson / glass white/10 + white/30 border). Mission 467 (py 96, h2 36/45 600 primary mb 32, divider 96×4 @672, p 18/29.25 muted-fg 832 wide). Feature grid 1216 → 3 × 378.66 gap 40, cards p 32 radius 8 border shadow-sm, icon wells 64 (primary/10, secondary/10) with 32 px glyphs, h3 20/28 mb 16, p 16/26 mb 24, links 16/500 crimson gap 4→8 on hover, hover translateY(−4) + shadow-lg. BarKode band 640 py 96 navy + grid pattern, tile 448×448 radius 16 4 px white/10 border shadow-2xl gradient, caption Fraunces 24/32 −0.6 px, badge 12/700 uppercase 1.2 px crimson, h2 48/60, p 18/29.25 white/80, outline-light 56 px button 178.3 wide. Testimonial 1024 wide radius 24 shadow-sm, media 409.59 (2/5) full height, body 3/5 p 56, glyph 40 crimson@50, quote Fraunces 24/33 mb 32, name 16/700 primary, meta 14/20, ghost button 188.86×38. Footer: py 64, grid 4 × 268 gap 48, logo 70×80, h2 18/28 600 −0.45 px mb 16, links 14/20 white/80 gap 12, description 14/22.75 max 320, tagline Fraunces italic 18/28 24 px below, bottom bar mt 64 pt 32 border white/10 12/16 white/60 (© left, "Built with ♥" right). | Feature cards 402 vs 350 tall (live copy is longer; third card is "A Lifelong Commitment" not "Nationwide Reach"); BarKode copy 6 lines vs 4 and eyebrow "A Service of Heartland K9s" (badge 242 vs 183 wide); testimonial card 404 vs 384 (real Madison Stratton quote); footer 593 vs 480 (10 + 10 editable menu links, Facebook icon, Candid link, legal menu, address row instead of "Serving … nationwide"). Tagline colour **deviation** (section 6). |
| home | 390 | 5421 → 6514 | Header 81, burger 40×40 @334,20; hero 717.39, badge @211.69, h1 36/45 358×90, p 18/28, CTAs 358×56 stacked gap 16; mission 681.5; feature cards 358 wide gap 40; testimonial media 358×256 then body p 40, quote 20/27.5; footer 1-column, cols 358 wide gap 48, brand column logo→description→tagline spacing 16/24. Mobile menu (open): panel 0,80 390×476 bg #fbfaf9 border-b shadow-lg, inner p 24/16 gap 16, links 18/28 500 358×36 at y 104…416 (p 4 8, radius 2), Donate wrap mt 8 pt 16 border-t, button 358×38 full width; body not scroll-locked — all identical to `interact.json`. | BarKode band 1098 vs 631: **deviation** — tile kept visible on mobile (reference collapses it to 0×0, section 7). Feature cards 1344 vs 1292 and testimonial 1005 vs 877 (copy). Footer 1587 vs 1141 (links/social/legal, see above). |
| **about** | 1440 | 2358 → 2595 | Band hero 373 (pt 96 pb 128, h1 60/60, p 20/32.5 white/80 672). Overlap card −64 px, 1024 wide, radius 16, shadow-xl, split 50/50: image 512 wide, body p 48 (eyebrow pill 12/700 secondary/10, h2 30/36 700 fg mb 24, p 16/26 muted-fg). Values: 960 px inner, 3 × 298.66 gap 32, muted cards p 32 radius 8 no border/shadow, 40 px crimson glyph mb 16, h3 20/28 mb 12, p 16/24. CTA band tint: primary/5, border-y, py 80, h2 30/36 fg mb 24, p 18/28 mb 40, buttons 56 px 14/500 — "Make a Donation" 177.98 (no arrow) + "Ways to Volunteer" 187.66 navy outline. | Legacy card 490 vs 438 (longer second paragraph), values cards 352 vs 280 (longer live values copy). |
| about | 390 | 4096 → 4716 | Hero 418, card image 358×358 above body p 40; values 358 wide gap 32; CTA buttons stacked full width 358×56 (two-button row). | Card/values copy lengths (+78 / +96); footer. |
| **program** | 1440 | 3153 → 3832 | Image hero 540 (60vh, min 400) overlay primary/70 + gradient, h1 60/60, p 20/32.5 500 white/90. "How It Works" h2 36/40 mb 16, divider, intro 18/28 max 768. Timeline: 1024 grid 2 × 488 gap 48, centre spine 1 px @719.5, cards p 32 radius 8 shadow-sm with 40 px navy glyph, h3 20/28 mb 12, p 16/24, right column offset 96 px, number badges 32 px crimson hanging ±16 px over the spine at card-top +33. Providers band py 80 muted/50 border-y, 48 px crimson icon, h2 30/36 fg, p 18/28 max 832, navy outline 38 px button. CTA navy py 96, h2 36/40, p 18/28 white/80 mb 40, primary 56 px 14/500 with 16 px arrow → 285.08 wide. | **5 steps vs 4** (1590 vs 1112 section) with the live step copy; providers copy ("Providing a K9" / "K9 Provider Criteria"); CTA copy 2 lines. |
| program | 390 | 4581 → 5833 | Hero 506.39, h1 36/40, timeline single column gap 48 (no spine/badges), CTA button inline 285 wide centred (single button, not stretched). | Five steps + longer copy; footer. |
| **veterans** | 1440 | 2670 → 3152 | Band hero 373 with stars pattern (dark stars @10 %, section 2). Questions card −64 px, 896 wide, radius 16, shadow-xl, p 48; 48 px secondary/10 icon well with 24 px glyph, h2 24/32, rows p 16 muted/50 radius 4 border/50 gap 24 with 20 px navy check + 18/24.75 500 question; footer callout primary/5 radius 8 p 24 mt 40, navy 40 px button 209.59 wide with file icon. Expect: 1024 grid 2 × 480 gap 64, h2 24/32 primary, numbered 32 px steps gap 32, muted card radius 16 p 32 with 48 px icon, h3 24/32, outline 38 px button 175.14. ADA band py 80, h2 30/36, p 18/28 mb 40, outline-light 38 px button 139.44. | Live 5 Questions (5 real questions, 3-line intro, secondary "Read the full 5 Questions" link) → card 939 vs 778; expect copy 768 vs 560. |
| veterans | 390 | 4280 → 5543 | Hero 385.5, card p 32, "Start Application" 242×40 full width of callout, "Read ADA FAQs" 139.44 centred (single button). | Copy lengths; footer. |
| **get-involved** | 1440 | 2042 → 2706 | Hero 373. Ways: 1152 grid 3 × 362.66 gap 32, cards −64 px radius 16 shadow-xl p 32 centred, 64 px icon wells, h3 24/32 mb 16, p 16/24 mb 32, buttons 40 px full-width 14/500 (primary crimson, navy outline ×2). Partners panel 896 wide muted radius 24 border shadow-sm p 64, 48 px crimson heart, h2 30/36, p 18/28 max 672 mb 32, second p 16/24 mb 40, navy 40 px button 211.66. | Cards 418 vs 394 (copy); partner panel 1033 vs 506 because `show_logos` renders the 13 Back-the-Pack partner logos (6-col grid, 24 px gap); copy mentions "Back the Pack". |
| get-involved | 390 | 3728 → 5392 | Cards stacked 358 wide, buttons 292×40; panel p 40; logo grid 2 columns. | Logos, copy; footer. |
| **barkode** | 1440 | 2951 → 3251 | Image hero 630 (70vh, min 500) overlay primary/80, eyebrow pill secondary/20 + secondary/30 border 14/700 uppercase 1.4 px, h1 72/72, p 20/32.5. Story panel: py 80, 896 wide primary/5 radius 16 border primary/10 p 48 centred, 48 px crimson icon, h2 30/36 primary mb 24, p 18/29.25 left-aligned. Protects: py 96 muted/30 border-y, 960 grid 3 × 298.66 gap 32, cards p 32 radius 8 shadow-sm (no lift), bare 40 px navy glyphs mb 24, h3 20/28 mb 12, p 16/24. CTA plain py 80, h2 30/36 fg, p 18/28, 56 px buttons. | Eyebrow has an icon and different text ("A Service of Heartland K9s"), 1-line vs 2-line hero paragraph (content block 16 px higher); story panel 2 long paragraphs; protects copy; CTA has two buttons (+ "Ask about BarKode") and longer copy. |
| barkode | 390 | 4641 → 5595 | Hero 590.8, h1 36/40 2 lines, cards 358 wide gap 32, CTA buttons stacked full width (two-button row). | Copy; footer. |
| **stories** | 1440 | 2238 → 3227 | Hero 340.5 (single-line paragraph). Featured card −64 px 1024 wide radius 16 shadow-xl split 50/50, image 511 wide, body p 48 on muted/30, 48 px glyph @40 %, quote Fraunces 24/33 mb 32, name 18/28 700 primary, meta 14/20. CTA plain py 96 (mt 48 rhythm) h2 30/36, p 18/28, 56 px primary. | Only one published story: the "More Stories" list renders the empty-state panel (`empty_text`) instead of the two reference placeholder quote cards (deliberately not imported); the **Teams in Training** block (2 team cards, 496 wide gap 32) is live content the reference lacks; CTA has a second "Share Your Story" button. |
| stories | 390 | 3818 → 5401 | Featured image 356×320 then body p 40; team cards 358 wide; CTA stacked. | As above; footer. |
| **contact** | 1440 | 1616 → 1729 | Hero 373. Card −64 px 1024 wide radius 16 shadow-xl: info column 2/5 (409.59) muted p 48 with 40 px primary/10 icon wells (20 px glyphs), h2 24/32 mb 32, rows gap 32, labels 16/24 700 mb 4, values 18/28 muted-fg; form column 3/5 p 48, h2 24/32 mb 24, 2-col grid gap 24 (246.59 each), labels 14/500, inputs 48 px muted/50 border radius 2 pl 12 14 px, select 48 px, textarea 120 px resize-y, submit navy 56 px full width 14/500 radius 2 @951. | 113 px: "Director cell" row (secondary phone) and an **address row** instead of "Service Area — nationwide" (settings-driven); hours row is settings text without weekday label. Footer. |
| contact | 390 | 2891 → 3397 | Stacked card, info p 40 → form p 40, inputs 276 wide 48 px, font-size 16 px on inputs (iOS no-zoom, as reference), submit 276×56. | Extra contact row (+60); footer. |

### Breakpoint checks (home)

| Width | Reference | WordPress | Notes |
|---|---|---|---|
| 1920 | not captured | hero 918 (85vh of 1080), container 1280 → cards 3 × 464, footer 4 × 332, no horizontal overflow | `docs/reports/screenshots/wp/chromium/home-1920.png` |
| 1440 | 765 hero, nav inline, 3 cols, 4 footer cols | identical | |
| 1024 | nav inline at x 285.69…807.28, Donate 876.06; cards 3 × 293.33; footer 4 × 204; testimonial 960 with 384 media | identical (docH 3838 vs 3624 = copy + BarKode text column 494 tall) | `home-1024-viewport.png` |
| 1023 | burger, h1 60 px, cards 3 × 208, footer 2 × 328, BarKode tile hidden | identical except the tile (**deviation**, 1064 vs 566) | `home-1023-viewport.png` |
| 768 | same as 1023 (docH 3984) | identical except the tile; mission 512 as reference; hero CTAs inline | `home-768-viewport.png` (WP capture is 768×1024 so its hero is 870 tall; measured at 900 vh it is 765 like the reference) |
| 767/640/639 | 1 column, footer 1 col | 1 column (`sm` row for CTAs) | measured in `results.json`, matched by the `sm`/`md`/`lg` Bootstrap breakpoints 640/768/1024 |

### Hover / focus / interaction (vs `interact.json`)

Card hover translate −4 px + shadow-lg; card link gap 4→8; nav link → crimson, active crimson; Donate → secondary/90; hero outline → white/20; footer link → white; input focus 1 px navy ring, select focus 2 px ring; menu toggle swaps menu/x icons and does not lock body scroll; hero entrance animation (slide 16/24 px, 700 ms `ease`, delays 0/150/300/500 ms, `both`) only with `prefers-reduced-motion: no-preference`.

## 5. Images

* Hero images: `loading="eager" fetchpriority="high" sizes="100vw"` with the `hk9-hero` srcset (`/`, `/program/`, `/barkode/`); About legacy image and Stories featured image (the LCP element on those routes) likewise; header logo eager without priority; footer logo and everything below the fold `loading="lazy" decoding="async"`.
* `sizes` per placement: BarKode tile `(max-width:767px) calc(100vw − 32px), 448px`; testimonial 410 px; legacy/featured `… (max-width:1087px) calc(50vw − 32px), 512px`; team cards 496/320 px; story cards 496 px; partner logos 160 px; logos 80 px.
* Core's `sizes="auto, …"` (WP 6.7+) is stripped when the theme supplies an explicit `sizes` — `auto` only helps when `sizes` is a guess, and it made `tools/screenshot.mjs` (which flips `loading` to eager before a full-page capture) re-select and re-fetch a candidate mid-capture, leaving blank tiles/logos in the archived screenshots. Block-content images keep core's behaviour.

## 6. Accessibility contrast (deliberate deviation)

* Reference footer tagline: Fraunces italic 18 px, `text-secondary` #b82e45 on `bg-primary` #1c2f4a = **2.26:1** (fails WCAG AA 4.5:1; 18 px regular is not "large text").
* Fix: keep hue/saturation (hsl 350°, 60 %) and raise lightness until the ratio passes → hsl(350 60 % 66.6 %) = **#dd7788 → 4.55:1**. Compiled as `--hk9-secondary-on-primary`; `hk9_root_css()` recomputes it with `hk9_accessible_tint()` whenever the primary/secondary colour settings change, so custom palettes stay compliant.
* Other text on navy checked: white/80 = 9.20:1, white/60 (bottom bar 12 px) = 5.88:1, crimson icons are decorative.
* axe-core (`tools/axe.mjs`, wcag2a/aa, wcag21a/aa, wcag22aa, best-practice): 0 critical / 0 serious / 0 moderate / 0 minor on the eight routes at 390 and 1440 after the fixes. (Before the hero animation change the tool, which scans right after `networkidle`, reported a transient `color-contrast` finding on the home hero buttons while they were still fading in at opacity 0; the reference never fades, and neither does the theme now.)

## 7. Deliberate deviations (keep)

1. **BarKode tile visible on mobile.** The reference's `w-full` tile inside an `items-center` flex column collapses to 0×0 below 1024 px, hiding the photo and caption. The theme keeps the 358 px square tile (home @390: section 1098 vs 631 px).
2. **Footer tagline tint** #dd7788 instead of #b82e45 (section 6).
3. **Stars pattern drawn in the foreground colour** — this *matches* the reference rendering (near-invisible dark stars); noted here because the token name (`--hk9-fg`) is deliberate, not an oversight.
4. **Social icons** in the footer brand column (settings-driven; the reference has no social links). Brand glyphs come from the last lucide release that shipped them (ISC); X has no glyph and renders as a text label.
5. **Mobile timeline order 1…N** (reference DOM order reads 1, 3, 2, 4), sr-only "Ways to get involved" heading, "More Stories"/"Teams in Training" section headers, "Read the full story" link on the featured card, contact rows as tel:/mailto: links — inherited from wave 2 (T1) and kept.
6. **Single-button CTA rows stay inline on mobile** (as the reference) while two-button rows stretch; the theme adds the `--multi` modifier automatically.
7. **`sizes="auto"` stripped** for theme-rendered images with explicit `sizes` (section 5).
8. **Gallery captions clamped to 4 lines** instead of core's scrollable overlay (axe serious → none); full caption text remains in the DOM and in the lightbox.
9. **Reference placeholder testimonials/quotes are not reproduced** — only verified live content is imported (stories list shows the empty state until more stories are published).

## 8. Content-driven differences (documented, not "fixed")

* More footer links (editable `footer_quick` / `footer_involved` / `legal` menus), Facebook icon and Candid/GuideStar link → footer 593 px (1440) / 1587 px (390) vs 480 / 1141.
* Five program steps vs four; live step, providers ("Providing a K9"), values, BarKode, 5-Questions and expect copy lengths; live testimonial/quote lengths; "A Service of Heartland K9s" eyebrow with icon on the BarKode hero; contact address/hours rows instead of nationwide/Mon–Fri; Teams block and empty stories list on `/stories/`; provider band copy; second CTA buttons where the live pages have them ("Ask about BarKode", "Share Your Story").

## 9. Health checks (final state)

* `wp-content/debug.log`: 0 bytes after all captures/measurements (WP_DEBUG + WP_DEBUG_LOG on).
* Console errors: 0 on all 20 route × width combinations measured; `tools/network-audit.mjs`: 16 URLs, only `localhost:8093` hosts, no failed requests, no console errors/warnings.
* Horizontal overflow: none at 390/768/1023/1024/1440/1920.
* Cross-engine: the veterans stars/BarKode grid masks, tile, and footer render identically in Chromium, WebKit and Firefox (Playwright).

## 10. Screenshots

* Current WordPress captures: `docs/reports/screenshots/wp/chromium/*.png` (all routes at 390/1440 full page + viewport, `home-390-menu-open.png`, `home-768/1023/1024/1920*.png`).
* Reference: `discovery/ref/screenshots/*.png`.
* Pixel diffs: `docs/reports/diffs/*-diff.png` + `docs/reports/diffs/report.md`.
* Side-by-side composites (reference left, WordPress right): `docs/reports/screenshots/side-by-side/` — `home-1440.png`, `home-390.png`, `home-390-menu-open.png`, `home-768-viewport.png`, `home-1024-viewport.png`, `about-1440.png`, `program-1440.png`, `veterans-1440.png`, `get-involved-1440.png`, `barkode-1440.png`, `stories-1440.png`, `contact-1440.png`, `contact-390.png`.
* Theme metadata: `theme/heartland-k9s/screenshot.png` (1200×900).

## 11. Tooling notes

* `tools/screenshot.mjs` sets `img.loading = 'eager'` on every image before the full-page capture; combined with core's `sizes="auto"` this re-fetched srcset candidates during Chromium's full-page capture and produced blank lazy images in the archived PNGs (home BarKode tile, partner logos). The theme no longer emits `auto` for its own images (section 5), so the captures are stable; block-content galleries (`/photos/`) can still show the effect if captured with that script — awaiting `img.decode()` again after the `loading` flip would make the tool robust.
* `tools/axe.mjs` scans immediately after `networkidle`; opacity-based entrance animations would show up as transient contrast findings there (section 6) — the theme's hero animation is slide-only for that reason and to match the reference.
