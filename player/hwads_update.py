#!/usr/bin/env python3
"""HWADS user-service updater: authenticated commands, bounded ZIPs, atomic releases and rollback."""
import argparse
import ast
import fcntl
import hashlib
import hmac
import json
import logging
from logging.handlers import RotatingFileHandler
import os
from pathlib import Path, PurePosixPath
import re
import shlex
import shutil
import signal
import subprocess
import sys
import tempfile
import time
import urllib.request
import urllib.parse
import uuid
import zipfile

PROTOCOL = 1
MAX_ZIP = 20 * 1024 * 1024
MAX_EXPANDED = 40 * 1024 * 1024
RUNTIME = frozenset(('hwads_sync.py','hwads_player.py','hwads_browser.py','hwads_launch.py','hwads-control.py','hwads_update.py','browser/index.html','browser/player.js','browser/player.css','browser/overlays.js','browser/overlays.css','browser/vendor/qrcodegen.js'))
EXTRAS = frozenset(('README.md','THIRD_PARTY.md','install.sh','device.example.json','update-manifest.json'))
ENVIRONMENT = ('DISPLAY','WAYLAND_DISPLAY','XDG_RUNTIME_DIR','DBUS_SESSION_BUS_ADDRESS','XAUTHORITY')
TERMINAL = ('completed','failed','rolled_back')
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *_): return None
OPENER = urllib.request.build_opener(NoRedirect)

def read(path, default=None):
    try: return json.loads(Path(path).read_text())
    except (OSError, ValueError): return {} if default is None else default

def atomic(path, data):
    path = Path(path);path.parent.mkdir(parents=True,exist_ok=True)
    temp = path.with_name(path.name+'.tmp-'+uuid.uuid4().hex)
    try:
        with temp.open('x') as f:
            os.chmod(temp,0o600);json.dump(data,f);f.flush();os.fsync(f.fileno())
        os.replace(temp,path)
        directory = os.open(path.parent,os.O_RDONLY)
        try: os.fsync(directory)
        finally: os.close(directory)
    finally:
        if temp.exists(): temp.unlink()

def canonical(command):
    return '|'.join(str(command.get(k,'')) for k in ('type','id','release_id','version','sha256','bytes','url','expires_at'))

def verify_command(command, config):
    if command.get('type') not in ('install_player','rollback_player') or not re.fullmatch(r'[a-f0-9-]{36}',str(command.get('id',''))): raise ValueError('Invalid update command')
    if not re.fullmatch(r'[A-Za-z0-9_-]{1,100}',str(command.get('release_id',''))) or not re.fullmatch(r'\d+\.\d+\.\d+',str(command.get('version',''))): raise ValueError('Invalid release identity')
    if not isinstance(command.get('expires_at'),int) or not time.time() < command['expires_at'] <= time.time()+8*86400: raise ValueError('Update command expired or clock is incorrect')
    key = hashlib.sha256(config['device_key'].encode()).hexdigest().encode()
    expected = hmac.new(key,canonical(command).encode(),hashlib.sha256).hexdigest()
    if not hmac.compare_digest(expected,str(command.get('signature',''))): raise ValueError('Update command authentication failed')
    if command['type']=='install_player':
        url = urllib.parse.urlsplit(command.get('url',''))
        if url.scheme!='https' or not url.hostname or url.username or url.password or url.fragment: raise ValueError('Update URL must use HTTPS')
        if not re.fullmatch(r'[a-f0-9]{64}',str(command.get('sha256',''))) or type(command.get('bytes')) is not int or not 0 < command['bytes'] <= MAX_ZIP: raise ValueError('Invalid update checksum or size')

def validate_manifest(manifest):
    if manifest.get('schema')!=1 or manifest.get('updater_protocol')!=PROTOCOL or not re.fullmatch(r'\d+\.\d+\.\d+',str(manifest.get('version',''))): raise ValueError('Unsupported player update manifest')
    files = manifest.get('files')
    if not isinstance(files,dict) or set(files)!=RUNTIME or any(not re.fullmatch(r'[a-f0-9]{64}',str(v)) for v in files.values()): raise ValueError('Incomplete or invalid runtime file list')
    return manifest

