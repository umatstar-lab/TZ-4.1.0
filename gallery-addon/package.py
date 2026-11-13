"""Build Gallery add-on without local fixtures or site data."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import xml.etree.ElementTree as E
r=Path(__file__).resolve().parent
version=E.parse(r/'gallery.xml').getroot().findtext('version')
out=r.parent/f'gallery_content_addon_v{version}_responsive_columns.zip'
files=[p for p in r.rglob('*') if p.is_file() and p.name not in ('package.py','README.ru.md') and '__pycache__' not in p.parts]
with ZipFile(out,'w',ZIP_DEFLATED) as z:
 for p in sorted(files):
  p.chmod(0o644);z.write(p,p.relative_to(r).as_posix())
with ZipFile(out) as z:assert z.testzip() is None
print(out.name,len(files),'files;',out.stat().st_size,'bytes')
