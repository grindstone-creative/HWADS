#!/usr/bin/env python3
"""HWADS device sync. Python 3 standard library; credentials never sent to media hosts."""
import argparse
from contextlib import closing
import math
import queue
import sqlite3
import uuid
import threading
import fcntl
import hashlib
import json
import logging
from logging.handlers import RotatingFileHandler
import os
from pathlib import Path
import random
import shutil
import re
import time
import urllib.error
import urllib.parse
import urllib.request

VERSION = '0.5.4'

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        raise ValueError('Redirect refused; configure the final HTTPS API/media URL')

OPENER = urllib.request.build_opener(NoRedirect)

def atomic_json(path, value):
    path = Path(path)
    path.parent.mkdir(parents=True, exist_ok=True)
    tmp = path.with_name(path.name + '.tmp')
    try:
        with open(tmp, 'w') as out:
            json.dump(value, out, separators=(',', ':'))
            out.flush()
            os.fsync(out.fileno())
        os.replace(tmp, path)
    finally:
        tmp.unlink(missing_ok=True)

def secure_url(url):
    u = urllib.parse.urlsplit(url)
    if u.scheme != 'https' or not u.hostname or u.username or u.password or u.fragment:
        raise ValueError('A final HTTPS URL without credentials or fragments is required')
    return url

def request(url, headers=None, body=None, limit=4 * 1024 * 1024):
    secure_url(url)
    req = urllib.request.Request(url, headers=headers or {}, data=body)
    try:
        with OPENER.open(req, timeout=30) as response:
            raw = response.read(limit + 1)
            if len(raw) > limit:
                raise ValueError('Response too large')
            return json.loads(raw), response.headers.get('ETag', '')
    except urllib.error.HTTPError as error:
        if error.code == 304:
            return None, ''
        # Do not include URLs/tokens/server response bodies in device logs.
        raise RuntimeError('API HTTP ' + str(error.code)) from None

def load_config(path):
    path = Path(path).expanduser()
    if path.stat().st_mode & 0o077:
        raise ValueError('Device config must be private: chmod 600 ' + str(path))
    c = json.loads(path.read_text())
    c['site_url'] = secure_url(c['site_url'].rstrip('/'))
    if urllib.parse.urlsplit(c['site_url']).query:
        raise ValueError('site_url must be the WordPress installation URL without a query')
    if not isinstance(c['device_id'], int) or c['device_id'] < 1:
        raise ValueError('Invalid device ID')
    if not re.fullmatch(r'[A-Za-z0-9_-]{43}', c['device_key']):
        raise ValueError('Invalid device key')
    return c

MAX_PLAYS = 100000


def database(root):
    path = Path(root) / 'config' / 'analytics.sqlite3'
    path.parent.mkdir(parents=True, exist_ok=True)
    db = sqlite3.connect(path, timeout=1)
    db.execute('PRAGMA journal_mode=WAL')
    db.execute('PRAGMA synchronous=FULL')
    db.execute('CREATE TABLE IF NOT EXISTS plays (id TEXT PRIMARY KEY, seq INTEGER NOT NULL, payload TEXT NOT NULL, changed REAL NOT NULL)')
    db.execute('CREATE TABLE IF NOT EXISTS rejected (id TEXT PRIMARY KEY, payload TEXT NOT NULL, reason TEXT NOT NULL)')
    db.execute('CREATE TABLE IF NOT EXISTS health (name TEXT PRIMARY KEY, value INTEGER NOT NULL)')
    return db


