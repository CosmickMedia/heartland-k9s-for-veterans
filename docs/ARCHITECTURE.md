# Architecture contract — Heartland Canines for Veterans WordPress build

This document is the binding interface between the companion plugin (`plugin/heartland-k9s-core`), the theme (`theme/heartland-k9s`), the payload builder (`tools/build-payload.mjs`) and the content authors. Every implementer follows it exactly; changes must be made here first.

## 1. Conventions

- PHP ≥ 8.1, WordPress ≥ 6.4 (tested 7.1). WordPress coding standards style (tabs, Yoda conditions, `esc_*`/`wp_kses` on output, `sanitize_*` on input, nonces + capability checks on every write). Strict types where practical (`declare(strict_types=1);` in plugin classes).
- Prefix: functions `hk9_`, hooks `hk9/...` (e.g. `do_action('hk9/sections/register', $registry)`), options `hk9_`, meta keys `hk9_` (public) / `_hk9_` (private/hidden), CSS classes `.hk9-`, JS globals `window.HK9`, CLI `wp hk9 ...`, REST namespace `hk9/v1`, text domains `heartland-k9s-core` (plugin) and `heartland-k9s` (theme).
- Plugin namespace `HK9\Core\...`, PSR-4 under `plugin/heartland-k9s-core/src/` (own autoloader). One class per file. No Composer at runtime. No jQuery dependency in admin JS except where WordPress media modal requires `wp.media` (that is fine).
- No frontend HTTP requests to fetch local files. No external fonts/CDNs at runtime. No `href="#"`.
- Theme must run without the plugin (all `HK9\Core` calls go through `function_exists()`/`class_exists()` guards or the theme helper layer in `inc/options.php` + `inc/sections.php`). Plugin must run without the theme.

## 2. Plugin layout

```
plugin/heartland-k9s-core/
  heartland-k9s-core.php        # header, constants, autoload, hooks
  uninstall.php                 # respects hk9_settings[advanced][purge_on_uninstall] (default false)
  src/
    Autoloader.php  Plugin.php (boot(): instantiates modules in order below)
    PostTypes/Registrar.php      # all CPTs + taxonomy + capabilities map + admin columns/filters/sortable + rewrite rules (listing pagination)
    PostTypes/Capabilities.php   # role grants on activation + map_meta_cap
    Meta/Registry.php            # register_post_meta for CPT fields (typed, schema, sanitize, auth)
    Fields/Field.php + Fields/Types/*.php + Fields/Renderer.php + Fields/Sanitizer.php   # field framework (§4)
    Sections/Definition.php  Sections/Registry.php  Sections/MetaBox.php  Sections/Accessor.php  Sections/definitions/*.php  # §5
    Settings/Schema.php  Settings/Store.php  Settings/Page.php (tabs)  # §7
    Admin/Menu.php (top-level "Heartland" menu)  Admin/Assets.php  Admin/Notices.php  Admin/ListTables.php
    Forms/Handler.php  Forms/ContactForm.php  Forms/ApplicationForm.php  Forms/Submissions.php  Forms/Mailer.php  Forms/Antispam.php  # §10
    Events/Dates.php             # timezone/all-day/upcoming/past helpers
    Redirects/Store.php  Redirects/Resolver.php  Redirects/Admin.php  # §9
    Privacy/Registry.php         # BarKode exposure controls, user enumeration hardening
    Analytics/Fathom.php
    Import/Manifest.php  Import/Map.php  Import/Runner.php  Import/Tokens.php  Import/Steps/{Validate,Terms,MediaFiles,MediaSizes,PostsStub,PostsHierarchy,Reading,PostsContent,Menus,Options,Redirects,Finalize}.php  Import/Rollback.php  Import/Admin.php  Import/Rest.php  Import/Log.php  # §8
    CLI/ImportCommand.php  CLI/RedirectsCommand.php  CLI/StatusCommand.php
    Rest/Pick.php                # hk9/v1/pick relationship search (edit_posts)
    Support/Helpers.php          # hk9_* global functions (loaded always): hk9_option(), hk9_section(), hk9_link(), hk9_icon_name_list(), hk9_event_*()
  assets/css/admin.css  assets/js/fields.js  assets/js/sections.js  assets/js/settings.js  assets/js/import.js   # plain JS/CSS, no build
  templates/emails/*.php        # mail bodies
```

Boot order in `Plugin::boot()`: Support/Helpers → Settings → PostTypes → Meta → Sections → Redirects → Privacy → Forms → Events → Analytics → Rest → Admin (is_admin) → Import (is_admin || WP_CLI) → CLI (WP_CLI).

