<?php
// Charger l'autoload de Composer au lieu des includes manuels
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    // Configuration SMTP (à adapter en PROD)
    private static $SMTP_HOST = 'smtp.gmail.com'; // Remplacer par votre hôte SMTP
    private static $SMTP_USER = 'martinshabani7@gmail.com'; // Remplacer par votre utilisateur SMTP
    private static $SMTP_PASS = 'oplx hrhb rdda wpob'; // Remplacer par votre mot de passe SMTP
    private static $SMTP_PORT = 587;
    private static $MAIL_FROM = 'martinshabani7@gmail.com';
    private static $MAIL_FROM_NAME = 'Le Gourmet';
    public static $MAIL_DEV_MODE = false; // Passer à false en PROD

    // Méthode existante pour l'envoi simple
    public static function send($to, $subject, $htmlBody) {
        return self::sendAdvanced($to, $subject, $htmlBody);
    }

    // NOUVELLE MÉTHODE : Envoi avancé avec options supplémentaires
    public static function sendAdvanced($to, $subject, $htmlBody, $options = []) {
        if (self::$MAIL_DEV_MODE) {
            // Mode DEV: Log dans un fichier
            $dir = dirname(__DIR__) . '/logs';
            if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
            $log = $dir . '/mail.log';
            $entry = "==== \nDate: " . date('c') . "\nTo: $to\nSubject: $subject\nOptions: " . json_encode($options) . "\n\n$htmlBody\n\n";
            @file_put_contents($log, $entry, FILE_APPEND);
            return true;
        }

        $mail = new PHPMailer(true);
        try {
            // Configuration du serveur
            $mail->isSMTP();
            $mail->Host       = self::$SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = self::$SMTP_USER;
            $mail->Password   = self::$SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = self::$SMTP_PORT;
            $mail->CharSet    = 'UTF-8';

            // Destinataires
            $fromEmail = $options['from_email'] ?? self::$MAIL_FROM;
            $fromName = $options['from_name'] ?? self::$MAIL_FROM_NAME;
            $mail->setFrom($fromEmail, $fromName);
            
            // Gestion des destinataires multiples
            if (is_array($to)) {
                foreach ($to as $recipient) {
                    $mail->addAddress($recipient);
                }
            } else {
                $mail->addAddress($to);
            }

            // Ajout des copies
            if (!empty($options['cc'])) {
                if (is_array($options['cc'])) {
                    foreach ($options['cc'] as $cc) {
                        $mail->addCC($cc);
                    }
                } else {
                    $mail->addCC($options['cc']);
                }
            }

            // Ajout des copies cachées
            if (!empty($options['bcc'])) {
                if (is_array($options['bcc'])) {
                    foreach ($options['bcc'] as $bcc) {
                        $mail->addBCC($bcc);
                    }
                } else {
                    $mail->addBCC($options['bcc']);
                }
            }

            // Adresse de réponse
            if (!empty($options['reply_to'])) {
                $replyName = $options['reply_name'] ?? '';
                $mail->addReplyTo($options['reply_to'], $replyName);
            }

            // Contenu
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = strip_tags($htmlBody);

            // Pièces jointes
            if (!empty($options['attachments']) && is_array($options['attachments'])) {
                foreach ($options['attachments'] as $attachment) {
                    if (isset($attachment['path']) && file_exists($attachment['path'])) {
                        $name = $attachment['name'] ?? basename($attachment['path']);
                        $mail->addAttachment($attachment['path'], $name);
                    }
                }
            }

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Erreur d'envoi d'email à $to: {$mail->ErrorInfo}");
            return false;
        }
    }

    // NOUVELLE MÉTHODE : Spécifique pour les contacts
    public static function sendContact($senderName, $senderEmail, $message, $adminEmail = null) {
        // Email à l'administrateur
        $adminEmail = $adminEmail ?? self::$MAIL_FROM;
        $adminSubject = "Nouveau message de $senderName - Le Gourmet";
        $adminHtml = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
                .content { background-color: white; padding: 20px; border: 1px solid #dee2e6; border-radius: 5px; }
                .info-item { margin-bottom: 10px; }
                .message { background-color: #f8f9fa; padding: 15px; border-left: 4px solid #0d6efd; margin: 20px 0; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; font-size: 12px; color: #6c757d; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Nouveau message depuis le site Le Gourmet</h2>
                </div>
                <div class='content'>
                    <div class='info-item'><strong>Nom:</strong> $senderName</div>
                    <div class='info-item'><strong>Email:</strong> $senderEmail</div>
                    <div class='info-item'><strong>Date:</strong> " . date('d/m/Y H:i:s') . "</div>
                    <div class='info-item'><strong>IP:</strong> " . ($_SERVER['REMOTE_ADDR'] ?? 'N/A') . "</div>
                    
                    <div class='message'>
                        <strong>Message:</strong><br>
                        " . nl2br(htmlspecialchars($message)) . "
                    </div>
                </div>
                <div class='footer'>
                    Cet email a été envoyé automatiquement depuis le formulaire de contact du site Le Gourmet.<br>
                    Ne pas répondre directement à cet email.
                </div>
            </div>
        </body>
        </html>
        ";

        // Email de confirmation à l'utilisateur
        $userSubject = "Confirmation de votre message - Le Gourmet";
        $userHtml = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
                .content { background-color: white; padding: 20px; border: 1px solid #dee2e6; border-radius: 5px; }
                .thank-you { color: #198754; font-weight: bold; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; font-size: 12px; color: #6c757d; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Merci pour votre message, $senderName !</h2>
                </div>
                <div class='content'>
                    <p class='thank-you'>✔ Votre message a bien été envoyé à notre équipe.</p>
                    <p>Nous vous répondrons dans les plus brefs délais.</p>
                    <p><strong>Récapitulatif de votre message :</strong></p>
                    <div style='background-color: #f8f9fa; padding: 15px; border-left: 4px solid #0d6efd; margin: 20px 0;'>
                        " . nl2br(htmlspecialchars($message)) . "
                    </div>
                    <p><em>Date d'envoi : " . date('d/m/Y à H:i:s') . "</em></p>
                </div>
                <div class='footer'>
                    Cet email est un accusé de réception automatique.<br>
                    Restaurant Le Gourmet - C/Les Volcans, 75001 Goma<br>
                    Tél: +243 973 900 115 - Email: contact@legourmet.fr
                </div>
            </div>
        </body>
        </html>
        ";

        // Envoi à l'administrateur
        $adminSuccess = self::sendAdvanced($adminEmail, $adminSubject, $adminHtml, [
            'from_email' => 'contact@legourmet.fr',
            'from_name' => 'Restaurant Le Gourmet',
            'reply_to' => $senderEmail,
            'reply_name' => $senderName
        ]);

        // Envoi de confirmation à l'utilisateur
        $userSuccess = self::sendAdvanced($senderEmail, $userSubject, $userHtml, [
            'from_email' => 'contact@legourmet.fr',
            'from_name' => 'Restaurant Le Gourmet',
            'reply_to' => 'contact@legourmet.fr',
            'reply_name' => 'Le Gourmet'
        ]);

        return $adminSuccess && $userSuccess;
    }
}
?>