def validate(data):
    if not isinstance(data, dict):
        raise ValueError('Invalid observation')
    uuid.UUID(data['play_id'])
    if data.get('engine') not in ('browser', 'mpv') or data.get('outcome') not in ('playing', 'completed', 'interrupted', 'error'):
        raise ValueError('Invalid playback result')
    for key in ('placement_id', 'version_id', 'seq', 'elapsed_ms'):
        if type(data.get(key)) is not int or data[key] < (1 if key != 'elapsed_ms' else 0):
            raise ValueError('Invalid playback number')
    if data['elapsed_ms'] > 86400000 or data['seq'] > 100000:
        raise ValueError('Observation exceeds limits')
    if not isinstance(data.get('started_at'), (int, float)) or not math.isfinite(data['started_at']):
        raise ValueError('Invalid playback time')
    grant = data.get('grant', '')
    if not isinstance(grant, str) or len(grant) > 1024:
        raise ValueError('Invalid attribution')
    return {k: data[k] for k in ('play_id','placement_id','version_id','seq','elapsed_ms','started_at','outcome','engine','grant')}


class Recorder:
    def __init__(self, root):
        self.root = Path(root)
        try:
            self.enabled = json.loads((self.root/'config/device.json').read_text()).get('analytics_enabled', True) is not False
        except (OSError, ValueError):
            self.enabled = True
        self.pending = queue.Queue(maxsize=1024)
        self.stopping = threading.Event()
        self.dropped = 0
        self.thread = threading.Thread(target=self.run, daemon=True)
        self.thread.start()

    def submit(self, data):
        if not self.enabled:
            return True
        try:
            self.pending.put_nowait(validate(data))
            return True
        except (queue.Full, ValueError, KeyError, TypeError, AttributeError):
            self.dropped += 1
            return False

    def run(self):
        db = None
        while not self.stopping.is_set() or not self.pending.empty():
            try:
                row = self.pending.get(timeout=.2)
            except queue.Empty:
                continue
            try:
                if db is None:
                    db = database(self.root)
                exists = db.execute('SELECT seq FROM plays WHERE id=?', (row['play_id'],)).fetchone()
                if not exists and db.execute('SELECT (SELECT COUNT(*) FROM plays)+(SELECT COUNT(*) FROM rejected)').fetchone()[0] >= MAX_PLAYS:
                    raise ValueError('Analytics queue full')
                db.execute('INSERT INTO plays VALUES (?,?,?,?) ON CONFLICT(id) DO UPDATE SET seq=excluded.seq,payload=excluded.payload,changed=excluded.changed WHERE excluded.seq>plays.seq',
                           (row['play_id'], row['seq'], json.dumps(row), time.time()))
                if self.dropped:
                    db.execute("INSERT INTO health VALUES ('dropped',?) ON CONFLICT(name) DO UPDATE SET value=value+excluded.value", (self.dropped,))
                    self.dropped = 0
                db.commit()
            except (OSError, sqlite3.Error, ValueError):
                self.dropped += 1
                if db:
                    db.rollback()
                logging.warning('Analytics observation not saved; playback continues')
            finally:
                self.pending.task_done()
        if db:
            db.close()

    def close(self):
        self.stopping.set()
        self.thread.join(timeout=2)


class Play:
    def __init__(self, recorder, item, engine):
        self.recorder = recorder
        self.enabled = bool(item.get('placement_id') and item.get('version_id') and item.get('analytics_grant'))
        self.row = dict(play_id=str(uuid.uuid4()), placement_id=item.get('placement_id'), version_id=item.get('version_id'),
                        grant=item.get('analytics_grant', ''), seq=0, elapsed_ms=0, started_at=time.time(), outcome='playing', engine=engine)
        self.elapsed = 0.0
        self.last_save = time.monotonic()
        self.save()

    def add(self, seconds):
        self.elapsed += max(0, seconds)
        if time.monotonic() - self.last_save >= 5:
            self.save()

    def save(self, outcome='playing'):
        if not self.enabled:
            return
        self.row.update(seq=self.row['seq']+1, elapsed_ms=int(min(86400, self.elapsed)*1000), outcome=outcome)
        self.recorder.submit(dict(self.row))
        self.last_save = time.monotonic()


