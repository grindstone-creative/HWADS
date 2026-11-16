#!/usr/bin/env python3
"""One persistent mpv window; atomically rereads HWADS playlists between placements."""
import argparse
from datetime import datetime, timezone
import fcntl
import json
import logging
import random
import threading
import uuid
import math
from logging.handlers import RotatingFileHandler
import os
from pathlib import Path
import select
import signal
import socket
import subprocess
import tempfile
import time
from hwads_sync import Recorder, Play, atomic_json

STOP = False

def stop(*_):
    global STOP
    STOP = True

def active(item, now=None):
    if item.get('active') is False:
        return False
    now = now or datetime.now(timezone.utc)
    try:
        start, end = item.get('start_utc'), item.get('end_utc')
        return (not start or datetime.fromisoformat(start.replace('Z', '+00:00')) <= now) and (not end or now < datetime.fromisoformat(end.replace('Z', '+00:00')))
    except (ValueError, TypeError):
        return False

def items(root, include_fallback=False):
    try:
        data = json.loads((root / 'config/playlist.json').read_text())
        if isinstance(data, list):
            rows = data  # Existing local playlist format remains usable before first sync.
        else:
            mix = data.get('playback_mix', {})
            if mix.get('enabled'):
                local=[dict(x,mix_group='local',mix_weight=100,mix_signature=mix.get('signature',''),mix_fallback=True,mix_local=True) for x in placeholders(root)]
                rows = []
                for group in mix.get('groups', []):
                    for x in group.get('items', []):
                        if active(x) and media_path(root, x).is_file():rows.append(dict(x, mix_group=str(group['playlist_id']), mix_weight=group['weight'], mix_signature=mix.get('signature', '')))
                fallback = [dict(x, mix_group='fallback', mix_weight=100, mix_signature=mix.get('signature', ''), mix_fallback=True) for x in mix.get('fallback', []) if active(x) and media_path(root, x).is_file()]
                if rows:return rows + fallback + local if include_fallback else rows
                if fallback:return fallback+local if include_fallback else fallback
                return local
            primary = sorted([x for x in data['primary'] if active(x) and media_path(root, x).is_file()], key=lambda x: x.get('order', 999999))
            house = sorted([x for x in data['house'] if active(x) and media_path(root, x).is_file()], key=lambda x: x.get('order', 999999))
            rows = primary + house if primary and data.get('house_in_rotation') else primary or house
            if rows:
                return rows
        rows = [x for x in rows if active(x) and media_path(root, x).is_file()]
        if rows:
            return sorted(rows, key=lambda x: x.get('order', 999999))
    except (OSError, ValueError, TypeError, KeyError):
        pass
    return placeholders(root)

def placeholders(root):
    return [{'file': str(p.resolve()), 'type': 'image', 'duration': 7, 'placeholder': True} for p in sorted((root / 'placeholders').glob('*')) if p.suffix.lower() in ('.jpg', '.jpeg', '.png', '.webp') and p.is_file()]

def media_path(root, item):
    if item.get('placeholder'):
        p = Path(item['file']).resolve()
        return p if p.parent == (root / 'placeholders').resolve() else root / '.invalid-media'
    p = (root / 'media' / item['file']).resolve()
    return p if p.parent == (root / 'media').resolve() else root / '.invalid-media'

