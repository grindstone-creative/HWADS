import contextlib,json,sys,tempfile,unittest,uuid
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
import hwads_sync as sync
class PurgeTests(unittest.TestCase):
 def test_purges_snapshot_preserves_newer_and_retries(self):
  with tempfile.TemporaryDirectory() as root:
   ids=[str(uuid.uuid4()) for _ in range(3)]
   with contextlib.closing(sync.database(root)) as db:
    for i,(seq,outcome) in enumerate([(1,'completed'),(2,'completed'),(1,'playing')]):db.execute('INSERT INTO plays VALUES (?,?,?,?)',(ids[i],seq,json.dumps({'outcome':outcome}),0))
    db.commit()
   command={'id':str(uuid.uuid4()),'records':[{'play_id':i,'seq':1} for i in ids]}
   self.assertEqual(sync.purge_pending(root,command)['deleted'],2)
   self.assertEqual(sync.purge_pending(root,command)['deleted'],2)
   with contextlib.closing(sync.database(root)) as db:self.assertEqual({r[0] for r in db.execute('SELECT id FROM plays')},{ids[1]})
 def test_delivery_acknowledges_command(self):
  with tempfile.TemporaryDirectory() as root:
   calls=[];command={'id':str(uuid.uuid4()),'records':[]}
   def request(url,headers,body):
    calls.append(json.loads(body));return ({'queue_purge':command} if len(calls)==1 else {}),''
   sync.Delivery(root,'https://fixture.test',lambda:{},request).flush()
   self.assertEqual(calls[1]['purge_ack'],{'id':command['id'],'deleted':0})
