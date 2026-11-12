const fs=require('node:fs');
const {chromium}=require('/opt/codex/cua_node/lib/node_modules/playwright-core');
async function open(username='developer'){
 const env=Object.fromEntries(fs.readFileSync(process.env.TZMT_TEST_ENV||'/workspace/tz-4.1.2/runtime/.env','utf8').trim().split('\n').map(l=>{const n=l.indexOf('=');return [l.slice(0,n),l.slice(n+1)]}));
 const base=process.env.TZMT_TEST_BASE||'http://127.0.0.1:8084';
 const browser=await chromium.launch({executablePath:'/usr/bin/chromium',headless:true,args:['--no-sandbox']});
 const page=await browser.newPage({viewport:{width:1450,height:1100}});const errors=[];
 page.on('pageerror',e=>errors.push(e.message));
 await page.goto(base+'/administrator/');
 await page.locator('input[name=username]').fill(username);await page.locator('input[name=passwd]').fill(env.TZ_ADMIN_PASSWORD);
 await Promise.all([page.waitForURL(u=>!u.searchParams.has('task')),page.locator('button[type=submit]').click()]);
 await page.goto(base+'/administrator/index.php?option=com_tz_media_transfer');
 await page.locator('#tzmt-source').waitFor();
 return {browser,page,errors};
}
async function api(page,action,data={}){
 return page.evaluate(async({action,data})=>{const cfg=Joomla.getOptions('com_tz_media_transfer');const r=await fetch(cfg.url,{method:'POST',credentials:'same-origin',body:new URLSearchParams({[cfg.token]:'1',payload:JSON.stringify({action,...data})})});return {status:r.status,body:await r.json()};},{action,data});
}
module.exports={open,api};
