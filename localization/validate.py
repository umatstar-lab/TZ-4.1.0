from pathlib import Path
import json,re,hashlib,xml.etree.ElementTree as E
from zipfile import ZipFile
from build import parse,decode,LOOKUP,ROOT,VALUES,TR
# build import regenerates deterministic payloads and validates completeness.
fmt=re.compile(r'%(?:\d+\$)?[-+0 #]*(?:\d+|\*)?(?:\.\d+)?[bcdeEfFgGosuxX]')
issues=[]
for i,en in enumerate(VALUES):
 for lang,t in TR[i].items():
  en=decode(en);t=decode(t)
  if fmt.findall(en)!=fmt.findall(t):issues.append((i,lang,'printf'))
  if re.findall(r'<[^>]+>',en)!=re.findall(r'<[^>]+>',t):issues.append((i,lang,'html'))
  url=lambda s:[x.rstrip(',;') for x in re.findall(r'https?://[^\s<>\"\']+',s)]
  if url(en)!=url(t):issues.append((i,lang,'urls',url(en),url(t)))
assert not issues,issues
report=json.loads((ROOT/'audit.json').read_text())
for pkg in report['packages']:
 z=ZipFile(ROOT.parent/pkg['zip']);xml=E.fromstring(z.read(next(n for n in z.namelist() if n.endswith('.xml'))))
 assert xml.attrib['type']=='file'
 paths=[]
 for target in xml.findall('fileset/files'):
  assert '..' not in Path(target.attrib['target']).parts
  for filename in target:
   assert filename.tag=='filename' and Path(filename.text).name==filename.text
   n=target.attrib['folder']+'/'+filename.text;assert n in z.namelist();paths.append(n)
   d,dup=parse(ROOT/'build'/pkg['group']/n);assert not dup
 assert len(paths)==pkg['files']
 assert len(z.namelist())==len(paths)+1
 assert all(n.endswith(('.ini','.xml')) for n in z.namelist())
 assert hashlib.sha256((ROOT.parent/pkg['zip']).read_bytes()).hexdigest()==pkg['sha256']
print('PASS all translated values, placeholders, HTML, URLs, package paths, ZIP contents')
print('Non-Cyrillic values retained:',[(i,VALUES[i]) for i in TR if not re.search('[А-Яа-яІіЇїЄєҐґ]',TR[i]['ru-RU'])])
