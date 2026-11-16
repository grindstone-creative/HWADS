import hashlib,hmac,io,json,os
from pathlib import Path
import tempfile,time,unittest,zipfile
from unittest.mock import patch
import sys
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
import hwads_update as u

class Response(io.BytesIO):
    def __enter__(self):return self
    def __exit__(self,*_):self.close()

class UpdateTests(unittest.TestCase):
    def setUp(self):
        self.tmp=tempfile.TemporaryDirectory();self.addCleanup(self.tmp.cleanup);self.root=Path(self.tmp.name).resolve()
        for d in ('config','media','logs','releases','bootstrap'):(self.root/d).mkdir()
        self.old=self.root/'releases/old';self.old.mkdir();u.atomic(self.old/'release.json',{'version':'0.4.0','release_id':'old','updater_protocol':1})
        (self.root/'scripts').symlink_to(self.old)
        self.config={'device_key':'a'*43};u.atomic(self.root/'config/device.json',self.config);u.atomic(self.root/'config/player-environment.json',{'DISPLAY':':0'})
        (self.root/'media/keep.jpg').write_bytes(b'keep')
        self.zip=self.root/'package.zip';self.build_zip()
        data=self.zip.read_bytes();self.command={'type':'install_player','id':'12345678-1234-1234-1234-123456789012','release_id':hashlib.sha256(data).hexdigest(),'version':'0.4.1','sha256':hashlib.sha256(data).hexdigest(),'bytes':len(data),'url':'https://example.test/player.zip','expires_at':int(time.time())+3600};self.sign()
    def sign(self):self.command['signature']=hmac.new(hashlib.sha256(self.config['device_key'].encode()).hexdigest().encode(),u.canonical(self.command).encode(),hashlib.sha256).hexdigest()
    def build_zip(self,extra=None,corrupt=False):
        files={name:(b'pass\n' if name.endswith('.py') else b'test') for name in u.RUNTIME};manifest={'schema':1,'updater_protocol':1,'version':'0.4.1','files':{k:hashlib.sha256(v).hexdigest() for k,v in files.items()}}
        with zipfile.ZipFile(self.zip,'w') as z:
            for k,v in files.items():z.writestr('hwads-pi/'+k,b'changed' if corrupt and k=='hwads_sync.py' else v)
            z.writestr('hwads-pi/update-manifest.json',json.dumps(manifest))
            if extra:z.writestr(extra,b'bad')
    def run_apply(self,healthy=True):
        return patch.multiple(u,download=lambda *_:self.zip,stop_player=lambda *_:None,service=lambda *_:None,start_player=lambda *_:None,healthy=lambda *_:healthy)
    def test_analytics_queue_and_sidecars_survive_update_and_rollback(self):
        import hwads_sync as analytics
        from contextlib import closing
        with closing(analytics.database(self.root)) as db:
            db.execute('INSERT INTO plays VALUES (?,?,?,?)',('preserved',1,'{}',time.time()));db.commit()
        paths=[self.root/'config/analytics.sqlite3']
        # Release switching must not touch any config sidecars, open or closed.
        for suffix in ('-wal','-shm'):
            path=self.root/('config/analytics.sqlite3'+suffix);path.write_bytes(b'preserve sidecar');paths.append(path)
        before={p:p.read_bytes() for p in paths}
        with self.run_apply():u.apply(self.root,self.command)
        self.assertEqual(before,{p:p.read_bytes() for p in paths})
        self.command.update(type='rollback_player',id='98765432-1234-1234-1234-123456789012',release_id='old',version='0.4.0');self.sign()
        with self.run_apply():u.apply(self.root,self.command)
        self.assertEqual(before,{p:p.read_bytes() for p in paths})

    def test_bootstrap_preserves_config_and_stages_runtime(self):
        before=(self.root/'config/device.json').read_bytes()
        with patch.object(u,'stop_player'):u.bootstrap(self.root,Path(__file__).resolve().parents[1])
        self.assertEqual(before,(self.root/'config/device.json').read_bytes());self.assertTrue((self.root/'bootstrap/hwads_update.py').is_file());self.assertTrue((self.root/'config/player.disabled').exists());self.assertEqual(u.status(self.root)['installed_version'],__import__('hwads_sync').VERSION)
    def test_health_requires_live_sync_and_fresh_player(self):
        stamp=time.time();u.atomic(self.root/'config/sync-runtime.json',{'pid':os.getpid(),'version':'0.4.1','started_at':stamp,'healthy_at':stamp});u.atomic(self.root/'config/player-state.json',{'updated_at':stamp,'state':'playing'})
        self.assertTrue(u.healthy(self.root,'0.4.1',stamp,False,timeout=.1))
        with patch.object(u,'process_alive',return_value=False),patch.object(u.time,'sleep'):
            self.assertFalse(u.healthy(self.root,'0.4.1',stamp,False,timeout=.01))
        u.atomic(self.root/'config/player-state.json',{'updated_at':stamp-10,'state':'playing'})
        with patch.object(u.time,'sleep'):self.assertFalse(u.healthy(self.root,'0.4.1',stamp,False,timeout=.01))
    def test_signed_command_and_tamper(self):
        u.verify_command(self.command,self.config);self.command['version']='9.0.0'
        with self.assertRaises(ValueError):u.verify_command(self.command,self.config)
    def test_expired_command(self):
        self.command['expires_at']=1;self.sign()
        with self.assertRaises(ValueError):u.verify_command(self.command,self.config)
    def test_archive_valid_and_traversal_rejected(self):
        self.assertEqual(u.inspect_archive(self.zip)['version'],'0.4.1');self.build_zip('hwads-pi/../config/device.json')
        with self.assertRaises(ValueError):u.inspect_archive(self.zip)
    def test_archive_unknown_file_rejected(self):
        self.build_zip('hwads-pi/config/device.json')
        with self.assertRaises(ValueError):u.inspect_archive(self.zip)
    def test_archive_checksum_rejected(self):
        self.build_zip(corrupt=True)
        with self.assertRaises(ValueError):u.inspect_archive(self.zip)
    def test_success_atomic_switch_and_preserved_data(self):
        before=(self.root/'config/device.json').read_bytes()
        with self.run_apply():u.apply(self.root,self.command)
        self.assertNotEqual((self.root/'scripts').resolve(),self.old);self.assertEqual((self.root/'previous-release').resolve(),self.old)
        self.assertEqual(before,(self.root/'config/device.json').read_bytes());self.assertEqual((self.root/'media/keep.jpg').read_bytes(),b'keep');self.assertEqual(u.status(self.root)['status'],'completed');self.assertEqual(u.status(self.root)['installed_version'],'0.4.1')
    def test_duplicate_completed_command_is_noop(self):
        with self.run_apply():u.apply(self.root,self.command)
        with patch.object(u,'download') as download:u.apply(self.root,self.command);download.assert_not_called()
    def test_health_failure_rolls_back(self):
        with self.run_apply(False):u.apply(self.root,self.command)
        self.assertEqual((self.root/'scripts').resolve(),self.old);self.assertEqual(u.status(self.root)['status'],'rolled_back')
    def test_disabled_playback_stays_disabled(self):
        (self.root/'config/player.disabled').touch()
        with patch.object(u,'download',return_value=self.zip),patch.object(u,'stop_player'),patch.object(u,'service'),patch.object(u,'healthy',return_value=True),patch.object(u,'start_player') as start:u.apply(self.root,self.command);start.assert_not_called()
        self.assertTrue((self.root/'config/player.disabled').exists())
    def test_explicit_rollback_switches_to_previous(self):
        with self.run_apply():u.apply(self.root,self.command)
        self.command.update(type='rollback_player',id='22345678-1234-1234-1234-123456789012',release_id='old',version='0.4.0',sha256='',bytes=0,url='');self.sign()
        with patch.object(u,'download') as download,patch.object(u,'stop_player'),patch.object(u,'service'),patch.object(u,'healthy',return_value=True),patch.object(u,'start_player'):u.apply(self.root,self.command);download.assert_not_called()
        self.assertEqual((self.root/'scripts').resolve(),self.old);self.assertEqual(u.status(self.root)['status'],'completed')
    def test_interrupted_update_recovers_previous(self):
        target=self.root/'releases/new';target.mkdir();u.swap(self.root,'scripts',target);u.atomic(self.root/'config/update-journal.json',{'id':self.command['id'],'previous':str(self.old),'target':str(target),'disabled':True,'phase':'switching'})
        with patch.object(u,'stop_player'),patch.object(u,'service'):u.recover(self.root)
        self.assertEqual((self.root/'scripts').resolve(),self.old);self.assertEqual(u.status(self.root)['status'],'rolled_back')
    def test_download_hash_size_and_no_credentials(self):
        payload=self.zip.read_bytes()
        with patch.object(u.OPENER,'open',return_value=Response(payload)) as opened:
            path=u.download(self.root,self.command);self.assertTrue(path.exists());self.assertFalse(opened.call_args.args[0].has_header('Authorization'))
        with patch.object(u.OPENER,'open',return_value=Response(b'wrong')):
            with self.assertRaises(ValueError):u.download(self.root,self.command)
    def test_downgrade_not_installed(self):
        self.command['version']='0.3.0';self.sign()
        with self.run_apply():u.apply(self.root,self.command)
        self.assertEqual((self.root/'scripts').resolve(),self.old);self.assertEqual(u.status(self.root)['status'],'failed')
    def test_staged_package_cannot_change_configuration(self):
        self.build_zip('hwads-pi/config/device.json')
        with self.run_apply():u.apply(self.root,self.command)
        self.assertEqual((self.root/'scripts').resolve(),self.old);self.assertEqual(u.status(self.root)['status'],'failed')

if __name__=='__main__':unittest.main()
