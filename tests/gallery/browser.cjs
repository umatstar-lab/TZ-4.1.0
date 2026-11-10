const { chromium } = require('/opt/codex/cua_node/lib/node_modules/playwright-core');
const assert = require('node:assert/strict');
(async()=>{
 const browser=await chromium.launch({executablePath:'/usr/bin/chromium',headless:true,args:['--no-sandbox']});
 try {
 const page=await browser.newPage({viewport:{width:1800,height:1100}});const errors=[];
 page.on('pageerror',e=>errors.push(e.message));
 for(const fmt of ['json','object','array','root-array','root-json']){
  await page.goto('http://127.0.0.1:8765/'+fmt+'.html');
  const dimensions=await page.locator('.gallery-listing').evaluateAll(es=>es.map(e=>{let r=e.getBoundingClientRect();return {x:r.x,y:r.y,w:r.width}}));
  assert.equal(dimensions.length,8);assert.equal(new Set(dimensions.map(e=>Math.round(e.x))).size,4);
  assert(await page.locator('.image-caption.under_image').first().isVisible());
  assert.equal(await page.evaluate(()=>typeof UIkit),'undefined');
  const image=await page.locator('.gallery-image img').first().boundingBox();
  const caption=await page.locator('.image-caption').first().boundingBox();assert(caption.y>=image.y+image.height);
  assert(await page.locator('.gallery-title .visually-hidden').first().evaluate(e=>e.getBoundingClientRect().width<=1));
  console.log('PASS',fmt,'four independent masonry columns and captions below images');
 }
 await page.goto('http://127.0.0.1:8765/json.html');await page.screenshot({path:'/workspace/gallery-fix/gallery-desktop.png',fullPage:true});
 await page.locator('.gallery-title').first().click();await page.locator('.fancybox-container').waitFor();
 assert.equal(await page.locator('.fancybox-caption').innerText(),'Підпис фотографії 1');
 await page.locator('[data-fancybox-close]').click();console.log('PASS lightbox opens with matching caption and closes');
 await page.setViewportSize({width:390,height:850});
 assert(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth));
 assert.equal(await page.locator('.gallery-listing').evaluateAll(es=>new Set(es.map(e=>Math.round(e.getBoundingClientRect().x))).size),1);
 await page.screenshot({path:'/workspace/gallery-fix/gallery-mobile.png',fullPage:true});console.log('PASS one mobile column without horizontal overflow');
 for(const type of ['grid','horizontal_masonry','carousel']){
  await page.goto('http://127.0.0.1:8765/'+type+'.html');
  assert.equal(await page.locator('.gallery-listing').evaluateAll(es=>es.filter(e=>!e.closest('.slick-cloned')).length),8);
  if(type==='carousel')assert(await page.locator('.slick-initialized').count());
  console.log('PASS alternative layout:',type);
 }
 await page.goto('http://127.0.0.1:8765/invalid.html');assert.equal(await page.locator('.gallery-listing').count(),0);
 assert.deepEqual(errors,[]);console.log('PASS invalid data renders no gallery; no JavaScript errors');
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