def purge_pending(root, command):
    """Idempotent command receipt; delete only unchanged snapshot checkpoints."""
    if not isinstance(command, dict) or not re.fullmatch(r'[a-f0-9-]{36}', str(command.get('id', ''))):
        return None
    records = command.get('records')
    if not isinstance(records, list) or len(records) > 50:
        return None
    with closing(database(root)) as db:
        db.execute('CREATE TABLE IF NOT EXISTS purge_receipts (id TEXT PRIMARY KEY, deleted INTEGER NOT NULL)')
        db.execute('BEGIN IMMEDIATE')
        prior = db.execute('SELECT deleted FROM purge_receipts WHERE id=?', (command['id'],)).fetchone()
        if prior:
            return {'id': command['id'], 'deleted': prior[0]}
        deleted = 0
        for record in records:
            if not isinstance(record, dict) or type(record.get('seq')) is not int or not isinstance(record.get('play_id'), str):
                continue
            row = db.execute('SELECT payload FROM plays WHERE id=? AND seq=?', (record['play_id'], record['seq'])).fetchone()
            if row:
                deleted += db.execute('DELETE FROM plays WHERE id=? AND seq=?', (record['play_id'], record['seq'])).rowcount
        db.execute('INSERT INTO purge_receipts VALUES (?,?)', (command['id'], deleted))
        db.commit()
        return {'id': command['id'], 'deleted': deleted}


class Delivery:
    """Independent worker; conditional acknowledgement cannot delete a newer checkpoint."""
    def __init__(self, root, api, headers, request):
        self.root, self.api, self.headers, self.request = root, api, headers, request
        self.stop = threading.Event()

    def flush(self):
        with closing(database(self.root)) as db:
            rows = db.execute('SELECT id,seq,payload FROM plays ORDER BY changed LIMIT 100').fetchall()
            response, _ = self.request(self.api+'/events', {**self.headers(), 'Content-Type':'application/json'},
                                       json.dumps({'schema':1,'plays':[json.loads(r[2]) for r in rows], 'health':status(self.root)}).encode())
            sent = {(r[0], r[1]) for r in rows}
            for ack in response.get('acknowledged', []):
                key = (ack.get('play_id'), ack.get('seq'))
                if key in sent:
                    db.execute('DELETE FROM plays WHERE id=? AND seq=?', key)
            rejected = response.get('rejected', [])
            for rejection in rejected:
                for play_id, seq, payload in rows:
                    if play_id == rejection.get('play_id'):
                        db.execute('INSERT OR REPLACE INTO rejected VALUES (?,?,?)', (play_id, payload, str(rejection.get('code', 'unknown'))[:100]))
                        db.execute('DELETE FROM plays WHERE id=? AND seq=?', (play_id, seq))
            if rejected:
                logging.warning('Analytics server rejected %s observations; preserved in rejected table', len(rejected))
            db.commit()
            purge_ack = purge_pending(self.root, response.get('queue_purge'))
            if purge_ack:
                self.request(self.api+'/events', {**self.headers(), 'Content-Type':'application/json'}, json.dumps({'schema':1, 'plays':[], 'health':status(self.root), 'purge_ack':purge_ack}).encode())
            snapshot_request=response.get('queue_snapshot_request')
            if isinstance(snapshot_request,dict) and re.fullmatch(r'[a-f0-9-]{36}',str(snapshot_request.get('id',''))):
                try:
                    snapshot=pending_records(self.root,include_grants=True);snapshot.pop('database',None);snapshot['request_id']=snapshot_request['id']
                    self.request(self.api+'/events',{**self.headers(),'Content-Type':'application/json'},json.dumps({'schema':1,'plays':[],'health':status(self.root),'queue_snapshot':snapshot}).encode())
                except (OSError,ValueError,sqlite3.Error,RuntimeError):logging.warning('Pending-play snapshot unavailable; request can retry')
            return len(response.get('acknowledged', []))

    def run(self):
        import random
        delay = 60
        while not self.stop.is_set():
            try:
                count = self.flush()
                delay = 1 if count >= 100 else 60
            except Exception as error:
                logging.warning('Analytics upload unavailable (%s); queue retained', type(error).__name__)
                delay = min(3600, max(60, delay*2))
            self.stop.wait(delay + random.uniform(0, min(10, delay/5)))


