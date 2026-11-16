import {chromium} from '/Users/grindstone/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';
import assert from 'node:assert/strict';
const browser=await chromium.launch({headless:true,executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'});
try {
 const base=process.env.HWADS_TEST_URL || 'http://127.0.0.1:9498',page=await browser.newPage();
 await page.goto(base+'/wp-admin/');const f=await(await page.request.get(base+'/wp-content/hwads-native-fixture.json')).json();
 for(const id of [f.placement,f.template]) {
  const response=await page.goto(base+'/wp-admin/admin-post.php?action=hpads_landing_preview&id='+id);
  assert.equal(response.status(),200);assert(page.url().includes('hwads_landing_preview='+id));assert(!page.url().includes('admin-post.php'));
  assert((response.headers()['cache-control']||'').includes('no-store'));assert((response.headers()['x-robots-tag']||'').includes('noindex'));
  assert(!(await page.content()).includes('critical error'));
 }
 const bad=await page.goto(base+'/?hwads_landing_preview=99999999');assert.equal(bad.status(),403);
 const guest=await browser.newContext();await guest.addCookies([{name:'playground_auto_login_already_happened',value:'1',url:base}]);const request=await guest.request.get(base+'/?hwads_landing_preview='+f.placement);assert.equal(request.status(),403);
 const legacy=await guest.request.get(base+'/wp-admin/admin-post.php?action=hpads_landing_preview&id='+f.template);assert.equal(legacy.status(),403);
 await guest.close();console.log('PASS: placement and template preview redirects, front-end response, private/no-store/noindex, invalid ID, anonymous front-end and legacy access denied');
}finally{await browser.close();}
