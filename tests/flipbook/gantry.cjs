// Public read-only browser check. Proposed assets are replaced ONLY in this browser.
// node gantry.cjs /path/to/addon https://site.example /path/to/article [/another/article]
const fs=require('node:fs');const path=require('node:path');const assert=require('node:assert/strict');
const {chromium}=require('/opt/codex/cua_node/lib/node_modules/playwright-core');
(async()=>{const root=process.argv[2],origin=process.argv[3],urls=process.argv.slice(4);
 const b=await chromium.launch({executablePath:'/usr/bin/chromium',headless:true,args:['--no-sandbox'],proxy:{server:process.env.HTTPS_PROXY||process.env.HTTP_PROXY}});
 try{for(const url of urls){
  const p=await b.newPage({viewport:{width:1657,height:1100}});const baseline=await b.newPage({viewport:{width:1657,height:1100}});const errors=[];p.on('pageerror',e=>errors.push(e.message));
  await p.route('**/flipbook_gallery/css/style.css*',r=>r.fulfill({contentType:'text/css',body:fs.readFileSync(path.join(root,'css/style.css'),'utf8')}));
  await p.addInitScript({content:"document.addEventListener('DOMContentLoaded',function(){"+fs.readFileSync(path.join(root,'js/layout.js'),'utf8')+"});"});
  await baseline.goto(new URL(url,origin).href,{waitUntil:'domcontentloaded',timeout:45000});
  const response=await p.goto(new URL(url,origin).href,{waitUntil:'domcontentloaded',timeout:45000});assert.equal(response.status(),200);
  await p.locator('.tz-flipbook-initialized.booklet').waitFor();await p.waitForTimeout(1000);
  for(const width of [1657,1200,1000,850,768,600,390,320,1657]){
   await baseline.setViewportSize({width,height:1100});await p.setViewportSize({width,height:1100});await p.waitForTimeout(300);
   const m=await p.locator('.tz_portfolio_flipbook_gallery').evaluate(e=>{
    const b=e.getBoundingClientRect(),title=document.querySelector('.tp-item-title').getBoundingClientRect(),menu=document.querySelector('aside .ba__menu-main>li:first-child').getBoundingClientRect(),arrow=e.querySelector('.b-arrow-next').getBoundingClientRect(),counter=document.querySelector('.flipbook-gallery-menu .b-current').getBoundingClientRect();
    return {bookWidth:b.width,internal:jQuery(e).booklet('option','width'),gap:b.top-title.bottom,heading:title.top,menu:menu.top,menuVisible:!!menu.width,arrowRight:arrow.right,viewport:document.documentElement.clientWidth,counterRight:counter.right,bookRight:b.right,scrollWidth:document.documentElement.scrollWidth};
   });
   const spacing=()=>['.tp-item-title','#tp-portfolio-template-body','#g-mainbar>.g-grid>.g-block'].map(s=>{const e=document.querySelector(s),c=getComputedStyle(e);return [s,c.marginTop,c.marginBottom,c.marginLeft,c.marginRight,c.paddingTop,c.paddingBottom,c.paddingLeft,c.paddingRight]});
   assert.deepEqual(await p.evaluate(spacing),await baseline.evaluate(spacing),'Template title/body spacing must be unchanged');
assert(m.arrowRight<=m.viewport,JSON.stringify(m));
   assert(Math.abs(m.counterRight-m.bookRight)<1,JSON.stringify(m));assert(Math.abs(m.bookWidth-m.internal)<1,JSON.stringify(m));assert(m.scrollWidth<=m.viewport+1,JSON.stringify(m));
   console.log(`PASS ${url} ${width}px: template spacing preserved, book and arrow fit, counter aligned`);
  }
  assert.deepEqual(errors,[]);await p.close();await baseline.close();
 }}finally{await b.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
