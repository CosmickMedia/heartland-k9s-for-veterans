# Network & console audit

Base http://localhost:8093 · 2026-09-11T17:10:29.362Z · chromium 153.0.8010.12 · viewport 1440×900 · each page loaded to networkidle then scrolled to the bottom (lazy assets)

Forbidden host pattern: `replit\.app|heartlandk9s\.org|fonts\.googleapis|fonts\.gstatic|cdnjs|jsdelivr|unpkg|bootstrapcdn|cdn\.` — applied to main-frame requests. Requests made from inside embedded <iframe>s (third-party embeds) are listed separately and do not fail the run.

## / — ✅
- Document status: 200
- Main-frame hosts: localhost:8093 (12)
- Forbidden external hosts (main frame): none
- Failed/4xx+ requests (main frame): none
- Console errors/warnings (first party): none

## /about/ — ✅
- Document status: 200
- Main-frame hosts: localhost:8093 (10)
- Forbidden external hosts (main frame): none
- Failed/4xx+ requests (main frame): none
- Console errors/warnings (first party): none

## /program/ — ✅
- Document status: 200
- Main-frame hosts: localhost:8093 (10)
- Forbidden external hosts (main frame): none
- Failed/4xx+ requests (main frame): none
- Console errors/warnings (first party): none

## /veterans/ — ✅
- Document status: 200
- Main-frame hosts: localhost:8093 (9)
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
- Main-frame hosts: localhost:8093 (10)
- Forbidden external hosts (main frame): none
- Failed/4xx+ requests (main frame): none
- Console errors/warnings (first party): none

## /stories/ — ✅
- Document status: 200
- Main-frame hosts: localhost:8093 (12)
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
- Main-frame hosts: localhost:8093 (13)
- Forbidden external hosts (main frame): none
- Failed/4xx+ requests (main frame): none
- Console errors/warnings (first party): none

## /donate/ — ✅
- Document status: 200
- Main-frame hosts: localhost:8093 (11)
- Forbidden external hosts (main frame): none
- Failed/4xx+ requests (main frame): none
- Console errors/warnings (first party): none

## /events/ — ✅
- Document status: 200
- Main-frame hosts: localhost:8093 (10)
- Forbidden external hosts (main frame): none
- Failed/4xx+ requests (main frame): none
- Console errors/warnings (first party): none

## /campaigns/ — ✅
- Document status: 200
- Main-frame hosts: localhost:8093 (16)
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

## /?s=dog — ✅
- Document status: 200
- Main-frame hosts: localhost:8093 (13)
- Forbidden external hosts (main frame): none
- Failed/4xx+ requests (main frame): none
- Console errors/warnings (first party): none

## /nonexistent-page/ — ✅
- Document status: 404
- Main-frame hosts: localhost:8093 (9)
- Forbidden external hosts (main frame): none
- Failed/4xx+ requests (main frame): none
- Console errors/warnings (first party): none

## /fixture-rich-blocks/ — ❌
- Document status: 200
- Main-frame hosts: localhost:8093 (17)
- Forbidden external hosts (main frame): none
- Failed/4xx+ requests (main frame): none
- Console errors/warnings (first party): warning: Unrecognized feature: 'web-share'. (localhost:8093)
- Embedded-frame hosts (third-party embed, informational): www.youtube.com (8), fonts.gstatic.com (1), googleads.g.doubleclick.net (2), static.doubleclick.net (1), www.google.com (1), i.ytimg.com (1), jnn-pa.googleapis.com (1), yt3.ggpht.com (1), www.gstatic.com (1) — matches forbidden pattern: fonts.gstatic.com (inside the embed, not first-party)
- Console errors/warnings from embedded frames (informational): warning: No available adapters. (www.youtube.com)

## /back-the-pack/ — ✅
- Document status: 200
- Main-frame hosts: localhost:8093 (22)
- Forbidden external hosts (main frame): none
- Failed/4xx+ requests (main frame): none
- Console errors/warnings (first party): none

## /the-hk9-coloring-book/ — ✅
- Document status: 200
- Main-frame hosts: localhost:8093 (14)
- Forbidden external hosts (main frame): none
- Failed/4xx+ requests (main frame): none
- Console errors/warnings (first party): none

## /online-application/ — ✅
- Document status: 200
- Main-frame hosts: localhost:8093 (11)
- Forbidden external hosts (main frame): none
- Failed/4xx+ requests (main frame): none
- Console errors/warnings (first party): none

## /barkode/hk923-005/ — ✅
- Document status: 200
- Main-frame hosts: localhost:8093 (10)
- Forbidden external hosts (main frame): none
- Failed/4xx+ requests (main frame): none
- Console errors/warnings (first party): none

## /hk9-current-teams-in-training/ — ✅
- Document status: 200
- Main-frame hosts: localhost:8093 (11)
- Forbidden external hosts (main frame): none
- Failed/4xx+ requests (main frame): none
- Console errors/warnings (first party): none

## /stories/madison-and-gunther/ — ✅
- Document status: 200
- Main-frame hosts: localhost:8093 (10)
- Forbidden external hosts (main frame): none
- Failed/4xx+ requests (main frame): none
- Console errors/warnings (first party): none

## /5-questions/ — ✅
- Document status: 200
- Main-frame hosts: localhost:8093 (10)
- Forbidden external hosts (main frame): none
- Failed/4xx+ requests (main frame): none
- Console errors/warnings (first party): none

Result: **1 of 24 URLs flagged** (a URL is flagged for a forbidden host, a failed/4xx+ sub-request or a console error/warning in the main frame).

## Notes

- `/fixture-rich-blocks/` is a LOCAL-ONLY blog fixture (`tools/wp.sh hk9-dev fixtures create`) whose YouTube embed block is a known, documented exception. Everything flagged on it is embed-related and nothing is theme/plugin markup:
  - The first-party console warning `Unrecognized feature: 'web-share'` is Chromium parsing the `allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"` attribute on the `<iframe>` that YouTube's oEmbed response returns through WordPress core (`grep -r web-share theme plugin` → 0 hits).
  - `fonts.gstatic.com` (Roboto for the YouTube player UI), `*.doubleclick.net`, `www.google.com`, `i.ytimg.com`, `jnn-pa.googleapis.com`, `yt3.ggpht.com`, `www.gstatic.com` and the `No available adapters.` warning are requests/logs made from inside the cross-origin YouTube frame.
- No production URL loads anything from the source sites, Google Fonts or a CDN; every first-party page is served entirely from `localhost:8093`.
- `/nonexistent-page/` answers 404 for the document itself (correct for the 404 template) with no failed sub-requests.
