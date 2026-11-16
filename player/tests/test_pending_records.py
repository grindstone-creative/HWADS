import contextlib,json,sys,tempfile,unittest,uuid
from pathlib import Path
from unittest.mock import patch
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
import hwads_sync as sync
class PendingTests(unittest.TestCase):
 def setUp(self):
  t=tempfile.TemporaryDirectory();self.addCleanup(t.cleanup);self.root=Path(t.name);self.id=str(uuid.uuid4());self.p={'play_id':self.id,'seq':1,'placement_id':12,'version_id':34,'started_at':1700000000,'elapsed_ms':1000,'outcome':'playing','engine':'browser','grant':'sensitive-attribution'}
 def insert(self,p=None,changed=1):
  p=p or self.p
  with contextlib.closing(sync.database(self.root)) as db:db.execute('INSERT OR REPLACE INTO plays VALUES (?,?,?,?)',(p['play_id'],p['seq'],json.dumps(p),changed));db.commit()
 def test_missing_db_is_reported_without_creating_it(self):
  result=sync.pending_records(self.root);self.assertFalse(result['exists']);self.assertFalse((self.root/'config').exists());self.assertTrue(result['database'].endswith('config/analytics.sqlite3'))
 def test_snapshot_is_read_only_and_omits_grants(self):
  self.insert();p=self.root/'config/analytics.sqlite3';before=p.stat().st_mtime_ns;data=sync.pending_records(self.root);self.assertEqual(data['pending'],1);self.assertEqual(data['records'][0]['placement_id'],12);self.assertEqual(data['records'][0]['elapsed_ms'],1000);self.assertNotIn('grant',data['records'][0]);self.assertEqual(before,p.stat().st_mtime_ns)
 def test_snapshot_is_bounded_but_counts_all_pending(self):
  for i in range(60):self.insert(dict(self.p,play_id=str(uuid.uuid4())),i)
  result=sync.pending_records(self.root,1000);self.assertEqual(result['pending'],60);self.assertEqual(len(result['records']),50);self.assertEqual(result['records'][0]['updated_at'],0)
 def test_requested_snapshot_follows_ack_and_preserves_new_checkpoint(self):
  self.insert();calls=[];request_id=str(uuid.uuid4())
  def request(url,headers,body):
   value=json.loads(body);calls.append(value)
   if len(calls)==1:
    self.insert(dict(self.p,seq=2,elapsed_ms=2000),2)
    return {'acknowledged':[{'play_id':self.id,'seq':1}],'queue_snapshot_request':{'id':request_id}},''
   return {'acknowledged':[]},''
  delivery=sync.Delivery(self.root,'https://fixture.test',lambda:{},request);self.assertEqual(delivery.flush(),1);snapshot=calls[1]['queue_snapshot'];self.assertEqual(snapshot['pending'],1);self.assertEqual(snapshot['records'][0]['seq'],2);self.assertEqual(snapshot['request_id'],request_id);self.assertNotIn('database',snapshot)
 def test_no_request_sends_no_diagnostic_detail(self):
  self.insert();calls=[]
  def request(url,headers,body):calls.append(json.loads(body));return {'acknowledged':[]},''
  sync.Delivery(self.root,'https://fixture.test',lambda:{},request).flush();self.assertEqual(len(calls),1);self.assertNotIn('queue_snapshot',calls[0])
 def test_failed_diagnostic_post_does_not_restore_acknowledged_plays(self):
  self.insert();calls=[]
  def request(url,headers,body):
   calls.append(json.loads(body))
   if len(calls)>1:raise OSError('offline')
   return {'acknowledged':[{'play_id':self.id,'seq':1}],'queue_snapshot_request':{'id':str(uuid.uuid4())}},''
  self.assertEqual(sync.Delivery(self.root,'https://fixture.test',lambda:{},request).flush(),1);self.assertEqual(sync.pending_records(self.root)['pending'],0)
