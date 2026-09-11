# Conflict log — reference design vs. live site content

Scope: every place where the Replit reference copy (`discovery/ref/reference-content.json`) and the live site (`discovery/live/content/*.json`, `discovery/discovery-result.json`) disagree, plus every content-driven layout deviation, and every copy edit made while authoring `payload-src/`. Authored 2026-09-11 against live captures dated 2026-09-10.

Policy applied throughout:

- **P1 — live is the source of truth for facts.** Contact details, program steps, eligibility framing, BarKode mechanics, partner/people/campaign/event/team data and quotes come from the live site verbatim wherever a verbatim sentence exists.
- **P2 — reference is the source of truth for structure and design copy.** Section order, headings, button labels and mission/values language are kept from the reference when they are not contradicted by the live site.
- **P3 — unverified factual claims are replaced, not kept.** A reference sentence that asserts a checkable fact the live site does not support (service area, office days, certification, "elite providers across the country", "fraudulent service dogs", "equipment", etc.) is rewritten from live wording or dropped. Values statements that assert no checkable fact ("a veteran has already paid the price") are kept and flagged here as editable.
- **P4 — nothing is invented.** Fields without a verified value are left empty (`pairing_year`, partner websites, campaign dates, event end time…) and listed in `docs/unresolved.md` when a business decision is needed.

## 1. Site-wide

| # | Topic | Reference | Live | Decision | Where |
|---|---|---|---|---|---|
| S1 | Placeholder links | 13 CTAs point at bare `https://heartlandk9s.org/` (`_blank`) | Real destinations exist | Header "Donate Now" + all "Make a Donation" links → `/donate/` (`live:page:2120`). Hero "Support a Service Dog", "Donate to Protect a Team", "Support Our Mission" → Zeffy form (`_blank`, matches the live sticky button label). "Start Application" → `/online-application/` (1989). "Read ADA FAQs" → `/service-dogs-and-the-ada/` (1995). "View Campaigns" → `/campaigns/` (2800). "View Our Providers" → "K9 Provider Criteria" → `/the-service-k9-program/` (3084). "Ways to Volunteer" → `/volunteer/` (2124). "Contact Us to Help" → `/contact/` (ref). Footer "K9 Providers" → 3084. | `settings.json`, all `ref-*.json`, `menus.json` |
| S2 | Footer map-pin line | "Serving disabled U.S. veterans nationwide" | No "nationwide" anywhere; street address 12651 Gateway Dr, Neosho MO 64850 | Footer/contact rows are settings-sourced (`contact.address_*`); no service-area copy shipped (`contact.service_area` empty). | `settings.json`, `ref-contact.json` |
| S3 | Hours | "Mon–Fri, 8:00am–5:00pm" / "Monday – Friday" | "HOURS 8:00am – 5:00pm" only; days never stated | `contact.hours` = "8:00am – 5:00pm", `contact.hours_days` = "" (editable). | `settings.json` |
| S4 | Second phone | Omitted | "Director cell: 417-312-7484" on Contact, footer, Donate, every registry record | Added as `contact.phone_secondary` labelled "Director cell"; rendered as a contact-page row. | `settings.json`, `ref-contact.json` |
| S5 | Emails | info@ only | info@, director@, development@ | All three stored; contact page + footer use info@; application form recipient director@ (see unresolved U3). | `settings.json` |
| S6 | Legal/EIN/seal | None | EIN 47-4991572, GuideStar/Candid seal, Facebook page | Stored in settings (`contact.ein`, `contact.candid_url`, `contact.facebook`, `show_guidestar_seal`). Tax statement copied from `/donate/` with the typo "extend" corrected to "extent". | `settings.json` |
| S7 | Header CTA | "Donate Now" → root | Live nav "DONATE" → `/donate/`; sticky "🐾 Support a Service Dog" → Zeffy | Header CTA "Donate Now" → `/donate/` page (`header.cta_link`); Zeffy kept as `links.donate_external` for the hero-style CTAs. | `settings.json` |
| S8 | Navigation | About / Program / Veterans / BarKode / Stories / Get Involved / Contact | DONATE / 5 QUESTIONS / APPLICATION / ADA FAQs / CONTACT / PARTNER WITH US / PHOTOS / VOLUNTEER / EVENTS / CAMPAIGNS | Primary menu = reference IA. Every live nav destination is reachable from the two footer menus (Quick Links: 5 Questions, ADA FAQs, Meet the Team, Photos; Get Involved: Donate, Volunteer, Campaigns, Events, Back the Pack, K9 Providers, Heartland Gear, Coloring Book, Obedience Training, Apply). Reference footer "Campaigns & Events → /get-involved" split into real Campaigns + Events links. | `menus.json` |
| S9 | Privacy link | None in either | — | Added a `legal` footer menu with Privacy Policy (`live:page:3`) so the migrated policy is discoverable. | `menus.json` |
| S10 | Analytics | None | Fathom site id present in live HTML | `analytics.fathom_site_id` left empty — the live id is not reproduced in the payload (see unresolved U4). | `settings.json` |
| S11 | Logo | `/heartland-k9s-logo.png` | `Concept-1-rocker-outlined-2.png` (byte-identical, 1073×1223) | Reference asset used for header/footer logo (`ref:asset:heartland-k9s-logo`); it is the same file. | `settings.json` |
| S12 | Photography | 5 AI-generated reference images | Real photos | Reference images kept for hero-home, about, program hero, barkode (visual fidelity, per plan). Testimonial/story image replaced with the real Madison Stratton portrait (`live:media:3444`, the 8th Meet-the-Team portrait, order-matched 11/11 per discovery). `ref:asset:veteran-story` is imported but not referenced by any section. Business decision to swap the remaining four is in `unresolved.md`. | `ref-home.json`, `ref-stories.json`, `stories/madison-stratton.json` |

