import {chromium} from '/Users/grindstone/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';import assert from 'node:assert/strict';
const base=process.env.HWADS_TEST_URL||'http://127.0.0.1:9490',browser=await chromium.launch({headless:true,executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'});
try{
 const page=await browser.newPage();await page.goto(base+'/wp-admin/');const f=await(await page.request.get(base+'/wp-content/hwads-native-fixture.json')).json();
 await page.goto(base+'/wp-admin/admin.php?page=hpads-updates');const row=page.locator('tr').filter({has:page.locator('a[href*="post='+f.device_pid+'&"]')});const checkbox=row.locator('input[name="devices[]"]');
 await checkbox.check();assert.ok((await row.innerText()).includes('scheduling available'));console.log('PASS: offline updater-ready device remains selectable for scheduling');
 await page.locator('select[name="release"]').selectOption({index:1});await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.getByRole('button',{name:'Schedule Selected Devices',exact:true}).click()]);
 assert.ok((await page.locator('body').innerText()).includes('Scheduled for'));assert.equal(await page.getByRole('button',{name:'Cancel Scheduled Update',exact:true}).count(),1);console.log('PASS: real scheduled deployment form queues request with visible due time');
 await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.getByRole('button',{name:'Cancel Scheduled Update',exact:true}).click()]);assert.ok((await page.locator('body').innerText()).includes('Scheduled update cancelled'));assert.equal(await page.getByRole('button',{name:'Cancel Scheduled Update',exact:true}).count(),0);console.log('PASS: real cancel form removes undelivered scheduled request');
}finally{await browser.close();}
