const { test } = require('node:test');
const assert = require('node:assert/strict');
const decode = require('jsqr');
const { createQr, normalizeUrl } = require('../src/js/qr-code');
const { resolveColors } = require('../src/js/qr-settings');

// Rasterize the real bundled encoder's canvas calls for independent decoding.
class Canvas {
    getContext() {
        const canvas = this;
        return {
            fillStyle: 'white',
            clearRect() { canvas.pixels = new Uint8ClampedArray(canvas.width * canvas.height * 4); },
            fillRect(x, y, width, height) {
                const color = { black: '#000000', white: '#ffffff' }[this.fillStyle] || this.fillStyle;
                const channels = color.slice(1).match(/../g).map(channel => parseInt(channel, 16));
                for (let row = y; row < y + height; row++) {
                    for (let col = x; col < x + width; col++) {
                        const offset = (row * canvas.width + col) * 4;
                        canvas.pixels.set([...channels, 255], offset);
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


test('renders custom foreground and background colors into a decodable QR code', () => {
    const value = 'https://example.com/custom-colors';
    const qr = createQr(QRious, { value, size: 300, ...resolveColors('#123456', '#fedcba') });
    const { pixels, width, height } = qr.canvas;
    assert.deepEqual([...pixels.slice(0, 4)], [254, 220, 186, 255]);
    const foregroundPixels = [];
    for (let offset = 0; offset < pixels.length; offset += 4) {
        if (pixels[offset] === 18 && pixels[offset + 1] === 52 && pixels[offset + 2] === 86) {
            foregroundPixels.push(offset);
        }
    }
    assert.ok(foregroundPixels.length > 0, 'Custom foreground must be rendered');
    assert.equal(decode(pixels, width, height)?.data, value);
});


test('custom sizes keep exact square dimensions and remain decodable', () => {
    const value = 'https://example.com/custom-size';
    for (const size of [128, 257, 512, 777, 1024]) {
        const qr = createQr(QRious, { value, size });
        assert.equal(qr.canvas.width, size);
        assert.equal(qr.canvas.height, size);
        assert.equal(decode(qr.canvas.pixels, size, size)?.data, value);
    }
});

test('dense codes grow only as needed and invalid dimensions never reach the renderer', () => {
    const value = 'https://example.com/' + 'a'.repeat(2800);
    const qr = createQr(QRious, { value, size: 128 });
    assert.ok(qr.size > 128);
    assert.equal(qr.canvas.width, qr.canvas.height);
    assert.equal(decode(qr.canvas.pixels, qr.size, qr.size)?.data, value);
    for (const size of [0, 127, 4097, 500.5, '', '1e3', null]) {
        assert.throws(() => createQr(QRious, { value, size }), { code: 'invalidSize' });
    }
    // The largest allowed canvas need not be rasterized just to check its bounds.
    const options = createQr(function (config) { Object.assign(this, config); }, { value, size: 4096 });
    assert.equal(options.size, 4096);
});
