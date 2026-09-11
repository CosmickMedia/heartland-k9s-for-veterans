#!/usr/bin/env node
/**
 * Capture full-page + viewport screenshots of a set of routes.
 *
 *   node tools/screenshot.mjs --base=http://localhost:8093 --out=docs/reports/screenshots/wp \
 *        [--routes=/,/about/,...] [--widths=390,1440] [--engines=chromium,webkit,firefox] [--menu]
 *
 * Defaults: the 8 reference routes, widths 390 + 1440, chromium only.
 * Animations/transitions are disabled, fonts awaited, 1.2 s settle. Output files:
 *   <out>/<engine>/<route-slug>-<width>.png (full page) and -<width>-viewport.png
 */
import { chromium, webkit, firefox } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';

const args = Object.fromEntries(process.argv.slice(2).map(a => { const [k, ...v] = a.replace(/^--/, '').split('='); return [k, v.length ? v.join('=') : true]; }));
const base = (args.base || 'http://localhost:8093').replace(/\/$/, '');
const out = args.out || 'docs/reports/screenshots/wp';
const widths = String(args.widths || '390,1440').split(',').map(Number);
const engines = String(args.engines || 'chromium').split(',');
const defaultRoutes = ['/', '/about/', '/program/', '/veterans/', '/get-involved/', '/barkode/', '/stories/', '/contact/'];
const routes = args.routes ? String(args.routes).split(',') : defaultRoutes;
const heights = { 390: 844, 360: 800, 768: 1024, 1024: 900, 1440: 900, 1920: 1080 };
const slug = r => (r === '/' ? 'home' : r.replace(/^\/|\/$/g, '').replace(/[\/?=&]+/g, '-'));
const settleCss = `*,*::before,*::after{animation:none!important;transition:none!important;caret-color:transparent!important}`;

for (const engineName of engines) {
  const engine = { chromium, webkit, firefox }[engineName];
  if (!engine) { console.error('unknown engine', engineName); process.exit(1); }
  const browser = await engine.launch();
  for (const width of widths) {
    const context = await browser.newContext({ viewport: { width, height: heights[width] || 900 }, deviceScaleFactor: 1, reducedMotion: 'no-preference' });
    const page = await context.newPage();
    for (const route of routes) {
      const url = base + route;
      const dir = path.join(out, engineName);
      fs.mkdirSync(dir, { recursive: true });
      try {
        await page.goto(url, { waitUntil: 'networkidle', timeout: 60000 });
        await page.addStyleTag({ content: settleCss });
        await page.evaluate(() => document.fonts && document.fonts.ready);
        await page.waitForTimeout(1200);
        // Ensure lazy images above and below the fold are loaded before a full-page capture.
        await page.evaluate(async () => {
          const imgs = Array.from(document.images);
          imgs.forEach(i => { i.loading = 'eager'; });
          await Promise.all(imgs.map(i => i.complete ? null : new Promise(r => { i.onload = i.onerror = r; })));
          window.scrollTo(0, document.body.scrollHeight); await new Promise(r => setTimeout(r, 300)); window.scrollTo(0, 0);
        });
        await page.waitForTimeout(300);
        const height = await page.evaluate(() => document.documentElement.scrollHeight);
        await page.screenshot({ path: path.join(dir, `${slug(route)}-${width}.png`), fullPage: true });
        await page.screenshot({ path: path.join(dir, `${slug(route)}-${width}-viewport.png`), fullPage: false });
        console.log(`${engineName} ${width} ${route} -> ${height}px`);
        if (args.menu && route === '/' && width < 1024) {
          const toggle = await page.$('.hk9-header__toggle, nav button');
          if (toggle) { await toggle.click(); await page.waitForTimeout(400); await page.screenshot({ path: path.join(dir, `home-${width}-menu-open.png`) }); }
        }
      } catch (e) {
        console.error(`FAILED ${engineName} ${width} ${route}: ${e.message}`);
      }
    }
    await context.close();
  }
  await browser.close();
}
