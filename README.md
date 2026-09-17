# OfferWeave Free

An editable, self-contained WordPress plugin for service catalogues, quantity-based
price calculation and quote requests. Developed and published by
**Convati UG (haftungsbeschränkt)**. Licensed under **GPL-3.0-only**; see [LICENSE](LICENSE).

This repository contains the complete **Free edition**. Read [readme.txt](readme.txt)
for features, installation, external services and the changelog. Download the
installable Free ZIP from [Releases](https://github.com/Convati-UG-haftungsbeschrankt/offerweave/releases).

## Requirements

- WordPress 6.5 or later, PHP 8.1 or later.
- Python 3.10 or later with its standard library to create the installation ZIP.
- PHP and Node.js are useful for optional source syntax checks. WordPress itself
  provides the plugin's JavaScript dependencies.

## Build from a clean clone

```sh
git clone https://github.com/Convati-UG-haftungsbeschrankt/offerweave.git
cd offerweave
python3 tools/package.py
```

The output is `dist/offerweave-free-VERSION.zip` and its `.sha256` file. Upload the
ZIP through **WordPress > Plugins > Add New > Upload Plugin**. The versioned release
tag contains the exact sources used for that release. To compare a local build
with an official release, download the release's `.sha256` file and compare its
digest with the local ZIP. Deterministic archive output uses fixed timestamps,
permissions and entry ordering; a different zlib implementation can affect ZIP
compression bytes without changing any contained source bytes.

## Edit and rebuild

Edit the PHP, CSS or readable JavaScript directly at its path in this repository.
For example, `assets/admin-free.js` is the complete editable Free editor; no private
generator or paid source is needed. Run `python3 tools/package.py` again. It reads
the current files, regenerates the file hashes and packages the changes. It never
copies a previously built ZIP. To add or remove a runtime file, update the explicit
list in `tools/files.json`. Development files and `.git` are excluded from the ZIP.

For a new version update the header and `OFFERWEAVE_VERSION` in `offerweave.php`,
the stable tag/changelog in `readme.txt`, and the visible version in both bundled
handbook languages and `handbook/manifest.json`. Keep the handbook file checksums
in that manifest synchronized with any edited handbook files. The package manifest's version and
file hashes are regenerated. `release-manifest.json` also retains the original
distribution's relative composition provenance for unchanged files; those paths
are metadata, not build dependencies. Edited files receive their public path as
their source reference. All required editable sources are already here.

Optional syntax checks use `php -l path/to/file.php` and
`node --check assets/admin-free.js`. For runtime validation, install the built ZIP
in a local WordPress test site and run the official
[Plugin Check](https://wordpress.org/plugins/plugin-check/). Test form delivery
using your local mail catcher and fictional data.

## Translations and third-party sources

Free uses normal WordPress.org language packs. OfferWeave translations become
available through WordPress when the corresponding packs are published. Missing
interface translations use the English source text. Saved offer-content translations
are independent of interface language packs.

Free does not include the Freemius SDK and does not contact Freemius in the background.
The local Pro price comparison contains optional links to the external Freemius
checkout and customer portal. Opening them is your choice. The separate Pro
package uses the SDK for its licence, update and account integration.

For documentation and support, see the bundled `handbook/` or
[offerweave.de](https://offerweave.de/). Do not include customer data or credentials
in public issues.
