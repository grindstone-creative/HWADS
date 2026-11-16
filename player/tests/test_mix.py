import json,random,sys,tempfile,unittest
from pathlib import Path
from unittest.mock import patch
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from hwads_player import PlaybackMixer,items
from hwads_sync import atomic_json
class MixTests(unittest.TestCase):
 def setUp(self):
  t=tempfile.TemporaryDirectory();self.addCleanup(t.cleanup);self.root=Path(t.name)
  for n in ('config','media','placeholders'):(self.root/n).mkdir()
  self.rows=[]
  for g,w,ds in [('A',60,[5,8,12]),('B',30,[60,90,120]),('C',10,[10,11,15])]:
   for i,d in enumerate(ds):
    f=g+str(i)+'.jpg';(self.root/'media'/f).write_bytes(b'test');self.rows.append(dict(file=f,mix_group=g,mix_weight=w,mix_signature='s',placement_id=len(self.rows)+1,version_id=42,duration=d,analytics_grant='original-grant'))
  self.m=PlaybackMixer(self.root,random.Random(2))
 def simulate(self,rows,n=3000,fixed=None):
  totals={};turns={};token='';elapsed=0;last=None
  with patch('hwads_player.atomic_json'):
   for _ in range(n):
    row,token=self.m.select(rows,token,elapsed);k=self.m.key(row);self.assertNotEqual(k,last);last=k;elapsed=row['duration'] if fixed is None else fixed;totals[row['mix_group']]=totals.get(row['mix_group'],0)+elapsed;turns[k]=turns.get(k,0)+1
  return totals,turns
 def test_actual_time_shares_with_long_videos_no_repeats_and_fair_turns(self):
  totals,turns=self.simulate(self.rows,6000);total=sum(totals.values())
  for g,w in [('A',.6),('B',.3),('C',.1)]:
   self.assertAlmostEqual(totals[g]/total,w,delta=.01);counts=[turns.get(self.m.key(r),0) for r in self.rows if r['mix_group']==g];self.assertLessEqual(max(counts)-min(counts),1)
 def test_declared_duration_does_not_drive_balance(self):
  totals,_=self.simulate([dict(r,duration=10000) for r in self.rows],1000,2);self.assertAlmostEqual(totals['A']/sum(totals.values()),.6,delta=.015)
 def test_active_groups_rebalance(self):
  totals,_=self.simulate([r for r in self.rows if r['mix_group']!='C']);self.assertAlmostEqual(totals['A']/sum(totals.values()),2/3,delta=.015)
 def test_restart_preserves_balance_bags_and_repeat_guard(self):
  row,t=self.m.select(self.rows);self.m.select(self.rows,t,90);before=json.loads(self.m.path.read_text());restored=PlaybackMixer(self.root,random.Random(3));self.assertEqual(restored.state['balances'],before['balances']);row,t=restored.select(self.rows);self.assertNotEqual(restored.key(row),before['last']);self.assertEqual(restored.state['scope'],before['scope'])
 def test_request_retries_do_not_double_credit_or_consume_bag(self):
  row,t=self.m.select(self.rows,request_id='first');self.m.select(self.rows,t,30,request_id='second');before=json.loads(self.m.path.read_text());r,t2=self.m.select(self.rows,t,30,request_id='second');self.assertEqual(before,self.m.state);r,t3=self.m.select(self.rows,t,30,request_id='retry');self.assertEqual(t2,t3);self.assertEqual(self.m.state['balances'],before['balances'])
 def test_fallback_only_for_empty_or_failed_mix(self):
  f=dict(self.rows[2],placement_id=100,mix_group='fallback',mix_weight=100,mix_fallback=True)
  row,t=self.m.select(self.rows+[f]);self.assertNotEqual(row['mix_group'],'fallback');row,t=self.m.select([f],t,10);self.assertEqual(row['mix_group'],'fallback')
  row,t=self.m.select([self.rows[0],f]);row,t=self.m.select([self.rows[0],f],t,0,failed=True);self.assertEqual(row['mix_group'],'fallback')
 def test_left_arrow_uses_played_history(self):
  a,t=self.m.select(self.rows);b,t=self.m.select(self.rows,t,10);back,t=self.m.select(self.rows,t,3,navigation=-1);self.assertEqual(self.m.key(a),self.m.key(back));self.assertNotEqual(self.m.key(b),self.m.key(back))
 def test_feedback_is_bounded_and_state_has_no_grants(self):
  for x in [-1,float('nan'),float('inf'),86401,'20',True]:
   with self.assertRaises(ValueError):self.m.select(self.rows,elapsed=x)
  self.m.select(self.rows);self.assertNotIn('original-grant',self.m.path.read_text())
 def test_scheduled_missing_media_and_fallback_filtering(self):
  a=dict(self.rows[0],start_utc='2099-01-01T00:00:00Z');b=dict(self.rows[1],file='missing.jpg');f=self.rows[2]
  data={'playback_mix':{'enabled':True,'signature':'x','groups':[{'playlist_id':10,'weight':100,'items':[a,b]}],'fallback':[f]}};atomic_json(self.root/'config/playlist.json',data);self.assertEqual(items(self.root)[0]['mix_group'],'fallback')
  a.pop('start_utc');atomic_json(self.root/'config/playlist.json',data);self.assertEqual(items(self.root)[0]['mix_group'],'10');self.assertEqual(len(items(self.root)),1);self.assertEqual(len(items(self.root,True)),2)
 def test_empty_mix_uses_local_placeholders(self):
  (self.root/'placeholders/local.png').write_bytes(b'img');atomic_json(self.root/'config/playlist.json',{'playback_mix':{'enabled':True,'groups':[],'fallback':[]}});self.assertTrue(items(self.root)[0]['placeholder']);self.assertEqual(items(self.root)[0]['mix_group'],'local')
 def test_legacy_primary_house_retains_order(self):
  rows=[{k:v for k,v in r.items() if not k.startswith('mix_')} for r in self.rows[:3]];atomic_json(self.root/'config/playlist.json',{'primary':[dict(rows[0],order=2),dict(rows[1],order=1)],'house':[rows[2]],'house_in_rotation':False});self.assertEqual([r['file'] for r in items(self.root)],[rows[1]['file'],rows[0]['file']]);self.assertFalse(any('mix_group' in r for r in items(self.root)))

 def test_corrupt_saved_state_recovers_and_disk_full_does_not_stop_playback(self):
  atomic_json(self.m.path,{'balances':[],'pending':[]});restored=PlaybackMixer(self.root);row,t=restored.select(self.rows);self.assertTrue(row)
  atomic_json(self.m.path,{'cursor':'broken','history':[{}]});restored=PlaybackMixer(self.root);row,t=restored.select(self.rows);self.assertTrue(row)
  with patch('hwads_player.atomic_json',side_effect=OSError('disk full')):row,t=restored.select(self.rows,t,10);self.assertTrue(row)
 def test_local_placeholder_is_last_resort_after_decode_failures(self):
  fallback=dict(self.rows[1],placement_id=100,mix_group='fallback',mix_weight=100,mix_fallback=True)
  local=dict(self.rows[2],placement_id=101,mix_group='local',mix_weight=100,mix_fallback=True,mix_local=True)
  row,t=self.m.select([fallback,local]);self.assertEqual(row['mix_group'],'fallback')
  row,t=self.m.select([fallback,local],t,0,failed=True);self.assertEqual(row['mix_group'],'local')

 def test_retried_normal_selection_does_not_turn_into_duplicate_fallback(self):
  row=self.rows[0];fallback=dict(row,mix_group='fallback',mix_fallback=True)
  a,t=self.m.select([row,fallback],request_id='same');b,t2=self.m.select([row,fallback],request_id='same');self.assertEqual(t,t2);self.assertEqual(b['mix_group'],'A')
