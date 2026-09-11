# Lighthouse (mobile, simulate throttling, median of 3)

Base: http://localhost:8093 · 2026-09-11T17:39:52.761Z · Lighthouse 12.8.2 · HeadlessChrome/152.0.0.0

Settings: formFactor=mobile · screen 412×823 @ 1.75 (mobile=true) · throttlingMethod=simulate (rtt 150 ms, throughput 1638.4 Kbps, cpuSlowdown 4×) · categories performance, accessibility, best-practices, seo

| URL | Performance | Accessibility | Best practices | SEO | FCP s | LCP s | TBT ms | CLS | SI s | Per-run scores |
|---|---|---|---|---|---|---|---|---|---|---|
| / | **84** | 100 | 100 | 100 | 2.11 | 4.21 | 0 | 0.003 | 2.11 | performance: 84/84/84 · accessibility: 100/100/100 · best-practices: 100/100/100 · seo: 100/100/100 |
| /about/ | **87** | 100 | 100 | 100 | 2.26 | 3.76 | 0 | 0.027 | 2.26 | performance: 87/87/85 · accessibility: 100/100/100 · best-practices: 100/100/100 · seo: 100/100/100 |
| /program/ | **87** | 100 | 100 | 100 | 2.26 | 3.76 | 0 | 0.003 | 2.26 | performance: 87/87/87 · accessibility: 100/100/100 · best-practices: 100/100/100 · seo: 100/100/100 |
| /contact/ | **91** | 100 | 100 | 100 | 2.26 | 3.16 | 0 | 0.002 | 2.26 | performance: 90/91/91 · accessibility: 100/100/100 · best-practices: 100/100/100 · seo: 100/100/100 |
| /news/ | **90** | 100 | 100 | 100 | 2.26 | 3.30 | 0 | 0.003 | 2.26 | performance: 90/90/90 · accessibility: 100/100/100 · best-practices: 100/100/100 · seo: 100/100/100 |
| /fixture-rich-blocks/ | **87** | 100 | 96 | 69 | 2.26 | 3.61 | 0 | 0.050 | 2.26 | performance: 86/88/87 · accessibility: 100/100/100 · best-practices: 96/96/96 · seo: 69/69/69 |
| /barkode/hk923-005/ | **87** | 100 | 100 | 69 | 2.26 | 3.76 | 0 | 0.000 | 2.26 | performance: 87/87/87 · accessibility: 100/100/100 · best-practices: 100/100/100 · seo: 69/69/69 |

