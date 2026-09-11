#!/usr/bin/env node
/**
 * Run Lighthouse (mobile preset by default, N runs, median) on representative pages and write a markdown report.
 *   node tools/lighthouse.mjs --base=http://localhost:8093 --out=docs/reports/lighthouse [--urls=/,/about/] [--runs=3] [--desktop] [--no-html]
 * - Mobile: simulated throttling (Lighthouse "Slow 4G": 150 ms RTT, 1.6 Mbps, 4× CPU), 412×823 @ 1.75 DPR.
 * - --desktop: Lighthouse's desktop preset (simulated 40 ms RTT / 10 Mbps / 1× CPU, 1350×940 @ 1).
 * - For any URL whose median Performance score is < 90 (unless --no-html) one extra run is saved as an HTML
 *   report (<slug>-diagnostic.report.html) and its top opportunities/diagnostics are summarised in the report.
 * Every run's JSON is kept in <out>/ so numbers can be re-derived; --reuse re-renders the report from those
 * files without re-running Lighthouse (runs whose JSON is missing are still executed).
 * Privacy: for URLs matching --private (default /barkode/ — the registry records) the saved JSON is scrubbed of
 * screenshots, DOM snippets/labels and page text, and no HTML diagnostic is written.
 */
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
const args = Object.fromEntries(process.argv.slice(2).map(a => { const [k, ...v] = a.replace(/^--/, '').split('='); return [k, v.length ? v.join('=') : true]; }));
const base = (args.base || 'http://localhost:8093').replace(/\/$/, '');
const out = args.out || 'docs/reports/lighthouse';
const runs = Number(args.runs || 3);
const desktop = Boolean(args.desktop);
const urls = args.urls ? String(args.urls).split(',') : ['/', '/about/', '/program/', '/contact/', '/news/'];
const slug = u => (u === '/' ? 'home' : u.replace(/^\/|\/$/g, '').replace(/[\/?=&]+/g, '-'));
const cats = ['performance', 'accessibility', 'best-practices', 'seo'];
const chromeFlags = '--chrome-flags=--headless=new --no-sandbox';
const privateRe = new RegExp(args.private || '/barkode/');
const scrub = file => { // remove anything that reproduces page content (screenshots, node snippets, text) from a saved LHR
  const j = JSON.parse(fs.readFileSync(file, 'utf8'));
  delete j.fullPageScreenshot;
  for (const id of ['full-page-screenshot', 'final-screenshot', 'screenshot-thumbnails']) if (j.audits[id]) delete j.audits[id].details;
  const walk = o => { if (Array.isArray(o)) return o.forEach(walk); if (o && typeof o === 'object') { for (const k of Object.keys(o)) { if (typeof o[k] === 'string' && (['snippet', 'nodeLabel', 'explanation', 'text'].includes(k) || o[k].startsWith('data:image'))) o[k] = '[scrubbed]'; else walk(o[k]); } } };
  for (const a of Object.values(j.audits)) if (a.details) walk(a.details);
  j.hk9Scrubbed = 'screenshots, node snippets/labels and text removed (private URL)';
  fs.writeFileSync(file, JSON.stringify(j));
};
const modeFlags = desktop ? ['--preset=desktop'] : ['--form-factor=mobile', '--screenEmulation.mobile'];
const lh = (url, extra) => execFileSync('npx', ['lighthouse', url, '--quiet', chromeFlags, `--only-categories=${cats.join(',')}`, ...modeFlags, '--throttling-method=simulate', ...extra], { stdio: 'inherit' });
fs.mkdirSync(out, { recursive: true });
const median = a => { const s = [...a].sort((x, y) => x - y); return s[Math.floor(s.length / 2)]; };
const rows = []; const diagnostics = []; let env = null;
for (const u of urls) {
  const scores = Object.fromEntries([...cats, 'lcp', 'cls', 'tbt', 'fcp', 'si'].map(k => [k, []]));
  const files = [];
  for (let i = 0; i < runs; i++) {
    const file = path.join(out, `${slug(u)}${desktop ? '-desktop' : ''}-run${i + 1}.json`);
    if (!(args.reuse && fs.existsSync(file))) { lh(base + u, ['--output=json', `--output-path=${file}`]); if (privateRe.test(u)) scrub(file); }
    const j = JSON.parse(fs.readFileSync(file, 'utf8'));
    if (j.runtimeError) console.error(`runtimeError on ${u} run ${i + 1}: ${j.runtimeError.code} ${j.runtimeError.message}`);
    env ??= { lh: j.lighthouseVersion, ua: j.environment.hostUserAgent, settings: j.configSettings };
    files.push({ file, j });
    for (const c of cats) scores[c].push(Math.round((j.categories[c].score ?? 0) * 100));
    scores.lcp.push(j.audits['largest-contentful-paint'].numericValue); scores.cls.push(j.audits['cumulative-layout-shift'].numericValue); scores.tbt.push(j.audits['total-blocking-time'].numericValue); scores.fcp.push(j.audits['first-contentful-paint'].numericValue); scores.si.push(j.audits['speed-index'].numericValue);
  }
  const perf = median(scores.performance);
  rows.push({ u, perf, a11y: median(scores.accessibility), bp: median(scores['best-practices']), seo: median(scores.seo), lcp: (median(scores.lcp) / 1000).toFixed(2), cls: median(scores.cls).toFixed(3), tbt: Math.round(median(scores.tbt)), fcp: (median(scores.fcp) / 1000).toFixed(2), si: (median(scores.si) / 1000).toFixed(2), runs: cats.map(c => `${c}: ${scores[c].join('/')}`).join(' · ') });
  if (perf < 90 && privateRe.test(u) && !args['no-html']) diagnostics.push(`### ${u} — Performance ${perf}: HTML diagnostic not written (private/registry URL; its report would embed the page). The opportunities match the other pages (see the raw, scrubbed run JSON).`, '');
  if (perf < 90 && !args['no-html'] && !privateRe.test(u)) {
    const prefix = path.join(out, `${slug(u)}${desktop ? '-desktop' : ''}-diagnostic`);
    if (!(args.reuse && fs.existsSync(`${prefix}.report.json`))) lh(base + u, ['--output=html', '--output=json', `--output-path=${prefix}`]); // with two formats Lighthouse writes <prefix>.report.html + <prefix>.report.json
    const html = `${prefix}.report.html`;
    const j = JSON.parse(fs.readFileSync(`${prefix}.report.json`, 'utf8'));
    const opp = Object.values(j.audits).filter(a => a.details?.type === 'opportunity' && (a.score ?? 1) < 1).sort((a, b) => (b.details.overallSavingsMs || 0) - (a.details.overallSavingsMs || 0)).slice(0, 8);
    const diag = Object.values(j.audits).filter(a => a.details?.type !== 'opportunity' && a.scoreDisplayMode === 'numeric' && a.score !== null && a.score < 0.9 && (a.displayValue || a.metricSavings) && !/-metric$|^(first-contentful-paint|largest-contentful-paint|total-blocking-time|cumulative-layout-shift|speed-index|interactive|max-potential-fid)$/.test(a.id)).slice(0, 8);
    const failing = Object.values(j.audits).filter(a => a.scoreDisplayMode === 'binary' && a.score === 0 && j.categories.performance.auditRefs.some(r => r.id === a.id)).slice(0, 8);
    diagnostics.push(`### ${u} — Performance ${Math.round(j.categories.performance.score * 100)} in the diagnostic run (${path.basename(html)})`, '', `Metrics: FCP ${(j.audits['first-contentful-paint'].numericValue / 1000).toFixed(2)} s · LCP ${(j.audits['largest-contentful-paint'].numericValue / 1000).toFixed(2)} s (${(() => { const it = j.audits['largest-contentful-paint-element']?.details?.items ?? []; const el = it[0]?.items?.[0]?.node ?? it[0]?.node; const ph = it[1]?.items?.map(x => `${x.phase} ${Math.round(x.timing)} ms`).join(', '); return `${el?.selector ?? 'n/a'}${el?.snippet ? ' ' + el.snippet.replace(/\s+/g, ' ').replace(base, '').replace(/alt="[^"]*"/, 'alt="…"').slice(0, 160) : ''}${ph ? ` — phases: ${ph}` : ''}`; })()}) · TBT ${Math.round(j.audits['total-blocking-time'].numericValue)} ms · CLS ${j.audits['cumulative-layout-shift'].numericValue.toFixed(3)} · SI ${(j.audits['speed-index'].numericValue / 1000).toFixed(2)} s`, '', 'Top opportunities (estimated savings):', '', ...(opp.length ? opp.map(a => `- **${a.title}** — ${a.displayValue || ''}${a.details.overallSavingsBytes ? ` (${Math.round(a.details.overallSavingsBytes / 1024)} KiB)` : ''}${a.details.items?.length ? `: ${a.details.items.slice(0, 3).map(i => (i.url || i.node?.snippet || i.source?.url || '').toString().replace(base, '').slice(0, 120)).filter(Boolean).join(', ')}` : ''}`) : ['- none']), '', 'Diagnostics scoring < 0.9 / failing:', '', ...([...diag, ...failing].length ? [...diag, ...failing].map(a => `- **${a.title}** — ${a.displayValue || `score ${a.score}`}${a.details?.items?.length ? `: ${a.details.items.slice(0, 3).map(i => (i.url || i.node?.snippet || i.source?.url || i.issueType || '').toString().replace(base, '').slice(0, 100)).filter(Boolean).join(', ')}` : ''}`) : ['- none']), '');
  }
}
const s = env.settings; const t = s.throttling;
const md = [`# Lighthouse (${desktop ? 'desktop preset' : 'mobile'}, ${s.throttlingMethod} throttling, median of ${runs})`, '', `Base: ${base} · ${new Date().toISOString()} · Lighthouse ${env.lh} · ${env.ua.match(/(HeadlessChrome|Chrome)\/[\d.]+/)?.[0] ?? env.ua}`, '', `Settings: formFactor=${s.formFactor} · screen ${s.screenEmulation.width}×${s.screenEmulation.height} @ ${s.screenEmulation.deviceScaleFactor} (mobile=${s.screenEmulation.mobile}) · throttlingMethod=${s.throttlingMethod} (rtt ${t.rttMs} ms, throughput ${t.throughputKbps} Kbps, cpuSlowdown ${t.cpuSlowdownMultiplier}×) · categories ${cats.join(', ')}`, '', '| URL | Performance | Accessibility | Best practices | SEO | FCP s | LCP s | TBT ms | CLS | SI s | Per-run scores |', '|---|---|---|---|---|---|---|---|---|---|---|'];
for (const r of rows) md.push(`| ${r.u} | **${r.perf}** | ${r.a11y} | ${r.bp} | ${r.seo} | ${r.fcp} | ${r.lcp} | ${r.tbt} | ${r.cls} | ${r.si} | ${r.runs} |`);
md.push('', `Medians are per-metric medians over the ${runs} runs (so a row's metrics may come from different runs). Raw JSON per run is alongside this file.`, '');
if (diagnostics.length) md.push('## Diagnostic runs (Performance < 90)', '', ...diagnostics);
fs.writeFileSync(path.join(out, desktop ? 'report-desktop.md' : 'report.md'), md.join('\n') + '\n');
console.log(md.join('\n'));
