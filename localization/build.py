"""Build three offline, file-only RU/UK localization extensions for Joomla 5/6."""
from pathlib import Path
from zipfile import ZipFile,ZIP_DEFLATED,ZipInfo
from collections import defaultdict,Counter
import json,re,hashlib,xml.etree.ElementTree as E,shutil
ROOT=Path(__file__).resolve().parent
VALUES=json.loads((ROOT/'translations/source-values.json').read_text())
TR={}
for n,line in enumerate((ROOT/'translations/reviewed.tsv').read_text().splitlines(),1):
 i,ru,uk=line.split('\t');i=int(i);assert i not in TR,n
 TR[i]={'ru-RU':ru,'uk-UA':uk}
assert set(TR)==set(range(len(VALUES)))
LOOKUP={s:TR[i] for i,s in enumerate(VALUES)}
def decode(s):return re.sub(r'\\+"','"',s)
def encode(s):return s.replace('"','\\"')
def parse(path):
 result={};duplicates=[]
 for n,line in enumerate(path.read_text(encoding='utf-8-sig').splitlines(),1):
  if not line.strip() or line.lstrip().startswith(';'):continue
  m=re.match(r'^([^=;]+?)\s*=\s*"(.*)"\s*$',line)
  if not m:raise ValueError((path,n,line))
  key,value=m.groups();key=key.strip()
  if key in result:duplicates.append(key)
  result[key]=value
 return result,duplicates
SOURCES=sorted(p for p in (ROOT/'source').rglob('*.ini') if 'en-GB' in p.parts)
modern={}
for p in SOURCES:
 d,_=parse(p)
 for key,v in d.items():
  if key.startswith('COM_TZ_PORTFOLIO_'):modern[key]=v
legacy=set()
for base in [Path('/workspace/tz-4.1.2/original/tz_portfolio-4.1.2'),ROOT.parent/'gallery-addon',Path('/workspace/flipbook-119/fixed')]:
 for p in base.rglob('*'):
  if p.is_file() and p.suffix in ['.php','.xml','.js']:
   legacy.update(re.findall(r'\bCOM_TZ_PORTFOLIO_PLUS_[A-Z0-9_]+\b',p.read_text(errors='replace')))
aliases={k:modern[k.replace('COM_TZ_PORTFOLIO_PLUS_','COM_TZ_PORTFOLIO_',1)] for k in sorted(legacy) if k.replace('COM_TZ_PORTFOLIO_PLUS_','COM_TZ_PORTFOLIO_',1) in modern}
GROUPS={'tz_portfolio_v4.1.2':('portfolio','TZ Portfolio 4.1.2 RU + UK'), 'flipbook_gallery_addon_v1.0.19':('flipbook','Flipbook Gallery 1.0.19 RU + UK'), 'gallery_content_addon_v2.1.7':('gallery','Content Gallery 2.1.7 RU + UK')}
work=ROOT/'build';shutil.rmtree(work,ignore_errors=True);work.mkdir()
report={'version':'1.0.0','languages':['ru-RU','uk-UA'],'source_files':[],'legacy_aliases':sorted(aliases),'packages':[]}
packages=defaultdict(dict)
def put(group,target,content):
 assert not target.startswith('/') and '..' not in Path(target).parts
 if target in packages[group]:assert packages[group][target]==content,target
 packages[group][target]=content
