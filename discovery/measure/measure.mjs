import puppeteer from '/Users/carleykuehner/.npm/_npx/3d99b142a90de122/node_modules/puppeteer-core/lib/esm/puppeteer/puppeteer-core.js';
import fs from 'node:fs';
const SP='/private/tmp/claude-501/-Users-carleykuehner-www-heartland-k9s-for-veterans/e00e485e-27ec-47ae-8b39-e0fa2a5559ea/scratchpad';
const CH='/Users/carleykuehner/.cache/puppeteer/chrome-headless-shell/mac_arm-145.0.7632.77/chrome-headless-shell-mac-arm64/chrome-headless-shell';
const BASE='https://heartland-canines-for-veterans.replit.app';
const routes=['/','/about','/program','/veterans','/get-involved','/barkode','/stories','/contact'];
const names={'/':'home','/about':'about','/program':'program','/veterans':'veterans','/get-involved':'get-involved','/barkode':'barkode','/stories':'stories','/contact':'contact'};
const browser=await puppeteer.launch({executablePath:CH,headless:true,args:['--no-sandbox','--disable-gpu','--hide-scrollbars'],userDataDir:SP+'/chrome-profile2'});
const page=await browser.newPage();
const reqs=new Set();
const reqList=[];
page.on('response',async res=>{const r=res.request();const u=r.url();const key=u;if(!reqs.has(key)){reqs.add(key);let len=null;try{len=res.headers()['content-length']||null;}catch{}reqList.push({url:u,type:r.resourceType(),status:res.status(),contentType:res.headers()['content-type']||'',contentLength:len,route:page.__route,vw:page.__vw});}});
const measureFn = fs.readFileSync(SP+'/measure/page-measure.js','utf8');
const results={};
const shots=[];
for (const vw of [1440,390]) {
  for (const route of routes) {
    page.__route=route; page.__vw=vw;
    await page.setViewport({width:vw,height: vw===390?844:900,deviceScaleFactor:1});
    await page.goto(BASE+route,{waitUntil:'networkidle0',timeout:60000});
    await page.evaluate(()=>document.fonts.ready);
    await new Promise(r=>setTimeout(r,1200));
    // scroll through to trigger any lazy things then back to top
    await page.evaluate(async()=>{const h=document.documentElement.scrollHeight;for(let y=0;y<h;y+=600){scrollTo(0,y);await new Promise(r=>setTimeout(r,40));}scrollTo(0,0);await new Promise(r=>setTimeout(r,300));});
    const n=names[route];
    const f1=`${SP}/ref/screenshots/${n}-${vw}.png`;
    await page.screenshot({path:f1,fullPage:true,captureBeyondViewport:true});
    const f2=`${SP}/ref/screenshots/${n}-${vw}-viewport.png`;
    await page.screenshot({path:f2,fullPage:false});
    shots.push(f1,f2);
    const m=await page.evaluate(measureFn);
    results[`${n}@${vw}`]=m;
    console.error('done',n,vw);
  }
}
// breakpoint tests on home at several widths (measure only)
for (const vw of [1024,1023,768,767,640,639]) {
  page.__route='/'; page.__vw=vw;
  await page.setViewport({width:vw,height:900,deviceScaleFactor:1});
  await page.goto(BASE+'/',{waitUntil:'networkidle0',timeout:60000});
  await page.evaluate(()=>document.fonts.ready);
  await new Promise(r=>setTimeout(r,800));
  results[`home@${vw}`]=await page.evaluate(measureFn);
  if (vw===1024||vw===1023||vw===768){ const f=`${SP}/ref/screenshots/home-${vw}-viewport.png`; await page.screenshot({path:f}); shots.push(f);}
  console.error('done home',vw);
}
fs.writeFileSync(SP+'/measure/results.json',JSON.stringify(results,null,1));
fs.writeFileSync(SP+'/measure/requests.json',JSON.stringify(reqList,null,1));
fs.writeFileSync(SP+'/measure/shots.json',JSON.stringify(shots,null,1));
await browser.close();
console.error('ALL DONE');