Activation (`Plugin::activate`): create `{$wpdb->prefix}hk9_import_map` via dbDelta, grant capabilities to roles, seed default redirects (option, no overwrite), register CPTs then `flush_rewrite_rules()`. Never imports content. Deactivation: flush rewrite rules only.

## 3. Post types, taxonomy, capabilities

| Type | Labels | public / queryable / search / rest / archive | Rewrite | Supports | Cap type / who |
|---|---|---|---|---|---|
| `hk9_story` | Stories / Story | true / true / false / true / false | `stories` | title, editor, excerpt, thumbnail, revisions | `hk9_story` (editor+) |
| `hk9_team` | Teams / Team | true / true / false / true / false | `teams` | title, editor, excerpt, thumbnail, revisions, page-attributes | `hk9_team` (editor+) |
| `hk9_person` | People / Person | false / false / true / false / false | false (query_var false) | title, editor, thumbnail, revisions, page-attributes | `hk9_person` (editor+) |
| `hk9_partner` | Partners / Partner | false / false / true / false / false | false | title, editor, thumbnail, page-attributes | `hk9_partner` (editor+) |
| `hk9_campaign` | Campaigns / Campaign | true / true / false / true / false | `campaigns` | title, editor, excerpt, thumbnail, revisions, page-attributes | `hk9_campaign` (editor+) |
| `hk9_event` | Events / Event | true / true / false / true / false | `events` | title, editor, excerpt, thumbnail, revisions | `hk9_event` (editor+) |
| `hk9_barkode` | BarKode Records / BarKode Record | **false / true / true / false / false**, `embeddable=false`, `show_in_nav_menus=false` | `barkode` | title, editor(off by default; use fields), thumbnail, revisions | `hk9_barkode` (administrator; editors only if `hk9_settings[advanced][editors_manage_registry]`) |
| `hk9_submission` | Form Submissions | false everywhere, `show_ui=true` under Heartland menu, `show_in_rest=false` | false | title | `hk9_submission` (administrator) |

All CPTs: `show_ui=true`, `show_in_menu='hk9'` (top-level "Heartland" menu, position 25, dashicon `dashicons-pets`), `menu_position` order: Stories, Teams, People, Partners, Campaigns, Events, BarKode Records, Submissions, then Settings, Redirects, Setup & Import. `map_meta_cap=true`, `capability_type=[singular, plural]`. Editors receive all `edit/publish/delete/read_private` caps for editorial types; administrators receive everything plus `hk9_manage_settings`, `hk9_run_import`, `hk9_manage_redirects`, `hk9_view_submissions`.

Taxonomy `hk9_partner_type` (hierarchical=false, public=false, show_ui=true, show_in_rest=true for the picker) with seeded terms: `back-the-pack` "Back the Pack Partner", `campaign-sponsor` "Campaign Sponsor", `provider` "K9 Provider", `community` "Community Partner".

Rewrite extras (Registrar): for each listing page slug in `['stories','events','campaigns','barkode','meet-the-team','hk9-current-teams-in-training','back-the-pack','photos','news']` add top rule `{slug}/page/([0-9]+)/?$ → index.php?pagename={slug}&paged=$matches[1]`. Filter `hk9_barkode_rewrite_rules` removes embed/attachment/feed rules. `pre_get_posts`: main frontend query whose `post_type` includes `hk9_barkode` (and is not singular) → `set_404()`. Admin guard: listing pages cannot be chosen as `post_parent` (filter `page_attributes_dropdown_pages_args` + `wp_insert_post_data` reject).

Admin columns: story (featured image, veteran display name, canine, featured, order), team (image, canine, status, featured), person (portrait, role, order), partner (logo, type, website, order), campaign (image, status, order, sponsors count), event (start, end, venue, status, upcoming/past), barkode (photo, registry_id, program_type, handler present?, legacy path). Sortable by order/date/start; filters by status/type.

## 4. Field framework (`HK9\Core\Fields`)

Each field is an array definition: `['type'=>..., 'key'=>..., 'label'=>..., 'help'=>..., 'default'=>..., 'required'=>bool, 'placeholder'=>..., ...type options]`. Field types and their **stored PHP value** (what `hk9_section()` returns and what the payload writes):

