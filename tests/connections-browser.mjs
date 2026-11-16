import {chromium} from '/Users/grindstone/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';
import assert from 'node:assert/strict';
import fs from 'node:fs';
const base=process.env.HWADS_TEST_URL||'http://127.0.0.1:9493',artifacts=new URL('../artifacts/',import.meta.url).pathname;fs.mkdirSync(artifacts,{recursive:true});
const browser=await chromium.launch({headless:true,executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'});
try {
 const page=await browser.newPage({viewport:{width:1450,height:1000}}),errors=[];page.setDefaultTimeout(10000);page.on('dialog',dialog=>dialog.accept());page.on('pageerror',e=>errors.push(e.message));
 await page.goto(base+'/wp-admin/');const f=await(await page.request.get(base+'/wp-content/hwads-native-fixture.json')).json();
 await page.goto(base+'/wp-admin/post.php?post='+f.playlist_pid+'&action=edit');
 await page.locator('.hwads-canvas .hwads-frame img').waitFor();await page.locator('.hwads-nav.hwads-next').waitFor();
 const active=page.getByRole('switch',{name:'Placement active'});await active.uncheck();
 await page.locator('.hwads-frame.hwads-not-current > .hwads-availability').filter({hasText:'Inactive'}).waitFor();
 assert.equal(await page.locator('.hwads-thumb.hwads-not-current').count(),1);
 await page.screenshot({path:artifacts+'connections-inactive-desktop.png',fullPage:true});
 console.log('Inactive mark passed');await active.check();await page.getByLabel('Start',{exact:true}).fill('2099-01-01T00:00');
 await page.locator('.hwads-frame > .hwads-availability').filter({hasText:'Scheduled'}).waitFor();
 console.log('Scheduled mark passed');await page.getByLabel('Start',{exact:true}).fill('');await page.getByLabel('End',{exact:true}).fill('2000-01-01T00:00');
 await page.locator('.hwads-frame > .hwads-availability').filter({hasText:'Expired'}).waitFor();
 console.log('Expired mark passed');await page.setViewportSize({width:390,height:844});await page.screenshot({path:artifacts+'connections-expired-mobile.png',fullPage:true});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2));
 await page.getByLabel('End',{exact:true}).fill('');assert.equal(await page.locator('.hwads-frame.hwads-not-current').count(),0);
 // Saving canonical Active must persist, rather than leaving a separate playlist gate.
 console.log('Mobile fit passed');await active.uncheck();await page.locator('#publish').click();await page.waitForURL(/post.php/);await page.locator('.hwads-canvas .hwads-frame img').waitFor();await page.locator('.hwads-nav.hwads-next').waitFor();assert.equal(await page.getByRole('switch',{name:'Placement active'}).isChecked(),false);
 await page.goto(base+'/wp-admin/post.php?post='+f.placement_pid+'&action=edit');await page.locator('.hwads-canvas .hwads-frame img').waitFor();assert.equal(await page.getByRole('switch',{name:'Placement active'}).isChecked(),false);
 await page.getByRole('switch',{name:'Placement active'}).check();await page.locator('#publish').click();await page.waitForURL(/post.php/);await page.locator('.hwads-canvas .hwads-frame img').waitFor();
 await page.goto(base+'/wp-admin/post.php?post='+f.playlist_pid+'&action=edit');await page.locator('.hwads-canvas .hwads-frame img').waitFor();assert.equal(await page.getByRole('switch',{name:'Placement active'}).isChecked(),true);
 assert.deepEqual(errors,[]);console.log('PASS: inactive/scheduled/expired marks, mobile fit, all placements remain visible, Active saved and shared across editors; no browser errors');
}finally {await browser.close();}
