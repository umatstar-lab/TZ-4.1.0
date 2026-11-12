// Test on an isolated Joomla 5.4.10 fixture with original flipbook data.
const fs=require('node:fs');const assert=require('node:assert/strict');
const {chromium}=require('/opt/codex/cua_node/lib/node_modules/playwright-core');
(async()=>{
 const expected=JSON.parse(fs.readFileSync(process.argv[2],'utf8'));
 const browser=await chromium.launch({executablePath:'/usr/bin/chromium',headless:true,args:['--no-sandbox']});
 try{
  const page=await browser.newPage({viewport:{width:1500,height:1000}});const errors=[],failed=[];
  page.on('pageerror',e=>{errors.push(e.message);console.error('Page error:',e.stack);});page.on('response',r=>{if(r.url().startsWith('http://127.0.0.1:8084/')&&r.status()>=400)failed.push([r.status(),r.url()]);});
  await page.route(/^https:\/\//,r=>r.abort());
  for(const item of expected){
   const res=await page.goto(`http://127.0.0.1:8084/index.php?option=com_tz_portfolio&view=article&id=${item.id}&Itemid=${item.menu}`);
   assert.equal(res.status(),200);await page.locator('.tz-flipbook-initialized.booklet').waitFor();
   assert.equal(await page.locator('.tz-flipbook-gallery-wrap').count(),1);
   const images=page.locator('.tz_portfolio_flipbook_gallery .gallery-image img');
   assert.equal(await images.count(),item.images.length);
   assert.deepEqual(await images.evaluateAll(es=>es.map(e=>decodeURIComponent(new URL(e.src).pathname.split('/').at(-1)))),item.images);
   assert.deepEqual(await images.evaluateAll(es=>es.map(e=>e.getAttribute('alt'))),item.captions);
   await images.evaluateAll(es=>Promise.all(es.map(e=>e.decode())));
   const before=await page.locator('.tz_portfolio_flipbook_gallery').evaluate(e=>jQuery(e).booklet('option','currentIndex'));
   await page.locator('.b-arrow-next').click();await page.waitForTimeout(1200);
   const after=await page.locator('.tz_portfolio_flipbook_gallery').evaluate(e=>jQuery(e).booklet('option','currentIndex'));
   assert.notEqual(after,before);
   console.log(`PASS flipbook ${item.id}: ${item.images.length} images/captions in order, exactly one book, next arrow`);
  }
  assert.deepEqual(errors,[]);assert.deepEqual(failed,[]);console.log('PASS Joomla 5.4.10 / Portfolio 4.1.2 flipbooks, resources and JS');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
