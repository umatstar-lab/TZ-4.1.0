"""Prepare browser fixtures from the actual installable ZIP; requires tz-dev-web-1."""
from pathlib import Path
import shutil, subprocess, zipfile
repo = Path(__file__).resolve().parents[2]
work = Path('/workspace/gallery-fix')
source = work / 'test-addon'
preview = work / 'preview'
with zipfile.ZipFile(repo / 'gallery_content_addon_v2.1.4_tzportfolio_4.1_layout_fix.zip') as package:
    assert package.testzip() is None
    for name in package.namelist():
        assert not Path(name).is_absolute() and '..' not in Path(name).parts
    package.extractall(source)
shutil.copytree(source, preview / 'components/com_tz_portfolio/add-ons/content/gallery', dirs_exist_ok=True)
for path in ['media/vendor/jquery/js/jquery.min.js', 'media/com_tz_portfolio/js/jquery.fancybox.min.js', 'media/com_tz_portfolio/css/jquery.fancybox.min.css']:
    target = preview / path
    target.parent.mkdir(parents=True, exist_ok=True)
    shutil.copyfile(Path('/workspace/tz-dev/site') / path, target)
images = preview / 'images/tz_portfolio_plus/gallery/42'
images.mkdir(parents=True, exist_ok=True)
for i, height in enumerate([220, 500, 300, 180, 420, 240, 360, 160], 1):
    (images / f'image-{i}.svg').write_text(f'<svg xmlns="http://www.w3.org/2000/svg" width="600" height="{height}"><rect width="600" height="{height}" fill="hsl({i * 35},25%,65%)"/><text x="30" y="60" font-size="40">Photo {i}</text></svg>')
# Copy directory contents so repeated runs do not create an extra nested directory.
subprocess.run(['docker', 'cp', str(source) + '/.', 'tz-dev-web-1:/tmp/gallery-addon'], check=True)
subprocess.run(['docker', 'cp', str(Path(__file__).with_name('render.php')), 'tz-dev-web-1:/tmp/gallery-render.php'], check=True)
for filename, args in [(fmt, [fmt]) for fmt in ['json', 'object', 'array', 'root-array', 'root-json', 'invalid']] + [(mode, ['json', mode]) for mode in ['grid', 'carousel', 'horizontal_masonry']]:
    result = subprocess.run(['docker', 'exec', 'tz-dev-web-1', 'php', '/tmp/gallery-render.php', *args], check=True, capture_output=True)
    (preview / (filename + '.html')).write_bytes(result.stdout)
print('Nine fixtures rendered from the installable ZIP.')