def status(root):
    with closing(database(root)) as db:
        pending, oldest = db.execute('SELECT COUNT(*),MIN(changed) FROM plays').fetchone()
        return {'pending':pending, 'oldest_pending_utc':oldest, 'rejected':db.execute('SELECT COUNT(*) FROM rejected').fetchone()[0],
                'dropped':dict(db.execute('SELECT name,value FROM health')).get('dropped',0)}




def pending_records(root, limit=50, include_grants=False):
    """Read-only diagnostic snapshot; never creates an empty DB at a mistyped root."""
    path=(Path(root).expanduser()/'config/analytics.sqlite3').resolve()
    result={'exists':path.is_file(),'database':str(path),'captured_at':time.time(),'pending':0,'records':[]}
    if not result['exists']:return result
    with closing(sqlite3.connect(path.as_uri()+'?mode=ro',uri=True,timeout=1)) as db:
        db.execute('BEGIN')
        result['pending']=db.execute('SELECT COUNT(*) FROM plays').fetchone()[0]
        for play_id,seq,payload,changed in db.execute('SELECT id,seq,payload,changed FROM plays ORDER BY changed,id LIMIT ?', (max(1,min(50,int(limit))),)):
            p=json.loads(payload)
            row={k:p.get(k) for k in ('placement_id','version_id','started_at','elapsed_ms','outcome','engine')}
            row.update(play_id=play_id,seq=seq,updated_at=changed)
            if include_grants:row['grant']=p.get('grant','')
            result['records'].append(row)
    return result

class SystemHealth:
    """Small Linux snapshots; unavailable probes remain null and never block playback."""
    def __init__(self, proc='/proc', thermal='/sys/class/thermal/thermal_zone0/temp'):
        self.proc=Path(proc);self.thermal=Path(thermal);self.previous=self.cpu_ticks()
    def cpu_ticks(self):
        try:
            line=(self.proc/'stat').read_text().splitlines()[0].split()
            if line[0]!='cpu' or len(line)<5:return None
            ticks=[int(x) for x in line[1:9]]
            if any(x<0 for x in ticks):return None
            return sum(ticks),ticks[3]+(ticks[4] if len(ticks)>4 else 0)
        except (OSError,ValueError,IndexError):return None
    def sample(self):
        data={'cpu_percent':None,'cpu_cores':os.cpu_count(),'load_average':None,'memory_total_bytes':None,'memory_available_bytes':None,'memory_used_bytes':None,'memory_used_percent':None,'temperature_c':None,'uptime_seconds':None}
        ticks=self.cpu_ticks()
        if ticks and self.previous:
            total,idle=ticks[0]-self.previous[0],ticks[1]-self.previous[1]
            if total>0 and 0<=idle<=total:data['cpu_percent']=round(100*(total-idle)/total,1)
        self.previous=ticks
        try:
            fields={}
            for line in (self.proc/'meminfo').read_text().splitlines():
                match=re.fullmatch(r'([A-Za-z_]+):\s+(\d+)\s+kB',line)
                if match:fields[match[1]]=int(match[2])*1024
            total=fields.get('MemTotal',0)
            if total>0 and ('MemAvailable' in fields or 'MemFree' in fields):
                available=fields.get('MemAvailable',sum(fields.get(k,0) for k in ('MemFree','Buffers','Cached','SReclaimable'))-fields.get('Shmem',0))
                available=max(0,min(total,available));used=total-available
                data.update(memory_total_bytes=total,memory_available_bytes=available,memory_used_bytes=used,memory_used_percent=round(100*used/total,1))
        except (OSError,ValueError):pass
        try:
            value=float(self.thermal.read_text().strip())/1000
            if math.isfinite(value) and -40<=value<=150:data['temperature_c']=round(value,1)
        except (OSError,ValueError):pass
        try:
            value=float((self.proc/'uptime').read_text().split()[0])
            if math.isfinite(value) and value>=0:data['uptime_seconds']=int(value)
        except (OSError,ValueError,IndexError):pass
        try:
            values=os.getloadavg()
            if len(values)==3 and all(math.isfinite(v) and v>=0 for v in values):data['load_average']=[round(v,2) for v in values]
        except (OSError,AttributeError):pass
        return data

