const { createQr } = require('./qr-code');

jQuery(document).ready(function ($) {
    function rrzeQrCreate(options) {
        try {
            return createQr(QRious, options);
        } catch (error) {
            alert(error.message);
            return null;
        }
    }

    function rrzeQrColorOptionsFromPayload (colors) {
        var c = colors || {};
        return {
            foreground: c.foreground || 'black',
            background: c.background || 'white',
            backgroundAlpha: typeof c.backgroundAlpha === 'number' ? c.backgroundAlpha : 1
        };
    }

    function rrzeQrWithFreshColors (callback) {
        $.post(
            rrzeQr.ajaxurl,
            {
                action: 'rrze_qr_get_colors',
                nonce: rrzeQr.nonce
            },
            function (res) {
                var colors = res.success && res.data ? res.data : (rrzeQr.colors || {});
                callback(colors);
            }
        ).fail(function () {
            callback(rrzeQr.colors || {});
        });
    }

    $('.download-qr').on('click', function (event) {
        event.preventDefault();
        var postId = $(this).data('id');
        var nonce = rrzeQr.nonce;
        var $target = $(event.target);

        $.post(
            rrzeQr.ajaxurl,
            {
                action: 'rrze_qr_get_permalink',
                nonce: nonce,
                post_id: postId
            },
            function (response) {
                if (!response.success) {
                    alert(response.data);
                    return;
                }
                var postUrl = response.data;
                rrzeQrWithFreshColors(function (colors) {
                    var qr = rrzeQrCreate(
                        Object.assign(
                            {
                                value: postUrl,
                                size: 300
                            },
                            rrzeQrColorOptionsFromPayload(colors)
                        )
                    );

                    if (!qr) { return; }
                    var link = $('<a>')
                        .attr('href', qr.toDataURL())
                        .attr('download', 'qr-code.png')
                        .text('Download QR Code');
                    $target.after(link);
                    link[0].click();
                    link.remove();
                });
            }
        );
    });

    $('#rrze-qr-form').on('submit', function (event) {
        event.preventDefault();
        var url = $('#rrze-qr-url').val();
        var canvas = $('#rrze-qr-canvas')[0];
        $('#rrze-qr-download, #rrze-qr-canvas').addClass('rrze-qr--hidden');

        rrzeQrWithFreshColors(function (colors) {
            var qr = rrzeQrCreate(
                Object.assign(
                    {
                        value: url,
                        size: 300,
                        element: canvas
                    },
                    rrzeQrColorOptionsFromPayload(colors)
                )
            );

            if (!qr) { return; }
            $('#rrze-qr-canvas').removeClass('rrze-qr--hidden');

            $('#rrze-qr-download')
                .attr('href', qr.toDataURL())
                .removeClass('rrze-qr--hidden');
        });
    });

    var $settingsPreview = $('#rrze-qr-settings-preview');
    $('#rrze-qr-preview-surface').on('change', function () {
        $settingsPreview.css('background', this.value === 'checkerboard' ? '' : this.value);
    });
    if ($settingsPreview.length && typeof QRious !== 'undefined') {
        var sampleUrl =
            rrzeQr.previewSampleUrl || window.location.href.split('#')[0];
        function rrzeQrUpdateSettingsPreview () {
            var fg = $('input[name="rrze_qr_foreground"]:checked').val();
            var bg = $('input[name="rrze_qr_background"]:checked').val();
            $.post(
                rrzeQr.ajaxurl,
                {
                    action: 'rrze_qr_resolve_colors',
                    nonce: rrzeQr.nonce,
                    foreground: fg,
                    background: bg
                },
                function (res) {
                    if (!res.success || !res.data) {
                        return;
                    }
                    var canvasEl = document.getElementById('rrze-qr-settings-preview');
                    if (!canvasEl) {
                        return;
                    }
                    rrzeQrCreate(
                        Object.assign(
                            {
                                element: canvasEl,
                                value: sampleUrl,
                                size: 180
                            },
                            rrzeQrColorOptionsFromPayload(res.data)
                        )
                    );
                }
            );
        }
        $('input[name="rrze_qr_foreground"], input[name="rrze_qr_background"]').on(
            'change',
            rrzeQrUpdateSettingsPreview
        );
        rrzeQrUpdateSettingsPreview();
    }
});
