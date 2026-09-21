const palette = { white: 'white', black: 'black', fau: '#036' };

function resolveColors(foreground, background) {
    if (!Object.hasOwn(palette, foreground) || !(Object.hasOwn(palette, background) || background === 'transparent')
        || (background !== 'transparent' && (foreground === background || (foreground !== 'white' && background !== 'white')))) {
        const error = new Error('Choose contrasting foreground and background colors.');
        error.code = 'contrast';
        throw error;
    }
    return {
        foreground: palette[foreground],
        background: background === 'transparent' ? 'white' : palette[background],
        backgroundAlpha: background === 'transparent' ? 0 : 1
    };
}

module.exports = { resolveColors };
