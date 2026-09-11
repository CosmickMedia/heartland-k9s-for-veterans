(() => {
const cs = (el, props) => { if(!el) return null; const s = getComputedStyle(el); const o = {}; props.forEach(p => o[p] = s.getPropertyValue(p)); return o; };
const R = (el) => { if(!el) return null; const b = el.getBoundingClientRect(); const sy=scrollY; return {x:+b.x.toFixed(2),y:+(b.y+sy).toFixed(2),w:+b.width.toFixed(2),h:+b.height.toFixed(2)}; };
const TXT=['font-family','font-size','font-weight','line-height','letter-spacing','color','font-variation-settings','font-optical-sizing','font-style','text-transform','text-align'];
const BOX=['display','padding-top','padding-bottom','padding-left','padding-right','margin-top','margin-bottom','max-width','width','height','min-height','gap','row-gap','column-gap','border-radius','border-width','border-top-width','border-color','box-shadow','background-color','background-image','background-size','background-position','background-repeat','opacity','mix-blend-mode','backdrop-filter','position','z-index','grid-template-columns','flex-direction','align-items','justify-content','overflow'];
const el = (sel, root=document) => root.querySelector(sel);
const all = (sel, root=document) => [...root.querySelectorAll(sel)];
const out = {url: location.pathname, vw: innerWidth, vh: innerHeight, docH: document.documentElement.scrollHeight};
out.body = cs(document.body, [...TXT,'background-color']);
out.html = cs(document.documentElement, ['font-size','line-height']);
// header
const nav = el('nav');
out.nav = {rect:R(nav), ...cs(nav, ['position','top','z-index','background-color','backdrop-filter','border-bottom-width','border-bottom-color','border-bottom-style','height'])};
const navInner = nav && nav.firstElementChild;
out.navInner = {rect:R(navInner), ...cs(navInner, ['max-width','padding-left','padding-right','height','width'])};
const logo = nav && nav.querySelector('img');
out.logo = {rect:R(logo), natural:{w:logo.naturalWidth,h:logo.naturalHeight}, src:logo.currentSrc, ...cs(logo,['height','width','object-fit'])};
const spans = nav.querySelectorAll('a span');
out.logoText = {rect:R(spans[0]), text: spans[0].textContent, ...cs(spans[0], TXT)};
out.logoSub = {rect:R(spans[1]), text: spans[1].textContent, ...cs(spans[1], TXT)};
const desktopNav = nav.querySelector('div.hidden');
out.desktopNav = desktopNav ? {rect:R(desktopNav), ...cs(desktopNav, ['display','gap','align-items'])} : null;
const navLinks = desktopNav ? all('a', desktopNav) : [];
out.navLinks = navLinks.map(a=>({t:a.textContent.trim(), rect:R(a), ...cs(a, TXT)}));
const contactWrap = desktopNav && desktopNav.querySelector(':scope > div');
out.contactWrap = contactWrap ? {rect:R(contactWrap), ...cs(contactWrap, ['margin-left','padding-left','border-left-width','border-left-color','gap'])} : null;
const donate = navLinks.find(a=>a.textContent.trim()==='Donate Now');
out.donate = donate ? {rect:R(donate), ...cs(donate, [...TXT,'background-color','padding-top','padding-bottom','padding-left','padding-right','border-radius','border-width','border-color','border-style','box-shadow','min-height','height','display','gap','transition-property','transition-duration'])} : null;
const burger = nav.querySelector('button');
out.burger = burger ? {rect:R(burger), ...cs(burger, ['display','padding','color','width','height']), svg: burger.querySelector('svg') ? {w:burger.querySelector('svg').getAttribute('width'), h:burger.querySelector('svg').getAttribute('height'), cls: burger.querySelector('svg').getAttribute('class')} : null} : null;
// main sections
const main = el('main');
const sections = all('section', main);
out.sections = sections.map((s,i)=>{const h=s.querySelector('h1,h2,h3'); return {i, rect:R(s), heading: h? h.textContent.trim().slice(0,60):null, cls: s.className, ...cs(s, ['padding-top','padding-bottom','padding-left','padding-right','margin-top','margin-bottom','background-color','min-height','height','border-top-width','border-bottom-width','border-top-color','position','overflow','text-align'])};});
// containers within sections
out.containers = sections.map((s,i)=>{const c=s.querySelector('.container'); return c?{i, rect:R(c), cls:c.className, ...cs(c, ['max-width','width','padding-left','padding-right','margin-left','margin-right'])}:null;});
// hero
const hero = sections[0];
out.hero = {rect:R(hero), cls:hero.className, ...cs(hero, ['height','min-height','display','align-items','justify-content','overflow','position','padding-top','padding-bottom'])};
const heroBg = hero.querySelector('div.absolute.inset-0');
out.heroBg = heroBg ? {rect:R(heroBg), cls: heroBg.className, ...cs(heroBg, ['background-image','background-size','background-position','background-repeat','z-index'])} : null;
out.heroOverlays = heroBg ? all(':scope > div', heroBg).map(d=>({cls:d.className, ...cs(d, ['background-color','background-image','mix-blend-mode','opacity','z-index'])})) : [];
const h1 = el('h1');
out.h1 = h1 ? {rect:R(h1), text:h1.textContent.trim(), cls:h1.className, ...cs(h1, [...TXT,'max-width','margin-bottom','animation-name','animation-duration','animation-delay','animation-fill-mode'])} : null;
const heroP = hero.querySelector('p');
out.heroP = heroP ? {rect:R(heroP), cls:heroP.className, ...cs(heroP, [...TXT,'max-width','margin-bottom'])} : null;
const heroBadge = hero.querySelector('div.inline-flex');
out.heroBadge = heroBadge ? {rect:R(heroBadge), cls:heroBadge.className, ...cs(heroBadge, [...TXT,'background-color','border-color','border-width','border-radius','padding-top','padding-bottom','padding-left','padding-right','backdrop-filter','gap','margin-bottom'])} : null;
const heroCtas = all('a', hero).filter(a=>/Support|Apply|Explore|Donate|Contact|Learn|Become|Get|Read|Meet|Start/i.test(a.textContent));
out.heroCtas = heroCtas.map(a=>({t:a.textContent.trim(), rect:R(a), cls:a.className, ...cs(a, [...TXT,'background-color','padding-top','padding-bottom','padding-left','padding-right','border-radius','border-width','border-color','border-style','box-shadow','min-height','height','display','gap','backdrop-filter'])}));
const ctaWrap = hero.querySelector('div.flex.flex-col');
out.heroCtaWrap = ctaWrap ? {rect:R(ctaWrap), cls: ctaWrap.className, ...cs(ctaWrap, ['flex-direction','gap','width'])} : null;
const heroContainer = hero.querySelector('.container');
out.heroContainer = heroContainer ? {rect:R(heroContainer), cls:heroContainer.className, ...cs(heroContainer, ['max-width','padding-left','padding-right'])} : null;
// h2s
out.h2s = all('h2', main).slice(0,6).map(h=>({text:h.textContent.trim().slice(0,50), rect:R(h), cls:h.className, ...cs(h, [...TXT,'margin-bottom','max-width'])}));
out.h3s = all('h3', main).slice(0,4).map(h=>({text:h.textContent.trim().slice(0,50), rect:R(h), cls:h.className, ...cs(h, [...TXT,'margin-bottom'])}));
// paragraphs sample
out.ps = all('p', main).slice(0,6).map(p=>({text:p.textContent.trim().slice(0,40), rect:R(p), cls:p.className, ...cs(p, [...TXT,'max-width','margin-bottom'])}));
// cards
const cards = all('.bg-card, .bg-muted.rounded-xl, .bg-muted.rounded-2xl, .bg-muted.rounded-3xl', main);
out.cards = cards.slice(0,6).map(c=>{const icon=c.querySelector('div.rounded-full, svg'); const iconWrap=c.querySelector('div.rounded-full'); return {cls:c.className, rect:R(c), ...cs(c, ['padding-top','padding-bottom','padding-left','padding-right','border-radius','border-width','border-color','box-shadow','background-color','transition-property','transition-duration','text-align','display','flex-direction','align-items']), iconWrap: iconWrap?{rect:R(iconWrap), ...cs(iconWrap,['width','height','background-color','color','border-radius','margin-bottom'])}:null, iconSvg: c.querySelector('svg')?{rect:R(c.querySelector('svg')), cls:c.querySelector('svg').getAttribute('class'), ...cs(c.querySelector('svg'),['width','height','color','opacity','stroke-width'])}:null, h3: c.querySelector('h3')?cs(c.querySelector('h3'),[...TXT,'margin-bottom']):null, p: c.querySelector('p')?cs(c.querySelector('p'),[...TXT,'margin-bottom']):null, link: c.querySelector('a')?{t:c.querySelector('a').textContent.trim(), cls:c.querySelector('a').className, ...cs(c.querySelector('a'),[...TXT,'gap','display'])}:null};});
const grids = all('.grid', main);
out.grids = grids.slice(0,5).map(g=>({cls:g.className, rect:R(g), ...cs(g, ['grid-template-columns','gap','row-gap','column-gap'])}));
// testimonial
const bq = el('blockquote', main);
if (bq) { const wrap = bq.closest('.bg-muted, .bg-card'); out.testimonial = {bq:{rect:R(bq), cls:bq.className, ...cs(bq, [...TXT,'margin-bottom'])}, wrap: wrap?{cls:wrap.className, rect:R(wrap), ...cs(wrap, ['background-color','border-radius','box-shadow','padding','display','flex-direction','overflow'])}:null, textCol: bq.parentElement?{cls:bq.parentElement.className, rect:R(bq.parentElement), ...cs(bq.parentElement, ['padding-top','padding-bottom','padding-left','padding-right','width'])}:null, imgCol: wrap&&wrap.firstElementChild?{cls:wrap.firstElementChild.className, rect:R(wrap.firstElementChild), ...cs(wrap.firstElementChild, ['background-image','background-size','background-position','height','width'])}:null, quoteSvg: bq.parentElement.querySelector('svg')?{...cs(bq.parentElement.querySelector('svg'),['width','height','color','opacity','margin-bottom'])}:null, author: bq.nextElementSibling?{html: bq.nextElementSibling.innerText.slice(0,80), name: cs(bq.nextElementSibling.querySelector('.font-bold'),[...TXT]), sub: cs(bq.nextElementSibling.querySelector('.text-sm'),[...TXT]), btn: bq.nextElementSibling.querySelector('a')?{rect:R(bq.nextElementSibling.querySelector('a')), cls:bq.nextElementSibling.querySelector('a').className, ...cs(bq.nextElementSibling.querySelector('a'),[...TXT,'background-color','padding-left','padding-right','padding-top','padding-bottom','min-height','border-radius','border-width','border-color'])}:null}:null}; }
// BarKode / other section CTA outline buttons
out.buttons = all('a[class*="inline-flex"], button', main).slice(0,12).map(b=>({t:b.textContent.trim().slice(0,30), cls:b.className, rect:R(b), ...cs(b, [...TXT,'background-color','padding-top','padding-bottom','padding-left','padding-right','border-radius','border-width','border-color','border-style','box-shadow','min-height','height','backdrop-filter'])}));
// badges
out.badges = all('.rounded-full.inline-flex, .inline-block.rounded, .rounded.inline-block', main).slice(0,4).map(b=>({t:b.textContent.trim().slice(0,40), cls:b.className, rect:R(b), ...cs(b, [...TXT,'background-color','border-color','border-width','border-radius','padding-top','padding-bottom','padding-left','padding-right'])}));
// divider bars
const bar = el('.w-24.h-1', main); out.dividerBar = bar? {rect:R(bar), ...cs(bar, ['width','height','background-color','border-radius','margin-bottom'])}:null;
// footer
const footer = el('footer');
out.footer = {rect:R(footer), ...cs(footer, ['background-color','color'])};
const fInner = footer.firstElementChild;
out.footerInner = {rect:R(fInner), cls:fInner.className, ...cs(fInner, ['max-width','padding-top','padding-bottom','padding-left','padding-right'])};
const fGrid = fInner.querySelector('.grid');
out.footerGrid = {rect:R(fGrid), cls:fGrid.className, ...cs(fGrid, ['grid-template-columns','gap'])};
out.footerCols = all(':scope > div', fGrid).map(d=>({rect:R(d), cls:d.className}));
const fLogo = footer.querySelector('img');
out.footerLogo = {rect:R(fLogo), ...cs(fLogo, ['height','width'])};
out.footerH3 = cs(footer.querySelector('h3'), [...TXT,'margin-bottom']);
const fLink = footer.querySelector('ul a');
out.footerLink = {rect:R(fLink), cls:fLink.className, ...cs(fLink, [...TXT,'text-decoration-line','transition-property','transition-duration'])};
out.footerUl = cs(footer.querySelector('ul'), [...TXT]);
out.footerUlSpace = footer.querySelectorAll('ul li')[1] ? cs(footer.querySelectorAll('ul li')[1], ['margin-top']) : null;
out.footerP = cs(footer.querySelector('p'), [...TXT,'max-width']);
out.footerTagline = cs(footer.querySelector('p.font-serif'), [...TXT]);
const fBottom = fInner.lastElementChild;
out.footerBottom = {rect:R(fBottom), cls:fBottom.className, ...cs(fBottom, [...TXT,'margin-top','padding-top','border-top-width','border-top-color','flex-direction','gap','justify-content'])};
// forms
const input = el('input', main); const ta = el('textarea', main); const sel = el('select', main); const label = el('label', main);
if (input) out.input = {rect:R(input), cls:input.className, ...cs(input, [...TXT,'height','background-color','border-width','border-color','border-radius','padding-left','padding-right','padding-top','padding-bottom','box-shadow','outline'])};
if (ta) out.textarea = {rect:R(ta), cls:ta.className, ...cs(ta, [...TXT,'min-height','background-color','border-width','border-color','border-radius','padding-left','padding-right','padding-top','padding-bottom','resize'])};
if (sel) out.select = {rect:R(sel), cls:sel.className, ...cs(sel, [...TXT,'height','background-color','border-width','border-color','border-radius','padding-left','padding-right'])};
if (label) out.label = {rect:R(label), cls:label.className, ...cs(label, [...TXT,'margin-bottom','display'])};
const submit = el('button[type=submit]', main); if (submit) out.submit = {rect:R(submit), cls:submit.className, ...cs(submit, [...TXT,'height','min-height','background-color','border-radius','border-width','border-color','padding-left','padding-right','width'])};
const form = el('form', main); if (form) out.form = {rect:R(form), cls: form.className, fields: all('input,select,textarea', form).map(f=>({id:f.id,type:f.type,required:f.required,placeholder:f.placeholder}))};
// fonts loaded
out.fonts = [...document.fonts].filter(f=>f.status==='loaded').map(f=>`${f.family} ${f.style} ${f.weight} ${f.stretch}`);
// mobile menu element
out.mobileMenu = nav.querySelector('div.lg\\:hidden.absolute') ? 'open' : 'closed';
return out;
})()