def inspect_archive(path):
    with zipfile.ZipFile(path) as archive:
        entries = archive.infolist()
        if len(entries)>50 or sum(x.file_size for x in entries)>MAX_EXPANDED: raise ValueError('Update archive is too large')
        seen=set()
        for info in entries:
            name=info.filename
            if name in seen: raise ValueError('Duplicate archive entry')
            seen.add(name)
            if not name.startswith('hwads-pi/') or '\\' in name or '\x00' in name or '..' in PurePosixPath(name).parts: raise ValueError('Unsafe archive path')
            mode=(info.external_attr>>16)&0o170000
            if mode not in (0,0o100000,0o040000) or info.flag_bits&1 or info.file_size>10*1024*1024: raise ValueError('Links, encrypted files and oversized entries are not allowed')
            relative=name[len('hwads-pi/'):]
            if info.is_dir():
                if relative not in ('','browser/','browser/vendor/'): raise ValueError('Unexpected archive directory')
            elif relative not in RUNTIME|EXTRAS: raise ValueError('Unexpected archive file: '+relative)
        raw=archive.read('hwads-pi/update-manifest.json')
        if len(raw)>32768: raise ValueError('Manifest is too large')
        manifest=validate_manifest(json.loads(raw))
        for name,digest in manifest['files'].items():
            content=archive.read('hwads-pi/'+name)
            if hashlib.sha256(content).hexdigest()!=digest: raise ValueError('Runtime checksum mismatch: '+name)
            if name.endswith('.py'): compile(content,name,'exec')
        return manifest

def metadata(target): return read(Path(target)/'release.json')

def release_target(root, link='scripts'):
    p=root/link
    if not p.is_symlink(): raise ValueError('Initial updater installation is required')
    target=p.resolve(strict=True)
    if target.parent!=(root/'releases').resolve() or not target.is_dir(): raise ValueError('Invalid local release target')
    return target

def swap(root, name, target):
    target=Path(target).resolve(strict=True)
    if target.parent!=(root/'releases').resolve(): raise ValueError('Release must remain inside this installation')
    temp=root/('.'+name+'-'+uuid.uuid4().hex)
    try: temp.symlink_to(target);os.replace(temp,root/name)
    finally:
        if temp.is_symlink():temp.unlink()

def wrapper(root,target):
    file=target/'start-player.sh'
    file.write_text('#!/bin/sh\nexec python3 '+shlex.quote(str(root/'scripts/hwads_launch.py'))+' --root '+shlex.quote(str(root))+'\n');file.chmod(0o700)

def state(root, **changes):
    path=root/'config/player-update.json';data=read(path);data.update(changes,updated_at=time.time());atomic(path,data);return data

def status(root):
    data=read(root/'config/player-update.json');out={k:data.get(k,'') for k in ('id','status','message','version','release_id','previous_version','previous_release_id')}
    out['updater_protocol']=PROTOCOL
    try:
        current=metadata(release_target(root));out['installed_version']=current.get('version','')
        previous=metadata(release_target(root,'previous-release'));out['rollback_available']=previous.get('updater_protocol')==PROTOCOL and release_target(root,'previous-release')!=release_target(root);out['previous_version']=previous.get('version','');out['previous_release_id']=previous.get('release_id','')
    except (ValueError,OSError): out.setdefault('rollback_available',False)
    return out

def download(root, command):
    if shutil.disk_usage(root).free < MAX_EXPANDED+command['bytes']*2: raise ValueError('Not enough free space for a safe update')
    directory=root/'updates';directory.mkdir(mode=0o700,exist_ok=True);path=directory/(command['id']+'.zip.part')
    try:
        with OPENER.open(urllib.request.Request(command['url']),timeout=45) as response,path.open('wb') as output:
            total=0;digest=hashlib.sha256()
            while True:
                chunk=response.read(256*1024)
                if not chunk:break
                total+=len(chunk)
                if total>command['bytes']:raise ValueError('Update download exceeded declared size')
                digest.update(chunk);output.write(chunk)
        if total!=command['bytes'] or not hmac.compare_digest(digest.hexdigest(),command['sha256']): raise ValueError('Update download checksum mismatch')
        return path
    except BaseException:
        if path.exists():path.unlink()
        raise

def stage(root, path, command):
    manifest=inspect_archive(path)
    if manifest['version']!=command['version']: raise ValueError('Package version differs from requested update')
    target=root/'releases'/command['release_id']
    if target.is_symlink():raise ValueError('Release target cannot be a link')
    if target.exists():
        if metadata(target).get('sha256')!=command['sha256']: raise ValueError('Release identity conflict')
        for name,digest in manifest['files'].items():
            file=target/name
            if file.is_symlink() or not file.is_file() or hashlib.sha256(file.read_bytes()).hexdigest()!=digest:raise ValueError('Stored release is damaged')
        return target
    temp=Path(tempfile.mkdtemp(prefix='.stage-',dir=root/'releases'))
    try:
        with zipfile.ZipFile(path) as archive:
            for name in RUNTIME:
                dest=temp/name;dest.parent.mkdir(parents=True,exist_ok=True);dest.write_bytes(archive.read('hwads-pi/'+name));
                with dest.open('rb') as handle:os.fsync(handle.fileno())
                dest.chmod(0o700 if name.endswith('.py') else 0o600)
        wrapper(root,temp);atomic(temp/'release.json',dict(manifest,release_id=command['release_id'],sha256=command['sha256']))
        os.replace(temp,target);return target
    finally:
        if temp.exists():shutil.rmtree(temp)

