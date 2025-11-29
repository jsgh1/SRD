<?php

require_once __DIR__ . '/email_config.php';

require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function enviarCodigoLogin($correo, $codigo) {
    $mail = new PHPMailer(true);

    try {
        // Configuración del servidor SMTP
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;

        // Si usas TLS (puerto 587)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        // Si tu proveedor usa SSL (puerto 465), sería:
        // $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        // $mail->Port       = 465;

        // Remitente
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

        // Destinatario
        $mail->addAddress($correo);

        // Contenido
        $mail->isHTML(true);
        $mail->Subject = 'Código de acceso al sistema';

        $mail->Body  = "
            <p>Hola,</p>
            <p>Tu código de acceso es: <strong>{$codigo}</strong></p>
            <p>Este código vence en 10 minutos.</p>
            <p>No lo compartas con nadie.</p>
        ";

        $mail->AltBody = "Hola,\n\nTu código de acceso es: {$codigo}\n\nEste código vence en 10 minutos.\n\nNo lo compartas con nadie.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Si quieres debug, puedes hacer un log:
        // error_log('Error al enviar correo: ' . $mail->ErrorInfo);
        return false;
    }
}
