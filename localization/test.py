"""Install and validate the three packages on the isolated local Joomla runtime."""
import subprocess,json,hashlib,sys
from pathlib import Path
sys.path.insert(0,str(Path(__file__).parent));from build import ROOT,parse,decode
container=sys.argv[1] if len(sys.argv)>1 else 'migration273-web-1'
assert container in ['migration273-web-1','tz412-web-1'],'Only isolated local fixtures supported'
report=json.loads((ROOT/'audit.json').read_text());expected={}
def run(*args):return subprocess.run(args,check=True,capture_output=True,text=True).stdout
for pkg in report['packages']:
 for target in pkg['targets']:
  p=ROOT/'build'/pkg['group']/'payload'/target;data,_=parse(p)
  lang='ru-RU' if '/ru-RU/' in target else 'uk-UA';name=p.name;extension=name.removeprefix(lang+'.').removesuffix('.ini');base=target.split('/language/')[0] if '/language/' in target else ''
  expected[target]={'sha256':hashlib.sha256(p.read_bytes()).hexdigest(),'values':{k:decode(v) for k,v in data.items()},'lang':lang,'extension':extension,'base':base}
 # The saved test site contains source directories copied by root in earlier sessions.
 # Change only the existing language destinations/nearest language parent on this isolated site.
 for target in pkg['targets']:
  folder='/var/www/html/'+str(Path(target).parent)
  script='$p='+json.dumps(folder)+';while(!is_dir($p)){$p=dirname($p);}chown($p,"www-data");chgrp($p,"www-data");'
  run('docker','exec',container,'php','-r',script)
 archive=ROOT.parent/pkg['zip'];dest='/tmp/'+archive.name
 run('docker','cp',str(archive),container+':'+dest);run('docker','exec',container,'chmod','644',dest)
 print(run('docker','exec','--user','www-data',container,'php','cli/joomla.php','extension:install','--path='+dest,'--no-interaction').strip())
Path('/tmp/tz-loc-expected.json').write_text(json.dumps(expected,ensure_ascii=False))
for p in [Path('/tmp/tz-loc-expected.json'),ROOT/'test-installed.php']:
 target='/tmp/'+p.name;run('docker','cp',str(p),container+':'+target);run('docker','exec',container,'chmod','644',target)
print(run('docker','exec','--user','www-data',container,'php','/tmp/test-installed.php'))