## 2. Home (`ref:page:home`)

| # | Section | Reference | Live | Decision |
|---|---|---|---|---|
| H1 | Hero badge / h1 / text | "IRS-Recognized 501(c)(3) Nonprofit" / "So They Never Walk Alone." / "Pairing eligible disabled U.S. veterans … at zero cost. Restoring independence, dignity, and purpose." | 501(c)(3) verified; tagline verified; "ZERO cost" verified | Kept verbatim. |
| H2 | Hero CTAs | Support a Service Dog → root; Apply for a Dog → /veterans | Zeffy sticky button uses the same label | "Support a Service Dog" → Zeffy (`_blank`); "Apply for a Dog" → `/veterans/`. |
| H3 | Mission | Heading "We believe those who served…" + reference paragraph | Not verbatim on live; "single goal", "proven canine training strategies", "empirical" sentences are live | Reference mission copy kept (P2 — values language, no contradicted fact). The verbatim live sentences are used on the About page and the Program intro instead. |
| H4 | Card 1 "Zero Cost to Veterans" | "…complete with training and equipment, is provided entirely free of charge." Link "Learn about funding" → /program | "HK9 PROUDLY gifts a service K9 at ZERO cost to Veterans"; "One of Us" campaign covers "all the gear, equipment, kenneling…" | Text now leads with the live "ZERO cost" sentence; "training and equipment" kept because the One of Us campaign copy supports it. Link retargeted to `/get-involved/` (the program page has no funding content; the Get Involved donate card explains what donations fund). |
| H5 | Card 2 "Unbreakable Bond" | Reference text | "right canine is paired with the right person" and "life-long bond … built on trust" are live | Kept verbatim; link "Our matching process" → `/program/`. |
| H6 | Card 3 "Nationwide Reach" | "While our roots are in the heartland, our impact spans the country…" → /about | No nationwide claim; evidence is regional (MO + 4-state area) | **Replaced** with "A Lifelong Commitment" using the live How-It-Works sentences verbatim: "Having a service dog is a lifetime commitment. Training, and working with your K9 is never “complete”. HK9 is available to help with re-training." Icon `heart` (in the lucide set). Link "What to expect" → `/veterans/`. |
| H7 | BarKode promo | Eyebrow "Proprietary System"; "specialized BarKode patch … certification beacon and emergency contact system" | "a unique service provided by Heartland Canines For Veterans"; "unique team number and QR code … secure database"; scan shows photo, team ID, tasks, emergency contact, vet info, HK9 contact | Eyebrow → "A Service of Heartland K9s" (live page title). Body rewritten from the live BarKode page. Heading, image, caption "Dedicated to Derron & Rosie" and button kept. |
| H8 | Testimonial | Fabricated quote, "USMC Veteran — Paired in 2022", AI image | Only real quote: Madison Stratton (Meet the Team) | Section sources the `hk9_story` record `live:story:madison-stratton` (`source: story`) and also carries the same quote/name/meta/image as manual fallback. Attribution: "Madison Stratton — USMC Veteran & HK9 Board Member — paired with Gunther". Image = real portrait (S12). |

