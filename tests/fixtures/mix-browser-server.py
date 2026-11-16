import base64,json,sys,tempfile,threading,shutil
from pathlib import Path
scripts=Path(__file__).resolve().parents[2]/'player';sys.path.insert(0,str(scripts))
from hwads_browser import LocalServer
from hwads_sync import atomic_json
with tempfile.TemporaryDirectory(prefix='hwads-mix-browser-') as name:
 root=Path(name)
 for d in ('media','config','placeholders'):(root/d).mkdir()
 img=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=')
 for file in ('a.png','b.png'):(root/'media'/file).write_bytes(img)
 shutil.copyfile('/private/tmp/hwads-validation/test.webm',root/'media/video.webm')
 def row(file,pid,vid,kind):return dict(file=file,type=kind,duration=1,placement_id=pid,version_id=vid,revision=1,analytics_grant='fixture-grant-'+str(vid),width=1920,height=1080,transition_in='none',transition_out='none',overlays=[])
 atomic_json(root/'config/device.json',{'analytics_enabled':True})
 atomic_json(root/'config/playlist.json',{'playback_mix':{'enabled':True,'signature':'browser-fixture','groups':[{'playlist_id':1,'weight':60,'items':[row('a.png',11,101,'image'),row('b.png',12,101,'image')]},{'playlist_id':2,'weight':40,'items':[row('video.webm',21,202,'video')]}],'fallback':[]}})
 server=LocalServer(root,scripts/'browser');thread=threading.Thread(target=server.serve_forever,daemon=True);thread.start();print(json.dumps({'url':'http://127.0.0.1:'+str(server.server_port)+'/'+server.token+'/','root':name}),flush=True)
 try:sys.stdin.read()
 finally:server.shutdown();server.server_close();thread.join()
