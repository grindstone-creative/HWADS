import {chromium} from '/Users/grindstone/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';
import assert from 'node:assert/strict';
const base=process.env.HWADS_TEST_URL||'http://127.0.0.1:9490';const f=await(await fetch(base+'/wp-content/hwads-browser-roles.json')).json();
const browser=await chromium.launch({headless:true,executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'});
try{
 const context=await browser.newContext({viewport:{width:1450,height:1000}});await context.addCookies([{name:'playground_auto_login_already_happened',value:'1',url:base},...Object.entries(f.cookies.owner).map(([name,value])=>({name,value,url:base}))]);const page=await context.newPage();
 await page.goto(base+'/wp-admin/admin.php?page=hpads-updates');await page.getByRole('button',{name:'Upload Player ZIP',exact:true}).waitFor();await page.locator('input[name="package"]').setInputFiles(new URL('../dist/hwads-pi-0.4.1.zip',import.meta.url).pathname);await page.getByRole('button',{name:'Upload Player ZIP',exact:true}).click();await page.waitForLoadState('domcontentloaded');
 if(!(await page.locator('select[name="release"] option').allTextContents()).some(s=>s.startsWith('0.4.1')))throw Error((await page.locator('body').innerText()).slice(0,1500));console.log('PASS: real multipart ZIP upload validates and appears in release picker');
 await page.locator('select[name="release"]').selectOption({index:1});await page.locator('input[name="devices[]"][value="'+f.device+'"]').check();await page.getByRole('button',{name:'Install on Selected Devices',exact:true}).click();await page.waitForLoadState('domcontentloaded');
 assert.ok((await page.locator('body').innerText()).includes('Waiting for device check-in'));console.log('PASS: nonce-protected deployment form queues selected fixture device');await page.screenshot({path:new URL('./player-updates-desktop.png',import.meta.url).pathname,fullPage:true});
}finally{await browser.close();}