class PlaybackMixer:
    """Time-deficit selection + shuffled per-playlist bags; state contains IDs, never grants."""
    def __init__(self, root, rng=None):
        self.root=Path(root);self.rng=rng or random.Random();self.lock=threading.RLock();self.path=self.root/'config/playback-mix-state.json'
        try:self.state=json.loads(self.path.read_text())
        except (OSError,ValueError):self.state={}
        try:
            if not isinstance(self.state,dict):raise ValueError()
            for key in ('balances','bags','known','cooldown','pending'):
                if key in self.state and not isinstance(self.state[key],dict):raise ValueError()
            for value in self.state.get('balances',{}).values():
                if not isinstance(value,(int,float)) or not math.isfinite(value):raise ValueError()
            for key in ('bags','known'):
                if any(not isinstance(v,list) or any(not isinstance(x,str) for x in v) for v in self.state.get(key,{}).values()):raise ValueError()
            history=self.state.get('history',[])
            if not isinstance(history,list) or any(not isinstance(k,str) for k in history):raise ValueError()
            cursor=self.state.get('cursor',-1)
            if type(cursor) is not int or not -1<=cursor<=50:raise ValueError()
            pending=self.state.get('pending',{})
            if not isinstance(pending.get('weights',{}),dict) or any(type(w) is not int or not 1<=w<=100 for w in pending.get('weights',{}).values()):raise ValueError()
        except (ValueError,TypeError):self.state={}
        self.last_save_warning=-float('inf')
    def save(self):
        try:atomic_json(self.path,self.state)
        except OSError:
            if time.monotonic()-self.last_save_warning>300:logging.warning('Mix state could not be saved; continuing playback in memory');self.last_save_warning=time.monotonic()
    @staticmethod
    def key(row):return str(row.get('placement_id') or row.get('file'))+':'+str(row.get('version_id') or 0)
    def select(self, rows, previous='', elapsed=0, navigation=1, request_id='', failed=False):
        with self.lock:
            if type(elapsed) not in (int,float) or not math.isfinite(elapsed) or not 0<=elapsed<=86400:raise ValueError('Invalid observed playback time')
            state=self.state;pending=state.get('pending',{});lookup={}
            for r in rows:
                key=self.key(r)
                if key not in lookup or lookup[key].get('mix_fallback') and not r.get('mix_fallback'):lookup[key]=r
            if pending and pending.get('key') in lookup and ((request_id and request_id==state.get('request_id')) or (previous and previous==state.get('consumed') and previous!=pending.get('token'))):
                return lookup[pending['key']],pending['token']
            if previous and previous==pending.get('token'):
                weights=pending.get('weights',{});total=sum(weights.values()) or 1;balances=state.setdefault('balances',{})
                for group,weight in weights.items():balances[group]=max(-86400,min(86400,balances.get(group,0)+elapsed*weight/total-(elapsed if group==pending.get('group') else 0)))
                state['consumed']=previous
                if failed:state.setdefault('cooldown',{})[pending['key']]=time.time()+60
            cooldown={k:v for k,v in state.get('cooldown',{}).items() if isinstance(v,(int,float)) and v>time.time() and k in lookup};state['cooldown']=cooldown
            eligible=[r for r in rows if self.key(r) not in cooldown]
            normal=[r for r in eligible if not r.get('mix_fallback')]
            eligible=normal or [r for r in eligible if r.get('mix_fallback') and not r.get('mix_local')] or [r for r in eligible if r.get('mix_local')]
            if not eligible:
                state['pending']={};self.save();return None,''
            groups={};weights={}
            for row in eligible:
                group=str(row.get('mix_group','legacy'));groups.setdefault(group,[]).append(row);weights[group]=int(row.get('mix_weight',100))
            scope=[str(eligible[0].get('mix_signature','')),[[g,w] for g,w in sorted(weights.items())]]
            if state.get('scope')!=scope:
                state['scope']=scope;state['balances']={g:0 for g in groups};state['bags']={}
            balances=state.setdefault('balances',{});bags=state.setdefault('bags',{});last=state.get('last','')
            history=[k for k in state.get('history',[]) if k in lookup][-50:];cursor=min(int(state.get('cursor',len(history)-1)),len(history)-1)
            row=None
            if navigation==-1 and cursor>0:
                cursor-=1;row=lookup[history[cursor]]
                if row not in eligible:row=None
            if row is None:
                candidates=[g for g,rs in groups.items() if any(self.key(r)!=last for r in rs)] or list(groups)
                maximum=max(balances.get(g,0) for g in candidates);ties=[g for g in candidates if abs(balances.get(g,0)-maximum)<1e-8]
                group=self.rng.choices(ties,weights=[weights[g] for g in ties],k=1)[0]
                available={self.key(r):r for r in groups[group]};bag=[k for k in bags.get(group,[]) if k in available]
                known=set(state.get('known',{}).get(group,[]));new=[k for k in available if k not in known];self.rng.shuffle(new);bag+=new
                if not bag:bag=list(available);self.rng.shuffle(bag)
                if bag[0]==last and len(bag)>1:bag[0],bag[1]=bag[1],bag[0]
                key=bag.pop(0);bags[group]=bag;state.setdefault('known',{})[group]=list(available);row=available[key]
                history=history[:cursor+1]+[key];history=history[-50:];cursor=len(history)-1
            key=self.key(row);token=uuid.uuid4().hex;state['last']=key;state['history']=history;state['cursor']=cursor;state['request_id']=request_id
            state['bags']={g:bags.get(g,[]) for g in groups};state['known']={g:state.get('known',{}).get(g,[]) for g in groups}
            state['pending']={'token':token,'key':key,'group':str(row.get('mix_group','legacy')),'weights':weights}
            self.save();return row,token

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--root', default='~/hpads')
    args = parser.parse_args()
    root = Path(args.root).expanduser().resolve()
    root.mkdir(parents=True, exist_ok=True)
    for name in ('config', 'media', 'placeholders', 'logs'):
        (root / name).mkdir(exist_ok=True)
    disabled = root / 'config/player.disabled'
    if disabled.exists():
        return 0
    signal.signal(signal.SIGTERM, stop)
    signal.signal(signal.SIGINT, stop)
    logging.basicConfig(level=logging.INFO, handlers=[RotatingFileHandler(root / 'logs/player.log', maxBytes=1024*1024, backupCount=2)])
    from hwads_sync import atomic_json
    state = root / 'config/player-state.json'
    recorder = Recorder(root)
    current_play = None
    mixer=PlaybackMixer(root);mix_token='';mix_elapsed=0;mix_failed=False
    with (root / '.player.lock').open('w') as lock:
        try:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            return 0
        with tempfile.TemporaryDirectory(prefix='hwads-mpv-') as tmp:
            ipc = str(Path(tmp) / 'ipc.sock')
            process, client = None, None
            try:
                process = subprocess.Popen(['mpv', '--no-config', '--keep-open=no', '--idle=yes', '--force-window=immediate', '--fullscreen', '--no-border', '--no-osc', '--no-osd-bar', '--cursor-autohide=always', '--image-display-duration=inf', '--really-quiet', '--input-ipc-server=' + ipc], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
                for _ in range(100):
                    if STOP or process.poll() is not None:
                        return 1
                    try:
                        client = socket.socket(socket.AF_UNIX)
                        client.connect(ipc)
                        break
                    except OSError:
                        client.close()
                        client = None
                        time.sleep(.1)
                if client is None:
                    raise RuntimeError('mpv IPC did not start')
                client.setblocking(False)
                # Observation subscriptions do not alter media selection or advancement.
                for oid, prop in enumerate(('time-pos', 'pause', 'paused-for-cache'), 1):
                    client.sendall((json.dumps({'command':['observe_property', oid, prop]})+'\n').encode())
                cursor, buffer = 0, b''
                while not STOP and not disabled.exists() and process.poll() is None:
                    rows = items(root, include_fallback=True)
                    if not rows:
                        atomic_json(state, {'state': 'idle', 'updated_at': time.time(), 'version_id': 0})
                        time.sleep(2)
                        continue
                    if any('mix_group' in row for row in rows):
                        item,mix_token=mixer.select(rows,previous=mix_token,elapsed=mix_elapsed,failed=mix_failed);mix_elapsed=0;mix_failed=False
                        if item is None:time.sleep(2);continue
                    else:
                        item=rows[cursor % len(rows)];cursor+=1;mix_token=''
                    # Discard prior stop events before loading the next item.
                    while select.select([client], [], [], 0)[0]:
                        if not client.recv(65536):
                            return 1
                    buffer = b''
                    client.sendall((json.dumps({'command': ['loadfile', str(media_path(root, item)), 'replace']}) + '\n').encode())
                    started, ended, last_status = False, False, 0
                    outcome, position, paused, cached = 'interrupted', None, False, False
                    observed_at = time.monotonic()
                    current_play = None
                    # Image timer starts after file-loaded; videos run to end-file.
                    loaded_at, requested_at = None, time.monotonic()
                    duration = float(item.get('duration') or 7)
                    while not STOP and not disabled.exists() and process.poll() is None and not ended:
                        now = time.monotonic()
                        if current_play and item.get('type') != 'video':
                            current_play.add(now-observed_at)
                        observed_at = now
                        if now - last_status >= 10:
                            atomic_json(state, {'state': 'playing' if loaded_at else 'idle', 'updated_at': time.time(), 'version_id': item.get('version_id', 0) if loaded_at else 0, 'placement_id': item.get('placement_id'), 'engine': 'mpv', 'media_files': [item['file']]})
                            last_status = now
                        if loaded_at and item.get('type') != 'video' and now - loaded_at >= max(1, duration):
                            outcome = 'completed'
                            break
                        if not loaded_at and now - requested_at > 30:
                            logging.warning('Media did not load; skipping placement')
                            break
                        if not active(item):
                            break
                        if select.select([client], [], [], .25)[0]:
                            chunk = client.recv(65536)
                            if not chunk:
                                return 1
                            buffer += chunk
                            while b'\n' in buffer:
                                line, buffer = buffer.split(b'\n', 1)
                                event = json.loads(line)
                                if event.get('event') == 'start-file':
                                    started = True
                                if event.get('event') == 'file-loaded':
                                    loaded_at = time.monotonic()
                                    last_status = 0
                                    if item.get('type') != 'video':
                                        current_play = Play(recorder, item, 'mpv')
                                        observed_at = loaded_at
                                if event.get('event') == 'property-change' and loaded_at:
                                    name, value = event.get('name'), event.get('data')
                                    if name == 'pause': paused = bool(value)
                                    if name == 'paused-for-cache': cached = bool(value)
                                    if name == 'time-pos' and isinstance(value, (int, float)):
                                        tick = time.monotonic()
                                        if position is not None and value > position[0] and not paused and not cached:
                                            if current_play is None: current_play = Play(recorder, item, 'mpv')
                                            current_play.add(min(value-position[0], max(0, tick-position[1])))
                                        position = (value, tick)
                                if event.get('event') == 'end-file' and started:
                                    ended = True
                                    outcome = 'completed' if event.get('reason') == 'eof' else 'error' if event.get('reason') == 'error' else 'interrupted'
                    mix_elapsed=current_play.elapsed if current_play else 0;mix_failed=outcome=='error' or not loaded_at
                    if current_play:
                        current_play.save(outcome)
                        current_play = None
                    if process.poll() is None:
                        client.sendall(b'{"command":["stop"]}\n')
                    if not loaded_at:
                        time.sleep(1)  # Avoid a tight loop if a local file cannot decode.
            except (OSError, ValueError, RuntimeError) as error:
                logging.error('Player stopped (%s)', type(error).__name__)
                return 1
            finally:
                if current_play:
                    current_play.save('interrupted')
                recorder.close()
                if client:
                    client.close()
                if process and process.poll() is None:
                    process.terminate()
                    try:
                        process.wait(timeout=5)
                    except subprocess.TimeoutExpired:
                        process.kill()
                        process.wait()
                atomic_json(state, {'state': 'idle', 'updated_at': time.time(), 'version_id': 0})
    return 0

if __name__ == '__main__':
    raise SystemExit(main())
