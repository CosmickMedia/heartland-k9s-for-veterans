// Keyboard walk with Playwright (chromium) for docs/reports/keyboard.md.
//   node docs/reports/screenshots/keyboard/keyboard-walk.mjs   (from the repo root; writes the PNGs + results.json next to itself)
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
const base = process.env.HK9_BASE || 'http://localhost:8093';
const dir = path.dirname(fileURLToPath(import.meta.url));
fs.mkdirSync(dir, { recursive: true });
const browser = await chromium.launch();
const results = [];
const step = (name, pass, evidence) => { results.push({ name, pass, evidence }); console.log(`${pass ? 'PASS' : 'FAIL'} ${name} — ${evidence}`); };
const active = p => p.evaluate(() => { const a = document.activeElement; if (!a || a === document.body) return { tag: 'body' }; const r = a.getBoundingClientRect(); const cs = getComputedStyle(a); return { tag: a.tagName.toLowerCase(), cls: a.className && String(a.className).split(' ').slice(0, 3).join('.'), id: a.id || null, text: (a.getAttribute('aria-label') || a.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 60), href: a.getAttribute('href'), rect: [Math.round(r.x), Math.round(r.y), Math.round(r.width), Math.round(r.height)], visible: r.width > 0 && r.height > 0 && cs.visibility !== 'hidden', outline: cs.outlineStyle !== 'none' ? `${cs.outlineWidth} ${cs.outlineStyle} ${cs.outlineColor}` : 'none', boxShadow: cs.boxShadow !== 'none' ? cs.boxShadow.slice(0, 60) : 'none', focusVisible: a.matches(':focus-visible') }; });
const shot = (p, name) => p.screenshot({ path: `${dir}/${name}.png` });

// ---------- 1. Home @ 1440: skip link -> header links -> Donate -> hero CTAs ----------
{
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const p = await ctx.newPage();
  await p.goto(base + '/', { waitUntil: 'networkidle' });
  const seq = [];
  for (let i = 0; i < 14; i++) { await p.keyboard.press('Tab'); const a = await active(p); seq.push(a); if (i === 0) await shot(p, '01-home-1440-skip-link'); if (i === 2) await shot(p, '02-home-1440-nav-link'); if (a.cls?.includes('hk9-header__cta')) await shot(p, '03-home-1440-donate'); if (a.cls?.includes('hk9-btn--lg') && a.text.startsWith('Support')) await shot(p, '04-home-1440-hero-cta-1'); if (a.text.startsWith('Apply')) { await shot(p, '05-home-1440-hero-cta-2'); break; } }
  const labels = seq.map(a => `${a.tag}${a.cls ? '.' + a.cls.split('.')[0] : ''}[${a.text}]${a.focusVisible ? '' : '(no :focus-visible)'}${a.visible ? '' : '(hidden!)'}`);
  const expected = ['Skip to content', 'Heartland K9s', 'About', 'Program', 'Veterans', 'BarKode', 'Stories', 'Get Involved', 'Contact', 'Donate Now', 'Support a Service Dog', 'Apply for a Dog'];
  const texts = seq.map(a => a.text);
  const ok = expected.every((e, i) => texts[i]?.startsWith(e));
  step('Home 1440: Tab order skip link → brand → 7 nav links → Donate → hero CTAs', ok, labels.join(' → '));
  step('Home 1440: skip link becomes visible on focus', seq[0].visible && seq[0].rect[1] >= 0 && seq[0].rect[3] > 0, `rect=${JSON.stringify(seq[0].rect)} outline=${seq[0].outline} shadow=${seq[0].boxShadow}`);
  step('Home 1440: every focused control has a visible focus indicator (outline or box-shadow)', seq.every(a => a.outline !== 'none' || a.boxShadow !== 'none'), seq.map(a => `${a.text.slice(0, 12)}: ${a.outline !== 'none' ? a.outline : a.boxShadow}`).join('; '));
  // Skip link activation
  await p.goto(base + '/', { waitUntil: 'networkidle' }); await p.keyboard.press('Tab'); await p.keyboard.press('Enter'); await p.waitForTimeout(200);
  const afterSkip = await p.evaluate(() => ({ hash: location.hash, active: document.activeElement.id || document.activeElement.tagName, mainTabindex: document.getElementById('main')?.getAttribute('tabindex'), scrollY: Math.round(scrollY) }));
  await p.keyboard.press('Tab'); const afterSkipTab = await active(p);
  step('Home 1440: Enter on skip link moves focus into <main> (next Tab lands inside main content)', afterSkip.hash === '#main' && !afterSkipTab.cls?.includes('hk9-header'), `${JSON.stringify(afterSkip)}; next Tab → ${afterSkipTab.tag}[${afterSkipTab.text}]`);
  await ctx.close();
}

