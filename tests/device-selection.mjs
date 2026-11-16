import fs from 'node:fs';import vm from 'node:vm';import assert from 'node:assert/strict';
const context={window:{},Math,Date,Map};vm.createContext(context);vm.runInContext(fs.readFileSync(new URL('../plugin/hpads/assets/device-selection.js',import.meta.url),'utf8'),context);const m=context.window.HWADSDeviceSelection;
const item=(id,seconds=10)=>({id,revision:1,seconds});
let data={mixed:false,primary:[item(1)],house:[item(2)],house_rotation:false};assert.equal(m.eligible(data).length,1);data.house_rotation=true;assert.equal(m.eligible(data).length,2);data.primary[0].end='2000-01-01';assert.equal(m.eligible(data)[0].id,2);
data={mixed:true,groups:[{id:1,weight:75,items:[item(1),item(2)]},{id:2,weight:25,items:[item(3,30)]}],fallback:[item(4)]};
let totals={1:0,2:0};for(let i=0;i<2000;i++){const x=m.select(m.eligible(data));totals[x.mix_group]+=x.seconds;m.charge(x.seconds);}assert.ok(Math.abs(totals[1]/(totals[1]+totals[2])-.75)<.01);
data.groups[0].items=[];data.groups[1].items=[];assert.equal(m.select(m.eligible(data)).id,4);data.fallback[0].end='2000-01-01';assert.equal(m.eligible(data).length,0);console.log('PASS configured playback eligibility, fallback, and duration-weighted share');
