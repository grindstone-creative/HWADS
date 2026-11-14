import fs from 'node:fs';import path from 'node:path';const{chromium}=await import(process.env.HWADS_PLAYWRIGHT_MODULE || 'playwright');
const root=process.env.HWADS_FIXTURE_ROOT || new URL('../',import.meta.url).pathname,fixture=JSON.parse(fs.readFileSync(root+'wordpress-cpt/wp-content/hwads-native-fixture.json','utf8'));
const browser=await chromium.launch({headless:true,args:['--disable-features=ViewTransition,ViewTransitionOnNavigation'],executablePath:process.env.HWADS_CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'});const page=await browser.newPage({viewport:{width:1500,height:1100}});const errors=[];page.on('pageerror',e=>errors.push(e.stack || e.message));let saved;
await page.route('**/*',async route=>{
 const req=route.request(),url=new URL(req.url());if(url.hostname!=='hpads.test'){await route.abort();return;}
 if(url.pathname==='/wp-admin/post.php'){
  if(req.method()==='POST'){saved=new URLSearchParams(req.postData());await route.fulfill({contentType:'text/html',body:'<html><body>Saved request captured</body></html>'});return;}
  const type=Number(url.searchParams.get('post'))===fixture.template_pid?'template':Number(url.searchParams.get('post'))===fixture.placement_pid?'placement':'playlist';await route.fulfill({contentType:'text/html',body:fs.readFileSync(root+'tests/native-'+type+'.html','utf8')});return;
 }
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
if(await page.locator('.hpads-item .duration').inputValue()!=='9')throw Error('saved duration missing');if(!await page.getByRole('button',{name:'Choose from Media Library',exact:true}).isVisible())throw Error('playlist media action missing');
const pe=await page.locator('#titlediv').boundingBox(),bucket=await page.locator('#hwads-details').boundingBox();if(!(bucket.y>pe.y)||await page.locator('#content').count())throw Error('playlist should have a bucket below its title, without web content');
await page.screenshot({path:root+'tests/native-playlist-screen.png',fullPage:true});await page.locator('.hpads-item .duration').fill('14');
await page.locator('#publish').click();await page.waitForFunction(()=>document.body.textContent.includes('Saved request captured'));
const items=JSON.parse(saved.get('items'));if(items[0].duration!=='14'||!saved.get('hwads_revision')||saved.get('parent_id')===null)throw Error('native playlist form did not serialize cards and revision');
console.log('PASS: native playlist title above Slidestone-style bucket, without web content; saved media card, duration editing, sidebar and serialized playlist submit.');
await page.goto('https://hpads.test/wp-admin/post.php?post='+fixture.placement_pid+'&action=edit');await page.locator('#hwads-toggle-content').waitFor();
if(await page.locator('#postdivrich').isVisible())throw Error('placement web editor should be collapsed initially');
const pp=await page.locator('.hwads-placement-preview').boundingBox(),pd=await page.locator('#hwads-details').boundingBox();if(!(pd.y>pp.y))throw Error('placement details not below large media preview');
await page.locator('#hwads-toggle-content').click();if(!await page.locator('#postdivrich').isVisible())throw Error('native content editor cannot be opened');await page.locator('#hwads-toggle-content').click();
await page.screenshot({path:root+'tests/native-placement-screen.png',fullPage:true});console.log('PASS: placement has large preview above details card and toggleable native web content editor.');
const appErrors=errors.filter(x=>!x.includes('Transition was aborted because of invalid state. ViewTransition opt-in disabled'));if(appErrors.length)throw Error(appErrors.join('\n'));if(errors.length)console.log('NOTE: Chrome cross-document transition aborted on the stubbed POST response; application assertions passed.');await browser.close();
