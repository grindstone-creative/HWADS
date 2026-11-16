(()=>{'use strict';
const stage=document.getElementById('hwads-display');let data=JSON.parse(document.getElementById('hwads-embed-data').textContent),index=-1,timer,run=0,etag='',observer;
const eligible=()=>{if(data.device_preview)return HWADSDeviceSelection.eligible(data);const now=Date.now();return data.items.filter(x=>(!x.start||Date.parse(x.start)<=now)&&(!x.end||Date.parse(x.end)>now));};
function reportRender(item,media,link,token){
 if(data.preview||!data.metrics)return;
 const cfg=data.metrics;let pending,done=false;
 let source='unknown';try{if(document.referrer)source=new URL(document.referrer).hostname===cfg.host?'local':'remote';}catch{}
 const watch=new IntersectionObserver(entries=>{
  clearTimeout(pending);
  if(!done&&entries[0].intersectionRatio>=.5&&document.visibilityState==='visible')pending=setTimeout(()=>{
   if(token!==run||document.visibilityState!=='visible'||!link.isConnected)return;
   if(item.video?(media.readyState<2||media.paused):(media.naturalWidth===0))return;
   done=true;watch.disconnect();document.removeEventListener('visibilitychange',visibility);
   const event={...cfg,placement_id:item.id,revision:item.revision,event_id:crypto.randomUUID(),source};delete event.endpoint;delete event.host;
   fetch(cfg.endpoint,{method:'POST',credentials:'omit',headers:{'Content-Type':'application/json'},body:JSON.stringify(event),keepalive:true}).catch(()=>{});
  },1000);
 },{threshold:[0,.5]});
 const visibility=()=>{clearTimeout(pending);watch.unobserve(link);if(token===run&&!done)watch.observe(link);else{watch.disconnect();document.removeEventListener('visibilitychange',visibility);}};
 document.addEventListener('visibilitychange',visibility);watch.observe(link);
 setTimeout(()=>{watch.disconnect();clearTimeout(pending);document.removeEventListener('visibilitychange',visibility);},Math.min(86400000,Math.max(60000,Number(item.seconds||300)*1000+30000)));
}
function animate(el,name='none',out){if(!el||name==='none'||!el.animate)return Promise.resolve();let a={opacity:0},b={opacity:1};if(name.startsWith('slide-')){const d=name.slice(6),axis=['left','right'].includes(d)?'X':'Y',sign=['left','up'].includes(d)?-1:1;a={transform:`translate${axis}(${sign*100}%)`};b={transform:`translate${axis}(0)`};}else if(name==='zoom'){a={opacity:0,transform:'scale(.8)'};b={opacity:1,transform:'scale(1)'};}else if(name.startsWith('wipe-')){const shapes={'wipe-left':'inset(0 0 0 100%)','wipe-right':'inset(0 100% 0 0)','wipe-up':'inset(100% 0 0 0)','wipe-down':'inset(0 0 100% 0)','wipe-diagonal':'polygon(0 0,0 0,0 0)','wipe-circle':'circle(0% at 50% 50%)','wipe-split':'inset(0 50% 0 50%)'};a={clipPath:shapes[name]||shapes['wipe-right']};b={clipPath:name==='wipe-circle'?'circle(75% at 50% 50%)':name==='wipe-diagonal'?'polygon(0 0,200% 0,0 200%)':'inset(0 0 0 0)'};}return el.animate(out?[b,a]:[a,b],{duration:500,easing:'ease-in-out',fill:'forwards'}).finished.catch(()=>{});}
let previewStarted=0,previewVideo=null;
async function show(step=1){if(previewStarted){HWADSDeviceSelection.charge(previewVideo?previewVideo.currentTime:(Date.now()-previewStarted)/1000,!!previewVideo?.error);previewStarted=0;}const token=++run;clearTimeout(timer);observer?.disconnect();const list=eligible();if(!list.length){stage.querySelectorAll('video').forEach(v=>v.pause());stage.replaceChildren();timer=setTimeout(()=>show(),30000);return;}
index=(index+step+list.length)%list.length;const item=data.device_preview&&data.mixed?HWADSDeviceSelection.select(list):list[index];if(!item){timer=setTimeout(()=>show(),3000);return;}const old=stage.lastElementChild,link=document.createElement('a'),media=document.createElement(item.video?'video':'img');link.href=item.href||'#';link.target='_blank';link.rel='noopener noreferrer';link.dataset.exit=item.exit||'none';link.style.position='absolute';link.style.visibility='hidden';
if(data.preview)link.addEventListener('click',e=>e.preventDefault());
if(item.video){media.muted=true;media.playsInline=true;media.preload='auto';}else media.alt=item.title||'';
let mediaFailed=false;const loaded=new Promise(resolve=>{let t=setTimeout(resolve,15000);media.addEventListener(item.video?'loadeddata':'load',()=>{clearTimeout(t);resolve();},{once:true});media.addEventListener('error',()=>{mediaFailed=true;clearTimeout(t);resolve();},{once:true});});media.src=item.url;link.append(media);await loaded;if(token!==run){media.removeAttribute('src');return;}
if(mediaFailed){if(data.device_preview&&data.mixed)HWADSDeviceSelection.charge(0,true);timer=setTimeout(()=>show(),3000);return;}
old?.querySelector('video')?.pause();stage.append(link);const fit=()=>{const w=Number(item.width)||media.videoWidth||media.naturalWidth||16,h=Number(item.height)||media.videoHeight||media.naturalHeight||9,r=Math.min(stage.clientWidth/w,stage.clientHeight/h);link.style.width=(w*r)+'px';link.style.height=(h*r)+'px';link.style.left=(stage.clientWidth-w*r)/2+'px';link.style.top=(stage.clientHeight-h*r)/2+'px';};fit();observer=new ResizeObserver(fit);observer.observe(stage);HWADSOverlays.render(link,item);try{await HWADSOverlays.ready(link);}catch{link.remove();if(token===run)timer=setTimeout(()=>show(),3000);return;}if(token!==run){link.remove();return;}link.style.visibility='visible';
if(old&&item.entrance==='fade'&&old.dataset.exit==='fade'){old.style.zIndex='2';await animate(old,'fade',true);}else await Promise.all([animate(link,item.entrance,false),old?animate(old,old.dataset.exit,true):Promise.resolve()]);old?.remove();if(token!==run)return;
if(data.device_preview){previewStarted=Date.now();previewVideo=item.video?media:null;}reportRender(item,media,link,token);
const expiry=item.end?Date.parse(item.end)-Date.now():Infinity;
if(item.video){media.onended=()=>{if(token===run)show();};media.onerror=()=>{if(token===run)timer=setTimeout(()=>show(),3000);};media.play().catch(()=>{if(token===run)timer=setTimeout(()=>show(),3000);});if(Number.isFinite(expiry))timer=setTimeout(()=>show(),Math.max(1,expiry));}
else timer=setTimeout(()=>show(),Math.max(1,Math.min(Math.max(1,Number(item.seconds)||10)*1000,expiry)));
}
document.getElementById('next')?.addEventListener('click',()=>show(1));document.getElementById('previous')?.addEventListener('click',()=>show(-1));
if(!data.preview||data.device_preview)setInterval(async()=>{if(document.visibilityState==='hidden')return;try{const url=new URL(location.href);url.searchParams.set('format','json');const response=await fetch(url,{credentials:'omit',cache:'no-cache',headers:etag?{'If-None-Match':etag}:{}});if(response.status===304)return;if(response.status===404){data.items=[];data.primary=[];data.house=[];data.groups=[];data.fallback=[];index=-1;show();return;}if(!response.ok)return;const next=await response.json();etag=response.headers.get('ETag')||'';if(JSON.stringify(next)!==JSON.stringify(data)){data=next;index=-1;show();}}catch{}},300000);
show();
})();
