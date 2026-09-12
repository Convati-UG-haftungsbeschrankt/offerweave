#!/usr/bin/env python3
"""Package the editable public Free sources, using only Python's standard library."""
from pathlib import Path
import argparse
import copy
import hashlib
import json
import re
import tempfile
import zipfile

ROOT = Path(__file__).resolve().parents[1]


def package(output):
    names = json.loads((ROOT / 'tools/files.json').read_text())
    if not isinstance(names, list) or len(set(names)) != len(names):
        raise ValueError('Expected a unique list of runtime files')
    entries = {}
    for name in names:
        if not isinstance(name, str) or not name or '\\' in name:
            raise ValueError('Invalid runtime path')
        path = Path(name)
        if path.is_absolute() or path.as_posix() != name or any(p in ('..', '.', '.git') for p in path.parts):
            raise ValueError('Unsafe runtime path: ' + name)
        target = ROOT / path
        if not target.is_file() or any(p.is_symlink() for p in [target, *target.parents] if p == ROOT or ROOT in p.parents):
            raise ValueError('Missing file or symbolic link: ' + name)
        entries[name] = target.read_bytes()
    original = json.loads(entries.pop('release-manifest.json'))
    if original.get('edition') != 'free' or original.get('distribution') != 'public':
        raise ValueError('Only the public Free edition is supported')
    version = re.search(rb'^ \* Version: (\d+\.\d+\.\d+)$', entries['offerweave.php'], re.M).group(1).decode()
    if ('Stable tag: ' + version).encode() not in entries['readme.txt']:
        raise ValueError('Header and readme versions differ')
    if ("define('OFFERWEAVE_VERSION', '" + version + "');").encode() not in entries['offerweave.php']:
        raise ValueError('Header and runtime versions differ')
    manifest = copy.deepcopy(original)
    manifest['version'] = version
    manifest['files'] = {name: hashlib.sha256(data).hexdigest() for name, data in sorted(entries.items())}
    # Preserve original release provenance for byte-identical files. Changed or
    # added files point to their directly editable public paths instead.
    sources = manifest.get('source_files', {})
    for name, digest in manifest['files'].items():
        if original.get('files', {}).get(name) != digest:
            sources[name] = {'source': name, 'sha256': digest}
    manifest['source_files'] = {name: data for name, data in sources.items() if name in entries}
    entries['release-manifest.json'] = (json.dumps(manifest, sort_keys=True, indent=2) + '\n').encode()
    output.mkdir(parents=True, exist_ok=True)
    archive = output / ('offerweave-free-' + version + '.zip')
    with tempfile.TemporaryDirectory(prefix='.offerweave-package-', dir=output) as tmp:
        candidate = Path(tmp) / archive.name
        with zipfile.ZipFile(candidate, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as z:
            for name, data in sorted(entries.items()):
                info = zipfile.ZipInfo('offerweave/' + name, date_time=(2026, 9, 6, 0, 0, 0))
                info.compress_type = zipfile.ZIP_DEFLATED
                info.create_system = 3
                info.external_attr = 0o100644 << 16
                z.writestr(info, data, compresslevel=9)
        with zipfile.ZipFile(candidate) as z:
            if z.testzip() is not None:
                raise ValueError('Invalid ZIP')
        if candidate.stat().st_size >= 10_000_000:
            raise ValueError('Free ZIP must be below 10,000,000 bytes')
        candidate.replace(archive)
    digest = hashlib.sha256(archive.read_bytes()).hexdigest()
    archive.with_suffix('.zip.sha256').write_text(digest + '  ' + archive.name + '\n')
    return {'archive': str(archive), 'sha256': digest, 'files': len(entries), 'bytes': archive.stat().st_size}


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output-dir', type=Path, default=ROOT / 'dist')
    print(json.dumps(package(parser.parse_args().output_dir.resolve()), indent=2))
