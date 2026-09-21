# RRZE QR

Generate and download QR codes in the WordPress administration area. QR images
are created locally in the browser with QRious; no external QR service receives
the URL.

## Requirements

- WordPress 6.4 or newer
- PHP 8.2 or newer
- JavaScript enabled in the administration area

## Usage

### Published posts and pages

Use **Download QR** in the posts or pages list to download a PNG containing the
item's permalink. The action is available for published posts and pages that the
current user can edit. The server enforces the same restrictions.

### Custom URLs

Administrators can open **Tools → Generate QR Code**, enter an HTTP or HTTPS URL,
and generate a preview with a download link. Generating another code updates the
same download link. Editing the URL hides the previous result.

Validation checks the URL format and QR capacity. It does **not** visit the URL,
check its HTTP status, or guarantee that its destination is reachable.
International domain names and paths are normalized to an ASCII URL before
encoding. URLs longer than 2,953 encoded characters are rejected.

### Colors and preview

Under **Settings → RRZE QR**, administrators can choose white, black, or FAU blue
for the foreground, and white, black, FAU blue, or transparency for the background.
The live preview uses the site's home URL. Its background selector helps evaluate
transparent output without changing the downloaded image.

Solid colors must contrast: one must be white and the other black or FAU blue.
Invalid saved combinations fall back to black on white with a settings message.
Transparent codes require a contrasting surface. Test inverted or transparent
codes with the intended scanners and background before publishing or printing.

### Export behavior

- PNG downloads use error correction level L.
- Every image reserves a four-module quiet zone on all sides.
- Image dimensions adapt to the QR version, with at least two pixels per module.
  Typical exports are approximately 300 pixels wide; dense codes can be larger.
- Network or generation failures appear alongside the relevant control.

## Development

```sh
npm ci
npm run build
npm test
npm run lint:js
npm run lint:php
```

The lockfile pins dependencies. Builds generate the committed files in `assets/`
and do not change the plugin version. Use `npm run release:patch` or
`npm run release:minor` explicitly when preparing a release, then review the
version changes.

Tests cover QR decoding and capacity boundaries, quiet zones, repeated generation,
request failures and response ordering, endpoint permissions, settings colors,
preview markup, asset versions, and translated feedback. PHP checks use isolated
WordPress stubs and do not alter the database. Browser, scanner, and real WordPress
role testing remain useful release checks.

## Libraries and translations

WordPress supplies jQuery through the script's `jquery` enqueue dependency. The
npm jQuery package is used only by development tests. QRious is copied from the
locked npm dependency into `assets/js/qrious.min.js` during the build.

Interface strings use the `rrze-qr` text domain. A translation template and German
translations are included in `languages/`.

## License

Licensed under the [GNU General Public License v3.0 or later](https://www.gnu.org/licenses/gpl-3.0.html).

See [LICENSE](LICENSE) for the license text and [third-party notices](THIRD-PARTY-NOTICES.md) for QRious attribution.

Developed by the [RRZE Webteam](https://github.com/RRZE-Webteam),
Friedrich-Alexander-Universität Erlangen-Nürnberg (FAU).
