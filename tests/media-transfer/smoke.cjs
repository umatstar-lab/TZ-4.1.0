const {open,api}=require('./helpers.cjs');const assert=require('node:assert/strict');
(async()=>{const {browser,page,errors}=await open();try{
 await page.waitForFunction(()=>!document.getElementById('tzmt-source').disabled);
 console.log('Page title',await page.title());console.log('UI message',await page.locator('#tzmt-message').innerText());
 const result=await api(page,'inventory');console.log('Inventory',JSON.stringify(result.body.data?.adapters.map(a=>({key:a.key,images:a.images,captions:a.captions}))));assert.equal(result.status,200);assert(result.body.success);
 await page.screenshot({path:'/workspace/media-transfer-smoke.png',fullPage:true});assert.deepEqual(errors,[]);
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1});
