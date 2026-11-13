const {open,api}=require('./helpers.cjs');const assert=require('node:assert/strict');const {execFileSync}=require('node:child_process');
const fixture=(action,user=false)=>execFileSync('docker',['exec',...user?['--user','www-data']:[],'migration273-web-1','php','/tmp/tzmt-grid-fixture.php',action],{encoding:'utf8'});
const snapshot=()=>JSON.parse(fixture('snapshot'));
async function call(p,a,d={}){const r=await api(p,a,d);assert.equal(r.status,200,JSON.stringify(r));assert(r.body.success,JSON.stringify(r));return r.body.data;}
(async()=>{fixture('prepare',true);const {browser,page,errors}=await open();let job;try{
const before=snapshot(),source=JSON.parse(before.article.media).grid_gallery;
await page.locator('#tzmt-source:not(:disabled)').waitFor();assert.equal(await page.locator('#tzmt-source option[value=grid_gallery]').count(),1);assert.equal(await page.locator('#tzmt-target option[value=grid_gallery]').count(),0);
const row=page.locator('#tzmt-materials tr').filter({has:page.locator('input[value="91009"]')});assert.deepEqual((await row.locator('td').allTextContents()).slice(2),['—','—','—','—','—','—']);assert.equal(await page.locator('th[scope=colgroup]').count(),3);
await page.locator('#tzmt-source').selectOption('grid_gallery');await page.locator('#tzmt-target').selectOption('gallery');await page.locator('#tzmt-scan').click();await page.waitForFunction(()=>document.getElementById('tzmt-message').textContent.includes('Поиск завершён'));
assert.deepEqual((await row.locator('td').allTextContents()).slice(2),['1','1','0','0','3','3']);assert.match(await page.locator('#tzmt-source-summary').innerText(),/3 изображений, 3 подписей/);console.log('PASS Grid source without modern provider, three table groups, dashes before scan, real counts across all three roots');
const rejected=await api(page,'start',{source:'flipbook_gallery',target:'grid_gallery',mode:'copy',articles:[91009]});assert.equal(rejected.status,400);assert.deepEqual(snapshot(),before);
for(const target of ['gallery','flipbook_gallery'])for(const mode of ['copy','move']){
 job=await call(page,'start',{source:'grid_gallery',target,mode,activate:true,articles:[91009]});assert.equal(job.total,3);
 for(let n=0;n<30&&['running','conflict'].includes(job.status);n++)job=job.status==='conflict'?await call(page,'resolve',{job:job.id,conflict:job.conflict.id,choice:'replace',all:true}):await call(page,'step',{job:job.id});assert.equal(job.status,'done',JSON.stringify(job));
 const after=snapshot(),media=JSON.parse(after.article.media);assert.deepEqual(media.image,{url:'unrelated.jpg'});
 if(mode==='copy')assert.deepEqual(media.grid_gallery,source);else{assert.deepEqual(media.grid_gallery.data,['gone.jpg']);assert.deepEqual(media.grid_gallery.title,['Відсутня світлина']);}
 const dest=target==='gallery'?JSON.parse(after.gallery.value).gallery_content:media.flipbook_gallery;
 assert.deepEqual(dest[target==='gallery'?'gallery_image':'data'],source.data.slice(0,3));assert.deepEqual(dest[target==='gallery'?'gallery_image_title':'title'],source.title.slice(0,3));assert.equal(after.article.type,target==='gallery'?'content_gallery':'flipbook_gallery');
 for(const name of source.data.slice(0,3)){assert(after.files[`images/com_tz_portfolio/${target}/91009/${name}`]);assert(after.files[`images/com_tz_portfolio/${target}/91009/resize/${name.slice(0,-4)}_xs.jpg`]);}
 console.log(`PASS legacy Grid → ${target}: ${mode}, image/caption conflicts, order, Unicode captions, thumbnails, missing record preserved, display selection`);
 job=await call(page,'undo',{job:job.id});while(job.status==='rolling_back')job=await call(page,'undoStep',{job:job.id});assert.equal(job.status,'undone');assert.deepEqual(snapshot(),before);await call(page,'purge',{job:job.id});job=null;console.log('PASS Grid transfer rollback restores metadata and file hashes exactly');
}
assert.deepEqual(errors,[]);
}finally{if(job){job=await call(page,'undo',{job:job.id});while(job.status==='rolling_back')job=await call(page,'undoStep',{job:job.id});await call(page,'purge',{job:job.id});}await browser.close();fixture('cleanup',true);}})().catch(e=>{console.error(e);process.exitCode=1});
