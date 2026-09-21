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
    test(`${file}: repeated generation updates one named download`, async () => {
        const { dom, $, requests } = await setup(file);
        try {
            for (const suffix of ['first', 'second']) {
                $('#rrze-qr-url').val('https://example.test/' + suffix);
                $('#rrze-qr-form').trigger('submit');
                requests.at(-1).deferred.resolve({ success: true, data: { foreground: 'black', background: 'white' } });
                await flush();
            }
            assert.equal($('a[download]').length, 1);
            assert.equal($('#rrze-qr-download').text(), 'Download QR Code');
            assert.equal($('#rrze-qr-download').hasClass('rrze-qr--hidden'), false);
            assert.equal(Buffer.from($('#rrze-qr-download').attr('href').split(',')[1], 'base64').toString(), 'https://example.test/second');
            $('#rrze-qr-url').val('https://example.test/' + 'a'.repeat(3000));
            $('#rrze-qr-form').trigger('submit');
            await flush();
            assert.equal($('#rrze-qr-download').hasClass('rrze-qr--hidden'), true);
            assert.match($('#rrze-qr-status').text(), /too long/);
        } finally { dom.window.close(); }
    });

    test(`${file}: failures recover controls and stale responses cannot replace newer input`, async () => {
        const { dom, $, requests } = await setup(file);
        try {
            $('#rrze-qr-url').val('https://example.test/old');
            $('#rrze-qr-form').trigger('submit');
            assert.equal($('#rrze-qr-form button').prop('disabled'), true);
            assert.equal(requests[0].timeout, 15000);
            $('#rrze-qr-url').val('https://example.test/new').trigger('input');
            $('#rrze-qr-form').trigger('submit');
            requests[1].deferred.resolve({ success: true, data: { foreground: 'black', background: 'white' } });
            await flush();
            const href = $('#rrze-qr-download').attr('href');
            requests[0].deferred.resolve({ success: true, data: { foreground: 'white', background: 'black' } });
            await flush();
            assert.equal($('#rrze-qr-download').attr('href'), href);
            $('#rrze-qr-form').trigger('submit');
            requests[2].deferred.reject({ status: 403 });
            await flush();
            assert.match($('#rrze-qr-status').text(), /Reload/);
            assert.equal($('#rrze-qr-form button').prop('disabled'), false);
            assert.equal($('#rrze-qr-download').attr('href'), undefined);
        } finally { dom.window.close(); }
    });

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

    test(`${file}: preview ignores old responses and hides invalid color combinations`, async () => {
        const { dom, $, requests, renders } = await setup(file, `
            <input type="radio" name="rrze_qr_foreground" value="black" checked>
            <input type="radio" name="rrze_qr_background" value="white" checked>
            <canvas id="rrze-qr-settings-preview"></canvas><p id="rrze-qr-preview-status"></p>`);
        try {
            $('input[name="rrze_qr_foreground"]').trigger('change');
            requests[1].deferred.resolve({ success: true, data: { foreground: '#036', background: 'white' } });
            await flush();
            requests[0].deferred.resolve({ success: true, data: { foreground: 'black', background: 'white' } });
            await flush();
            assert.equal(renders.length, 1);
            assert.equal(renders[0].foreground, '#036');
            $('input[name="rrze_qr_background"]').trigger('change');
            requests[2].deferred.reject({ responseJSON: { data: 'Choose contrasting colors.' } });
            await flush();
            assert.equal($('#rrze-qr-settings-preview').hasClass('rrze-qr--hidden'), true);
            assert.match($('#rrze-qr-preview-status').text(), /contrasting/);
        } finally { dom.window.close(); }
    });

    test(`${file}: translated feedback and renderer failures are visible`, async () => {
        const { dom, $, requests } = await setup(file);
        try {
            dom.window.rrzeQr.strings.ready = 'Der QR-Code ist fertig.';
            $('#rrze-qr-url').val('https://example.test/');
            $('#rrze-qr-form').trigger('submit');
            requests[0].deferred.resolve({ success: true, data: { foreground: 'black', background: 'white' } });
            await flush();
            assert.equal($('#rrze-qr-status').text(), 'Der QR-Code ist fertig.');
            delete dom.window.QRious;
            $('#rrze-qr-form').trigger('submit');
            requests[1].deferred.resolve({ success: true, data: { foreground: 'black', background: 'white' } });
            await flush();
            assert.equal($('#rrze-qr-status').text(), 'The QR code could not be generated.');
            assert.equal($('#rrze-qr-form button').prop('disabled'), false);
            assert.equal($('#rrze-qr-download').hasClass('rrze-qr--hidden'), true);
        } finally { dom.window.close(); }
    });
}