| type | options | stored value |
|---|---|---|
| `text` | `maxlength` | string |
| `textarea` | `rows` | string (newlines kept) |
| `richtext` | — (wp_editor teeny, kses) | string (HTML, `wp_kses_post`) |
| `number` | `min`,`max`,`step` | int or float |
| `toggle` | — | bool |
| `select` | `options` (value=>label), `multiple` | string (or string[] if multiple) |
| `icon` | — (select from lucide sprite list, with preview) | string (icon name, validated against `hk9_icon_name_list()`) |
| `image` | `size` (preview size) | int attachment id (0 = none) |
| `gallery` | `max` | int[] attachment ids |
| `file` | `mimes` | int attachment id |
| `link` | `allow_target`, `allow_internal` | object `{label:string, url:string, post_id:int, target:'_self'|'_blank', rel:string}` — when `post_id>0` the frontend uses `get_permalink(post_id)` and ignores `url` |
| `datetime` | `time_optional` | string `Y-m-d H:i` (site-local) or `Y-m-d` |
| `date` | — | string `Y-m-d` |
| `color` | — | string `#rrggbb` |
| `relationship` | `post_type` (string|array), `multiple`, `max`, `orderable` | int or int[] (post ids, validated to exist + be of type) |
| `repeater` | `fields` (array of field defs), `min`,`max`,`item_label` (field key used as row title), `collapsible` | array of associative arrays (row order = display order) |
| `group` | `fields` | associative array |

Renderer produces server HTML with `name` attributes following the section key: `hk9_sec_hero[heading]`, `hk9_sec_features[items][2][icon]`; every section form gets hidden `hk9_sec_<id>[__present]=1`. Repeaters: template row in `<template>`, add/remove/move-up/move-down buttons (keyboard accessible), native drag handle, empty state text, row title live-updates from `item_label`. Image/gallery/file: `wp.media` picker with preview, Replace, Remove. Link: tabs "Page/record" (search via `hk9/v1/pick`) / "External URL", plus label + "open in new tab". Relationship: searchable multi-select with sortable chips (`hk9/v1/pick?type=&s=`). All controls have `<label for>`, help text via `aria-describedby`, required markers.

`Sanitizer::sanitize(array $fields, mixed $input): array` is pure/idempotent: coerces per type, applies defaults for missing keys, drops unknown keys, validates ids/icons/urls, and always yields output that passes the section's REST schema. `Schema::for(array $fields): array` builds the JSON schema (type object, `properties`, `additionalProperties=false`, repeaters as `array` of objects, each property carries `default`).

## 5. Sections (page templates)

Registration: `Sections\Registry` loads `Sections/definitions/*.php`, each returning `['template'=>'about', 'label'=>..., 'sections'=>[ ['id'=>'hero','label'=>...,'fields'=>[...],'defaults'=>[...],'can_hide'=>bool,'can_reorder'=>bool], ...]]`. Meta key per section: `hk9_sec_<id>` **shared across templates that use the same section id and field set** (e.g. `hero_band`) — if two templates need different field sets, use distinct ids. Additionally every templated page has meta `hk9_sections_layout` = `{order: string[], hidden: string[]}` (object schema) edited in a "Page sections" side panel (drag/keyboard reorder + show/hide checkboxes), defaulting to the template's reference order.

Meta registration follows the validated design: `register_post_meta('page', key, ['type'=>'object','single'=>true,'revisions_enabled'=>true,'show_in_rest'=>['schema'=>Schema::for(fields)+['default'=>defaults],'prepare_callback'=>...],'sanitize_callback'=>Sanitizer,'auth_callback'=>edit_post])` with **no top-level `default`**. Also `add_filter("sanitize_post_meta_{key}_for_revision", ...)`, `rest_pre_insert_page` pre-sanitize, `wp_restore_post_revision` prio 9/11 absent-key protection, `admin_action_editpost` prio 5 classic-preview fallback, `save_post_page` prio 10 template-scoped write (never delete), `wp_ajax_hk9_section_panel` for template switch, and `sections.js` mirrors values synchronously into `wp.data.dispatch('core/editor').editPost({meta})` when the block editor is present.

Template slugs (file names in `theme/page-templates/`, `Template Name:` in comments): `home.php` "Home (sections)", `about.php` "About / Mission", `program.php` "Program", `veterans.php` "For Veterans", `get-involved.php` "Get Involved", `barkode.php` "BarKode Program", `stories.php` "Stories (listing)", `contact.php` "Contact", `landing.php` "Landing Page", `donate.php` "Donate", `events.php` "Events (listing)", `campaigns.php` "Campaigns (listing)", `partners.php` "Partners (Back the Pack)", `people.php` "People (Meet the Team)", `teams.php` "Teams (listing)", `highlighted-team.php` "Highlighted Team", `gallery.php` "Photo Gallery", `application.php` "Application", plus the default template (`page.php`) which renders the `hero_band` + block content card. `front-page.php` renders the page assigned in Reading settings using the `home` template sections (the Home page must have template `home.php` assigned; front-page.php delegates to it).

