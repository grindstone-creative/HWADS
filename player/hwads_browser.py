#!/usr/bin/env python3
"""Private loopback-only HWADS kiosk with offline media and bounded crash recovery."""
import argparse
import fcntl
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import json
import logging
from logging.handlers import RotatingFileHandler
import mimetypes
import os
from pathlib import Path
import secrets
import shutil
import signal
import subprocess
import threading
import time
import urllib.parse
from hwads_sync import atomic_json
from hwads_sync import Recorder
from hwads_player import items, media_path, PlaybackMixer

STOP = threading.Event()

class LocalServer(ThreadingHTTPServer):
    daemon_threads = True
    def __init__(self, root, assets):
        self.root, self.assets = root, assets
        self.analytics = Recorder(root)
        self.mixer = PlaybackMixer(root)
        self.token = secrets.token_urlsafe(24)
        self.last_ping = time.monotonic()
        self.started = self.last_ping
        super().__init__(('127.0.0.1', 0), Handler)

    def server_close(self):
        self.analytics.close()
        super().server_close()

class Handler(BaseHTTPRequestHandler):
    def log_message(self, *_):
        pass
    def allowed(self):
        # Require exact loopback Host + random path. No CORS, external listener or config route.
        return self.headers.get('Host') == '127.0.0.1:' + str(self.server.server_port)
    def send(self, data, content_type='application/json', status=200):
        self.send_response(status)
        self.send_header('Content-Type', content_type)
        self.send_header('Content-Length', str(len(data)))
        self.send_header('Cache-Control', 'no-store')
        self.send_header('X-Content-Type-Options', 'nosniff')
        self.send_header('Referrer-Policy', 'no-referrer')
        self.send_header('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self'; media-src 'self'; connect-src 'self'; object-src 'none'; frame-ancestors 'none'")
        self.end_headers()
        self.wfile.write(data)
    def route(self):
        if not self.allowed():
            return None
        prefix = '/' + self.server.token + '/'
        path = urllib.parse.urlsplit(self.path).path
        return urllib.parse.unquote(path[len(prefix):]) if path.startswith(prefix) else None
    def do_GET(self):
        route = self.route()
        if route is None:
            self.send(b'', 'text/plain', 404)
            return
        if route == 'playlist':
            rows = items(self.server.root)
            output = [self.row_output(row) for row in rows]
            self.send(json.dumps(output).encode())
            return
        if route.startswith('media/'):
            name = route[len('media/'):]
            rows = items(self.server.root, include_fallback=True)
            assets = rows + [o for row in rows for o in row.get('overlays',[]) if o.get('file')]
            matches = [x for x in assets if x['file'] == name]
            if not matches:
                self.send(b'', 'text/plain', 404)
                return
            self.serve_file(media_path(self.server.root, matches[0]), ranges=True)
            return
        name = 'index.html' if route == '' else route
        if name not in ('index.html','player.js','player.css','overlays.js','overlays.css','vendor/qrcodegen.js'):
            self.send(b'', 'text/plain', 404)
            return
        self.serve_file(self.server.assets / name)
    def row_output(self, row):
        value={k:row.get(k) for k in ('file','type','duration','start_utc','end_utc','placement_id','version_id','analytics_grant','transition_in','transition_out','qr_enabled','qr_url','qr_layout','overlays','width','height','mix_group','mix_weight','mix_signature','mix_fallback')}
        value['url']='media/'+urllib.parse.quote(row['file'],safe='')
        value['overlays']=[dict(o,**({'url':'media/'+urllib.parse.quote(o['file'],safe='')} if o.get('file') else {})) for o in row.get('overlays',[])]
        return value
    def serve_file(self, path, ranges=False):
        try:
            size = path.stat().st_size
            start, end, status = 0, size-1, 200
            if ranges and self.headers.get('Range'):
                import re
                match = re.fullmatch(r'bytes=(\d*)-(\d*)', self.headers['Range'])
                if not match or not any(match.groups()):
                    self.send(b'', 'text/plain', 416)
                    return
                if match[1]:
                    start = int(match[1]);end = min(int(match[2]), end) if match[2] else end
                else:
                    start = max(0, size-int(match[2]))
                if start > end or start >= size:
                    self.send_response(416);self.send_header('Content-Range','bytes */'+str(size));self.end_headers();return
                status = 206
            with path.open('rb') as source:
                self.send_response(status)
                self.send_header('Content-Type',mimetypes.guess_type(path.name)[0] or 'application/octet-stream')
                self.send_header('Content-Length',str(end-start+1))
                self.send_header('Cache-Control','no-store')
                self.send_header('X-Content-Type-Options','nosniff')
                self.send_header('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self'; media-src 'self'; connect-src 'self'; object-src 'none'; frame-ancestors 'none'")
                if ranges:
                    self.send_header('Accept-Ranges','bytes')
                if status == 206:
                    self.send_header('Content-Range',f'bytes {start}-{end}/{size}')
                self.end_headers();source.seek(start);left=end-start+1
                while left:
                    data=source.read(min(left,256*1024))
                    if not data:break
                    self.wfile.write(data);left-=len(data)
        except (OSError, BrokenPipeError):
            pass
    def do_POST(self):
        route = self.route()
        if route not in ('status', 'events', 'next'):
            self.send(b'', 'text/plain', 404);return
        expected = 'http://127.0.0.1:' + str(self.server.server_port)
        if self.headers.get('Origin') != expected:
            self.send(b'', 'text/plain', 403);return
        try:
            length=int(self.headers.get('Content-Length','0'))
            if length<=0 or length>(8192 if route=='events' else 2048):raise ValueError()
            data=json.loads(self.rfile.read(length))
            if route == 'next':
                row,token=self.server.mixer.select(items(self.server.root,include_fallback=True),
                    previous=str(data.get('previous',''))[:64],elapsed=data.get('elapsed',0),navigation=-1 if data.get('navigation')==-1 else 1,
                    request_id=str(data.get('request_id',''))[:64],failed=data.get('failed') is True)
                self.send(json.dumps({'item':self.row_output(row) if row else None,'token':token}).encode());return
            if route == 'events':
                ok = self.server.analytics.submit(data)
                self.send(b'{}', status=202 if ok else 503)
                return
            status={'state':data.get('state') if data.get('state') in ('playing','idle','error') else 'idle',
                    'updated_at':time.time(),'version_id':max(0,int(data.get('version_id') or 0)),
                    'placement_id':max(0,int(data.get('placement_id') or 0)), 'engine':'browser',
                    'error':str(data.get('error') or '')[:160], 'media_files':[str(x)[:256] for x in data.get('media_files',[])[:4]]}
            atomic_json(self.server.root/'config/player-state.json',status)
            self.server.last_ping=time.monotonic()
            self.send(b'{}')
        except (ValueError, TypeError, AttributeError):
            self.send(b'', 'text/plain', 400)

def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--root',default='~/hpads')
    args=parser.parse_args();root=Path(args.root).expanduser().resolve()
    for name in ('config','logs','media','placeholders'):(root/name).mkdir(parents=True,exist_ok=True)
    disabled=root/'config/player.disabled'
    if disabled.exists():return 0
    logging.basicConfig(level=logging.INFO,handlers=[RotatingFileHandler(root/'logs/browser.log',maxBytes=1024*1024,backupCount=2)])
    for sig in (signal.SIGTERM,signal.SIGINT):signal.signal(sig,lambda *_:STOP.set())
    with (root/'.player.lock').open('w') as lock:
        try:fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
        except BlockingIOError:return 0
        executable=shutil.which('chromium') or shutil.which('chromium-browser')
        if not executable:
            logging.error('Chromium missing; use hwads-control.py enable --engine mpv for fallback')
            return 1
        server=LocalServer(root,Path(__file__).parent/'browser')
        thread=threading.Thread(target=server.serve_forever,daemon=True);thread.start()
        url='http://127.0.0.1:'+str(server.server_port)+'/'+server.token+'/'
        command=[executable,'--password-store=basic','--kiosk','--no-first-run','--no-default-browser-check','--disable-session-crashed-bubble','--autoplay-policy=no-user-gesture-required',
                 '--user-data-dir='+str(root/'config/chromium-profile'),url]
        child=None;restarts=0
        try:
            while not STOP.is_set() and not disabled.exists():
                server.last_ping=time.monotonic()
                child=subprocess.Popen(command,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,start_new_session=True)
                launched=time.monotonic()
                while not STOP.wait(1) and not disabled.exists() and child.poll() is None:
                    if time.monotonic()-server.last_ping>120:
                        logging.error('Browser unresponsive; restarting');break
                try:os.killpg(child.pid,signal.SIGTERM)
                except ProcessLookupError:pass
                try:child.wait(timeout=5)
                except subprocess.TimeoutExpired:
                    os.killpg(child.pid,signal.SIGKILL);child.wait()
                if STOP.is_set() or disabled.exists():break
                restarts=1 if time.monotonic()-launched>600 else restarts+1
                if restarts>=3:
                    disabled.touch(mode=0o600)
                    logging.error('Three browser failures; playback disabled to prevent a restart loop')
                    break
                STOP.wait(10)
        finally:
            server.shutdown();server.server_close()
            atomic_json(root/'config/player-state.json',{'state':'idle','updated_at':time.time(),'version_id':0,'engine':'browser'})
    return 0

if __name__=='__main__':raise SystemExit(main())
