<?php
// services/EmailService.php

require_once '../../config/database.php'; // Pour avoir accès à $pdo
require_once '../../vendor/autoload.php'; // Pour PHPMailer
require_once 'Mailer.php'; // Votre classe Mailer existante

class EmailService {
    
    /**
     * Envoie un email pour une réservation
     * 
     * @param string $to Email du destinataire
     * @param string $subject Sujet de l'email
     * @param string $template Type de template ('created', 'confirmation', 'completed', 'cancelled')
     * @param array $data Données pour le template
     * @return bool Succès de l'envoi
     */
    public static function sendReservationEmail($to, $subject, $template, $data) {
        try {
            // Générer le contenu HTML
            $htmlBody = self::getTemplate($template, $data);
            
            // Utiliser votre classe Mailer existante
            $sent = Mailer::send($to, $subject, $htmlBody);
            
            // Logger l'envoi
            self::logEmail($data['reservation_id'] ?? 0, $template, $to, $sent ? 'sent' : 'failed');
            
            return $sent;
            
        } catch (Exception $e) {
            error_log("Erreur envoi email: " . $e->getMessage());
            self::logEmail($data['reservation_id'] ?? 0, $template, $to, 'failed', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Envoie une notification à l'admin pour nouvelle réservation
     * 
     * @param int $reservation_id ID de la réservation
     * @param PDO $pdo Connexion à la base de données
     */
    public static function sendNewReservationNotification($reservation_id, $pdo) {
        // Récupérer les détails de la réservation
        $stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ?");
        $stmt->execute([$reservation_id]);
        $reservation = $stmt->fetch();
        
        if (!$reservation) return;
        
        // Email de l'admin (à configurer selon votre système)
        $admin_email = self::getAdminEmail(); // Nouvelle méthode pour récupérer l'email admin
        
        $subject = "Nouvelle réservation #" . $reservation_id;
        $htmlBody = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd;'>
                <h2 style='color: #333;'>Nouvelle réservation à traiter</h2>
                <p>Une nouvelle réservation a été créée et nécessite votre attention.</p>
                <p><strong>Détails :</strong></p>
                <div style='background: #f9f9f9; padding: 15px; margin: 15px 0;'>
                    <p><strong>Numéro :</strong> #{$reservation_id}</p>
                    <p><strong>Client :</strong> {$reservation['customer_name']}</p>
                    <p><strong>Email :</strong> {$reservation['customer_email']}</p>
                    <p><strong>Téléphone :</strong> {$reservation['customer_phone']}</p>
                    <p><strong>Date :</strong> {$reservation['reservation_date']}</p>
                    <p><strong>Heure :</strong> {$reservation['reservation_time']}</p>
                    <p><strong>Personnes :</strong> {$reservation['party_size']}</p>
                    <p><strong>Demandes spéciales :</strong> " . ($reservation['special_requests'] ?: 'Aucune') . "</p>
                </div>
                <p>Connectez-vous à l'administration pour confirmer cette réservation.</p>
            </div>
        </body>
        </html>
        ";
        
        // Utiliser votre classe Mailer
        $sent = Mailer::send($admin_email, $subject, $htmlBody);
        
        // Logger la notification
        self::logEmail($reservation_id, 'admin_notification', $admin_email, $sent ? 'sent' : 'failed');
        
        return $sent;
    }
    
    /**
     * Récupère l'email de l'administrateur depuis la base de données
     */
    private static function getAdminEmail() {
        global $pdo;
        
        try {
            // Chercher un utilisateur avec rôle admin
            $stmt = $pdo->prepare("SELECT email FROM users WHERE role = 'admin' AND is_active = 1 LIMIT 1");
            $stmt->execute();
            $admin = $stmt->fetch();
            
            return $admin ? $admin['email'] : 'admin@legourmet.com'; // Fallback
        } catch (Exception $e) {
            return 'admin@legourmet.com'; // Email par défaut
        }
    }
    
    /**
     * Génère le contenu HTML de l'email selon le template
     */
    private static function getTemplate($template, $data) {
        // Configuration du restaurant (à personnaliser)
        $restaurant_name = "Le Gourmet";
        $restaurant_phone = "+243 97 000 0000";
        $restaurant_address = "123 Avenue du Restaurant, Kinshasa, RDC";
        $restaurant_email = "contact@legourmet.com";
        
        // Base du template HTML
        $baseTemplate = function($content, $titleColor = '#333') use ($restaurant_name, $restaurant_phone, $restaurant_address, $restaurant_email) {
            return "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Le Gourmet - Restaurant</title>
                <style>
                    body { font-family: 'Arial', sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f5f5f5; }
                    .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
                    .header { background-color: #d4a762; color: white; padding: 30px 20px; text-align: center; }
                    .header h1 { margin: 0; font-size: 28px; }
                    .content { padding: 30px 20px; }
                    .reservation-details { background-color: #f9f9f9; padding: 20px; border-left: 4px solid #d4a762; margin: 20px 0; }
                    .footer { background-color: #333; color: white; padding: 20px; text-align: center; font-size: 14px; }
                    .button { display: inline-block; background-color: #d4a762; color: white; padding: 12px 25px; text-decoration: none; border-radius: 4px; font-weight: bold; margin: 15px 0; }
                    .highlight { color: #d4a762; font-weight: bold; }
                    .thank-you { font-style: italic; background-color: #f8f9fa; padding: 20px; border-left: 4px solid #3498db; margin: 25px 0; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Le Gourmet</h1>
                        <p>Restaurant &amp; Culinaire d'exception</p>
                    </div>
                    <div class='content'>
                        {$content}
                    </div>
                    <div class='footer'>
                        <p><strong>{$restaurant_name}</strong><br>
                        {$restaurant_address}<br>
                        Téléphone: {$restaurant_phone}<br>
                        Email: {$restaurant_email}</p>
                        <p>© " . date('Y') . " Le Gourmet. Tous droits réservés.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
        };
        
        switch($template) {
            case 'created':
                $content = "
                    <h2 style='color: #d4a762;'>Réservation créée</h2>
                    <p>Bonjour <span class='highlight'>{$data['customer_name']}</span>,</p>
                    <p>Votre réservation a été créée avec succès. Elle est actuellement <strong>en attente de confirmation</strong>.</p>
                    
                    <div class='reservation-details'>
                        <h3 style='margin-top: 0;'>Détails de votre réservation :</h3>
                        <p><strong>Numéro de réservation :</strong> #{$data['reservation_id']}</p>
                        <p><strong>Date :</strong> {$data['reservation_date']}</p>
                        <p><strong>Heure :</strong> {$data['reservation_time']}</p>
                        <p><strong>Nombre de personnes :</strong> {$data['party_size']}</p>
                        <p><strong>Table :</strong> {$data['table_number']}</p>
                    </div>
                    
                    <p><strong>Prochaine étape :</strong> Vous recevrez un email de confirmation une fois que notre équipe aura validé votre réservation.</p>
                    <p>Si vous avez des questions, n'hésitez pas à nous contacter.</p>
                ";
                return $baseTemplate($content, '#d4a762');
                
            case 'confirmation':
                $content = "
                    <h2 style='color: #2ecc71;'>🎉 Réservation confirmée !</h2>
                    <p>Bonjour <span class='highlight'>{$data['customer_name']}</span>,</p>
                    <p>Nous avons le plaisir de vous confirmer votre réservation.</p>
                    
                    <div style='background-color: #e8f5e9; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                        <p style='font-size: 18px; font-weight: bold; color: #2e7d32;'>
                            ✅ Votre commande est en cours de préparation.
                        </p>
                    </div>
                    
                    <div class='reservation-details'>
                        <h3 style='margin-top: 0;'>Résumé de votre réservation :</h3>
                        <p><strong>Numéro :</strong> #{$data['reservation_id']}</p>
                        <p><strong>Date :</strong> {$data['reservation_date']}</p>
                        <p><strong>Heure :</strong> {$data['reservation_time']}</p>
                        <p><strong>Nombre de personnes :</strong> {$data['party_size']}</p>
                        <p><strong>Table attribuée :</strong> {$data['table_number']}</p>
                    </div>
                    
                    <h3>Informations importantes :</h3>
                    <ul>
                        <li>Nous vous recommandons d'arriver 5 à 10 minutes avant l'heure prévue.</li>
                        <li>Pour toute modification, veuillez nous contacter au moins 24h à l'avance.</li>
                        <li>En cas de retard, merci de nous prévenir par téléphone.</li>
                    </ul>
                    
                    <p>Nous avons hâte de vous accueillir !</p>
                ";
                return $baseTemplate($content, '#2ecc71');
                
            case 'completed':
                $content = "
                    <h2 style='color: #3498db;'>Merci pour votre visite !</h2>
                    <p>Bonjour <span class='highlight'>{$data['customer_name']}</span>,</p>
                    <p>Votre réservation n°<strong>#{$data['reservation_id']}</strong> a été marquée comme terminée.</p>
                    
                    <div class='thank-you'>
                        <p style='font-size: 16px; line-height: 1.8;'>
                            Nous tenons à vous remercier chaleureusement pour la confiance que vous nous avez accordée.
                            Votre satisfaction est notre priorité et nous espérons vous revoir très bientôt dans notre établissement.
                        </p>
                    </div>
                    
                    <p>Nous espérons que votre expérience chez <strong>Le Gourmet</strong> a été à la hauteur de vos attentes.</p>
                    
                    <p><strong>Votre avis compte pour nous !</strong><br>
                    N'hésitez pas à nous laisser un avis sur :</p>
                    <ul>
                        <li>Notre page Google</li>
                        <li>TripAdvisor</li>
                        <li>Notre page Facebook</li>
                    </ul>
                    
                    <p>À très bientôt pour de nouvelles découvertes culinaires !</p>
                    
                    <p style='margin-top: 30px;'><strong>Cordialement,</strong><br>
                    L'équipe du restaurant Le Gourmet</p>
                ";
                return $baseTemplate($content, '#3498db');
                
            case 'cancelled':
                $content = "
                    <h2 style='color: #e74c3c;'>Réservation annulée</h2>
                    <p>Bonjour <span class='highlight'>{$data['customer_name']}</span>,</p>
                    <p>Votre réservation n°<strong>#{$data['reservation_id']}</strong> a été annulée.</p>
                    
                    <div class='reservation-details'>
                        <h3 style='margin-top: 0;'>Détails annulés :</h3>
                        <p><strong>Date :</strong> {$data['reservation_date']}</p>
                        <p><strong>Heure :</strong> {$data['reservation_time']}</p>
                        <p><strong>Nombre de personnes :</strong> {$data['party_size']}</p>
                    </div>
                    
                    <div style='background-color: #ffebee; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                        <p><strong>Important :</strong> Si cette annulation n'a pas été effectuée par vous ou en cas d'erreur, 
                        veuillez nous contacter rapidement au <span class='highlight'>{$restaurant_phone}</span>.</p>
                    </div>
                    
                    <p>Nous espérons vous accueillir prochainement dans notre établissement.</p>
                    
                    <p>Pour réserver à nouveau, vous pouvez :</p>
                    <ul>
                        <li>Visiter notre site web</li>
                        <li>Nous appeler directement</li>
                        <li>Passer en personne à notre restaurant</li>
                    </ul>
                ";
                return $baseTemplate($content, '#e74c3c');
                
            case 'cancelled_admin':
                $content = "
                    <h2 style='color: #e74c3c;'>Réservation annulée par l'administration</h2>
                    <p>Bonjour <span class='highlight'>{$data['customer_name']}</span>,</p>
                    <p>Votre réservation n°<strong>#{$data['reservation_id']}</strong> a été annulée par notre équipe.</p>
                    
                    <div style='background-color: #ffebee; padding: 20px; border-radius: 5px; margin: 20px 0;'>
                        <p>Pour plus d'informations concernant cette annulation, 
                        veuillez nous contacter au <span class='highlight'>{$restaurant_phone}</span> ou par email à <span class='highlight'>{$restaurant_email}</span>.</p>
                    </div>
                    
                    <p>Nous nous excusons sincèrement pour la gêne occasionnée et espérons pouvoir vous accueillir 
                    dans de meilleures conditions prochainement.</p>
                    
                    <p>Pour toute nouvelle réservation, notre équipe reste à votre disposition.</p>
                ";
                return $baseTemplate($content, '#e74c3c');
                
            default:
                return "";
        }
    }
    
    /**
     * Logge l'envoi d'email dans la base de données
     * 
     * @param int $reservation_id
     * @param string $email_type
     * @param string $sent_to
     * @param string $status
     * @param string|null $error_message
     */
    private static function logEmail($reservation_id, $email_type, $sent_to, $status, $error_message = null) {
        global $pdo;
        
        try {
            if (!$pdo) {
                error_log("Erreur: \$pdo n'est pas défini pour le log email");
                return;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO reservation_emails_log 
                (reservation_id, email_type, sent_to, status, error_message, sent_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$reservation_id, $email_type, $sent_to, $status, $error_message]);
            
        } catch (Exception $e) {
            error_log("Erreur log email: " . $e->getMessage());
        }
    }
    
    /**
     * Envoie un email de test
     * 
     * @param string $to Email du destinataire
     * @return bool Succès de l'envoi
     */
    public static function sendTestEmail($to) {
        $subject = "Test d'envoi d'email - Le Gourmet";
        $htmlBody = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2>Test d'envoi d'email</h2>
            <p>Cet email a été envoyé pour tester la configuration SMTP du système de réservation.</p>
            <p>Date: " . date('d/m/Y H:i:s') . "</p>
            <p>Si vous recevez cet email, la configuration est correcte.</p>
        </body>
        </html>
        ";
        
        return Mailer::send($to, $subject, $htmlBody);
    }
}
?>