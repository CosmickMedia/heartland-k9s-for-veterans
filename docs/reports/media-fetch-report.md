# Media fetch report

Tool: `tools/fetch-media.mjs` (`npm run media:fetch`). Index: `payload/media-index.json`. Manifest table: `docs/media-manifest.md`. User-Agent `HK9-migration/1.0 (+https://heartlandk9s.org)`, concurrency 4, 3 retries with exponential backoff (1 s → 2 s → 4 s + jitter), 30000 ms idle timeout per response, 150 ms politeness delay before each request.

## Latest run

- Started 2026-09-11T12:11:16.558Z, finished 2026-09-11T12:11:16.580Z, duration **0.0s**
- Processed 251 items: **251 ok** (0 downloaded, 0 copied from the reference bundle, 251 skipped by resume), **0 failed**, 0 retries
- Bytes downloaded this run: 0 B (0)

## Initial full fetch

- Started 2026-09-11T12:09:01.520Z, duration **1m 7s**: 251 processed, **251 ok** (239 downloaded, 5 copied, 7 skipped), **0 failed**, 0 retries, 654.7 MB (686,533,252 bytes) downloaded

## Totals (index state)

- Items expected: **251** (244 live + 7 reference) · in index: **251** (244 live, 7 reference)
- Status: **251 ok / 0 failed**
- Total bytes on disk: **663.1 MB** (695,319,289 bytes)
- Unscaled originals fetched instead of the served `-scaled` file: 68
- By MIME: image/jpeg 201 · image/png 39 · application/pdf 5 · image/webp 3 · video/mp4 1 · application/vnd.openxmlformats-officedocument.wordprocessingml.document 1 · image/svg+xml 1

## Failures

None — every item in the index is `ok`.

## Validation notes

Every file was checked for: non-empty body, received bytes = `Content-Length` (identity encoding requested), response `Content-Type` = expected MIME **or** file magic bytes = expected MIME, magic bytes never contradicting the expected MIME, and (where the discovery manifest recorded a size) the manifest filesize.

Accepted on magic bytes because the server sent a different `Content-Type` (reference assets are local copies and are always validated by magic bytes):

- `live:media:3745` (application/vnd.openxmlformats-officedocument.wordprocessingml.document) — magic-bytes (server sent text/plain)

Every downloaded file matched the filesize recorded in the discovery manifest.

## Duplicate files (identical sha256)

Kept as separate attachments (the live library has separate records); the importer may dedupe by sha256.

| sha256 | Bytes | Keys |
|---|---|---|
| `c31ea4fefaa5` | 59,357 | `live:media:2149`, `live:media:2806` |
| `8736eb2bee17` | 100,100 | `live:media:2836`, `live:media:3076` |
| `55751cbfdcd4` | 645,317 | `live:media:2899`, `live:media:2901` |
| `ea82df1a63d7` | 35,268 | `live:media:2926`, `live:media:3253` |
| `359f3b5541a1` | 18,685 | `live:media:2988`, `live:media:3088` |
| `558ab18952bd` | 159,325 | `live:media:3028`, `ref:asset:heartland-k9s-logo` |
| `dcee25f84527` | 11,785,964 | `live:media:3506`, `live:media:3514` |
| `be517ac77094` | 319,798 | `live:media:3511`, `live:media:3607` |
| `4804d238b404` | 11,424,913 | `live:media:3512`, `live:media:3540` |
| `2f79523f0ecc` | 12,697,342 | `live:media:3513`, `live:media:3548` |

## External media not downloaded

These hosts appear in the live pages or the reference bundle but are deliberately left external (they are third-party seals, buttons, scripts or link targets, not site content):

| Host | Asset | Example | Why not downloaded |
|---|---|---|---|
| `widgets.guidestar.org` | Candid/GuideStar transparency seal (SVG) | https://widgets.guidestar.org/prod/v1/pdp/transparency-seal/9494475/svg | Third-party trust seal that must stay live so it reflects the current profile status; rendered by the theme from settings (contact.show_guidestar_seal), never re-hosted. |
| `www.paypalobjects.com` | PayPal hosted "Donate" button image (GIF) | https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif | PayPal branding asset; the donate page renders its own button and the PayPal hosted-button form uses links.paypal_hosted_button_id. |
| `www.paypal.com` | PayPal tracking pixel (GIF) | https://www.paypal.com/en_US/i/scr/pixel.gif | Tracking pixel, not content. |
| `www.zeffy.com` | Zeffy donation form | https://www.zeffy.com/en-US/donation-form/donate-to-heartland-k9s-it-will-change-lives | External link target only (links.donate_external); nothing to download. |
| `app.candid.org` | Candid nonprofit profile | https://app.candid.org/profile/9494475/heartland-canines-for-veterans-inc-47-4991572/ | External link target only (contact.candid_url); nothing to download. |
| `cdn.usefathom.com` | Fathom analytics script | https://cdn.usefathom.com/script.js | Loaded at runtime only when analytics.fathom_site_id is set; not a media asset. |
| `fonts.googleapis.com` | Google Fonts CSS (reference bundle) | https://fonts.googleapis.com/css2?family=Fraunces…&family=Inter… | Fonts are built locally by tools/fonts/build-fonts.py (WOFF2 + OFL); no runtime CDN. |

## Special cases

- `live:media:2061` (Homepage-Hero-1.jpg) is hidden from the anonymous REST API (401 rest_forbidden) but its upload URL is public; it was fetched directly and its date comes from the HTTP `Last-Modified` header.
- PDF attachments expose a JPEG preview as their `full` size in the REST API; the tool downloads the `source_url` (the actual PDF), never the preview.
- `ref:asset:heartland-k9s-logo` is byte-identical to `live:media:3028` (see duplicate table). `ref:asset:favicon` is the Replit placeholder (orange rounded square), retained only for traceability — not to be used as the site icon.
- For `video/mp4` only bytes are recorded (no dimension probe); PDF/DOCX have no dimensions.

## Run history

| Started | Duration | Processed | OK | Downloaded | Copied | Skipped (resume) | Failed | Retries | Bytes downloaded | Options |
|---|---|---|---|---|---|---|---|---|---|---|
| 2026-09-11T12:07:13.359Z | 2.1s | 7 | 7 | 5 | 2 | 0 | 0 | 0 | 7.4 MB | only=7 |
| 2026-09-11T12:07:53.062Z | 0.0s | 7 | 7 | 0 | 0 | 7 | 0 | 0 | 0 B | only=7 |
| 2026-09-11T12:09:01.520Z | 1m 7s | 251 | 251 | 239 | 5 | 7 | 0 | 0 | 654.7 MB | — |
| 2026-09-11T12:10:41.939Z | 0.1s | 7 | 7 | 0 | 7 | 0 | 0 | 0 | 0 B | only=7 force |
| 2026-09-11T12:10:42.034Z | 0.0s | 251 | 251 | 0 | 0 | 251 | 0 | 0 | 0 B | — |
| 2026-09-11T12:10:49.935Z | 0.3s | 251 | 251 | 0 | 0 | 251 | 0 | 0 | 0 B | verify |
| 2026-09-11T12:11:16.558Z | 0.0s | 251 | 251 | 0 | 0 | 251 | 0 | 0 | 0 B | — |