### 5.1 Shared section definitions

- `hero_band` (templates: about, veterans, get-involved, stories, contact, landing, donate, events, campaigns, partners, people, teams, highlighted-team, gallery, application, default page): `eyebrow` text, `heading` text (default = page title when empty), `text` textarea (default = excerpt when empty), `pattern` select (`none`|`stars`|`grid`), `show` handled by layout.
- `hero_image` (templates: home, program, barkode): `eyebrow` text, `eyebrow_icon` icon, `heading` text, `heading_break_after` text (word after which a `<br class="hk9-md-br">` is inserted; default "Never" on home, empty elsewhere), `text` textarea, `image` image, `image_mobile` image, `focal` select (`center`|`top`|`bottom`|`left`|`right`), `height` select (`85vh`|`70vh`|`60vh`), `overlay` select (`60`|`70`|`80`), `gradient` toggle, `buttons` repeater(max 2){ `link` link, `style` select(`primary`|`outline-light`) }, `animate` toggle.
- `cta_band` (all templates): `heading` text, `text` textarea, `buttons` repeater(max 2){ link, style select(`primary`|`outline`|`outline-light`|`ghost`) }, `tone` select (`navy`|`tint`|`plain`|`muted`).
- `feature_cards`: `heading` text, `intro` textarea, `divider` toggle, `align` select(`center`|`left`), `columns` select(2|3), `cards` repeater{ `icon` icon, `tone` select(`navy`|`crimson`), `title` text, `text` textarea, `link` link, `decorate` toggle (corner blob) }.
- `faq`: `heading` text, `intro` textarea, `items` repeater{ `question` text, `answer` richtext }, `source_note` text.

### 5.2 Per-template sections (reference order = default order)

- **home**: `hero_image`, `mission` { `heading` text, `divider` toggle, `text` textarea }, `feature_cards` (id `features`), `barkode_feature` { `eyebrow` text, `heading` text, `text` textarea, `image` image, `image_caption` text, `button` link, `pattern` toggle }, `testimonial` { `source` select(`story`|`manual`), `story` relationship(hk9_story), `quote` textarea, `name` text, `meta` text, `image` image, `button` link }.
- **about**: `hero_band`, `legacy` { `eyebrow` text, `heading` text, `body` richtext, `image` image, `image_side` select(`left`|`right`) }, `values` = `feature_cards` variant with `tone` fixed crimson & muted cards (id `values`, same fields as feature_cards), `cta_band` (id `cta`).
- **program**: `hero_image`, `steps` { `heading` text, `intro` textarea, `steps` repeater{ `icon` icon, `title` text, `text` textarea } }, `providers` { `icon` icon, `heading` text, `text` textarea, `button` link }, `cta_band` (id `cta`).
- **veterans**: `hero_band` (pattern stars default), `questions` { `heading` text, `intro` textarea, `items` repeater{ `question` text }, `footer_text` textarea, `button` link, `secondary_link` link }, `expect` { `heading` text, `steps` repeater{ `title` text, `text` textarea }, `card_icon` icon, `card_title` text, `card_text` textarea, `card_button` link }, `cta_band` (id `ada`).
- **get-involved**: `hero_band`, `ways` = `feature_cards` variant with buttons (fields: `cards` repeater{ icon, tone, title, text, `button` link }), `partners` { `icon` icon, `heading` text, `body` richtext, `button` link, `show_logos` toggle }.
- **barkode**: `hero_image`, `story` { `icon` icon, `heading` text, `body` richtext }, `feature_cards` (id `protects`), `cta_band` (id `cta`).
- **stories**: `hero_band`, `featured` { `source` select(`story`|`manual`), `story` relationship(hk9_story), `quote` textarea, `name` text, `meta` text, `image` image }, `list` { `heading` text, `mode` select(`auto`|`manual`), `stories` relationship(hk9_story, multiple, orderable), `count` number, `empty_text` textarea }, `teams` { `heading` text, `intro` textarea, `mode` select(`auto`|`manual`), `teams` relationship(hk9_team, multiple), `show` toggle }, `cta_band` (id `cta`).
- **contact**: `hero_band`, `info` { `heading` text, `rows` repeater{ `source` select(`phone`|`phone_secondary`|`email`|`hours`|`address`|`custom`), `icon` icon, `label` text, `value` textarea, `link` link } }, `form` { `heading` text, `intro` textarea, `form` select(`contact`|`application`), `success_heading` text, `success_text` textarea }.
- **landing**: `hero_band`, `feature_cards` (id `cards`, hidden by default), `faq` (hidden by default), `tiers` { `heading` text, `intro` textarea, `items` repeater{ `name` text, `price` text, `quantity` text, `benefits` textarea (one per line), `highlight` toggle, `button` link }, hidden by default }, `cta_band` (id `cta`, hidden by default). Block content renders in the overlap card after the hero.
- **donate**: `hero_band`, `options` { `heading` text, `intro` textarea, `items` repeater{ `icon` icon, `logo` image, `title` text, `text` textarea, `button` link, `primary` toggle } }, `mail_in` { `heading` text, `text` textarea, `use_settings_address` toggle }, `tax` { `text` textarea (default from settings EIN statement) }, `cta_band` (id `cta`).
- **events**: `hero_band`, `upcoming` { `heading` text, `empty_text` textarea, `count` number }, `past` { `show` toggle, `heading` text, `count` number }, `cta_band` (id `cta`).
- **campaigns**: `hero_band`, `list` { `heading` text, `intro` textarea, `mode` select(`auto`|`manual`), `campaigns` relationship(hk9_campaign, multiple, orderable), `show_sponsors` toggle }, `cta_band` (id `cta`).
- **partners**: `hero_band`, `logos` { `heading` text, `intro` textarea, `mode` select(`auto`|`manual`), `type` relationship-like select of `hk9_partner_type` term slug (`select` with options from terms, default `back-the-pack`), `partners` relationship(hk9_partner, multiple, orderable), `columns` select(3|4|5|6) }, `cta_band` (id `cta`).
- **people**: `hero_band`, `grid` { `heading` text, `intro` textarea, `mode` select(`auto`|`manual`), `people` relationship(hk9_person, multiple, orderable), `columns` select(2|3) }, `cta_band` (id `cta`).
- **teams**: `hero_band`, `list` { `heading` text, `intro` textarea, `mode` select(`auto`|`manual`), `status` select(`all`|`in-training`|`graduated`|`therapy`), `teams` relationship(hk9_team, multiple, orderable) }, `cta_band` (id `cta`).
- **highlighted-team**: `hero_band`, `team` { `team` relationship(hk9_team), `heading` text, `intro` textarea }, `cta_band` (id `cta`).
- **gallery**: `hero_band`, `gallery_options` { `lightbox` toggle, `columns` select(2|3|4), `captions` toggle, `images` gallery (used when block content has no gallery) }.
- **application**: `hero_band`, `intro` (block content), `form` { `heading` text, `notice` textarea, `success_page` link, `show_five_questions_link` toggle }.

