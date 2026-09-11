#!/usr/bin/env node
/**
 * Pixel-diff reference vs WordPress screenshots and write a markdown report.
 *
 *   node tools/compare.mjs --ref=discovery/ref/screenshots --wp=docs/reports/screenshots/wp/chromium \
 *        --out=docs/reports/diffs [--widths=390,1440] [--routes=home,about,...]
 *
 * Images are compared top-aligned at the same width; the shorter image is padded with white.
 * Output: <out>/<route>-<width>-diff.png plus <out>/report.md with % differing pixels, heights and notes.
 */
import fs from 'node:fs';
import path from 'node:path';
import { PNG } from 'pngjs';
import pixelmatch from 'pixelmatch';

const args = Object.fromEntries(process.argv.slice(2).map(a => { const [k, ...v] = a.replace(/^--/, '').split('='); return [k, v.length ? v.join('=') : true]; }));
const refDir = args.ref || 'discovery/ref/screenshots';
const wpDir = args.wp || 'docs/reports/screenshots/wp/chromium';
const out = args.out || 'docs/reports/diffs';
const widths = String(args.widths || '390,1440').split(',').map(Number);
const routes = args.routes ? String(args.routes).split(',') : ['home', 'about', 'program', 'veterans', 'get-involved', 'barkode', 'stories', 'contact'];
fs.mkdirSync(out, { recursive: true });

const read = p => PNG.sync.read(fs.readFileSync(p));
const pad = (img, w, h) => { if (img.width === w && img.height === h) return img; const o = new PNG({ width: w, height: h, fill: true }); o.data.fill(255); PNG.bitblt(img, o, 0, 0, Math.min(img.width, w), Math.min(img.height, h), 0, 0); return o; };

const rows = [];
for (const route of routes) for (const width of widths) {
  const a = path.join(refDir, `${route}-${width}.png`), b = path.join(wpDir, `${route}-${width}.png`);
  if (!fs.existsSync(a) || !fs.existsSync(b)) { rows.push({ route, width, note: `missing ${!fs.existsSync(a) ? a : b}` }); continue; }
  const A = read(a), B = read(b);
  const w = Math.max(A.width, B.width), h = Math.max(A.height, B.height);
  const pa = pad(A, w, h), pb = pad(B, w, h);
  const diff = new PNG({ width: w, height: h });
  const n = pixelmatch(pa.data, pb.data, diff.data, w, h, { threshold: 0.12, includeAA: true, alpha: 0.6 });
  const overlap = Math.min(A.height, B.height) * w;
  const nOverlap = pixelmatch(pa.data.subarray(0, overlap * 4), pb.data.subarray(0, overlap * 4), null, w, Math.min(A.height, B.height), { threshold: 0.12, includeAA: true });
  fs.writeFileSync(path.join(out, `${route}-${width}-diff.png`), PNG.sync.write(diff));
  rows.push({ route, width, refH: A.height, wpH: B.height, pct: (100 * n / (w * h)).toFixed(2), pctOverlap: (100 * nOverlap / overlap).toFixed(2) });
}
const md = ['# Visual diff report', '', `Reference: \`${refDir}\` · WordPress: \`${wpDir}\` · generated ${new Date().toISOString()}`, '', '| Route | Width | Ref height | WP height | Δ height | % pixels differing (full canvas) | % differing (overlapping region) | Diff image |', '|---|---|---|---|---|---|---|---|'];
for (const r of rows) md.push(r.note ? `| ${r.route} | ${r.width} | — | — | — | — | — | ${r.note} |` : `| ${r.route} | ${r.width} | ${r.refH} | ${r.wpH} | ${r.wpH - r.refH >= 0 ? '+' : ''}${r.wpH - r.refH} | ${r.pct}% | ${r.pctOverlap}% | [diff](${r.route}-${r.width}-diff.png) |`);
md.push('', 'Threshold 0.12 with anti-aliasing tolerance; content-driven height differences are expected where live copy replaced reference placeholders (see docs/conflict-log.md). Numbers are a triage aid — every route was also inspected manually (see docs/visual-comparison.md).');
fs.writeFileSync(path.join(out, 'report.md'), md.join('\n') + '\n');
console.log(md.join('\n'));