// ---------- 2. Home @ 390: mobile menu by keyboard ----------
{
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const p = await ctx.newPage();
  await p.goto(base + '/', { waitUntil: 'networkidle' });
  const seq = [];
  for (let i = 0; i < 4; i++) { await p.keyboard.press('Tab'); const a = await active(p); seq.push(a); if (a.cls?.includes('hk9-header__toggle')) break; }
  const onToggle = seq[seq.length - 1].cls?.includes('hk9-header__toggle');
  step('Home 390: Tab reaches the menu toggle (skip link → brand → toggle)', onToggle && seq.length === 3, seq.map(a => `${a.tag}[${a.text}]`).join(' → '));
  await shot(p, '06-home-390-toggle-focus');
  await p.keyboard.press('Enter'); await p.waitForTimeout(300);
  const opened = await p.evaluate(() => ({ expanded: document.querySelector('[data-hk9-toggle]').getAttribute('aria-expanded'), hidden: document.getElementById('hk9-mobile-menu').hidden, visible: (() => { const r = document.getElementById('hk9-mobile-menu').getBoundingClientRect(); return r.height > 0; })(), label: document.querySelector('[data-hk9-toggle]').getAttribute('aria-label') || document.querySelector('[data-hk9-toggle]').textContent.trim() }));
  step('Home 390: Enter on toggle opens the panel (aria-expanded=true, panel not hidden)', opened.expanded === 'true' && !opened.hidden && opened.visible, JSON.stringify(opened));
  await shot(p, '07-home-390-menu-open');
  const panelSeq = [];
  for (let i = 0; i < 12; i++) { await p.keyboard.press('Tab'); const a = await active(p); const inPanel = await p.evaluate(() => !!document.activeElement.closest('#hk9-mobile-menu')); panelSeq.push({ ...a, inPanel }); if (!inPanel) break; if (i === 0) await shot(p, '08-home-390-menu-first-item'); }
  const inside = panelSeq.filter(a => a.inPanel);
  step('Home 390: Tab moves through the panel items (7 links + Donate) in order', inside.length === 8 && inside[0].text === 'About' && inside[7].text.startsWith('Donate'), inside.map(a => `${a.tag}[${a.text}]`).join(' → ') + (panelSeq.length > inside.length ? ` → (left panel to ${panelSeq[panelSeq.length - 1].tag}[${panelSeq[panelSeq.length - 1].text}])` : ''));
  const stateAfterLeave = await p.evaluate(() => ({ expanded: document.querySelector('[data-hk9-toggle]').getAttribute('aria-expanded'), hidden: document.getElementById('hk9-mobile-menu').hidden }));
  step('Home 390: tabbing past the last panel item closes the panel (focusout rule)', stateAfterLeave.expanded === 'false' && stateAfterLeave.hidden, JSON.stringify(stateAfterLeave));
  await shot(p, '09-home-390-menu-last-item-and-after');
  // Reopen and Escape
  await p.evaluate(() => document.querySelector('[data-hk9-toggle]').focus()); await p.keyboard.press('Enter'); await p.waitForTimeout(200); await p.keyboard.press('Tab'); await p.keyboard.press('Tab');
  const before = await active(p);
  await p.keyboard.press('Escape'); await p.waitForTimeout(200);
  const afterEsc = await p.evaluate(() => ({ expanded: document.querySelector('[data-hk9-toggle]').getAttribute('aria-expanded'), hidden: document.getElementById('hk9-mobile-menu').hidden, activeIsToggle: document.activeElement === document.querySelector('[data-hk9-toggle]') }));
  step('Home 390: Escape closes the open panel and returns focus to the toggle', afterEsc.expanded === 'false' && afterEsc.hidden && afterEsc.activeIsToggle, `focus was on ${before.tag}[${before.text}] → ${JSON.stringify(afterEsc)}`);
  await shot(p, '10-home-390-after-escape');
  await ctx.close();
}

