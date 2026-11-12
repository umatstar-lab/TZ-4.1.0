// Local Joomla 5.4.10 fixture. Resize one initialized book without reloading it.
const assert=require('node:assert/strict');const fs=require('node:fs');
const {chromium}=require('/opt/codex/cua_node/lib/node_modules/playwright-core');
(async()=>{const browser=await chromium.launch({executablePath:'/usr/bin/chromium',headless:true,args:['--no-sandbox']});try{
 for(const source of ['minified','readable']){
  const page=await browser.newPage({viewport:{width:1651,height:1100}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route(/^https:\/\//,r=>r.abort());
  if(source==='readable')await page.route('**/jquery.booklet.latest.min.js*',r=>r.fulfill({contentType:'application/javascript',body:fs.readFileSync(process.argv[2],'utf8')}));
  await page.goto('http://127.0.0.1:8084/index.php?option=com_tz_portfolio&view=article&id=6&Itemid=405');
  await page.locator('.tz-flipbook-initialized.booklet').waitFor();
  await page.locator('.tz_portfolio_flipbook_gallery').evaluate(e=>jQuery(e).on('bookletchange.resizeTest',(event,data)=>{e.dataset.completeIndex=String(data.index)}));
  await page.locator('.b-arrow-next').click();await page.waitForFunction(()=>document.querySelector('.tz_portfolio_flipbook_gallery').dataset.completeIndex==='2');
  for(const width of [1651,1280,1000,769,768,767,600,390,320,1651]){
   await page.setViewportSize({width,height:1100});await page.waitForTimeout(150);
   const m=await page.locator('.tz_portfolio_flipbook_gallery').evaluate(e=>{
    const b=e.getBoundingClientRect(),shell=e.parentElement.getBoundingClientRect(),right=e.querySelector('.b-arrow-next').getBoundingClientRect(),left=e.querySelector('.b-arrow-prev').getBoundingClientRect();
    const pages=[...e.querySelectorAll('.b-page.b-p1,.b-page.b-p2')].map(p=>p.getBoundingClientRect());
    return {bookWidth:b.width,internalWidth:jQuery(e).booklet('option','width'),ratio:b.height/b.width,bookLeft:b.left,bookRight:b.right,rightLeft:right.left,rightRight:right.right,leftLeft:left.left,leftRight:left.right,shellLeft:shell.left,shellRight:shell.right,pagesRight:Math.max(...pages.map(p=>p.right)),pageTotal:e.querySelectorAll('.gallery-image img').length,instances:document.querySelectorAll('.tz-flipbook-gallery-wrap').length,index:jQuery(e).booklet('option','currentIndex'),overflow:document.documentElement.scrollWidth-innerWidth};
   });
   assert(Math.abs(m.bookWidth-m.internalWidth)<1,JSON.stringify(m));
   assert(m.pagesRight<=m.bookRight+1,JSON.stringify(m));
   assert(m.rightLeft>=m.bookRight-1,JSON.stringify(m));assert(m.leftRight<=m.bookLeft+1,JSON.stringify(m));
   assert(m.leftLeft>=m.shellLeft-1 && m.rightRight<=m.shellRight+1,JSON.stringify(m));
   assert(Math.abs(m.ratio-0.6)<0.01,JSON.stringify(m));assert.equal(m.pageTotal,21);assert.equal(m.instances,1);assert.equal(m.index,2);assert(m.overflow<=1,JSON.stringify(m));
   console.log(`PASS ${source} resize ${width}px: pages fit book, arrows outside, ratio and current spread retained`);
  }
  await page.locator('.tz_portfolio_flipbook_gallery').evaluate(e=>jQuery(e).on('bookletchange.resizeTest',(event,data)=>{e.dataset.completeIndex=String(data.index)}));
  await page.locator('.b-arrow-next').click();await page.waitForFunction(()=>document.querySelector('.tz_portfolio_flipbook_gallery').dataset.completeIndex==='4');assert.equal(await page.locator('.tz_portfolio_flipbook_gallery').evaluate(e=>jQuery(e).booklet('option','currentIndex')),4);
  await page.locator('.b-arrow-prev').click();await page.waitForFunction(()=>document.querySelector('.tz_portfolio_flipbook_gallery').dataset.completeIndex==='2');assert.equal(await page.locator('.tz_portfolio_flipbook_gallery').evaluate(e=>jQuery(e).booklet('option','currentIndex')),2);
  assert.deepEqual(errors,[]);await page.close();
 }
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
