<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Si vous utilisez Composer, utilisez l'autoload
require_once '../vendor/autoload.php';

class Mailer {
    // Configuration SMTP (à adapter en PROD)
    private static $SMTP_HOST = 'smtp.gmail.com';
    private static $SMTP_USER = 'martinshabani7@gmail.com';
    private static $SMTP_PASS = 'oplx hrhb rdda wpob';
    private static $SMTP_PORT = 587;
    private static $MAIL_FROM = 'martinshabani7@gmail.com';
    private static $MAIL_FROM_NAME = 'Le Gourmet';
    private static $MAIL_DEV_MODE = false; // Passer à false en PROD

    public static function send($to, $subject, $htmlBody) {
        if (self::$MAIL_DEV_MODE) {
            // Mode DEV: Log dans un fichier
            $dir = dirname(__DIR__) . '/logs';
            if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
            $log = $dir . '/mail.log';
            $entry = "==== " . date('Y-m-d H:i:s') . " ====\n";
            $entry .= "To: $to\n";
            $entry .= "Subject: $subject\n";
            $entry .= "Body: " . substr(strip_tags($htmlBody), 0, 200) . "...\n\n";
            @file_put_contents($log, $entry, FILE_APPEND);
            
            // Afficher dans les logs PHP
            error_log("Email DEV mode - To: $to, Subject: $subject");
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
            
            // Timeout augmenté pour éviter les erreurs
            $mail->Timeout    = 30;
            $mail->SMTPDebug  = 0; // Mettre à 2 pour le débogage

            // Destinataires
            $mail->setFrom(self::$MAIL_FROM, self::$MAIL_FROM_NAME);
            $mail->addAddress($to);
            $mail->addReplyTo('contact@legourmet.com', 'Contact Le Gourmet');

            // Contenu
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = strip_tags($htmlBody);

            $sent = $mail->send();
            
            if (!$sent) {
                error_log("Erreur PHPMailer pour $to: {$mail->ErrorInfo}");
            }
            
            return $sent;
            
        } catch (Exception $e) {
            error_log("Exception PHPMailer pour $to: {$mail->ErrorInfo}");
            return false;
        }
    }
    
    /**
     * Test la connexion SMTP
     */
    public static function testSMTP() {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = self::$SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = self::$SMTP_USER;
            $mail->Password   = self::$SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = self::$SMTP_PORT;
            $mail->Timeout    = 10;
            
            return $mail->smtpConnect();
        } catch (Exception $e) {
            error_log("Test SMTP échoué: " . $e->getMessage());
            return false;
        }
    }
}
?>