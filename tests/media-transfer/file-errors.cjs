const {open,api}=require('./helpers.cjs');const assert=require('node:assert/strict');const {execFileSync}=require('node:child_process');
const fixture=action=>execFileSync('docker',['exec','--user','www-data','migration273-web-1','php','/tmp/tzmt-file-errors-fixture.php',action],{encoding:'utf8'});const snapshot=()=>JSON.parse(fixture('snapshot'));
async function call(p,a,d={}){const r=await api(p,a,d);assert.equal(r.status,200,JSON.stringify(r));assert(r.body.success,JSON.stringify(r));return r.body.data;}
async function finish(page,j){for(let n=0;n<30&&j.status==='running';n++)j=await call(page,'step',{job:j.id});return j;}
(async()=>{fixture('prepare');const {browser,page,errors}=await open();let job;try{
 const before=snapshot();
 for(const mode of ['copy','move']){
  job=await call(page,'start',{source:'grid_gallery',target:'gallery',mode,activate:true,articles:[91010]});assert.equal(job.total,5);
  job=await call(page,'step',{job:job.id});assert.equal(job.processed,1);assert.equal(job.status,'running');
  job=await call(page,'step',{job:job.id});assert.equal(job.status,'file_error');assert.equal(job.file_error.name,'bad.jpg');assert(job.file_error.path.endsWith('/images/com_tz_portfolio/grid_gallery/91010/bad.jpg'));
  const errorId=job.file_error.id;let r=await api(page,'skip',{job:job.id,file_error:'stale',all:true});assert.equal(r.status,400);
  const atPause=snapshot();r=await api(page,'skip',{job:job.id,file_error:errorId,all:'yes'});assert.equal(r.status,400);assert.deepEqual(snapshot(),atPause);
  if(mode==='copy'){
   job=await call(page,'skip',{job:job.id,file_error:errorId,all:false});assert.equal(job.stats.skipped,1);
   r=await api(page,'skip',{job:job.id,file_error:errorId,all:false});assert.equal(r.status,400);
   job=await finish(page,job);assert.equal(job.status,'file_error');assert.equal(job.file_error.name,'bad.png');
   job=await call(page,'skip',{job:job.id,file_error:job.file_error.id,all:true});
  }else job=await call(page,'skip',{job:job.id,file_error:errorId,all:true});
  job=await finish(page,job);assert.equal(job.status,'done',JSON.stringify(job));assert.equal(job.processed,5);assert.equal(job.stats.skipped,2);assert.equal(job.stats[mode==='copy'?'copied':'moved'],3);
  assert.deepEqual(job.skipped_files.map(f=>f.path),['bad.jpg','bad.png'].map(n=>'/var/www/html/images/com_tz_portfolio/grid_gallery/91010/'+n));
  const after=snapshot(),src=JSON.parse(after.article.media).grid_gallery,dest=JSON.parse(after.gallery.value).gallery_content;
  assert.deepEqual(dest.gallery_image,['008.png','good.jpg','last.jpg']);assert.deepEqual(dest.gallery_image_title,['Коректна світлина','Зберегти підпис','Остання світлина']);
  if(mode==='copy')assert.deepEqual(src,JSON.parse(before.article.media).grid_gallery);else{assert.deepEqual(src.data,['bad.jpg','bad.png']);assert.deepEqual(src.title,['Підозрілий файл','Пошкоджений PNG']);}
  for(const n of ['bad.jpg','bad.png'])assert.equal(after.files['images/com_tz_portfolio/grid_gallery/91010/'+n],before.files['images/com_tz_portfolio/grid_gallery/91010/'+n]);
  await page.reload();const loaded=await call(page,'status',{job:job.id});assert.deepEqual(loaded.skipped_files,job.skipped_files);
  await page.locator(`#tzmt-history tr[data-job="${job.id}"] button`).first().click();await page.locator('#tzmt-skipped-panel:not([hidden])').waitFor();assert.match(await page.locator('#tzmt-skipped-total').innerText(),/2/);assert.equal(await page.locator('#tzmt-skipped-files tr').count(),2);
  for(const f of job.skipped_files)assert((await page.locator('#tzmt-skipped-files').innerText()).includes(f.path));assert.equal(await page.locator('#tzmt-skipped-files script').count(),0);assert.equal(await page.evaluate(()=>window.tzmtErrorXss),undefined);
  console.log('PASS '+mode+': real PNG accepted, one/all error skips, stale decision blocked, bad originals and captions retained, report persisted after reload');
  job=await call(page,'undo',{job:job.id});while(job.status==='rolling_back')job=await call(page,'undoStep',{job:job.id});assert.equal(job.status,'undone');assert.deepEqual(snapshot(),before);await call(page,'purge',{job:job.id});job=null;
 }
 for(const choice of ['cancel','skip']){
  await page.reload();await page.locator('#tzmt-source:not(:disabled)').waitFor();await page.locator('#tzmt-source').selectOption('grid_gallery');await page.locator('#tzmt-target').selectOption('gallery');await page.locator('#tzmt-scan').click();await page.waitForFunction(()=>document.getElementById('tzmt-message').textContent.includes('Поиск завершён'));await page.locator('#tzmt-select-none').click();await page.locator('#tzmt-materials input[value="91010"]').check();
  const response=page.waitForResponse(r=>{try{return JSON.parse(new URLSearchParams(r.request().postData()||'').get('payload')||'{}').action==='start';}catch{return false;}});await page.locator('#tzmt-start').click();job=(await (await response).json()).data;
  await page.locator('#tzmt-file-error[open]').waitFor();assert.match(await page.locator('#tzmt-file-error-path').innerText(),/bad.jpg/);assert.equal(await page.locator('#tzmt-file-error script').count(),0);
  if(choice==='cancel'){
   await page.locator('#tzmt-file-error-cancel').click();await page.waitForFunction(()=>!document.getElementById('tzmt-file-error').open);job=await call(page,'status',{job:job.id});assert.equal(job.status,'cancelled');assert.equal(job.processed,1);
  }else{
   await page.locator('#tzmt-file-error-skip').click();await page.waitForFunction(()=>document.getElementById('tzmt-file-error').open&&document.getElementById('tzmt-file-error-path').textContent.includes('bad.png'));await page.locator('#tzmt-file-error-all').click();await page.waitForFunction(()=>document.getElementById('tzmt-progress-text').textContent.startsWith('Завершено'));job=await call(page,'status',{job:job.id});assert.equal(job.stats.skipped,2);assert.match(await page.locator('#tzmt-skipped-total').innerText(),/2/);
  }
  console.log('PASS UI file-error '+choice+': buttons work and modal closes, no script execution');
  job=await call(page,'undo',{job:job.id});while(job.status==='rolling_back')job=await call(page,'undoStep',{job:job.id});assert.equal(job.status,'undone');assert.deepEqual(snapshot(),before);await call(page,'purge',{job:job.id});job=null;
 }
 fixture('thumbnail');const withThumbnail=snapshot();
 job=await call(page,'start',{source:'grid_gallery',target:'gallery',mode:'move',articles:[91010]});job=await call(page,'step',{job:job.id});
 assert.equal(job.status,'file_error');assert.match(job.file_error.message,/resize\/008_xl.png/);assert.deepEqual(snapshot(),withThumbnail);
 job=await call(page,'skip',{job:job.id,file_error:job.file_error.id,all:true});job=await finish(page,job);assert.equal(job.status,'done');assert.equal(job.stats.skipped,3);assert.equal(job.stats.moved,2);
 job=await call(page,'undo',{job:job.id});while(job.status==='rolling_back')job=await call(page,'undoStep',{job:job.id});assert.equal(job.status,'undone');assert.deepEqual(snapshot(),withThumbnail);await call(page,'purge',{job:job.id});job=null;fixture('thumbnail-remove');assert.deepEqual(snapshot(),before);
 console.log('PASS invalid thumbnail rolls back the already written original before offering skip; move retains original and thumbnail; undo remains exact');
 assert.deepEqual(errors,[]);
}finally{if(job){await call(page,'cancel',{job:job.id});job=await call(page,'undo',{job:job.id});while(job.status==='rolling_back')job=await call(page,'undoStep',{job:job.id});await call(page,'purge',{job:job.id});}await browser.close();fixture('cleanup');}})().catch(e=>{console.error(e);process.exitCode=1});
