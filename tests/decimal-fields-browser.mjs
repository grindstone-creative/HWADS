import fs from 'node:fs';import assert from 'node:assert/strict';
import {chromium} from '/Users/grindstone/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';
const source=fs.readFileSync(new URL('../plugin/hpads/assets/workspace.js',import.meta.url),'utf8');
const helper=source.slice(source.indexOf('function field('),source.indexOf('function sourceField('));
const browser=await chromium.launch({headless:true,executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'});
try{const page=await browser.newPage();await page.setContent('<form id="form"><div id="panel"></div><button>Save</button></form>');
await page.addScriptTag({content:`const panel=document.getElementById('panel');function el(tag,cls,text){const e=document.createElement(tag);if(text!=null)e.textContent=text;return e;}\n${helper}\nwindow.makeField=field;`});
const result=await page.evaluate(()=>{const tests=[['Left (%)',6.61,{min:0,max:92}],['Top (%)',17.83,{min:0,max:92}],['Size (%)',14.37,{min:8,max:35}],['Width (%)',29.19,{min:5,max:100}],['Thumbnail frame',.75,{min:0,max:86400}],['Text size',2.61,{min:1,max:15}],['Inherited seconds','',{min:1,max:3600}]];const out=tests.map(([label,value,limits])=>{const input=makeField(label,'number',value,()=>{},limits);return {label,value:input.value,valid:input.checkValidity(),step:input.step};});const form=document.getElementById('form');let saved=false;form.addEventListener('submit',e=>{e.preventDefault();saved=true;});form.requestSubmit();return {out,saved};});
assert(result.out.every(x=>x.valid&&x.step==='any'));assert.equal(result.out.at(-1).value,'');assert.equal(result.saved,true);console.log('PASS: real browser accepts decimal drag coordinates/sizes, thumbnail timestamps and font sizes; blank inherited duration stays empty; form submits.');
}finally{await browser.close();}
