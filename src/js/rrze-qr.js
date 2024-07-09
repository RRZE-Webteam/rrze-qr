jQuery(document).ready(function($) {
    $('.download-qr').on('click', function(event) {
        event.preventDefault();
        var postId = $(this).data('id');
        var nonce = rrzeQr.nonce;

        alert('hi');

        // AJAX request to get the permalink
        $.post(rrzeQr.ajaxurl, {
            action: 'rrze_qr_get_permalink',
            nonce: nonce,
            post_id: postId
        }, function(response) {
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

    $('#rrze-qr-form').on('submit', function(event) {
        event.preventDefault();
        var url = $('#rrze-qr-url').val();
        var nonce = rrzeQr.nonce;

        $.post(rrzeQr.ajaxurl, {
            action: 'rrze_qr_generate',
            nonce: nonce,
            url: url
        }, function(response) {
            if (response.success) {
                var qr = new QRious({
                    value: response.data,
                    size: 300
                });

                var canvas = $('#rrze-qr-canvas')[0];
                $('#rrze-qr-canvas').show();
                qr.set({
                    element: canvas
                });

                var downloadLink = $('#rrze-qr-download');
                downloadLink.attr('href', qr.toDataURL());
                downloadLink.show();
            } else {
                alert(response.data);
            }
        });
    });
});