## 3. About (`ref:page:about`)

| # | Section | Reference | Live | Decision |
|---|---|---|---|---|
| A1 | Hero text | "Driven by a single goal: to do our part in making the world a better place for the veterans who have served this great nation." | Verbatim on live HOW IT WORKS | Kept. |
| A2 | "Est. 2015" badge | Present | Ted Donaldson bio "since its founding in 2015"; logo rocker "EST. 2015" | Kept (supported). Homepage first published 2017 — noted, not a conflict. |
| A3 | Legacy body | "a veteran has already paid the price"; "no eligible disabled veteran should face financial barriers…"; "proven canine training strategies… right canine is paired with the right person" | First sentence not on live (values statement); rest verbatim | Kept, and the live sentence "Our decision making process is informed by comprehensive empirical studies and high quality data evaluation." appended. "Already paid the price" flagged as editable reference copy. |
| A4 | Value "Excellence" | "We partner with premier K9 providers and utilize rigorous training standards…" | "our network of breeders"; K9 must pass "temperament exam given by our trainer"; documented intake criteria | Rewritten from live facts (network of breeders/trainers/kennels, intake standards, temperament exam). |
| A5 | Value "Commitment" | "Our support doesn't end at placement…" | "Training, and working with your K9 is never “complete”. HK9 is available to help with re-training." | Reference sentence kept and live re-training clause added. |
| A6 | CTA band | Make a Donation → root; Ways to Volunteer → /get-involved | — | → `/donate/` and `/volunteer/` (live pages). |

## 4. Program (`ref:page:program`)

| # | Section | Reference | Live | Decision |
|---|---|---|---|---|
| P1 | Steps intro | "Our decision-making process is informed by comprehensive empirical data. We don't just assign a dog; we carefully craft a team…" | "Our decision making process is informed by comprehensive empirical studies and high quality data evaluation. We strive to build productive relationships in our teams and make a positive impact with all of our pursuits." | Live sentences used verbatim. |
| P2 | **Timeline count and order** | 4 steps: Application & Assessment → Precision Matching → Rigorous Training → Team Placement | 5 explicit steps (5 Questions → online screening application → phone conversation & baseline assessment → full application → search for the right-fit K9), then a training process | **5 steps in live order** (layout deviation: the staggered 2-column timeline gains a fifth item; the theme must handle an odd count). Training/graduation facts (obedience from day one, service training not before 7 months, final practical exam → graduation) folded into step 5 so the page still covers training. Typos in live ("provided", "guidlines") not carried over. |
| P3 | "Our K9 Providers" band | "elite K9 providers and trainers across the country … breeding and preparing working dogs of the highest caliber"; "View Our Providers" → root | No provider directory; `/the-service-k9-program/` lists intake criteria | Renamed "Providing a K9"; text = live intake criteria summary; button "K9 Provider Criteria" → `live:page:3084`. Live page contradicts itself on the age window (accepts 10 weeks–16 months, rejects >14 months) — the rejection line about 14 months is omitted from the summary; see unresolved U6. Icon `shield-alert` kept for visual fidelity. |
| P4 | "Certified Heartland K9s team" | Step 4 claim | "A successful exam results in graduation of the K9 team." (live) | "certified" dropped; "graduation" used. |
| P5 | Navy CTA | "…review our eligibility requirements and begin the application process." | Live frames the 5 Questions as self-assessment; "Veteran or Family Member of a Veteran considering a Service K9" | Rewritten: "If you are a Veteran—or the family member of a Veteran—considering a Service K9, we invite you to read the 5 Questions and begin the application process." Button → `/veterans/`. |

