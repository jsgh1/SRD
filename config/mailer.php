<?php

require_once __DIR__ . '/email_config.php';

require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Envía el código de acceso (login) al sistema
 */
function enviarCodigoLogin($correo, $codigo) {
    $mail = new PHPMailer(true);

    try {

        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($correo);

        $mail->isHTML(true);
        $mail->Subject = 'Codigo de acceso al sistema';

        $mail->Body = '
<table width="100%" cellpadding="0" cellspacing="0" style="font-family: Arial, sans-serif; background:#f2f4f8; padding:40px 0; margin:0;">
    <tr>
        <td align="center">
            <table width="520" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:14px; overflow:hidden; box-shadow:0 6px 18px rgba(0,0,0,0.08);">
                
                <!-- Header -->
                <tr>
                    <td align="center" style="background:linear-gradient(135deg,#4e73df,#224abe); padding:20px 30px;">
                        <div style="font-size:11px; letter-spacing:2px; text-transform:uppercase; color:#cbd4ff; margin-bottom:4px;">
                            Seguridad de acceso
                        </div>
                        <div style="font-size:22px; font-weight:700; color:#ffffff; margin:0;">
                            Código de verificación
                        </div>
                    </td>
                </tr>

                <!-- Nombre Empresa -->
                <tr>
                    <td align="center" style="padding:18px 30px 8px 30px;">
                        <div style="display:inline-block; padding:8px 14px; border-radius:999px; border:1px solid #e1e5f2; font-size:12px; color:#6c757d; text-transform:uppercase; letter-spacing:1.5px;">
                            <b>SRD</b> - Sistema de Registro
                        </div>
                    </td>
                </tr>

                <!-- Contenido principal -->
                <tr>
                    <td style="padding:0 30px 24px 30px; color:#343a40; font-size:15px; line-height:1.7;">
                        <p style="margin:12px 0 6px 0;">Hola,</p>
                        <p style="margin:0 0 16px 0;">
                            Para completar tu inicio de sesión, utiliza el siguiente código de verificación:
                        </p>

                        <!-- Código -->
                        <div style="text-align:center; margin:22px 0 18px 0;">
                            <span style="
                                display:inline-block;
                                background:#4e73df;
                                color:#ffffff;
                                padding:14px 26px;
                                font-size:28px;
                                letter-spacing:4px;
                                border-radius:10px;
                                font-weight:bold;
                                box-shadow:0 4px 10px rgba(78,115,223,0.45);
                            ">
                                '.$codigo.'
                            </span>
                        </div>

                        <p style="margin:0 0 10px 0; text-align:center; font-size:14px; color:#6c757d;">
                            Este código vence en <strong>10 minutos</strong>.
                        </p>
                        <p style="margin:6px 0 0 0; font-size:13px; color:#6c757d; text-align:center;">
                            Por tu seguridad, no compartas este código con nadie.
                        </p>
                    </td>
                </tr>

                <!-- Línea divisoria -->
                <tr>
                    <td style="padding:0 30px;">
                        <hr style="border:none; border-top:1px solid #eceff4; margin:0;">
                    </td>
                </tr>

                <!-- Mensaje secundario -->
                <tr>
                    <td style="padding:16px 30px 10px 30px; font-size:12px; color:#8c98a4; line-height:1.6;">
                        <p style="margin:0 0 6px 0;">
                            Si tú no solicitaste este código, es posible que alguien haya intentado acceder a tu cuenta.
                        </p>
                        <p style="margin:0;">
                            Te recomendamos revisar tu actividad reciente y, si es necesario, cambiar tu contraseña.
                        </p>
                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td align="center" style="padding:18px 20px 20px 20px; background:#f8f9fc; font-size:11px; color:#a0a8b8;">
                        Este es un mensaje automático, por favor no respondas a este correo.<br>
                        &copy; '.date("Y").' <b>SRD</b>. Todos los derechos reservados.
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
';

        $mail->AltBody = "Hola,\n\nTu código de acceso es: {$codigo}\n\nEste código vence en 10 minutos.\n\nNo lo compartas con nadie.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Envía el código de verificación para cambio de correo
 */
function enviarCodigoCambioEmail($correo, $codigo) {
    $mail = new PHPMailer(true);

    try {

        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($correo);

        $mail->isHTML(true);
        $mail->Subject = 'Codigo de verificacion para cambio de correo';

        $mail->Body = '
<table width="100%" cellpadding="0" cellspacing="0" style="font-family: Arial, sans-serif; background:#f2f4f8; padding:40px 0; margin:0;">
    <tr>
        <td align="center">
            <table width="520" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:14px; overflow:hidden; box-shadow:0 6px 18px rgba(0,0,0,0.08);">
                
                <!-- Header -->
                <tr>
                    <td align="center" style="background:linear-gradient(135deg,#1cc88a,#13855c); padding:20px 30px;">
                        <div style="font-size:11px; letter-spacing:2px; text-transform:uppercase; color:#c9ffe4; margin-bottom:4px;">
                            Seguridad de cuenta
                        </div>
                        <div style="font-size:22px; font-weight:700; color:#ffffff; margin:0;">
                            Verifica tu nuevo correo
                        </div>
                    </td>
                </tr>

                <!-- Nombre Empresa -->
                <tr>
                    <td align="center" style="padding:18px 30px 8px 30px;">
                        <div style="display:inline-block; padding:8px 14px; border-radius:999px; border:1px solid #e1e5f2; font-size:12px; color:#6c757d; text-transform:uppercase; letter-spacing:1.5px;">
                            <b>SRD</b> - Sistema de Registro
                        </div>
                    </td>
                </tr>

                <!-- Contenido principal -->
                <tr>
                    <td style="padding:0 30px 24px 30px; color:#343a40; font-size:15px; line-height:1.7;">
                        <p style="margin:12px 0 6px 0;">Hola,</p>
                        <p style="margin:0 0 16px 0;">
                            Has solicitado cambiar el correo de acceso al Sistema de Registro.
                            Para confirmar esta acción, utiliza el siguiente código de verificación:
                        </p>

                        <!-- Código -->
                        <div style="text-align:center; margin:22px 0 18px 0;">
                            <span style="
                                display:inline-block;
                                background:#1cc88a;
                                color:#ffffff;
                                padding:14px 26px;
                                font-size:28px;
                                letter-spacing:4px;
                                border-radius:10px;
                                font-weight:bold;
                                box-shadow:0 4px 10px rgba(28,200,138,0.45);
                            ">
                                '.$codigo.'
                            </span>
                        </div>

                        <p style="margin:0 0 10px 0; text-align:center; font-size:14px; color:#6c757d;">
                            Este código vence en <strong>10 minutos</strong>.
                        </p>
                        <p style="margin:6px 0 0 0; font-size:13px; color:#6c757d; text-align:center;">
                            Si no solicitaste este cambio, simplemente ignora este mensaje.
                        </p>
                    </td>
                </tr>

                <!-- Línea divisoria -->
                <tr>
                    <td style="padding:0 30px;">
                        <hr style="border:none; border-top:1px solid #eceff4; margin:0;">
                    </td>
                </tr>

                <!-- Mensaje secundario -->
                <tr>
                    <td style="padding:16px 30px 10px 30px; font-size:12px; color:#8c98a4; line-height:1.6;">
                        <p style="margin:0 0 6px 0;">
                            Por seguridad, solo se actualizará tu correo si ingresas correctamente este código
                            dentro del tiempo de validez.
                        </p>
                        <p style="margin:0;">
                            Si ves actividad sospechosa, ponte en contacto con el administrador del sistema.
                        </p>
                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td align="center" style="padding:18px 20px 20px 20px; background:#f8f9fc; font-size:11px; color:#a0a8b8;">
                        Este es un mensaje automático, por favor no respondas a este correo.<br>
                        &copy; '.date("Y").' <b>SRD</b>. Todos los derechos reservados.
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
';

        $mail->AltBody = "Hola,\n\nHas solicitado cambiar el correo de acceso al Sistema de Registro.\n\n".
                         "Tu código de verificación es: {$codigo}\n\n".
                         "Este código vence en 10 minutos.\n\n".
                         "Si no solicitaste este cambio, ignora este mensaje.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
