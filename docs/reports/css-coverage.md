# CSS coverage (theme 1.0.1 single stylesheet → 1.0.2 core + bundles)

Measured with Playwright `page.coverage.startCSSCoverage()` (Chromium) at 390 and 1440 px on every template, after scrolling to the bottom, opening the mobile menu, hovering a few interactive elements and tabbing three times. The stylesheet was served **expanded** with a source map so every rule maps back to its SCSS partial; bytes below are expanded-CSS bytes (the shipped files are compressed). Script: session scratchpad `coverage.mjs` (not committed); the numbers drove the bundle split in `tools/build-css.mjs` / `hk9_style_bundles()`.

## 1.0.1 — used bytes per partial across 29 URLs (206335 bytes expanded, 1635 rules)

| partial | rules | bytes | rules used (any URL) | bytes used (any URL) | decision |
|---|---|---|---|---|---|
| _blog-states.scss | 196 | 21705 | 48 | 5284 | blog.css |
| _pages-reference.scss | 191 | 19833 | 160 | 16911 | pages.css (site-wide overrides → core _pages-shared) |
| _pages-migrated.scss | 154 | 16608 | 115 | 12685 | records.css |
| _prose.scss | 118 | 12595 | 34 | 3949 | content.css (.screen-reader-text → core) |
| bootstrap:_buttons.scss | 24 | 12551 | 3 | 2080 | dropped (.hk9-btn self-contained) |
| _forms.scss | 53 | 9950 | 34 | 6644 | forms.css |
| _grids.scss | 72 | 8908 | 41 | 5741 | records.css (.hk9-status/.hk9-info → core) |
| _singles.scss | 78 | 8334 | 62 | 6315 | records.css |
| _sections.scss | 75 | 7775 | 57 | 5707 | core |
| _blog.scss | 56 | 6593 | 22 | 2777 | blog.css |
| _buttons.scss | 33 | 6181 | 23 | 4675 | core |
| bootstrap:_utilities.scss | 109 | 5639 | 0 | 0 | dropped (utilities API) |
| _layout.scss | 57 | 5547 | 46 | 4794 | core |
| bootstrap:_reboot.scss | 70 | 5120 | 34 | 2450 | core |
| _header.scss | 38 | 4659 | 31 | 3990 | core |
| bootstrap:_root.scss | 1 | 4642 | 1 | 4642 | replaced by a 21-variable :root |
| _base.scss | 39 | 4631 | 27 | 3849 | core |
| _cards.scss | 42 | 4582 | 33 | 4015 | core |
| _hero.scss | 41 | 4396 | 36 | 3958 | core |
| bootstrap:_forms.scss | 26 | 4356 | 0 | 0 | dropped (validation mixins) |
| _footer.scss | 38 | 4067 | 36 | 3963 | core |
| bootstrap:_form-check.scss | 25 | 4047 | 4 | 697 | forms.css |
| bootstrap:_form-control.scss | 28 | 3476 | 4 | 672 | forms.css |
| bootstrap:_pagination.scss | 12 | 3064 | 0 | 0 | dropped |
| _utilities.scss | 11 | 1847 | 2 | 431 | core |
| _tokens.scss | 1 | 1515 | 1 | 1515 | core |
| bootstrap:_form-select.scss | 8 | 1510 | 1 | 833 | forms.css |
| bootstrap:_type.scss | 16 | 1049 | 0 | 0 | dropped |
| bootstrap:_containers.scss | 6 | 821 | 5 | 690 | core |
| _fonts.scss | 2 | 715 | 0 | 0 | core |
| bootstrap:_labels.scss | 4 | 613 | 0 | 0 | dropped |
| bootstrap:_transitions.scss | 8 | 427 | 0 | 0 | dropped |
| ? | 1 | 375 | 0 | 0 | (sass charset/comment) |
| bootstrap:_grid.scss | 1 | 183 | 1 | 183 | dropped (:root breakpoints) |
| bootstrap:_form-text.scss | 1 | 95 | 0 | 0 | dropped |

## Per-URL used bytes (1.0.1, expanded)

| URL | rules used / 1635 | bytes used / 206335 |
|---|---|---|
| / | 235 | 36672 |
| /about/ | 204 | 33095 |
| /program/ | 209 | 33215 |
| /contact/ | 195 | 32990 |
| /news/ | 158 | 26288 |
| /photos/ | 161 | 26298 |
| /barkode/hk923-005/ | 177 | 29346 |
| /stories/madison-and-gunther/ | 180 | 30102 |
| /donate/ | 200 | 32420 |
| /veterans/ | 215 | 33722 |
| /get-involved/ | 179 | 30577 |
| /barkode/ | 215 | 35366 |
| /stories/ | 223 | 32807 |
| /events/ | 208 | 32919 |
| /campaigns/ | 202 | 32524 |
| /meet-the-team/ | 162 | 27279 |
| /hk9-current-teams-in-training/ | 196 | 31769 |
| /our-highlighted-team/ | 174 | 28794 |
| /online-application/ | 218 | 38201 |
| /5-questions/ | 171 | 27348 |
| /privacy-policy/ | 148 | 25314 |
| /back-the-pack/ | 171 | 27743 |
| /teams/billy-and-caddie/ | 195 | 31234 |
| /events/joplin-jailbirds-veteran-appreciation-night/ | 193 | 30956 |
| /campaigns/educational-coloring-book/ | 219 | 34420 |
| /?s=dog | 214 | 37722 |
| /this-page-does-not-exist/ | 173 | 31531 |
| /thank-you/ | 148 | 25569 |
| /heartland-gear/ | 169 | 26951 |

## 1.0.2 core (theme.css, 57212 bytes expanded, 486 rules) — used on the five Lighthouse URLs

| URL | rules used | bytes used |
|---|---|---|
| / | 231 / 486 | 28894 / 57212 |
| /about/ | 191 / 486 | 25274 / 57212 |
| /program/ | 187 / 486 | 23903 / 57212 |
| /contact/ | 149 / 486 | 19511 / 57212 |
| /news/ | 133 / 486 | 17959 / 57212 |

Shipped sizes (compressed): theme.css 47.0 kB / 9.2 kB gzip · forms.css 16.4 / 3.1 · content.css 12.2 / 2.5 · blog.css 26.2 / 4.4 · pages.css 14.0 / 2.5 · records.css 30.1 / 4.8 (was: theme.css 172.6 kB / 27.4 kB gzip). Lighthouse `unused-css-rules` passes on every audited page.
