(()=>{'use strict';
const stage=document.getElementById('stage');let selectionToken='',observedSeconds=0,selectionFailed=false,selectionRequest='';let current=null,index=0,lastAdvance=Date.now(),lastMediaTime=-1,lastVideoProgress=Date.now(),lastError='',playing=false,navigation=0;
// Arrow keys interrupt only playback, keeping automatic advancement and transitions intact.
document.addEventListener('keydown',event=>{
 if(!current || event.repeat || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey || event.target?.isContentEditable || ['INPUT','TEXTAREA','SELECT'].includes(event.target?.tagName))return;
 if(event.key!=='ArrowLeft' && event.key!=='ArrowRight')return;
 event.preventDefault();navigation=event.key==='ArrowLeft'?-1:1;
});
function nextIndex(count){if(navigation===-1)index-=2;navigation=0;index=((index%count)+count)%count;return index++;}
const wait=ms=>new Promise(r=>setTimeout(r,ms));
async function report(){try{await fetch('status',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({state:playing?'playing':lastError?'error':'idle',version_id:playing?current?.item.version_id:0,placement_id:playing?current?.item.placement_id:0,error:lastError,media_files:Array.from(stage.querySelectorAll('[data-file]')).map(x=>x.dataset.file)})});}catch{}}
function frames(name,out){let a={opacity:0},b={opacity:1};if(name?.startsWith('slide-')){const dir=name.slice(6),axis=['left','right'].includes(dir)?'X':'Y',sign=['left','up'].includes(dir)?-1:1;a={transform:`translate${axis}(${sign*100}%)`};b={transform:`translate${axis}(0)`};}else if(name==='zoom'){a={opacity:0,transform:'scale(.8)'};b={opacity:1,transform:'scale(1)'};}if(name && name.startsWith('wipe-')){const shapes={'wipe-left':'inset(0 0 0 100%)','wipe-right':'inset(0 100% 0 0)','wipe-up':'inset(100% 0 0 0)','wipe-down':'inset(0 0 100% 0)','wipe-diagonal':'polygon(0 0,0 0,0 0)','wipe-circle':'circle(0% at 50% 50%)','wipe-split':'inset(0 50% 0 50%)'};a={clipPath:shapes[name]||'inset(0 100% 0 0)'};b={clipPath:name==='wipe-circle'?'circle(75% at 50% 50%)':name==='wipe-diagonal'?'polygon(0 0,200% 0,0 200%)':'inset(0 0 0 0)'};}return out?[b,a]:[a,b];}
async function animate(el,name,out){if(!name||name==='none')return;try{await el.animate(frames(name,out),{duration:500,easing:'ease-in-out',fill:'forwards'}).finished;}catch{}}
function qr(url){if(!url||!window.HPADSQRCode)return null;try{const code=HPADSQRCode.QrCode.encodeText(url,HPADSQRCode.QrCode.Ecc.QUARTILE),n=code.size+8,ns='http://www.w3.org/2000/svg',svg=document.createElementNS(ns,'svg');svg.setAttribute('viewBox',`0 0 ${n} ${n}`);svg.setAttribute('shape-rendering','crispEdges');const bg=document.createElementNS(ns,'rect');bg.setAttribute('width',n);bg.setAttribute('height',n);bg.setAttribute('fill','#fff');svg.append(bg);const path=document.createElementNS(ns,'path'),segments=[];for(let y=0;y<code.size;y++)for(let x=0;x<code.size;x++)if(code.getModule(x,y))segments.push(`M${x+4},${y+4}h1v1h-1z`);path.setAttribute('d',segments.join(''));path.setAttribute('fill','#000');svg.append(path);const wrapper=document.createElement('div');wrapper.className='qr';wrapper.append(svg);return wrapper;}catch{lastError='QR generation failed';return null;}}
async function load(item){const slide=document.createElement('div');slide.className='slide';slide.dataset.file=item.file;const video=item.type==='video',media=document.createElement(video?'video':'img');if(video){media.autoplay=false;media.muted=true;media.playsInline=true;media.preload='auto';}else media.alt='';const surface=document.createElement('div');surface.className='hwads-player-surface';surface.append(media);slide.append(surface);const fit=()=>{const w=Number(item.width)||media.videoWidth||media.naturalWidth||16,h=Number(item.height)||media.videoHeight||media.naturalHeight||9,r=Math.min(stage.clientWidth/w,stage.clientHeight/h);surface.style.width=(w*r)+'px';surface.style.height=(h*r)+'px';};slide._fit=fit;HWADSOverlays.render(surface,item);surface.querySelectorAll('[data-overlay]').forEach(el=>{const o=item.overlays?.[Number(el.dataset.overlay)];if(o?.file)el.dataset.file=o.file;});let timer;
 const ready=new Promise((resolve,reject)=>{media.addEventListener(video?'loadeddata':'load',resolve,{once:true});media.addEventListener('error',()=>reject(Error('Media failed to decode')),{once:true});timer=setTimeout(()=>reject(Error('Media load timeout')),30000);});media.src=item.url;
 try{await Promise.all([ready,HWADSOverlays.ready(surface)]);slide._fit();return {slide,media,item,video};}catch(e){media.removeAttribute('src');if(video)media.load();throw e;}finally{clearTimeout(timer);}}
// Cumulative checkpoints are safe to retry and never block playback on network delivery.
let analyticsPending=0;
function observation(row){if(analyticsPending>=64)return;analyticsPending++;(async()=>{try{for(let attempt=0;attempt<3;attempt++){try{const r=await fetch('events',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(row),signal:AbortSignal.timeout(3000)});if(r.ok)return;}catch{}await wait(250*(attempt+1));}}finally{analyticsPending--;}})();}
async function playback(next){
 let ended=false,failed=false,elapsed=0,seq=0,started=false,startedAt=0,last=performance.now(),lastPos=Number(next.media.currentTime)||0,saved=last,outcome='interrupted';
 const enabled=!!(next.item.analytics_grant&&next.item.placement_id&&next.item.version_id),id=enabled?crypto.randomUUID():'';
 const save=result=>{if(enabled&&started)observation({play_id:id,placement_id:next.item.placement_id,version_id:next.item.version_id,grant:next.item.analytics_grant,seq:++seq,elapsed_ms:Math.floor(elapsed),started_at:startedAt,outcome:result,engine:'browser'});saved=performance.now();};
 const begin=()=>{if(!started){startedAt=Date.now()/1000;started=true;save('playing');}};
 const measure=(final=false)=>{const now=performance.now(),dt=Math.max(0,now-last);if(next.video){const pos=Number(next.media.currentTime)||0;if(started&&(!next.media.paused||final))elapsed+=Math.min(dt,Math.max(0,(pos-lastPos)*1000));lastPos=pos;}else if(started)elapsed+=dt;last=now;};
 const finish=()=>{measure(true);ended=true;outcome='completed';};const error=()=>{measure();failed=true;ended=true;outcome='error';};const videoStart=()=>{last=performance.now();lastPos=Number(next.media.currentTime)||0;begin();};
 next.media.addEventListener('ended',finish,{once:true});next.media.addEventListener('error',error,{once:true});next.media.addEventListener('playing',videoStart);
 try{
 if(next.video){await next.media.play();lastMediaTime=-1;lastVideoProgress=Date.now();}else begin();
 playing=true;report();const start=Date.now(),duration=Math.max(1,Number(next.item.duration)||7)*1000,end=next.item.end_utc?Date.parse(next.item.end_utc):Infinity;
 while(!ended&&!navigation){measure();if(Date.now()>=end)break;if(!next.video&&Date.now()-start>=duration){outcome='completed';break;}if(next.video){const t=next.media.currentTime;if(t!==lastMediaTime){lastMediaTime=t;lastVideoProgress=Date.now();}else if(Date.now()-lastVideoProgress>60000){outcome='error';throw Error('Video stalled');}}if(performance.now()-saved>=5000)save('playing');await wait(200);}
 }catch(e){outcome='error';throw e;}finally{measure();next.observedSeconds=elapsed/1000;save(failed?'error':outcome);if(next.video)next.media.pause();next.media.removeEventListener('ended',finish);next.media.removeEventListener('error',error);next.media.removeEventListener('playing',videoStart);}
}
async function run(){for(;;){let selectionIssued=false;try{const response=await fetch('playlist',{cache:'no-store'});if(!response.ok)throw Error('Local playlist unavailable');const rows=await response.json();if(!rows.length){current?.media.pause?.();stage.replaceChildren();current=null;playing=false;await report();await wait(5000);continue;}
 let item;
 if(rows.some(row=>row.mix_group)){
  selectionRequest ||= crypto.randomUUID();const direction=navigation===-1?-1:1;navigation=0;
  const response=await fetch('next',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({request_id:selectionRequest,previous:selectionToken,elapsed:observedSeconds,failed:selectionFailed,navigation:direction})});
  if(!response.ok)throw Error('Mix selection unavailable');const selected=await response.json();selectionRequest='';selectionToken=selected.token||'';observedSeconds=0;selectionFailed=false;item=selected.item;selectionIssued=!!selectionToken;
  if(!item){playing=false;await report();await wait(3000);continue;}
 }else{item=rows[nextIndex(rows.length)];selectionToken='';observedSeconds=0;selectionFailed=false;}
 const next=await load(item),old=current;
 // Ready media is inserted before transition begins, preventing loading flashes.
 if(old)old.slide.style.zIndex='2';next.slide.style.zIndex='1';stage.append(next.slide);current=next;lastError='';lastAdvance=Date.now();
 if(old && item.transition_in==='fade' && old.item.transition_out==='fade')await animate(old.slide,'fade',true);
 else await Promise.all([animate(next.slide,item.transition_in,false),old?animate(old.slide,old.item.transition_out,true):Promise.resolve()]);
 if(old){old.media.removeAttribute('src');if(old.video)old.media.load();old.slide.remove();}
 try{await playback(next);}finally{observedSeconds=next.observedSeconds||0;}
 }catch(error){if(selectionIssued)selectionFailed=true;playing=false;lastError=error.message;await report();await wait(3000);}}
}
window.addEventListener('resize',()=>stage.querySelectorAll('.slide').forEach(s=>s._fit?.()));
setInterval(report,15000);report();run();
})();