def service(action, unit):
    subprocess.run(['systemctl','--user',action,unit],check=True,timeout=30,stdout=subprocess.DEVNULL,stderr=subprocess.PIPE)

def stop_player(root):
    # The stable launcher path is used on the Pi; the control module targets only this installation.
    import importlib.util
    path=root/'scripts/hwads-control.py';spec=importlib.util.spec_from_file_location('hwads_update_control',path);module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module);module.stop_players(root)

def start_player(root):
    if (root/'config/player.disabled').exists():return
    env=os.environ.copy();saved=read(root/'config/player-environment.json')
    for key in ENVIRONMENT:
        if isinstance(saved.get(key),str) and len(saved[key])<=2048:env[key]=saved[key]
    if not (env.get('DISPLAY') or env.get('WAYLAND_DISPLAY')):raise ValueError('No desktop session recorded; start the player locally once')
    with (root/'logs/launcher.log').open('ab') as log:subprocess.Popen([sys.executable,str(root/'scripts/hwads_launch.py'),'--root',str(root)],env=env,stdout=log,stderr=log,start_new_session=True)

def process_alive(pid):
    if not isinstance(pid,int) or pid<2:return False
    try:os.kill(pid,0);return True
    except OSError:return False

def healthy(root, version, since, disabled, timeout=90):
    deadline=time.monotonic()+timeout
    while time.monotonic()<deadline:
        sync=read(root/'config/sync-runtime.json');player=read(root/'config/player-state.json')
        sync_ok=sync.get('version')==version and sync.get('started_at',0)>=since and sync.get('healthy_at',0)>=since and time.time()-sync.get('healthy_at',0)<35 and process_alive(sync.get('pid'))
        player_ok=disabled or (root/'config/player.disabled').exists() or player.get('updated_at',0)>=since and player.get('state') in ('playing','idle')
        if sync_ok and player_ok:return True
        time.sleep(1)
    return False

def apply(root, command):
    root=Path(root).resolve();config=read(root/'config/device.json');verify_command(command,config)
    old_state=read(root/'config/player-update.json')
    if old_state.get('id')==command['id'] and old_state.get('status') in TERMINAL:return
    previous=release_target(root);current=metadata(previous);archive=None
    try:
        state(root,id=command['id'],status='downloading' if command['type']=='install_player' else 'validating',message='',version=command['version'],release_id=command['release_id'])
        if command['type']=='install_player':
            if tuple(map(int,command['version'].split('.')))<=tuple(map(int,current.get('version','0.0.0').split('.'))):raise ValueError('Install requires a newer version; use Roll back for the previous release')
            archive=download(root,command);state(root,status='validating');target=stage(root,archive,command)
        else:
            target=release_target(root,'previous-release');info=metadata(target)
            if info.get('updater_protocol')!=PROTOCOL or info.get('release_id')!=command['release_id'] or info.get('version')!=command['version']:raise ValueError('Previous release no longer matches rollback request')
        disabled=(root/'config/player.disabled').exists()
        if not disabled and not any(read(root/'config/player-environment.json').get(k) for k in ('DISPLAY','WAYLAND_DISPLAY')):raise ValueError('Start the player in the desktop once before remote updating')
        journal={'id':command['id'],'previous':str(previous),'target':str(target),'disabled':disabled,'version':command['version'],'phase':'switching'}
        atomic(root/'config/update-journal.json',journal);state(root,status='installing')
        stop_player(root);swap(root,'previous-release',previous);swap(root,'scripts',target)
        started=time.time();state(root,status='awaiting_health');service('restart','hwads-sync');
        if not disabled:start_player(root)
        if not healthy(root,command['version'],started,disabled):raise RuntimeError('New release did not report healthy startup')
        atomic(root/'config/update-journal.json',dict(journal,phase='confirmed'))
        state(root,status='completed',message=('Rolled back and startup confirmed' if command['type']=='rollback_player' else 'Installed and startup confirmed'),installed_version=command['version'],previous_version=current.get('version',''),previous_release_id=current.get('release_id',''))
    except Exception as error:
        journal=read(root/'config/update-journal.json')
        if journal.get('id')==command['id'] and journal.get('phase')=='switching':
            try:
                stop_player(root);swap(root,'scripts',previous);service('restart','hwads-sync')
                if not journal.get('disabled'):start_player(root)
                atomic(root/'config/update-journal.json',dict(journal,phase='reverted'));state(root,status='rolled_back',message='Update failed; previous code restored: '+str(error)[:200])
            except Exception:
                state(root,status='failed',message='Recovery needs local attention; previous release retained')
                logging.exception('Update recovery failed')
        else:state(root,status='failed',message=str(error)[:240])
        logging.warning('Player update failed: %s',type(error).__name__)
    finally:
        if archive and archive.exists():archive.unlink()

