import json
from pathlib import Path
import sqlite3
import sys
import tempfile
import time
import unittest
from unittest.mock import patch
import uuid
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
import hwads_sync as analytics


def observation(**changes):
    return dict(play_id=str(uuid.uuid4()), placement_id=10, version_id=20, seq=1, elapsed_ms=0,
                started_at=time.time(), outcome='playing', engine='browser', grant='signed-fixture', **changes)


class AnalyticsTests(unittest.TestCase):
    def setUp(self):
        self.temp=tempfile.TemporaryDirectory(prefix='hwads-analytics-')
        self.root=Path(self.temp.name)
        self.recorder=analytics.Recorder(self.root)

    def tearDown(self):
        self.recorder.close()
        self.temp.cleanup()

    def rows(self):
        self.recorder.pending.join()
        with analytics.database(self.root) as db:
            return [json.loads(r[0]) for r in db.execute('SELECT payload FROM plays')]

    def test_cumulative_checkpoints_coalesce_and_survive_restart(self):
        row=observation();self.assertTrue(self.recorder.submit(row))
        self.recorder.submit(dict(row,seq=3,elapsed_ms=7000,outcome='completed'))
        self.recorder.submit(dict(row,seq=2,elapsed_ms=5000))
        self.assertEqual(len(self.rows()),1)
        self.assertEqual(self.rows()[0]['elapsed_ms'],7000)
        self.recorder.close()
        with analytics.database(self.root) as db:
            self.assertEqual(db.execute('SELECT seq FROM plays').fetchone()[0],3)

    def test_network_failure_retains_queue(self):
        self.recorder.submit(observation());self.rows()
        def failed(*args):raise OSError('offline')
        with self.assertRaises(OSError):analytics.Delivery(self.root,'https://fixture',lambda:{},failed).flush()
        self.assertEqual(len(self.rows()),1)

    def test_acknowledgement_does_not_delete_newer_checkpoint(self):
        row=observation();self.recorder.submit(row);self.rows()
        def request(url,headers,body):
            self.assertEqual(url,'https://fixture/events')
            payload=json.loads(body);self.assertEqual(payload['plays'][0]['seq'],1)
            self.recorder.submit(dict(row,seq=2,elapsed_ms=5000));self.rows()
            return {'acknowledged':[{'play_id':row['play_id'],'seq':1}]},''
        analytics.Delivery(self.root,'https://fixture',lambda:{},request).flush()
        self.assertEqual(self.rows()[0]['seq'],2)

    def test_matching_ack_removes_persisted_row(self):
        row=observation();self.recorder.submit(row);self.rows()
        def request(*args):return {'acknowledged':[{'play_id':row['play_id'],'seq':1}]},''
        analytics.Delivery(self.root,'https://fixture',lambda:{},request).flush()
        self.assertEqual(self.rows(),[])

    def test_rejected_record_preserved_without_blocking_later_uploads(self):
        row=observation();self.recorder.submit(row);self.rows()
        def request(*args):return {'rejected':[{'play_id':row['play_id'],'code':'clock'}]},''
        analytics.Delivery(self.root,'https://fixture',lambda:{},request).flush()
        self.assertEqual(self.rows(),[])
        self.assertEqual(analytics.status(self.root)['rejected'],1)

    def test_test_device_excludes_recording(self):
        self.recorder.close()
        (self.root/'config').mkdir(exist_ok=True)
        (self.root/'config/device.json').write_text('{"analytics_enabled":false}')
        self.recorder=analytics.Recorder(self.root)
        self.recorder.submit(observation())
        self.assertEqual(self.rows(),[])

    def test_bounded_queue_reports_loss(self):
        with patch.object(analytics,'MAX_PLAYS',1):
            self.recorder.submit(observation());self.rows()
            self.recorder.submit(observation());self.rows()
            # A successful checkpoint persists the outstanding loss counter.
            row=self.rows()[0];self.recorder.submit(dict(row,seq=2));self.rows()
        self.assertEqual(len(self.rows()),1)
        self.assertEqual(analytics.status(self.root)['dropped'],1)

    def test_invalid_observations_rejected(self):
        row=observation()
        for data in [dict(row,elapsed_ms=-1),dict(row,started_at=float('nan')),dict(row,seq=True),dict(row,outcome='scan'),dict(row,play_id='bad')]:
            self.assertFalse(self.recorder.submit(data))

    def test_unpublished_local_placeholder_never_counts(self):
        play=analytics.Play(self.recorder,{'file':'placeholder.jpg'},'mpv');play.add(7);play.save('completed')
        self.assertEqual(self.rows(),[])

    def test_play_uses_cumulative_time_and_final_result(self):
        play=analytics.Play(self.recorder,{'placement_id':10,'version_id':20,'analytics_grant':'signed'},'mpv')
        play.add(2.5);play.add(-10);play.add(4.5);play.save('completed')
        row=self.rows()[0]
        self.assertEqual(row['elapsed_ms'],7000);self.assertEqual(row['outcome'],'completed')


