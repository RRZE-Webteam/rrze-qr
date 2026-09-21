const { test } = require('node:test');
const assert = require('node:assert/strict');
const { resolveColors } = require('../src/js/qr-settings');

test('local previews reject unsafe color pairs and preserve transparency', () => {
    for (const foreground of ['white', 'black', 'fau']) {
        for (const background of ['white', 'black', 'fau', 'transparent']) {
            const valid = background === 'transparent' || (foreground !== background && [foreground, background].includes('white'));
            if (valid) {
                const colors = resolveColors(foreground, background);
                assert.equal(colors.backgroundAlpha, background === 'transparent' ? 0 : 1);
            } else {
                assert.throws(() => resolveColors(foreground, background), { code: 'contrast' });
            }
        }
    }
    assert.throws(() => resolveColors('toString', 'white'), { code: 'contrast' });
    assert.throws(() => resolveColors('black', 'red'), { code: 'contrast' });
});
