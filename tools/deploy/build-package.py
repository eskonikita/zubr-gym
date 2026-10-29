"""Build a portable CMS archive without credentials, caches or database files."""
from pathlib import Path
import hashlib
import json
import zipfile

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'artifacts'
OUT.mkdir(exist_ok=True)
EXCLUDED = (
    'public/core/cache/', 'public/core/logs/', 'public/core/packages/',
    'public/core/export/', 'public/core/import/', 'public/setup/',
    'cms/private/', 'cms/backups/', 'cms/core/',
)
SECRET_CONFIGS = {
    'public/config.core.php', 'public/core/config/config.inc.php',
    'public/manager/config.core.php', 'public/connectors/config.core.php',
}

files = []
for directory in ['public', 'cms', 'tools/deploy']:
    for path in (ROOT / directory).rglob('*'):
        if not path.is_file():
            continue
        name = path.relative_to(ROOT).as_posix()
        if name in SECRET_CONFIGS or name.startswith(EXCLUDED):
            continue
        if path.suffix in {'.sql', '.gz', '.log', '.bak', '.pyc'} or '__pycache__' in path.parts:
            continue
        files.append((name, path))
files.append(('DEPLOY.md', ROOT / 'DEPLOY.md'))
archive = OUT / 'zubr-gym-production-files.zip'
temporary = archive.with_suffix('.zip.tmp')
with zipfile.ZipFile(temporary, 'w', zipfile.ZIP_DEFLATED, compresslevel=6) as package:
    for name, path in sorted(files):
        package.write(path, name)
temporary.replace(archive)

manifest = {
    'host': 'https://zubr-gym.by/',
    'files': len(files),
    'uncompressed_bytes': sum(path.stat().st_size for _, path in files),
    'archive_bytes': archive.stat().st_size,
    'excluded': list(EXCLUDED) + sorted(SECRET_CONFIGS),
}
(OUT / 'package-manifest.json').write_text(json.dumps(manifest, indent=2) + '\n', encoding='utf-8')
checks = []
for path in [archive, OUT / 'zubr-gym-production.sql.gz']:
    if path.exists():
        checks.append(hashlib.sha256(path.read_bytes()).hexdigest() + '  ' + path.name)
(OUT / 'SHA256SUMS.txt').write_text('\n'.join(checks) + '\n', encoding='ascii')
print(json.dumps(manifest, indent=2))