Frontend contract: theme templates call `hk9_section( $post_id, 'hero_band' )` etc. (returns array with defaults, never null) and `hk9_sections_layout( $post_id, 'about' )` (returns ordered visible section ids). Template parts live in `theme/template-parts/sections/<section-type>.php` and receive `$args['data']` (the section array) + `$args['post_id']`.

## 6. CPT meta (registered via `Meta\Registry`, edited with the same field framework in a "Details" meta box)

- `hk9_story`: `veteran_name` text (approved public display name), `branch` text, `canine_name` text, `relationship` select(`service`|`therapy`|`in-training`), `pairing_year` text (only if verified), `quote` textarea, `featured` toggle, `gallery` gallery, `team` relationship(hk9_team), `source_note` text (admin-only; not rendered). Order = `menu_order`. Story body = post_content, image = featured image.
- `hk9_team`: `canine_name` text, `handler_name` text (approved public), `status` select(`in-training`|`graduated`|`therapy`), `year` text, `featured` toggle, `gallery` gallery, `donate_link` link, `barkode` relationship(hk9_barkode), `summary` textarea. Body = post_content.
- `hk9_person`: `role` text, `email` text (org domain only; validated), `links` repeater{ link }, `quote` textarea. Bio = post_content, portrait = featured image, order = `menu_order`.
- `hk9_partner`: `website` link, `tier` text, `since` text. Logo = featured image, description = post_content, order = `menu_order`, type = `hk9_partner_type` terms.
- `hk9_campaign`: `summary` textarea, `status` select(`active`|`completed`|`paused`), `start` date, `end` date, `cta` link, `secondary_cta` link, `sponsors` relationship(hk9_partner, multiple, orderable), `gallery` gallery, `goal_text` text (only when sourced), `featured` toggle. Body = post_content, order = `menu_order`.
- `hk9_event`: `start` datetime, `end` datetime, `all_day` toggle, `time_tbd` toggle, `timezone` select (site tz default; list of common US zones), `venue` text, `address` textarea, `registration` link, `ticket_info` textarea, `organizer_name` text, `organizer_contact` text, `status` select(`scheduled`|`cancelled`|`postponed`), `featured` toggle, `flyer` image. Helper `hk9_event_is_upcoming($id)`, `hk9_event_datetime_range($id)` (formatted), `hk9_events_query($args)` (`upcoming`|`past`, ordered by start asc/desc).
- `hk9_barkode`: `dog_name` text, `program_type` select(`service`|`therapy`|`in-training`), `registry_id` text, `legacy_path` text (e.g. `/hk923-005/`; seeds a redirect), `breed` text, `task_description` text, `tasks` textarea, `handler_name` text, `emergency_contact` textarea, `vet_contact` textarea, `certification` text, `do_not_separate` toggle, `notice` textarea, `contact_line` text (default from settings), `id_card_images` gallery, `review_notes` textarea (**never rendered, not in REST**; admin-only), `status_note` text. Photo = featured image.

