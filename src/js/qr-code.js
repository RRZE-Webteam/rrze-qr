// Byte capacities for QR versions 1–40 at error correction level L.
const capacities = [17, 32, 53, 78, 106, 134, 154, 192, 230, 271, 321, 367, 425, 458,
    520, 586, 644, 718, 792, 858, 929, 1003, 1091, 1171, 1273, 1367, 1465, 1528,
    1628, 1732, 1840, 1952, 2068, 2188, 2303, 2431, 2563, 2699, 2809, 2953];

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
    const value = normalizeUrl(options.value);
    const version = capacities.findIndex(capacity => value.length <= capacity) + 1;
    const modules = 17 + 4 * version;
    // Include four blank modules on each edge; keep modules at least two pixels wide.
    const moduleSize = Math.max(2, Math.floor((options.size || 300) / (modules + 8)));
    return new QRious(Object.assign({}, options, {
        value,
        level: 'L',
        size: (modules + 8) * moduleSize,
        padding: 4 * moduleSize
    }));
}

module.exports = { normalizeUrl, createQr };
