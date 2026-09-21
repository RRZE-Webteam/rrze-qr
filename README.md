# RRZE QR

Create, configure, and download QR codes from the **QR-Codes** menu in WordPress.
The workspace uses WordPress components and WordPress's bundled React. QR images
are generated locally in the browser; no external QR service receives the URL.

## Requirements

- WordPress 6.4 or newer
- PHP 8.2 or newer
- JavaScript enabled in the administration area

## QR-Codes workspace

Authors, editors, administrators, and custom roles with permission to edit posts
or pages can use the workspace. Enter an HTTP or HTTPS destination URL and adjust:

- Foreground and background: black, white, and FAU blue presets, plus a custom
  color picker for each
- Transparent background: a separate toggle that remembers the last solid color
- Export size: approximately 300, 600, or 1200 pixels

The preview updates automatically. **Download PNG** exports the current code.
A transparent preview has a background selector; this affects the preview only,
and the downloaded image remains transparent.

Changes apply to the current download. Administrators can explicitly **Save as
defaults** to set the colors and export size for future codes, including downloads
from post and page lists. The destination URL is never saved as a site default.
**Reset to defaults** restores the saved appearance without changing the URL.

Existing color settings are retained when upgrading. Bookmarks to the former
Tools and Settings pages redirect to the new workspace.

## Downloads from post and page lists

Use **Download QR** on a published post or page that you can edit. The download
uses the item's permalink and the site's current default colors and size. The
server enforces the same publication and permission requirements as the row action.

## Validation and scanning

Validation checks the URL format and QR capacity. It does not visit the URL,
check its HTTP status, or guarantee that its destination is reachable.
International domain names and paths are normalized to an ASCII URL. URLs longer
than 2,953 encoded characters are rejected.

Identical foreground and background colors cannot be downloaded or saved. Other
custom colors are allowed, with a warning for low contrast. Choose a dark foreground
on a light background for reliable scanning. Transparent codes need a contrasting
surface; test codes with the intended scanner and background before publishing or
printing. Contrast hints do not guarantee that a code will scan.

PNG exports use error correction level L and include a four-module quiet zone.
Dimensions adapt to the QR version so each module occupies whole pixels, with at
least two pixels per module. Dense codes can exceed the smallest requested size.

## Development

```sh
npm ci
npm run build
npm test
npm run lint:js
npm run lint:php
```

The lockfile pins dependencies. Builds regenerate the committed files in `assets/`
and do not change the plugin version. Use `npm run release:patch` or
`npm run release:minor` explicitly when preparing a release.

The React workspace has its own entry point in `src/js/admin.js`. WordPress supplies
`wp-components`, `wp-element`, `wp-i18n`, and the components stylesheet. JSX uses
the classic transform to support WordPress 6.4. The small post-list entry point
uses WordPress's `jquery` dependency; npm jQuery is only for development tests.
QRious is copied from the locked npm dependency when building.

Automated checks cover QR decoding and capacity, quiet zones, color combinations,
post downloads, permissions, default persistence, legacy settings, and asset
registration. PHP checks use isolated WordPress stubs and do not change the
database. Browser interaction and visual checks are performed manually.

Interface strings use the `rrze-qr` text domain. PHP and JavaScript German
translations are included in `languages/`. The JavaScript translation map in
`languages/source-map.json` maps source references to the built entry point.

## License

Licensed under the [GNU General Public License v3.0 or later](https://www.gnu.org/licenses/gpl-3.0.html).
See [LICENSE](LICENSE) and [third-party notices](THIRD-PARTY-NOTICES.md).

Developed by the [RRZE Webteam](https://github.com/RRZE-Webteam),
Friedrich-Alexander-Universität Erlangen-Nürnberg (FAU).