## 5. For Veterans (`ref:page:veterans`)

| # | Section | Reference | Live | Decision |
|---|---|---|---|---|
| V1 | **The 5 Questions** | 5 invented yes/no eligibility gates ("honorable discharge", "physical or psychological disability"…) + "If you answered Yes…" | 5 self-assessment questions (`/5-questions/`, id 16), "there are no wrong answers", "not meant to be answered with a simple yes or no" | Live questions verbatim (numbering removed; the list renders its own markers). Intro and footer carry the live framing verbatim. Card icon left to the template (reference `circle-alert` + `check`). Secondary link "Read the full 5 Questions" → `live:page:16`. |
| V2 | Start Application | → root | `/online-application/` (1989) | → `live:page:1989`. |
| V3 | What to Expect steps | Application Review / Interview & Assessment / Team Training (reference text) | Live process: screening application → phone conversation & baseline assessment → emailed full application → right-fit K9 → training (commands, de-escalation, conflict resolution, ADA) → final practical exam → graduation | Three steps rewritten from live facts: "Screening & Assessment", "Full Application", "Matching & Team Training". |
| V4 | BarKode side card | "proprietary BarKode system—a digital certification and emergency contact patch" | Live BarKode mechanics | Rewritten from live (team number + QR, secure database, what a scan shows, free for HK9-trained teams). |
| V5 | ADA band | "We provide comprehensive education on these rights during your training." | Training "includes … Americans with Disabilities Act (ADA)" (live) | Softened to "ADA education is part of every Veteran's training with HK9" + pointer to the DOJ FAQ page. Button → `live:page:1995`. |

## 6. Get Involved (`ref:page:get-involved`)

| # | Section | Reference | Live | Decision |
|---|---|---|---|---|
| G1 | Donate card | Reference text; button → root | Donate page | Text kept; button → `/donate/`. |
| G2 | Campaigns card | Reference text; button → root | `/campaigns/` | Text kept; button → `live:page:2800`. |
| G3 | Volunteer card | Generic volunteer copy | "Paws for Training" volunteer temporary-home program; facility/events/gatherings | Rewritten to include Paws for Training and the live phrasing; button "Contact Us to Help" → `/contact/`. |
| G4 | Partners band | "sponsorship opportunities, matching gifts, or hosting an event" | Gear page: "grants, corporate donations and matches, fundraisers"; Back the Pack partners | "matching gifts" kept (supported by "corporate donations and matches"); "joining our Back the Pack partners" added; `show_logos` on so the 13 partner logos render. Button → `/contact/`. |

## 7. BarKode (`ref:page:barkode`, replaces `live:page:2944`)

