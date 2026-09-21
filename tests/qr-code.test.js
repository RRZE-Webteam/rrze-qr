const { test } = require('node:test');
const assert = require('node:assert/strict');
const decode = require('jsqr');
const { createQr, normalizeUrl } = require('../src/js/qr-code');

// Rasterize the real bundled encoder's canvas calls for independent decoding.
class Canvas {
    getContext() {
        const canvas = this;
        return {
            fillStyle: 'white',
            clearRect() { canvas.pixels = new Uint8ClampedArray(canvas.width * canvas.height * 4); },
            fillRect(x, y, width, height) {
                const shade = this.fillStyle === 'black' ? 0 : 255;
                for (let row = y; row < y + height; row++) {
                    for (let col = x; col < x + width; col++) {
                        const offset = (row * canvas.width + col) * 4;
                        canvas.pixels.set([shade, shade, shade, 255], offset);
                    }
                }
            }
        };
    }
}
global.HTMLCanvasElement = Canvas;
global.HTMLImageElement = class {};
global.document = { createElement: tag => tag === 'canvas' ? new Canvas() : new HTMLImageElement() };
const QRious = require('../assets/js/qrious.min.js');

test('rejects over-capacity URLs instead of silently truncating', () => {
    const prefix = 'https://example.com/';
    for (const value of [prefix + 'a'.repeat(3000), prefix + 'a'.repeat(3000) + 'different']) {
        assert.throws(() => createQr(QRious, { value, size: 800 }), /too long/);
    }
    assert.throws(() => normalizeUrl(prefix + 'ä'.repeat(600)), /too long/);
});

test('encodes a full URL at the capacity boundary', () => {
    const prefix = 'https://example.com/';
    const value = prefix + 'a'.repeat(2953 - prefix.length);
    const qr = createQr(QRious, { value, size: 800 });
    const result = decode(qr.canvas.pixels, qr.canvas.width, qr.canvas.height);
    assert.equal(result?.data, value);
    assert.throws(() => normalizeUrl(value + 'b'), /too long/);
});

test('normalizes international URLs to an ASCII URL that round-trips', () => {
    const value = 'https://münchen.example/straße?q=こんにちは#你好';
    const qr = createQr(QRious, { value, size: 400 });
    const result = decode(qr.canvas.pixels, qr.canvas.width, qr.canvas.height);
    assert.equal(result?.data, new URL(value).href);
});

test('rejects malformed and unsupported URLs', () => {
    for (const value of ['', 'example.com', 'javascript:alert(1)', 'ftp://example.com']) {
        assert.throws(() => normalizeUrl(value), /valid HTTP/);
    }
});
