// Called when a QR code is successfully scanned
function onScanSuccess(decodedText, decodedResult) {
    $('#status')
        .removeClass()
        .addClass('alert alert-secondary')
        .text('Processing...');

    $.post('attendance_in.php', { enroll: decodedText }, function (response) {
        if (response === 'success') {
            $('#status')
                .removeClass()
                .addClass('alert alert-success')
                .text('Attendance marked!');
            setTimeout(() => location.reload(), 1000);
        } else if (response === 'already') {
            $('#status')
                .removeClass()
                .addClass('alert alert-warning')
                .text('Already marked today.');
        } else if (response === 'not_found') {
            $('#status')
                .removeClass()
                .addClass('alert alert-danger')
                .text('Student not found.');
        } else {
            $('#status')
                .removeClass()
                .addClass('alert alert-danger')
                .text('Unexpected error.');
        }
    });
}

// Initialize QR scanner
function initQrScanner() {
    const scanner = new Html5QrcodeScanner("reader", {
        fps: 10,
        qrbox: 250,
        rememberLastUsedCamera: true,
    });

    scanner.render(onScanSuccess);
}

// Auto-start on page load
$(document).ready(function () {
    initQrScanner();
});
