/* Shared by the WordPress editor, embeds and offline Chromium player. No design library. */
(()=>{'use strict';
const clamp=(v,min,max,fallback)=>Number.isFinite(Number(v))?Math.max(min,Math.min(max,Number(v))):fallback;
function render(host,item,{editable=false,onSelect}={}){
 host._hwadsOverlayObserver?.disconnect();host.querySelector('.hwads-overlays')?.remove();host.classList.add('hwads-surface');
 const layer=document.createElement('div');layer.className='hwads-overlays';host.append(layer);
 function box(data,id){const el=document.createElement('div');el.className='hwads-overlay';el.dataset.overlay=id;el.style.left=clamp(data.x,0,95,5)+'%';el.style.top=clamp(data.y,0,95,5)+'%';el.style.width=clamp(data.width??data.size,5,100,30)+'%';
 if(editable){el.tabIndex=0;el.setAttribute('role','button');el.setAttribute('aria-label',id==='qr'?'Select QR code':'Select overlay '+(Number(id)+1));el.addEventListener('click',()=>onSelect?.(id));el.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();onSelect?.(id);}});}layer.append(el);return el;}
 (item.overlays||[]).slice(0,8).forEach((o,i)=>{const el=box(o,String(i));if(o.type==='image'){const img=document.createElement('img');img.alt='';img.draggable=false;if(/^(https?:|media\/)/.test(o.url||''))img.src=o.url;img.addEventListener('load',constrain,{once:true});el.append(img);}else{el.classList.add('hwads-text');el.textContent=String(o.text||'');el.style.fontFamily=['serif','monospace'].includes(o.font)?o.font:'sans-serif';el.style.fontSize=clamp(o.size,1,15,4)+'cqw';el.style.color=/^#[a-f\d]{6}$/i.test(o.color)?o.color:'#ffffff';el.style.backgroundColor=/^#[a-f\d]{6}$/i.test(o.background)?o.background:'transparent';el.style.textAlign=['center','right'].includes(o.align)?o.align:'left';}});
 if(item.qr_enabled && item.qr_url && window.HPADSQRCode){const q=item.qr_layout||{size:15,x:82,y:70},el=box({...q,width:q.size},'qr');el.classList.add('hwads-qr');try{const qr=HPADSQRCode.QrCode.encodeText(item.qr_url,HPADSQRCode.QrCode.Ecc.QUARTILE),n=qr.size+8,ns='http://www.w3.org/2000/svg',svg=document.createElementNS(ns,'svg');svg.setAttribute('viewBox',`0 0 ${n} ${n}`);const bg=document.createElementNS(ns,'rect');bg.setAttribute('width',n);bg.setAttribute('height',n);const background=/^#[a-f\d]{6}$/i.test(q.background)?q.background:'#ffffff',foreground=/^#[a-f\d]{6}$/i.test(q.foreground)?q.foreground:'#000000';bg.setAttribute('fill',background);el.style.backgroundColor=background;svg.append(bg);const path=document.createElementNS(ns,'path'),segments=[];for(let y=0;y<qr.size;y++)for(let x=0;x<qr.size;x++)if(qr.getModule(x,y))segments.push(`M${x+4},${y+4}h1v1h-1z`);path.setAttribute('d',segments.join(''));path.setAttribute('fill',foreground);svg.append(path);el.append(svg);}catch{el.remove();}}
 // Keep the whole element on canvas, including square QR codes on portrait/landscape slides.
 function constrain(){const h=host.clientHeight;if(!h)return;for(const el of layer.children){const top=parseFloat(el.style.top)||0;el.style.top=Math.min(top,Math.max(0,100-el.offsetHeight/h*100))+'%';}}
 host._hwadsOverlayObserver=new ResizeObserver(constrain);host._hwadsOverlayObserver.observe(host);requestAnimationFrame(constrain);return layer;
}
function ready(host,timeout=10000){return Promise.all(Array.from(host.querySelectorAll('.hwads-overlay img')).map(img=>new Promise((resolve,reject)=>{
 if(img.complete)return img.naturalWidth?resolve():reject(Error('Overlay image could not load'));
 const clean=()=>{clearTimeout(timer);img.removeEventListener('load',loaded);img.removeEventListener('error',failed);};
 const loaded=()=>{clean();resolve();},failed=()=>{clean();reject(Error('Overlay image could not load'));};
 const timer=setTimeout(()=>{clean();reject(Error('Overlay image load timeout'));},timeout);img.addEventListener('load',loaded,{once:true});img.addEventListener('error',failed,{once:true});
})));}
window.HWADSOverlays={render,clamp,ready};
})();
