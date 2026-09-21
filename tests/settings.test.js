const { test } = require('node:test');
const assert = require('node:assert/strict');
const { normalizeColor, resolveColors, colorContrast } = require('../src/js/qr-settings');

test('preserves legacy presets and allows custom opaque hex colors', () => {
    for (const foreground of ['white', 'black', 'fau']) {
        for (const background of ['white', 'black', 'fau', 'transparent']) {
            if (foreground !== background) {
                const colors = resolveColors(foreground, background);
                assert.equal(colors.backgroundAlpha, background === 'transparent' ? 0 : 1);
            } else {
                assert.throws(() => resolveColors(foreground, background), { code: 'contrast' });
            }
        }
    }
    assert.deepEqual(resolveColors(' #A1b ', '#FEDcBa'), {
        foreground: '#aa11bb', background: '#fedcba', backgroundAlpha: 1
    });
    assert.deepEqual(resolveColors('#123456', 'transparent'), {
        foreground: '#123456', background: '#ffffff', backgroundAlpha: 0
    });
    assert.equal(normalizeColor('FAU'), '#003366');
});

test('rejects invalid colors and equivalent foreground/background values', () => {
    for (const value of [undefined, null, [], {}, '', 'toString', 'red', 'transparent', 'url(x)', 'rgb(0,0,0)', '#12', '#gggggg', '#0008', '#00000080']) {
        assert.equal(normalizeColor(value), null);
        assert.throws(() => resolveColors(value, '#fff'), { code: 'invalidColor' });
        if (value !== 'transparent') {
            assert.throws(() => resolveColors('#000', value), { code: 'invalidColor' });
        }
    }
    for (const [foreground, background] of [['white', '#fff'], ['#abc', '#AABBCC'], ['#003366', 'fau']]) {
        assert.throws(() => resolveColors(foreground, background), { code: 'contrast' });
    }
});

test('contrast hints distinguish low contrast, inverted colors, and transparency', () => {
    assert.deepEqual(colorContrast(resolveColors('#000', '#fff')), { ratio: 21, inverted: false });
    assert.deepEqual(colorContrast(resolveColors('#fff', '#000')), { ratio: 21, inverted: true });
    assert.ok(colorContrast(resolveColors('#eee', '#fff')).ratio < 3);
    assert.ok(colorContrast(resolveColors('#123456', '#fedcba')).ratio > 3);
    assert.equal(colorContrast(resolveColors('#123456', 'transparent')), null);
});
