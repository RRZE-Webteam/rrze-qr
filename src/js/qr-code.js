// QRious encodes single-byte values at level L (maximum 2,953 bytes).
function normalizeUrl(value) {
    let url;
    try {
        url = new URL(value);
    } catch (error) {
        throw new Error('Enter a valid HTTP or HTTPS URL.');
    }
    if (!['http:', 'https:'].includes(url.protocol)) {
        throw new Error('Enter a valid HTTP or HTTPS URL.');
    }
    const normalized = url.href;
    if (normalized.length > 2953) {
        throw new Error('This URL is too long for a QR code. Use a shorter URL (maximum 2,953 encoded characters).');
    }
    return normalized;
}

function createQr(QRious, options) {
    return new QRious(Object.assign({}, options, {
        value: normalizeUrl(options.value),
        level: 'L'
    }));
}

module.exports = { normalizeUrl, createQr };
