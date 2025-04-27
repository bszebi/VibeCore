<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';

if(isset($_POST['send'])){
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';//a mail server címe, ehhez ne nyúlj
    $mail->SMTPAuth = true;
    $mail->Username = 'kurinczjozsef@gmail.com';//a te email címed
    $mail->Password = 'qtmayweajrtybnck';//a te jelszavad 
    $mail->SMTPSecure = 'ssl';
    $mail->Port = 465;

    $mail->setFrom('kurinczjozsef@gmail.com');//a te email címed
    $mail->addAddress($_POST['email']);

    $mail->isHTML(true);

    $mail->Subject = $_POST['subject'];
    $mail->Body = $_POST['message'];

    $mail->send();

    echo "
    <script>
        alert('Email sent successfully');
        window.location.href = 'index.php';
    </script>
    ";
}
