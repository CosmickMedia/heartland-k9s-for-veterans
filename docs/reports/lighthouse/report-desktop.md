# Lighthouse (desktop preset, simulate throttling, median of 1)

Base: http://localhost:8093 · 2026-09-11T17:25:23.656Z · Lighthouse 12.8.2 · HeadlessChrome/152.0.0.0

Settings: formFactor=desktop · screen 1350×940 @ 1 (mobile=false) · throttlingMethod=simulate (rtt 40 ms, throughput 10240 Kbps, cpuSlowdown 1×) · categories performance, accessibility, best-practices, seo

| URL | Performance | Accessibility | Best practices | SEO | FCP s | LCP s | TBT ms | CLS | SI s | Per-run scores |
|---|---|---|---|---|---|---|---|---|---|---|
| / | **99** | 100 | 100 | 100 | 0.49 | 0.89 | 0 | 0.003 | 0.49 | performance: 99 · accessibility: 100 · best-practices: 100 · seo: 100 |
| /about/ | **100** | 100 | 100 | 100 | 0.49 | 0.73 | 0 | 0.004 | 0.49 | performance: 100 · accessibility: 100 · best-practices: 100 · seo: 100 |

Medians are per-metric medians over the 1 runs (so a row's metrics may come from different runs). Raw JSON per run is alongside this file.

