"""Build an installable administrator component without any site data."""
from pathlib import Path
import zipfile
import hashlib
import xml.etree.ElementTree as ET
root=Path(__file__).resolve().parent
version=ET.parse(root/'tz_media_transfer.xml').getroot().findtext('version')
out=root.parent/f'com_tz_media_transfer_v{version}.zip'
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

updates=ET.Element('updates')
update=ET.SubElement(updates,'update')
for tag,value in [('name','TZ Media Transfer'),('description','Image and caption transfer for TZ Portfolio 4.1+'),('element','com_tz_media_transfer'),('type','component'),('version',version),('client','administrator'),('infourl','https://github.com/umatstar-lab/TZ-4.1.0/blob/main/media-transfer/README.ru.md')]:
    ET.SubElement(update,tag).text=value
ET.SubElement(update,'targetplatform',name='joomla',version=r'(5\.4|6\.[0-9]+)')
ET.SubElement(update,'php_minimum').text='8.1.0'
ET.SubElement(update,'supported_databases',mysql='5.7',mariadb='10.4')
ET.SubElement(update,'stability').text='stable'
downloads=ET.SubElement(update,'downloads')
ET.SubElement(downloads,'downloadurl',type='full',format='zip').text=f'https://github.com/umatstar-lab/TZ-4.1.0/raw/refs/heads/main/{out.name}'
for algorithm in ('sha256','sha512'):
    ET.SubElement(update,algorithm).text=hashlib.new(algorithm,out.read_bytes()).hexdigest()
ET.indent(updates,space='  ')
update_path=root.parent/'updates'/'tz_media_transfer.xml'
update_path.parent.mkdir(exist_ok=True)
ET.ElementTree(updates).write(update_path,encoding='utf-8',xml_declaration=True)
print('Update metadata generated with Joomla 5.4/6.x targets and SHA-256/SHA-512.')