class Sync:
    def __init__(self, config, root):
        self.config, self.root = config, Path(root).expanduser()
        self.system_health=SystemHealth()
        self.media = self.root / 'media'
        self.media.mkdir(parents=True, exist_ok=True)
        (self.root / 'config').mkdir(exist_ok=True)
        self.api = config['site_url'] + '/wp-json/hpads/v1/devices/' + str(config['device_id'])
        self.state_path = self.root / 'config/sync-state.json'
        self.active_path = self.root / 'config/playlist.json'
        try:
            self.state = json.loads(self.state_path.read_text())
        except (OSError, ValueError):
            self.state = {}
        self.interval = max(60, min(3600, int(self.state.get('interval', 300))))
        self.etag = self.state.get('etag', '') if self.active_path.exists() and self.state.get('client_version') == VERSION else ''

    def headers(self):
        return {'Authorization': 'Bearer ' + self.config['device_key'], 'Accept': 'application/json', 'User-Agent': 'HWADS-Sync/' + VERSION}

    def download(self, item):
        sha, size = item['sha256'], item['bytes']
        if not re.fullmatch('[a-f0-9]{64}', sha) or not isinstance(size, int) or size <= 0:
            raise ValueError('Invalid media checksum/size')
        if size > int(self.config.get('max_media_bytes', 2 * 1024 ** 3)):
            raise ValueError('Media exceeds configured download limit')
        mime = item.get('mime', '')
        ext = {'image/jpeg': '.jpg', 'image/png': '.png', 'image/webp': '.webp', 'image/gif': '.gif', 'video/mp4': '.mp4', 'video/webm': '.webm', 'video/quicktime': '.mov'}.get(mime)
        if not ext:
            raise ValueError('Unsupported media type')
        target = self.media / (sha + ext)
        if target.exists() and target.stat().st_size == size:
            # Verify each selected file once per changed manifest, never every polling cycle.
            with target.open('rb') as source:
                digest = hashlib.file_digest(source, 'sha256').hexdigest() if hasattr(hashlib, 'file_digest') else hash_stream(source)
            if digest == sha:
                return target.name
        secure_url(item['url'])
        part = target.with_suffix(target.suffix + '.part')
        try:
            # Separate request: never attach Authorization to a media/CDN URL.
            req = urllib.request.Request(item['url'], headers={'User-Agent': 'HWADS-Sync/' + VERSION})
            with OPENER.open(req, timeout=60) as source, part.open('wb') as output:
                h, received = hashlib.sha256(), 0
                while True:
                    chunk = source.read(256 * 1024)
                    if not chunk:
                        break
                    received += len(chunk)
                    if received > size:
                        raise ValueError('Media size mismatch')
                    output.write(chunk)
                    h.update(chunk)
                if received != size or h.hexdigest() != sha:
                    raise ValueError('Media checksum/size mismatch')
                output.flush()
                os.fsync(output.fileno())
            os.replace(part, target)
        finally:
            part.unlink(missing_ok=True)
        return target.name

    def sync(self):
        headers = self.headers()
        if self.etag:
            headers['If-None-Match'] = self.etag
        payload, etag = request(self.api + '/playlist', headers)
        if payload is None:
            self.state['synced_at'] = time.time()
            atomic_json(self.state_path, self.state)
            return False
        if payload.get('schema') != 1 or payload.get('device_id') != self.config['device_id']:
            raise ValueError('Unexpected manifest/device')
        orientation = payload.get('orientation')
        if orientation not in ('horizontal', 'vertical'):
            raise ValueError('Invalid orientation')
        slots, versions = {}, {}
        manifests = {slot: payload.get(slot) for slot in ('primary', 'house')}
        mix = payload.get('playback_mix') or {'enabled': False}
        mix_groups = []
        if mix.get('enabled'):
            groups = mix.get('groups')
            if mix.get('schema') != 1 or not isinstance(groups, list) or not 1 <= len(groups) <= 20:
                raise ValueError('Invalid Playback mix')
            seen = set()
            for group in groups:
                if not isinstance(group,dict):raise ValueError('Invalid mix row')
                pid, weight = group.get('playlist_id'), group.get('weight')
                if type(pid) is not int or pid <= 0 or pid in seen or type(weight) is not int or not 1 <= weight <= 100:
                    raise ValueError('Invalid mix playlist/weight')
                seen.add(pid);key = 'mix:' + str(pid)
                manifest = group.get('manifest')
                if manifest is not None and not isinstance(manifest,dict):raise ValueError('Invalid mix manifest')
                if manifest and manifest.get('playlist_id') != pid:raise ValueError('Wrong mix playlist')
                manifests[key] = manifest;mix_groups.append({'playlist_id': pid, 'weight': weight, 'slot': key})
            if sum(g['weight'] for g in mix_groups) != 100:raise ValueError('Mix shares must total 100')
            manifests['mix:fallback'] = mix.get('fallback')
        for slot, manifest in manifests.items():
            rows = []
            if manifest:
                if manifest.get('schema') != 1 or not isinstance(manifest.get('items'), list):
                    raise ValueError('Invalid playlist manifest')
                versions[slot] = int(manifest['version_id'])
                for item in manifest['items']:
                    if item.get('orientation') != orientation:
                        continue
                    duration = item.get('duration_seconds')
                    video = item.get('mime', '').startswith('video/')
                    if not video and (not isinstance(duration, (float, int)) or duration <= 0):
                        raise ValueError('Invalid image duration')
                    overlays = []
                    raw_overlays = item.get('overlays', [])
                    if not isinstance(raw_overlays, list) or len(raw_overlays) > 8:
                        raise ValueError('Invalid overlays')
                    for overlay in raw_overlays:
                        if not isinstance(overlay, dict) or overlay.get('type') not in ('text','image'):
                            raise ValueError('Invalid overlay')
                        visual = {k: overlay.get(k) for k in ('type','x','y','width','text','size','color','background','align','font')}
                        if overlay['type'] == 'image':
                            if overlay.get('mime') not in ('image/jpeg','image/png','image/webp') or overlay.get('bytes', 0) > 5*1024*1024:
                                raise ValueError('Invalid overlay image')
                            visual['file'] = self.download(overlay)
                        overlays.append(visual)
                    rows.append({'file': self.download(item), 'type': 'video' if video else 'image', 'duration': None if video else duration,
                                 'overlays': overlays, 'qr_layout': item.get('qr_layout', {'size':15,'x':82,'y':70}), 'width':item.get('width'), 'height':item.get('height'),
                                 'active': True, 'order': len(rows), 'start_utc': item.get('start_utc'), 'end_utc': item.get('end_utc'),
                                 'transition_in': item.get('transition_in', 'none'), 'transition_out': item.get('transition_out', 'none'),
                                 'qr_enabled': bool(item.get('qr_enabled')) and bool(item.get('placement_id')),
                                 'qr_url': self.config['site_url'] + '/?' + urllib.parse.urlencode({'hpads_ad':item.get('placement_id'), 'hwads_source':'qr'}) if item.get('placement_id') else '',
                                 'analytics_grant': manifest.get('analytics_grant', ''), 'placement_id': item.get('placement_id'), 'version_id': manifest['version_id']})
            slots[slot] = rows
        # Single atomic activation: all media already verified. Existing files retained for offline playback.
        active = {'schema': 1, 'device_id': payload['device_id'], 'orientation': orientation, 'house_in_rotation': bool(payload.get('house_in_rotation')),
                  'versions': versions, **slots}
        if mix.get('enabled'):
            active['playback_mix'] = {'enabled': True, 'signature': str(mix.get('signature', ''))[:128],
                'groups': [dict(playlist_id=g['playlist_id'], weight=g['weight'], items=slots[g['slot']]) for g in mix_groups],
                'fallback': slots.get('mix:fallback', [])}
            for key in list(active):
                if key.startswith('mix:'):del active[key]
        if self.active_path.exists():
            atomic_json(self.root / 'config/previous-playlist.json', json.loads(self.active_path.read_text()))
        atomic_json(self.active_path, active)
        self.interval = max(60, min(3600, int(payload.get('heartbeat_seconds', 300))))
        self.etag = etag
        self.state = {**self.state, 'client_version': VERSION, 'etag': etag, 'versions': versions, 'interval': self.interval, 'synced_at': time.time()}
        atomic_json(self.state_path, self.state)
        logging.info('Activated complete playlist version(s): %s', versions)
        return True

    def heartbeat(self, error=False):
        try:
            player = json.loads((self.root / 'config/player-state.json').read_text())
        except (OSError, ValueError):
            player = {}
        fresh = time.time() - player.get('updated_at', 0) < 45
        disabled = (self.root / 'config/player.disabled').exists()
        playing = not disabled and fresh and player.get('state') == 'playing'
        state = 'disabled' if disabled else ('playing' if playing else 'error' if error or fresh and player.get('state') == 'error' else 'idle')
        body = {'active_version': player.get('version_id', 0) if playing else 0, 'player_version': 'HWADS-Pi/' + VERSION,
                'state': state, 'error': 'Sync failed; keeping last complete playlist' if error else str(player.get('error') or '')[:160],
                'placement_id': player.get('placement_id', 0) if playing else 0, 'engine': player.get('engine', 'unknown'),
                'system_health':self.system_health.sample(), 'last_sync_utc': int(self.state.get('synced_at', 0)), 'disk_free_bytes': shutil.disk_usage(self.root).free, 'sync_version': VERSION, 'purge_command_id':self.state.get('purge_command_id',''), 'purge_files':self.state.get('purge_files',0), 'purge_bytes':self.state.get('purge_bytes',0)}
        response, _ = request(self.api + '/heartbeat', {**self.headers(), 'Content-Type': 'application/json'}, json.dumps(body).encode())
        for command in response.get('commands', []):
            if command.get('type') == 'purge_unused_media' and command.get('id') != self.state.get('purge_command_id'):
                files, size = self.purge()
                self.state.update(purge_command_id=str(command['id']),purge_files=files,purge_bytes=size)
                atomic_json(self.state_path,self.state)

    def purge(self):
        protected = set()
        # If either playlist cannot be parsed, do not guess what is safe to delete.
        for path in (self.active_path, self.root / 'config/previous-playlist.json'):
            if not path.exists():
                continue
            data = json.loads(path.read_text())
            rows = data if isinstance(data,list) else data.get('primary',[]) + data.get('house',[]) + [item for group in data.get('playback_mix',{}).get('groups',[]) for item in group.get('items',[])] + data.get('playback_mix',{}).get('fallback',[])
            protected.update(row['file'] for row in rows)
            protected.update(o['file'] for row in rows for o in row.get('overlays',[]) if o.get('file'))
        try:
            player = json.loads((self.root / 'config/player-state.json').read_text())
            protected.update(player.get('media_files',[]))
        except (OSError,ValueError):
            pass
        count, size = 0, 0
        for path in self.media.iterdir():
            if path.is_symlink() or not path.is_file() or path.name in protected or path.suffix == '.part':
                continue
            stat = path.stat()
            if time.time()-stat.st_mtime < 86400:
                continue
            path.unlink();count+=1;size+=stat.st_size
        logging.info('Purged %s unused media files (%s bytes)',count,size)
        return count,size

    def run(self, once=False):
        # Analytics has its own background network worker; sync/publication timing is unchanged.
        import threading
        delivery = Delivery(self.root, self.api, self.headers, request)
        if not once and self.config.get('analytics_enabled', True):
            threading.Thread(target=delivery.run, daemon=True).start()
        while True:
            failed = False
            try:
                self.sync()
            except Exception as error:
                failed = True
                logging.error('Sync failed (%s); retaining last complete playlist', type(error).__name__)
            try:
                self.heartbeat(failed)
            except Exception as error:
                logging.warning('Heartbeat unavailable (%s)', type(error).__name__)
            if once:
                return 1 if failed else 0
            time.sleep(self.interval + random.uniform(0, 30))