// ---------- 3. Contact form ----------
{
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const p = await ctx.newPage();
  await p.goto(base + '/contact/', { waitUntil: 'networkidle' });
  await p.evaluate(() => document.querySelector('#hk9-form-contact-first_name, .hk9-form--contact input[name="first_name"], .hk9-form--contact input:not([type=hidden]):not([tabindex="-1"])').scrollIntoView({ block: 'center' }));
  await p.evaluate(() => { const first = document.querySelector('.hk9-form--contact'); const before = document.createElement('a'); before.href = '#'; before.id = 'kb-anchor'; before.textContent = 'anchor'; first.parentNode.insertBefore(before, first); before.focus(); });
  const seq = [];
  for (let i = 0; i < 12; i++) { await p.keyboard.press('Tab'); const a = await active(p); const inForm = await p.evaluate(() => !!document.activeElement.closest('.hk9-form--contact')); seq.push({ ...a, inForm }); if (a.tag === 'button' && /send/i.test(a.text)) break; if (!inForm) break; }
  const names = await p.evaluate(() => Array.from(document.querySelectorAll('.hk9-form--contact [name]:not([type=hidden])')).map(e => `${e.tagName.toLowerCase()}[${e.name}]${e.tabIndex < 0 ? '(tabindex -1)' : ''}`));
  step('Contact: Tab order goes first name → last name → email → subject → message → Send (honeypot skipped)', seq.map(a => a.id || a.text).join(' → ').includes('first_name') && seq.every(a => a.inForm) && seq.some(a => a.id?.includes('last_name')) && seq.some(a => a.id?.includes('email')) && seq.some(a => a.id?.includes('subject')) && seq.some(a => a.id?.includes('message')) && /send/i.test(seq[seq.length - 1].text) && !seq.some(a => a.id?.includes('hk9_website')), `${seq.map(a => `${a.tag}#${a.id || ''}[${a.text.slice(0, 14)}]`).join(' → ')} | controls: ${names.join(', ')}`);
  step('Contact: each form control shows a focus indicator', seq.every(a => a.outline !== 'none' || a.boxShadow !== 'none'), seq.map(a => `${a.id || a.text.slice(0, 10)}: ${a.outline !== 'none' ? a.outline : a.boxShadow}`).join('; '));
  await p.evaluate(() => document.getElementById('kb-anchor')?.remove());
  await p.evaluate(() => { const e = document.getElementById('hk9-form-contact-email'); e.scrollIntoView({ block: 'center', behavior: 'instant' }); e.focus({ preventScroll: true }); }); await p.waitForTimeout(300); await shot(p, '11-contact-field-focus');
  // Submit the invalid (empty) form the way a keyboard user would: Tab to the Send button (letting the smooth scroll settle), then Enter.
  await p.goto(base + '/contact/', { waitUntil: 'networkidle' });
  let name = ''; for (let i = 0; i < 40 && !/send/i.test(name); i++) { await p.keyboard.press('Tab'); name = await p.evaluate(() => document.activeElement.textContent.trim()); }
  await p.waitForTimeout(700);
  await p.keyboard.press('Enter'); await p.waitForTimeout(900);
  const after = await p.evaluate(() => { const s = document.getElementById('hk9-form-contact-summary'); const a = document.activeElement; const r = s.getBoundingClientRect(); const h = document.querySelector('.hk9-header').getBoundingClientRect(); return { summaryHidden: s.hidden, summaryRole: s.getAttribute('role'), summaryText: s.textContent.replace(/\s+/g, ' ').trim().slice(0, 200), activeIsSummary: a === s, activeDesc: `${a.tagName.toLowerCase()}#${a.id}`, invalidCount: document.querySelectorAll('.hk9-form--contact [aria-invalid="true"]').length, describedby: document.getElementById('hk9-form-contact-first_name')?.getAttribute('aria-describedby'), noValidate: document.querySelector('.hk9-form--contact').noValidate, url: location.href, summaryTop: Math.round(r.top), headerBottom: Math.round(h.bottom), obscuredPx: Math.max(0, Math.round(h.bottom - r.top)), scrollPaddingTop: getComputedStyle(document.documentElement).scrollPaddingTop }; });
  step('Contact: submitting an invalid form moves focus to the error summary (role=alert)', !after.summaryHidden && after.activeIsSummary && after.summaryRole === 'alert', JSON.stringify(after));
  step('Contact: the focused error summary is not obscured by the sticky header (scroll-padding-top honoured)', after.obscuredPx === 0 && after.summaryTop >= after.headerBottom, `summaryTop=${after.summaryTop}px headerBottom=${after.headerBottom}px scroll-padding-top=${after.scrollPaddingTop}`);
  await shot(p, '12-contact-error-summary-focus');
  // Summary links move focus to the field
  await p.keyboard.press('Tab'); const link = await active(p); await p.keyboard.press('Enter'); await p.waitForTimeout(700); const field = await active(p);
  step('Contact: first summary link focuses its field', link.tag === 'a' && field.tag !== 'a' && field.id?.startsWith('hk9-form-contact-'), `${link.tag}[${link.text}] → ${field.tag}#${field.id} aria-invalid=${await p.evaluate(() => document.activeElement.getAttribute('aria-invalid'))}`);
  await shot(p, '13-contact-summary-link-to-field');
  await ctx.close();
}

// ---------- 4. Photos lightbox ----------
{
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const p = await ctx.newPage();
  await p.goto(base + '/photos/', { waitUntil: 'networkidle' });
  const info = await p.evaluate(() => ({ figures: document.querySelectorAll('.wp-lightbox-container').length, triggers: document.querySelectorAll('.wp-lightbox-container .lightbox-trigger').length, overlay: !!document.querySelector('.wp-lightbox-overlay'), overlayRole: document.querySelector('.wp-lightbox-overlay')?.getAttribute('role'), overlayModal: document.querySelector('.wp-lightbox-overlay')?.getAttribute('aria-modal'), firstTriggerLabel: document.querySelector('.lightbox-trigger')?.getAttribute('aria-label') }));
  step('Photos: gallery images have keyboard lightbox triggers (WP core image lightbox)', info.triggers > 0 && info.overlay, JSON.stringify(info));
  await p.evaluate(() => { const t = document.querySelector('.lightbox-trigger'); t.scrollIntoView({ block: 'center' }); const a = document.createElement('a'); a.href = '#'; a.id = 'kb-anchor'; a.textContent = 'x'; t.closest('figure').insertBefore(a, t.closest('figure').firstChild); a.focus(); });
  await p.keyboard.press('Tab'); await p.waitForTimeout(400); /* WP core fades the trigger in over .2s */ const trig = await active(p); await p.evaluate(() => document.getElementById('kb-anchor')?.remove());
  step('Photos: Tab reaches the first lightbox trigger button', trig.cls?.includes('lightbox-trigger'), `${trig.tag}.${trig.cls}[${trig.text}] outline=${trig.outline} shadow=${trig.boxShadow} focusVisible=${trig.focusVisible}`);
  await shot(p, '14-photos-trigger-focus');
  await p.keyboard.press('Enter'); await p.waitForTimeout(600);
  const open = await p.evaluate(() => { const o = document.querySelector('.wp-lightbox-overlay'); const a = document.activeElement; return { active: o?.classList.contains('active'), visibility: getComputedStyle(o).visibility, ariaModal: o.getAttribute('aria-modal'), role: o.getAttribute('role'), activeInOverlay: o.contains(a), activeDesc: `${a.tagName.toLowerCase()}.${String(a.className).split(' ')[0]}[${a.getAttribute('aria-label') || ''}]`, bodyOverflow: getComputedStyle(document.body).overflow, imgSrc: o.querySelector('img')?.currentSrc?.split('/').pop() }; });
  step('Photos: Enter opens the lightbox (overlay active, role=dialog, aria-modal, focus inside)', open.active && open.visibility === 'visible' && open.role === 'dialog' && open.ariaModal === 'true' && open.activeInOverlay, JSON.stringify(open));
  await shot(p, '15-photos-lightbox-open');
  await p.keyboard.press('Tab'); const inDlg1 = await active(p); await p.keyboard.press('Tab'); const inDlg2 = await active(p); await p.keyboard.press('Tab'); const inDlg3 = await active(p);
  const trapped = await p.evaluate(() => document.querySelector('.wp-lightbox-overlay').contains(document.activeElement));
  step('Photos: Tab cycles within the open lightbox (focus stays inside the dialog)', trapped, [inDlg1, inDlg2, inDlg3].map(a => `${a.tag}.${a.cls?.split('.')[0]}[${a.text}]`).join(' → '));
  await shot(p, '16-photos-lightbox-tab');
  await p.keyboard.press('Escape'); await p.waitForTimeout(600);
  const closed = await p.evaluate(() => { const o = document.querySelector('.wp-lightbox-overlay'); const a = document.activeElement; return { active: o.classList.contains('active'), visibility: getComputedStyle(o).visibility, activeDesc: `${a.tagName.toLowerCase()}.${String(a.className).split(' ')[0]}[${a.getAttribute('aria-label') || ''}]`, focusOnTrigger: a.classList.contains('lightbox-trigger'), focusInFigure: !!a.closest('.wp-lightbox-container') }; });
  step('Photos: Escape closes the lightbox and returns focus to the trigger', !closed.active && closed.visibility === 'hidden' && (closed.focusOnTrigger || closed.focusInFigure), JSON.stringify(closed));
  await shot(p, '17-photos-lightbox-closed');
  await ctx.close();
}
await browser.close();
fs.writeFileSync(`${dir}/results.json`, JSON.stringify(results, null, 2));
console.log(`\n${results.filter(r => r.pass).length}/${results.length} passed`);
