# payload-mini — synthetic importer fixture

A tiny `hk9-payload/1` source tree used to exercise every importer step:

- 3 generated images (`generate.mjs`, sharp): a 2800×1400 JPEG (forces `-scaled` + `original_image`), a PNG card, and a PNG marked `sensitive` (title/alt/caption must be scrubbed on import and withheld from logs).
- `mini:asset:card-copy`: a byte-identical copy of the card under its own key (added by hand to `media-index.json`, referenced by an image block on the history page) so the importer's shared-attachment path is exercised: one attachment, two map rows, re-run skips, one rollback deletion.
- 4 pages: `mini:page:home` (template `home.php`, `hk9_sec_hero_image` meta with a deferred `{{post_url}}` link), `mini:page:about` (template `about.php`, `hk9_sec_hero_band` + `hk9_sec_legacy` + `hk9_sections_layout` meta), `mini:page:history` (child of about), `mini:page:news` (posts page).
- 1 `hk9_story` with typed meta + a `post_tag` term token, 1 term, 1 menu (post_type + custom + nested items, location `mini`), `option:hk9_settings` deep-merge, reading settings, 2 redirects (one `{type:"record"}` target).

Build it into the plugin's mounted tests directory (visible to the docker stack):

```
node tools/build-payload.mjs --src=tools/fixtures/payload-mini --out=plugin/heartland-k9s-core/tests/payload-mini \
  --media-index=tools/fixtures/payload-mini/media-index.json --copy-media
tools/wp.sh hk9 import /var/www/html/wp-content/plugins/heartland-k9s-core/tests/payload-mini --user=admin --dry-run
```

All keys are prefixed `mini:` so the fixture never collides with production payload records in the import map.
