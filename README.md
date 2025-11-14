# RRZE QR

[![Version](https://img.shields.io/github/package-json/v/rrze-webteam/rrze-qr/main?label=Version)](https://github.com/RRZE-Webteam/rrze-qr)
[![Release
Version](https://img.shields.io/github/v/release/rrze-webteam/rrze-qr?label=Release+Version)](https://github.com/RRZE-Webteam/rrze-qr/releases/)
[![GitHub
License](https://img.shields.io/github/license/rrze-webteam/rrze-qr)](https://github.com/RRZE-Webteam/rrze-qr)
[![GitHub
issues](https://img.shields.io/github/issues/rrze-webteam/rrze-qr)](https://github.com/RRZE-Webteam/rrze-qr/issues)

------------------------------------------------------------------------

## Overview

**RRZE QR** provides a simple way to generate **QR codes** for:

-   Posts\
-   Pages\
-   Arbitrary URLs

The plugin integrates directly into the WordPress backend and allows
users to quickly create and download QR codes pointing to the permalink
of any published page or post --- or to any custom URL entered via a
built-in tool.

------------------------------------------------------------------------

## Features

-   **Generate QR codes for posts and pages:**\
    Adds a "Generate QR" link to the list view ("All Posts", "All
    Pages") for every *published* item.

-   **Download as PNG:**\
    Clicking "Generate QR" returns a ready-to-download PNG file
    containing the QR code for the permalink.

-   **Tool section with URL input:**\
    Under **Tools → Generate QR**, users can enter any URL, validate it,
    and instantly generate a QR code.

-   **Client-side rendering:**\
    QR codes are generated directly in the browser via JavaScript --- no
    server-side processing required.

-   **Lightweight & fast:**\
    Minimal footprint and no external API calls.

------------------------------------------------------------------------

## How It Works

### 1. QR codes for posts & pages

In the WordPress backend, the plugin adds a **"Generate QR"** action
link next to each published post or page.

When clicked:

1.  The permalink of the item is determined.\
2.  A QR code is generated using the **QRious** library.\
3.  The browser displays a PNG download dialog.

### 2. QR generator tool

In the admin area under:

    Tools → Generate QR

you'll find:

-   A URL input field\
-   Live URL validation\
-   Instant QR code rendering\
-   A download button for the PNG file

If the URL is valid (HTTP status is not 4xxx), the QR code is displayed.

------------------------------------------------------------------------

## Libraries

-   **QRious** -- QR code rendering (minified version bundled)\
-   **jQuery** -- Used by the backend UI

Both libraries are included directly in the plugin.

------------------------------------------------------------------------

## License

Licensed under the\
[GNU General Public License v2.0 or
later](https://www.gnu.org/licenses/gpl-2.0.html).

------------------------------------------------------------------------

## Credits

Developed and maintained by the\
**RRZE Webteam, Friedrich-Alexander-Universität Erlangen-Nürnberg
(FAU)**\
👉 https://github.com/RRZE-Webteam/rrze-qr
