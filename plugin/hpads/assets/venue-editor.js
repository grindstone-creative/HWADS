(()=>{'use strict';
function init(){
 let panel=document.getElementById('hwads-venue-dashboard');const form=document.getElementById('post'),details=document.getElementById('hwads-details');
 if(!panel)return;
 let layout=panel.parentElement;if(form&&details){layout=document.createElement('div');layout.className='hwads-venue-editor-layout';form.before(layout);layout.append(panel,form);}
 const title=document.getElementById('titlediv');if(title&&form&&details)details.querySelector('.inside')?.prepend(title);
 const heading=layout.parentElement.querySelector('h1.wp-heading-inline');if(heading)heading.textContent='Venue Dashboard';
 const identity=new URL(location.href).searchParams,post=form?form.querySelector('[name=post_ID]').value:identity.get('edit');let controller,sequence=0,uploading=false;
 const status=document.createElement('p');status.className='hwads-venue-panel-status';status.setAttribute('role','status');(form?layout:panel).before(status);
 function enhance(){
  const h=panel.querySelector('h1');if(h)h.hidden=true;
  panel.querySelector('.nav-tab-wrapper')?.setAttribute('aria-label','Venue sections');
  panel.querySelectorAll('.nav-tab').forEach(a=>{if(a.classList.contains('nav-tab-active'))a.setAttribute('aria-current','page');});
  // Newly loaded Add Template links retain the venue as their initial owner.
  panel.querySelectorAll('a[href*="post-new.php"]').forEach(a=>{const u=new URL(a.href);if(u.searchParams.get('post_type')==='hpads_template'){u.searchParams.set('owner','company:'+panel.dataset.venue);u.searchParams.set('template_venue',panel.dataset.venue);a.href=u.href;}});
  document.dispatchEvent(new CustomEvent('hwads:venue-panel'));
 }
 function local(url){return url.origin===location.origin&&url.pathname===location.pathname&&(form?url.searchParams.get('post')===post&&url.searchParams.get('action')==='edit':url.searchParams.get('page')==='hpads-company'&&url.searchParams.get('edit')===post);}
 async function load(url,push=true){
  if(uploading){status.textContent='Wait for the template upload to finish before changing sections.';return;}
  controller?.abort();controller=new AbortController();const current=++sequence;
  panel.setAttribute('aria-busy','true');status.classList.remove('screen-reader-text');status.textContent='Loading section…';
  try{
   const response=await fetch(url,{credentials:'same-origin',signal:controller.signal});
   if(!response.ok)throw Error('Could not load this section. Your venue edits are still here. Try again.');
   const doc=new DOMParser().parseFromString(await response.text(),'text/html'),next=doc.getElementById('hwads-venue-dashboard');
   if(!next||next.dataset.venue!==panel.dataset.venue)throw Error('This section is unavailable. Your venue edits are still here.');
   if(current!==sequence)return;
   panel.replaceWith(next);panel=next;enhance();
   if(push)history.pushState({hwadsVenue:post},'',url);
   status.classList.add('screen-reader-text');status.textContent='Section loaded.';
  }catch(error){if(error.name!=='AbortError'&&current===sequence)status.textContent=error.message;}
  finally{if(current===sequence)panel.removeAttribute('aria-busy');}
 }
 layout.addEventListener('click',event=>{
  const a=event.target.closest('a');if(!a||!panel.contains(a)||event.button!==0||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey||a.target==='_blank')return;
  if(details&&a.getAttribute('href')==='#hwads-details'){event.preventDefault();details.classList.remove('closed');details.scrollIntoView({behavior:'smooth',block:'start'});details.querySelector('input')?.focus({preventScroll:true});return;}
  const url=new URL(a.href);if(!local(url))return;event.preventDefault();load(url);
 });
 layout.addEventListener('submit',event=>{
  const target=event.target;if(!panel.contains(target)||target.method.toLowerCase()!=='get')return;
  const url=new URL(target.getAttribute('action')||location.href,location.href);url.search=new URLSearchParams(new FormData(target,event.submitter)).toString();
  if(!local(url))return;event.preventDefault();load(url);
 });
 document.addEventListener('hwads:template-upload',event=>{uploading=event.detail.uploading;});
 history.replaceState({...history.state,hwadsVenue:post},'',location.href);
 window.addEventListener('popstate',()=>{const url=new URL(location.href);if(local(url))load(url,false);});
 enhance();
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