## 7. Settings (`hk9_settings` option, one array; `Settings\Schema` defines typed keys with defaults; `hk9_option('group.key', $default)` reads with dot-notation)

Tabs (Heartland → Settings, `hk9_manage_settings`): **Branding** (`branding.header_logo` image [fallback: core custom_logo], `branding.footer_logo` image, `branding.header_logo_height` number 64, `branding.footer_logo_height` number 80, `branding.wordmark_line1` "Heartland K9s", `branding.wordmark_line2` "For Veterans", `branding.show_wordmark` bool, `branding.site_icon_note` (links to Customizer)); **Colors & Fonts** (`colors.primary` "#1c2f4a", `colors.secondary` "#b82e45", `colors.background` "#fbfaf9", `colors.foreground` "#15191f" [computed from hsl(220 30% 12%)], `colors.muted` "#f3f0eb", `colors.muted_foreground` "#52637a", `colors.border` "#e5e0dc", `colors.accent` "#e6dfd6", `fonts.serif` select(`fraunces`|`system-serif`), `fonts.sans` select(`inter`|`system-sans`)); **Contact** (`contact.phone_main` "800-913-6189", `contact.phone_main_label` "Main", `contact.phone_secondary` "417-312-7484", `contact.phone_secondary_label` "Director cell", `contact.email` "info@heartlandk9s.org", `contact.email_director` "director@heartlandk9s.org", `contact.email_development` "development@heartlandk9s.org", `contact.address_line1` "12651 Gateway Dr", `contact.address_line2` "", `contact.city` "Neosho", `contact.state` "MO", `contact.zip` "64850", `contact.hours` "8:00am – 5:00pm", `contact.hours_days` "" , `contact.service_area` "", `contact.facebook` url, `contact.instagram` url, `contact.youtube` url, `contact.linkedin` url, `contact.x` url, `contact.candid_url`, `contact.show_guidestar_seal` bool true, `contact.ein` "47-4991572", `contact.legal_name` "Heartland Canines for Veterans Inc", `contact.tax_statement` text); **Destinations** (`links.donate` link → /donate/ page, `links.donate_external` link → Zeffy URL, `links.paypal_hosted_button_id` text, `links.amazon_wishlist` link, `links.application` link → /online-application/, `links.five_questions` link, `links.ada` link, `links.volunteer` link, `links.provider` link → /the-service-k9-program/, `links.campaigns` link, `links.events` link, `links.stories` link, `links.contact` link, `links.gear` link → Links Ink, `links.coloring_book` link, `links.obedience` link, `links.teams` link, `links.people` link, `links.photos` link, `links.barkode` link); **Header** (`header.cta_label` "Donate Now", `header.cta_link` link (default = links.donate), `header.show_cta` bool, `header.sticky` bool true); **Footer** (`footer.description` text, `footer.tagline` "So They Never Walk Alone.", `footer.col2_heading` "Quick Links", `footer.col3_heading` "Get Involved", `footer.col4_heading` "Contact Us", `footer.copyright` "© {year} Heartland Canines for Veterans. All rights reserved.", `footer.credit` "Built with ♥ for our veterans.", `footer.show_seal` bool); **Blog** (`blog.layout` select(`list`|`grid`), `blog.hero_title` "News", `blog.hero_text`, `blog.hero_image` image, `blog.show_author` bool, `blog.show_date` bool true, `blog.show_categories` bool true, `blog.show_tags` bool true, `blog.show_related` bool true, `blog.related_count` 3, `blog.show_featured_image` bool true); **Forms** (`forms.contact_recipients` textarea (one per line), `forms.application_recipients`, `forms.from_name`, `forms.from_email`, `forms.subjects` repeater{ `value`, `label` } default 5 reference subjects, `forms.rate_limit` number 5/hour, `forms.retention_days` 90, `forms.store_submissions` bool true, `forms.contact_success_text`, `forms.application_success_page` link → /thank-you/); **Analytics** (`analytics.fathom_site_id` text); **Advanced** (`advanced.editors_manage_registry` bool false, `advanced.purge_on_uninstall` bool false, `advanced.disable_user_enumeration` bool true, `advanced.output_seo_meta` bool true).

