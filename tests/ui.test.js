const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { JSDOM } = require('jsdom');

async function setup(file, extra = '') {
    const dom = new JSDOM(`<form id="rrze-qr-form"><input id="rrze-qr-url"><button type="submit">Generate</button></form>
        <canvas id="rrze-qr-canvas" class="rrze-qr--hidden"></canvas>
        <a id="rrze-qr-download" class="rrze-qr--hidden" download="qr-code.png">Download QR Code</a>${extra}`, {
        url: 'https://example.test/wp-admin/', runScripts: 'outside-only'
    });
    const { window } = dom;
    window.eval(fs.readFileSync(require.resolve('jquery'), 'utf8'));
    const $ = window.jQuery;
    const requests = [];
    $.post = (url, data, callback) => {
        const deferred = $.Deferred();
        if (callback) { deferred.done(callback); }
        requests.push({ data, deferred });
        return deferred.promise();
    };
    window.rrzeQr = { ajaxurl: '/ajax', nonce: 'test', previewSampleUrl: 'https://example.test/' };
    window.QRious = function (options) { this.toDataURL = () => 'data:image/png;base64,' + window.btoa(options.value); };
    window.require = () => require('../src/js/qr-code');
    const alerts = [];
    window.alert = message => alerts.push(message);
    window.eval(fs.readFileSync(file, 'utf8'));
    await new Promise(resolve => $(resolve));
    return { dom, $, requests, alerts };
}

for (const file of ['src/js/rrze-qr.js', 'assets/js/rrze-qr.min.js']) {
    test(`${file}: repeated generation updates one named download`, async () => {
        const { dom, $, requests } = await setup(file);
        try {
            for (const suffix of ['first', 'second']) {
                $('#rrze-qr-url').val('https://example.test/' + suffix);
                $('#rrze-qr-form').trigger('submit');
                requests.at(-1).deferred.resolve({ success: true, data: { foreground: 'black', background: 'white' } });
            }
            assert.equal($('a[download]').length, 1);
            assert.equal($('#rrze-qr-download').text(), 'Download QR Code');
            assert.equal($('#rrze-qr-download').hasClass('rrze-qr--hidden'), false);
            assert.equal(Buffer.from($('#rrze-qr-download').attr('href').split(',')[1], 'base64').toString(), 'https://example.test/second');
            $('#rrze-qr-url').val('https://example.test/' + 'a'.repeat(3000));
            $('#rrze-qr-form').trigger('submit');
            requests.at(-1).deferred.resolve({ success: true, data: {} });
            assert.equal($('#rrze-qr-download').hasClass('rrze-qr--hidden'), true);
        } finally { dom.window.close(); }
    });
}
