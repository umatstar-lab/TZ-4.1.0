"""Prepare test copies for the isolated Joomla 6.2 runtime; does not touch a site.

The PHP fixtures require both the joom_ prefix and an explicit marker inside
that local container. See patches/media-transfer-joomla62.md before running.
"""
from pathlib import Path
import argparse

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('output', type=Path)
args = parser.parse_args()
source = Path(__file__).resolve().parent
args.output.mkdir(parents=True, exist_ok=True)
for path in source.iterdir():
    if path.suffix not in ('.php', '.cjs'):
        continue
    text = path.read_text()
    if path.suffix == '.php':
        text = text.replace("$c->dbprefix!=='migrationj54_'", "($c->dbprefix!=='joom_' || !is_file('/tmp/tzmt-j62-fixtures-authorized'))")
        if path.name == 'fixtures.php':
            text = text.replace('WHERE id=6', 'WHERE id=5')
    else:
        text = text.replace('migration273-web-1', 'tz412-web-1').replace('127.0.0.1:8084', '127.0.0.1:8082')
    destination = args.output / path.name
    destination.write_text(text)
    destination.chmod(0o644)
print(f'Joomla 6.2 fixture test copies prepared in {args.output}; no site changes made.')
