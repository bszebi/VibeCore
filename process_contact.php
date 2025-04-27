<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $subject = $_POST['subject'];
    $message = $_POST['message'];

    try {
        $mail = new PHPMailer(true);

        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'kurinczjozsef@gmail.com';
        $mail->Password = 'qtmayweajrtybnck';
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;
        $mail->CharSet = 'UTF-8';

        // Recipients
        $mail->setFrom($email, $name);
        $mail->addAddress('kurinczjozsef@gmail.com');

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Új kapcsolatfelvételi üzenet: " . $subject;
        
        // Email body
        $mail->Body = "
            <html>
            <head>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        line-height: 1.6;
                        color: #333;
                    }
                    .container {
                        max-width: 600px;
                        margin: 0 auto;
                        padding: 20px;
                    }
                    .header {
                        background: #3498db;
                        color: white;
                        padding: 20px;
                        border-radius: 5px 5px 0 0;
                        margin-bottom: 20px;
                    }
                    .content {
                        background: #f9f9f9;
                        padding: 20px;
                        border-radius: 0 0 5px 5px;
                    }
                    .field {
                        margin-bottom: 15px;
                    }
                    .label {
                        font-weight: bold;
                        color: #2c3e50;
                    }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Új kapcsolatfelvételi üzenet</h2>
                    </div>
                    <div class='content'>
                        <div class='field'>
                            <span class='label'>Név:</span> {$name}
                        </div>
                        <div class='field'>
                            <span class='label'>Email:</span> {$email}
                        </div>
                        <div class='field'>
                            <span class='label'>Tárgy:</span> {$subject}
                        </div>
                        <div class='field'>
                            <span class='label'>Üzenet:</span><br>
                            " . nl2br(htmlspecialchars($message)) . "
                        </div>
                    </div>
                </div>
            </body>
            </html>
        ";

        $mail->send();
        
        // Return success response
        echo json_encode(['success' => true, 'message' => 'Az üzenet sikeresen elküldve!']);
    } catch (Exception $e) {
        // Return error response
        echo json_encode(['success' => false, 'message' => 'Hiba történt az üzenet küldése során. Kérjük próbálja újra később.']);
    }
} else {
    // If not POST request, redirect to contact page
    header('Location: kapcsolat.php');
    exit();
} 