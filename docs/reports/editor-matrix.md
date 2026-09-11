# Editor task matrix — verification report

Generated 2026-09-11T18:21:53.227Z by `node tools/editor-matrix.mjs` against http://localhost:8093 (WordPress admin as `admin`, Playwright chromium 1440×900, 43 s). Every task was performed through the wp-admin UI, proven with an HTTP fetch of the rendered frontend page (curl-equivalent, no browser cache) and reverted; the site state was compared with a WP-CLI snapshot taken before the run (section meta, page fields, `hk9_settings`, primary menu items, record lists).

**Result: 14 pass, 0 fail, 0 blocked** (14 tasks; this run re-executed task(s) 10 and merged them into the previous results).

| # | Task | Result | Time | Screenshots |
|---|---|---|---|---|
| 1 | About page: hero_band heading → Update → frontend h1 → restore via Revisions | **PASS** | 42s | [em-01-about-hero-edit.png](screenshots/admin/em-01-about-hero-edit.png)<br>[em-01-about-revisions-screen.png](screenshots/admin/em-01-about-revisions-screen.png)<br>[em-01-about-after-restore.png](screenshots/admin/em-01-about-after-restore.png) |
| 2 | About page: replace the Legacy section image via the media picker → Update → frontend img → revert | **PASS** | 9s | [em-02-about-legacy-picker.png](screenshots/admin/em-02-about-legacy-picker.png)<br>[em-02-about-legacy-reverted.png](screenshots/admin/em-02-about-legacy-reverted.png) |
| 3 | Home page: change the primary hero CTA label + link (internal page picker) → Update → frontend → revert | **PASS** | 9s | [em-03-home-hero-cta-edit.png](screenshots/admin/em-03-home-hero-cta-edit.png)<br>[em-03-home-hero-cta-reverted.png](screenshots/admin/em-03-home-hero-cta-reverted.png) |
| 4 | Home page: move feature card 3 to position 1 (move buttons, then keyboard for the revert) → Update → frontend order → revert | **PASS** | 7s | [em-04-home-cards-moved.png](screenshots/admin/em-04-home-cards-moved.png)<br>[em-04-home-cards-reverted.png](screenshots/admin/em-04-home-cards-reverted.png) |
| 5 | Home page: hide the "mission" section in the Page sections panel → Update → gone on the frontend → show again | **PASS** | 7s | [em-05-home-mission-hidden-panel.png](screenshots/admin/em-05-home-mission-hidden-panel.png)<br>[em-05-home-mission-restored.png](screenshots/admin/em-05-home-mission-restored.png) |
| 6 | Preview: change the About hero heading, Preview in new tab (no Update) → preview shows it, live page does not → discard | **PASS** | 8s | [em-06-about-preview-tab.png](screenshots/admin/em-06-about-preview-tab.png)<br>[em-06-about-after-discard.png](screenshots/admin/em-06-about-after-discard.png) |
| 7 | Add a Story (title, quote, veteran name, canine, featured image, featured toggle) → publish → on /stories/ with its own URL → delete permanently | **PASS** | 14s | [em-07-story-editor.png](screenshots/admin/em-07-story-editor.png)<br>[em-07-stories-listing.png](screenshots/admin/em-07-stories-listing.png) |
| 8 | Add an Event (future date/time, venue, registration link) → Upcoming on /events/ → set the date in the past → Past → delete | **PASS** | 15s | [em-08-event-editor.png](screenshots/admin/em-08-event-editor.png)<br>[em-08-events-upcoming.png](screenshots/admin/em-08-events-upcoming.png)<br>[em-08-events-past.png](screenshots/admin/em-08-events-past.png) |
| 9 | Add a Person (name, role, portrait) → /meet-the-team/ (order) → delete; add a Partner (name, logo, website, type back-the-pack) → /back-the-pack/ → delete | **PASS** | 19s | [em-09-person-editor.png](screenshots/admin/em-09-person-editor.png)<br>[em-09-people-listing.png](screenshots/admin/em-09-people-listing.png)<br>[em-09-partner-editor.png](screenshots/admin/em-09-partner-editor.png)<br>[em-09-partners-listing.png](screenshots/admin/em-09-partners-listing.png) |
| 10 | Appearance → Menus: add a custom link to the Primary menu → header (desktop + mobile panel) → remove it | **PASS** | 15s | [em-10-menu-added.png](screenshots/admin/em-10-menu-added.png)<br>[em-10-menu-header-desktop.png](screenshots/admin/em-10-menu-header-desktop.png)<br>[em-10-menu-header-mobile.png](screenshots/admin/em-10-menu-header-mobile.png) |
| 11 | Heartland → Settings → Contact: change the main phone → footer + contact page → restore; Branding: swap the header logo → header img → restore | **PASS** | 16s | [em-11-settings-contact.png](screenshots/admin/em-11-settings-contact.png)<br>[em-11-settings-contact-saved.png](screenshots/admin/em-11-settings-contact-saved.png)<br>[em-11-settings-branding-picker.png](screenshots/admin/em-11-settings-branding-picker.png)<br>[em-11-header-logo-swapped.png](screenshots/admin/em-11-header-logo-swapped.png) |
| 12 | Heartland → Settings → Header: change the Donate CTA label → header changes → restore | **PASS** | 7s | [em-12-settings-header.png](screenshots/admin/em-12-settings-header.png)<br>[em-12-header-cta-changed.png](screenshots/admin/em-12-header-cta-changed.png) |
| 13 | Quick Edit on About (title only) → section meta untouched (hk9_sec_hero_band before/after) → restore the title | **PASS** | 24s | [em-13-about-quick-edit.png](screenshots/admin/em-13-about-quick-edit.png) |
| 14 | Contact form as an anonymous visitor: no-JS POST to admin-post.php and JS submit → Heartland → Submissions + Mailpit → delete both | **PASS** | 25s | [em-14-contact-js-success.png](screenshots/admin/em-14-contact-js-success.png)<br>[em-14-submissions-list.png](screenshots/admin/em-14-submissions-list.png) |

