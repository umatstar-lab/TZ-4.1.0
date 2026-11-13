// Run on the isolated fixtures prepared for engine.cjs and ui.cjs.
const {open,api}=require('./helpers.cjs');const assert=require('node:assert/strict');
async function call(page,action,data={}){const r=await api(page,action,data);assert.equal(r.status,200,JSON.stringify(r));assert(r.body.success,JSON.stringify(r));return r.body.data;}
(async()=>{const {browser,page,errors}=await open();const created=[];try{
 for(const kind of ['image','caption']){
  let job=await call(page,'start',{source:'flipbook_gallery',target:'gallery',mode:'copy',activate:false,articles:[91008]});created.push(job.id);
  job=await call(page,'step',{job:job.id});
  if(kind==='caption'){job=await call(page,'resolve',{job:job.id,conflict:job.conflict.id,choice:'keep',all:false});job=await call(page,'step',{job:job.id});}
  assert.equal(job.conflict.kind,kind);const processed=job.processed;
  await page.reload();await page.locator(`#tzmt-history tr[data-job="${job.id}"] button`).first().click();await page.locator('#tzmt-conflict[open]').waitFor();
  // Cancel is a non-submit action: no conflict choice is required.
  await page.locator('#tzmt-conflict-cancel').click();await page.waitForFunction(()=>!document.getElementById('tzmt-conflict').open);
  job=await call(page,'status',{job:job.id});assert.equal(job.status,'cancelled');assert.equal(job.conflict,null);assert.equal(job.processed,processed);
  job=await call(page,'step',{job:job.id});assert.equal(job.status,'cancelled');assert.equal(job.processed,processed);
  assert(await page.locator('#tzmt-resume').isHidden());assert(await page.locator('#tzmt-undo').isVisible());
  await page.reload();job=await call(page,'status',{job:job.id});assert.equal(job.status,'cancelled');
  console.log('PASS cancel '+kind+' conflict: modal closed, server stopped, no further processing, state survives reload, undo available');
 }
 assert.deepEqual(errors,[]);
 }finally{for(const id of created)await call(page,'cancel',{job:id}).then(()=>call(page,'purge',{job:id}));await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1});
