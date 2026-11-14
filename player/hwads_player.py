#!/usr/bin/env python3
"""One persistent mpv window; atomically rereads HWADS playlists between placements."""
import argparse
from datetime import datetime, timezone
import fcntl
import json
import logging
from logging.handlers import RotatingFileHandler
import os
from pathlib import Path
import select
import signal
import socket
import subprocess
import tempfile
import time

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

def items(root):
    try:
        data = json.loads((root / 'config/playlist.json').read_text())
        if isinstance(data, list):
            rows = data  # Existing local playlist format remains usable before first sync.
        else:
            primary = [x for x in data['primary'] if active(x)]
            house = [x for x in data['house'] if active(x)]
            rows = primary + house if primary and data.get('house_in_rotation') else primary or house
        rows = [x for x in rows if active(x) and media_path(root, x).is_file()]
        if rows:
            return sorted(rows, key=lambda x: x.get('order', 999999))
    except (OSError, ValueError, TypeError, KeyError):
        pass
    return [{'file': str(p.resolve()), 'type': 'image', 'duration': 7, 'placeholder': True} for p in sorted((root / 'placeholders').glob('*')) if p.suffix.lower() in ('.jpg', '.jpeg', '.png', '.webp') and p.is_file()]

def media_path(root, item):
    if item.get('placeholder'):
        p = Path(item['file']).resolve()
        return p if p.parent == (root / 'placeholders').resolve() else root / '.invalid-media'
    p = (root / 'media' / item['file']).resolve()
    return p if p.parent == (root / 'media').resolve() else root / '.invalid-media'

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
                cursor, buffer = 0, b''
                while not STOP and not disabled.exists() and process.poll() is None:
                    rows = items(root)
                    if not rows:
                        atomic_json(state, {'state': 'idle', 'updated_at': time.time(), 'version_id': 0})
                        time.sleep(2)
                        continue
                    item = rows[cursor % len(rows)]
                    cursor += 1
                    # Discard prior stop events before loading the next item.
                    while select.select([client], [], [], 0)[0]:
                        if not client.recv(65536):
                            return 1
                    buffer = b''
                    client.sendall((json.dumps({'command': ['loadfile', str(media_path(root, item)), 'replace']}) + '\n').encode())
                    started, ended, last_status = False, False, 0
                    # Image timer starts after file-loaded; videos run to end-file.
                    loaded_at, requested_at = None, time.monotonic()
                    duration = float(item.get('duration') or 7)
                    while not STOP and not disabled.exists() and process.poll() is None and not ended:
                        now = time.monotonic()
                        if now - last_status >= 10:
                            atomic_json(state, {'state': 'playing' if loaded_at else 'idle', 'updated_at': time.time(), 'version_id': item.get('version_id', 0) if loaded_at else 0, 'placement_id': item.get('placement_id')})
                            last_status = now
                        if loaded_at and item.get('type') != 'video' and now - loaded_at >= max(1, duration):
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
                                if event.get('event') == 'end-file' and started:
                                    ended = True
                    if process.poll() is None:
                        client.sendall(b'{"command":["stop"]}\n')
                    if not loaded_at:
                        time.sleep(1)  # Avoid a tight loop if a local file cannot decode.
            except (OSError, ValueError, RuntimeError) as error:
                logging.error('Player stopped (%s)', type(error).__name__)
                return 1
            finally:
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
