<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class MailService {

    public static function sendPasswordRecovery(string $toEmail, string $toName, string $newPassword): bool {
        $mail = new PHPMailer(true);

        try {
            // Configuración SMTP Gmail
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['MAIL_FROM']     ?? '';
            $mail->Password   = $_ENV['MAIL_PASSWORD'] ?? '';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            // Remitente y destinatario
            $mail->setFrom(
                $_ENV['MAIL_FROM'] ?? '',
                $_ENV['MAIL_NAME'] ?? 'LumbalgiaSG'
            );
            $mail->addAddress($toEmail, $toName);

            // Contenido
            $mail->isHTML(true);
            $mail->Subject = 'Recuperación de contraseña — LumbalgiaSG';
            $mail->Body    = self::buildTemplate($toName, $newPassword);
            $mail->AltBody = "Hola $toName, tu nueva contraseña es: $newPassword";

            $mail->send();
            return true;

        } catch (Exception $e) {
            error_log('MailService error: ' . $e->getMessage());
            return false;
        }
    }

    private static function buildTemplate(string $name, string $password): string {
        return "
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset='UTF-8'>
          <style>
            body { font-family: Arial, sans-serif; background: #f5f6fa; margin: 0; padding: 0; }
            .container { max-width: 520px; margin: 40px auto; background: #fff;
                         border-radius: 12px; overflow: hidden;
                         box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
            .header { background: #112240; padding: 32px; text-align: center; }
            .header h1 { color: #fff; font-size: 22px; margin: 0; letter-spacing: -0.02em; }
            .header p  { color: rgba(255,255,255,0.55); font-size: 13px; margin: 6px 0 0; }
            .body { padding: 32px; }
            .body p { color: #2d3142; font-size: 14px; line-height: 1.7; margin: 0 0 16px; }
            .password-box { background: #f0f6ff; border: 1.5px solid #dce8f8;
                            border-radius: 8px; padding: 16px; text-align: center;
                            margin: 20px 0; }
            .password-box .label { font-size: 11px; color: #7a7f96; text-transform: uppercase;
                                   letter-spacing: 0.1em; font-weight: 700; margin-bottom: 6px; }
            .password-box .pwd { font-size: 24px; font-weight: 800; color: #1e3a5f;
                                 letter-spacing: 0.1em; font-family: monospace; }
            .warning { background: #fdf3dc; border-left: 3px solid #8a5c00;
                       border-radius: 6px; padding: 12px 14px; font-size: 13px;
                       color: #8a5c00; margin-top: 16px; }
            .footer { background: #f5f6fa; padding: 20px 32px; text-align: center;
                      font-size: 12px; color: #a0a6b8; border-top: 1px solid #e8eaf2; }
          </style>
        </head>
        <body>
          <div class='container'>
            <div class='header'>
              <h1>LumbalgiaSG</h1>
              <p>Sistema gamificado de aprendizaje adaptativo</p>
            </div>
            <div class='body'>
              <p>Hola <strong>$name</strong>,</p>
              <p>Recibimos una solicitud de recuperación de contraseña para tu cuenta de administrador. Tu nueva contraseña temporal es:</p>
              <div class='password-box'>
                <div class='label'>Nueva contraseña</div>
                <div class='pwd'>$password</div>
              </div>
              <div class='warning'>
                Por seguridad, cambia esta contraseña tan pronto como inicies sesión.
              </div>
              <p style='margin-top:20px;font-size:13px;color:#7a7f96;'>
                Si no solicitaste este cambio, ignora este correo. Tu contraseña anterior sigue siendo válida.
              </p>
            </div>
            <div class='footer'>
              LumbalgiaSG &mdash; Universidad de Guayaquil &mdash; 2026
            </div>
          </div>
        </body>
        </html>";
    }
}