<?php
// ../admin/api/client_orders_cancel.php
session_start();
require_once '../../config/database.php';
require_once '../../config/security.php';

header('Content-Type: application/json');

// Vérifier la session
if (!Security::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Vérifier le token CSRF
if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF invalide']);
    exit();
}

// Vérifier la méthode
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

try {
    // Récupérer les données
    $order_id = (int)($_POST['order_id'] ?? 0);
    $reason = $_POST['cancellation_reason'] ?? '';
    $notes = $_POST['cancellation_notes'] ?? '';
    
    if ($order_id <= 0) {
        throw new Exception('ID de commande invalide');
    }
    
    if (empty($reason)) {
        throw new Exception('Veuillez spécifier une raison');
    }
    
    $customer_id = $_SESSION['user_id'];
    
    // Vérifier que la commande appartient bien au client
    $stmt = $pdo->prepare("
        SELECT 
            o.*,
            COUNT(oi.id) as items_count
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.id = ? AND o.customer_id = ?
        GROUP BY o.id
    ");
    
    $stmt->execute([$order_id, $customer_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Commande non trouvée ou accès refusé');
    }
    
    // Vérifier si la commande peut être annulée
    $cancellable_statuses = ['pending', 'confirmed'];
    if (!in_array($order['status'], $cancellable_statuses)) {
        $status_text = $order['status'] === 'preparing' ? 'en préparation' : 
                      ($order['status'] === 'ready' ? 'prête' : 
                      ($order['status'] === 'served' ? 'servie' : 
                      ($order['status'] === 'cancelled' ? 'déjà annulée' : $order['status'])));
        
        throw new Exception('Cette commande ne peut plus être annulée (statut: ' . $status_text . ')');
    }
    
    // Vérifier le délai de cancellation (par exemple, commande créée il y a moins de 30 minutes)
    $stmt = $pdo->prepare("
        SELECT TIMESTAMPDIFF(MINUTE, created_at, NOW()) as minutes_ago 
        FROM orders 
        WHERE id = ?
    ");
    $stmt->execute([$order_id]);
    $time_diff = $stmt->fetchColumn();
    
    if ($time_diff > 30) { // 30 minutes max pour annuler
        throw new Exception('Le délai d\'annulation est dépassé (30 minutes maximum)');
    }
    
    // Commencer la transaction
    $pdo->beginTransaction();
    
    try {
        // 1. Mettre à jour le statut de la commande
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET 
                status = 'cancelled', 
                updated_at = NOW(),
                notes = CONCAT(COALESCE(notes, ''), '\n\n--- ANNULATION ---\nRaison: ', ?, '\nDate: ', NOW(), '\n', ?)
            WHERE id = ?
        ");
        
        $cancellation_notes = $reason;
        if (!empty($notes)) {
            $cancellation_notes .= " - " . $notes;
        }
        
        $stmt->execute([$reason, $notes, $order_id]);
        
        // 2. Enregistrer dans l'historique des statuts
        try {
            $stmt = $pdo->prepare("
                INSERT INTO order_status_history (
                    order_id,
                    status,
                    notes,
                    changed_by,
                    created_at
                ) VALUES (?, ?, ?, ?, NOW())
            ");
            
            $history_notes = "Annulée par le client. Raison: " . $reason;
            if (!empty($notes)) {
                $history_notes .= " - " . $notes;
            }
            
            $stmt->execute([
                $order_id,
                'cancelled',
                $history_notes,
                'customer_' . $customer_id
            ]);
        } catch (Exception $e) {
            // La table order_status_history n'existe peut-être pas
            error_log("Note: order_status_history non disponible: " . $e->getMessage());
        }
        
        // 3. Libérer la table si commande sur place
        if ($order['order_type'] === 'dine_in' && !empty($order['table_number'])) {
            $stmt = $pdo->prepare("
                UPDATE tables 
                SET is_available = 1 
                WHERE table_name = ?
            ");
            $stmt->execute([$order['table_number']]);
            
            // Mettre à jour l'occupation de table si la table existe
            try {
                $stmt = $pdo->prepare("
                    UPDATE table_occupations 
                    SET end_time = NOW(),
                        notes = CONCAT(COALESCE(notes, ''), '\nAnnulée par le client: ', ?)
                    WHERE order_id = ? AND end_time IS NULL
                ");
                $stmt->execute([$cancellation_notes, $order_id]);
            } catch (Exception $e) {
                // La table table_occupations n'existe peut-être pas
                error_log("Note: table_occupations non disponible: " . $e->getMessage());
            }
        }
        
        // 4. Gérer le remboursement si paiement déjà effectué
        if ($order['payment_status'] === 'paid') {
            // Mettre à jour le statut de paiement
            $stmt = $pdo->prepare("
                UPDATE orders 
                SET payment_status = 'refunded',
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$order_id]);
            
            // Enregistrer le remboursement si la table existe
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO refunds (
                        order_id,
                        amount,
                        reason,
                        status,
                        created_at
                    ) VALUES (?, ?, ?, 'pending', NOW())
                ");
                
                $stmt->execute([
                    $order_id,
                    $order['total_amount'],
                    $cancellation_notes
                ]);
            } catch (Exception $e) {
                // La table refunds n'existe peut-être pas
                error_log("Note: refunds non disponible: " . $e->getMessage());
            }
            
            // Notifier du remboursement
            sendRefundNotification($customer_id, $order['order_number'], $order['total_amount']);
        }
        
        // 5. Restaurer le stock si nécessaire (si vous gérez les stocks)
        // Décommentez cette section si vous avez une gestion de stock
        /*
        $stmt = $pdo->prepare("
            SELECT product_id, quantity 
            FROM order_items 
            WHERE order_id = ?
        ");
        $stmt->execute([$order_id]);
        $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($order_items as $item) {
            $stmt = $pdo->prepare("
                UPDATE products 
                SET stock = stock + ? 
                WHERE id = ?
            ");
            $stmt->execute([$item['quantity'], $item['product_id']]);
        }
        */
        
        // Valider la transaction
        $pdo->commit();
        
        // 6. Envoyer une notification de confirmation (optionnel)
        sendCancellationEmail($customer_id, $order['order_number'], $reason, $order['total_amount']);
        
        // 7. Notifier l'administration (optionnel)
        notifyAdminCancellation($order_id, $order['order_number'], $customer_id, $reason);
        
        echo json_encode([
            'success' => true,
            'message' => 'Commande annulée avec succès',
            'order_number' => $order['order_number'],
            'refund_status' => ($order['payment_status'] === 'paid') ? 'en attente' : 'non applicable'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Erreur PDO: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données'
    ]);
    
} catch (Exception $e) {
    error_log("Erreur: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Envoyer un email de notification d'annulation
 */
function sendCancellationEmail($customer_id, $order_number, $reason, $total_amount = null) {
    // Récupérer les informations du client
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT email, CONCAT(first_name, ' ', last_name) as name 
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$customer_id]);
        $customer = $stmt->fetch();
        
        if (!$customer) return;
        
        $email = $customer['email'];
        $name = $customer['name'];
        
        // Log pour débogage
        $log_message = "Email d'annulation à envoyer pour la commande $order_number\n";
        $log_message .= "À: $name <$email>\n";
        $log_message .= "Raison: $reason\n";
        if ($total_amount) {
            $log_message .= "Montant: " . number_format($total_amount, 2, ',', ' ') . " €\n";
        }
        
        error_log($log_message);
        
        // Envoi d'email réel
        $subject = "Annulation de votre commande #$order_number - Le Gourmet";
        $amount_text = $total_amount ? 
            "<p><strong>Montant de la commande:</strong> " . number_format($total_amount, 2, ',', ' ') . " $</p>" : 
            "";
        
        $refund_text = $total_amount ? 
            "<p>Un remboursement sera traité dans les plus brefs délais.</p>" : 
            "";
        
        $message = "
        <html>
        <head>
            <title>Annulation de commande</title>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #f8f9fa; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .alert { background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .footer { background-color: #f8f9fa; padding: 10px; text-align: center; font-size: 12px; color: #6c757d; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Le Gourmet</h1>
                    <h2>Annulation de commande</h2>
                </div>
                <div class='content'>
                    <p>Bonjour $name,</p>
                    <div class='alert'>
                        <p>Votre commande <strong>#$order_number</strong> a été annulée.</p>
                    </div>
                    <p><strong>Raison indiquée:</strong> $reason</p>
                    $amount_text
                    $refund_text
                    <p>Si vous avez des questions, n'hésitez pas à nous contacter.</p>
                    <p>À bientôt,</p>
                    <p><em>L'équipe Le Gourmet</em></p>
                </div>
                <div class='footer'>
                    <p>Ceci est un email automatique, merci de ne pas y répondre.</p>
                    <p>© " . date('Y') . " Le Gourmet - Tous droits réservés</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Le Gourmet <noreply@legourmet.com>" . "\r\n";
        
        // mail($email, $subject, $message, $headers);
        
        return true;
    } catch (Exception $e) {
        error_log("Erreur envoi email d'annulation: " . $e->getMessage());
        return false;
    }
}

/**
 * Notifier l'administration d'une annulation
 */
function notifyAdminCancellation($order_id, $order_number, $customer_id, $reason) {
    global $pdo;
    
    try {
        // Récupérer les détails de la commande
        $stmt = $pdo->prepare("
            SELECT 
                o.total_amount,
                o.order_type,
                u.email as customer_email,
                CONCAT(u.first_name, ' ', u.last_name) as customer_name
            FROM orders o
            JOIN users u ON o.customer_id = u.id
            WHERE o.id = ?
        ");
        $stmt->execute([$order_id]);
        $order_details = $stmt->fetch();
        
        if (!$order_details) return;
        
        $log_message = "ANNULATION ADMIN - Commande #$order_number\n";
        $log_message .= "Client: {$order_details['customer_name']} <{$order_details['customer_email']}>\n";
        $log_message .= "ID Client: $customer_id\n";
        $log_message .= "Type: {$order_details['order_type']}\n";
        $log_message .= "Montant: " . number_format($order_details['total_amount'], 2, ',', ' ') . " €\n";
        $log_message .= "Raison: $reason\n";
        
        error_log($log_message);
        
        // Vous pourriez envoyer un email à l'administrateur ici
        // ou une notification via un webhook, Slack, etc.
        
        return true;
    } catch (Exception $e) {
        error_log("Erreur notification admin: " . $e->getMessage());
        return false;
    }
}

/**
 * Notification pour remboursement
 */
function sendRefundNotification($customer_id, $order_number, $amount) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT email, CONCAT(first_name, ' ', last_name) as name 
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$customer_id]);
        $customer = $stmt->fetch();
        
        if (!$customer) return;
        
        $log_message = "Notification de remboursement pour la commande $order_number\n";
        $log_message .= "À: {$customer['name']} <{$customer['email']}>\n";
        $log_message .= "Montant: " . number_format($amount, 2, ',', ' ') . " €\n";
        
        error_log($log_message);
        
        return true;
    } catch (Exception $e) {
        error_log("Erreur notification remboursement: " . $e->getMessage());
        return false;
    }
}