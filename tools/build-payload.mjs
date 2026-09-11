#!/usr/bin/env node
/**
 * build-payload.mjs — assemble payload/manifest.json (+ payload/content/) from payload-src/.
 *
 *   node tools/build-payload.mjs [--src=payload-src] [--out=payload] [--media-index=payload/media-index.json]
 *                                [--copy-media] [--no-verify] [--quiet]
 *
 * Inputs
 *   <src>/records/**\/*.json   one record object or an array of records (hk9-payload/1 shapes, ARCHITECTURE.md §8)
 *   <src>/content/*.html      block markup with {{tokens}}; a record's "content" names a file here
 *                             (or is attached automatically when content/<key with ':' -> '__'>.html exists)
 *   <src>/sources.json        optional { sources: {...}, requires: { theme } }
 *   media-index.json          { items: { key: { file, sha256, bytes, mime, width, height, title, alt, caption,
 *                               description, date, parent?, sensitive? } } } written by tools/fetch-media.mjs
 *                             (missing -> build with zero media and warn)
 * Output
 *   <out>/manifest.json, <out>/content/*.html (and <out>/media/** when --copy-media copies from <src>/media/**)
 *
 * Every {{token}} must resolve to a record of the right kind (or, for terms, a seeded taxonomy term slug listed
 * in <src>/known-terms.json); dangling tokens fail the build. Record shapes are validated the same way the
 * importer's validate step does, so a build that passes here loads cleanly.
 */

import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '..');

const args = Object.fromEntries(
  process.argv.slice(2).map((a) => {
    const m = a.match(/^--([^=]+)(?:=(.*))?$/);
    return m ? [m[1], m[2] ?? true] : [a, true];
  })
);

const SRC = path.resolve(ROOT, args.src || 'payload-src');
const OUT = path.resolve(ROOT, args.out || 'payload');
const MEDIA_INDEX = args['media-index'] ? path.resolve(ROOT, args['media-index']) : null;
const COPY_MEDIA = Boolean(args['copy-media']);
const VERIFY = !args['no-verify'];
const QUIET = Boolean(args.quiet);

const TOKEN_RE = /\{\{(media_url|media|post_url|post|term):([^{}|]+?)(?:\|([a-z_]+))?\}\}/g;
const STRUCTURAL = new Set(['attachment', 'term', 'menu', 'option', 'reading', 'redirect']);
const POST_STATUSES = new Set(['publish', 'draft', 'private', 'pending', 'future']);
const META_TYPES = new Set(['string', 'integer', 'number', 'boolean', 'array', 'object']);
const OPTION_ALLOW = new Set([
  'blogname', 'blogdescription', 'timezone_string', 'date_format', 'time_format', 'start_of_week',
  'posts_per_page', 'posts_per_rss', 'site_icon', 'default_comment_status', 'default_ping_status',
]);

const errors = [];
const warnings = [];
const log = (...m) => { if (!QUIET) console.log(...m); };
const fail = (m) => errors.push(m);
const warn = (m) => warnings.push(m);

/* ------------------------------------------------------------------ helpers */

function walk(dir, ext) {
  if (!fs.existsSync(dir)) return [];
  const out = [];
  for (const entry of fs.readdirSync(dir, { withFileTypes: true }).sort((a, b) => a.name.localeCompare(b.name))) {
    const p = path.join(dir, entry.name);
    if (entry.isDirectory()) out.push(...walk(p, ext));
    else if (entry.isFile() && entry.name.endsWith(ext)) out.push(p);
  }
  return out;
}

function sha256File(p) {
  const h = crypto.createHash('sha256');
  h.update(fs.readFileSync(p));
  return h.digest('hex');
}

function isStr(v) { return typeof v === 'string'; }
function isObj(v) { return v && typeof v === 'object' && !Array.isArray(v); }

