#!/usr/bin/env node
/**
 * Structured-data check: fetch pages, extract every <script type="application/ld+json">,
 * parse them and validate the graph against a per-type checklist.
 *
 *   node tools/schema-check.mjs [--base=http://localhost:8093] [--urls=/,/about/,…] [--expect-scripts=1] [--noindex=/barkode/hk923-005/] [--json]
 *
 * Checks per URL:
 *  - every JSON-LD block parses; exactly `--expect-scripts` blocks (default 1) — with an SEO plugin
 *    whose graph Heartland merges into (Slim SEO) that is still 1; with other SEO plugins pass 2;
 *  - every block is an @graph (or a single node) with @context https://schema.org;
 *  - no duplicated Organization / WebSite nodes: a type may appear once per distinct @id, and every
 *    Organization / NGO / WebSite node must carry an @id (so merging by identifier is possible);
 *  - required properties per type: Organization/NGO {name,url}, WebSite {name,url}, WebPage* {url,name},
 *    BreadcrumbList {itemListElement: ListItem positions 1..n with name}, Article/BlogPosting
 *    {headline,datePublished,author}, Event {name,startDate,location}, FAQPage {mainEntity: Question{name,
 *    acceptedAnswer{text}}}, Person {name}, ItemList {itemListElement}, DonateAction {target};
 *  - ISO 8601 dates (startDate/endDate/datePublished/dateModified), absolute http(s) URLs;
 *  - every {@id} reference resolves to a node in the same page's graphs.
 * Noindex rows (`--noindex=`, default one registry record; `--noindex=` empty to skip): the page must carry a
 *  robots meta with `noindex`, and — because noindex + a self-referencing canonical are conflicting signals —
 *  print NO <link rel="canonical"> and NO JSON-LD block (full mode: Head::print()/Schema::printable() skip both).
 * Exit code 1 when any URL has errors. Warnings do not fail the run.
 */
import process from 'node:process';

const args = Object.fromEntries(process.argv.slice(2).map(a => { const [k, ...v] = a.replace(/^--/, '').split('='); return [k, v.length ? v.join('=') : true]; }));
const base = String(args.base || 'http://localhost:8093').replace(/\/$/, '');
const urls = args.urls ? String(args.urls).split(',').filter(Boolean) : ['/', '/about/', '/events/', '/meet-the-team/', '/service-dogs-and-the-ada/', '/donate/', '/news/'];
const expectScripts = args['expect-scripts'] === undefined ? 1 : Number(args['expect-scripts']);
const noindexUrls = args.noindex === undefined ? ['/barkode/hk923-005/'] : (args.noindex === true ? [] : String(args.noindex).split(',').filter(Boolean));
const asJson = Boolean(args.json);

const ISO = /^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}(:\d{2})?(\.\d+)?(Z|[+-]\d{2}:\d{2}))?$/;
const URLRE = /^https?:\/\/[^\s]+$/i;
const types = n => (Array.isArray(n?.['@type']) ? n['@type'] : n?.['@type'] ? [n['@type']] : []);
const has = (n, t) => types(n).includes(t);
const isRef = v => v && typeof v === 'object' && !Array.isArray(v) && Object.keys(v).length === 1 && '@id' in v;

// Nested nodes (organizer, publisher, author…) need a name; top-level Organization/NGO/WebSite nodes also need url (checked below).
const CHECKS = {
  Organization: ['name'], NGO: ['name'], WebSite: ['name', 'url'],
  WebPage: ['url', 'name'], CollectionPage: ['url', 'name'], AboutPage: ['url', 'name'], ContactPage: ['url', 'name'], SearchResultsPage: ['url', 'name'],
  Article: ['headline', 'datePublished', 'author'], BlogPosting: ['headline', 'datePublished', 'author'], NewsArticle: ['headline', 'datePublished', 'author'],
  Event: ['name', 'startDate', 'location'], FAQPage: ['mainEntity'], BreadcrumbList: ['itemListElement'], Person: ['name'], ItemList: ['itemListElement'],
  DonateAction: ['target'], ImageObject: ['url'], PostalAddress: [], Place: ['name'], Offer: ['url'],
};

