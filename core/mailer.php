<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../config/services.php';

function enviarCorreo($destinatario, $asunto, $cuerpo) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = config_get('mail', 'host', 'smtp.gmail.com');
        $mail->SMTPAuth = true;
        $mail->Username = config_get('mail', 'username', '');
        $mail->Password = str_replace(' ', '', (string)config_get('mail', 'password', ''));
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int)config_get('mail', 'port', 587);

        $fromEmail = config_get('mail', 'from_email', $mail->Username);
        $fromName = config_get('mail', 'from_name', 'MOVEON');
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($destinatario);

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $asunto;
        $mail->Body = $cuerpo;
        $mail->AltBody = strip_tags($cuerpo);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Error al enviar correo: {$mail->ErrorInfo}");
        return false;
    }
}

