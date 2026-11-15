import fs from 'node:fs';import path from 'node:path';const{chromium}=await import('/Users/grindstone/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs');
const root=new URL('../',import.meta.url).pathname,fixture=JSON.parse(fs.readFileSync(root+'wordpress-cpt/wp-content/hwads-native-fixture.json','utf8'));
const browser=await chromium.launch({headless:true,args:['--disable-features=ViewTransition,ViewTransitionOnNavigation'],executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'});const page=await browser.newPage({viewport:{width:1500,height:1100}});const errors=[];page.on('pageerror',e=>errors.push(e.stack || e.message));let saved;
await page.route('**/*',async route=>{
 const req=route.request(),url=new URL(req.url());if(url.hostname!=='hpads.test'){await route.abort();return;}
 if(url.pathname==='/wp-admin/post.php'){
  if(req.method()==='POST'){saved=new URLSearchParams(req.postData());await route.fulfill({contentType:'text/html',body:'<html><body>Saved request captured</body></html>'});return;}
  const type=['template','placement','playlist','venue','location','device','advertiser'].find(x=>Number(url.searchParams.get('post'))===fixture[x+'_pid']) || 'playlist';await route.fulfill({contentType:'text/html',body:fs.readFileSync(root+'tests/native-'+type+'.html','utf8')});return;
 }
 if(url.pathname==='/wp-admin/post-new.php'){await route.fulfill({contentType:'text/html',body:fs.readFileSync(root+'tests/native-new-'+url.searchParams.get('post_type')+'.html','utf8')});return;}
 if(url.pathname==='/wp-admin/admin-post.php' && url.searchParams.get('action')==='hpads_playlist_preview'){await route.fulfill({contentType:'text/html',body:fs.readFileSync(root+'tests/native-playback.html','utf8')});return;}
 if(url.pathname==='/wp-admin/admin-ajax.php'){
  const params=new URLSearchParams(req.postData() || url.search);const action=params.get('action');
  if(action==='query-attachments'){await route.fulfill({json:{success:true,data:[{id:fixture.attachment,title:'Native image',filename:'native.png',url:'https://hpads.test/wp-content/uploads/2026/11/native.png',link:'',alt:'Native image',description:'',caption:'',type:'image',subtype:'png',mime:'image/png',width:160,height:90,author:1,date:Date.now(),modified:Date.now(),sizes:{full:{url:'https://hpads.test/wp-content/uploads/2026/11/native.png',width:160,height:90},thumbnail:{url:'https://hpads.test/wp-content/uploads/2026/11/native.png',width:160,height:90}},compat:{item:'',meta:''}}]}});return;}
  await route.fulfill({json:{success:true,data:{}}});return;
 }
 if(url.pathname.startsWith('/wp-json/') || url.searchParams.has('rest_route')){await route.fulfill({json:{id:1,name:'Test Owner',slug:'owner',meta:{},capabilities:{}}});return;}
 const file=path.resolve(root+'wordpress-cpt','.'+decodeURIComponent(url.pathname));
 if(!file.startsWith(root+'wordpress-cpt/')||!fs.existsSync(file)||!fs.statSync(file).isFile()){await route.fulfill({status:404,body:''});return;}
 const ext=path.extname(file);await route.fulfill({contentType:({'.js':'application/javascript','.css':'text/css','.png':'image/png','.svg':'image/svg+xml','.woff2':'font/woff2','.json':'application/json'})[ext]||'application/octet-stream',body:fs.readFileSync(file)});
});
await page.goto('https://hpads.test/wp-admin/post.php?post='+fixture.template_pid+'&action=edit');await page.locator('#hwads-choose-media').waitFor();
await page.locator('#content-html').click();await page.locator('#content').fill('<p>Browser edited web content</p>');
const editor=await page.locator('#wp-content-wrap').boundingBox(),details=await page.locator('#hwads-details').boundingBox(),sidebar=await page.locator('#submitdiv').boundingBox();
if(!(details.y>editor.y && sidebar.x>editor.x))throw Error('native editor/metabox/sidebar layout incorrect');
if(await page.locator('form#post form').count())throw Error('nested form in native editor');
await page.locator('#hwads-choose-media').click();await page.locator('.media-modal').waitFor();await page.locator('.media-modal').getByText('Media Library',{exact:true}).click();await page.locator('.attachments .attachment').first().click();await page.getByRole('button',{name:'Use Media',exact:true}).click();
if(await page.locator('#hwads-attachment').inputValue()!==String(fixture.attachment))throw Error('WordPress media selection failed');
await page.locator('input[name="hwads[link_url]"]').fill('https://example.com/from-native');await page.screenshot({path:root+'tests/native-template-screen.png',fullPage:true});
await page.locator('#publish').click();await page.waitForFunction(()=>document.body.textContent.includes('Saved request captured'));
if(saved.get('content')!=='<p>Browser edited web content</p>'||saved.get('hwads[attachment_id]')!==String(fixture.attachment)||saved.get('hwads[link_url]')!=='https://example.com/from-native'||!saved.get('hwads_native_nonce'))throw Error('native submit omitted fields');
console.log('PASS: real native Visual/Text editor above details card, right sidebar, WordPress Media Library selection and submitted content/media/options.');
await page.goto('https://hpads.test/wp-admin/post.php?post='+fixture.playlist_pid+'&action=edit');await page.locator('#hpads-playlist .hpads-item').first().waitFor();
if(await page.locator('form#post form').count())throw Error('nested playlist forms');if(await page.locator('.hpads-item .creative').count()!==1)throw Error('saved playlist placement missing');
if(await page.locator('#publish').inputValue()!=='Update' || await page.locator('#save-post').count() || await page.locator('[name="hwads_publish"]').count())throw Error('published playlist should use standard Update without Save Draft or custom publish buttons');
await page.frameLocator('#hwads-inline-playlist').locator('#stage img').waitFor();
if(await page.locator('#postbox-container-1 select[name="parent_id"]').count()!==1 || await page.locator('#postbox-container-1 select[name="purpose"]').count()!==1 || await page.locator('#hwads-details select[name="parent_id"],#hwads-details select[name="purpose"]').count())throw Error('playlist Location and Purpose are not exclusively in the native sidebar');
if(await page.locator('.hpads-item .duration').inputValue()!=='9')throw Error('saved duration missing');if(!await page.getByRole('button',{name:'New Placement from Media Library',exact:true}).isVisible())throw Error('playlist media action missing');
const pe=await page.locator('#titlediv').boundingBox(),bucket=await page.locator('#hwads-details').boundingBox();if(!(bucket.y>pe.y)||await page.locator('#content').count())throw Error('playlist should have a bucket below its title, without web content');
await page.screenshot({path:root+'tests/native-playlist-screen.png',fullPage:true});await page.locator('.hpads-item .placement-title').fill('Browser Card Name');await page.locator('.hpads-item .duration').fill('14');
await page.locator('#publish').click();await page.waitForFunction(()=>document.body.textContent.includes('Saved request captured'));
const items=JSON.parse(saved.get('items'));if(items[0].title!=='Browser Card Name'||items[0].duration!=='14'||!saved.get('hwads_revision')||saved.get('parent_id')===null)throw Error('native playlist form did not serialize cards and revision');
console.log('PASS: native playlist title above Slidestone-style bucket, without web content; saved media card, duration editing, sidebar and serialized playlist submit.');
await page.goto('https://hpads.test/wp-admin/post.php?post='+fixture.placement_pid+'&action=edit');await page.locator('.hwads-placement-preview').waitFor();if(await page.locator('#toplevel_page_hpads-dashboard a').getByText('My Ad Placements',{exact:true}).count())throw Error('duplicate top-level placement navigation remains');if(!await page.locator('#toplevel_page_hpads-dashboard li.current a').textContent().then(x=>x.trim()==='Playlists'))throw Error('placement editor should remain under Playlists');
if(!await page.locator('#postdivrich').isVisible())throw Error('placement landing-page editor must be visible');
const pp=await page.locator('.hwads-placement-preview').boundingBox(),pd=await page.locator('#hwads-details').boundingBox();if(!(pd.y>pp.y))throw Error('placement details not below large media preview');
await page.waitForFunction(()=>window.tinymce && tinymce.get('content') && tinymce.get('content').initialized);const toolbar=await page.locator('#postdivrich .mce-toolbar-grp').boundingBox();if(!toolbar || toolbar.width<400)throw Error('placement Visual editor toolbar initialized at an invalid width');await page.screenshot({path:root+'tests/native-placement-visual-screen.png',fullPage:true});await page.locator('#content-html').click();if(!await page.locator('#content').isVisible())throw Error('placement Code editor is not usable');
await page.screenshot({path:root+'tests/native-placement-screen.png',fullPage:true});console.log('PASS: placement has large preview above details card and always-visible native landing-page editor.');
await page.goto('https://hpads.test/wp-admin/post.php?post='+fixture.device_pid+'&action=edit');await page.locator('#hwads-details').waitFor();
if(await page.locator('#publish').inputValue()!=='Save Device')throw Error('device save label incorrect');
if(await page.locator('select[name="orientation"]').inputValue()!=='vertical')throw Error('device configuration missing');
const db=await page.locator('#hwads-details').boundingBox(),ds=await page.locator('#submitdiv').boundingBox();if(!(ds.x>db.x))throw Error('device save sidebar missing');
if(await page.locator('#hwads-details form').count())throw Error('device editor has nested forms');
await page.locator('.notice code').evaluateAll(nodes=>nodes.forEach(x=>x.textContent='[local test key redacted]'));
await page.screenshot({path:root+'tests/native-device-screen.png',fullPage:true});
await page.route('https://hpads.test/wp-admin/admin-post.php',async r=>{saved=new URLSearchParams(r.request().postData());await r.fulfill({contentType:'text/html',body:'<body>Cleanup request captured</body>'});});
await page.locator('input[name="update_time"]').fill('01:45');await page.locator('input[name="schedule_enabled"]').check();
await page.getByRole('button',{name:'Save Location Update Schedule',exact:true}).click();await page.waitForFunction(()=>document.body.textContent.includes('Cleanup request captured'));
if(saved.get('action')!=='hwads_update_policy'||saved.get('update_time')!=='01:45'||!saved.get('location_id')||!saved.get('hwads_policy_nonce')||saved.has('post_ID'))throw Error('shared update schedule must submit its own form and location scope');
console.log('PASS: device editor saves the shared location update time in an independent nonce-protected form.');
await page.goto('https://hpads.test/wp-admin/post.php?post='+fixture.device_pid+'&action=edit');
await page.getByRole('button',{name:'Purge unused media',exact:true}).click();await page.waitForFunction(()=>document.body.textContent.includes('Cleanup request captured'));
if(saved.get('action')!=='hwads_purge_device'||!saved.get('hwads_purge_nonce')||!saved.get('hwads_purge_device')||saved.has('post_ID'))throw Error('cleanup must use its own nonce-protected form without saving device edits');
console.log('PASS: device cleanup submits its own action/nonce and does not submit native editor changes.');
await page.goto('https://hpads.test/wp-admin/post.php?post='+fixture.venue_pid+'&action=edit');await page.locator('#hwads-details').waitFor();
if(await page.locator('#publish').inputValue()!=='Save Venue' || !await page.locator('input[name="venue_active"]').isChecked())throw Error('venue save/status UI incorrect');
console.log('PASS: native device details/status/save sidebar and venue Save Venue/active controls.');
await page.goto('https://hpads.test/wp-admin/post-new.php?post_type=hpads_playlist');await page.locator('#hwads-details').waitFor();if(await page.locator('#publish').inputValue()!=='Publish' || !await page.locator('#save-post').isVisible())throw Error('unpublished playlist must show standard Save Draft and Publish');console.log('PASS: unpublished playlist has Save Draft/Publish; published playlist has Update and embedded player.');
await page.goto('https://hpads.test/wp-admin/post.php?post='+fixture.playlist_pid+'&action=edit');
if(!(await page.locator('#hwads-embed input').inputValue()).includes('id="'+fixture.playlist+'"'))throw Error('playlist copy shortcode ID incorrect');
await page.goto('https://hpads.test/wp-admin/post.php?post='+fixture.placement_pid+'&action=edit');
if(!(await page.locator('#hwads-embed input').inputValue()).includes('id="'+fixture.placement+'"'))throw Error('placement copy shortcode ID incorrect');
await page.goto('https://hpads.test/wp-admin/post.php?post='+fixture.template_pid+'&action=edit');if(await page.locator('#hwads-embed').count())throw Error('templates must not have embed box');
for(const type of ['playlist','placement']){
 await page.route('https://hpads.test/?hwads_embed='+type+'*',r=>r.fulfill({contentType:'text/html',body:fs.readFileSync(root+'tests/embed-'+type+'.html','utf8')}));
 await page.goto('https://hpads.test/?hwads_embed='+type+'&id='+fixture[type]);await page.locator('#hwads-display img').waitFor();
 if(await page.locator('#wpadminbar').count())throw Error('public embed contains admin chrome');
 if(!(await page.locator('#hwads-display a').getAttribute('href')))throw Error('public embed missing destination');
}
console.log('PASS: copy-ready playlist/placement shortcode IDs, no template embeds, and standalone public playback without admin chrome.');
const appErrors=errors.filter(x=>!x.includes('Transition was aborted because of invalid state. ViewTransition opt-in disabled'));if(appErrors.length)throw Error(appErrors.join('\n'));if(errors.length)console.log('NOTE: Chrome cross-document transition aborted on the stubbed POST response; application assertions passed.');await browser.close();
