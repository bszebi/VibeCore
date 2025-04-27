<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Bank QR Kód</title>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            margin: 50px;
        }
        #qrcode {
            margin: 20px auto;
        }
    </style>
</head>
<body>
    <h2>OTP Bank Átutalási QR-kód</h2>
    <p>Szkenneld be a telefonoddal az OTP SmartBank megnyitásához!</p>

    <div id="qrcode"></div>

    <script>



        var qrcode = new QRCode(document.getElementById("qrcode"), {
            text: "IBAN: HU92117734180088534000000000\nNév: Kedvezményezett\nÖsszeg: 1000.00 HUF\nKözlemény: QR fizetes",
            width: 256,
            height: 256,
            colorDark : "#000000",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.H
        });
    </script>
</body>
</html>
