jQuery(document).ready(function ($) {
    $('.download-qr').on('click', function (event) {
        event.preventDefault();
        var postId = $(this).data('id');
        var nonce = rrzeQr.nonce;

        // AJAX request to get the permalink
        $.post(rrzeQr.ajaxurl, {
            action: 'rrze_qr_get_permalink',
            nonce: nonce,
            post_id: postId
        }, function (response) {
            if (response.success) {
                var postUrl = response.data;

                var qr = new QRious({
                    value: postUrl,
                    size: 300
                });

                var link = $('<a>')
                    .attr('href', qr.toDataURL())
                    .attr('download', 'qr-code.png')
                    .text('Download QR Code');
                $(event.target).after(link);
                link[0].click();
                link.remove();
            } else {
                alert(response.data);
            }
        });
    });

    $('#rrze-qr-form').on('submit', function (event) {
        event.preventDefault();
        var url = $('#rrze-qr-url').val();
        var canvas = $('#rrze-qr-canvas')[0];

        var qr = new QRious({
            value: url,
            size: 300,
            element: canvas
        });

        // Zeige den QR-Code im Canvas an
        $('#rrze-qr-canvas').show();

        // Erstelle den Download-Button für den QR-Code
        var downloadLink = $('<a>')
            .attr('href', qr.toDataURL())
            .attr('download', 'qr-code.png')
            .addClass('button button-primary')
            .css('margin-left', '10px') // Beispiel: Füge etwas Abstand zum QR-Code hinzu
            .html('<span class="rrze-qr-download-icon dashicons dashicons-download"></span>');

        $('#rrze-qr-form').append(downloadLink);
    });
});