Medians are per-metric medians over the 3 runs (so a row's metrics may come from different runs). Raw JSON per run is alongside this file.

## Diagnostic runs (Performance < 90)

### / — Performance 84 in the diagnostic run (home-diagnostic.report.html)

Metrics: FCP 2.26 s · LCP 4.21 s (main#main > section.hk9-hero > div.hk9-hero__bg > img.attachment-hk9-hero <img width="1024" height="1024" src="/wp-content/uploads/2026/09/hero-home-768x768.jpg" class="attachment-hk9-hero size-hk9-hero" alt="…" decoding="async" loadi — phases: TTFB 453 ms, Load Delay 2218 ms, Load Time 121 ms, Render Delay 1417 ms) · TBT 0 ms · CLS 0.003 · SI 2.26 s

Top opportunities (estimated savings):

- **Eliminate render-blocking resources** — Est savings of 1,460 ms: /wp-content/themes/heartland-k9s/assets/dist/theme.css?ver=1789146103
- **Serve images in next-gen formats** — Est savings of 128 KiB (128 KiB): /wp-content/uploads/2023/05/Concept-1-rocker-outlined-2-263x300.png, /wp-content/uploads/2026/09/barkode-768x768.jpg, /wp-content/uploads/2026/09/hero-home-768x768.jpg
- **Properly size images** — Est savings of 84 KiB (84 KiB): /wp-content/uploads/2023/05/Concept-1-rocker-outlined-2-263x300.png, /wp-content/uploads/2026/09/barkode-768x768.jpg
- **Reduce unused CSS** — Est savings of 23 KiB (23 KiB): /wp-content/themes/heartland-k9s/assets/dist/theme.css?ver=1789146103

Diagnostics scoring < 0.9 / failing:

- **Network dependency tree** — score 0: 

### /about/ — Performance 87 in the diagnostic run (about-diagnostic.report.html)

Metrics: FCP 2.26 s · LCP 3.76 s (section#hk9-legacy > div.hk9-overlap__card > div.hk9-overlap__media > img.attachment-large <img width="1024" height="1024" src="/wp-content/uploads/2026/09/about-dog-768x768.jpg" class="attachment-large size-large" alt="…" decoding="async" loading="ea — phases: TTFB 453 ms, Load Delay 1852 ms, Load Time 134 ms, Render Delay 1321 ms) · TBT 0 ms · CLS 0.027 · SI 2.26 s

Top opportunities (estimated savings):

- **Eliminate render-blocking resources** — Est savings of 1,620 ms: /wp-content/themes/heartland-k9s/assets/dist/theme.css?ver=1789146103
- **Serve images in next-gen formats** — Est savings of 83 KiB (83 KiB): /wp-content/uploads/2023/05/Concept-1-rocker-outlined-2-263x300.png, /wp-content/uploads/2026/09/about-dog-768x768.jpg
- **Properly size images** — Est savings of 83 KiB (83 KiB): /wp-content/uploads/2023/05/Concept-1-rocker-outlined-2-263x300.png, /wp-content/uploads/2026/09/about-dog-768x768.jpg
- **Reduce unused CSS** — Est savings of 24 KiB (24 KiB): /wp-content/themes/heartland-k9s/assets/dist/theme.css?ver=1789146103

Diagnostics scoring < 0.9 / failing:

- **Network dependency tree** — score 0: 

### /program/ — Performance 87 in the diagnostic run (program-diagnostic.report.html)

Metrics: FCP 2.26 s · LCP 3.76 s (main#main > section.hk9-hero > div.hk9-hero__bg > img.attachment-hk9-hero <img width="1024" height="1024" src="/wp-content/uploads/2026/09/training-768x768.jpg" class="attachment-hk9-hero size-hk9-hero" alt="…" decoding="async" loadin — phases: TTFB 452 ms, Load Delay 1893 ms, Load Time 111 ms, Render Delay 1299 ms) · TBT 0 ms · CLS 0.003 · SI 2.26 s

Top opportunities (estimated savings):

- **Eliminate render-blocking resources** — Est savings of 1,470 ms: /wp-content/themes/heartland-k9s/assets/dist/theme.css?ver=1789146103
- **Serve images in next-gen formats** — Est savings of 89 KiB (89 KiB): /wp-content/uploads/2023/05/Concept-1-rocker-outlined-2-263x300.png, /wp-content/uploads/2026/09/training-768x768.jpg
- **Properly size images** — Est savings of 52 KiB (52 KiB): /wp-content/uploads/2023/05/Concept-1-rocker-outlined-2-263x300.png
- **Reduce unused CSS** — Est savings of 24 KiB (24 KiB): /wp-content/themes/heartland-k9s/assets/dist/theme.css?ver=1789146103

Diagnostics scoring < 0.9 / failing:

- **Network dependency tree** — score 0: 

### /fixture-rich-blocks/ — Performance 86 in the diagnostic run (fixture-rich-blocks-diagnostic.report.html)

Metrics: FCP 2.55 s · LCP 3.60 s (article#post-1811 > div.hk9-overlap > figure.hk9-post__featured > img.attachment-hk9-hero <img width="1600" height="1000" src="/wp-content/uploads/2026/09/hk9-fixture-4-768x480.jpg" class="attachment-hk9-hero size-hk9-hero" alt="…" decoding="async" l — phases: TTFB 451 ms, Load Delay 1742 ms, Load Time 42 ms, Render Delay 1369 ms) · TBT 0 ms · CLS 0.050 · SI 2.55 s

Top opportunities (estimated savings):

- **Eliminate render-blocking resources** — Est savings of 1,470 ms: /wp-content/themes/heartland-k9s/assets/dist/theme.css?ver=1789146103
- **Properly size images** — Est savings of 52 KiB (52 KiB): /wp-content/uploads/2023/05/Concept-1-rocker-outlined-2-263x300.png
- **Serve images in next-gen formats** — Est savings of 49 KiB (49 KiB): /wp-content/uploads/2023/05/Concept-1-rocker-outlined-2-263x300.png
- **Reduce unused CSS** — Est savings of 22 KiB (22 KiB): /wp-content/themes/heartland-k9s/assets/dist/theme.css?ver=1789146103

Diagnostics scoring < 0.9 / failing:

- **Network dependency tree** — score 0: 

### /barkode/hk923-005/ — Performance 87: HTML diagnostic not written (private/registry URL; its report would embed the page). The opportunities match the other pages (see the raw, scrubbed run JSON).