function tokensIn(value, out = []) {
  if (Array.isArray(value)) value.forEach((v) => tokensIn(v, out));
  else if (isObj(value)) Object.values(value).forEach((v) => tokensIn(v, out));
  else if (isStr(value) && value.includes('{{')) {
    for (const m of value.matchAll(TOKEN_RE)) out.push({ kind: m[1], key: m[2].trim(), mod: m[3] || '' });
  }
  return out;
}

function singleToken(value, kinds) {
  if (!isStr(value)) return null;
  const m = value.match(/^\{\{(media_url|media|post_url|post|term):([^{}|]+?)(?:\|([a-z_]+))?\}\}$/);
  if (!m || !kinds.includes(m[1])) return null;
  return m[1] === 'term' && !m[2].startsWith('term:') ? 'term:' + m[2].trim() : m[2].trim();
}

function contentFileName(key) {
  return key.replace(/[^A-Za-z0-9_.-]+/g, '__') + '.html';
}

/* ------------------------------------------------------------------ inputs */

const sourcesFile = path.join(SRC, 'sources.json');
const sourcesCfg = fs.existsSync(sourcesFile) ? JSON.parse(fs.readFileSync(sourcesFile, 'utf8')) : {};
const knownTermsFile = path.join(SRC, 'known-terms.json');
const knownTerms = new Set(fs.existsSync(knownTermsFile) ? JSON.parse(fs.readFileSync(knownTermsFile, 'utf8')) : []);

const records = [];
for (const file of walk(path.join(SRC, 'records'), '.json')) {
  let data;
  try {
    data = JSON.parse(fs.readFileSync(file, 'utf8'));
  } catch (e) {
    fail(`${path.relative(ROOT, file)}: invalid JSON (${e.message})`);
    continue;
  }
  const list = Array.isArray(data) ? data : [data];
  list.forEach((r, i) => {
    if (!isObj(r)) { fail(`${path.relative(ROOT, file)}[${i}]: record must be an object`); return; }
    records.push({ ...r, __file: path.relative(ROOT, file) });
  });
}

