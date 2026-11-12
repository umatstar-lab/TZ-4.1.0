// Usage: node gallery-pages.cjs private-expectations.json
// Expectations: [{id, menu, images: [...], captions: [...]}]. Do not commit site data.
const fs = require('node:fs');
const assert = require('node:assert/strict');
const {chromium} = require('/opt/codex/cua_node/lib/node_modules/playwright-core');
(async () => {
  const expected = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
  const browser = await chromium.launch({executablePath:'/usr/bin/chromium',headless:true,args:['--no-sandbox']});
  try {
    const page = await browser.newPage({viewport:{width:1500,height:1000}});
    const failures = [], errors = [];
    page.on('response', r => {if(r.url().startsWith('http://127.0.0.1:8084/') && r.status()>=400) failures.push([r.status(),r.url()]);});
    page.on('pageerror', e => errors.push(e.message));
    // The gallery must work without external UIkit/CDN resources.
    await page.route(/^https:\/\//, route => route.abort());
    for (const item of expected) {
      const response = await page.goto(`http://127.0.0.1:8084/index.php?option=com_tz_portfolio&view=article&id=${item.id}&Itemid=${item.menu}`);
      assert.equal(response.status(),200);
      assert.equal(await page.locator('.container-header').count(),1);
      assert.equal(await page.locator('.gallery-listing').count(), item.images.length);
      assert.deepEqual((await page.locator('.image-caption').allTextContents()).map(s=>s.trim()),item.captions.map(s=>s.trim()));
      const names=await page.locator('.gallery-listing .gallery-image > img').evaluateAll(es=>es.map(e=>decodeURIComponent(new URL(e.src).pathname.split('/').at(-1))));
      assert.deepEqual(names,item.images);
      await page.locator('.gallery-listing img').evaluateAll(es=>Promise.all(es.map(e=>e.decode())));
      const columns=await page.locator('.gallery-listing').evaluateAll(es=>new Set(es.map(e=>Math.round(e.getBoundingClientRect().x))).size);
      assert(columns>=2);
      assert(await page.locator('.gallery-title .visually-hidden').first().evaluate(e=>e.getBoundingClientRect().width<=1));
      await page.locator('.gallery-title').first().click();
      await page.locator('.fancybox-container').waitFor();
      assert.equal(await page.locator('.fancybox-caption').innerText(),item.captions[0].trim().replace(/\s+/g,' '));
      await page.locator('[data-fancybox-close]').click();
      console.log(`PASS article ${item.id}: template, ${item.images.length} images/captions in order, masonry and lightbox`);
    }
    await page.setViewportSize({width:390,height:850});
    assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
    assert.deepEqual(failures,[]); assert.deepEqual(errors,[]);
    console.log('PASS Joomla 5.4.10: mobile width, resources and JavaScript');
  } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
