import json
from pathlib import Path
import sys
import tempfile
import threading
import unittest
import urllib.request
import urllib.error
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from hwads_browser import LocalServer
from hwads_sync import Sync,atomic_json

class BrowserServerTests(unittest.TestCase):
    def setUp(self):
        self.tmp=tempfile.TemporaryDirectory();self.addCleanup(self.tmp.cleanup);self.root=Path(self.tmp.name)
        for d in ('config','media','placeholders'):(self.root/d).mkdir()
        self.row={'file':'a.mp4','type':'video','duration':None,'placement_id':2,'version_id':3,'qr_enabled':True,'qr_url':'https://example.test/?hpads_ad=2','transition_in':'wipe-circle','transition_out':'fade'}
        (self.root/'media/a.mp4').write_bytes(b'0123456789')
        atomic_json(self.root/'config/playlist.json',{'primary':[self.row],'house':[]})
        (self.root/'config/device.json').write_text('SECRET')
        self.server=LocalServer(self.root,Path(__file__).resolve().parents[1]/'browser')
        self.thread=threading.Thread(target=self.server.serve_forever,daemon=True);self.thread.start();self.addCleanup(self.close)
        self.base='http://127.0.0.1:'+str(self.server.server_port)
        self.url=self.base+'/'+self.server.token+'/'
    def close(self):self.server.shutdown();self.server.server_close();self.thread.join()
    def test_manifest_qr_transitions_no_credentials(self):
        with urllib.request.urlopen(self.url+'playlist') as r:data=json.load(r)
        self.assertTrue(data[0]['qr_enabled']);self.assertEqual(data[0]['transition_in'],'wipe-circle');self.assertNotIn('SECRET',json.dumps(data))
        with self.assertRaises(urllib.error.HTTPError) as e:urllib.request.urlopen(self.url+'config/device.json')
        self.assertEqual(e.exception.code,404)
    def test_media_byte_ranges_for_video(self):
        with urllib.request.urlopen(urllib.request.Request(self.url+'media/a.mp4',headers={'Range':'bytes=2-5'})) as r:
            self.assertEqual(r.status,206);self.assertEqual(r.read(),b'2345');self.assertEqual(r.headers['Content-Range'],'bytes 2-5/10')
    def test_wrong_token_host_and_origin_rejected(self):
        for req in [urllib.request.Request(self.base+'/wrong/playlist'),urllib.request.Request(self.url+'playlist',headers={'Host':'evil.test'})]:
            with self.assertRaises(urllib.error.HTTPError) as e:urllib.request.urlopen(req)
            self.assertEqual(e.exception.code,404)
        with self.assertRaises(urllib.error.HTTPError) as e:urllib.request.urlopen(urllib.request.Request(self.url+'status',data=b'{}',headers={'Origin':'https://evil.test'}))
        self.assertEqual(e.exception.code,403)
    def test_status_stores_playing_files_and_engine(self):
        req=urllib.request.Request(self.url+'status',data=json.dumps({'state':'playing','placement_id':2,'version_id':3,'media_files':['a.mp4']}).encode(),headers={'Origin':self.base,'Content-Type':'application/json'})
        with urllib.request.urlopen(req):pass
        data=json.loads((self.root/'config/player-state.json').read_text());self.assertEqual(data['engine'],'browser');self.assertEqual(data['media_files'],['a.mp4'])
    def test_purge_keeps_active_previous_playback_placeholders_and_young_files(self):
        import os,time
        for name in ['old.jpg','previous.jpg','playing.jpg','young.jpg']:(self.root/'media'/name).write_bytes(b'old')
        for name in ['old.jpg','previous.jpg','playing.jpg']:os.utime(self.root/'media'/name,(time.time()-90000,time.time()-90000))
        atomic_json(self.root/'config/previous-playlist.json',{'primary':[{'file':'previous.jpg'}],'house':[]})
        atomic_json(self.root/'config/player-state.json',{'media_files':['playing.jpg']})
        client=Sync({'site_url':'https://example.test','device_id':7,'device_key':'a'*43},self.root)
        self.assertEqual(client.purge(),(1,3))
        self.assertFalse((self.root/'media/old.jpg').exists())
        for name in ['a.mp4','previous.jpg','playing.jpg','young.jpg']:self.assertTrue((self.root/'media'/name).exists())

if __name__=='__main__':unittest.main()