def recover(root):
    journal=read(root/'config/update-journal.json')
    if journal.get('phase')!='switching':return
    previous=Path(journal['previous']).resolve(strict=True)
    if previous.parent!=(root/'releases').resolve():raise ValueError('Invalid recovery journal')
    stop_player(root);swap(root,'scripts',previous);service('restart','hwads-sync')
    if not journal.get('disabled'):start_player(root)
    atomic(root/'config/update-journal.json',dict(journal,phase='reverted'));state(root,id=journal.get('id',''),status='rolled_back',message='Recovered an interrupted update; previous code restored')

def bootstrap(root, source):
    root=Path(root).expanduser().resolve();source=Path(source).resolve();manifest=validate_manifest(read(source/'update-manifest.json'))
    for name,digest in manifest['files'].items():
        p=source/name
        if p.is_symlink() or not p.is_file() or hashlib.sha256(p.read_bytes()).hexdigest()!=digest:raise ValueError('Bootstrap file verification failed: '+name)
    for name in ('config','media','logs','placeholders','releases','bootstrap','updates'):(root/name).mkdir(parents=True,exist_ok=True)
    (root/'config/player.disabled').touch(mode=0o600)
    if (root/'scripts/hwads-control.py').is_file():stop_player(root)
    if (root/'scripts').is_dir() and not (root/'scripts').is_symlink():
        legacy=root/'releases'/('legacy-'+uuid.uuid4().hex);os.replace(root/'scripts',legacy);atomic(legacy/'release.json',{'version':'0.0.0','release_id':legacy.name,'updater_protocol':0});(root/'scripts').symlink_to(legacy)
    previous=release_target(root) if (root/'scripts').is_symlink() else None
    target=root/'releases'/('bootstrap-'+uuid.uuid4().hex);target.mkdir()
    for name in RUNTIME:
        p=target/name;p.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(source/name,p);p.chmod(0o700 if name.endswith('.py') else 0o600)
    wrapper(root,target);atomic(target/'release.json',dict(manifest,release_id=target.name))
    if previous:swap(root,'previous-release',previous)
    swap(root,'scripts',target)
    temp=root/'bootstrap/hwads_update.py.tmp';shutil.copyfile(source/'hwads_update.py',temp);temp.chmod(0o700);os.replace(temp,root/'bootstrap/hwads_update.py');wrapper(root,root/'bootstrap')
    if not (root/'config/device.json').exists():shutil.copyfile(source/'device.example.json',root/'config/device.json')
    (root/'config/device.json').chmod(0o600)
    state(root,status='idle',message='Updater installed; playback remains disabled',installed_version=manifest['version'])

def main():
    parser=argparse.ArgumentParser();parser.add_argument('command',choices=('daemon','bootstrap'));parser.add_argument('--root',default='~/hpads');parser.add_argument('--source');args=parser.parse_args();root=Path(args.root).expanduser().resolve()
    if args.command=='bootstrap':bootstrap(root,args.source);return
    (root/'logs').mkdir(parents=True,exist_ok=True);logging.basicConfig(level=logging.INFO,handlers=[RotatingFileHandler(root/'logs/updater.log',maxBytes=1024*1024,backupCount=2)])
    with (root/'.update.lock').open('a') as lock:
        try:fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
        except BlockingIOError:return
        recover(root)
        last_runtime=0
        while True:
            if time.monotonic()-last_runtime>10:
                atomic(root/'config/updater-runtime.json',{'pid':os.getpid(),'protocol':PROTOCOL,'updated_at':time.time()});last_runtime=time.monotonic()
            pending=root/'config/pending-player-update.json'
            if pending.exists():
                command=read(pending)
                try:apply(root,command)
                except Exception as error:logging.warning('Rejected update request: %s',type(error).__name__)
                # Do not remove a newer request written while this one was running.
                if read(pending).get('id')==command.get('id'):pending.unlink(missing_ok=True)
            time.sleep(1)
if __name__=='__main__':main()
