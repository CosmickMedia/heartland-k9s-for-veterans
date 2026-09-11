#!/usr/bin/env node
/** Run axe-core on a list of URLs at 390 and 1440 and report violations (serious/critical fail the run).
 *   node tools/axe.mjs --base=http://localhost:8093 [--urls=/,/about/...] --out=docs/reports/axe.md */
import { chromium } from 'playwright';
import { AxeBuilder } from '@axe-core/playwright';
import fs from 'node:fs';
const args = Object.fromEntries(process.argv.slice(2).map(a => { const [k, ...v] = a.replace(/^--/, '').split('='); return [k, v.length ? v.join('=') : true]; }));
const base = (args.base || 'http://localhost:8093').replace(/\/$/, '');
const urls = args.urls ? String(args.urls).split(',') : ['/', '/about/', '/program/', '/veterans/', '/get-involved/', '/barkode/', '/stories/', '/contact/', '/news/', '/donate/', '/events/', '/campaigns/', '/meet-the-team/', '/photos/', '/?s=dog', '/nonexistent-page/'];
const out = args.out || 'docs/reports/axe.md';
const browser = await chromium.launch();
const md = ['# axe-core accessibility scan', '', `Base ${base} · ${new Date().toISOString()}`, '', '| URL | Width | Critical | Serious | Moderate | Minor | Details |', '|---|---|---|---|---|---|---|'];
let bad = 0;
for (const u of urls) for (const width of [390, 1440]) {
  const ctx = await browser.newContext({ viewport: { width, height: width < 1024 ? 844 : 900 } });
  const page = await ctx.newPage();
  await page.goto(base + u, { waitUntil: 'networkidle' });
  const r = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice']).analyze();
  const by = i => r.violations.filter(v => v.impact === i);
  bad += by('critical').length + by('serious').length;
  md.push(`| ${u} | ${width} | ${by('critical').length} | ${by('serious').length} | ${by('moderate').length} | ${by('minor').length} | ${r.violations.map(v => `${v.id} (${v.impact}, ${v.nodes.length}×: ${v.nodes[0]?.target?.[0] ?? ''})`).join('; ') || '—'} |`);
  await ctx.close();
}
await browser.close();
fs.mkdirSync('docs/reports', { recursive: true });
fs.writeFileSync(out, md.join('\n') + '\n');
console.log(md.join('\n'));
process.exit(bad ? 1 : 0);
