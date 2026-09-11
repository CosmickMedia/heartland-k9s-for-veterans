#!/usr/bin/env node
/** Load each URL and list every request host; fail on requests to the source sites, Google Fonts or CDNs; also collect console errors.
 *   node tools/network-audit.mjs --base=http://localhost:8093 [--urls=...] --out=docs/reports/network-audit.md */
import { chromium } from 'playwright';
import fs from 'node:fs';
const args = Object.fromEntries(process.argv.slice(2).map(a => { const [k, ...v] = a.replace(/^--/, '').split('='); return [k, v.length ? v.join('=') : true]; }));
const base = (args.base || 'http://localhost:8093').replace(/\/$/, '');
const urls = args.urls ? String(args.urls).split(',') : ['/', '/about/', '/program/', '/veterans/', '/get-involved/', '/barkode/', '/stories/', '/contact/', '/news/', '/donate/', '/events/', '/campaigns/', '/meet-the-team/', '/photos/', '/back-the-pack/', '/5-questions/'];
const forbidden = /replit\.app|heartlandk9s\.org|fonts\.googleapis|fonts\.gstatic|cdnjs|jsdelivr|unpkg|bootstrapcdn|cdn\./i;
const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await ctx.newPage();
const md = ['# Network & console audit', '', `Base ${base} · ${new Date().toISOString()}`, ''];
let fail = 0;
for (const u of urls) {
  const hosts = new Map(); const errors = []; const failed = [];
  const onReq = r => { const h = new URL(r.url()).host; hosts.set(h, (hosts.get(h) || 0) + 1); };
  const onFail = r => failed.push(r.url());
  const onResp = r => { if (r.status() >= 400) failed.push(`${r.status()} ${r.url()}`); };
  const onCon = m => { if (['error', 'warning'].includes(m.type())) errors.push(`${m.type()}: ${m.text()}`); };
  page.on('request', onReq); page.on('requestfailed', onFail); page.on('response', onResp); page.on('console', onCon); page.on('pageerror', e => errors.push('pageerror: ' + e.message));
  await page.goto(base + u, { waitUntil: 'networkidle' });
  await page.evaluate(async () => { window.scrollTo(0, document.body.scrollHeight); await new Promise(r => setTimeout(r, 800)); });
  page.off('request', onReq); page.off('requestfailed', onFail); page.off('response', onResp); page.off('console', onCon);
  const external = [...hosts.keys()].filter(h => forbidden.test(h));
  if (external.length || failed.length || errors.length) fail++;
  md.push(`## ${u}`, `- Hosts: ${[...hosts.entries()].map(([h, n]) => `${h} (${n})`).join(', ')}`, `- Forbidden external hosts: ${external.join(', ') || 'none'}`, `- Failed/4xx+ requests: ${failed.join(', ') || 'none'}`, `- Console errors/warnings: ${errors.join(' | ') || 'none'}`, '');
}
await browser.close();
fs.mkdirSync('docs/reports', { recursive: true });
fs.writeFileSync(args.out || 'docs/reports/network-audit.md', md.join('\n'));
console.log(md.join('\n'));
process.exit(fail ? 1 : 0);
