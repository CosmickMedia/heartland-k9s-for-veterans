#!/usr/bin/env node
/**
 * URL coverage report — proves that no important live URL is lost by the in-place migration.
 *
 *   node tools/url-coverage.mjs [--suite=<results.json>] [--probe-live] [--sitemap=<file>] [--out=<file>]
 *
 * Inputs
 *   urls-internal_all.csv                  Screaming Frog crawl of https://heartlandk9s.org/ (350 rows)
 *   https://heartlandk9s.org/sitemap-post-type-page.xml  fetched read-only (40 page URLs; --sitemap=<file> reads a saved copy)
 *   docs/migration-map.md                  the per-page dispositions (ids, new URLs, redirects)
 *   payload-lite/manifest.json             live_path of every live:media:<id> record (the file the live site keeps)
 *   payload/media-index.json               served_full_url (the -scaled rendition WordPress serves for big originals)
 *   payload-src/records/redirects.json     payload redirect rules
 *   plugin/…/src/Redirects/Store.php       the plugin's seeded redirect rules
 *   tools/url-matrix.sh                    which URLs the curl matrix asserts
 *   --suite=<json>                         results written by plugin/heartland-k9s-core/tests/url-coverage-suite.sh
 *                                          (the fresh-stack simulation); when omitted the numbers embedded in the
 *                                          previous report are reused.
 *   --probe-live                           HEAD the sitemap-only URLs on the live site once (read-only) to record
 *                                          their live status; otherwise the statuses embedded in the previous report
 *                                          are reused.
 *
 * Output: docs/reports/url-coverage.md — one row per crawled/sitemapped URL: type, live status, post-migration
 * expectation and how it is verified (url-matrix row, adoption rule, file untouched, simulation result).
 */
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(new URL('..', import.meta.url).pathname);
const args = Object.fromEntries(process.argv.slice(2).map(a => { const m = a.match(/^--([^=]+)(?:=(.*))?$/); return m ? [m[1], m[2] ?? true] : [a, true]; }));
const OUT = path.resolve(root, typeof args.out === 'string' ? args.out : 'docs/reports/url-coverage.md');
const LIVE = 'https://heartlandk9s.org';
const UA = 'hk9-url-coverage (read-only verification; tools/url-coverage.mjs)';

/* ------------------------------------------------------------------ inputs */

function parseCsv(text) {
  const rows = []; let row = [], field = '', quoted = false;
  for (let i = 0; i < text.length; i++) {
    const c = text[i];
    if (quoted) {
      if (c === '"') { if (text[i + 1] === '"') { field += '"'; i++; } else quoted = false; }
      else field += c;
    } else if (c === '"') quoted = true;
    else if (c === ',') { row.push(field); field = ''; }
    else if (c === '\n') { row.push(field); rows.push(row); row = []; field = ''; }
    else if (c !== '\r') field += c;
  }
  if (field !== '' || row.length) { row.push(field); rows.push(row); }
  return rows;
}

const csvText = fs.readFileSync(path.join(root, 'urls-internal_all.csv'), 'utf8').replace(/^﻿/, '');
const csvRows = parseCsv(csvText);
const header = csvRows[0];
const col = name => { const i = header.indexOf(name); if (i < 0) throw new Error(`CSV column missing: ${name}`); return i; };
const crawl = csvRows.slice(1).filter(r => r.length > 5).map(r => ({
  url: r[col('Address')],
  contentType: (r[col('Content Type')] || '').split(';')[0].trim(),
  status: r[col('Status Code')],
  indexability: r[col('Indexability')],
  redirect: r[col('Redirect URL')] || '',
}));
if (crawl.length !== 350) console.warn(`warning: expected 350 crawl rows, parsed ${crawl.length}`);

// Previous report state (suite results + live probe) survives re-runs without the flags.
let previousState = {};
if (fs.existsSync(OUT)) {
  const m = fs.readFileSync(OUT, 'utf8').match(/<!-- hk9-url-coverage-state\n([\s\S]*?)\n-->/);
  if (m) { try { previousState = JSON.parse(m[1]); } catch { previousState = {}; } }
}

