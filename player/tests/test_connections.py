"""Cross-flow regressions: legacy fallback, playback ordering and health snapshots."""
import json
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import patch
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import hwads_player as player
import hwads_sync as sync

class ConnectionTests(unittest.TestCase):
    def setUp(self):
        t=tempfile.TemporaryDirectory();self.addCleanup(t.cleanup);self.root=Path(t.name)
        for d in ('config','media','placeholders'):(self.root/d).mkdir()
        self.client=sync.Sync({'site_url':'https://example.test','device_id':7,'device_key':'a'*43},self.root)
    def row(self,name,order):
        (self.root/'media'/name).write_bytes(b'media')
        return {'file':name,'order':order}
    def test_missing_primary_uses_available_house(self):
        p=self.row('primary.jpg',0);h=self.row('house.jpg',0)
        (self.root/'media/primary.jpg').unlink()
        sync.atomic_json(self.client.active_path,{'primary':[p],'house':[h]})
        self.assertEqual(player.items(self.root),[h])
    def test_legacy_combination_preserves_each_playlist_order(self):
        p=[self.row('p2.jpg',1),self.row('p1.jpg',0)];h=[self.row('h2.jpg',1),self.row('h1.jpg',0)]
        sync.atomic_json(self.client.active_path,{'primary':p,'house':h,'house_in_rotation':True})
        self.assertEqual([x['file'] for x in player.items(self.root)],['p1.jpg','p2.jpg','h1.jpg','h2.jpg'])
    def heartbeat(self):
        with patch.object(sync,'request',return_value=({},'')) as api:
            self.client.heartbeat();return json.loads(api.call_args.args[2])
    def test_fresh_player_error_is_not_idle(self):
        sync.atomic_json(self.root/'config/player-state.json',{'state':'error','error':'Decode failed','updated_at':sync.time.time()})
        body=self.heartbeat();self.assertEqual(body['state'],'error');self.assertEqual(body['error'],'Decode failed')
    def test_local_disable_overrides_stale_playing_state(self):
        sync.atomic_json(self.root/'config/player-state.json',{'state':'playing','version_id':42,'updated_at':sync.time.time()})
        (self.root/'config/player.disabled').touch()
        body=self.heartbeat();self.assertEqual(body['state'],'disabled');self.assertEqual(body['active_version'],0)
    def test_playing_file_survives_purge_after_manifest_replacements(self):
        import os
        old=self.row('long-video.mp4',0);os.utime(self.root/'media'/old['file'],(0,0))
        sync.atomic_json(self.client.active_path,{'primary':[],'house':[]})
        sync.atomic_json(self.root/'config/player-state.json',{'media_files':[old['file']]})
        self.assertEqual(self.client.purge(),(0,0));self.assertTrue((self.root/'media'/old['file']).exists())
