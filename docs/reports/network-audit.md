# Network & console audit

Base http://localhost:8093 · 2026-09-11T19:19:43.896Z · chromium 153.0.8010.12 · viewport 1440×900 · each page loaded to networkidle then scrolled to the bottom (lazy assets)

Forbidden host pattern: `replit\.app|heartlandk9s\.org|fonts\.googleapis|fonts\.gstatic|cdnjs|jsdelivr|unpkg|bootstrapcdn|cdn\.` — applied to main-frame requests. Requests made from inside embedded <iframe>s (third-party embeds) are listed separately and do not fail the run.

## / — ✅
- Document status: 200
- Main-frame hosts: localhost:8093 (10)
- Forbidden external hosts (main frame): none
- Failed/4xx+ requests (main frame): none
- Console errors/warnings (first party): none

## /about/ — ✅
- Document status: 200
- Main-frame hosts: localhost:8093 (9)
- Forbidden external hosts (main frame): none
- Failed/4xx+ requests (main frame): none
- Console errors/warnings (first party): none

## /program/ — ✅
- Document status: 200
- Main-frame hosts: localhost:8093 (9)
- Forbidden external hosts (main frame): none
- Failed/4xx+ requests (main frame): none
- Console errors/warnings (first party): none

## /veterans/ — ✅
- Document status: 200
- Main-frame hosts: localhost:8093 (8)
- Forbidden external hosts (main frame): none
- Failed/4xx+ requests (main frame): none
- Console errors/warnings (first party): none

## /get-involved/ — ✅
- Document status: 200
- Main-frame hosts: localhost:8093 (22)
- Forbidden external hosts (main frame): none
- Failed/4xx+ requests (main frame): none
- Console errors/warnings (first party): none

## /barkode/ — ✅
- Document status: 200
- Main-frame hosts: localhost:8093 (9)
- Forbidden external hosts (main frame): none
- Failed/4xx+ requests (main frame): none
- Console errors/warnings (first party): none

## /stories/ — ✅
- Document status: 200
- Main-frame hosts: localhost:8093 (11)
- Forbidden external hosts (main frame): none
- Failed/4xx+ requests (main frame): none
- Console errors/warnings (first party): none

## /contact/ — ✅
- Document status: 200
- Main-frame hosts: localhost:8093 (11)
- Forbidden external hosts (main frame): none
- Failed/4xx+ requests (main frame): none
- Console errors/warnings (first party): none

## /news/ — ✅
- Document status: 200
- Main-frame hosts: localhost:8093 (9)
- Forbidden external hosts (main frame): none
- Failed/4xx+ requests (main frame): none
- Console errors/warnings (first party): none

## /donate/ — ✅
- Document status: 200
- Main-frame hosts: localhost:8093 (10)
- Forbidden external hosts (main frame): none
- Failed/4xx+ requests (main frame): none
- Console errors/warnings (first party): none

## /events/ — ✅
- Document status: 200
- Main-frame hosts: localhost:8093 (9)
- Forbidden external hosts (main frame): none
- Failed/4xx+ requests (main frame): none
- Console errors/warnings (first party): none

## /campaigns/ — ✅
- Document status: 200
- Main-frame hosts: localhost:8093 (15)
- Forbidden external hosts (main frame): none
- Failed/4xx+ requests (main frame): none
- Console errors/warnings (first party): none

## /meet-the-team/ — ✅
- Document status: 200
- Main-frame hosts: localhost:8093 (20)
- Forbidden external hosts (main frame): none
- Failed/4xx+ requests (main frame): none
- Console errors/warnings (first party): none

## /photos/ — ✅
- Document status: 200
- Main-frame hosts: localhost:8093 (81)
- Forbidden external hosts (main frame): none
- Failed/4xx+ requests (main frame): none
- Console errors/warnings (first party): none

## /back-the-pack/ — ✅
- Document status: 200
- Main-frame hosts: localhost:8093 (21)
- Forbidden external hosts (main frame): none
- Failed/4xx+ requests (main frame): none
- Console errors/warnings (first party): none

## /5-questions/ — ✅
- Document status: 200
- Main-frame hosts: localhost:8093 (10)
- Forbidden external hosts (main frame): none
- Failed/4xx+ requests (main frame): none
- Console errors/warnings (first party): none

Result: **PASS** — all 16 URLs clean (a URL is flagged for a forbidden host, a failed/4xx+ sub-request or a console error/warning in the main frame).
