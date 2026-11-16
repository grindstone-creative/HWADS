import json,sys,tempfile,unittest
from pathlib import Path
from unittest.mock import patch
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
import hwads_sync as sync
class HealthTests(unittest.TestCase):
 def setUp(self):
  t=tempfile.TemporaryDirectory();self.addCleanup(t.cleanup);self.root=Path(t.name);self.proc=self.root/'proc';self.proc.mkdir();self.temp=self.root/'temperature';self.temp.write_text('53750');self.write_stat(100,100);(self.proc/'meminfo').write_text('MemTotal: 4000 kB\nMemAvailable: 1500 kB\nMemFree: 1000 kB\n');(self.proc/'uptime').write_text('12345.67 9000.0');self.reader=sync.SystemHealth(self.proc,self.temp)
 def write_stat(self,busy,idle): (self.proc/'stat').write_text('cpu '+str(busy)+' 0 0 '+str(idle)+' 0 0 0 0 999 999\ncpu0 1 2 3 4\n')
 def sample(self):
  with patch.object(sync.os,'getloadavg',return_value=(.25,.5,.75)),patch.object(sync.os,'cpu_count',return_value=4):return self.reader.sample()
 def test_memory_cpu_temperature_load_uptime(self):
  self.write_stat(150,150);d=self.sample();self.assertEqual(d['cpu_percent'],50);self.assertEqual(d['memory_total_bytes'],4096000);self.assertEqual(d['memory_used_bytes'],2560000);self.assertEqual(d['memory_used_percent'],62.5);self.assertEqual(d['temperature_c'],53.8);self.assertEqual(d['load_average'],[.25,.5,.75]);self.assertEqual(d['uptime_seconds'],12345);self.assertEqual(d['cpu_cores'],4)
 def test_no_cpu_ticks_yet_is_unknown_not_zero(self):self.assertIsNone(self.sample()['cpu_percent'])
 def test_counter_reset_is_unknown_and_recovers(self):
  self.write_stat(1,1);self.assertIsNone(self.sample()['cpu_percent']);self.write_stat(11,31);self.assertEqual(self.sample()['cpu_percent'],25)
 def test_memavailable_fallback(self):
  (self.proc/'meminfo').write_text('MemTotal: 4000 kB\nMemFree: 1000 kB\nBuffers: 100 kB\nCached: 300 kB\nSReclaimable: 200 kB\nShmem: 100 kB\n');self.assertEqual(self.sample()['memory_available_bytes'],1500*1024)
 def test_missing_linux_probes_do_not_stop_sample(self):
  for p in self.proc.iterdir():p.unlink()
  self.temp.unlink()
  with patch.object(sync.os,'getloadavg',side_effect=OSError('unsupported')):d=self.reader.sample()
  for k in ('cpu_percent','memory_used_bytes','temperature_c','uptime_seconds','load_average'):self.assertIsNone(d[k])
 def test_bad_probe_values_remain_unknown(self):
  self.temp.write_text('999999999');(self.proc/'uptime').write_text('nan');(self.proc/'stat').write_text('corrupt');self.assertIsNone(self.sample()['temperature_c']);self.assertIsNone(self.sample()['uptime_seconds']);self.assertIsNone(self.sample()['cpu_percent'])
 def test_heartbeat_carries_compact_readings(self):
  c=sync.Sync({'site_url':'https://fixture.test','device_id':1,'device_key':'a'*43},self.root/'device');c.system_health=self.reader
  with patch.object(sync,'request',return_value=({},'')) as req:c.heartbeat()
  body=json.loads(req.call_args.args[2]);self.assertEqual(body['system_health']['memory_used_percent'],62.5);self.assertLess(len(req.call_args.args[2]),4096)
