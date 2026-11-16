import {chromium} from '/Users/grindstone/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';
import assert from 'node:assert/strict';
// Run after native.php + playlist-limits.php prepare the disposable browser fixture.
const browser=await chromium.launch({headless:true,executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'});
try{
 const page=await browser.newPage({viewport:{width:1500,height:1100}}),errors=[],dialogs=[];page.on('pageerror',e=>errors.push(e.message));page.on('dialog',async d=>{dialogs.push(d.message());await d.accept();});
 const base=process.env.HWADS_TEST_URL||'http://127.0.0.1:9494';await page.goto(base+'/wp-admin/');const f=await(await page.request.get(base+'/wp-content/hwads-limits-fixture.json')).json();
 await page.goto(base+'/wp-admin/post.php?post='+f.playlist_pid+'&action=edit');await page.locator('.hwads-nav.hwads-next').waitFor();
 await page.getByLabel('Content type',{exact:true}).selectOption('menus');assert.equal(await page.getByLabel('Maximum active placements',{exact:true}).count(),1);assert.equal(await page.getByLabel('Content type',{exact:true}).count(),1);
 assert.match(await page.locator('.hwads-playlist-capacity').textContent(),/6 \/ 100 placements · 6 \/ 5 active/);
 await page.locator('.hwads-thumb').first().click();await page.getByRole('switch',{name:'Placement active'}).uncheck();assert.match(await page.locator('.hwads-playlist-capacity').textContent(),/5 \/ 5 active/);
 await page.getByRole('switch',{name:'Placement active'}).click();assert.equal(await page.getByRole('switch',{name:'Placement active'}).isChecked(),false);assert(dialogs.some(d=>d.includes('Only 5 placements can be active')));
 await page.getByLabel('Maximum active placements',{exact:true}).fill('15');await page.getByRole('switch',{name:'Placement active'}).check();assert.match(await page.locator('.hwads-playlist-capacity').textContent(),/6 \/ 15 active/);
 await page.locator('#publish').click();await page.waitForURL(/post.php/);await page.locator('.hwads-nav.hwads-next').waitFor();assert.equal(await page.getByLabel('Maximum active placements',{exact:true}).inputValue(),'15');assert.match(await page.locator('.hwads-playlist-capacity').textContent(),/6 \/ 15 active/);
 await page.getByLabel('Content type',{exact:true}).selectOption('community');assert.equal(await page.getByLabel('Maximum active placements',{exact:true}).inputValue(),'5');
 assert.equal(await page.locator('.hwads-playlist-capacity.is-over-limit').count(),1);
 await page.locator('.hwads-thumb').first().click();await page.getByRole('switch',{name:'Placement active'}).uncheck();await page.locator('#publish').click();await page.waitForURL(/post.php/);await page.locator('.hwads-nav.hwads-next').waitFor();await page.getByRole('link',{name:'Edit placement',exact:true}).click();await page.locator('.hwads-frame img').waitFor();const before=dialogs.length;await page.getByRole('switch',{name:'Placement active'}).click();assert.equal(await page.getByRole('switch',{name:'Placement active'}).isChecked(),false);assert(dialogs.length>before);
 assert.deepEqual(errors,[]);console.log('PASS: one per-playlist maximum field; immediate activation prompt leaves switch off; editable maximum persists; type suggests default; live counters; no browser errors');
}finally{await browser.close();}
