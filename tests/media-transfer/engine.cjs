const {open,api}=require('./helpers.cjs');const assert=require('node:assert/strict');const {execFileSync}=require('node:child_process');
const snapshot=()=>JSON.parse(execFileSync('docker',['exec','migration273-web-1','php','/tmp/tzmt-inspect.php'],{encoding:'utf8'}));
async function call(page,action,p={}){const r=await api(page,action,p);assert.equal(r.status,200,JSON.stringify(r));assert(r.body.success,JSON.stringify(r));return r.body.data;}
const gallery=(s,id)=>JSON.parse(s.galleries[id].value).gallery_content;
const flip=(s,id)=>JSON.parse(s.articles[id].media).flipbook_gallery;
(async()=>{const {browser,page,errors}=await open();try{
 const before=snapshot();const inv=await call(page,'inventory');assert(!inv.adapters.flatMap(a=>a.articles).some(a=>a.id===91004));console.log('PASS inventory excludes trash');
 for(const id of [91004,91006,91007]){const r=await api(page,'start',{source:'flipbook_gallery',target:'gallery',mode:'copy',articles:[id]});assert.equal(r.status,400);assert(!r.body.success);}assert.deepEqual(snapshot(),before);console.log('PASS trash, unsafe filenames and missing files rejected before writes');
 let job=await call(page,'start',{source:'flipbook_gallery',target:'gallery',mode:'copy',activate:false,articles:[91001]});
 job=await call(page,'step',{job:job.id});assert.equal(job.status,'conflict');assert.equal(job.conflict.kind,'image');assert(job.conflict.same_content);
 const stale=job.conflict.id;
 job=await call(page,'resolve',{job:job.id,conflict:stale,choice:'keep',all:false});
 const bad=await api(page,'resolve',{job:job.id,conflict:stale,choice:'replace',all:true});assert.equal(bad.status,400);console.log('PASS stale conflict decision cannot be applied twice');
 job=await call(page,'step',{job:job.id});assert.equal(job.conflict.kind,'caption');
 job=await call(page,'resolve',{job:job.id,conflict:job.conflict.id,choice:'duplicate',all:true});
 job=await call(page,'step',{job:job.id});assert.equal(job.processed,1);
 job=await call(page,'step',{job:job.id});assert.equal(job.conflict.kind,'image');assert(!job.conflict.same_content);
 job=await call(page,'resolve',{job:job.id,conflict:job.conflict.id,choice:'replace',all:true});
 for(let n=0;n<10&&job.status==='running';n++)job=await call(page,'step',{job:job.id});
 assert.equal(job.status,'done',JSON.stringify(job));assert.equal(job.stats.caption_copies,2);assert.equal(job.stats.replaced,1);
 let after=snapshot();assert.deepEqual(after.articles[91001],before.articles[91001]);const g=gallery(after,91001);
 assert.deepEqual(g.gallery_image,['a.jpg','b.jpg','a__copy_1.jpg','фото з підписом.jpg','samehash__copy_1.jpg']);assert.equal(g.gallery_image_title[0],'Старий підпис A');assert.equal(g.gallery_image_title[2],'Новий підпис A');assert.equal(g.gallery_image_title[4],'Інша підпис того ж фото');
 assert.equal(after.files['images/tz_portfolio_plus/gallery/91001/b.jpg'],before.files['images/tz_portfolio/flipbook_gallery/91001/b.jpg']);
 assert.equal(after.files['images/tz_portfolio_plus/gallery/91001/resize/b_xs.jpg'],before.files['images/tz_portfolio/flipbook_gallery/91001/resize/b_xs.jpg']);
 assert.equal(after.files['images/tz_portfolio_plus/gallery/91001/resize/a__copy_1_xs.jpg'],before.files['images/tz_portfolio/flipbook_gallery/91001/resize/a_xs.jpg']);
 console.log('PASS copy, replacement, hashes, thumbnails, separate photos for two captions, independent apply-all policies');
 job=await call(page,'undo',{job:job.id});for(let i=0;i<20&&job.status==='rolling_back';i++)job=await call(page,'undoStep',{job:job.id});assert.equal(job.status,'undone',JSON.stringify(job));assert.deepEqual(snapshot(),before);console.log('PASS complete copy rollback restores metadata and file checksums exactly');
 job=await call(page,'start',{source:'flipbook_gallery',target:'gallery',mode:'move',activate:true,articles:[91002]});
 for(let n=0;n<10&&['running','conflict'].includes(job.status);n++){if(job.status==='conflict')job=await call(page,'resolve',{job:job.id,conflict:job.conflict.id,choice:'keep',all:true});else job=await call(page,'step',{job:job.id});}
 assert.equal(job.status,'done',JSON.stringify(job));assert.equal(job.stats.moved,1);assert.equal(job.stats.skipped,1);after=snapshot();assert.deepEqual(flip(after,91002).data,['keep.jpg']);assert(!after.files['images/tz_portfolio/flipbook_gallery/91002/move.jpg']);assert(!after.files['images/tz_portfolio/flipbook_gallery/91002/resize/move_xs.jpg']);assert(after.files['images/tz_portfolio/flipbook_gallery/91002/keep.jpg']);assert.equal(after.articles[91002].type,'content_gallery');
 console.log('PASS move removes only successfully transferred sources; skipped files retained; display switch works');
 job=await call(page,'undo',{job:job.id});for(let i=0;i<15&&job.status==='rolling_back';i++)job=await call(page,'undoStep',{job:job.id});assert.equal(job.status,'undone',JSON.stringify(job));assert.deepEqual(snapshot(),before);console.log('PASS move rollback restores source originals, derivatives, captions and media type');
 job=await call(page,'start',{source:'gallery',target:'flipbook_gallery',mode:'copy',activate:true,articles:[91003]});
 for(let n=0;n<10&&['running','conflict'].includes(job.status);n++){if(job.status==='conflict')job=await call(page,'resolve',{job:job.id,conflict:job.conflict.id,choice:'keep',all:true});else job=await call(page,'step',{job:job.id});}
 assert.equal(job.status,'done',JSON.stringify(job));after=snapshot();assert.deepEqual(flip(after,91003).data,['reverse.jpg','q.jpg']);assert.equal(flip(after,91003).title[0],'Наявна підпис, не стерти');assert.equal(after.articles[91003].type,'flipbook_gallery');
 job=await call(page,'undo',{job:job.id});for(let i=0;i<15&&job.status==='rolling_back';i++)job=await call(page,'undoStep',{job:job.id});assert.equal(job.status,'undone');assert.deepEqual(snapshot(),before);console.log('PASS reverse transfer and rollback; empty incoming caption does not erase destination text');
 job=await call(page,'start',{source:'flipbook_gallery',target:'gallery',mode:'copy',articles:[91005]});job=await call(page,'step',{job:job.id});assert.equal(job.status,'conflict');job=await call(page,'resolve',{job:job.id,conflict:job.conflict.id,choice:'rename',all:false});job=await call(page,'step',{job:job.id});assert.equal(job.status,'done',JSON.stringify(job));after=snapshot();assert.deepEqual(gallery(after,91005).gallery_image,['orphan__copy_1.jpg']);assert.equal(after.files['images/tz_portfolio_plus/gallery/91005/orphan.jpg'],before.files['images/tz_portfolio_plus/gallery/91005/orphan.jpg']);
 job=await call(page,'undo',{job:job.id});for(let i=0;i<10&&job.status==='rolling_back';i++)job=await call(page,'undoStep',{job:job.id});assert.equal(job.status,'undone',JSON.stringify(job));assert.deepEqual(snapshot(),before);console.log('PASS orphan-file collision, explicit rename, new Gallery row insertion and rollback deletion');
 const csrf=await page.evaluate(async()=>{const cfg=Joomla.getOptions('com_tz_media_transfer');const r=await fetch(cfg.url,{method:'POST',body:new URLSearchParams({payload:JSON.stringify({action:'inventory'})})});return {status:r.status,type:r.headers.get('content-type'),nosniff:r.headers.get('x-content-type-options')};});assert.equal(csrf.status,403);assert.match(csrf.type,/application\/json/);assert.equal(csrf.nosniff,'nosniff');console.log('PASS CSRF guard');
 assert.deepEqual(errors,[]);console.log('All transfer engine checks passed.');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1});
