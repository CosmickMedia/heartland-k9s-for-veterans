# Lighthouse (mobile, simulate throttling, median of 3)

Base: http://localhost:8093 · 2026-09-13T14:48:20.608Z · Lighthouse 12.6.1 · HeadlessChrome/152.0.0.0

Settings: formFactor=mobile · screen 412×823 @ 1.75 (mobile=true) · throttlingMethod=simulate (rtt 150 ms, throughput 1638.4 Kbps, cpuSlowdown 4×) · categories performance, accessibility, best-practices, seo

| URL | Performance | Accessibility | Best practices | SEO | FCP s | LCP s | TBT ms | CLS | SI s | Per-run scores |
|---|---|---|---|---|---|---|---|---|---|---|
| / | **94** | 100 | 100 | 100 | 1.36 | 3.08 | 0 | 0.000 | 1.36 | performance: 94/94/93 · accessibility: 100/100/100 · best-practices: 100/100/100 · seo: 100/100/100 |
| /about/ | **96** | 100 | 100 | 100 | 1.36 | 2.64 | 0 | 0.000 | 1.36 | performance: 96/97/96 · accessibility: 100/100/100 · best-practices: 100/100/100 · seo: 100/100/100 |
| /program/ | **96** | 100 | 100 | 100 | 1.36 | 2.78 | 0 | 0.000 | 1.36 | performance: 96/95/96 · accessibility: 100/100/100 · best-practices: 100/100/100 · seo: 100/100/100 |
| /contact/ | **93** | 100 | 100 | 100 | 1.50 | 3.16 | 0 | 0.000 | 1.50 | performance: 93/93/93 · accessibility: 100/100/100 · best-practices: 100/100/100 · seo: 100/100/100 |
| /news/ | **99** | 100 | 100 | 100 | 1.36 | 2.18 | 0 | 0.000 | 1.36 | performance: 99/99/99 · accessibility: 100/100/100 · best-practices: 100/100/100 · seo: 100/100/100 |
| /events/ | **98** | 100 | 100 | 100 | 1.36 | 2.41 | 0 | 0.000 | 1.36 | performance: 98/98/98 · accessibility: 100/100/100 · best-practices: 100/100/100 · seo: 100/100/100 |
| /meet-the-team/ | **95** | 100 | 100 | 100 | 1.50 | 2.93 | 0 | 0.000 | 1.50 | performance: 95/94/95 · accessibility: 100/100/100 · best-practices: 100/100/100 · seo: 100/100/100 |
| /photos/ | **75** | 100 | 100 | 100 | 1.65 | 8.03 | 0 | 0.000 | 1.65 | performance: 75/78/75 · accessibility: 100/100/100 · best-practices: 100/100/100 · seo: 100/100/100 |

Medians are per-metric medians over the 3 runs (so a row's metrics may come from different runs). Raw JSON per run is alongside this file.

## Diagnostic runs (Performance < 90)

### /photos/ — Performance 75 in the diagnostic run (photos-diagnostic.report.html)

Metrics: FCP 1.65 s · LCP 9.16 s (main#main > section.hk9-hero > div.hk9-hero__content > p.hk9-hero__text <p class="hk9-hero__text hk9-copy"> — phases: TTFB 451 ms, Load Delay 0 ms, Load Time 0 ms, Render Delay 8706 ms) · TBT 0 ms · CLS 0.000 · SI 1.65 s

Top opportunities (estimated savings):

- **Properly size images** — Est savings of 1,802 KiB (1802 KiB): /wp-content/uploads/2023/02/Screenshot_20230206-105720_Messenger.jpg, /wp-content/uploads/2022/12/justin-768x1024.jpg, /wp-content/uploads/2023/04/903A0477-683x1024.jpg
- **Serve images in next-gen formats** — Est savings of 924 KiB (924 KiB): /wp-content/uploads/2023/02/Screenshot_20230206-105720_Messenger.jpg, /wp-content/uploads/2022/12/justin-768x1024.jpg, /wp-content/uploads/2023/04/903A0477-683x1024.jpg
- **Eliminate render-blocking resources** — Est savings of 860 ms: /wp-content/themes/heartland-k9s/assets/dist/records.css?ver=1789309393, /wp-content/themes/heartland-k9s/assets/dist/theme.css?ver=1789309393, /wp-content/themes/heartland-k9s/assets/dist/content.css?ver=1789309393
- **Efficiently encode images** — Est savings of 211 KiB (211 KiB): /wp-content/uploads/2023/02/Screenshot_20230206-105720_Messenger.jpg

Diagnostics scoring < 0.9 / failing:

- **Network dependency tree** — score 0

