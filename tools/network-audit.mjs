#!/usr/bin/env node
/** Load each URL and list every request host; fail on requests to the source sites, Google Fonts or CDNs; also collect console errors.
 *   node tools/network-audit.mjs --base=http://localhost:8093 [--urls=...] --out=docs/reports/network-audit.md
 * Requests and console messages are attributed to the main frame (first-party page) or to embedded
 * <iframe>s (third-party embeds such as YouTube). Only main-frame problems fail the run; embedded-frame
 * hosts/warnings are still listed so they stay visible. The document's own status is reported
 * separately (a 404 route answering 404 is correct, not a failed request). */
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';
const args = Object.fromEntries(process.argv.slice(2).map(a => { const [k, ...v] = a.replace(/^--/, '').split('='); return [k, v.length ? v.join('=') : true]; }));
const base = (args.base || 'http://localhost:8093').replace(/\/$/, '');
const urls = args.urls ? String(args.urls).split(',') : ['/', '/about/', '/program/', '/veterans/', '/get-involved/', '/barkode/', '/stories/', '/contact/', '/news/', '/donate/', '/events/', '/campaigns/', '/meet-the-team/', '/photos/', '/back-the-pack/', '/5-questions/'];
const forbidden = /replit\.app|heartlandk9s\.org|fonts\.googleapis|fonts\.gstatic|cdnjs|jsdelivr|unpkg|bootstrapcdn|cdn\./i;
const baseHost = new URL(base).host;
const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const md = ['# Network & console audit', '', `Base ${base} · ${new Date().toISOString()} · ${browser.browserType().name()} ${browser.version()} · viewport 1440×900 · each page loaded to networkidle then scrolled to the bottom (lazy assets)`, '', `Forbidden host pattern: \`${forbidden.source}\` — applied to main-frame requests. Requests made from inside embedded <iframe>s (third-party embeds) are listed separately and do not fail the run.`, ''];
let fail = 0;
for (const u of urls) {
  const page = await ctx.newPage(); // fresh page per URL so late requests from a previous page's embeds are never attributed to this one
  const hosts = new Map(); const frameHosts = new Map(); const errors = []; const frameErrors = []; const failed = [];
  const docUrl = base + u;
  const isMain = r => r.frame() === page.mainFrame();
  const onReq = r => { const h = new URL(r.url()).host; const m = isMain(r) ? hosts : frameHosts; m.set(h, (m.get(h) || 0) + 1); };
  const onFail = r => { if (isMain(r)) failed.push(`(failed) ${r.url()}`); };
  const onResp = r => { if (r.status() >= 400 && isMain(r.request()) && r.url() !== docUrl) failed.push(`${r.status()} ${r.url()}`); };
  const onCon = m => {
    if (!['error', 'warning'].includes(m.type())) return;
    const loc = m.location()?.url || '';
    if (loc === docUrl && /Failed to load resource/.test(m.text())) return; // Chrome's line for the document's own 4xx (reported as document status)
    const fromEmbed = loc && !loc.startsWith('about:') && new URL(loc).host !== baseHost;
    (fromEmbed ? frameErrors : errors).push(`${m.type()}: ${m.text()}${loc ? ` (${new URL(loc).host})` : ''}`);
  };
  const onErr = e => errors.push('pageerror: ' + e.message);
  page.on('request', onReq); page.on('requestfailed', onFail); page.on('response', onResp); page.on('console', onCon); page.on('pageerror', onErr);
  const resp = await page.goto(docUrl, { waitUntil: 'networkidle' });
  await page.evaluate(async () => { window.scrollTo(0, document.body.scrollHeight); await new Promise(r => setTimeout(r, 800)); });
  page.off('request', onReq); page.off('requestfailed', onFail); page.off('response', onResp); page.off('console', onCon); page.off('pageerror', onErr);
  await page.close();
  const external = [...hosts.keys()].filter(h => forbidden.test(h));
  const frameExternal = [...frameHosts.keys()].filter(h => forbidden.test(h));
  const docStatus = resp?.status() ?? 0;
  const bad = external.length || failed.length || errors.length;
  if (bad) fail++;
  md.push(`## ${u} — ${bad ? '❌' : '✅'}`, `- Document status: ${docStatus}`, `- Main-frame hosts: ${[...hosts.entries()].map(([h, n]) => `${h} (${n})`).join(', ')}`, `- Forbidden external hosts (main frame): ${external.join(', ') || 'none'}`, `- Failed/4xx+ requests (main frame): ${failed.join(', ') || 'none'}`, `- Console errors/warnings (first party): ${errors.join(' | ') || 'none'}`);
  if (frameHosts.size) md.push(`- Embedded-frame hosts (third-party embed, informational): ${[...frameHosts.entries()].map(([h, n]) => `${h} (${n})`).join(', ')}${frameExternal.length ? ` — matches forbidden pattern: ${frameExternal.join(', ')} (inside the embed, not first-party)` : ''}`);
  if (frameErrors.length) md.push(`- Console errors/warnings from embedded frames (informational): ${frameErrors.join(' | ')}`);
  md.push('');
}
await browser.close();
md.push(`Result: ${fail ? `**${fail} of ${urls.length} URLs flagged**` : `**PASS** — all ${urls.length} URLs clean`} (a URL is flagged for a forbidden host, a failed/4xx+ sub-request or a console error/warning in the main frame).`, '');
const out = args.out || 'docs/reports/network-audit.md';
fs.mkdirSync(path.dirname(out), { recursive: true });
fs.writeFileSync(out, md.join('\n'));
console.log(md.join('\n'));
process.exit(fail ? 1 : 0);
