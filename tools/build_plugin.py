from pathlib import Path
import zipfile, re
root=Path(__file__).resolve().parents[1]
source=root/'plugin/hpads'
version=re.search(r"define\('HPADS_VERSION', '([^']+)'\)",(source/'hpads.php').read_text()).group(1)
target=root/f'dist/hwads-{version}.zip'
target.parent.mkdir(parents=True,exist_ok=True)
with zipfile.ZipFile(target,'w',zipfile.ZIP_DEFLATED) as archive:
    for path in sorted(source.rglob('*')):
        if path.is_file() and path.name!='.DS_Store':
            archive.write(path,'hpads/'+str(path.relative_to(source)))
with zipfile.ZipFile(target) as archive:
    assert archive.testzip() is None
    assert 'hpads/includes/native-editor.php' in archive.namelist()
print(target)
