from pathlib import Path
from zipfile import ZipFile
import hashlib,json
from build import ROOT,SOURCES,parse
report=json.loads((ROOT/'audit.json').read_text());matched=[]
for p in SOURCES:
 rel=p.relative_to(ROOT/'source');group=rel.parts[0];tail=Path(*rel.parts[1:])
 if group=='tz_portfolio_v4.1.2':original=Path('/workspace/tz-4.1.2/original/tz_portfolio-4.1.2')/tail
 elif group.startswith('gallery_'):original=ROOT.parent/'gallery-addon'/tail
 else:original=Path('/workspace/flipbook-119/fixed')/tail
 assert p.read_bytes()==original.read_bytes(),rel
 matched.append(str(rel))
report['original_english_files_byte_verified']=matched
with ZipFile('/tmp/tz-language-system.zip') as z:
 nested=z.read('system/language/en-GB/en-GB.tp_style_system.ini')
 own=ROOT/'source/tz_portfolio_v4.1.2/setup/packages/styles/system/language/en-GB/en-GB.tp_style_system.ini'
 temp=Path('/tmp/tz-system-nested.ini');temp.write_bytes(nested)
 assert parse(temp)[0]==parse(own)[0]
 report['nested_system_zip_same_language_values']=True
 report['nested_system_zip_comment_links_differ']=nested!=own.read_bytes()
src=ROOT/'source/gallery_content_addon_v2.1.7/language';en,_=parse(src/'en-GB/en-GB.tp_addon_content_gallery.ini')
report['input_gallery']={}
for lang in ['ru-RU','uk-UA']:
 d,_=parse(src/lang/(lang+'.tp_addon_content_gallery.ini'))
 report['input_gallery'][lang]={'missing_keys':sorted(set(en)-set(d)),'extra_keys':sorted(set(d)-set(en)),'unchanged_english_values':[k for k in en if d.get(k)==en[k]],'keys':len(d)}
report['source_unique_keys']=sum(x['keys'] for x in report['source_files'])
(ROOT/'audit.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
print('PASS',len(matched),'original English files byte-identical; embedded style duplicate verified')
print('Gallery input',[(l,len(r['unchanged_english_values'])) for l,r in report['input_gallery'].items()])
