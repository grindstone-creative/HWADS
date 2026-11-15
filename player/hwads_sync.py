#!/usr/bin/env python3
"""HWADS device sync. Python 3 standard library; credentials never sent to media hosts."""
import argparse
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

VERSION = '0.2.0'

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

class Sync:
    def __init__(self, config, root):
        self.config, self.root = config, Path(root).expanduser()
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
        for slot in ('primary', 'house'):
            manifest = payload.get(slot)
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
                    rows.append({'file': self.download(item), 'type': 'video' if video else 'image', 'duration': None if video else duration,
                                 'active': True, 'order': len(rows), 'start_utc': item.get('start_utc'), 'end_utc': item.get('end_utc'),
                                 'transition_in': item.get('transition_in', 'none'), 'transition_out': item.get('transition_out', 'none'),
                                 'qr_enabled': bool(item.get('qr_enabled')) and bool(item.get('placement_id')),
                                 'qr_url': self.config['site_url'] + '/?' + urllib.parse.urlencode({'hpads_ad':item.get('placement_id'), 'hwads_source':'qr'}) if item.get('placement_id') else '',
                                 'placement_id': item.get('placement_id'), 'revision': item.get('placement_revision'), 'version_id': manifest['version_id']})
            slots[slot] = rows
        # Single atomic activation: all media already verified. Existing files retained for offline playback.
        active = {'schema': 1, 'device_id': payload['device_id'], 'orientation': orientation, 'house_in_rotation': bool(payload.get('house_in_rotation')),
                  'versions': versions, **slots}
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
        playing = player.get('state') == 'playing' and time.time() - player.get('updated_at', 0) < 45
        body = {'active_version': player.get('version_id', 0) if playing else 0, 'player_version': 'HWADS-Pi/' + VERSION,
                'state': 'playing' if playing else ('error' if error else 'idle'), 'error': 'Sync failed; keeping last complete playlist' if error else str(player.get('error') or '')[:160],
                'placement_id': player.get('placement_id', 0) if playing else 0, 'engine': player.get('engine', 'mpv'),
                'last_sync_utc': int(self.state.get('synced_at', 0)), 'disk_free_bytes': shutil.disk_usage(self.root).free, 'sync_version': VERSION, 'purge_command_id':self.state.get('purge_command_id',''), 'purge_files':self.state.get('purge_files',0), 'purge_bytes':self.state.get('purge_bytes',0)}
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
            rows = data if isinstance(data,list) else data.get('primary',[]) + data.get('house',[])
            protected.update(row['file'] for row in rows)
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
    args = parser.parse_args()
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
            return Sync(load_config(args.config), root).run(args.once)
        except Exception as error:
            logging.error('Configuration failure (%s)', type(error).__name__)
            print('Configuration error: ' + str(error))
            return 1

if __name__ == '__main__':
    raise SystemExit(main())