async function loadSitemap() {
  if (typeof args.sitemap === 'string') return fs.readFileSync(path.resolve(root, args.sitemap), 'utf8');
  const res = await fetch(`${LIVE}/sitemap-post-type-page.xml`, { headers: { 'user-agent': UA }, signal: AbortSignal.timeout(30000) });
  if (!res.ok) throw new Error(`sitemap fetch failed: HTTP ${res.status}`);
  return res.text();
}
const sitemapXml = await loadSitemap();
const sitemapUrls = [...sitemapXml.matchAll(/<loc>([^<]+)<\/loc>/g)].map(m => m[1].trim());
const sitemapImages = [...sitemapXml.matchAll(/<image:loc>([^<]+)<\/image:loc>/g)].map(m => m[1].trim());
const sitemapFetchedAt = typeof args.sitemap === 'string' ? (previousState.sitemapFetchedAt || 'saved copy') : new Date().toISOString();

// migration-map.md — Pages table + "Other live URLs" table.
const mapText = fs.readFileSync(path.join(root, 'docs/migration-map.md'), 'utf8');
const mapPages = new Map();
for (const line of mapText.split('\n')) {
  const m = line.match(/^\| `([^`]+)` \| (.*?) \| (Migrated[^|]*|Decomposed|Replaced|Draft[^|]*|Registry|Redirect|Kept[^|]*|Not preserved) \| ([^|]*) \| ([^|]*) \| ([^|]*) \|/);
  if (!m) continue;
  const id = (m[2].match(/\((\d+)\)\s*$/) || [])[1];
  mapPages.set(m[1], { live: m[1], title: m[2], id: id ? Number(id) : null, disposition: m[3].trim(), newUrl: m[4].trim(), template: m[5].trim(), redirect: m[6].trim() });
}

// Payload: live upload paths.
const lite = JSON.parse(fs.readFileSync(path.join(root, 'payload-lite/manifest.json'), 'utf8'));
const liveMedia = new Map(); // live_path -> {key,id,...}
for (const r of lite.records) {
  if (r.type === 'attachment' && r.live_path) liveMedia.set(r.live_path, { key: r.key, id: Number(r.key.split(':')[2]), width: r.width, height: r.height, bytes: r.bytes, mime: r.mime });
}
const mediaIndex = JSON.parse(fs.readFileSync(path.join(root, 'payload/media-index.json'), 'utf8')).items;
const served = new Map(); // served relative path (e.g. 2021/03/x-scaled.jpg) -> {key, sourcePath}
for (const [key, item] of Object.entries(mediaIndex)) {
  if (!key.startsWith('live:media:')) continue;
  const rel = u => (u || '').replace(`${LIVE}/wp-content/uploads/`, '');
  if (item.served_full_url && item.served_full_url !== item.source_url) served.set(rel(item.served_full_url), { key, sourcePath: rel(item.source_url) });
}

// Redirect rules: payload + plugin seeds.
const payloadRedirects = JSON.parse(fs.readFileSync(path.join(root, 'payload-src/records/redirects.json'), 'utf8'));
const storePhp = fs.readFileSync(path.join(root, 'plugin/heartland-k9s-core/src/Redirects/Store.php'), 'utf8');
const seedSlugs = [...((storePhp.match(/RECORD_SLUGS = \[([\s\S]*?)\];/) || [])[1] || '').matchAll(/'([^']+)'/g)].map(m => m[1]);
const seedPaths = new Map([...storePhp.matchAll(/\$rules\['([^']+)'\]\s*=\s*\[\s*'to'\s*=>\s*\$path\(\s*'([^']+)'\s*\)/g)].map(m => [m[1], m[2]]));
for (const s of seedSlugs) seedPaths.set(`/${s}/`, `/barkode/${s}/`);
const payloadRedirectFrom = new Map(payloadRedirects.map(r => [r.from, r]));

// url-matrix.sh coverage.
const matrixText = fs.readFileSync(path.join(root, 'tools/url-matrix.sh'), 'utf8');
const matrix = new Map(); // path -> "200" | "301 → x"
for (const m of matrixText.matchAll(/for p in ([^;]+); do check "\$p" (\d+); done/g)) for (const p of m[1].trim().split(/\s+/)) matrix.set(p, m[2]);
for (const m of matrixText.matchAll(/for s in ([^;]+); do\n([\s\S]*?)\ndone/g)) {
  const slugs = m[1].trim().split(/\s+/);
  for (const c of m[2].matchAll(/check "([^"]+)" (\d+)(?: "([^"]*)")?/g)) for (const s of slugs) matrix.set(c[1].replace(/\$s/g, s), c[2] + (c[3] ? ` → ${c[3].replace(/\$s/g, s)}` : ''));
}
for (const m of matrixText.matchAll(/^\s*(?:if .*?\|\| .*?; then )?check "([^"$]+)" (\d+)(?: "([^"]*)")?/gm)) matrix.set(m[1], m[2] + (m[3] ? ` → ${m[3].replace('$BASE', '')}` : ''));

// Simulation results.
let suite = previousState.suite || null;
if (typeof args.suite === 'string') suite = JSON.parse(fs.readFileSync(path.resolve(args.suite), 'utf8'));
const suiteResult = (p) => suite && suite.results && suite.results[p] ? suite.results[p] : null;

/* --------------------------------------------------------------- classify */

const rel = url => url.replace(/^https?:\/\/heartlandk9s\.org/, '');
const registryPaths = new Set([...seedSlugs.map(s => `/${s}/`), '/master_template_barkode/']);
const draftRedirects = new Set(['/success/', '/success/sample-success-story/']);

function classify(row) {
  const url = row.url;
  const p = rel(url);
  if (url.startsWith('http://')) return { type: 'http redirect' };
  if (p.startsWith('/author/')) return { type: 'author archive' };
  if (p.startsWith('/wp-content/uploads/cache/')) {
    // FooGallery: uploads/cache/<yyyy>/<mm>/<basename with "." → "_">/<crc>.<ext> — a rendition of one live attachment.
    const m = p.match(/^\/wp-content\/uploads\/cache\/(\d{4}\/\d{2})\/([^/]+)\/[^/]+$/);
    let media = null, file = null;
    const same = (lp) => path.posix.dirname(lp) === m[1] && path.posix.basename(lp).replace(/\.[^.]+$/, '').replace(/\./g, '_') === m[2];
    if (m) {
      for (const [lp, info] of liveMedia) if (same(lp)) { media = info; file = lp; break; }
      // Big images: FooGallery worked from the -scaled rendition WordPress serves as "full".
      if (!media) for (const [sp, s] of served) if (same(sp)) { media = liveMedia.get(s.sourcePath); file = sp; break; }
    }
    return { type: 'FooGallery cache thumb', media, file };
  }
  if (p.startsWith('/wp-content/uploads/fusion-styles/')) return { type: 'theme asset', sub: 'fusion-styles' };
  if (p.startsWith('/wp-content/uploads/')) {
    const file = p.replace('/wp-content/uploads/', '');
    if (liveMedia.has(file)) return { type: 'image original', media: liveMedia.get(file), file };
    if (served.has(file)) { const s = served.get(file); return { type: 'image original', scaled: true, media: liveMedia.get(s.sourcePath), file, sourcePath: s.sourcePath }; }
    const m = file.match(/^(.*)-(\d+)x(\d+)\.([A-Za-z0-9]+)$/);
    if (m) {
      const base = `${m[1]}.${m[4]}`;
      const media = liveMedia.get(base) || (served.has(base) ? liveMedia.get(served.get(base).sourcePath) : null);
      if (media) return { type: 'image size variant', media, file, base, size: `${m[2]}x${m[3]}` };
    }
    return { type: 'image (unmatched)', file };
  }
  if (p.startsWith('/wp-content/themes/Avada/')) return { type: 'theme asset', sub: 'Avada' };
  if (p.startsWith('/wp-content/plugins/')) return { type: 'plugin asset', sub: p.split('/')[3] };
  if (p.startsWith('/wp-includes/')) return { type: 'core asset' };
  if (row.contentType === 'text/html' || row.fromSitemap) {
    if (registryPaths.has(p)) return { type: 'registry page' };
    return { type: 'page' };
  }
  return { type: 'other' };
}

const pluginNote = {
  'fusion-builder': 'Avada Builder — deactivated before the import (install.md B.2), deleted after sign-off',
  'fusion-core': 'Avada Core — deactivated before the import, deleted after sign-off',
  'foogallery': 'FooGallery — deactivated before the import, deleted after sign-off',
  'foobox-image-lightbox': 'FooBox — deactivated before the import, deleted after sign-off',
  'cookie-notice-and-consent-banner': 'Cookie Notice stays active and keeps enqueueing its own script',
};

function expectation(row, c) {
  const p = rel(row.url);
  const r = suiteResult(p);
  const sim = r ? `sim ${r.status}${r.location ? ' → ' + r.location.replace(/^https?:\/\/[^/]+/, '') : ''}${r.ok ? ' ✅' : ' ❌'}` : null;
  const mx = matrix.has(p) ? `url-matrix \`${p}\` (${matrix.get(p)})` : null;
  switch (c.type) {
    case 'page': {
      const mp = mapPages.get(p);
      if (draftRedirects.has(p)) {
        const to = seedPaths.get(p) || '/stories/';
        return { after: `301 → ${to} (page adopted as draft, redirect rule)`, how: [mx, `seed + redirects.json (\`${p}\` → \`${to}\`)`, mp ? `migration-map: ${mp.disposition}` : null, sim] };
      }
      const id = mp && mp.id ? `live:page:${mp.id}` : (p === '/' ? 'ref:page:home → page 6' : null);
      return { after: '200 same URL', how: [mx, `adoption (${id ? `${id} by id + slug` : 'by slug'}; ${mp ? mp.disposition : 'in migration map'})`, sim] };
    }
    case 'registry page': {
      const to = seedPaths.get(p) || '/barkode/';
      const mp = mapPages.get(p);
      return { after: `301 → ${to}${p === '/master_template_barkode/' ? ' (program page; template imported as draft)' : ' (→ 200, noindex)'}`, how: [mx, matrix.has(to) ? `url-matrix \`${to}\` (${matrix.get(to)})` : null, `adoption (${mp && mp.id ? `live:barkode:${mp.id}` : 'registry page'} converted in place, id + slug kept)`, 'seed + redirects.json', sim] };
    }
    case 'author archive':
      return { after: '301 → / (as today)', how: [mx, `seed + redirects.json (\`${p}\` → \`/\`; resolver runs on parse_request prio 1, before Privacy::block_author_query; \`?author=N\` stays 404)`, sim] };
    case 'http redirect': {
      const target = rel(row.redirect || row.url.replace('http://', 'https://'));
      const covered = allRows.find(x => rel(x.url) === target && x.url.startsWith('https://'));
      return { after: `301 → https (host-level, unchanged)`, how: [`LiteSpeed/Hostinger http→https rule, not WordPress; target ${covered ? `row #${covered.n}` : `\`${target}\` not crawled separately`}`] };
    }
    case 'image original': {
      const m = c.media;
      return { after: '200 same URL (file kept on disk untouched)', how: [`adoption: ${m ? `${m.key} → attachment #${m.id}` : '?'}${c.scaled ? ` (WordPress -scaled rendition of \`${c.sourcePath}\`, served as "full")` : ` (live_path \`${c.file}\`)`}; never moved or re-uploaded`, sim] };
    }
    case 'image size variant': {
      const m = c.media;
      return { after: '200 same URL (file kept on disk untouched)', how: [`WordPress ${c.size} rendition of \`${c.base}\` (${m ? `${m.key} → #${m.id}` : '?'}); size files stay next to the original, the importer only adds missing theme sizes`, sim] };
    }
    case 'FooGallery cache thumb':
      return { after: 'kept on disk untouched; no longer referenced (FooGallery deactivated) — not a content URL', how: [`FooGallery rendition of ${c.media ? `${c.media.key} → #${c.media.id} (\`${c.file}\`)` : 'a live attachment (no live_path match — check)'}; not part of the payload; nothing deletes \`uploads/cache/\`; the new pages render the same attachment by id`, sim ? `${sim} (absent from the simulation by design: not in the payload)` : null] };
    case 'theme asset':
      if (c.sub === 'fusion-styles') return { after: 'gone: Avada generated CSS (`uploads/fusion-styles/`) no longer referenced — file stays on disk (uploads untouched); not a content URL', how: ['Avada is replaced by the Heartland theme'] };
      return { after: `gone: ${c.sub} theme asset no longer referenced — not a content URL`, how: ['Avada is replaced by the Heartland theme; files stay on disk until the theme is deleted'] };
    case 'plugin asset':
      return { after: c.sub === 'cookie-notice-and-consent-banner' ? 'still served (plugin stays active)' : `gone: ${c.sub} asset no longer referenced — not a content URL`, how: [pluginNote[c.sub] || c.sub] };
    case 'core asset':
      return { after: 'still served (WordPress core file); no longer enqueued by the theme', how: ['core file, unaffected'] };
    default:
      return { after: '?', how: ['UNCLASSIFIED — investigate'] };
  }
}

/* ------------------------------------------------------------ live probe */

const crawlUrls = new Set(crawl.map(r => r.url));
const sitemapOnly = sitemapUrls.filter(u => !crawlUrls.has(u));
let probe = previousState.probe || {};
if (args['probe-live']) {
  probe = {};
  for (const u of sitemapOnly) {
    try {
      const res = await fetch(u, { method: 'HEAD', redirect: 'manual', headers: { 'user-agent': UA }, signal: AbortSignal.timeout(30000) });
      probe[u] = { status: res.status, location: res.headers.get('location') || '', at: new Date().toISOString() };
    } catch (e) { probe[u] = { status: 0, location: '', error: String(e.message || e) }; }
  }
}

/* ------------------------------------------------------------------ rows */

const allRows = [];
let n = 0;
for (const r of crawl) allRows.push({ ...r, n: ++n, source: 'crawl' });
for (const u of sitemapOnly) allRows.push({ url: u, contentType: 'text/html', status: probe[u] ? String(probe[u].status) : '—', indexability: '', redirect: probe[u] ? probe[u].location : '', n: ++n, source: 'sitemap', fromSitemap: true });
for (const r of allRows) r.c = classify(r);
for (const r of allRows) r.e = expectation(r, r.c);

const byType = new Map();
for (const r of allRows) byType.set(r.c.type, (byType.get(r.c.type) || 0) + 1);

// Consistency checks.
const problems = [];
for (const r of allRows) if (r.c.type === 'image (unmatched)' || r.c.type === 'other') problems.push(`row #${r.n} ${r.url} is unclassified`);
for (const r of allRows) if (r.c.type === 'FooGallery cache thumb' && !r.c.media) problems.push(`row #${r.n} ${rel(r.url)} does not map to a live attachment`);
for (const u of sitemapUrls) { const p = rel(u); if (!mapPages.has(p)) problems.push(`sitemap URL ${p} is missing from docs/migration-map.md`); }
for (const p of ['/author/admin/', '/author/hk9director/']) {
  if (!seedPaths.has(p)) problems.push(`${p} is not in the plugin seed list (Store::seeds)`);
  if (!payloadRedirectFrom.has(p)) problems.push(`${p} is not in payload-src/records/redirects.json`);
  if (!matrix.has(p)) problems.push(`${p} has no url-matrix row`);
}
for (const r of allRows) if (r.c.type === 'page' && !draftRedirects.has(rel(r.url)) && !matrix.has(rel(r.url))) problems.push(`page ${rel(r.url)} has no url-matrix row`);
for (const r of allRows) if (r.c.type === 'registry page' && !matrix.has(rel(r.url))) problems.push(`registry path ${rel(r.url)} has no url-matrix row`);
for (const u of sitemapImages) if (!crawlUrls.has(u)) problems.push(`sitemap image ${u} was not crawled`);
if (suite && suite.results) for (const r of allRows) { const s = suite.results[rel(r.url)]; if (s && s.ok === false && r.c.type !== 'FooGallery cache thumb') problems.push(`simulation failed for ${rel(r.url)}: ${s.status}${s.location ? ' → ' + s.location : ''}`); }

/* ------------------------------------------------- --emit-urls (for the suite) */

if (typeof args['emit-urls'] === 'string') {
  const list = allRows.map(r => {
    const p = rel(r.url);
    let expect = 'skip';
    switch (r.c.type) {
      case 'page': expect = draftRedirects.has(p) ? `301:${seedPaths.get(p) || '/stories/'}` : '200'; break;
      case 'registry page': expect = `301:${seedPaths.get(p) || '/barkode/'}`; break;
      case 'author archive': expect = '301:/'; break;
      case 'image original': case 'image size variant': expect = '200'; break;
      case 'FooGallery cache thumb': expect = 'absent'; break;
      default: expect = 'skip';
    }
    return { n: r.n, path: r.url.startsWith('http://') ? r.url : p, type: r.c.type, expect, source: r.source };
  });
  fs.writeFileSync(path.resolve(args['emit-urls']), JSON.stringify(list, null, 1));
  console.log(`${args['emit-urls']}: ${list.length} URLs`);
  process.exit(problems.length ? 1 : 0);
}

/* ---------------------------------------------------------------- report */

const esc = s => String(s).replace(/\|/g, '\\|');
const lines = [];
lines.push('# URL coverage — every crawled / sitemapped live URL after the in-place migration');
lines.push('');
lines.push(`Generated by \`node tools/url-coverage.mjs\` on ${new Date().toISOString().slice(0, 19)}Z. Inputs: \`urls-internal_all.csv\` (Screaming Frog crawl of https://heartlandk9s.org/, ${crawl.length} rows), \`sitemap-post-type-page.xml\` (fetched read-only ${sitemapFetchedAt.slice(0, 19)}, ${sitemapUrls.length} URLs, ${sitemapOnly.length} of them not in the crawl), \`docs/migration-map.md\` (${mapPages.size} live URLs), \`payload-lite/manifest.json\` (${liveMedia.size} live upload paths) + \`payload/media-index.json\` (${served.size} -scaled renditions), \`payload-src/records/redirects.json\` (${payloadRedirects.length} rules) + \`Redirects\\Store::seeds()\` (${seedPaths.size} rules), \`tools/url-matrix.sh\` (${matrix.size} asserted paths)${suite ? `, simulation results from \`tests/url-coverage-suite.sh\` (${suite.ran_at || ''})` : ''}.`);
lines.push('');
lines.push('Migration mode: **adoption** (`docs/install.md` §B). Every one of the 40 live pages keeps its post id and slug, the 16 registry pages become `hk9_barkode` records with the same id and slug (`/<slug>/` → 301 → `/barkode/<slug>/`), and no file under `wp-content/uploads/` is moved, re-uploaded or deleted — the content-only payload reuses the 244 live attachments by id at their existing paths. Image URLs therefore keep working by construction; the rows below list the mechanism for each one and the simulation that exercised it.');
lines.push('');
lines.push('## Rules');
lines.push('');
lines.push('| Type | Rule after the migration | How it is verified |');
lines.push('|---|---|---|');
lines.push('| page | 200 at the same URL (adopted by live id + slug; `ref:page:*` pages adopt the live page with that slug: `/` = page 6, `/contact/` = 2032, `/barkode/` = 2944) | `tools/url-matrix.sh` row (200) on the main stack; simulation on the fresh stack; `/success/` + `/success/sample-success-story/` are adopted as drafts and 301 to `/stories/` |');
lines.push('| registry page | 301 → `/barkode/<slug>/` (→ 200, `X-Robots-Tag: noindex`); the master template 301 → `/barkode/` | url-matrix rows (301 with `X-Redirect-By: hk9-legacy` + 200 target); seed list + `redirects.json`; adoption converts the page in place |');
lines.push('| author archive | 301 → `/` exactly like the live site (`/author/admin/`, `/author/hk9director/`) | seed list + `redirects.json`; the resolver runs on `parse_request` prio 1, before `Privacy::block_author_query` (`pre_get_posts`), so the redirect wins; `?author=N` stays 404; url-matrix rows; simulation |');
lines.push('| http redirect | 301 to https — a host-level (LiteSpeed/Hostinger) rule, untouched by the migration | the https target of each row is its own row in this table |');
lines.push('| image original / -scaled | 200 at the same URL: the file is the live attachment\'s own file (`live_path` / WordPress `-scaled` rendition); adoption binds `live:media:<id>` to attachment `<id>` and never copies, renames or deletes files | `payload-lite` `live_path` + `media-index` `served_full_url` match every URL (see rows); simulation: every URL fetched → 200 |');
lines.push('| image size variant | 200: WordPress `-WxH` renditions (all `medium`, ≤ 300 px) stay next to the original; the importer only adds the theme sizes that do not exist yet | rendition name resolves to a live upload path; simulation → 200 |');
lines.push('| FooGallery cache thumb | kept on disk untouched (`uploads/cache/`); no longer referenced once FooGallery is deactivated — not a content URL (every thumb is a rendition of an attachment listed above) | not part of the payload (the simulation has no `uploads/cache/`, reported separately); the new gallery/partner pages render the same attachments by id |');
lines.push('| theme / plugin / core asset | Avada, Fusion Builder/Core, FooGallery, FooBox assets are no longer referenced (not content URLs); Cookie Notice keeps serving its script; `wp-includes` files remain | not applicable — no content, no inbound links to preserve |');
lines.push('');
lines.push('## Summary');
lines.push('');
lines.push('| Type | URLs |');
lines.push('|---|---|');
for (const [t, c] of [...byType.entries()].sort((a, b) => b[1] - a[1])) lines.push(`| ${t} | ${c} |`);
lines.push(`| **total** | **${allRows.length}** (${crawl.length} crawled + ${sitemapOnly.length} sitemap-only) |`);
lines.push('');
if (suite) {
  lines.push('## Simulation (fresh stack, `plugin/heartland-k9s-core/tests/url-coverage-suite.sh`)');
  lines.push('');
  lines.push(`Run ${suite.ran_at || ''} against \`${suite.base || 'http://localhost:8095'}\` — WordPress ${suite.wp_version || '?'}, plugin ${suite.plugin_version || '?'} / theme ${suite.theme_version || '?'} installed from \`${suite.zips || 'build/*.zip'}\`; old-site fixture: ${suite.fixture ? `${suite.fixture.pages} pages with live ids/slugs, ${suite.fixture.attachments} attachments at their live paths` : '?'}; import: ${suite.import || '?'}.`);
  lines.push('');
  lines.push('| Check | Result |');
  lines.push('|---|---|');
  for (const [k, v] of Object.entries(suite.summary || {})) lines.push(`| ${esc(k)} | ${esc(v)} |`);
  lines.push('');
  if (suite.notes && suite.notes.length) { for (const note of suite.notes) lines.push(`- ${note}`); lines.push(''); }
}
lines.push(`## Consistency checks`);
lines.push('');
if (problems.length) for (const p of problems) lines.push(`- ❌ ${p}`); else lines.push('- ✅ every URL is classified, every sitemap URL is in the migration map, every page and registry path has a url-matrix row, both author archives are seeded, in `redirects.json` and in the matrix, the sitemap image was crawled' + (suite ? ', and no simulated URL failed (FooGallery cache thumbs excluded by design)' : '') + '.');
lines.push('');
lines.push('## Every URL');
lines.push('');
lines.push('Live status = Screaming Frog (crawl rows) or a read-only HEAD probe (sitemap-only rows). "sim" = the fresh-stack simulation result for the same path.');
lines.push('');
lines.push('| # | URL | Type | Live | After migration | Verified by |');
lines.push('|---|---|---|---|---|---|');
const order = ['page', 'registry page', 'author archive', 'http redirect', 'image original', 'image size variant', 'FooGallery cache thumb', 'theme asset', 'plugin asset', 'core asset', 'image (unmatched)', 'other'];
const sorted = [...allRows].sort((a, b) => (order.indexOf(a.c.type) - order.indexOf(b.c.type)) || rel(a.url).localeCompare(rel(b.url)));
for (const r of sorted) {
  const u = r.url.startsWith('http://') ? r.url : rel(r.url);
  const live = `${r.status}${r.redirect ? ' → ' + esc(rel(r.redirect)) : ''}${r.source === 'sitemap' ? ' (sitemap)' : ''}`;
  lines.push(`| ${r.n} | \`${esc(u)}\` | ${r.c.type} | ${live} | ${esc(r.e.after)} | ${r.e.how.filter(Boolean).map(esc).join('; ')} |`);
}
lines.push('');
lines.push('<!-- hk9-url-coverage-state');
lines.push(JSON.stringify({ suite, probe, sitemapFetchedAt }, null, 0));
lines.push('-->');
lines.push('');
fs.mkdirSync(path.dirname(OUT), { recursive: true });
fs.writeFileSync(OUT, lines.join('\n'));
console.log(`${path.relative(root, OUT)}: ${allRows.length} URLs (${crawl.length} crawled + ${sitemapOnly.length} sitemap-only)`);
for (const [t, c] of byType) console.log(`  ${t}: ${c}`);
if (problems.length) { console.log('problems:'); for (const p of problems) console.log(`  - ${p}`); process.exitCode = 1; }
