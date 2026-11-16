import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import {randomUUID} from 'node:crypto';
const code=fs.readFileSync(new URL('../player/browser/player.js',import.meta.url),'utf8').replace('setInterval(report,15000);report();run();','globalThis.test={playback,setCurrent(){current={};}};');
async function scenario({video=false,fail=false,interrupt=false,expiry=false,grant=true,stall=false}={}){
 let clock=0,keydown;const events=[],listeners={};let mediaStart=0,ended=false;
 const media={paused:true,ended:false,get currentTime(){return video?(stall?Math.min(.4,(clock-mediaStart)/1000):(clock-mediaStart)/1000):0;},async play(){mediaStart=clock;this.paused=false;listeners.playing?.();},pause(){this.paused=true;},addEventListener(n,fn){listeners[n]=fn;},removeEventListener(n){delete listeners[n];}};
 function tick(ms){clock+=ms;if(video&&!ended&&clock>=1000&&!stall){ended=true;media.paused=true;media.ended=true;listeners[fail?'error':'ended']?.();}if(interrupt&&clock>=400)keydown({key:'ArrowRight',preventDefault(){}});}
 class ClockDate extends Date{static now(){return 1800000000000+clock;}}
 const sandbox={document:{getElementById(){return {querySelectorAll(){return[];}};},addEventListener(n,f){keydown=f;}},window:{addEventListener(){}},Date:ClockDate,performance:{now:()=>clock},Promise,AbortSignal,crypto:{randomUUID},setTimeout(fn,ms){tick(ms);queueMicrotask(fn);return 1;},clearTimeout(){},fetch:async(url,opts)=>{if(url==='events')events.push(JSON.parse(opts.body));return{ok:true};}};
 vm.createContext(sandbox);vm.runInContext(code,sandbox);sandbox.test.setCurrent();
 const item={placement_id:1,version_id:2,duration:1,analytics_grant:grant?'signed':'',end_utc:expiry?new Date(1800000000400).toISOString():null};
 let error;try{await sandbox.test.playback({video,media,item});}catch(e){error=e;}
 await new Promise(resolve=>setImmediate(resolve));
 return{events,error,clock,media};
}
let r=await scenario();assert.equal(r.events[0].elapsed_ms,0);assert.equal(r.events[0].outcome,'playing');assert.equal(r.events.at(-1).outcome,'completed');assert.equal(r.events.at(-1).elapsed_ms,1000);assert.equal(new Set(r.events.map(x=>x.play_id)).size,1);console.log('PASS: full image produces one start and one cumulative completion.');
r=await scenario({interrupt:true});assert.equal(r.events.at(-1).outcome,'interrupted');assert.equal(r.events.at(-1).elapsed_ms,400);console.log('PASS: manual skip keeps partial time without completion.');
r=await scenario({expiry:true});assert.equal(r.events.at(-1).outcome,'interrupted');console.log('PASS: schedule cutoff does not become completion.');
r=await scenario({video:true});assert.equal(r.events.at(-1).outcome,'completed');assert.equal(r.events.at(-1).elapsed_ms,1000);console.log('PASS: natural video EOF includes final progress.');
r=await scenario({video:true,fail:true});assert.equal(r.events.at(-1).outcome,'error');console.log('PASS: video error never counts as completion.');
r=await scenario({video:true,stall:true});assert(r.error);assert.equal(r.events.at(-1).outcome,'error');assert.equal(r.events.at(-1).elapsed_ms,400);assert(r.events.length>2);console.log('PASS: stalled video excludes frozen time and checkpoints cumulative progress.');
r=await scenario({grant:false});assert.equal(r.events.length,0);console.log('PASS: unsigned local playback and placeholders do not generate events.');