for source in SOURCES:
 rel=source.relative_to(ROOT/'source');group=GROUPS[rel.parts[0]][0];data,duplicates=parse(source)
 report['source_files'].append({'path':str(rel),'keys':len(data),'sha256':hashlib.sha256(source.read_bytes()).hexdigest(),'duplicates':duplicates})
 for lang in ['ru-RU','uk-UA']:
  translated={key:LOOKUP[value][lang] for key,value in data.items()}
  # Compatibility aliases belong to the component language, not executable overrides.
  if rel.parts[0]=='tz_portfolio_v4.1.2' and 'languages'==rel.parts[1]:
   translated.update({key:LOOKUP[value][lang] for key,value in aliases.items()})
  if group in ['gallery','flipbook']:
   key='COM_TZ_PORTFOLIO_PLUS_OPTION_INHERIT_FROM_CATEGORY';translated[key]={'ru-RU':'Наследовать от категории','uk-UA':'Успадкувати від категорії'}[lang]
  content='; '+lang+' localization — '+rel.parts[0]+'\n; GNU/GPL v2 or later. UTF-8 without BOM.\n'+'\n'.join(key+'="'+encode(decode(value))+'"' for key,value in translated.items())+'\n'
  if group in ['gallery','flipbook']:
   folder='content/gallery' if group=='gallery' else 'mediatype/flipbook_gallery';name=source.name.replace('en-GB',lang)
   targets=[f'components/com_tz_portfolio/add-ons/{folder}/language/{lang}/{name}']
  elif rel.parts[1]=='languages':
   prefix='administrator/' if rel.parts[2]=='admin' else ''
   targets=[f'{prefix}language/{lang}/{source.name}']
  else:
   parts=rel.parts;kind=parts[3]
   if kind=='add-ons':
    addon=parts[4:6];targets=[f'components/com_tz_portfolio/add-ons/{"/".join(addon)}/language/{lang}/{source.name.replace("en-GB",lang)}']
   elif kind=='styles':targets=[f'components/com_tz_portfolio/styles/{parts[4]}/language/{lang}/{source.name.replace("en-GB",lang)}']
   elif kind=='modules':
    module=parts[4];targets=[f'modules/{module}/language/{lang}/{source.name}',f'language/{lang}/{source.name}',f'administrator/language/{lang}/{source.name}']
   elif kind=='plugins':
    folder,plugin=parts[4:6];targets=[f'plugins/{folder}/{plugin}/language/{lang}/{source.name}',f'administrator/language/{lang}/{source.name}']
   else:raise ValueError(rel)
  for target in targets:put(group,target,content)
for original,(group,label) in GROUPS.items():
 root=work/group;root.mkdir();element='files_tz_'+group+'_localization_ru_uk'
 xml=E.Element('extension',type='file',method='upgrade',version='5.0')
 for tag,text in [('name',label+' localization'),('author','Umatstar Lab'),('creationDate','2026-11-16'),('version','1.0.0'),('license','GNU General Public License version 2 or later'),('description','Russian and Ukrainian localization only. Install after the corresponding extension. Site content and settings are not changed.')]:E.SubElement(xml,tag).text=text
 fs=E.SubElement(xml,'fileset');byfolder=defaultdict(list)
 for target,content in sorted(packages[group].items()):
  p=root/'payload'/target;p.parent.mkdir(parents=True,exist_ok=True);p.write_text(content);p.chmod(0o644);byfolder[str(Path(target).parent)].append(Path(target).name)
 for target,names in sorted(byfolder.items()):
  files=E.SubElement(fs,'files',folder='payload/'+target,target=target)
  for name in names:E.SubElement(files,'filename').text=name
 E.indent(xml,space='  ');E.ElementTree(xml).write(root/(element+'.xml'),encoding='utf-8',xml_declaration=True)
 output=ROOT.parent/f'tz_{group}_localization_ru_uk_v1.0.0.zip'
 with ZipFile(output,'w',ZIP_DEFLATED) as z:
  for p in sorted(root.rglob('*')):
   if p.is_file():
    info=ZipInfo(p.relative_to(root).as_posix(),(2026,11,16,0,0,0));info.compress_type=ZIP_DEFLATED;info.external_attr=0o100644<<16;z.writestr(info,p.read_bytes())
 output.chmod(0o644)
 with ZipFile(output) as z:assert z.testzip() is None
 report['packages'].append({'group':group,'zip':output.name,'files':len(packages[group]),'sha256':hashlib.sha256(output.read_bytes()).hexdigest(),'targets':sorted(packages[group])})
 print(output.name,len(packages[group]),'INI files',output.stat().st_size,'bytes')
(ROOT/'audit.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
