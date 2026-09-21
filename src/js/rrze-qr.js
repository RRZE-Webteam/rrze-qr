const { createQr } = require('./qr-code');

jQuery(document).ready(function ($) {
    function text(key) {
        return rrzeQr.strings[key];
    }
    function errorText(error) {
        return (error.code && text(error.code)) || error.message;
    }
    function render(options) {
        try {
            return createQr(QRious, options);
        } catch (error) {
            if (!error.code) { error.code = 'generationFailed'; }
            throw error;
        }
    }

    function colorOptions(colors) {
        return {
            foreground: colors.foreground,
            background: colors.background,
            backgroundAlpha: colors.backgroundAlpha
        };
    }

    async function request(data) {
        try {
            const response = await $.ajax({
                url: rrzeQr.ajaxurl,
                method: 'POST',
                data: Object.assign({ nonce: rrzeQr.nonce }, data),
                timeout: 15000
            });
            if (!response || !response.success || !response.data) {
                throw new Error(typeof response?.data === 'string' ? response.data : text('requestFailed'));
            }
            return response.data;
        } catch (error) {
            if (error instanceof Error) { throw error; }
            throw new Error(typeof error.responseJSON?.data === 'string' ? error.responseJSON.data : text('requestFailed'));
        }
    }

    function status($element, message) {
        $element.text(message);
    }

    $(document).on('click', '.download-qr', async function (event) {
        event.preventDefault();
        const $link = $(this);
        if ($link.attr('aria-disabled') === 'true') { return; }
        let $status = $link.siblings('.rrze-qr-row-status');
        if (!$status.length) {
            $status = $('<span class="rrze-qr-row-status" role="status" aria-live="polite"></span>');
            $link.after($status);
        }
        $link.attr({ 'aria-disabled': 'true', 'aria-busy': 'true' });
        status($status, text('generating'));
        try {
            const data = await request({ action: 'rrze_qr_get_permalink', post_id: $link.data('id') });
            const qr = render(Object.assign({ value: data.url, size: data.size || 300 }, colorOptions(data.colors)));
            const download = $('<a>').attr({ href: qr.toDataURL(), download: 'qr-code-' + $link.data('id') + '.png' });
            $link.after(download);
            download[0].click();
            download.remove();
            status($status, text('downloadStarted'));
        } catch (error) {
            status($status, errorText(error));
        } finally {
            $link.removeAttr('aria-disabled aria-busy');
        }
    });

});
