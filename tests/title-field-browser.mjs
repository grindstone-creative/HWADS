import {chromium} from '/Users/grindstone/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';
import assert from 'node:assert/strict';
const base=process.env.HWADS_TEST_URL||'http://127.0.0.1:9490';
const browser=await chromium.launch({headless:true,executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'});
let checks=0;const ok=(c,label)=>{assert.ok(c,label);checks++;console.log('PASS: '+label);};
try {
 const page=await browser.newPage({viewport:{width:1440,height:1000}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto(base+'/wp-admin/');const f=await(await page.request.get(base+'/wp-content/hwads-native-fixture.json')).json();
 for(const kind of ['template','placement']) {
  await page.goto(base+'/wp-admin/post.php?post='+f[kind+'_pid']+'&action=edit');await page.locator('.hwads-canvas .hwads-frame img').waitFor();
  ok(await page.getByLabel('Placement name',{exact:true}).count()===(kind==='placement'?1:0),'only native name field shown on '+kind);
  ok(await page.getByLabel('Template name',{exact:true}).count()===(kind==='template'?1:0),'native '+kind+' name is clearly labeled');
  ok(await page.locator('.hwads-tool-panel [aria-label="Placement name"]').count()===0,'duplicate name removed from '+kind+' slide settings');
  if(kind==='template'){ok(await page.getByRole('switch',{name:'Placement active'}).count()===0,'templates do not offer a playback active switch');ok(await page.getByRole('heading',{name:'Template settings',exact:true}).count()===1,'template settings heading is accurate');}
  else {const toggle=page.getByRole('switch',{name:'Placement active'});ok(await page.getByRole('heading',{name:'Placement settings',exact:true}).count()===1,'placement settings heading replaces slide settings');ok(await toggle.count()===1 && await toggle.isChecked(),'one active switch appears in placement heading');await toggle.uncheck();ok(!(await page.locator('[name="hwads[active]"]').isChecked()),'header switch updates canonical active field');}
  const name='Unified '+kind+' name';await page.locator('#title').fill(name);
  await page.getByLabel('External URL',{exact:true}).fill('https://example.com/unified-name');
  ok(await page.locator('#title').inputValue()===name,'editing another control preserves '+kind+' name');
  ok(await page.locator('.hwads-single-proxy .placement-title').inputValue()===name,'internal proxy follows canonical '+kind+' name');
  ok(await page.locator('.hwads-frame > img').getAttribute('alt')===name,'preview uses current '+kind+' name');
  ok(await page.locator('#post').evaluate(n=>new FormData(n).get('post_title'))===name,'native form submits canonical '+kind+' name');
  await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.locator('#publish').click()]);await page.locator('.hwads-canvas .hwads-frame img').waitFor();
  ok(await page.locator('#title').inputValue()===name,'saved '+kind+' name reloads correctly');
  if(kind==='placement'){
   const toggle=page.getByRole('switch',{name:'Placement active'});ok(!await toggle.isChecked(),'off state persists after saving');await page.locator('.hwads-settings:not([inert])').waitFor();await toggle.focus();await toggle.press('Space');ok(await toggle.isChecked(),'active switch works with keyboard');
   await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.locator('#publish').click()]);await page.locator('.hwads-canvas .hwads-frame img').waitFor();ok(await page.getByRole('switch',{name:'Placement active'}).isChecked(),'on state persists after saving');
   await page.setViewportSize({width:390,height:844});ok(await page.getByRole('heading',{name:'Placement settings',exact:true}).isVisible() && await page.getByRole('switch',{name:'Placement active'}).isVisible(),'heading and switch remain visible on mobile');await page.setViewportSize({width:1440,height:1000});
  }
 }
 await page.goto(base+'/wp-admin/post.php?post='+f.playlist_pid+'&action=edit');await page.locator('.hwads-tool-panel').getByLabel('Placement name',{exact:true}).waitFor();
 ok(await page.locator('.hwads-tool-panel').getByLabel('Placement name',{exact:true}).inputValue()==='Unified placement name','playlist slide settings use saved placement name');
 ok((await page.locator('.hwads-thumb').first().getAttribute('aria-label')).includes('Unified placement name'),'playlist thumbnail uses saved placement name');
 ok(await page.getByRole('switch',{name:'Placement active'}).isChecked(),'playlist header switch follows current placement state');
 ok(errors.length===0,'no name synchronization JavaScript errors');
 console.log('TITLE BROWSER TOTAL: '+checks+' checks passed');
} finally {await browser.close();}
