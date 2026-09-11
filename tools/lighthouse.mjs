#!/usr/bin/env node
/**
 * Run Lighthouse (mobile preset, 3 runs, median) on representative pages and write a markdown table.
 *   node tools/lighthouse.mjs --base=http://localhost:8093 --out=docs/reports/lighthouse [--urls=/,/about/] [--runs=3]
 */
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
const args = Object.fromEntries(process.argv.slice(2).map(a => { const [k, ...v] = a.replace(/^--/, '').split('='); return [k, v.length ? v.join('=') : true]; }));
const base = (args.base || 'http://localhost:8093').replace(/\/$/, '');
const out = args.out || 'docs/reports/lighthouse';
const runs = Number(args.runs || 3);
const urls = args.urls ? String(args.urls).split(',') : ['/', '/about/', '/program/', '/contact/', '/news/'];
fs.mkdirSync(out, { recursive: true });
const median = a => { const s = [...a].sort((x, y) => x - y); return s[Math.floor(s.length / 2)]; };
const rows = [];
for (const u of urls) {
  const scores = { performance: [], accessibility: [], 'best-practices': [], seo: [], lcp: [], cls: [], tbt: [], fcp: [] };
  for (let i = 0; i < runs; i++) {
    const file = path.join(out, `${u === '/' ? 'home' : u.replace(/^\/|\/$/g, '').replace(/\//g, '-')}-run${i + 1}.json`);
    execFileSync('npx', ['lighthouse', base + u, '--quiet', '--chrome-flags=--headless=new --no-sandbox', '--preset=perf', '--only-categories=performance,accessibility,best-practices,seo', '--form-factor=mobile', '--screenEmulation.mobile', '--throttling-method=simulate', '--output=json', `--output-path=${file}`], { stdio: 'inherit' });
    const j = JSON.parse(fs.readFileSync(file, 'utf8'));
    for (const c of ['performance', 'accessibility', 'best-practices', 'seo']) scores[c].push(Math.round(j.categories[c].score * 100));
    scores.lcp.push(j.audits['largest-contentful-paint'].numericValue); scores.cls.push(j.audits['cumulative-layout-shift'].numericValue); scores.tbt.push(j.audits['total-blocking-time'].numericValue); scores.fcp.push(j.audits['first-contentful-paint'].numericValue);
  }
  rows.push({ u, perf: median(scores.performance), a11y: median(scores.accessibility), bp: median(scores['best-practices']), seo: median(scores.seo), lcp: (median(scores.lcp) / 1000).toFixed(2), cls: median(scores.cls).toFixed(3), tbt: Math.round(median(scores.tbt)), fcp: (median(scores.fcp) / 1000).toFixed(2), all: scores.performance.join('/') });
}
const md = ['# Lighthouse (mobile, simulated throttling, median of ' + runs + ')', '', `Base: ${base} · ${new Date().toISOString()} · Lighthouse ${JSON.parse(fs.readFileSync('node_modules/lighthouse/package.json')).version}`, '', '| URL | Performance (runs) | Accessibility | Best practices | SEO | LCP s | CLS | TBT ms | FCP s |', '|---|---|---|---|---|---|---|---|---|'];
for (const r of rows) md.push(`| ${r.u} | ${r.perf} (${r.all}) | ${r.a11y} | ${r.bp} | ${r.seo} | ${r.lcp} | ${r.cls} | ${r.tbt} | ${r.fcp} |`);
fs.writeFileSync(path.join(out, 'report.md'), md.join('\n') + '\n');
console.log(md.join('\n'));