def update_loop(client):
    from hwads_update import status, verify_command, atomic, read
    last_error=0
    while True:
        interval=30
        try:
            info=status(client.root)
            runtime=read(client.root/'config/updater-runtime.json')
            info['ready']=(client.root/'bootstrap/hwads_update.py').is_file() and client.root.joinpath('scripts').is_symlink() and time.time()-runtime.get('updated_at',0)<45
            response,_=request(client.api+'/player-update',{**client.headers(),'Content-Type':'application/json'},json.dumps(info).encode())
            # A successful authenticated round trip proves the restarted sync process is alive.
            boot=read(client.root/'config/sync-runtime.json')
            if boot.get('version')==VERSION:atomic(client.root/'config/sync-runtime.json',{**boot,'healthy_at':time.time()})
            command=response.get('command')
            if command and info['ready']:
                verify_command(command,client.config)
                if not (info.get('id')==command['id'] and info.get('status') in ('completed','failed','rolled_back')):
                    atomic(client.root/'config/pending-player-update.json',command)
            if command or info.get('status') in ('downloading','validating','installing','awaiting_health'):interval=5
        except Exception as error:
            if time.time()-last_error>300:logging.warning('Player update polling unavailable (%s)',type(error).__name__);last_error=time.time()
            interval=5 if read(client.root/'config/player-update.json').get('status')=='awaiting_health' else 30
        time.sleep(interval)


