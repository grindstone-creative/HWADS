/* Expected playback preview: independent time-deficit mixer, never device telemetry. */
(()=>{'use strict';
let scope='',balances={},bags={},known={},last='',pending=null,cooldown={};
const key=x=>`${x.id}:${x.revision}`;
const shuffle=rows=>{for(let i=rows.length-1;i>0;i--){const j=Math.floor(Math.random()*(i+1));[rows[i],rows[j]]=[rows[j],rows[i]];}return rows;};
window.HWADSDeviceSelection={eligible(data,now=Date.now()){
 const active=x=>(!x.start||Date.parse(x.start)<=now)&&(!x.end||Date.parse(x.end)>now);
 if(!data.mixed){const p=data.primary.filter(active),h=data.house.filter(active);return p.length?(data.house_rotation?p.concat(h):p):h;}
 const rows=data.groups.flatMap(g=>g.items.filter(active).map(x=>({...x,mix_group:String(g.id),mix_weight:g.weight})));
 return rows.concat(data.fallback.filter(active).map(x=>({...x,mix_group:'fallback',mix_weight:100,mix_fallback:true})));
},charge(seconds,failed=false){
 if(!pending)return;const total=Object.values(pending.weights).reduce((a,b)=>a+b,0)||1;
 for(const [g,w] of Object.entries(pending.weights))balances[g]=Math.max(-86400,Math.min(86400,(balances[g]||0)+seconds*w/total-(g===pending.group?seconds:0)));
 if(failed)cooldown[pending.key]=Date.now()+60000;pending=null;
},select(rows){
 rows=rows.filter(x=>(cooldown[key(x)]||0)<=Date.now());const normal=rows.filter(x=>!x.mix_fallback);rows=normal.length?normal:rows.filter(x=>x.mix_fallback);if(!rows.length)return null;
 const groups={},weights={};for(const x of rows){(groups[x.mix_group]||=[]).push(x);weights[x.mix_group]=x.mix_weight;}
 const nextScope=JSON.stringify(Object.keys(weights).sort().map(g=>[g,weights[g]]));if(scope!==nextScope){scope=nextScope;balances={};bags={};known={};}
 const candidates=Object.keys(groups).filter(g=>groups[g].some(x=>key(x)!==last));if(!candidates.length)candidates.push(...Object.keys(groups));
 const max=Math.max(...candidates.map(g=>balances[g]||0)),ties=candidates.filter(g=>Math.abs((balances[g]||0)-max)<1e-8);
 let draw=Math.random()*ties.reduce((n,g)=>n+weights[g],0),group=ties[ties.length-1];for(const g of ties){draw-=weights[g];if(draw<0){group=g;break;}}
 const available=new Map(groups[group].map(x=>[key(x),x]));let bag=(bags[group]||[]).filter(k=>available.has(k));bag.push(...shuffle([...available.keys()].filter(k=>!(known[group]||[]).includes(k))));if(!bag.length)bag=shuffle([...available.keys()]);
 if(bag[0]===last&&bag.length>1)[bag[0],bag[1]]=[bag[1],bag[0]];
 const selected=bag.shift();bags[group]=bag;known[group]=[...available.keys()];last=selected;pending={group,weights,key:selected};return available.get(selected);
}};
})();
