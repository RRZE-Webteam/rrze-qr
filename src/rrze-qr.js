document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.generate-qr').forEach(function(element) {
        element.addEventListener('click', function(event) {
            event.preventDefault();
            var postId = event.target.getAttribute('data-id');
            var postUrl = location.origin + '/?p=' + postId;

            var qr = new QRious({
                value: postUrl,
                size: 300
            });

            var link = document.createElement('a');
            link.href = qr.toDataURL();
            link.download = 'qr-code.png';
            link.textContent = 'Download QR Code';
            event.target.parentNode.appendChild(link);
        });
    });

    var form = document.getElementById('rrze-qr-form');
    if (form) {
        form.addEventListener('submit', function(event) {
            event.preventDefault();
            var url = document.getElementById('rrze-qr-url').value;
            var nonce = rrzeQr.nonce;

            fetch(rrzeQr.ajaxurl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'rrze_qr_generate',
                    nonce: nonce,
                    url: url
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    var qr = new QRious({
                        value: data.data,
                        size: 300
                    });

                    var canvas = document.getElementById('rrze-qr-canvas');
                    canvas.style.display = 'block';
                    qr.set({
                        element: canvas
                    });

                    var downloadLink = document.getElementById('rrze-qr-download');
                    downloadLink.href = qr.toDataURL();
                    downloadLink.style.display = 'block';
                } else {
                    alert(data.data);
                }
            });
        });
    }
});