class MPVObservationTests(unittest.TestCase):
    def test_real_image_loop_records_completion_without_network_or_real_mpv(self):
        import hwads_player as player
        with tempfile.TemporaryDirectory(prefix='hwads-mpv-observe-') as directory:
            root=Path(directory);(root/'config').mkdir();(root/'media').mkdir()
            (root/'media/a.jpg').write_bytes(b'fixture')
            (root/'config/playlist.json').write_text(json.dumps([{'file':'a.jpg','type':'image','duration':1,'placement_id':1,'version_id':2,'analytics_grant':'signed'}]))
            class Process:
                def poll(self):return None
                def terminate(self):pass
                def wait(self,timeout=None):return 0
            class Socket:
                data=b''
                def connect(self,path):pass
                def setblocking(self,value):pass
                def close(self):pass
                def recv(self,size):data=self.data;self.data=b'';return data
                def sendall(self,raw):
                    command=json.loads(raw)['command']
                    if command[0]=='loadfile':self.data=b'{"event":"start-file"}\n{"event":"file-loaded"}\n'
                    if command[0]=='stop':player.STOP=True
            client=Socket()
            def select(reads,writes,errors,timeout):
                if timeout==0:return [],[],[]
                if client.data:return [client],[],[]
                time.sleep(.05);return [],[],[]
            player.STOP=False
            with patch.object(sys,'argv',['hwads_player.py','--root',str(root)]),patch.object(player.subprocess,'Popen',return_value=Process()),patch.object(player.socket,'socket',return_value=client),patch.object(player.select,'select',side_effect=select):
                self.assertEqual(player.main(),0)
            player.STOP=False
            with analytics.database(root) as db:
                rows=[json.loads(r[0]) for r in db.execute('SELECT payload FROM plays')]
            self.assertEqual(len(rows),1)
            self.assertEqual(rows[0]['outcome'],'completed')
            self.assertGreaterEqual(rows[0]['elapsed_ms'],1000)
            self.assertLess(rows[0]['elapsed_ms'],1500)

    def test_video_eof_and_errors_are_distinguished(self):
        import hwads_player as player
        for reason in ('eof','error','stop'):
            with self.subTest(reason=reason), tempfile.TemporaryDirectory(prefix='hwads-video-observe-') as directory:
                root=Path(directory);(root/'config').mkdir();(root/'media').mkdir()
                (root/'media/a.mp4').write_bytes(b'fixture')
                (root/'config/playlist.json').write_text(json.dumps([{'file':'a.mp4','type':'video','placement_id':1,'version_id':2,'analytics_grant':'signed'}]))
                class Process:
                    def poll(self):return None
                    def terminate(self):pass
                    def wait(self,timeout=None):return 0
                class Socket:
                    data=[]
                    def connect(self,path):pass
                    def setblocking(self,value):pass
                    def close(self):pass
                    def recv(self,size):return (json.dumps(self.data.pop(0))+'\n').encode()
                    def sendall(self,raw):
                        cmd=json.loads(raw)['command']
                        if cmd[0]=='loadfile':self.data=[{'event':'start-file'},{'event':'file-loaded'},{'event':'property-change','name':'time-pos','data':0},{'event':'property-change','name':'time-pos','data':.05},{'event':'property-change','name':'time-pos','data':.10},{'event':'end-file','reason':reason}]
                        if cmd[0]=='stop':player.STOP=True
                client=Socket()
                def select(reads,writes,errors,timeout):
                    if timeout==0:return [],[],[]
                    time.sleep(.05)
                    return ([client],[],[]) if client.data else ([],[],[])
                player.STOP=False
                with patch.object(sys,'argv',['hwads_player.py','--root',str(root)]),patch.object(player.subprocess,'Popen',return_value=Process()),patch.object(player.socket,'socket',return_value=client),patch.object(player.select,'select',side_effect=select):
                    self.assertEqual(player.main(),0)
                player.STOP=False
                with analytics.database(root) as db:
                    rows=[json.loads(r[0]) for r in db.execute('SELECT payload FROM plays')]
                self.assertEqual(len(rows),1)
                self.assertEqual(rows[0]['outcome'],{'eof':'completed','error':'error','stop':'interrupted'}[reason])
                self.assertGreater(rows[0]['elapsed_ms'],0)
                self.assertLessEqual(rows[0]['elapsed_ms'],100)

if __name__=='__main__':unittest.main()
