import puppeteer from '/Users/carleykuehner/.npm/_npx/3d99b142a90de122/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
import fs from 'node:fs';
const SP='/private/tmp/claude-501/-Users-carleykuehner-www-heartland-k9s-for-veterans/e00e485e-27ec-47ae-8b39-e0fa2a5559ea/scratchpad';
const CH='/Users/carleykuehner/.cache/puppeteer/chrome-headless-shell/mac_arm-145.0.7632.77/chrome-headless-shell-mac-arm64/chrome-headless-shell';
const BASE='https://heartland-canines-for-veterans.replit.app';
const browser=await puppeteer.launch({executablePath:CH,headless:true,args:['--no-sandbox','--disable-gpu','--hide-scrollbars'],userDataDir:SP+'/chrome-profile2'});
const page=await browser.newPage();
const out={};
const cs=(sel,props)=>page.evaluate((sel,props)=>{const el=document.querySelector(sel);if(!el)return null;const s=getComputedStyle(el);const o={};props.forEach(p=>o[p]=s.getPropertyValue(p));const b=el.getBoundingClientRect();o.rect={x:b.x,y:b.y+scrollY,w:b.width,h:b.height};return o;},sel,props);
// ---- mobile menu at 390
await page.setViewport({width:390,height:844,deviceScaleFactor:1});
await page.goto(BASE+'/',{waitUntil:'networkidle0'});
await page.evaluate(()=>document.fonts.ready); await new Promise(r=>setTimeout(r,500));
out.burgerClosed=await cs('nav button',['display','padding','width','height','color']);
out.burgerIconClosed=await page.evaluate(()=>document.querySelector('nav button svg').getAttribute('class'));
await page.click('nav button'); await new Promise(r=>setTimeout(r,500));
out.burgerIconOpen=await page.evaluate(()=>document.querySelector('nav button svg').getAttribute('class'));
out.mobileMenu=await cs('nav > div.absolute',['position','top','left','width','background-color','border-bottom-width','border-bottom-color','box-shadow','z-index','animation-name','animation-duration','padding']);
out.mobileMenuInner=await cs('nav > div.absolute > div',['padding-top','padding-bottom','padding-left','padding-right','gap','flex-direction']);
out.mobileMenuLinks=await page.evaluate(()=>[...document.querySelectorAll('nav > div.absolute a')].map(a=>{const s=getComputedStyle(a);const b=a.getBoundingClientRect();return {t:a.textContent.trim(),cls:a.className,fs:s.fontSize,fw:s.fontWeight,lh:s.lineHeight,color:s.color,bg:s.backgroundColor,pad:s.padding,radius:s.borderRadius,rect:{x:b.x,y:b.y,w:b.width,h:b.height}};}));
out.mobileDonateWrap=await cs('nav > div.absolute div.border-t',['padding-top','margin-top','border-top-width','border-top-color','gap']);
out.bodyScrollLockWhenOpen=await page.evaluate(()=>getComputedStyle(document.body).overflow);
await page.screenshot({path:SP+'/ref/screenshots/home-390-menu-open.png'});
// close via burger
await page.click('nav button'); await new Promise(r=>setTimeout(r,300));
out.menuAfterSecondClick=await page.evaluate(()=>!!document.querySelector('nav > div.absolute'));
// open and click a link -> closes?
await page.click('nav button'); await new Promise(r=>setTimeout(r,300));
await page.evaluate(()=>{[...document.querySelectorAll('nav > div.absolute a')].find(a=>a.textContent.trim()==='About').click();});
await new Promise(r=>setTimeout(r,500));
out.afterLinkClick={url:await page.evaluate(()=>location.pathname), menuOpen:await page.evaluate(()=>!!document.querySelector('nav > div.absolute')), scrollY: await page.evaluate(()=>scrollY)};
// active link color on /about desktop
await page.setViewport({width:1440,height:900,deviceScaleFactor:1});
await page.goto(BASE+'/about',{waitUntil:'networkidle0'}); await new Promise(r=>setTimeout(r,500));
out.activeNavLink=await page.evaluate(()=>{const a=[...document.querySelectorAll('nav div.hidden a')].find(a=>a.textContent.trim()==='About');const s=getComputedStyle(a);return {cls:a.className,color:s.color};});
out.inactiveNavLink=await page.evaluate(()=>{const a=[...document.querySelectorAll('nav div.hidden a')].find(a=>a.textContent.trim()==='Program');const s=getComputedStyle(a);return {cls:a.className,color:s.color};});
// hover states on home desktop
await page.goto(BASE+'/',{waitUntil:'networkidle0'}); await new Promise(r=>setTimeout(r,500));
const hov=async(sel,props,find)=>{const h=await page.evaluateHandle((sel,find)=>{let els=[...document.querySelectorAll(sel)];if(find)els=els.filter(e=>e.textContent.trim().startsWith(find));return els[0];},sel,find);const el=h.asElement();if(!el)return null;await el.scrollIntoView(); const before=await el.evaluate((e,props)=>{const s=getComputedStyle(e);const o={};props.forEach(p=>o[p]=s.getPropertyValue(p));return o;},props);await el.hover();await new Promise(r=>setTimeout(r,450));const after=await el.evaluate((e,props)=>{const s=getComputedStyle(e);const o={};props.forEach(p=>o[p]=s.getPropertyValue(p));return o;},props);await page.mouse.move(0,0);await new Promise(r=>setTimeout(r,400));return {before,after};};
out.hover_navLink=await hov('nav div.hidden a',['color'],'Program');
out.hover_donate=await hov('nav div.hidden a',['background-color','color','box-shadow','border-color','transform','filter'],'Donate');
out.hover_logoImg=await hov('nav img',['transform','scale']);
out.hover_heroPrimary=await hov('main section a',['background-color','color','transform','box-shadow'],'Support a Service Dog');
out.hover_heroOutline=await hov('main section a',['background-color','color','transform','box-shadow'],'Apply for a Dog');
out.hover_card=await hov('main .card-hover-effect',['translate','transform','box-shadow']);
out.hover_cardLink=await hov('main .card-hover-effect a',['gap','color']);
out.hover_ghost=await hov('main a',['background-color','color'],'Read More Stories');
out.hover_footerLink=await hov('footer ul a',['color']);
// focus ring on contact inputs
await page.goto(BASE+'/contact',{waitUntil:'networkidle0'}); await new Promise(r=>setTimeout(r,500));
await page.focus('#firstName'); await new Promise(r=>setTimeout(r,200));
out.inputFocus=await cs('#firstName',['outline','outline-style','box-shadow','border-color']);
out.inputPlaceholderColor=await page.evaluate(()=>getComputedStyle(document.querySelector('#firstName'),'::placeholder').color);
await page.focus('#subject'); await new Promise(r=>setTimeout(r,200));
out.selectFocus=await cs('#subject',['outline','box-shadow','border-color']);
// form validation: check validity without submitting
out.formValidity=await page.evaluate(()=>{const f=document.querySelector('form');return {checkValidity:f.checkValidity(), noValidate:f.noValidate, action:f.action, method:f.method, fields:[...f.elements].filter(e=>e.name!==undefined&&e.tagName!=='BUTTON').map(e=>({id:e.id,name:e.name,required:e.required,type:e.type,valid:e.validity.valid}))};});
// toast/toaster element presence
out.toasterPresent=await page.evaluate(()=>!!document.querySelector('[role="region"][aria-label*="Notifications"], ol[data-radix-collection-item], [data-sonner-toaster], .toast, ol.fixed'));
out.toastViewportCls=await page.evaluate(()=>{const ol=document.querySelector('ol');return ol?ol.className:null;});
// scroll-to-top on route change
await page.evaluate(()=>scrollTo(0,800)); await new Promise(r=>setTimeout(r,200));
await page.evaluate(()=>{[...document.querySelectorAll('nav div.hidden a')].find(a=>a.textContent.trim()==='Stories').click();}); await new Promise(r=>setTimeout(r,500));
out.scrollAfterRouteChange={path:await page.evaluate(()=>location.pathname), scrollY:await page.evaluate(()=>scrollY)};
// 404 page
await page.goto(BASE+'/does-not-exist',{waitUntil:'networkidle0'}); await new Promise(r=>setTimeout(r,500));
out.notFound=await page.evaluate(()=>({title:document.title, text:document.querySelector('main')?.innerText.slice(0,300), status: null}));
// animation classes defs
out.animateInDef=await page.evaluate(()=>{const found=[];for(const ss of document.styleSheets){try{for(const r of ss.cssRules){if(r.selectorText&&/animate-in|slide-in-from-bottom|fill-mode-both|delay-150|delay-300|delay-500|duration-700|hover-elevate|active-elevate/.test(r.selectorText))found.push(r.cssText.slice(0,300));}}catch(e){}}return found;});
fs.writeFileSync(SP+'/measure/interact.json',JSON.stringify(out,null,1));
await browser.close();
console.error('DONE');
