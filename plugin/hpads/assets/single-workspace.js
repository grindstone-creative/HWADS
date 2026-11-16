/* Adapt one native ad/template form to the shared visual workspace; native fields remain authoritative. */
(()=>{'use strict';
const mount=document.getElementById('hwads-workspace-mount');if(!mount?.dataset.single)return;
const form=document.getElementById('post');if(!form)return;
const card=JSON.parse(mount.dataset.card||'{}'),editor=document.createElement('div'),row=document.createElement('div');
editor.id='hpads-playlist';editor.className='hwads-single-proxy';editor.dataset.defaultDuration=String(card.default_duration||10);editor.dataset.activeLimit=String(card.active_limit||15);editor.dataset.bound=JSON.stringify({[card.id||0]:card});row.className='hpads-item';row.dataset.card=JSON.stringify(card);row.dataset.visuals=JSON.stringify(card.overlays||[]);row.dataset.qrLayout=JSON.stringify(card.qr_layout||{size:15,x:82,y:70});row.dataset.thumbnailTime=card.thumbnail_time??1;
const select=document.createElement('select');select.className='creative';select.add(new Option(card.title||'Ad',String(card.id||0)));row.append(select);
const pairs=[];
for(const [cls,name,type] of [['placement-title',null,'text'],['duration','duration','number'],['active','active','checkbox'],['link-url','link_url','url'],['start','start','datetime-local'],['end','end','datetime-local'],['qr-enabled','qr_enabled','checkbox'],['transition-in','transition_in','select'],['transition-out','transition_out','select']]){
 const original=name?form.querySelector('[name="hwads['+name+']"]'):document.getElementById('title');
 const proxy=original?original.cloneNode(true):document.createElement(type==='select'?'select':'input');proxy.removeAttribute('id');proxy.removeAttribute('name');proxy.className=cls;if(type!=='select')proxy.type=type;if(original){proxy.value=original.value;proxy.checked=original.checked;}
 if(type==='select'&&!proxy.options.length)proxy.add(new Option('None','none'));
 if(cls==='duration'){proxy.dataset.inherited=String(!original?.value);proxy.value=original?.value||editor.dataset.defaultDuration;}
 row.append(proxy);if(original){pairs.push({original,proxy,cls});if(name)original.closest('p')?.setAttribute('hidden','');}
}
const edit=document.createElement('a');edit.className='button';edit.href='#postdivrich';row.append(edit);editor.append(row);form.append(editor);
const hidden={};for(const key of ['overlays','qr_layout','thumbnail_time']){const input=document.createElement('input');input.type='hidden';input.name='hwads['+key+']';form.append(input);hidden[key]=input;}
let syncing=false;
function sync(){if(syncing)return;syncing=true;for(const {original,proxy,cls} of pairs){if(cls==='placement-title')proxy.value=original.value;else if(original.type==='checkbox')original.checked=proxy.checked;else original.value=cls==='duration'&&proxy.dataset.inherited==='true'?'':proxy.value;}hidden.overlays.value=row.dataset.visuals||'[]';hidden.qr_layout.value=row.dataset.qrLayout||'{}';hidden.thumbnail_time.value=row.dataset.thumbnailTime||1;syncing=false;}
form.addEventListener('input',sync);form.addEventListener('change',sync);form.addEventListener('submit',sync);
const title=document.getElementById('title');title?.addEventListener('input',()=>{row.querySelector('.placement-title').value=title.value;card.title=title.value;row.dataset.card=JSON.stringify(card);select.options[0].textContent=title.value||'Ad';document.dispatchEvent(new Event('hwads:title-updated'));});
document.addEventListener('hwads:media-selected',event=>{const a=event.detail;Object.assign(card,{preview:a.url,video:a.type==='video',width:a.width||a.sizes?.full?.width||1920,height:a.height||a.sizes?.full?.height||1080});row.dataset.card=JSON.stringify(card);row.dataset.thumbnailTime=1;sync();document.dispatchEvent(new Event('hwads:workspace-refresh'));});
sync();
})();
