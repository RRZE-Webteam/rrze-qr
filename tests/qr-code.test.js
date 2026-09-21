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

test('exports four-module quiet zones and decodable images at version transitions', () => {
    for (const length of [17, 18, 32, 33, 230, 231, 271, 272, 2809, 2810, 2953]) {
        const prefix = 'https://a.co/';
        const value = prefix + 'a'.repeat(length - prefix.length);
        const qr = createQr(QRious, { value, size: 300 });
        const { width, height, pixels } = qr.canvas;
        assert.ok(qr.padding >= 8, 'At least two pixels per module');
        for (let y = 0; y < height; y++) {
            for (let x = 0; x < width; x++) {
                if (x < qr.padding || y < qr.padding || x >= width - qr.padding || y >= height - qr.padding) {
                    assert.equal(pixels[(y * width + x) * 4], 255, 'The quiet zone must be blank');
                }
            }
        }
        assert.equal(decode(pixels, width, height)?.data, value);
    }
});