## Site state at the end

All compared values are identical to the start snapshot: About/Home section meta (11 keys), titles/slugs/content, `hk9_settings` (effective values), the 7 primary menu items and the story/event/person/partner/submission record lists. Revision counts: About 23 → 23, Home 29 → 29 (revisions created by Update/Restore are expected and kept; the preview autosave was removed).

## Usability findings

- (task 1) After "Restore This Revision" WordPress 7.1 redirects to post.php?…&message=5&revision=<id>, and the block editor immediately bounces back to the Compare Revisions screen (core useClassicRevisionRedirect), so the "Post restored to revision" notice is never shown. The restore itself works; editors get no confirmation and have to click "Go to editor" themselves.
- (task 1) Restoring a revision that differs only in section meta does not add a new revision (core saves the revision before wp_restore_post_revision_meta() runs, so the latest revision keeps showing the temporary heading while the page already shows the original). Harmless for content, but the revision list no longer has an entry equal to the current state.
- (task 4) BUG — the icon preview beside every "Icon" select is blank: the admin renders <use href="…icons.svg#<name>"> but the theme sprite ids are "hk9-icon-<name>" (nothing hooks the hk9/fields/icon_symbol_prefix filter), so editors choose icons from a text list without seeing them.
- (task 6) After a preview and reload the editor shows "There is an autosave of this post that is more recent than the version below. View the autosave There is an a…" — editors may wonder whether to restore it; the autosave holds only the previewed heading (the script deletes that autosave in cleanup).
- (task 7) Story cards on /stories/ use the "Veteran display name" as the card heading (the post title only appears on the single page). This mirrors the reference site, but the Details box does not say so — a help line under "Veteran display name" ("shown as the card heading on Success Stories") would avoid surprises.
- (task 9) A new Person defaults to Order 0 and therefore jumps to the first position on /meet-the-team/ (existing people use 1–11). Editors must set "Order" in the Page Attributes box; a help note or a default of "last" would prevent an accidental reshuffle.
- (task 10) Appearance → Menus: with the item list just taller than the viewport, a mouse click on the sticky "Save Menu" button does not register — focusing the button on mousedown scrolls the page ~33px, the sticky footer (#nav-menu-footer) snaps to its natural position and the mouseup lands outside the button. Scrolling to the bottom first, or pressing Enter on the focused button, saves normally (WordPress core nav-menus.php behaviour, chromium).
- (task 12) BUG — saving the Header tab drops the stored label of "CTA link" (header.cta_link.label "Donate Now" → ""): the settings link field only posts mode/post_id/url/target, so the imported label is discarded on every save. No visible effect today (the theme prints header.cta_label and supplies its own labels for links.*), but the same happens to every Destinations link label whenever that tab is saved, and any future use of those labels would show empty text.

## Task details

### 1. About page: hero_band heading → Update → frontend h1 → restore via Revisions

**Result:** PASS

**Steps**
1. Open the About page editor (post 1453), expand the "Sections — About / Mission" meta box, change Hero band → Heading.
2. Click Update.
3. Fetch /about/ and read the hero h1.
4. Open the Revisions screen for the previous revision (the latest one whose Hero band equals the pre-change value) and click "Restore This Revision".

**Expected**
- Frontend h1 shows "Our Mission (tmp Editor Matrix 1 20260911T181501)" after Update; after restoring the previous revision the h1 shows "Our Mission" and hk9_sec_hero_band equals the original; the Update adds one revision (the restore may add another — recorded).

**Observed**
- OK — REST 200, stored heading "Our Mission (tmp Editor Matrix 1 20260911T181501)"
- OK — frontend h1 = "Our Mission (tmp Editor Matrix 1 20260911T181501)"
- OK — revisions 22 → 23 after Update
- revision 1947 (1453-revision-v1) diff does not show the section meta fields
- OK — hk9_sec_hero_band restored to the original (heading "Our Mission")
- OK — frontend h1 = "Our Mission"
- revision count: 22 before → 23 after Update → 23 after restore; after the restore the browser landed on /wp-admin/revision.php?revision=1947

**Screenshots:** `docs/reports/screenshots/admin/em-01-about-hero-edit.png`, `docs/reports/screenshots/admin/em-01-about-revisions-screen.png`, `docs/reports/screenshots/admin/em-01-about-after-restore.png`

_(result carried over from the run of 2026-09-11T18:17:10.157Z)_

### 2. About page: replace the Legacy section image via the media picker → Update → frontend img → revert

**Result:** PASS

**Steps**
1. Open the About editor, Legacy section → Image → "Replace" → Media Library → pick an existing image → "Use this".
2. Click Update, fetch /about/.
3. Revert: "Replace" again and re-select the original image ("About split-card image"), Update.

**Expected**
- Legacy image changes from attachment 1404 ("About split-card image") to "BarKode hero / tile background"; frontend .hk9-legacy__media img src changes; after re-selecting the original the meta equals the start value.

**Observed**
- OK — picker set hidden input to 1405 (preview http://localhost:8093/wp-content/uploads/2026/09/barkode-300x300.jpg)
- OK — stored image id 1405
- OK — frontend img src about-dog.jpg → barkode.jpg
- OK — meta back to original (image 1404), frontend src about-dog.jpg

**Screenshots:** `docs/reports/screenshots/admin/em-02-about-legacy-picker.png`, `docs/reports/screenshots/admin/em-02-about-legacy-reverted.png`

_(result carried over from the run of 2026-09-11T18:12:34.219Z)_

### 3. Home page: change the primary hero CTA label + link (internal page picker) → Update → frontend → revert

**Result:** PASS

**Steps**
1. Open the Home editor, Hero → Buttons → #1 → Label; switch the link to "Page / record" and search "Donate".
2. Click Update, fetch /.
3. Revert: restore the label, switch the link back to "External URL" (clears the page selection), Update.

**Expected**
- Primary hero button changes from "Support a Service Dog" → "Give Today (tmp Editor Matrix 3)" and its href from https://www.zeffy.com/en-US/donation-form/donate-to-heartland-k9s-it-will-change-lives to the Donate page permalink; after reverting the stored value equals the start value.

**Observed**
- OK — picked "Donate" (post 1434)
- OK — stored label/post_id Give Today (tmp Editor Matrix 3) / 1434
- OK — frontend button "Give Today (tmp Editor Matrix 3)" → http://localhost:8093/donate/
- OK — meta identical to start; frontend button "Support a Service Dog" → https://www.zeffy.com/en-US/donation-form/donate-to-heartland-k9s-it-will-change-lives

**Screenshots:** `docs/reports/screenshots/admin/em-03-home-hero-cta-edit.png`, `docs/reports/screenshots/admin/em-03-home-hero-cta-reverted.png`

_(result carried over from the run of 2026-09-11T18:12:34.219Z)_

### 4. Home page: move feature card 3 to position 1 (move buttons, then keyboard for the revert) → Update → frontend order → revert

**Result:** PASS

**Steps**
1. Open the Home editor, Feature cards → Cards → card #3: click "Move up" twice (mouse).
2. Click Update, fetch /.
3. Revert with the keyboard: focus card #1 "Move down" (Tab order) and press Enter twice, Update.

**Expected**
- Cards render as [Zero Cost to Veterans | Unbreakable Bond | A Lifelong Commitment]; after moving card 3 up twice with the mouse: [A Lifelong Commitment | Zero Cost to Veterans | Unbreakable Bond]; after moving it down twice with the keyboard the original order and meta return.

**Observed**
- OK — frontend order at start [Zero Cost to Veterans \| Unbreakable Bond \| A Lifelong Commitment]
- icon previews in the Cards repeater: 0/3 render a glyph (first <use href> = http://localhost:8093/wp-content/themes/heartland-k9s/assets/dist/icons.svg?ver=1789145736#shield-check)
- OK — panel order [A Lifelong Commitment \| Zero Cost to Veterans \| Unbreakable Bond], focus stays on "Move up"
- OK — frontend order [A Lifelong Commitment \| Zero Cost to Veterans \| Unbreakable Bond]
- OK — keyboard moves worked, focus followed the moved row (data-index 2); panel order [Zero Cost to Veterans \| Unbreakable Bond \| A Lifelong Commitment]
- OK — meta identical to start; frontend [Zero Cost to Veterans \| Unbreakable Bond \| A Lifelong Commitment]

**Screenshots:** `docs/reports/screenshots/admin/em-04-home-cards-moved.png`, `docs/reports/screenshots/admin/em-04-home-cards-reverted.png`

_(result carried over from the run of 2026-09-11T18:17:10.158Z)_

### 5. Home page: hide the "mission" section in the Page sections panel → Update → gone on the frontend → show again

**Result:** PASS

**Steps**
1. Open the Home editor, "Page sections" side panel → untick "Mission".
2. Click Update, fetch /.
3. Tick "Mission" again, Update, fetch /.

**Expected**
- section#hk9-mission disappears from / after unticking Mission and updating; ticking it again restores it and hk9_sections_layout equals the start value.

**Observed**
- OK — mission section present at start
- content panel shows the "Hidden" badge on the Mission section: true
- OK — hidden=[mission], frontend has no #hk9-mission
- OK — layout meta identical to start, mission section back

**Screenshots:** `docs/reports/screenshots/admin/em-05-home-mission-hidden-panel.png`, `docs/reports/screenshots/admin/em-05-home-mission-restored.png`

_(result carried over from the run of 2026-09-11T18:19:45.294Z)_

### 6. Preview: change the About hero heading, Preview in new tab (no Update) → preview shows it, live page does not → discard

**Result:** PASS

**Steps**
1. Open the About editor, change Hero band → Heading, open the Preview menu → "Preview in new tab".
2. Discard: reload the editor without saving (accept the "leave page" prompt).

**Expected**
- The preview tab renders the unsaved heading; /about/ keeps the saved heading; the stored meta is unchanged; reloading the editor discards the change.

**Observed**
- OK — autosave 200, preview tab h1 = "Preview only (tmp Editor Matrix 6)" (http://localhost:8093/about/?preview_id=1453&preview_nonce=f0121da7bc&preview=true)
- OK — live /about/ h1 = "Our Mission", stored meta unchanged
- OK — editor reloaded with "Our Mission"
- cleanup: deleted 1 autosave revision(s) created by the preview so the editor does not show a "more recent autosave" notice

**Screenshots:** `docs/reports/screenshots/admin/em-06-about-preview-tab.png`, `docs/reports/screenshots/admin/em-06-about-after-discard.png`

_(result carried over from the run of 2026-09-11T18:17:10.158Z)_

### 7. Add a Story (title, quote, veteran name, canine, featured image, featured toggle) → publish → on /stories/ with its own URL → delete permanently

**Result:** PASS

**Steps**
1. Heartland → Stories → Add New: type the title, fill Details (Veteran display name, Canine name, Quote, Featured story), set a featured image from the library.
2. Publish (pre-publish panel → Publish).
3. Fetch /stories/ and the story URL.
4. Delete: Stories list → Trash → Trash view → Delete Permanently.

**Expected**
- The story publishes, is listed on /stories/ with a card linking to /stories/<slug>/ (HTTP 200, h1 = title), and is gone from /stories/ and the database after deletion.

**Observed**
- OK — published http://localhost:8093/stories/tmp-editor-matrix-story/ with meta {"veteran":"Sam Example","canine":"Biscuit","quote":"Every day is easier with Biscuit by my side.","featured":"1","thumb":1404}
- OK — one card on /stories/ (heading = veteran display name "Sam Example", quote shown, featured image rendered); every link to the story uses the single URL http://localhost:8093/stories/tmp-editor-matrix-story/
- OK — single URL http://localhost:8093/stories/tmp-editor-matrix-story/ → 200, h1 "tmp Editor Matrix Story"
- OK — UI delete "deleted", record gone, /stories/ no longer lists it

**Screenshots:** `docs/reports/screenshots/admin/em-07-story-editor.png`, `docs/reports/screenshots/admin/em-07-stories-listing.png`

_(result carried over from the run of 2026-09-11T18:12:34.220Z)_

### 8. Add an Event (future date/time, venue, registration link) → Upcoming on /events/ → set the date in the past → Past → delete

**Result:** PASS

**Steps**
1. Heartland → Events → Add New: title, Start date + time, Venue, Registration link (External URL), Publish.
2. Fetch /events/: the event must be inside #hk9-upcoming and not inside #hk9-past.
3. Edit the event: Start date → 2024-01-15, Update, fetch /events/ again.
4. Delete: Events list → Trash → Delete Permanently.

**Expected**
- The event appears in section#hk9-upcoming on /events/ with start 2026-10-21 18:00; after changing the start to 2024-01-15 it moves to section#hk9-past; deleted afterwards.

**Observed**
- OK — published, start "2026-10-21 18:00", venue "Example Hall", registration https://example.com/register
- OK — listed under Upcoming only
- OK — start "2024-01-15 18:00", listed under Past only
- OK — UI delete "deleted", record gone, /events/ no longer lists it

**Screenshots:** `docs/reports/screenshots/admin/em-08-event-editor.png`, `docs/reports/screenshots/admin/em-08-events-upcoming.png`, `docs/reports/screenshots/admin/em-08-events-past.png`

_(result carried over from the run of 2026-09-11T18:12:34.220Z)_

### 9. Add a Person (name, role, portrait) → /meet-the-team/ (order) → delete; add a Partner (name, logo, website, type back-the-pack) → /back-the-pack/ → delete

**Result:** PASS

**Steps**
1. Heartland → People → Add New (classic editor): title, Role / title, Set featured image (portrait) from the library, Publish.
2. Fetch /meet-the-team/ and locate the card.
3. Delete the person (list → Trash → Delete Permanently).
4. Heartland → Partners → Add New: title, Website URL, Partner Type "Back the Pack Partner", Set featured image (logo), Publish.
5. Fetch /back-the-pack/ and locate the logo tile.
6. Delete the partner (list → Trash → Delete Permanently).

**Expected**
- Both records publish from the classic editor (these types are not in REST), the person shows on /meet-the-team/ (position reported), the partner logo/link shows on /back-the-pack/; both deleted.

**Observed**
- OK — classic editor, published with role + portrait 1404, menu_order 0
- OK — card at position 1 of 12 (menu_order 0; listing sorts by Order then title), role "Volunteer Coordinator", portrait rendered
- OK — person deleted, no longer on /meet-the-team/
- OK — published with type [back-the-pack] and website https://example.com/
- OK — tile rendered as a link to https://example.com/ with the logo
- OK — partner deleted, no longer on /back-the-pack/

**Screenshots:** `docs/reports/screenshots/admin/em-09-person-editor.png`, `docs/reports/screenshots/admin/em-09-people-listing.png`, `docs/reports/screenshots/admin/em-09-partner-editor.png`, `docs/reports/screenshots/admin/em-09-partners-listing.png`

_(result carried over from the run of 2026-09-11T18:12:34.220Z)_

### 10. Appearance → Menus: add a custom link to the Primary menu → header (desktop + mobile panel) → remove it

**Result:** PASS

**Steps**
1. Open Appearance → Menus (menu 74), Custom Links → URL + Link Text → "Add to Menu" → "Save Menu".
2. Fetch / and check the desktop nav + the mobile panel markup; open the mobile panel at 390px.
3. Back in Appearance → Menus: expand the item → "Remove" → "Save Menu".

**Expected**
- The link renders in .hk9-header__list (desktop nav) and in #hk9-mobile-menu (mobile panel) after Save Menu; after removing it the menu items equal the start list.

**Observed**
- OK — menu item 1956 saved
- OK — desktop nav link → https://example.com/matrix; mobile panel link present
- OK — mobile panel shows the link after tapping the menu toggle (at y=468px, panel opacity 1)
- OK — menu items identical to start, link gone from the header

**Screenshots:** `docs/reports/screenshots/admin/em-10-menu-added.png`, `docs/reports/screenshots/admin/em-10-menu-header-desktop.png`, `docs/reports/screenshots/admin/em-10-menu-header-mobile.png`

### 11. Heartland → Settings → Contact: change the main phone → footer + contact page → restore; Branding: swap the header logo → header img → restore

**Result:** PASS

**Steps**
1. Settings → Contact tab → "Main phone" → Save changes.
2. Restore "Main phone" to 800-913-6189 → Save changes.
3. Settings → Branding tab → Header logo "Replace" → pick another library image → Save changes.
4. Restore: "Replace" → pick "Concept 1 rocker outlined (2)" → Save changes.

**Expected**
- Footer + contact page show 800-555-0199 after saving, then 800-913-6189 again; header <img class="hk9-header__logo"> src changes to the picked image and back; hk9_settings equals the start value at the end.

**Observed**
- OK — saved (notice "Heartland forms: 1 submission in the last 7 days was saved but its email notification could not be sent. Review them Dismiss this notice. Settings saved. Dismiss this notice."); footer tel "800-555-0199", contact page info rows + footer show 800-555-0199, old number gone
- OK — contact settings identical to start, footer shows 800-913-6189
- OK — header logo src Concept-1-rocker-outlined-2-263x300.png → hero-home-300x300.jpg (attachment 1406)
- OK — effective settings identical to start, header logo back

**Screenshots:** `docs/reports/screenshots/admin/em-11-settings-contact.png`, `docs/reports/screenshots/admin/em-11-settings-contact-saved.png`, `docs/reports/screenshots/admin/em-11-settings-branding-picker.png`, `docs/reports/screenshots/admin/em-11-header-logo-swapped.png`

_(result carried over from the run of 2026-09-11T18:12:34.220Z)_

### 12. Heartland → Settings → Header: change the Donate CTA label → header changes → restore

**Result:** PASS

**Steps**
1. Settings → Header tab → "CTA label" → Save changes.
2. Restore the label to "Donate Now" → Save changes.

**Expected**
- .hk9-header__cta text changes from "Donate Now" to "Give Today (tmp Editor Matrix 12)" and back; the mobile panel button follows; hk9_settings equals the start value at the end.

**Observed**
- OK — header CTA "Give Today (tmp Editor Matrix 12)", mobile CTA "Give Today (tmp Editor Matrix 12)"
- OK — header CTA back to "Donate Now"
- DEFECT (does not block the task) — after the UI round-trip the stored settings differ in one key: header.cta_link {"label":"Donate Now","url":"","post_id":1434,"target":"_self","rel":""} → {"label":"","url":"","post_id":1434,"target":"_self","rel":""}; the header still renders correctly because it prints header.cta_label. Restored with WP-CLI in cleanup.
- cleanup: hk9_settings restored with WP-CLI (effective values differed from the start)

**Screenshots:** `docs/reports/screenshots/admin/em-12-settings-header.png`, `docs/reports/screenshots/admin/em-12-header-cta-changed.png`

_(result carried over from the run of 2026-09-11T18:12:34.220Z)_

### 13. Quick Edit on About (title only) → section meta untouched (hk9_sec_hero_band before/after) → restore the title

**Result:** PASS

**Steps**
1. Pages list → About → Quick Edit → Title "About (tmp Editor Matrix 13)" → Update.
2. Quick Edit again → Title "About" → Update.

**Expected**
- Only post_title changes; every hk9_sec_* key and hk9_sections_layout are byte-identical before/after; the slug stays "about"; the title is restored the same way.

**Observed**
- OK — title now "About (tmp Editor Matrix 13)", slug "about"
- OK — all 5 section keys + content identical (hero heading still "Our Mission", frontend h1 "Our Mission")
- OK — title restored, meta identical

**Screenshots:** `docs/reports/screenshots/admin/em-13-about-quick-edit.png`

_(result carried over from the run of 2026-09-11T18:12:34.220Z)_

### 14. Contact form as an anonymous visitor: no-JS POST to admin-post.php and JS submit → Heartland → Submissions + Mailpit → delete both

**Result:** PASS

**Steps**
1. No-JS: GET /contact/ in a fresh context, read the hidden protocol fields (nonce, timestamp, token), wait 3 s (time trap), POST to admin-post.php.
2. JS: open /contact/ in the same anonymous context, fill the form, submit (fetch to hk9/v1/forms/contact), wait for the inline success.
3. Heartland → Submissions: both rows listed; Mailpit: search the marker.
4. Delete: first submission via the list (Trash → Delete Permanently), the second the same way; delete the Mailpit messages.

**Expected**
- Both submissions succeed (303 → status=sent for the no-JS POST; inline success for the JS path), two rows appear under Heartland → Submissions with "Sent" badges, Mailpit holds two [HK9 Contact] messages; rows + messages deleted.

**Observed**
- --reset-rate-limit: deleted hk9_form_rl_0c312a1231392b33c889e9e73eeaf963 (count 5) left by earlier test submissions
- OK — 303 → /contact/?hk9_form=contact&status=sent&t=77e18f90e1aa2fba503538fe561ad0b9#hk9-form-contact
- OK — landing page shows the success panel "Message Sent"
- OK — submission 1948 stored, _hk9_mail_sent=1
- OK — REST 200, inline success shown (focus moved to "hk9-form__success"), submission 1949 _hk9_mail_sent=1
- OK — rows 1948, 1949 listed with "Sent"
- OK — Mailpit: 2 messages — [HK9 Contact] Veteran Application Inquiry from Matrix JS EM20260911T180747 \| [HK9 Contact] Veteran Application Inquiry from Matrix NoJS EM20260911T180747
- OK — UI deletes deleted/deleted, 2 Mailpit message(s) deleted

**Screenshots:** `docs/reports/screenshots/admin/em-14-contact-js-success.png`, `docs/reports/screenshots/admin/em-14-submissions-list.png`

_(result carried over from the run of 2026-09-11T18:12:34.220Z)_

## How to re-run

```
node tools/editor-matrix.mjs                # all 14 tasks
node tools/editor-matrix.mjs --only=7,8     # a subset
node tools/editor-matrix.mjs --headed       # watch the browser
```

The script removes anything left from an interrupted run (records titled "tmp Editor Matrix …"), restores page meta / settings with WP-CLI if a UI revert did not leave the original value (reported in the task as "cleanup: … restored with WP-CLI"), puts the admin user's editor UI preferences (`wp_persisted_preferences`: meta box drawer open/height, welcome guide) back to their pre-run value, and exits non-zero when a task fails.