def hash_stream(source):
    h = hashlib.sha256()
    for chunk in iter(lambda: source.read(256 * 1024), b''):
        h.update(chunk)
    return h.hexdigest()

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--root', default='~/hpads')
    parser.add_argument('--config', default='~/hpads/config/device.json')
    parser.add_argument('--once', action='store_true')
    parser.add_argument('--analytics-pending',action='store_true',help='Print up to 50 pending plays from the read-only queue database and exit')
    parser.add_argument('--analytics-status', action='store_true', help='Print analytics queue health and exit')
    args = parser.parse_args()
    if args.analytics_pending:
        print(json.dumps(pending_records(Path(args.root).expanduser()),indent=2));return 0
    if args.analytics_status:
        print(json.dumps(status(Path(args.root).expanduser())))
        return 0
    root = Path(args.root).expanduser()
    root.mkdir(parents=True, exist_ok=True)
    with (root / '.sync.lock').open('w') as lock:
        try:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            return 0
        (root / 'logs').mkdir(exist_ok=True)
        logging.basicConfig(level=logging.INFO, handlers=[RotatingFileHandler(root / 'logs/sync.log', maxBytes=1024*1024, backupCount=2)])
        try:
            client=Sync(load_config(args.config), root)
            if not args.once:
                from hwads_update import atomic
                atomic(root/'config/sync-runtime.json',{'pid':os.getpid(),'version':VERSION,'started_at':time.time()})
                threading.Thread(target=update_loop,args=(client,),daemon=True).start()
            return client.run(args.once)
        except Exception as error:
            logging.error('Configuration failure (%s)', type(error).__name__)
            print('Configuration error: ' + str(error))
            return 1

if __name__ == '__main__':
    raise SystemExit(main())
