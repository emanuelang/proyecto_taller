<?php
// core/mailer.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

function enviarCorreo($destinatario, $asunto, $cuerpo) {
    $mail = new PHPMailer(true);

    try {
        // Configuración del servidor (Usamos GMAIL como default para testing/producción)
        // El usuario deberá reemplazar estas credenciales por las reales de su empresa en producción
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        // Credenciales SMTP recomendadas (App Passwords)
        $mail->Username   = 'taller.carpooling@gmail.com'; // EDITAR: Correo real
        $mail->Password   = 'contraseña_de_aplicacion';    // EDITAR: Contraseña de aplicación
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Remitente y destinatario
        $mail->setFrom('taller.carpooling@gmail.com', 'Carpooling App');
        $mail->addAddress($destinatario);

        // Contenido
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $asunto;
        $mail->Body    = $cuerpo;
        $mail->AltBody = strip_tags($cuerpo);

        $mail->send();
        return true;
    } catch (Exception $e) {
        // En un entorno de producción, registrar el error en log
        error_log("Error al enviar correo: {$mail->ErrorInfo}");
        return false;
    }
}
?>
