(function ($) {
    'use strict';
    document.querySelectorAll('input[type="file"][name="media"]').forEach(file => {
        const max = Number(file.dataset.maxBytes);
        function validate() {
            const selected = file.files && file.files[0];
            const sizeLabel=(file.closest('p') && file.closest('p').querySelector('.hpads-upload-size')) || file.form.querySelector('.hpads-upload-size');
            if(sizeLabel) sizeLabel.textContent=selected ? ' Selected: '+selected.name+' · '+(selected.size/1048576).toFixed(2)+' MB ('+selected.size.toLocaleString()+' bytes). Limit: '+(max/1048576).toFixed(2)+' MB.' : '';
            file.setCustomValidity(selected && max > 0 && selected.size > max
                ? 'This file exceeds the server upload limit of ' + (max / 1048576).toFixed(1) + ' MB. Use a smaller file or ask your host to increase the upload limit.' : '');
        }
        file.addEventListener('change', validate);
        file.form.addEventListener('submit', event => {
            validate();
            if (!file.checkValidity()) { event.preventDefault(); file.reportValidity(); }
        });
    });
    const templatePicker=document.getElementById('hpads-template-picker');
    if(templatePicker) templatePicker.addEventListener('change',()=>{
        const url=new URL(window.location.href); url.searchParams.set('source_template_id',templatePicker.value); url.searchParams.delete('owner'); window.location.href=url.toString();
    });
    const mediaFile=document.getElementById('hpads-media-file');
    if(mediaFile) { let previewUrl; mediaFile.addEventListener('change',()=>{
        const file=mediaFile.files && mediaFile.files[0]; if(!file) return;
        if(previewUrl) URL.revokeObjectURL(previewUrl); previewUrl=URL.createObjectURL(file);
        const box=document.getElementById('hpads-media-preview'); const media=document.createElement(file.type.startsWith('video/')?'video':'img');
        if(media.tagName==='VIDEO') {media.controls=true;media.preload='metadata';} media.src=previewUrl; box.replaceChildren(media);
    }); }
    const ownerSelect=document.querySelector('.hpads-placement-form select[name="owner"]');
    if(ownerSelect) {
        const ownerForm=ownerSelect.form, playlists=ownerForm.querySelector('select[name="rotation_id"]');
        const ownerStatus=ownerForm.querySelector('.hpads-owner-status'), retry=ownerForm.querySelector('.hpads-refresh-owner');
        let pending=false, sequence=0;
        async function refreshPlaylists() {
            if(!playlists) { if(ownerStatus) ownerStatus.textContent='Owner selected. Your draft remains on this page.'; return; }
            const request=++sequence, previous=playlists.value;
            pending=true;playlists.disabled=true;if(retry) retry.hidden=true;
            if(ownerStatus) ownerStatus.textContent='Loading eligible playlists…';
            try {
                if(!window.HPADSAdmin) throw new Error('Editor settings unavailable');
                const body=new URLSearchParams({action:'hpads_owner_playlists',nonce:HPADSAdmin.ownerNonce,owner:ownerSelect.value});
                const response=await fetch(HPADSAdmin.ajaxUrl,{method:'POST',credentials:'same-origin',body});
                const result=await response.json();
                if(!response.ok || !result.success || !Array.isArray(result.data.playlists)) throw new Error('Playlist lookup failed');
                if(request!==sequence) return;
                playlists.replaceChildren();
                result.data.playlists.forEach(option=>{const el=document.createElement('option');el.value=String(option.value);el.textContent=option.text;playlists.append(el);});
                playlists.value=Array.from(playlists.options).some(o=>o.value===previous)?previous:'0';
                if(ownerStatus) ownerStatus.textContent=playlists.options.length>1?'Eligible playlists updated. Your other edits have been kept.':'No eligible playlists for this owner. Ask the account manager to check location eligibility.';
                playlists.disabled=false;
            } catch(error) {
                if(request!==sequence) return;
                playlists.replaceChildren(new Option('Playlist lookup unavailable — retry','0'));playlists.disabled=true;
                if(ownerStatus) ownerStatus.textContent='Could not load playlists. Your edits are still here; use Retry Playlist Lookup.';
                if(retry) retry.hidden=false;
            } finally { if(request===sequence) pending=false; }
        }
        ownerSelect.addEventListener('change',refreshPlaylists);
        if(retry) retry.addEventListener('click',refreshPlaylists);
        ownerForm.addEventListener('submit',event=>{if(pending || (playlists && playlists.disabled)){event.preventDefault();if(ownerStatus)ownerStatus.textContent='Wait for the playlist lookup, or retry it before saving. Your edits have been kept.';}});
    }
    document.querySelectorAll('input[name="operation"][value="submit_ad"], input[name="operation"][value="copy_placement"]').forEach(action=>{
        const draft=document.querySelector('input[name="operation"][value="record"], input[name="operation"][value="placement_record"]');
        if(!draft) return;
        let changed=false; draft.form.addEventListener('input',()=>{changed=true;}); draft.form.addEventListener('change',()=>{changed=true;});
        action.form.addEventListener('submit',event=>{if(changed || (window.tinymce && tinymce.get('hpads_description') && tinymce.get('hpads_description').isDirty())){event.preventDefault();window.alert('Save your placement edits before submitting or copying this entry.');}});
    });
    const templateDrop=document.getElementById('hpads-template-drop'),templateFiles=document.getElementById('hpads-template-files');
    if(templateDrop && templateFiles){
        let uploading=false;
        const status=document.getElementById('hpads-template-upload-status'),results=document.getElementById('hpads-template-upload-results');
        async function uploadTemplates(files){
            if(uploading)return;uploading=true;
            const owner=document.getElementById('hpads-template-owner').value,orientation=document.getElementById('hpads-template-orientation').value;
            try{for(const file of files){
                if(file.size>Number(templateFiles.dataset.maxBytes)){status.textContent=file.name+': exceeds the server upload limit.';break;}
                status.textContent='Uploading '+file.name+'…';
                const body=new FormData();Object.entries({action:'hpads_template_upload',nonce:HPADSAdmin.createNonce,owner,orientation,title:file.name,placement_type:'advertising'}).forEach(([k,v])=>body.set(k,v));body.set('media',file);
                const response=await fetch(HPADSAdmin.ajaxUrl,{method:'POST',credentials:'same-origin',body});const result=await response.json();
                if(!response.ok || !result.success)throw new Error(file.name+': '+(result.data && result.data.message || 'Upload failed.'));
                const li=document.createElement('li'),a=document.createElement('a');a.href=result.data.edit;a.textContent=result.data.title+' — Edit template';li.append(a);results.append(li);
                status.textContent='Template created. Edit it above, or refresh the list when uploads finish.';
            }}catch(error){status.textContent=error.message;}finally{uploading=false;templateFiles.value='';}
        }
        templateDrop.addEventListener('click',()=>templateFiles.click());templateDrop.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();templateFiles.click();}});
        templateFiles.addEventListener('change',()=>uploadTemplates(Array.from(templateFiles.files)));
        templateDrop.addEventListener('dragover',e=>{e.preventDefault();});templateDrop.addEventListener('drop',e=>{e.preventDefault();uploadTemplates(Array.from(e.dataTransfer.files));});
    }
    const editor = document.getElementById('hpads-playlist');
    if (!editor) return;
    const form = editor.closest('form');
    const hidden = document.getElementById('hpads-items');
    const media = JSON.parse(editor.dataset.media);
    const defaultDuration=Number(editor.dataset.defaultDuration || 10);
    const bound = JSON.parse(editor.dataset.bound || '{}');
    const available = Object.entries(media).filter(([id])=>Number(id)>0);
    const addButton = document.getElementById('hpads-add-item');
    const emptyMessage = document.createElement('p');
    emptyMessage.className = 'description hpads-empty-rotation';
    emptyMessage.setAttribute('role','status');
    const savedRotation = Number(form.querySelector('input[name="id"]').value)>0;
    emptyMessage.textContent = savedRotation
        ? 'No placements exist for this playlist yet. Use “Add New Placement for This Playlist” above. You can start from a template in My Ad Templates; it creates a new independent placement.'
        : 'Save this playlist with its location first, then create a placement for it.';
    emptyMessage.hidden = available.length>0;
    editor.before(emptyMessage);
    addButton.disabled = available.length===0;
    let dirty = false, importing = false;
    const status=document.getElementById('hpads-import-status');
    function input(type, value, label) {
        const el = document.createElement('input');
        el.type = type;
        el.setAttribute('aria-label', label);
        if (type === 'checkbox') el.checked = !!value;
        else el.value = value == null ? '' : value;
        return el;
    }
    function button(text, label, fn) {
        const el = document.createElement('button');
        el.type = 'button'; el.className = 'button'; el.textContent = text;
        el.setAttribute('aria-label', label); el.addEventListener('click', fn);
        return el;
    }
    function row(item) {
        const el = document.createElement('div'); el.className = 'hpads-item';
        const handle = document.createElement('span'); handle.className = 'hpads-handle'; handle.textContent = '↕'; handle.title = 'Drag to reorder';
        el.append(handle);
        const preview=document.createElement('div');preview.className='hpads-card-preview';el.append(preview);
        const edit=document.createElement('a');edit.className='button';edit.textContent='Edit Placement';
        const duplicate=button('Duplicate','Duplicate placement',()=>createPlacement({source_id:select.value}));
        const select = document.createElement('select'); select.className = 'creative'; select.setAttribute('aria-label', 'Placement');
        available.forEach(([id, name]) => { if (+id) { const o = document.createElement('option'); o.value = id; o.textContent = name; select.append(o); } });
        if (item.creative_id && !media[item.creative_id]) { const o = document.createElement('option'); o.value = item.creative_id; o.textContent = 'Unavailable placement #' + item.creative_id; select.append(o); }
        if(!select.options.length) {
            const option=document.createElement('option'); option.value=''; option.textContent='No placements available for this playlist'; select.append(option); select.disabled=true;
        }
        select.value = item.creative_id || select.value;
        const placementTitle=input('text',bound[item.creative_id] && bound[item.creative_id].title || '', 'Placement name');placementTitle.className='placement-title';
        const duration = input('number', item.duration, 'Image duration override in seconds'); duration.className = 'duration'; duration.min = 1; duration.max = 3600; duration.placeholder = String(defaultDuration);
        const inherited=document.createElement('span'); inherited.className='hpads-duration-inherited';
        function durationState(value){duration.dataset.inherited=value == null || value==='';duration.value=duration.dataset.inherited==='true'?defaultDuration:value;inherited.textContent=duration.dataset.inherited==='true'?'Inherited default: '+defaultDuration+' seconds':'Placement override';}
        durationState(item.duration);duration.addEventListener('input',()=>{if(duration.value===''){durationState(null);}else{duration.dataset.inherited='false';inherited.textContent='Placement override';}});
        const active = input('checkbox', item.active, 'Active'); active.className = 'active';
        const activeLabel = document.createElement('label'); activeLabel.append(active, ' Active');
        const start = input('datetime-local', item.start, 'Start ('+(window.HPADSAdmin && HPADSAdmin.timezone || 'site timezone')+')'); start.className = 'start';
        const end = input('datetime-local', item.end, 'End ('+(window.HPADSAdmin && HPADSAdmin.timezone || 'site timezone')+')'); end.className = 'end';
        function labeled(text, control) { const label = document.createElement('label'); const span = document.createElement('span'); span.textContent = text; label.append(span, control); return label; }
        el.append(labeled('Placement', select), labeled('Placement name',placementTitle), labeled('Seconds', duration), inherited, activeLabel, labeled('Start', start), labeled('End', end));
        el.append(button('↑', 'Move item up', () => { if (el.previousElementSibling) editor.insertBefore(el, el.previousElementSibling); dirty = true; }));
        el.append(button('↓', 'Move item down', () => { if (el.nextElementSibling) editor.insertBefore(el.nextElementSibling, el); dirty = true; }));
        const remove=button('Remove', 'Remove item', () => { el.remove(); dirty = true; });
        const external=input('url','','External URL (optional)');external.className='link-url';
        const qr=input('checkbox',false,'Show QR Code');qr.className='qr-enabled';const qrLabel=document.createElement('label');qrLabel.append(qr,' Show QR Code');
        function transitionField(cls,label){const select=document.createElement('select');select.className=cls;Object.entries(window.HPADSAdmin && HPADSAdmin.transitions || {none:'None',fade:'Fade','slide-left':'Slide left'}).forEach(([value,text])=>select.add(new Option(text,value)));el.append(labeled(label,select));return select;}
        const tin=transitionField('transition-in','Entrance'),tout=transitionField('transition-out','Exit');
        el.append(labeled('External URL (optional)',external),qrLabel);
        const templateEdit=document.createElement('a');templateEdit.textContent='Edit Source Template';templateEdit.target='_blank';templateEdit.rel='noopener';el.append(templateEdit);
        [start,end].forEach(control=>{const calendar=button('Calendar','Choose '+(control===start?'start':'end')+' date',()=>{try{control.showPicker();}catch{control.focus();}});control.after(calendar);});
        function entryValues() {
            const data=bound[select.value];
            placementTitle.value=data && data.title || '';placementTitle.readOnly=!!data && data.editable===false;
            el.dataset.revision=data?data.revision:'';external.value=data && data.link_url || '';qr.checked=!!(data && data.qr_enabled);tin.value=data && data.transition_in || 'none';tout.value=data && data.transition_out || 'none';templateEdit.hidden=!(data && data.template_edit);if(data && data.template_edit)templateEdit.href=data.template_edit;
            preview.replaceChildren(); if(data && data.preview) { const media=document.createElement(data.video?'video':'img');media.src=data.preview;if(data.video){media.controls=true;media.preload='none';}else{media.alt=data.title || '';}preview.append(media); }
            edit.hidden=!data || !data.edit; if(data && data.edit) edit.href=data.edit;duplicate.disabled=!data;
            el.append(edit,duplicate,remove);
            [duration,start,end].forEach(control=>{control.readOnly=false;});
            if(data) { durationState(data.duration);start.value=data.start || '';end.value=data.end || '';duration.title='Clear to restore the inherited default.';start.title=end.title='Timezone: '+(window.HPADSAdmin && HPADSAdmin.timezone || 'WordPress site timezone'); }
        }
        select.addEventListener('change',entryValues); entryValues();
        editor.append(el);
    }
    async function createPlacement(values,file) {
        if(importing) return;
        importing=true;
        if(status) status.textContent='Creating placement…';
        try {
            const body=new FormData();body.set('action','hpads_playlist_create');body.set('nonce',HPADSAdmin.createNonce);
            body.set('rotation_id',form.querySelector('input[name="id"]').value);
            Object.entries(values).forEach(([k,v])=>body.set(k,v));if(file)body.set('media',file);
            const response=await fetch(HPADSAdmin.ajaxUrl,{method:'POST',credentials:'same-origin',body});const result=await response.json();
            if(!response.ok || !result.success) throw new Error(result.data && result.data.message || 'Could not create placement.');
            const data=result.data;media[data.id]=data.title;bound[data.id]=data;available.push([String(data.id),data.title]);
            editor.querySelectorAll('select.creative').forEach(select=>select.add(new Option(data.title,String(data.id))));
            row({creative_id:data.id,active:true});dirty=true;addButton.disabled=false;emptyMessage.hidden=true;
            if(status)status.textContent='Placement created. Save Draft to keep the playlist order.';
            return true;
        }catch(error){if(status)status.textContent=error.message;return false;}
        finally{importing=false;}
    }
    const drop=document.getElementById('hpads-dropzone'),files=document.getElementById('hpads-bulk-media');
    let batch=false;
    async function uploadFiles(list){
        if(batch || importing)return;batch=true;
        try{for(const file of list){
            const max=Number(files.dataset.maxBytes || 0);
            if(max && file.size>max){status.textContent=file.name+': exceeds the server upload limit.';break;}
            const owner=form.querySelector('[name="upload_owner"]').value;
            if(!owner){status.textContent='No connected owner is available for this playlist.';break;}
            if(!await createPlacement({owner,title:file.name,orientation:'auto',placement_type:'advertising'},file))break;
        }}finally{batch=false;files.value='';}
    }
    const library=document.getElementById('hpads-library-media');
    if(library)library.addEventListener('click',()=>{
        if(batch || importing)return;
        const frame=wp.media({title:'Choose media for a new placement',button:{text:'Create Placement'},multiple:false,library:{type:['image','video']}});
        frame.on('select',()=>{const a=frame.state().get('selection').first().toJSON();createPlacement({owner:form.querySelector('[name="upload_owner"]').value,title:a.title || a.filename,attachment_id:a.id,orientation:'auto',placement_type:'advertising'});});frame.open();
    });
    if(drop && files){
        drop.addEventListener('click',()=>files.click());drop.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();files.click();}});
        files.addEventListener('change',()=>uploadFiles(Array.from(files.files)));
        ['dragenter','dragover'].forEach(type=>drop.addEventListener(type,e=>{e.preventDefault();drop.classList.add('is-dragging');}));
        drop.addEventListener('dragleave',()=>drop.classList.remove('is-dragging'));
        drop.addEventListener('drop',e=>{e.preventDefault();drop.classList.remove('is-dragging');uploadFiles(Array.from(e.dataTransfer.files));});
        document.getElementById('hpads-copy-template').addEventListener('click',()=>{const id=document.getElementById('hpads-playlist-template').value;if(id && !batch)createPlacement({source_id:id});});
    }
    form.addEventListener('submit',event=>{if(importing || batch){event.preventDefault();if(status)status.textContent='Wait for uploads to finish before saving.';}});
    JSON.parse(editor.dataset.items).forEach(row);
    $(editor).sortable({handle: '.hpads-handle', update: () => { dirty = true; }});
    addButton.addEventListener('click', () => { if(!available.length) return; row({active: true}); dirty = true; });
    form.addEventListener('input', () => { dirty = true; });
    form.addEventListener('change', () => { dirty = true; });
    form.addEventListener('submit', event => {
        if(event.defaultPrevented)return;
        hidden.value = JSON.stringify(Array.from(editor.children).map(el => ({
            creative_id: +el.querySelector('.creative').value,title:el.querySelector('.placement-title').value,
            duration: el.querySelector('.duration').dataset.inherited==='true'?'':el.querySelector('.duration').value,
            active: el.querySelector('.active').checked,
            start: el.querySelector('.start').value,
            end: el.querySelector('.end').value,revision:Number(el.dataset.revision),link_url:el.querySelector('.link-url').value,qr_enabled:el.querySelector('.qr-enabled').checked,transition_in:el.querySelector('.transition-in').value,transition_out:el.querySelector('.transition-out').value
        })));
        dirty = false;
    });
    document.querySelectorAll('input[name="operation"][value="publish"]').forEach(el => {
        el.form.addEventListener('submit', event => { if (dirty || importing || batch) { event.preventDefault(); window.alert('Save the draft before publishing your changes.'); } });
    });
})(jQuery);
