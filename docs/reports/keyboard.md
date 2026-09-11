# Keyboard walk

Run 2026-09-11 against http://localhost:8093 with Playwright 1.63.0 / Chromium 153.0.8010.12, via `node docs/reports/screenshots/keyboard/keyboard-walk.mjs` (script, per-step evidence in `results.json` and every screenshot live in `docs/reports/screenshots/keyboard/`). Focus is moved only with the keyboard (Tab / Shift+Tab / Enter / Escape); the script reads `document.activeElement`, `:focus-visible`, computed `outline`/`box-shadow` and geometry after each key.

**Result: 19/19 checks pass.** One theme CSS weakness found (hero CTA focus ring, below) — reported, not edited.

## 1. Home @ 1440 — skip link → header → Donate → hero CTAs

| # | Check | Result | Evidence |
|---|---|---|---|
| 1 | Tab order | ✅ | `a.hk9-skip-link` → `a.hk9-header__brand` → 7 × `a.hk9-header__nav-link` (About, Program, Veterans, BarKode, Stories, Get Involved, Contact) → `a.hk9-header__cta` "Donate Now" → hero `a.hk9-btn--primary` "Support a Service Dog" → `a.hk9-btn--outline-light` "Apply for a Dog" |
| 2 | Skip link becomes visible on focus | ✅ | rect 16,16 156×48, navy pill with `2px solid #fff` outline (`01-home-1440-skip-link.png`) |
| 3 | Visible focus indicator on every stop | ✅ (see note) | nav links: `outline 2px solid rgb(28,47,74)` offset 2px; buttons: `box-shadow 0 0 0 1px rgb(28,47,74)` (`02-…nav-link.png`, `03-…donate.png`, `04/05-…hero-cta-*.png`) |
| 4 | Enter on skip link | ✅ | `location.hash=#main`, `main[tabindex=-1]` receives focus, next Tab lands on the first hero CTA (not back in the header) |

Composite: `sheet-home-1440.png`.

**Note — hero CTA focus ring is not perceivable (theme CSS).** `.hk9-btn:focus-visible { outline: none; box-shadow: 0 0 0 1px var(--hk9-ring) }` (source `theme/heartland-k9s/assets/src/scss/_buttons.scss:35-38`, `--hk9-ring: var(--hk9-primary)` = `#1c2f4a` in `_tokens.scss:37`; the `--primary` variant at `_buttons.scss:69-74` only changes background/border colour on focus, which is also imperceptible) replaces the theme's generic `:where(a,button,…):focus-visible { outline: 2px solid var(--hk9-ring); outline-offset: 2px }` with a 1 px navy (`#1c2f4a`) ring. On the light header that ring is thin but visible around "Donate Now"; on the dark hero (navy overlay) the focused "Support a Service Dog" button is indistinguishable from its unfocused state — compare the two crops in `zoom-hero-cta.png` (left: focused, right: unfocused) — and the same applies to every `.hk9-btn` placed on a dark section (hero, CTA bands, footer Donate). Computed styles confirm the only change on focus is that 1 px navy shadow (measured with `document.activeElement` after a real Tab: `:focus-visible=true`, `outline: none`, `box-shadow: rgb(28,47,74) 0 0 0 1px`). The check above passes on the letter of "has an indicator" but this fails the intent of WCAG 2.4.7 / 2.4.13 (indicator ≥ 3:1 against adjacent colours; navy-on-navy ≈ 1:1). Suggested fix (theme, not applied): a two-tone ring that works on any background, e.g. `box-shadow: 0 0 0 2px var(--hk9-bg), 0 0 0 4px var(--hk9-ring)`, or simply not overriding the generic outline for `.hk9-btn`.

## 2. Home @ 390 — mobile menu by keyboard

| # | Check | Result | Evidence |
|---|---|---|---|
| 5 | Tab reaches the toggle | ✅ | skip link → brand → `button.hk9-header__toggle` "Open menu" (`06-home-390-toggle-focus.png`, 2 px navy ring) |
| 6 | Enter opens the panel | ✅ | `aria-expanded="true"`, `#hk9-mobile-menu` un-hidden and laid out, toggle label switches to "Close menu" (`07-home-390-menu-open.png`) |
| 7 | Tab walks the panel in order | ✅ | About → Program → Veterans → BarKode → Stories → Get Involved → Contact → "Donate Now" (`08-home-390-menu-first-item.png`) |
| 8 | Tab past the last item closes the panel | ✅ | `focusout` rule: `aria-expanded="false"`, panel hidden, focus continues to the first hero CTA (`09-home-390-menu-last-item-and-after.png`) |
| 9 | Escape closes and returns focus | ✅ | with focus on "Program" inside the panel, Escape → `aria-expanded="false"`, panel hidden, `document.activeElement` is the toggle (`10-home-390-after-escape.png`, ring on the toggle) |

