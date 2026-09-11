#!/usr/bin/env node
/** Run axe-core on a list of URLs at 390 and 1440 and report violations (serious/critical fail the run).
 *   node tools/axe.mjs --base=http://localhost:8093 [--urls=/,/about/...] --out=docs/reports/axe.md
 * The summary table lists every violation; a details section below lists each serious/critical
 * violation with all affected selectors (first 10 per rule/page) so they can be fixed without re-running. */
import { chromium } from 'playwright';
import { AxeBuilder } from '@axe-core/playwright';
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
const args = Object.fromEntries(process.argv.slice(2).map(a => { const [k, ...v] = a.replace(/^--/, '').split('='); return [k, v.length ? v.join('=') : true]; }));
const base = (args.base || 'http://localhost:8093').replace(/\/$/, '');
const urls = args.urls ? String(args.urls).split(',') : ['/', '/about/', '/program/', '/veterans/', '/get-involved/', '/barkode/', '/stories/', '/contact/', '/news/', '/donate/', '/events/', '/campaigns/', '/meet-the-team/', '/photos/', '/?s=dog', '/nonexistent-page/'];
const out = args.out || 'docs/reports/axe.md';
const axeVersion = require('axe-core/package.json').version;
const browser = await chromium.launch();
const md = ['# axe-core accessibility scan', '', `Base ${base} · ${new Date().toISOString()} · axe-core ${axeVersion} · Playwright ${require('playwright/package.json').version} · ${browser.browserType().name()} ${browser.version()}`, '', 'Tags: wcag2a, wcag2aa, wcag21a, wcag21aa, wcag22aa, best-practice. Serious/critical violations fail the run (exit 1).', '', '| URL | Width | Critical | Serious | Moderate | Minor | Details |', '|---|---|---|---|---|---|---|'];
const details = [];
let bad = 0, inFrame = 0; // inFrame: serious/critical groups whose every node lives inside an <iframe> (third-party embed DOM, e.g. YouTube)
for (const u of urls) for (const width of [390, 1440]) {
  const ctx = await browser.newContext({ viewport: { width, height: width < 1024 ? 844 : 900 } });
  const page = await ctx.newPage();
  await page.goto(base + u, { waitUntil: 'networkidle' });
  const r = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice']).analyze();
  const by = i => r.violations.filter(v => v.impact === i);
  bad += by('critical').length + by('serious').length;
  inFrame += [...by('critical'), ...by('serious')].filter(v => v.nodes.every(n => n.target.length > 1 && /^iframe/.test(n.target[0]))).length;
  md.push(`| ${u} | ${width} | ${by('critical').length} | ${by('serious').length} | ${by('moderate').length} | ${by('minor').length} | ${r.violations.map(v => `${v.id} (${v.impact}, ${v.nodes.length}×: \`${v.nodes[0]?.target?.[0] ?? ''}\`)`).join('; ') || '—'} |`);
  for (const v of r.violations) {
    if (!['critical', 'serious'].includes(v.impact)) continue;
    details.push(`### ${u} @ ${width} — \`${v.id}\` (${v.impact}, ${v.nodes.length} node${v.nodes.length === 1 ? '' : 's'})`, '', `${v.help} — ${v.helpUrl}`, '');
    for (const n of v.nodes.slice(0, 10)) details.push(`- \`${n.target.join(' ')}\` — ${n.failureSummary.replace(/\s+/g, ' ').trim()}`);
    if (v.nodes.length > 10) details.push(`- … ${v.nodes.length - 10} more`);
    details.push('');
  }
  await ctx.close();
}
await browser.close();
md.push('', `Result: ${bad ? `**FAIL** — ${bad} serious/critical violation group(s)` : '**PASS** — no serious/critical violations'} across ${urls.length} URLs × 2 widths.${inFrame ? ` ${inFrame} of the ${bad} group(s) are entirely inside a cross-origin <iframe> (third-party embed DOM such as YouTube — not theme/plugin markup); ${bad - inFrame} are in first-party markup.` : ''}`, '');
if (details.length) md.push('## Serious / critical details', '', ...details);
fs.mkdirSync(path.dirname(out), { recursive: true });
fs.writeFileSync(out, md.join('\n') + '\n');
console.log(md.join('\n'));
process.exit(bad ? 1 : 0);
