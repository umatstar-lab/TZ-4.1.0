"""Build the migration bundle without including any site/database data."""
from pathlib import Path
import hashlib
import io
import zipfile

root = Path(__file__).resolve().parents[1]
files = {
    "README.ru.md": root / "migration/README.ru.md",
    "tools/migrate-flipbook.php": root / "migration/migrate-flipbook.php",
    "installers/tz_portfolio_tz-portfolio.v4.1.2-fixed.zip": root / "tz_portfolio_tz-portfolio.v4.1.2-fixed.zip",
    "installers/gallery_content_addon_v2.1.4_tzportfolio_4.1_layout_fix.zip": root / "gallery_content_addon_v2.1.4_tzportfolio_4.1_layout_fix.zip",
}
output = root / "migration_plus273_to_portfolio412.zip"
with zipfile.ZipFile(output, "w", compression=zipfile.ZIP_DEFLATED) as archive:
    for name, path in files.items():
        archive.write(path, name)
    archive.writestr("SHA256SUMS", "".join(
        hashlib.sha256(path.read_bytes()).hexdigest() + "  " + name + "\n"
        for name, path in files.items()
    ))
with zipfile.ZipFile(output) as archive:
    assert archive.testzip() is None
    assert set(archive.namelist()) == set(files) | {"SHA256SUMS"}
    for name, path in files.items():
        assert archive.read(name) == path.read_bytes()
        if name.endswith(".zip"):
            with zipfile.ZipFile(io.BytesIO(archive.read(name))) as installer:
                assert installer.testzip() is None
print(f"{output.name}: {output.stat().st_size} bytes; contents, checksums and ZIP CRC verified")
