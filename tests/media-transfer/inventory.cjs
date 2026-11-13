const {open,api}=require('./helpers.cjs');const assert=require('node:assert/strict');const {execFileSync}=require('node:child_process');
const fixture=action=>execFileSync('docker',['exec','migration273-web-1','php','/tmp/tzmt-inventory-file.php',action]);
async function call(page,action,data={}){const r=await api(page,action,data);assert.equal(r.status,200,JSON.stringify(r));assert(r.body.success,JSON.stringify(r));return r.body.data;}
(async()=>{const {browser,page,errors}=await open();let job;try{
 const actions=[];page.on('request',r=>{if(r.postData()){const data=new URLSearchParams(r.postData()).get('payload');if(data)actions.push(JSON.parse(data).action);}});
 await page.reload();await page.locator('#tzmt-source:not(:disabled)').waitFor();
 assert(!actions.includes('inventory'));assert.equal(await page.locator('#tzmt-source-summary').innerText(),'');assert.equal(await page.locator('#tzmt-target-summary').innerText(),'');
 const row=page.locator('#tzmt-materials tr').filter({has:page.locator('input[value="91001"]')});assert.equal(await row.count(),1);
 assert.deepEqual(await row.locator('td').allTextContents(),['','TZMT test — копирование (ID 91001)','—','—','—','—','—','—']);assert(await page.locator('#tzmt-start').isDisabled());
 assert.equal(await page.locator('#tzmt-materials').locator('..').locator('th[scope=colgroup]').count(),3);
 console.log('PASS opening shows materials with six dashes, empty summaries, no automatic file scan');
 await page.locator('#tzmt-scan').click();await page.waitForFunction(()=>document.getElementById('tzmt-message').textContent.includes('Поиск завершён'));
 assert.deepEqual((await row.locator('td').allTextContents()).slice(2),['4','3','2','1','0','0']);
 const missing=page.locator('#tzmt-materials tr').filter({has:page.locator('input[value="91007"]')});assert.deepEqual((await missing.locator('td').allTextContents()).slice(2),['0','0','0','0','0','0']);assert(await missing.locator('input').isDisabled());
 assert(!/Недоступно|Проверка|Не найдено файлов/.test(await page.locator('#tzmt').innerText()));
 console.log('PASS scan counts existing files and their captions in all add-on columns; absent originals count as zero');
 fixture('hide');
 await page.locator('#tzmt-scan').click();await page.waitForFunction(()=>document.getElementById('tzmt-message').textContent.includes('Поиск завершён'));
 assert.deepEqual((await row.locator('td').allTextContents()).slice(2),['3','2','2','1','0','0']);
 console.log('PASS repeat scan detects a removed original and removes its caption from the count');
 job=await call(page,'start',{source:'flipbook_gallery',target:'gallery',mode:'copy',activate:false,articles:[91001]});assert.equal(job.total,3);
 for(let i=0;i<20&&['running','conflict'].includes(job.status);i++)job=job.status==='conflict'?await call(page,'resolve',{job:job.id,conflict:job.conflict.id,choice:'keep',all:true}):await call(page,'step',{job:job.id});
 assert.equal(job.status,'done',JSON.stringify(job));assert.equal(job.processed,3);console.log('PASS transfer processes the three available originals without being blocked by a stale missing record');
 job=await call(page,'undo',{job:job.id});while(job.status==='rolling_back')job=await call(page,'undoStep',{job:job.id});assert.equal(job.status,'undone');
 await call(page,'purge',{job:job.id});job=null;fixture('restore');
 await page.locator('#tzmt-scan').click();await page.waitForFunction(()=>document.getElementById('tzmt-message').textContent.includes('Поиск завершён'));assert.deepEqual((await row.locator('td').allTextContents()).slice(2),['4','3','2','1','0','0']);
 await page.locator('#tzmt-select-none').click();assert.equal(await page.locator('#tzmt-materials input:checked').count(),0);await page.locator('#tzmt-select-all').click();assert(await row.locator('input').isChecked());
 assert.deepEqual(errors,[]);console.log('PASS restored original counted on next scan, bulk selection works, no JS errors');
 }finally{if(job){job=await call(page,'undo',{job:job.id});while(job.status==='rolling_back')job=await call(page,'undoStep',{job:job.id});await call(page,'purge',{job:job.id});}fixture('restore');await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1});
