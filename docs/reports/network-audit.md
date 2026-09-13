# Network & console audit

Base http://localhost:8093 · 2026-09-13T11:26:58.935Z · chromium 153.0.8010.12 · viewport 1440×900 · each page loaded to networkidle then scrolled to the bottom (lazy assets)

Forbidden host pattern: `replit\.app|heartlandk9s\.org|fonts\.googleapis|fonts\.gstatic|cdnjs|jsdelivr|unpkg|bootstrapcdn|cdn\.` — applied to main-frame requests. Requests made from inside embedded <iframe>s (third-party embeds) are listed separately and do not fail the run.

## / — ✅
- Document status: 200
- Main-frame hosts: localhost:8093 (10)
- Forbidden external hosts (main frame): none
- Failed/4xx+ requests (main frame): none
- Console errors/warnings (first party): none

## /privacy-policy/ — ✅
- Document status: 200
- Main-frame hosts: localhost:8093 (8)
- Forbidden external hosts (main frame): none
- Failed/4xx+ requests (main frame): none
- Console errors/warnings (first party): none

Result: **PASS** — all 2 URLs clean (a URL is flagged for a forbidden host, a failed/4xx+ sub-request or a console error/warning in the main frame).