// Noindexed view: robots noindex present, no canonical link, no JSON-LD at all.
function checkNoindex(url, html, headers) {
  const errors = []; const warnings = []; const info = [];
  const robots = [...html.matchAll(/<meta\b[^>]*name=["']robots["'][^>]*content=["']([^"']*)["']/gi)].map(m => m[1]);
  const canonicals = [...html.matchAll(/<link\b[^>]*rel=["']canonical["'][^>]*>/gi)].map(m => m[0]);
  const prevNext = [...html.matchAll(/<link\b[^>]*rel=["'](?:prev|next)["'][^>]*>/gi)].length;
  const blocks = extract(html);
  const xrobots = headers?.get?.('x-robots-tag') || '';
  info.push(`noindex row: robots meta ${robots.length ? JSON.stringify(robots) : 'none'}${xrobots ? `, X-Robots-Tag "${xrobots}"` : ''}, ${canonicals.length} canonical, ${prevNext} prev/next, ${blocks.length} JSON-LD block(s)`);
  if (!robots.some(r => /\bnoindex\b/i.test(r))) errors.push('no <meta name="robots"> with noindex');
  if (canonicals.length) errors.push(`noindexed view prints ${canonicals.length} <link rel="canonical"> (${canonicals.join(' ')})`);
  if (prevNext) errors.push(`noindexed view prints ${prevNext} rel prev/next link(s)`);
  if (blocks.length) errors.push(`noindexed view prints ${blocks.length} JSON-LD block(s)`);
  if (!/<meta\b[^>]*property=["']og:title["']/i.test(html)) warnings.push('no og:title (social tags are expected to stay)');
  return { url, ok: errors.length === 0, errors, warnings, info };
}

function extract(html) {
  const out = [];
  const re = /<script\b[^>]*type=["']application\/ld\+json["'][^>]*>([\s\S]*?)<\/script>/gi;
  let m;
  while ((m = re.exec(html))) out.push(m[1].trim());
  return out;
}

function walk(node, fn, path = '$') {
  if (Array.isArray(node)) return node.forEach((v, i) => walk(v, fn, `${path}[${i}]`));
  if (node && typeof node === 'object') {
    fn(node, path);
    for (const [k, v] of Object.entries(node)) if (k !== '@context') walk(v, fn, `${path}.${k}`);
  }
}

function checkUrl(url, html) {
  const errors = []; const warnings = []; const info = [];
  const blocks = extract(html);
  info.push(`${blocks.length} JSON-LD block(s)`);
  if (blocks.length !== expectScripts) errors.push(`expected ${expectScripts} JSON-LD block(s), found ${blocks.length}`);
  const nodes = []; const ids = new Map(); const refs = [];
  blocks.forEach((raw, i) => {
    let data;
    try { data = JSON.parse(raw); } catch (e) { errors.push(`block ${i + 1}: invalid JSON (${e.message})`); return; }
    if (/<\/script/i.test(raw)) errors.push(`block ${i + 1}: contains an unescaped </script>`);
    const ctx = data['@context'];
    if (!(ctx === 'https://schema.org' || ctx === 'http://schema.org' || ctx === 'https://schema.org/')) errors.push(`block ${i + 1}: @context is ${JSON.stringify(ctx)}`);
    const graph = Array.isArray(data['@graph']) ? data['@graph'] : [data];
    if (!Array.isArray(data['@graph'])) warnings.push(`block ${i + 1}: no @graph (single node)`);
    for (const n of graph) {
      if (!n || typeof n !== 'object') { errors.push(`block ${i + 1}: non-object graph entry`); continue; }
      nodes.push({ n, block: i + 1 });
    }
    walk(graph, (obj, path) => {
      if (isRef(obj)) refs.push({ id: obj['@id'], path, block: i + 1 });
      for (const k of ['startDate', 'endDate', 'datePublished', 'dateModified', 'validFrom']) if (k in obj && !ISO.test(String(obj[k]))) errors.push(`block ${i + 1} ${path}.${k}: not ISO 8601 (${obj[k]})`);
      for (const k of ['url', 'contentUrl', 'item', 'target', 'sameAs', 'image']) {
        if (!(k in obj)) continue;
        const vals = Array.isArray(obj[k]) ? obj[k] : [obj[k]];
        for (const v of vals) if (typeof v === 'string' && !URLRE.test(v) && !(k === 'target' && v.includes('{'))) errors.push(`block ${i + 1} ${path}.${k}: not an absolute URL (${v})`);
      }
    });
  });

  // Type registry + duplicates.
  const byType = new Map();
  for (const { n, block } of nodes) {
    const id = n['@id'];
    if (typeof id === 'string') {
      const key = id;
      if (ids.has(key) && ids.get(key).block === block) errors.push(`duplicate @id in the same block: ${id}`);
      ids.set(key, { n, block });
    }
    for (const t of types(n)) byType.set(t, [...(byType.get(t) || []), { n, block }]);
    if (types(n).length === 0) errors.push(`block ${block}: node without @type (${id || 'no @id'})`);
  }
  for (const t of ['Organization', 'NGO', 'WebSite']) {
    const list = byType.get(t) || [];
    for (const { n, block } of list) if (typeof n['@id'] !== 'string') errors.push(`block ${block}: ${t} node without @id (cannot merge)`);
    for (const { n, block } of list) for (const k of ['name', 'url']) if (!n[k]) errors.push(`block ${block}: top-level ${t} ${n['@id'] || ''} missing ${k}`);
    const distinct = new Set(list.map(({ n }) => n['@id'] || Symbol()));
    if (distinct.size > 1) errors.push(`${t}: ${distinct.size} distinct nodes (${[...distinct].map(String).join(', ')})`);
  }
  const orgIds = new Set([...(byType.get('Organization') || []), ...(byType.get('NGO') || [])].map(({ n }) => n['@id']));
  if (orgIds.size > 1) errors.push(`Organization/NGO: ${orgIds.size} distinct @ids (${[...orgIds].join(', ')})`);
  if (blocks.length > 1) {
    const across = [...(byType.get('Organization') || []), ...(byType.get('NGO') || [])];
    const blocksWithOrg = new Set(across.map(x => x.block));
    if (blocksWithOrg.size > 1) info.push(`Organization appears in ${blocksWithOrg.size} blocks with the same @id (merged by identifier)`);
  }

  // Required properties per type.
  walk(nodes.map(x => x.n), (obj, path) => {
    for (const t of types(obj)) {
      const req = CHECKS[t];
      if (!req) continue;
      for (const k of req) if (!(k in obj) || obj[k] === '' || obj[k] === null || (Array.isArray(obj[k]) && !obj[k].length)) errors.push(`${path} (${t}): missing ${k}`);
    }
    if (has(obj, 'BreadcrumbList')) {
      const items = Array.isArray(obj.itemListElement) ? obj.itemListElement : [];
      items.forEach((it, i) => {
        if (!has(it, 'ListItem')) errors.push(`${path}.itemListElement[${i}]: not a ListItem`);
        if (it.position !== i + 1) errors.push(`${path}.itemListElement[${i}]: position ${it.position} ≠ ${i + 1}`);
        if (!it.name) errors.push(`${path}.itemListElement[${i}]: missing name`);
        if (i < items.length - 1 && !it.item) errors.push(`${path}.itemListElement[${i}]: non-final item without item URL`);
      });
    }
    if (has(obj, 'FAQPage')) {
      const qs = Array.isArray(obj.mainEntity) ? obj.mainEntity : [obj.mainEntity];
      qs.forEach((q, i) => {
        if (!has(q, 'Question')) errors.push(`${path}.mainEntity[${i}]: not a Question`);
        if (!q?.name) errors.push(`${path}.mainEntity[${i}]: missing name`);
        if (!q?.acceptedAnswer || !has(q.acceptedAnswer, 'Answer') || !q.acceptedAnswer.text) errors.push(`${path}.mainEntity[${i}]: missing acceptedAnswer.text`);
      });
    }
    if (has(obj, 'ItemList')) {
      const items = Array.isArray(obj.itemListElement) ? obj.itemListElement : [];
      items.forEach((it, i) => { if (it.position !== i + 1) errors.push(`${path}.itemListElement[${i}]: position ${it.position} ≠ ${i + 1}`); });
    }
    if (has(obj, 'Event')) {
      if (obj.location && !isRef(obj.location) && !has(obj.location, 'Place') && !has(obj.location, 'VirtualLocation')) errors.push(`${path}.location: not a Place`);
      if (obj.location && has(obj.location, 'Place') && !obj.location.address) warnings.push(`${path}.location: Place without address`);
      if (!obj.eventStatus) warnings.push(`${path}: no eventStatus`);
      if (!obj.image) warnings.push(`${path}: no image`);
      if (!obj.description) warnings.push(`${path}: no description`);
    }
    if (has(obj, 'NGO') || has(obj, 'Organization')) {
      if (obj['@id'] && !isRef(obj)) {
        for (const k of ['logo', 'sameAs', 'address', 'nonprofitStatus', 'taxID']) if (!(k in obj)) warnings.push(`${path} (${types(obj).join('/')}): no ${k}`);
      }
    }
    if (has(obj, 'Person') && ('email' in obj || 'telephone' in obj)) errors.push(`${path} (Person): must not expose email/telephone`);
  });

  // Reference resolution.
  for (const r of refs) if (!ids.has(r.id)) {
    const known = [...ids.keys()];
    const sameFragment = known.find(k => k.split('#')[1] === String(r.id).split('#')[1]);
    if (sameFragment) errors.push(`block ${r.block} ${r.path}: reference ${r.id} resolves to no node (closest: ${sameFragment})`);
    else errors.push(`block ${r.block} ${r.path}: reference ${r.id} resolves to no node`);
  }

  const typeSummary = [...byType.entries()].map(([t, l]) => `${t}×${l.length}`).join(', ');
  return { url, ok: errors.length === 0, errors, warnings, info: [...info, `types: ${typeSummary || 'none'}`] };
}

const results = [];
for (const [u, noindex] of [...urls.map(u => [u, false]), ...noindexUrls.map(u => [u, true])]) {
  const target = base + u;
  let html = '';
  try {
    const res = await fetch(target, { headers: { 'user-agent': 'hk9-schema-check/1.0' } });
    html = await res.text();
    const r = noindex ? checkNoindex(u, html, res.headers) : checkUrl(u, html);
    if (res.status !== 200) (noindex ? r.errors : r.warnings).push(`HTTP ${res.status}`);
    results.push(r);
  } catch (e) {
    results.push({ url: u, ok: false, errors: [`fetch failed: ${e.message}`], warnings: [], info: [] });
  }
}

if (asJson) {
  console.log(JSON.stringify(results, null, 2));
} else {
  for (const r of results) {
    console.log(`${r.ok ? 'PASS' : 'FAIL'}  ${r.url}`);
    for (const i of r.info) console.log(`      · ${i}`);
    for (const w of r.warnings) console.log(`      ! ${w}`);
    for (const e of r.errors) console.log(`      ✗ ${e}`);
  }
  const failed = results.filter(r => !r.ok).length;
  console.log(`\n${results.length - failed}/${results.length} URLs pass`);
}
process.exit(results.every(r => r.ok) ? 0 : 1);
