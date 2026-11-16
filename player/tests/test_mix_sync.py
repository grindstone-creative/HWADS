import hashlib,io,json,sys,tempfile,unittest,os,time
from pathlib import Path
from unittest.mock import patch
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
import hwads_sync as sync
from hwads_player import items
class Response(io.BytesIO):
 def __enter__(self):return self
 def __exit__(self,*_):self.close()
class MixSyncTests(unittest.TestCase):
 def setUp(self):
  t=tempfile.TemporaryDirectory();self.addCleanup(t.cleanup);self.root=Path(t.name);self.client=sync.Sync({'site_url':'https://example.test','device_id':7,'device_key':'a'*43},self.root);self.media=b'img'
  self.item={'sha256':hashlib.sha256(self.media).hexdigest(),'bytes':3,'mime':'image/jpeg','url':'https://cdn.test/a.jpg','orientation':'horizontal','duration_seconds':8,'placement_id':20,'placement_revision':4}
  def manifest(pid,version):return {'schema':1,'playlist_id':pid,'version_id':version,'analytics_grant':'grant-'+str(version),'items':[dict(self.item,placement_id=pid)]}
  self.payload={'schema':1,'device_id':7,'orientation':'horizontal','primary':None,'house':None,'playback_mix':{'schema':1,'enabled':True,'signature':'s','groups':[{'playlist_id':1,'weight':60,'manifest':manifest(1,11)},{'playlist_id':2,'weight':40,'manifest':manifest(2,12)}],'fallback':manifest(3,13)}}
 def sync(self):
  with patch.object(sync,'request',return_value=(self.payload,'"mix"')),patch.object(sync.OPENER,'open',return_value=Response(self.media)):return self.client.sync()
 def test_atomic_mix_and_original_analytics_attribution(self):
  self.sync();data=json.loads(self.client.active_path.read_text());self.assertEqual([g['weight'] for g in data['playback_mix']['groups']],[60,40]);self.assertEqual(len(items(self.root)),2)
  for row,version in zip(items(self.root),[11,12]):self.assertEqual(row['version_id'],version);self.assertEqual(row['analytics_grant'],'grant-'+str(version));self.assertNotIn('revision',row)
 def test_incomplete_download_keeps_old_mix_and_etag(self):
  self.sync();before=self.client.active_path.read_bytes();self.payload['playback_mix']['groups'][1]['manifest']['items'][0]['sha256']='b'*64
  with self.assertRaises(ValueError):self.sync()
  self.assertEqual(before,self.client.active_path.read_bytes());self.assertEqual(self.client.etag,'"mix"')
 def test_invalid_weights_or_wrong_manifest_rejected(self):
  self.payload['playback_mix']['groups'][0]['weight']=59
  with self.assertRaises(ValueError):self.sync()
  self.assertFalse(self.client.active_path.exists());self.payload['playback_mix']['groups'][0]['weight']=60;self.payload['playback_mix']['groups'][0]['manifest']['playlist_id']=999
  with self.assertRaises(ValueError):self.sync()
 def test_purge_retains_mixed_and_fallback_media(self):
  self.sync();path=next(self.client.media.iterdir());os.utime(path,(time.time()-90000,)*2);self.assertEqual(self.client.purge(),(0,0));self.assertTrue(path.exists())
 def test_empty_orientation_group_is_available_for_later_offline_schedule(self):
  self.payload['playback_mix']['groups'][0]['manifest']['items'][0]['orientation']='vertical';self.sync();self.assertEqual(len(items(self.root)),1);self.assertEqual(items(self.root)[0]['mix_weight'],40)
