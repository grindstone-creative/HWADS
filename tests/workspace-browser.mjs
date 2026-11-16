import {chromium} from '/Users/grindstone/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';
import fs from 'node:fs';import assert from 'node:assert/strict';
const base=process.env.HWADS_TEST_URL||'http://127.0.0.1:9487',root=new URL('../',import.meta.url).pathname;
fs.mkdirSync(root+'artifacts',{recursive:true});
const browser=await chromium.launch({headless:true,executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',args:['--autoplay-policy=no-user-gesture-required']});
try{
const page=await browser.newPage({viewport:{width:1536,height:1050}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
await page.goto(base+'/wp-admin/');const fixture=await (await page.request.get(base+'/wp-content/hwads-native-fixture.json')).json();
await page.goto(base+'/wp-admin/post.php?post='+fixture.playlist_pid+'&action=edit');
await page.locator('.hwads-canvas .hwads-frame img').waitFor();await page.locator('.hwads-nav.hwads-next').waitFor();
assert.equal(await page.locator('#hwads-workspace-mount').isVisible(),true);
const canvas=await page.locator('.hwads-canvas').boundingBox(),settings=await page.locator('.hwads-settings').boundingBox(),main=await page.locator('#poststuff').boundingBox();assert(settings.x>canvas.x);assert(main.y>canvas.y);
assert.equal(await page.locator('#all-dates').count(),0);assert.equal(await page.getByRole('button',{name:'Pause',exact:true}).count(),0);
await page.getByRole('tab',{name:'QR',exact:true}).click();const toggle=page.getByLabel('Show QR code',{exact:true});if(!await toggle.isChecked())await toggle.check();await page.locator('.hwads-canvas .hwads-qr svg').waitFor();
await page.getByLabel('Code color',{exact:true}).fill('#112233');await page.getByLabel('Background color',{exact:true}).fill('#fff4dd');assert.equal(await page.locator('.hwads-qr svg path').getAttribute('fill'),'#112233');assert.equal(await page.locator('.hwads-qr svg rect').getAttribute('fill'),'#fff4dd');await page.getByLabel('Size (%)',{exact:true}).fill('20');await page.getByLabel('Edge spacing (source pixels)',{exact:true}).fill('2');await page.getByRole('button',{name:'Top left',exact:true}).click();const qr=await page.locator('.hwads-qr').boundingBox();assert(qr.x>=canvas.x && qr.x<canvas.x+30);assert(qr.y>=canvas.y && qr.y<canvas.y+30);
await page.locator('.hwads-qr').click();await page.locator('.hwads-resize').waitFor();
await page.getByRole('tab',{name:'Elements',exact:true}).click();await page.getByRole('button',{name:'+ Text',exact:true}).click();await page.getByLabel('Text',{exact:true}).fill('Browser overlay edit');await page.getByLabel('Text color',{exact:true}).fill('#00ff00');assert.equal(await page.locator('.hwads-canvas .hwads-text').last().textContent(),'Browser overlay edit');
await page.getByRole('tab',{name:'Slide',exact:true}).click();assert.equal(await page.getByLabel('Entrance',{exact:true}).count(),1);assert.equal(await page.getByLabel('Exit',{exact:true}).count(),1);
await page.screenshot({path:root+'artifacts/playlist-desktop.png',fullPage:true});
await page.getByRole('button',{name:'+ Add slide',exact:true}).click();await page.locator('#hpads-playlist-template').selectOption(String(fixture.template));const before=await page.locator('#hpads-playlist>.hpads-item').count();await page.getByRole('button',{name:'Add Selected Template',exact:true}).click();await page.waitForFunction(n=>document.querySelectorAll('#hpads-playlist>.hpads-item').length===n,before+1);await page.locator('.hwads-nav.hwads-next').waitFor();
await page.locator('#publish').click();await page.waitForURL(/post\.php/);await page.locator('.hwads-canvas .hwads-frame img').waitFor();assert.equal(await page.locator('#hpads-playlist>.hpads-item').count(),before+1);
// The original placement keeps its new overlay after a real WordPress Update.
await page.locator('.hwads-thumb').first().click();await page.locator('.hwads-nav.hwads-next').waitFor();await page.locator('.hwads-canvas .hwads-text').filter({hasText:'Browser overlay edit'}).last().waitFor();assert.equal(await page.locator('.hwads-canvas .hwads-qr').count(),1);
await page.setViewportSize({width:390,height:844});await page.getByRole('tab',{name:'Elements',exact:true}).click();const mobileCanvas=await page.locator('.hwads-canvas').boundingBox(),mobileSettings=await page.locator('.hwads-settings').boundingBox();assert(mobileSettings.y>mobileCanvas.y+mobileCanvas.height);assert(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth+2));await page.screenshot({path:root+'artifacts/playlist-mobile.png',fullPage:true});
// Private native editing URL must not expose draft content to anonymous visitors.
const anonymous=await browser.newContext();await anonymous.addCookies([{name:'playground_auto_login_already_happened',value:'1',url:base}]);const response=await anonymous.request.get(base+'/?post_type=hpads_placement&p='+fixture.placement_pid);assert.equal(response.status(),404);await anonymous.close();
await page.setViewportSize({width:1536,height:1050});await page.goto(base+'/wp-admin/');await page.locator('#wp-admin-bar-hwads-view-as').hover();await page.locator('#wp-admin-bar-hwads-view-hpads_venue_user a').click();await page.getByText('Read-only role preview:',{exact:false}).waitFor();assert.equal(await page.locator('#menu-plugins').count(),0);await page.locator('.notice-warning a').filter({hasText:'Return to SuperAdmin'}).first().click();await page.locator('#menu-plugins').waitFor();
assert.deepEqual(errors,[]);console.log('PASS: real WordPress desktop/mobile workspace, QR position/size/selection, text overlays, separate transitions, template creation, native Update/reload persistence and anonymous draft protection.');
}finally{await browser.close();}
