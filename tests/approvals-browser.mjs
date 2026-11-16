import fs from 'node:fs';
const {chromium}=await import('/Users/grindstone/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs');
const base=process.env.HWADS_TEST_URL||'http://127.0.0.1:9490';
const fixture=await (await fetch(base+'/wp-content/hwads-browser-roles.json')).json();
const browser=await chromium.launch({headless:true,executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'});
const errors=[];let count=0;
function ok(c,label){if(!c)throw Error(label);console.log('PASS: '+label);count++;}
async function pageFor(role,viewport={width:1450,height:1000}){
 const context=await browser.newContext({viewport});const values=fixture.cookies[role];
 await context.addCookies([{name:'playground_auto_login_already_happened',value:'1',url:base},...Object.entries(values).map(([name,value])=>({name,value,url:base}))]);
 const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));return page;
}
try{
 const admin=await pageFor('owner');await admin.goto(base+'/wp-admin/post.php?post='+fixture.venue_pid+'&action=edit');
 await admin.getByText('Who needs to approve?',{exact:true}).waitFor();ok(await admin.locator('input[name="ad_approval_mode"][value="all"]').isChecked(),'native venue editor displays saved all-reviewer selection');
 ok(await admin.locator('input[name="ad_approval_reviewers[]"]').count()>=2,'venue editor lists eligible staff');
 await admin.evaluate(()=>{const banner=document.createElement('div');banner.id='fixture-divi-promotion';banner.innerHTML='A New And Improved Divi Builder Experience Is Available! <a href="#">Switch To The New Divi Builder</a>';document.body.append(banner);const unrelated=document.createElement('button');unrelated.id='fixture-divi-control';unrelated.textContent='Load Layout';document.body.append(unrelated);});
 await admin.waitForFunction(()=>document.getElementById('fixture-divi-promotion').hidden);ok(await admin.locator('#fixture-divi-control').isVisible(),'Divi promotion is hidden while real controls remain visible');
 const reviewer=await pageFor('first');await reviewer.goto(base+'/wp-admin/admin.php?page=hpads-approvals');await reviewer.getByRole('link',{name:/Needs my review/}).waitFor();
 ok(await reviewer.getByText('Approval Test Ad',{exact:true}).count()>=1,'reviewer sees pending placement in own queue');
 await reviewer.getByRole('link',{name:'Review Ad',exact:true}).last().click();await reviewer.getByRole('button',{name:'Approve Ad',exact:true}).waitFor();
 const frame=reviewer.frameLocator('iframe[title="Saved placement preview"]');await frame.locator('#hwads-display img').waitFor();await frame.locator('#hwads-display img').evaluate(async img=>{await img.decode();await new Promise(r=>setTimeout(r,650));});
 ok(!(await frame.locator('body').innerText()).includes('Warning:'),'private preview renders without PHP warnings');
 ok(await frame.locator('#hwads-display img').count()===1,'private review preview loads actual slide through player renderer');
 ok(await reviewer.locator('#post').count()===0,'review-only user receives no editable WordPress post form');
 await reviewer.screenshot({path:new URL('./approval-review-desktop.png',import.meta.url).pathname,fullPage:true});
 await reviewer.getByRole('textbox',{name:'Review note (optional)'}).fill('Browser verified approval');
 await reviewer.getByRole('button',{name:'Approve Ad',exact:true}).click();await reviewer.waitForLoadState('domcontentloaded');
 ok(await reviewer.getByText('1 of 2 approved',{exact:true}).count()>0,'real review submission records vote and returns to progress panel');
 await reviewer.goto(base+'/wp-admin/admin.php?page=hpads-approvals');ok(await reviewer.getByText('No matching approvals.',{exact:true}).count()>0,'completed personal review leaves Needs my review');
 const outsider=await pageFor('outsider');await outsider.goto(base+'/wp-admin/admin.php?page=hpads-approvals&view=all&status=all');ok(await outsider.getByText('Approval Test Ad',{exact:true}).count()===0,'unrelated venue cannot find placement in queue');
 const response=await outsider.goto(base+'/wp-admin/admin.php?page=hpads-approvals&review='+fixture.ad);ok((await outsider.locator('body').innerText()).includes('Placement unavailable'),'forged direct review URL rejected');
 await admin.goto(base+'/wp-admin/admin.php?page=hpads-approvals&view=all&status=all');await admin.screenshot({path:new URL('./approval-queue-desktop.png',import.meta.url).pathname,fullPage:true});
 const mobile=await pageFor('first',{width:390,height:844});await mobile.goto(base+'/wp-admin/admin.php?page=hpads-approvals&review='+fixture.ad);await mobile.getByRole('heading',{name:'Approval Test Ad',exact:true}).waitFor();
 ok(await mobile.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2),'review page fits mobile viewport');
 await mobile.goto(base+'/wp-admin/admin.php?page=hpads-approvals&view=all&status=all');ok(await mobile.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2),'approval queue keeps wide table inside a scrolling container on mobile');
 ok(!errors.length,'no approval UI JavaScript errors');console.log('APPROVAL BROWSER TOTAL: '+count+' checks passed');
}finally{await browser.close();}
