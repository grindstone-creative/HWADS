from pathlib import Path
import zipfile, hashlib, json, argparse, re
root=Path(__file__).resolve().parents[1]
source=root/'player'
# Runtime manifest is consumed without executing install.sh on remote updates.
files=['hwads_sync.py','hwads_player.py','hwads_browser.py','hwads_launch.py','hwads-control.py','hwads_update.py','browser/index.html','browser/player.js','browser/player.css','browser/overlays.js','browser/overlays.css','browser/vendor/qrcodegen.js']
for asset in ('overlays.js','overlays.css'):assert (source/'browser'/asset).read_bytes()==(root/'plugin/hpads/assets'/asset).read_bytes()
source_version=re.search(r"VERSION = '([^']+)'",(source/'hwads_sync.py').read_text()).group(1)
parser=argparse.ArgumentParser();parser.add_argument('--version',default=source_version);version=parser.parse_args().version
assert re.fullmatch(r'\d+\.\d+\.\d+',version)
payload={name:(source/name).read_bytes() for name in files};payload['hwads_sync.py']=re.sub(rb"VERSION = '[^']+'",("VERSION = '"+version+"'").encode(),payload['hwads_sync.py'])
manifest={'schema':1,'updater_protocol':1,'version':version,'files':{name:hashlib.sha256(payload[name]).hexdigest() for name in files}}
if version==source_version:(source/'update-manifest.json').write_text(json.dumps(manifest,indent=2)+'\n')
target=root/f'dist/hwads-pi-{version}.zip';target.parent.mkdir(exist_ok=True)
with zipfile.ZipFile(target,'w',zipfile.ZIP_DEFLATED) as z:
 for name in files:z.writestr('hwads-pi/'+name,payload[name])
 z.writestr('hwads-pi/update-manifest.json',json.dumps(manifest,indent=2)+'\n')
 for name in ['README.md','THIRD_PARTY.md','device.example.json','install.sh']:z.write(source/name,'hwads-pi/'+name)
with zipfile.ZipFile(target) as z:assert z.testzip() is None
print(target)