| # | Section | Reference | Live | Decision |
|---|---|---|---|---|
| B1 | Hero eyebrow | "PROPRIETARY SYSTEM" | "BarKode – A Service of Heartland Canines for Veterans" | "A Service of Heartland K9s", icon `qr-code`. |
| B2 | Hero text | "…dedicated to the memory of Derron and his service dog, Rosie." | "dedicated to Derron and his service dog Rosie" | "A vital safety net dedicated to Derron and his service dog, Rosie." ("Derron, a veteran" is never stated live — not asserted.) |
| B3 | Story | Reference's expanded narrative | Live homepage dedication text | Paragraph 1 = live homepage dedication verbatim; paragraph 2 = live BarKode page description verbatim (voluntary validation service, first responders, ADA ambiguity). |
| B4 | "How BarKode Protects" cards | Instant Identification (patch, "certified, trained service animal"); Emergency Contacts (protocols); "Legitimacy & Trust — In an era of fraudulent service dogs…" | Live facts: team number + QR in secure database; scan shows photo, team ID, trained tasks, emergency contact, vet info, HK9 contact; voluntary validation of HK9-trained teams | All three rewritten from live facts; the "fraudulent service dogs" claim removed. |
| B5 | CTA band | "specialized patches … underlying registry"; Donate to Protect a Team → root | Free for HK9-trained teams; outside teams after application, evaluation, additional training and annual fee; "secure database" | Text carries the live fee clause and "secure database"; primary button → Zeffy (`_blank`); second button "Ask about BarKode" → `/contact/` (live page says to contact HK9 for more information). |
| B6 | Live page 2944 | — | Full live copy | All live BarKode sentences are represented in B3–B5; `live:page:2944` is **not** imported separately (its slug `barkode` is taken by this page). |

## 8. Stories (`ref:page:stories`)

| # | Section | Reference | Live | Decision |
|---|---|---|---|---|
| T1 | Featured testimonial | Fabricated "Before I got my dog…" quote | Madison Stratton quote (Meet the Team) | Sourced from `live:story:madison-stratton` (with manual fallback fields filled identically). |
| T2 | Testimonial grid | Two fabricated quotes (Army 2023, Navy 2021) | `/success/` is an empty placeholder | Replaced by the auto story list (`mode: auto`, count 6) with an honest empty-state text; no fabricated grid. |
| T3 | Teams section | Not in reference | Two teams in training (Billy & Caddie, Marilyn & Travis) | Section shown (`mode: manual`, both teams) — content-driven addition. |
| T4 | CTA | "Support Our Mission" → root | — | → Zeffy (`_blank`) + secondary "Share Your Story" → `/contact/`. |
| T5 | Story record | — | Bio on Meet the Team, quote inside bio | `hk9_story` "Madison & Gunther": veteran_name, branch "U.S. Marine Corps", canine "Gunther", relationship `service` (registry title "Madison and Gunther (Service K9)"), quote verbatim, `pairing_year` empty (live only says she "found Heartland in 2023"), `source_note` "Sourced from Meet the Team bio, heartlandk9s.org, 2026-09-10". Body is the bio re-paragraphed with a quote block and a closing source line. |

## 9. Contact (`ref:page:contact`, replaces `live:page:2032`)

| # | Section | Reference | Live | Decision |
|---|---|---|---|---|
| C1 | Info rows | Phone / Email / Office Hours "Monday – Friday, 8:00am – 5:00pm" / Service Area "…nationwide" | Address, main phone, Director cell, Facebook; hours (homepage) without days | Rows are settings-sourced: phone, phone_secondary ("Director cell"), email, hours, address. No service-area row. Facebook lives in settings (footer social). |
| C2 | Form | Simulated (no endpoint) | `[ccf_form id="2132"]` dead shortcode | `form: contact` (plugin handler); success copy from the reference toast. Original CCF field list unrecoverable (unresolved U2). |
| C3 | Live page 2032 | — | Heading + address + phones + dead shortcode | Not imported separately; all facts live in settings. |

## 10. Records

