import hashlib
import io
import json
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import patch
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
import hwads_sync as sync
import hwads_player as player

class Response(io.BytesIO):
    headers = {}
    def __enter__(self): return self
    def __exit__(self,*_): self.close()

class SyncTests(unittest.TestCase):
    def setUp(self):
        self.tmp=tempfile.TemporaryDirectory();self.addCleanup(self.tmp.cleanup)
        self.root=Path(self.tmp.name)
        self.client=sync.Sync({'site_url':'https://example.test/HPADS','device_id':7,'device_key':'a'*43},self.root)
        self.data=b'image bytes'
        self.item={'sha256':hashlib.sha256(self.data).hexdigest(),'bytes':len(self.data),'mime':'image/jpeg','url':'https://cdn.test/a.jpg','orientation':'horizontal','duration_seconds':9,'placement_id':2,'placement_revision':3}
        self.payload={'schema':1,'device_id':7,'orientation':'horizontal','primary':{'schema':1,'version_id':4,'items':[self.item]},'house':None,'heartbeat_seconds':300}
    def complete(self):
        with patch.object(sync,'request',return_value=(self.payload,'"one"')),patch.object(sync.OPENER,'open',return_value=Response(self.data)):
            self.assertTrue(self.client.sync())
    def test_complete_activation_duration_and_credential_separation(self):
        with patch.object(sync,'request',return_value=(self.payload,'"one"')) as api,patch.object(sync.OPENER,'open',return_value=Response(self.data)) as media:
            self.client.sync()
        value=json.loads(self.client.active_path.read_text())
        self.assertEqual(value['primary'][0]['duration'],9)
        self.assertEqual(value['versions'],{'primary':4})
        self.assertIn('Authorization',api.call_args.args[1])
        self.assertFalse(media.call_args.args[0].has_header('Authorization'))
    def test_failed_second_download_never_activates_partial_update(self):
        self.complete();before=self.client.active_path.read_bytes()
        self.payload['primary']['version_id']=5
        self.payload['primary']['items'].append(dict(self.item,sha256='b'*64))
        with patch.object(sync,'request',return_value=(self.payload,'"two"')),patch.object(sync.OPENER,'open',return_value=Response(b'bad')):
            with self.assertRaises(ValueError):self.client.sync()
        self.assertEqual(before,self.client.active_path.read_bytes())
        self.assertEqual(self.client.etag,'"one"')
        self.assertFalse(list(self.client.media.glob('*.part')))
    def test_offline_retains_last_good_manifest(self):
        self.complete();before=self.client.active_path.read_bytes()
        with patch.object(sync,'request',side_effect=OSError('offline')):
            self.assertEqual(self.client.run(once=True),1)
        self.assertEqual(before,self.client.active_path.read_bytes())
    def test_etag_unchanged_does_not_rewrite_or_download(self):
        self.complete();before=self.client.active_path.stat().st_mtime_ns
        with patch.object(sync,'request',return_value=(None,'')) as api,patch.object(self.client,'download') as download:
            self.assertFalse(self.client.sync());download.assert_not_called()
            self.assertEqual(api.call_args.args[1]['If-None-Match'],'"one"')
        self.assertEqual(before,self.client.active_path.stat().st_mtime_ns)
    def test_orientation_filter(self):
        self.payload['primary']['items']=[dict(self.item,orientation='vertical')]
        with patch.object(sync,'request',return_value=(self.payload,'"one"')),patch.object(self.client,'download') as download:
            self.client.sync();download.assert_not_called()
        self.assertEqual(json.loads(self.client.active_path.read_text())['primary'],[])
    def test_corrupt_local_cache_downloaded_again(self):
        self.complete();file=next(self.client.media.iterdir());file.write_bytes(b'x'*len(self.data))
        with patch.object(sync,'request',return_value=(self.payload,'"two"')),patch.object(sync.OPENER,'open',return_value=Response(self.data)) as download:
            self.client.sync();download.assert_called_once()
        self.assertEqual(file.read_bytes(),self.data)
    def test_device_and_https_validation(self):
        for url in ['http://example.test','https://user:pass@example.test','file:///tmp/a']:
            with self.assertRaises(ValueError):sync.secure_url(url)
        self.payload['device_id']=8
        with patch.object(sync,'request',return_value=(self.payload,'tag')):
            with self.assertRaises(ValueError):self.client.sync()
    def test_empty_assignment_activates_empty_without_old_ads(self):
        self.complete();self.payload['primary']=None
        with patch.object(sync,'request',return_value=(self.payload,'"empty"')):self.client.sync()
        self.assertEqual(json.loads(self.client.active_path.read_text())['primary'],[])
    def test_video_full_length_and_schedule_fallback(self):
        self.item.update(mime='video/mp4',duration_seconds=None,start_utc='2999-01-01T00:00:00Z')
        self.complete()
        self.assertIsNone(json.loads(self.client.active_path.read_text())['primary'][0]['duration'])
        self.assertEqual(player.items(self.root),[])
        (self.root/'placeholders').mkdir();(self.root/'placeholders/house.jpg').write_bytes(b'house')
        self.assertTrue(player.items(self.root)[0]['placeholder'])
    def test_schedule_and_path_traversal(self):
        self.assertFalse(player.active({'end_utc':'2000-01-01T00:00:00Z'}))
        self.assertFalse(player.active({'start_utc':'2999-01-01T00:00:00Z'}))
        self.assertFalse(player.active({'start_utc':'invalid'}))
        self.assertFalse(player.active({'active':False}))
        self.assertEqual(player.media_path(self.root,{'file':'../config/device.json'}),self.root/'.invalid-media')
    def test_heartbeat_reports_player_not_download(self):
        self.complete()
        with patch.object(sync,'request',return_value=({},'')) as api:
            self.client.heartbeat()
            self.assertEqual(json.loads(api.call_args.args[2])['state'],'idle')
            sync.atomic_json(self.root/'config/player-state.json',{'state':'playing','updated_at':sync.time.time(),'version_id':4})
            self.client.heartbeat()
            self.assertEqual(json.loads(api.call_args.args[2])['active_version'],4)

    def test_overlay_download_is_verified_and_atomic(self):
        self.complete();before=self.client.active_path.read_bytes()
        overlay=dict(self.item,type='image',bytes=4,sha256=hashlib.sha256(b'logo').hexdigest(),url='https://cdn.test/logo.jpg',x=5,y=5,width=20)
        self.item['overlays']=[overlay,{'type':'text','text':'Special','x':5,'y':5,'width':40,'size':4}]
        with patch.object(sync,'request',return_value=(self.payload,'new')),patch.object(sync.OPENER,'open',return_value=Response(b'bad!')):
            with self.assertRaises(ValueError):self.client.sync()
        self.assertEqual(before,self.client.active_path.read_bytes())
        with patch.object(sync,'request',return_value=(self.payload,'new')),patch.object(sync.OPENER,'open',return_value=Response(b'logo')):
            self.client.sync()
        data=json.loads(self.client.active_path.read_text())['primary'][0]
        self.assertTrue((self.client.media/data['overlays'][0]['file']).is_file())
        self.assertNotIn('url',data['overlays'][0]);self.assertEqual(data['overlays'][1]['text'],'Special')

    def test_overlay_purge_preserves_active_assets(self):
        self.complete();logo=self.client.media/'logo.png';logo.write_bytes(b'logo')
        data=json.loads(self.client.active_path.read_text());data['primary'][0]['overlays']=[{'type':'image','file':'logo.png'}];sync.atomic_json(self.client.active_path,data)
        import os
        os.utime(logo,(0,0));self.client.purge();self.assertTrue(logo.exists())

if __name__=='__main__':unittest.main()
