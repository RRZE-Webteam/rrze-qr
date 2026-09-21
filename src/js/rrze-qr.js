const { createQr, normalizeUrl } = require('./qr-code');

jQuery(document).ready(function ($) {
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
                throw new Error(typeof response?.data === 'string' ? response.data : 'The request failed. Reload the page and try again.');
            }
            return response.data;
        } catch (error) {
            if (error instanceof Error) { throw error; }
            throw new Error(typeof error.responseJSON?.data === 'string' ? error.responseJSON.data : 'The request failed. Reload the page and try again.');
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
        status($status, 'Generating QR code…');
        try {
            const data = await request({ action: 'rrze_qr_get_permalink', post_id: $link.data('id') });
            const qr = createQr(QRious, Object.assign({ value: data.url, size: 300 }, colorOptions(data.colors)));
            const download = $('<a>').attr({ href: qr.toDataURL(), download: 'qr-code-' + $link.data('id') + '.png' });
            $link.after(download);
            download[0].click();
            download.remove();
            status($status, 'QR code download started.');
        } catch (error) {
            status($status, error.message);
        } finally {
            $link.removeAttr('aria-disabled aria-busy');
        }
    });

    const $form = $('#rrze-qr-form');
    const $submit = $form.find('button[type="submit"]');
    const $status = $('#rrze-qr-status');
    let generation = 0;
    function hideResult() {
        $('#rrze-qr-download, #rrze-qr-canvas').addClass('rrze-qr--hidden');
        $('#rrze-qr-download').removeAttr('href');
    }
    $('#rrze-qr-url').on('input', function () {
        generation++;
        hideResult();
        $submit.prop('disabled', false);
        $form.removeAttr('aria-busy');
        status($status, '');
    });
    $form.on('submit', async function (event) {
        event.preventDefault();
        const current = ++generation;
        hideResult();
        $submit.prop('disabled', true);
        $form.attr('aria-busy', 'true');
        status($status, 'Generating QR code…');
        try {
            const url = normalizeUrl($('#rrze-qr-url').val());
            const colors = await request({ action: 'rrze_qr_get_colors' });
            if (current !== generation) { return; }
            const qr = createQr(QRious, Object.assign({ value: url, size: 300, element: $('#rrze-qr-canvas')[0] }, colorOptions(colors)));
            $('#rrze-qr-canvas').removeClass('rrze-qr--hidden');
            $('#rrze-qr-download').attr('href', qr.toDataURL()).removeClass('rrze-qr--hidden');
            status($status, 'QR code is ready.');
        } catch (error) {
            if (current === generation) { status($status, error.message); }
        } finally {
            if (current === generation) {
                $submit.prop('disabled', false);
                $form.removeAttr('aria-busy');
            }
        }
    });

    const $preview = $('#rrze-qr-settings-preview');
    $('#rrze-qr-preview-surface').on('change', function () {
        $preview.css('background', this.value === 'checkerboard' ? '' : this.value);
    });
    let previewGeneration = 0;
    async function updatePreview() {
        const current = ++previewGeneration;
        const $previewStatus = $('#rrze-qr-preview-status');
        $preview.addClass('rrze-qr--hidden');
        status($previewStatus, 'Updating preview…');
        try {
            const colors = await request({
                action: 'rrze_qr_resolve_colors',
                foreground: $('input[name="rrze_qr_foreground"]:checked').val(),
                background: $('input[name="rrze_qr_background"]:checked').val()
            });
            if (current !== previewGeneration) { return; }
            createQr(QRious, Object.assign({ element: $preview[0], value: rrzeQr.previewSampleUrl, size: 180 }, colorOptions(colors)));
            $preview.removeClass('rrze-qr--hidden');
            status($previewStatus, 'Preview updated.');
        } catch (error) {
            if (current === previewGeneration) { status($previewStatus, error.message); }
        }
    }
    if ($preview.length) {
        $('input[name="rrze_qr_foreground"], input[name="rrze_qr_background"]').on('change', updatePreview);
        updatePreview();
    }
});
