const legacyColors = { white: '#ffffff', black: '#000000', fau: '#04316a' };

function normalizeColor(value) {
    if (typeof value !== 'string') { return null; }
    const color = value.trim().toLowerCase();
    if (Object.hasOwn(legacyColors, color)) { return legacyColors[color]; }
    if (/^#[0-9a-f]{6}$/.test(color)) { return color; }
    if (/^#[0-9a-f]{3}$/.test(color)) {
        return '#' + [...color.slice(1)].map(character => character + character).join('');
    }
    return null;
}

function resolveColors(foreground, background) {
    const fg = normalizeColor(foreground);
    const bg = background === 'transparent' ? 'transparent' : normalizeColor(background);
    if (!fg || !bg || fg === bg) {
        const error = new Error('Choose valid, different foreground and background colors.');
        error.code = !fg || !bg ? 'invalidColor' : 'contrast';
        throw error;
    }
    return {
        foreground: fg,
        background: bg === 'transparent' ? '#ffffff' : bg,
        backgroundAlpha: bg === 'transparent' ? 0 : 1
    };
}

function luminance(hex) {
    const channels = hex.slice(1).match(/../g).map(channel => {
        const value = parseInt(channel, 16) / 255;
        return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
    });
    return channels[0] * 0.2126 + channels[1] * 0.7152 + channels[2] * 0.0722;
}

// A contrast hint for solid backgrounds, not a guarantee that a code will scan.
function colorContrast(colors) {
    if (colors.backgroundAlpha === 0) { return null; }
    const foreground = luminance(colors.foreground);
    const background = luminance(colors.background);
    return {
        ratio: (Math.max(foreground, background) + 0.05) / (Math.min(foreground, background) + 0.05),
        inverted: foreground > background
    };
}

module.exports = { normalizeColor, resolveColors, colorContrast };
