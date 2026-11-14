(()=>{
 const refresh=document.getElementById('hwads-refresh-preview');if(refresh)refresh.addEventListener('click',()=>{const frame=document.getElementById('hwads-inline-playlist');frame.src=frame.src;});
 const toggle=document.getElementById('hwads-toggle-content');if(toggle)toggle.addEventListener('click',()=>{const open=document.body.classList.toggle('hwads-show-web-editor');toggle.setAttribute('aria-expanded',String(open));toggle.textContent=open?'Hide Web Content':'Edit Web Content';});
 const mediaButton=document.getElementById('hwads-choose-media');
 if(mediaButton)mediaButton.addEventListener('click',()=>{
  const frame=wp.media({title:'Choose ad media',button:{text:'Use Media'},multiple:false,library:{type:['image','video']}});
  frame.on('select',()=>{const a=frame.state().get('selection').first().toJSON();document.getElementById('hwads-attachment').value=a.id;const box=document.getElementById('hwads-native-preview'),el=document.createElement(a.type==='video'?'video':'img');el.src=a.url;if(a.type==='video'){el.controls=true;el.preload='metadata';}else el.alt='';box.replaceChildren(el);});frame.open();
 });
 const owner=document.querySelector('select[name="hwads[owner]"]'),playlist=document.querySelector('select[name="hwads[rotation_id]"]'),status=document.getElementById('hwads-owner-status');let pending=false;
 if(owner && playlist)owner.addEventListener('change',async()=>{pending=true;playlist.disabled=true;const old=playlist.value;try{const response=await fetch(HPADSAdmin.ajaxUrl,{method:'POST',credentials:'same-origin',body:new URLSearchParams({action:'hpads_owner_playlists',nonce:HPADSAdmin.ownerNonce,owner:owner.value})});const result=await response.json();if(!response.ok || !result.success)throw Error('Playlist lookup failed.');playlist.replaceChildren(...result.data.playlists.map(x=>new Option(x.text,String(x.value))));playlist.value=Array.from(playlist.options).some(x=>x.value===old)?old:'0';playlist.disabled=false;status.textContent='Playlists updated.';}catch(e){status.textContent=e.message;}finally{pending=false;}});
 const form=document.getElementById('post');if(form)form.addEventListener('submit',e=>{if(pending || (playlist && playlist.disabled)){e.preventDefault();status.textContent='Wait for the playlist lookup before saving.';}});
 const playlistScreen=window.HWADSNative && HWADSNative.postType==='hpads_playlist';
 const publish=document.getElementById('publish');if(publish && !playlistScreen){const label=window.HWADSNative && HWADSNative.saveLabel || 'Save Draft';if(publish.tagName==='INPUT')publish.value=label;else publish.textContent=label;}
 document.querySelectorAll('#visibility,#post-status-select,#misc-publishing-actions .misc-pub-curtime').forEach(x=>x.hidden=true);if(!playlistScreen){const minor=document.getElementById('minor-publishing-actions');if(minor)minor.hidden=true;}else{const preview=document.getElementById('preview-action');if(preview)preview.hidden=true;}
})();
