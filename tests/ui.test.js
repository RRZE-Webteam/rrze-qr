const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { JSDOM } = require('jsdom');

async function setup(file, extra = '') {
    const dom = new JSDOM(`<form id="rrze-qr-form"><input id="rrze-qr-url"><button type="submit">Generate</button></form>
        <p id="rrze-qr-status" role="status"></p>
        <canvas id="rrze-qr-canvas" class="rrze-qr--hidden"></canvas>
        <a id="rrze-qr-download" class="rrze-qr--hidden" download="qr-code.png">Download QR Code</a>${extra}`, {
        url: 'https://example.test/wp-admin/', runScripts: 'outside-only'
    });
    const { window } = dom;
    window.eval(fs.readFileSync(require.resolve('jquery'), 'utf8'));
    const $ = window.jQuery;
    const requests = [];
    $.ajax = ({ data, timeout }) => {
        const deferred = $.Deferred();
        requests.push({ data, deferred, timeout });
        return deferred.promise();
    };
    window.rrzeQr = {
        ajaxurl: '/ajax', nonce: 'test', previewSampleUrl: 'https://example.test/',
        strings: {
            invalidUrl: 'Enter a valid HTTP or HTTPS URL.', tooLong: 'This URL is too long.',
            requestFailed: 'The request failed. Reload the page and try again.',
            generationFailed: 'The QR code could not be generated.',
            generating: 'Generating QR code…', downloadStarted: 'QR code download started.',
            ready: 'QR code is ready.', updatingPreview: 'Updating preview…', previewUpdated: 'Preview updated.'
        }
    };
    const renders = [];
    window.QRious = function (options) {
        renders.push(options);
        this.toDataURL = () => 'data:image/png;base64,' + window.btoa(options.value);
    };
    window.require = () => require('../src/js/qr-code');
    const alerts = [];
    window.alert = message => alerts.push(message);
    window.eval(fs.readFileSync(file, 'utf8'));
    await new Promise(resolve => $(resolve));
    return { dom, $, requests, alerts, renders };
}

const flush = () => new Promise(resolve => setTimeout(resolve, 15));

for (const file of ['src/js/rrze-qr.js', 'assets/js/rrze-qr.min.js']) {
    test(`${file}: a row download uses one request and prevents duplicate clicks`, async () => {
        const { dom, $, requests } = await setup(file, '<span><a class="download-qr" data-id="42" href="#">Download QR</a></span>');
        try {
            dom.window.HTMLAnchorElement.prototype.click = function () {};
            $('.download-qr').trigger('click').trigger('click');
            assert.equal(requests.length, 1);
            requests[0].deferred.resolve({ success: true, data: { url: 'https://example.test/post', colors: { foreground: 'black', background: 'white' } } });
            await flush();
            assert.equal(requests.length, 1);
            assert.match($('.rrze-qr-row-status').text(), /download started/);
            assert.equal($('.download-qr').attr('aria-disabled'), undefined);
        } finally { dom.window.close(); }
    });

}