| # | Record set | Source | Decisions / edits |
|---|---|---|---|
| R1 | 11 `hk9_person` | Meet the Team (2856) | Live order preserved via `menu_order`; portraits order-matched to the 11 images (discovery: 11/11). Board President titled "Chris Flemming" (heading spelling); bio keeps "Chris Fleming" verbatim — see unresolved U5. Larry Warren has no bio on live → no content. Line breaks inside Cody's and Ted's bios (hard `<br>`s in the source) joined into sentences; wording untouched. Emails: director@ (Jimmy), development@ (Ted); the Volunteer Coordinator's personal address is **not** imported. |
| R2 | 13 `hk9_partner` | Back the Pack FooGallery 2094 (live order) | Titles from alt text where present (Pinnacle Pet, Petland, Trademark Poodles, Veterans of Foreign Wars, Compton CPA Group P.C.), otherwise from the filename (Bennett, Jack, Alice CBD, Cup O Joe, Joplin Main X, and the two Facebook-numbered files) — flagged in unresolved U7. `OIP.jpg` is the logo that links to petlandjoplin.com on the Campaigns page → titled "Petland Joplin" with that website; Pinnacle Pet gets the customer.pinnaclepet.net link from Campaigns. All other websites empty. All tagged `back-the-pack`; Pinnacle Pet, Petland Joplin and Jack additionally `campaign-sponsor` (they appear in the Campaigns thank-you blocks). The Campaigns-page Pinnacle logo variant (media 2926) is not a separate partner. |
| R3 | 5 `hk9_campaign` | Campaigns (2800) | Live order; bodies verbatim as blocks (empty spacer h1s dropped). "Paws" for Giving: the stale line "will be available by 2023!" removed (per discovery disposition) — stickers described as 4"×4", no cost. Sponsor relationships: Coloring Book → Pinnacle Pet; One of Us → Pinnacle Pet, Petland Joplin, Jack. CTA labels verbatim; all contact CTAs → `ref:page:contact`; "Go to the Book page" → `live:page:3627`. Status `active`, no dates/goals (none on live). One of Us has no campaign image on live → no featured image. |
| R4 | 1 `hk9_event` | Events (2483) | Start 2026-07-25 18:00 America/Chicago (gates), game 7 PM in body; end unknown → empty. Venue "Joe Becker Stadium", address "Joplin, MO" (no street address on live). Ticket info and SimpleTix registration verbatim. Status `scheduled`; the plugin's date logic classifies it as past. Older flyers attached to the Events page are media only (not fabricated as events). |
| R5 | 2 `hk9_team` | Teams in Training (3011) + Highlighted Team (3496) | Billy & Caddie: featured, summary from the teams page, body from the highlighted-team page; image 3684 (same photo on both pages). Marilyn & Travis: "alllll obedience training" normalised to "all obedience training" (copy edit). Both `in-training`; donate links → `/donate/` (live has no per-team form). `barkode` relationship left 0 — registry records are outside this content area (see integration notes). |
| R6 | Terms | Live tags + ARCHITECTURE §3 | `poker-run`, `veterans-day` (empty archives like live) and the four `hk9_partner_type` terms. |
| R7 | Reading | Plan | Front page = `ref:page:home`; posts page = new Page "News" (`new:page:news`, default template, short intro). |
| R8 | Redirects | Plan / ARCHITECTURE §9 | `/master_template_barkode/` → BarKode page; `/success/` + sample → Stories; slider URLs → `/`; two FooGallery query URLs → Photos / Back the Pack; 15 legacy root-level registry paths → `{type:"record", slug}` (canonical `/barkode/<slug>/`). |

## 11. Content-driven layout deviations (for the visual-comparison phase)

1. **Program timeline has 5 items instead of 4** (P2) — the staggered two-column layout ends with an unpaired item.
2. **Stories page has no two-card testimonial grid**; it has an auto story list (currently one story → empty state) and a Teams-in-Training section (T2, T3).
3. **Contact info card has 5 rows instead of 4** (address + Director cell added; service-area row removed).
4. **BarKode CTA band has two buttons** (donate + contact) instead of one (B5).
5. **Get Involved partners band** renders the 13 partner logos under the copy (`show_logos: true`).
6. **Home card 3 icon** is `heart` instead of `map-pin` (H6).
7. **Footer Get Involved column** has 10 links instead of 4; a Legal menu (Privacy Policy) is added (S8, S9).