Theme reads settings only via `hk9_option()`; when the plugin is missing the theme's `inc/defaults.php` provides the same defaults (phones/emails empty except for reference-verified defaults listed above — the theme ships the same defaults so a plugin-less activation still shows correct contact info; documented).

## 8. Payload & importer — see the validated design in `discovery/design-validation.json` (importDesign + importJudge). Summary of the contract the payload builder must emit:

`payload/manifest.json` = `{ "format":"hk9-payload/1", "generated_at":ISO, "sources":{...}, "records":[...] }`. Record shapes:
- attachment: `{key, type:"attachment", file, sha256, mime, size, title, alt, caption, description, date, parent?:"{{post:K}}", sensitive?:bool}`
- term: `{key:"term:post_tag:poker-run", type:"term", taxonomy, slug, name, description?}`
- post-like: `{key, type:"page"|"post"|"hk9_story"|..., status, slug, title, parent?, template?, content?: "content/<file>.html", excerpt?, date?, menu_order?, featured?: "{{media:K}}", meta:{ key:{type, value} }, terms:{ taxonomy:[ "{{term:...}}" | "slug" ] }, sensitive?:bool}`
- menu: `{key:"menu:primary", type:"menu", name, locations:[...], items:[{key, kind:"post_type"|"custom", object?:"{{post:K}}", url?, title?, target?, classes?, parent?:itemKey, order}]}`
- option: `{key:"option:hk9_settings", type:"option", name, merge:"deep"|"replace", value}`
- reading: `{key:"reading", type:"reading", show_on_front:"page", page_on_front:"{{post:K}}", page_for_posts:"{{post:K}}", posts_per_page:10}`
- redirect: `{key:"redirect:/hk923-005/", type:"redirect", from, to: "{{post_url:K}}"|path|{type:"record",slug}, status:301}`
Tokens: `{{media:K}}` → attachment id; `{{media_url:K}}` (scaled/full URL) and `{{media_url:K|original}}`; `{{post:K}}` → id; `{{post_url:K}}` → permalink; `{{term:tax:slug}}` → term id. Source keys: `live:page:<id>`, `live:media:<id>`, `ref:page:<route-slug>` (home, about, program, veterans, get-involved, barkode, stories, contact), `ref:asset:<name>`, `live:person:<slug>`, `live:partner:<slug>`, `live:campaign:<slug>`, `live:event:<slug>`, `live:team:<slug>`, `live:story:<slug>`, `live:barkode:<live-page-id>`, `new:page:<slug>` (pages that exist on neither source, e.g. `news`), `term:<tax>:<slug>`, `menu:<location>`, `option:<name>`, `reading`, `redirect:<from>`.

Block content in `content/*.html` uses standard block markup with image blocks as `<!-- wp:image {"id":{{media:K}},"sizeSlug":"large"} --><figure class="wp-block-image size-large"><img src="{{media_url:K}}" alt="..." class="wp-image-{{media:K}}"/></figure><!-- /wp:image -->`; the importer replaces tokens inside JSON attributes and HTML alike and validates `parse_blocks()` count before/after.

## 9. Redirects
Option `hk9_redirects` `{version:1, rules:{ "<normalized-source>": {to:{type:"post",id}|{type:"path",path}|{type:"record",slug}, status:301|302|410, enabled:bool, seed:bool, note, updated, by} }}`. Resolver on `parse_request` prio 1 (GET/HEAD; skip admin/REST/cron), key normalization = urldecode → lowercase → ensure leading+trailing slash; query rules keyed `/?k=v`; preserve remaining query string; runtime loop guard; `wp_safe_redirect($url, $status, 'hk9-legacy')` + `Cache-Control: public, max-age=86400`; `pre_redirect_guess_404_permalink` returns `false` (no guessing) site-wide. Admin: Heartland → Redirects list table (add/edit/delete/enable/test), CLI `wp hk9 redirects list|add|delete|seed|test [--http]`.

