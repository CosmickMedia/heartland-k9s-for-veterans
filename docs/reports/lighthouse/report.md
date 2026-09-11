# Lighthouse (mobile, simulate throttling, median of 3)

Base: http://localhost:8093 · 2026-09-11T19:31:35.672Z · Lighthouse 12.8.2 · HeadlessChrome/152.0.0.0

Settings: formFactor=mobile · screen 412×823 @ 1.75 (mobile=true) · throttlingMethod=simulate (rtt 150 ms, throughput 1638.4 Kbps, cpuSlowdown 4×) · categories performance, accessibility, best-practices, seo

| URL | Performance | Accessibility | Best practices | SEO | FCP s | LCP s | TBT ms | CLS | SI s | Per-run scores |
|---|---|---|---|---|---|---|---|---|---|---|
| / | **94** | 100 | 100 | 100 | 1.36 | 3.08 | 0 | 0.000 | 1.36 | performance: 94/94/94 · accessibility: 100/100/100 · best-practices: 100/100/100 · seo: 100/100/100 |
| /about/ | **97** | 100 | 100 | 100 | 1.36 | 2.63 | 0 | 0.000 | 1.36 | performance: 97/96/97 · accessibility: 100/100/100 · best-practices: 100/100/100 · seo: 100/100/100 |
| /program/ | **96** | 100 | 100 | 100 | 1.36 | 2.78 | 0 | 0.000 | 1.36 | performance: 96/96/96 · accessibility: 100/100/100 · best-practices: 100/100/100 · seo: 100/100/100 |
| /contact/ | **98** | 100 | 100 | 100 | 1.66 | 2.26 | 0 | 0.000 | 1.66 | performance: 98/98/98 · accessibility: 100/100/100 · best-practices: 100/100/100 · seo: 100/100/100 |
| /news/ | **99** | 100 | 100 | 100 | 1.36 | 2.18 | 0 | 0.000 | 1.36 | performance: 99/99/98 · accessibility: 100/100/100 · best-practices: 100/100/100 · seo: 100/100/100 |

Medians are per-metric medians over the 3 runs (so a row's metrics may come from different runs). Raw JSON per run is alongside this file.

