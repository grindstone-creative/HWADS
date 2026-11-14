"""Exercise the real player loop against a deterministic fake mpv IPC process."""
import json
import os
from pathlib import Path
import signal
import subprocess
import sys
import tempfile
import time
import unittest

class PlayerIPCTest(unittest.TestCase):
    def test_one_window_multiple_items_and_persistent_disable(self):
        scripts=Path(__file__).resolve().parents[1]
        with tempfile.TemporaryDirectory(prefix='hwads-player-test-') as directory:
            root=Path(directory);(root/'bin').mkdir();(root/'config').mkdir();(root/'media').mkdir()
            for name in ('a.jpg','b.jpg'):(root/'media'/name).write_bytes(b'fixture')
            (root/'config/playlist.json').write_text(json.dumps([{'file':'a.jpg','type':'image','duration':1},{'file':'b.jpg','type':'image','duration':1}]))
            fake=root/'bin/mpv'
            fake.write_text('#!'+sys.executable+'''\nimport json,os,socket,sys
from pathlib import Path
root=Path(os.environ['HWADS_TEST_ROOT'])
with (root/'launches').open('a') as f:f.write('launch\\n')
ipc=next(x.split('=',1)[1] for x in sys.argv if x.startswith('--input-ipc-server='))
s=socket.socket(socket.AF_UNIX);s.bind(ipc);s.listen(1);c,_=s.accept();stream=c.makefile('rb')
for line in stream:
 command=json.loads(line)['command']
 if command[0]=='loadfile':
  with (root/'loads').open('a') as f:f.write(command[1]+'\\n')
  c.sendall(b'{"event":"start-file"}\\n{"event":"file-loaded"}\\n')
 elif command[0]=='stop':c.sendall(b'{"event":"end-file","reason":"stop"}\\n')
''')
            fake.chmod(0o755)
            env=dict(os.environ,PATH=str(root/'bin')+os.pathsep+os.environ['PATH'],HWADS_TEST_ROOT=str(root))
            p=subprocess.Popen([sys.executable,str(scripts/'hwads_player.py'),'--root',str(root)],env=env,stdout=subprocess.DEVNULL,stderr=subprocess.PIPE)
            try:
                deadline=time.monotonic()+10
                while time.monotonic()<deadline:
                    if (root/'loads').exists() and len((root/'loads').read_text().splitlines())>=3:break
                    if p.poll() is not None:self.fail('Player exited: '+p.stderr.read().decode())
                    time.sleep(.1)
                self.assertGreaterEqual(len((root/'loads').read_text().splitlines()),3)
                self.assertEqual((root/'launches').read_text().splitlines(),['launch'])
                (root/'config/player.disabled').touch()
                p.wait(timeout=5)
                self.assertEqual(p.returncode,0)
                self.assertEqual(json.loads((root/'config/player-state.json').read_text())['state'],'idle')
                # An autostart while disabled must exit without launching mpv again.
                subprocess.run([sys.executable,str(scripts/'hwads_player.py'),'--root',str(root)],env=env,check=True,timeout=5)
                self.assertEqual((root/'launches').read_text().splitlines(),['launch'])
            finally:
                if p.poll() is None:p.terminate();p.wait(timeout=5)
                p.stderr.close()

if __name__=='__main__':unittest.main()