Composite: `sheet-mobile-menu.png`.

## 3. Contact form — Tab through, invalid submit → error summary

| # | Check | Result | Evidence |
|---|---|---|---|
| 10 | Tab order, honeypot skipped | ✅ | `#hk9-form-contact-first_name` → `-last_name` → `-email` → `select#…-subject` → `textarea#…-message` → `button` "Send Message"; the `hk9_website` honeypot has `tabindex="-1"` and is never reached |
| 11 | Focus indicator on each control | ✅ | inputs/select/textarea: navy 2 px outline + shadow (`11-contact-field-focus.png`); Send button: 1 px navy ring on the navy button (same weakness as above, though the button sits on a white card so the ring is at least perceivable) |
| 12 | Invalid submit moves focus to the summary | ✅ | Tab to Send, Enter (form is `noValidate`, JS validates): `#hk9-form-contact-summary[role=alert][tabindex=-1]` un-hidden and focused; text "Please correct the following: First Name is required. Last Name is required. Email Address is required. Please select an option for Subject. Message is required."; 5 controls get `aria-invalid="true"` + `aria-describedby` → inline error ids (`12-contact-error-summary-focus.png`) |
| 13 | Summary not obscured by the sticky header | ✅ | summary top at 96 px = `html { scroll-padding-top: calc(var(--hk9-header-h) + var(--hk9-sticky-top) + 1rem) }` (80 + 0 + 16), header bottom 81 px, 0 px overlap at 1440 and 390 |
| 14 | Summary links focus their field | ✅ | Tab → `a[href="#hk9-form-contact-first_name"]` "First Name is required.", Enter → `input#hk9-form-contact-first_name` focused, `aria-invalid="true"` (`13-contact-summary-link-to-field.png`) |

Composite: `sheet-contact.png`. (An earlier draft of this walk focused the Send button programmatically; that started the page's smooth scroll to the button, which was still animating when the summary took focus and left the summary 111 px under the header. That was a harness artifact — with a real Tab sequence the summary lands exactly at the scroll-padding offset — so the check was rewritten to use the real key sequence and to measure the overlap.)

## 4. Photos — lightbox open/close by keyboard

| # | Check | Result | Evidence |
|---|---|---|---|
| 15 | Keyboard triggers exist | ✅ | 71 `figure.wp-lightbox-container`, 71 `button.lightbox-trigger` (WP 7.1 core image lightbox / Interactivity API), one shared `.wp-lightbox-overlay` |
| 16 | Tab reaches the trigger | ✅ | `button.lightbox-trigger[aria-label="Enlarge 1 of 71"]`, `:focus-visible`, `outline 3px auto rgb(0,95,204)`; core fades the button in over 0.2 s so it is invisible for the first frames after focus (`14-photos-trigger-focus.png` taken after the transition; `zoom-photos-trigger.png`) |
| 17 | Enter opens the lightbox | ✅ | overlay `.active`, `visibility: visible`, `role="dialog"`, `aria-modal="true"`, focus inside (`div.wp-lightbox-overlay[aria-label="Enlarged image 1 of 71"]`), large image loaded (`15-photos-lightbox-open.png`) |
| 18 | Tab stays inside the dialog | ✅ | Close → Previous → Next, `document.activeElement` never leaves the overlay (`16-photos-lightbox-tab.png`) |
| 19 | Escape closes and restores focus | ✅ | overlay inactive/hidden, focus back on `button.lightbox-trigger` "Enlarge 1 of 71" (`17-photos-lightbox-closed.png`) |

Composite: `zoom-photos-trigger.png` (trigger focus before open vs after close).

## Summary of issues for the theme (not edited)

1. **`.hk9-btn:focus-visible` ring is 1 px navy** → invisible on dark backgrounds (hero CTAs, any button on a navy section). Theme CSS; see §1 note.

Everything else in scope (skip link, header order, mobile menu open/Tab/Escape/return-focus, form Tab order, honeypot exclusion, error summary focus + `role=alert`, scroll-padding under the sticky header, summary→field links, lightbox open/trap/Escape/return-focus) behaves correctly.
