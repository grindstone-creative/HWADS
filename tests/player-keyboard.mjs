import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
let source=fs.readFileSync(new URL('../player/browser/player.js',import.meta.url),'utf8');
source=source.replace('setInterval(report,15000);report();run();', 'globalThis.test={nextIndex,playback,setCurrent(){current={};},get navigation(){return navigation;}};');
let keydown;
const state={document:{getElementById(){return {querySelectorAll(){return [];}};},addEventListener(type,fn){keydown=fn;}},Date,performance,Promise,setTimeout,clearTimeout,fetch:async()=>({}),window:{addEventListener(){}}};
vm.createContext(state);vm.runInContext(source,state);const t=state.test;
function key(key,extra={}){let prevented=false;keydown({key,preventDefault(){prevented=true;},...extra});return prevented;}
assert.equal(key('ArrowRight'),false);t.setCurrent();
assert.equal(t.nextIndex(3),0);assert.equal(key('ArrowLeft'),true);assert.equal(t.nextIndex(3),2); // first wraps backwards
assert.equal(key('ArrowRight'),true);assert.equal(t.nextIndex(3),0);assert.equal(t.nextIndex(3),1); // auto resumes
assert.equal(key('ArrowRight',{repeat:true}),false);assert.equal(key('ArrowLeft',{ctrlKey:true}),false);assert.equal(key('ArrowRight',{target:{tagName:'INPUT'}}),false);assert.equal(key('x'),false);
assert.equal(key('ArrowLeft'),true);assert.equal(t.nextIndex(1),0);assert.equal(t.nextIndex(1),0);
let paused=false;const media={play:async()=>{},pause(){paused=true;},addEventListener(){},removeEventListener(){}};
key('ArrowRight');await t.playback({video:true,media,item:{duration:999}});assert.equal(paused,true);
t.nextIndex(3);key('ArrowLeft');await t.playback({video:false,media,item:{duration:999}});
console.log('PASS: left/right navigation, both wrap directions, automatic resumption, single item, ignored repeats/modifiers/inputs, and interruption of video/image playback.');
