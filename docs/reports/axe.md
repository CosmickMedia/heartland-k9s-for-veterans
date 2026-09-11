# axe-core accessibility scan

Base http://localhost:8093 · 2026-09-11T17:06:46.576Z · axe-core 4.13.0 · Playwright 1.63.0 · chromium 153.0.8010.12

Tags: wcag2a, wcag2aa, wcag21a, wcag21aa, wcag22aa, best-practice. Serious/critical violations fail the run (exit 1).

| URL | Width | Critical | Serious | Moderate | Minor | Details |
|---|---|---|---|---|---|---|
| / | 390 | 0 | 0 | 0 | 0 | — |
| / | 1440 | 0 | 0 | 0 | 0 | — |
| /about/ | 390 | 0 | 0 | 0 | 0 | — |
| /about/ | 1440 | 0 | 0 | 0 | 0 | — |
| /program/ | 390 | 0 | 0 | 0 | 0 | — |
| /program/ | 1440 | 0 | 0 | 0 | 0 | — |
| /veterans/ | 390 | 0 | 0 | 0 | 0 | — |
| /veterans/ | 1440 | 0 | 0 | 0 | 0 | — |
| /get-involved/ | 390 | 0 | 0 | 0 | 0 | — |
| /get-involved/ | 1440 | 0 | 0 | 0 | 0 | — |
| /barkode/ | 390 | 0 | 0 | 0 | 0 | — |
| /barkode/ | 1440 | 0 | 0 | 0 | 0 | — |
| /stories/ | 390 | 0 | 0 | 0 | 0 | — |
| /stories/ | 1440 | 0 | 0 | 0 | 0 | — |
| /contact/ | 390 | 0 | 0 | 0 | 0 | — |
| /contact/ | 1440 | 0 | 0 | 0 | 0 | — |
| /news/ | 390 | 0 | 0 | 0 | 0 | — |
| /news/ | 1440 | 0 | 0 | 0 | 0 | — |
| /donate/ | 390 | 0 | 0 | 0 | 0 | — |
| /donate/ | 1440 | 0 | 0 | 0 | 0 | — |
| /events/ | 390 | 0 | 0 | 0 | 0 | — |
| /events/ | 1440 | 0 | 0 | 0 | 0 | — |
| /campaigns/ | 390 | 0 | 0 | 0 | 0 | — |
| /campaigns/ | 1440 | 0 | 0 | 0 | 0 | — |
| /meet-the-team/ | 390 | 0 | 0 | 0 | 0 | — |
| /meet-the-team/ | 1440 | 0 | 0 | 0 | 0 | — |
| /photos/ | 390 | 0 | 0 | 0 | 0 | — |
| /photos/ | 1440 | 0 | 0 | 0 | 0 | — |
| /?s=dog | 390 | 0 | 0 | 0 | 0 | — |
| /?s=dog | 1440 | 0 | 0 | 0 | 0 | — |
| /nonexistent-page/ | 390 | 0 | 0 | 0 | 0 | — |
| /nonexistent-page/ | 1440 | 0 | 0 | 0 | 0 | — |
| /fixture-rich-blocks/ | 390 | 2 | 1 | 0 | 0 | aria-allowed-attr (critical, 1×: `iframe`); aria-prohibited-attr (serious, 1×: `iframe`); button-name (critical, 1×: `iframe`) |
| /fixture-rich-blocks/ | 1440 | 2 | 1 | 0 | 0 | aria-allowed-attr (critical, 1×: `iframe`); aria-prohibited-attr (serious, 1×: `iframe`); button-name (critical, 1×: `iframe`) |
| /back-the-pack/ | 390 | 0 | 0 | 0 | 0 | — |
| /back-the-pack/ | 1440 | 0 | 0 | 0 | 0 | — |
| /the-hk9-coloring-book/ | 390 | 0 | 0 | 0 | 0 | — |
| /the-hk9-coloring-book/ | 1440 | 0 | 0 | 0 | 0 | — |
| /online-application/ | 390 | 0 | 0 | 0 | 0 | — |
| /online-application/ | 1440 | 0 | 0 | 0 | 0 | — |
| /barkode/hk923-005/ | 390 | 0 | 0 | 0 | 0 | — |
| /barkode/hk923-005/ | 1440 | 0 | 0 | 0 | 0 | — |
| /hk9-current-teams-in-training/ | 390 | 0 | 0 | 0 | 0 | — |
| /hk9-current-teams-in-training/ | 1440 | 0 | 0 | 0 | 0 | — |
| /stories/madison-and-gunther/ | 390 | 0 | 0 | 0 | 0 | — |
| /stories/madison-and-gunther/ | 1440 | 0 | 0 | 0 | 0 | — |

Result: **FAIL** — 6 serious/critical violation group(s) across 23 URLs × 2 widths. 6 of the 6 group(s) are entirely inside a cross-origin <iframe> (third-party embed DOM such as YouTube — not theme/plugin markup); 0 are in first-party markup.

## Serious / critical details

### /fixture-rich-blocks/ @ 390 — `aria-allowed-attr` (critical, 1 node)

Elements must only use supported ARIA attributes — https://dequeuniversity.com/rules/axe/4.13/aria-allowed-attr?application=playwright

- `iframe .ytmVideoInfoVideoTitle` — Fix all of the following: ARIA attribute is not allowed: aria-level="2"

### /fixture-rich-blocks/ @ 390 — `aria-prohibited-attr` (serious, 1 node)

Elements must only use permitted ARIA attributes — https://dequeuniversity.com/rules/axe/4.13/aria-prohibited-attr?application=playwright

- `iframe #movie_player` — Fix all of the following: aria-label attribute cannot be used on a div with no valid role attribute.

### /fixture-rich-blocks/ @ 390 — `button-name` (critical, 1 node)

Buttons must have discernible text — https://dequeuniversity.com/rules/axe/4.13/button-name?application=playwright

- `iframe .ytmVideoInfoChannelAvatar` — Fix any of the following: Element does not have inner text that is visible to screen readers aria-label attribute does not exist or is empty aria-labelledby attribute does not exist, references elements that do not exist or references elements that are empty Element has no title attribute Element does not have an implicit (wrapped) <label> Element does not have an explicit <label> Element's default semantics were not overridden with role="none" or role="presentation"

### /fixture-rich-blocks/ @ 1440 — `aria-allowed-attr` (critical, 1 node)

Elements must only use supported ARIA attributes — https://dequeuniversity.com/rules/axe/4.13/aria-allowed-attr?application=playwright

- `iframe .ytmVideoInfoVideoTitle` — Fix all of the following: ARIA attribute is not allowed: aria-level="2"

### /fixture-rich-blocks/ @ 1440 — `aria-prohibited-attr` (serious, 1 node)

Elements must only use permitted ARIA attributes — https://dequeuniversity.com/rules/axe/4.13/aria-prohibited-attr?application=playwright

- `iframe #movie_player` — Fix all of the following: aria-label attribute cannot be used on a div with no valid role attribute.

### /fixture-rich-blocks/ @ 1440 — `button-name` (critical, 1 node)

Buttons must have discernible text — https://dequeuniversity.com/rules/axe/4.13/button-name?application=playwright

- `iframe .ytmVideoInfoChannelAvatar` — Fix any of the following: Element does not have inner text that is visible to screen readers aria-label attribute does not exist or is empty aria-labelledby attribute does not exist, references elements that do not exist or references elements that are empty Element has no title attribute Element does not have an implicit (wrapped) <label> Element does not have an explicit <label> Element's default semantics were not overridden with role="none" or role="presentation"