## 10. Forms
Two forms rendered by the theme via `hk9_render_form('contact'|'application', $args)` (plugin provides markup + handler; theme provides CSS). POST to `admin-post.php?action=hk9_form_submit` (works without JS; JS enhances with fetch to `hk9/v1/forms/{id}` returning the same result JSON). Protections: nonce, honeypot field `hk9_website` (must be empty), time-trap (`hk9_ts` ≥ 3 s old, ≤ 6 h), per-IP-hash rate limit (`forms.rate_limit`/hour via transients, hashed with a daily salt), duplicate token (`hk9_token` single-use transient), field length caps, `sanitize_email`/`is_email`, header injection impossible (no user input in headers except Reply-To validated email). On success: optional `hk9_submission` record (title = form + date; fields in private meta; IP stored only as hash; retention cron deletes after `forms.retention_days`), `wp_mail` to recipients (subject prefixed `[HK9 Contact]`/`[HK9 Application Inquiry]`), then redirect to the page with `?hk9_form=contact&status=sent&t=<token>` (no PII in URL) or the configured success page; on failure re-render with inline errors + error summary (`role=alert`, focus moved). `wp_mail` result is recorded on the submission (`mail_sent` bool) and shown in admin.

Contact fields: `first_name`*, `last_name`*, `email`*, `subject`* (select from settings), `message`*. Application-inquiry fields: `first_name`*, `last_name`*, `email`*, `phone`, `city`, `state`, `applicant_type`* (select: `veteran`|`family_member`|`other`), `heard_from` (select), `message`*, `read_five_questions`* (checkbox). No medical/disability questions.

## 11. Theme CSS/markup contract

Tokens as CSS custom properties on `:root` (emitted by the theme from settings): `--hk9-primary`, `--hk9-secondary`, `--hk9-bg`, `--hk9-fg`, `--hk9-muted`, `--hk9-muted-fg`, `--hk9-border`, `--hk9-accent`, `--hk9-card`, `--hk9-radius: .25rem`, `--hk9-font-serif`, `--hk9-font-sans`, `--hk9-header-h: 80px`, `--hk9-logo-h: 64px`, `--hk9-footer-logo-h: 80px`. Bootstrap maps: breakpoints/containers 640/768/1024/1280/1536; `.container` padding 16px (<768) / 32px (≥768).

Key classes (BEM): `.hk9-header` (`--sticky`, `__inner`, `__brand`, `__logo`, `__wordmark`, `__nav`, `__nav-link` (`is-active`), `__divider`, `__cta`, `__toggle`, `__mobile` (`is-open`), `__mobile-link`), `.hk9-hero` (`--image`, `--band`, `--h85`, `--h70`, `--h60`, `__bg`, `__overlay`, `__gradient`, `__badge`, `__title`, `__text`, `__actions`), `.hk9-section` (`--py24`, `--py20`, `--tint`, `--navy`, `--muted`, `--bordered`), `.hk9-container` (= `.container`), `.hk9-narrow` (max 896px), `.hk9-wide` (max 1024px), `.hk9-divider` (96×4 crimson bar), `.hk9-card` (`--center`, `--muted`, `--hover`, `__icon` (`--navy`, `--crimson`), `__title`, `__text`, `__link`, `__blob`), `.hk9-overlap` (the −64px card), `.hk9-timeline` (`__item`, `__num`, `__line`), `.hk9-testimonial` (`__media`, `__body`, `__quote`, `__author`), `.hk9-cta-band`, `.hk9-btn` (Bootstrap `.btn` extended: `--primary` crimson, `--navy`, `--outline`, `--outline-light`, `--ghost`, `--lg` 56px, `--full`), `.hk9-form` (`__field`, `__label`, `__input`, `__error`, `__summary`, `__success`), `.hk9-footer` (`__grid`, `__brand`, `__col`, `__heading`, `__list`, `__bottom`), `.hk9-prose` (block content styles), `.hk9-blog` (`__list`, `__card`, `__meta`, `__pagination`), `.hk9-record` (BarKode registry layout), `.hk9-gallery`, `.hk9-people`, `.hk9-partners`, `.hk9-events`, `.hk9-campaigns`, `.hk9-teams`, `.hk9-skip-link`, `.hk9-visually-hidden`.

Helpers (theme `inc/template-tags.php`): `hk9_icon(string $name, array $attrs = []): string`, `hk9_image(int $id, string $size, array $attrs = [], bool $eager = false): string`, `hk9_button(array $link, string $style, array $attrs = []): string`, `hk9_the_hero(array $data, string $variant)`, `hk9_section_open/close($id, $classes)`, `hk9_pagination()`, `hk9_post_card($post)`, `hk9_link_url(array $link): string`, `hk9_link_attrs(array $link): string`.