// Media index -> attachment records.
let mediaIndexPath = MEDIA_INDEX;
if (!mediaIndexPath) {
  for (const candidate of [path.join(OUT, 'media-index.json'), path.join(SRC, 'media-index.json')]) {
    if (fs.existsSync(candidate)) { mediaIndexPath = candidate; break; }
  }
}
let mediaCount = 0;
let mediaBytes = 0;
if (mediaIndexPath && fs.existsSync(mediaIndexPath)) {
  let index;
  try {
    index = JSON.parse(fs.readFileSync(mediaIndexPath, 'utf8'));
  } catch (e) {
    fail(`${path.relative(ROOT, mediaIndexPath)}: invalid JSON (${e.message})`);
    index = { items: {} };
  }
  const items = isObj(index.items) ? index.items : {};
  for (const [key, item] of Object.entries(items)) {
    if (!isObj(item) || !isStr(item.file) || !isStr(item.sha256)) {
      fail(`media-index item ${key}: needs file + sha256`);
      continue;
    }
    const rec = {
      key,
      type: 'attachment',
      file: item.file.replace(/^\.?\//, ''),
      sha256: item.sha256.toLowerCase(),
      mime: item.mime || '',
      size: Number(item.bytes ?? item.size ?? 0),
      title: item.title ?? '',
      alt: item.alt ?? '',
      caption: item.caption ?? '',
      description: item.description ?? '',
      date: item.date ?? '',
    };
    if (item.width) rec.width = Number(item.width);
    if (item.height) rec.height = Number(item.height);
    if (item.parent) rec.parent = item.parent;
    if (item.sensitive) rec.sensitive = true;
    records.push({ ...rec, __file: path.relative(ROOT, mediaIndexPath) });
    mediaCount++;
    mediaBytes += rec.size;
  }
} else {
  warn(`media-index.json not found (${mediaIndexPath ? path.relative(ROOT, mediaIndexPath) : 'payload/media-index.json'}); building with zero media.`);
}

/* ---------------------------------------------------------------- validate */

const byKey = new Map();
for (const r of records) {
  if (!isStr(r.key) || !r.key.trim()) { fail(`${r.__file}: record without a "key"`); continue; }
  if (r.key.length > 191) fail(`${r.key}: key longer than 191 characters`);
  if (byKey.has(r.key)) { fail(`${r.key}: duplicate key (also in ${byKey.get(r.key).__file})`); continue; }
  byKey.set(r.key, r);
}

const typeOf = (r) => (STRUCTURAL.has(r.type) ? r.type : 'post_like');

function tokenTargetOk(kind, key) {
  const mkey = kind === 'term' && !key.startsWith('term:') ? 'term:' + key : key;
  const rec = byKey.get(mkey);
  if (!rec) return kind === 'term' && knownTerms.has(mkey);
  const t = typeOf(rec);
  if (kind === 'media' || kind === 'media_url') return t === 'attachment';
  if (kind === 'post' || kind === 'post_url') return t === 'post_like';
  if (kind === 'term') return t === 'term';
  return false;
}

function checkTokens(where, value) {
  for (const { kind, key, mod } of tokensIn(value)) {
    if (!tokenTargetOk(kind, key)) fail(`${where}: dangling token {{${kind}:${key}}}`);
    if (mod && !(kind === 'media_url' && mod === 'original')) fail(`${where}: unsupported modifier |${mod} on {{${kind}:${key}}}`);
  }
}

function checkShape(r) {
  const k = r.key;
  const type = r.type;
  if (!isStr(type) || !type) { fail(`${k}: missing "type"`); return; }
  switch (type) {
    case 'attachment':
      if (!isStr(r.file) || !r.file) fail(`${k}: attachment needs "file"`);
      if (!/^[a-f0-9]{64}$/.test(r.sha256 || '')) fail(`${k}: attachment needs a 64-hex "sha256"`);
      if (!isStr(r.mime) || !r.mime) fail(`${k}: attachment needs "mime"`);
      if (r.parent !== undefined && !singleToken(r.parent, ['post'])) fail(`${k}: "parent" must be {{post:K}}`);
      if (r.date && Number.isNaN(Date.parse(r.date))) fail(`${k}: unparsable date "${r.date}"`);
      break;
    case 'term':
      for (const f of ['taxonomy', 'slug', 'name']) if (!isStr(r[f]) || !r[f]) fail(`${k}: term needs "${f}"`);
      if (isStr(r.taxonomy) && isStr(r.slug) && k !== `term:${r.taxonomy}:${r.slug}`) fail(`${k}: term key must be "term:${r.taxonomy}:${r.slug}"`);
      break;
    case 'menu': {
      if (!isStr(r.name) || !r.name) fail(`${k}: menu needs "name"`);
      if (r.locations !== undefined && !Array.isArray(r.locations)) fail(`${k}: "locations" must be an array`);
      const items = Array.isArray(r.items) ? r.items : [];
      const keys = new Set();
      items.forEach((it, i) => {
        if (!isObj(it) || !isStr(it.key) || !it.key) { fail(`${k}: item #${i} needs "key"`); return; }
        if (keys.has(it.key)) fail(`${k}: duplicate item key ${it.key}`);
        keys.add(it.key);
        if (byKey.has(it.key)) fail(`${k}: item key ${it.key} collides with a record key`);
        if (!['post_type', 'custom'].includes(it.kind)) fail(`${k}/${it.key}: "kind" must be post_type|custom`);
        if (it.kind === 'post_type' && !singleToken(it.object, ['post'])) fail(`${k}/${it.key}: post_type items need "object" = {{post:K}}`);
        if (it.kind === 'custom' && (!isStr(it.url) || !it.url)) fail(`${k}/${it.key}: custom items need "url"`);
        if (it.order !== undefined && !Number.isInteger(it.order)) fail(`${k}/${it.key}: "order" must be an integer`);
      });
      items.forEach((it) => {
        if (isObj(it) && it.parent && !keys.has(it.parent)) fail(`${k}/${it.key}: parent "${it.parent}" is not an item of this menu`);
      });
      break;
    }
    case 'option':
      if (!isStr(r.name) || !r.name) fail(`${k}: option needs "name"`);
      else if (!(r.name.startsWith('hk9_') || OPTION_ALLOW.has(r.name))) fail(`${k}: option "${r.name}" is not importable (hk9_* or site-info allow-list only)`);
      if (r.merge !== undefined && !['deep', 'replace'].includes(r.merge)) fail(`${k}: "merge" must be deep|replace`);
      if (!('value' in r)) fail(`${k}: option needs "value"`);
      if (k !== `option:${r.name}`) fail(`${k}: option key must be "option:${r.name}"`);
      break;
    case 'reading':
      if (k !== 'reading') fail(`${k}: reading record key must be "reading"`);
      if (r.show_on_front !== undefined && !['page', 'posts'].includes(r.show_on_front)) fail(`${k}: "show_on_front" must be page|posts`);
      for (const f of ['page_on_front', 'page_for_posts']) if (r[f] && !singleToken(r[f], ['post'])) fail(`${k}: "${f}" must be {{post:K}}`);
      break;
    case 'redirect':
      if (!isStr(r.from) || !r.from) fail(`${k}: redirect needs "from"`);
      if (isObj(r.to)) {
        if (r.to.type !== 'record' || !isStr(r.to.slug)) fail(`${k}: object "to" must be {type:"record", slug}`);
      } else if ((!isStr(r.to) || !r.to) && Number(r.status ?? 301) !== 410) fail(`${k}: redirect needs "to"`);
      if (r.status !== undefined && ![301, 302, 410].includes(Number(r.status))) fail(`${k}: "status" must be 301, 302 or 410`);
      if (isStr(r.from) && k !== `redirect:${r.from}`) warn(`${k}: conventional key would be "redirect:${r.from}"`);
      break;
    default:
      if (!isStr(r.title)) fail(`${k}: post needs "title"`);
      if (!isStr(r.slug) || !r.slug) fail(`${k}: post needs "slug"`);
      if (r.status !== undefined && !POST_STATUSES.has(r.status)) fail(`${k}: bad status "${r.status}"`);
      if (r.parent && !singleToken(r.parent, ['post'])) fail(`${k}: "parent" must be {{post:K}}`);
      if (r.featured && !singleToken(r.featured, ['media'])) fail(`${k}: "featured" must be {{media:K}}`);
      if (r.template !== undefined && type !== 'page') warn(`${k}: "template" is ignored for type ${type}`);
      if (r.meta !== undefined) {
        if (!isObj(r.meta)) fail(`${k}: "meta" must be an object`);
        else for (const [mk, mv] of Object.entries(r.meta)) {
          if (!isObj(mv) || !('value' in mv)) fail(`${k}: meta "${mk}" must be {type, value}`);
          else if (mv.type !== undefined && !META_TYPES.has(mv.type)) fail(`${k}: meta "${mk}" unknown type "${mv.type}"`);
        }
      }
      if (r.terms !== undefined && !isObj(r.terms)) fail(`${k}: "terms" must be {taxonomy: [tokens|slugs]}`);
      if (r.date && Number.isNaN(Date.parse(r.date))) fail(`${k}: unparsable date "${r.date}"`);
      if (r.content !== undefined && !isStr(r.content)) fail(`${k}: "content" must be a file name`);
  }
}

for (const r of byKey.values()) checkShape(r);

/* ------------------------------------------------------------------ content */

fs.mkdirSync(path.join(OUT, 'content'), { recursive: true });
// Remove stale content files so the output only contains what this build produced.
for (const f of fs.readdirSync(path.join(OUT, 'content'))) {
  if (f.endsWith('.html')) fs.unlinkSync(path.join(OUT, 'content', f));
}

let contentCount = 0;
for (const r of byKey.values()) {
  if (typeOf(r) !== 'post_like') continue;
  let name = null;
  if (isStr(r.content) && r.content) {
    name = r.content.replace(/^content\//, '');
  } else if (r.content === undefined && fs.existsSync(path.join(SRC, 'content', contentFileName(r.key)))) {
    name = contentFileName(r.key);
  }
  if (!name) { delete r.content; continue; }
  const srcFile = path.join(SRC, 'content', name);
  if (!fs.existsSync(srcFile)) { fail(`${r.key}: content file "${name}" not found in ${path.relative(ROOT, path.join(SRC, 'content'))}`); continue; }
  const html = fs.readFileSync(srcFile, 'utf8').replace(/\r\n/g, '\n');
  checkTokens(`${r.key} (${name})`, html);
  const outName = contentFileName(r.key);
  fs.writeFileSync(path.join(OUT, 'content', outName), html);
  r.content = `content/${outName}`;
  contentCount++;
}

/* ------------------------------------------------------------ tokens/media */

for (const r of byKey.values()) {
  const copy = { ...r };
  delete copy.content; delete copy.__file; delete copy.key; delete copy.type; delete copy.file; delete copy.sha256;
  checkTokens(r.key, copy);
}

for (const r of byKey.values()) {
  if (r.type !== 'attachment') continue;
  const srcCandidate = path.join(SRC, r.file);
  const outFile = path.join(OUT, r.file);
  if (COPY_MEDIA && fs.existsSync(srcCandidate)) {
    fs.mkdirSync(path.dirname(outFile), { recursive: true });
    fs.copyFileSync(srcCandidate, outFile);
  }
  if (!fs.existsSync(outFile)) { fail(`${r.key}: media file "${r.file}" missing under ${path.relative(ROOT, OUT)}`); continue; }
  const size = fs.statSync(outFile).size;
  if (!r.size) r.size = size;
  else if (r.size !== size) warn(`${r.key}: declared size ${r.size} != actual ${size}`);
  if (VERIFY) {
    const sha = sha256File(outFile);
    if (sha !== r.sha256) fail(`${r.key}: sha256 mismatch for ${r.file}`);
  }
}

/* ------------------------------------------------------------------ output */

if (errors.length) {
  console.error(`\nbuild-payload: ${errors.length} error(s):`);
  for (const e of errors) console.error('  - ' + e);
  for (const w of warnings) console.error('  ! ' + w);
  process.exit(1);
}

const order = ['attachment', 'term', 'post_like', 'menu', 'option', 'reading', 'redirect'];
const grouped = Object.fromEntries(order.map((t) => [t, []]));
for (const r of byKey.values()) {
  const clean = { ...r };
  delete clean.__file;
  grouped[typeOf(r)].push(clean);
}
const manifest = {
  format: 'hk9-payload/1',
  generated_at: new Date().toISOString(),
  sources: sourcesCfg.sources || {},
  requires: { theme: 'heartland-k9s', ...(sourcesCfg.requires || {}) },
  records: order.flatMap((t) => grouped[t]),
};
fs.mkdirSync(OUT, { recursive: true });
fs.writeFileSync(path.join(OUT, 'manifest.json'), JSON.stringify(manifest, null, 2) + '\n');

const counts = {};
for (const r of manifest.records) counts[r.type] = (counts[r.type] || 0) + 1;
log(`build-payload: wrote ${path.relative(ROOT, path.join(OUT, 'manifest.json'))}`);
log(`  records: ${manifest.records.length}  (${Object.entries(counts).map(([k, v]) => `${k}=${v}`).join(', ')})`);
log(`  content files: ${contentCount}   media: ${mediaCount} (${(mediaBytes / 1048576).toFixed(1)} MB)`);
for (const w of warnings) log('  ! ' + w);
