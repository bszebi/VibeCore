<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eszközök QR Kódjai</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        .qr-container {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .qr-content {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            text-align: center;
        }
        .eszköz-card {
            transition: transform 0.2s;
            cursor: pointer;
        }
        .eszköz-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .search-container {
            max-width: 600px;
            margin: 2rem auto;
        }
        #qrcode {
            margin: 1rem auto;
        }
        .print-button {
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container mt-5">
        <h1 class="text-center mb-4">Eszközök QR Kódjai</h1>
        
        <div class="search-container">
            <div class="input-group mb-4">
                <input type="text" id="searchInput" class="form-control" placeholder="Keresés eszközök között...">
                <button class="btn btn-primary" type="button">
                    <i class="fas fa-search"></i> Keresés
                </button>
            </div>
        </div>

        <div class="row row-cols-1 row-cols-md-3 g-4" id="eszkozList">
            <?php
            require_once 'includes/db.php';
            
            $sql = "SELECT * FROM eszkozok ORDER BY nev ASC";
            $result = $conn->query($sql);
            
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    echo '<div class="col">';
                    echo '<div class="card h-100 eszköz-card" onclick="showQR(\'' . $row['id'] . '\', \'' . $row['nev'] . '\')">';
                    echo '<div class="card-body">';
                    echo '<h5 class="card-title">' . $row['nev'] . '</h5>';
                    echo '<p class="card-text">Azonosító: ' . $row['id'] . '</p>';
                    echo '<p class="card-text">Típus: ' . $row['tipus'] . '</p>';
                    echo '</div>';
                    echo '</div>';
                    echo '</div>';
                }
            } else {
                echo '<div class="col-12 text-center"><p>Nem található eszköz az adatbázisban.</p></div>';
            }
            ?>
        </div>
    </div>

    <div class="qr-container" id="qrModal">
        <div class="qr-content">
            <h3 id="qrTitle"></h3>
            <div id="qrcode"></div>
            <button class="btn btn-primary print-button" onclick="printQR()">
                <i class="fas fa-print"></i> QR kód nyomtatása
            </button>
            <button class="btn btn-secondary ms-2" onclick="closeQR()">Bezárás</button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/your-fontawesome-kit.js"></script>
    <script>
        let qrcode = null;

        function showQR(id, name) {
            const modal = document.getElementById('qrModal');
            const title = document.getElementById('qrTitle');
            const qrContainer = document.getElementById('qrcode');
            
            title.textContent = name;
            qrContainer.innerHTML = '';
            
            modal.style.display = 'flex';
            
            qrcode = new QRCode(qrContainer, {
                text: id,
                width: 256,
                height: 256
            });
        }

        function closeQR() {
            document.getElementById('qrModal').style.display = 'none';
        }

        function printQR() {
            const printWindow = window.open('', '', 'width=600,height=600');
            printWindow.document.write('<html><head><title>QR Kód Nyomtatás</title>');
            printWindow.document.write('</head><body>');
            printWindow.document.write('<div style="text-align:center;">');
            printWindow.document.write('<h2>' + document.getElementById('qrTitle').textContent + '</h2>');
            printWindow.document.write(document.getElementById('qrcode').innerHTML);
            printWindow.document.write('</div></body></html>');
            printWindow.document.close();
            printWindow.print();
        }

        const searchInput = document.getElementById('searchInput');
        searchInput.addEventListener('input', function() {
            const searchText = this.value.toLowerCase();
            const cards = document.querySelectorAll('.eszköz-card');
            
            cards.forEach(card => {
                const cardText = card.textContent.toLowerCase();
                const cardContainer = card.parentElement;
                if (cardText.includes(searchText)) {
                    cardContainer.style.display = '';
                } else {
                    cardContainer.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html> 