"""Build an installable administrator component without any site data."""
from pathlib import Path
import zipfile
root=Path(__file__).resolve().parent
out=root.parent/'com_tz_media_transfer_v1.0.5.zip'
files=[root/'tz_media_transfer.xml']
for folder in ['admin','media']:
    files.extend(p for p in (root/folder).rglob('*') if p.is_file())
with zipfile.ZipFile(out,'w',compression=zipfile.ZIP_DEFLATED) as archive:
    for path in sorted(files):
        path.chmod(0o644)
        archive.write(path,path.relative_to(root).as_posix())
with zipfile.ZipFile(out) as archive:
    assert archive.testzip() is None
    assert len(archive.namelist())==len(files)
    assert all(((i.external_attr>>16)&0o777)==0o644 for i in archive.infolist())
print(f'{out.name}: {len(files)} files, {out.stat().st_size} bytes; CRC and permissions verified')
