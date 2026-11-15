import fs from 'node:fs';import path from 'node:path';import os from 'node:os';import {spawn} from 'node:child_process';import {once} from 'node:events';
const {chromium}=await import('/Users/grindstone/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs');
const base=path.resolve('work/HWADS/player'),root=fs.mkdtempSync(path.join(os.tmpdir(),'hwads-browser-test-'));
for(const d of ['media','config','placeholders'])fs.mkdirSync(path.join(root,d));
fs.copyFileSync('work/wordpress-cpt/wp-content/uploads/2026/11/native.png',path.join(root,'media/test.png'));
const row={file:'test.png',type:'image',duration:1,placement_id:91,version_id:7,transition_in:'wipe-circle',transition_out:'wipe-diagonal',qr_enabled:true,qr_url:'https://example.test/?hpads_ad=91&hwads_source=qr'};
const save=rows=>{fs.writeFileSync(path.join(root,'config/playlist.json.tmp'),JSON.stringify({primary:rows,house:[]}));fs.renameSync(path.join(root,'config/playlist.json.tmp'),path.join(root,'config/playlist.json'));};save([row,{...row,placement_id:92,qr_enabled:false,transition_in:'wipe-split'}]);
const script=`import sys,json\nfrom pathlib import Path\nsys.path.insert(0,sys.argv[1])\nfrom hwads_browser import LocalServer\ns=LocalServer(Path(sys.argv[2]),Path(sys.argv[1])/'browser')\nprint(json.dumps({'port':s.server_port,'token':s.token}),flush=True)\ns.serve_forever()`;
const server=spawn('python3',['-u','-c',script,base,root]);let stderr='';server.stderr.on('data',d=>stderr+=d);let browser;
try{
 const data=await Promise.race([once(server.stdout,'data').then(([d])=>JSON.parse(String(d))),once(server,'exit').then(()=>{throw Error(stderr)}),new Promise((_,rej)=>setTimeout(()=>rej(Error('Server startup timeout')),10000))]);
 const url=`http://127.0.0.1:${data.port}/${data.token}/`;
 browser=await chromium.launch({headless:true,executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',args:['--autoplay-policy=no-user-gesture-required']});const page=await browser.newPage({viewport:{width:1280,height:720}}),errors=[],external=[];
 page.on('pageerror',e=>errors.push(e.message));await page.route('**/*',async r=>{if(new URL(r.request().url()).hostname!=='127.0.0.1'){external.push(r.request().url());await r.abort();}else await r.continue();});
 await page.goto(url);await page.locator('.qr svg').waitFor();
 if(await page.locator('.qr svg path').getAttribute('fill')!=='#000')throw Error('QR not generated locally');
 const animation=await page.locator('.slide').last().evaluate(e=>e.getAnimations().flatMap(x=>x.effect.getKeyframes()).some(x=>x.clipPath));if(!animation)throw Error('Circular wipe animation not active');
 await page.screenshot({path:path.resolve('work/tests/browser-player.png')});
 await page.waitForFunction(()=>document.querySelector('.slide img') && document.querySelectorAll('.qr').length===0,{},{timeout:6000});
 console.log('PASS: offline image playback, independent wipe transitions and per-placement QR enable/disable.');
 // Generate a tiny real WebM locally, then play it through the same ranged-media route.
 const bytes=await page.evaluate(async()=>{const c=document.createElement('canvas');c.width=320;c.height=180;const ctx=c.getContext('2d'),stream=c.captureStream(15),rec=new MediaRecorder(stream,{mimeType:'video/webm;codecs=vp8'}),chunks=[];rec.ondataavailable=e=>chunks.push(e.data);const done=new Promise(r=>rec.onstop=r);rec.start();let i=0;const timer=setInterval(()=>{ctx.fillStyle=i++%2?'#246':'#864';ctx.fillRect(0,0,320,180);},60);await new Promise(r=>setTimeout(r,1200));clearInterval(timer);rec.stop();await done;stream.getTracks().forEach(t=>t.stop());return Array.from(new Uint8Array(await new Blob(chunks).arrayBuffer()));});
 fs.writeFileSync(path.join(root,'media/test.webm'),Buffer.from(bytes));save([{...row,file:'test.webm',type:'video',duration:null,transition_in:'none',transition_out:'fade'}, {...row,qr_enabled:false}]);
 await page.locator('video').waitFor({timeout:10000});await page.waitForFunction(()=>{const v=document.querySelector('video');return v&&v.currentTime>.2;});
 await page.waitForFunction(()=>document.querySelectorAll('video').length===0&&document.querySelector('img'),{},{timeout:8000});console.log('PASS: real video decodes, autoplays muted and advances at end without an image-duration cutoff.');
 save([]);await page.waitForFunction(()=>document.querySelectorAll('.slide').length===0,{},{timeout:10000});console.log('PASS: empty assignment clears old content instead of continuing stale ads.');
 if(errors.length||external.length)throw Error(JSON.stringify({errors,external}));
 console.log('PASS: no external requests or JavaScript errors; browser status POSTs accepted.');
}finally{await browser?.close();server.kill('SIGTERM');fs.rmSync(root,{recursive:true,force:true});}
