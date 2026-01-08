<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/security.php';
require_once '../../services/EmailService.php'; // Ajoutez cette ligne

header('Content-Type: application/json; charset=utf-8');

// Vérifier si l'utilisateur est connecté MAIS permettre la création sans login pour les clients non-enregistrés
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Certaines actions nécessitent d'être connecté, d'autres non
$actionsRequiringLogin = ['list', 'update', 'delete', 'get'];
$actionsForEveryone = ['create'];

// Si l'action nécessite un login mais l'utilisateur n'est pas connecté
if (in_array($action, $actionsRequiringLogin) && !Security::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Veuillez vous connecter pour accéder à cette fonctionnalité']);
    exit;
}

// Pour l'action 'create', on peut permettre aux non-connectés de créer une réservation
// Mais si l'utilisateur est connecté, on utilisera ses infos
if ($action === 'create' && !Security::isLoggedIn()) {
    // On permet la création sans être connecté
    $user_id = null;
} else if (Security::isLoggedIn()) {
    $user_id = $_SESSION['user_id'] ?? null;
} else {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Requête invalide']);
    exit;
}

try {
    // Si l'utilisateur est connecté ET n'est pas admin, vérifier s'il est actif
    if (Security::isLoggedIn() && !Security::isAdmin()) {
        $stmt = $pdo->prepare("SELECT is_active FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user || !$user['is_active']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Votre compte n\'est pas actif. Veuillez contacter l\'administrateur.']);
            exit;
        }
    }

    switch ($action) {
        case 'list':
            // Vérifier que l'utilisateur est connecté (déjà vérifié plus haut)
            if (!Security::isAdmin()) {
                // Un client ne peut voir que ses propres réservations
                $sql = "SELECT r.*, u.first_name, u.last_name, u.email 
                        FROM reservations r 
                        LEFT JOIN users u ON u.id = r.customer_id 
                        WHERE r.customer_id = ?";
                $params = [$user_id];
                
                // Ajouter les filtres si spécifiés
                $status = $_GET['status'] ?? '';
                $date = $_GET['date'] ?? '';
                
                if ($status !== '') { 
                    $sql .= " AND r.status = ?"; 
                    $params[] = $status; 
                }
                if ($date !== '') { 
                    $sql .= " AND r.reservation_date = ?"; 
                    $params[] = $date; 
                }
                $sql .= " ORDER BY r.reservation_date DESC, r.reservation_time DESC LIMIT 200";
            } else {
                // L'admin voit toutes les réservations
                $status = $_GET['status'] ?? '';
                $date = $_GET['date'] ?? '';

                $sql = "SELECT r.*, u.first_name, u.last_name, u.email 
                        FROM reservations r 
                        LEFT JOIN users u ON u.id = r.customer_id 
                        WHERE 1";
                $params = [];
                if ($status !== '') { 
                    $sql .= " AND r.status = ?"; 
                    $params[] = $status; 
                }
                if ($date !== '') { 
                    $sql .= " AND r.reservation_date = ?"; 
                    $params[] = $date; 
                }
                $sql .= " ORDER BY r.reservation_date DESC, r.reservation_time DESC LIMIT 200";
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        case 'create':
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) { 
                throw new Exception('Token CSRF invalide'); 
            }
            
            // Si l'utilisateur est connecté, on utilise ses informations de profil
            if (Security::isLoggedIn() && !Security::isAdmin()) {
                // Récupérer les infos de l'utilisateur connecté
                $stmt = $pdo->prepare("SELECT first_name, last_name, email, phone FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                
                if (!$user) {
                    throw new Exception('Utilisateur non trouvé');
                }
                
                $customer_name = $user['first_name'] . ' ' . $user['last_name'];
                $customer_email = $user['email'];
                $customer_phone = $user['phone'] ?? '';
                $customer_id = $user_id;
            } else if (Security::isAdmin() && Security::isLoggedIn()) {
                // Pour l'admin, on peut créer une réservation pour n'importe qui
                $customer_name = Security::sanitizeInput($_POST['customer_name'] ?? '');
                $customer_email = Security::sanitizeInput($_POST['customer_email'] ?? '');
                $customer_phone = Security::sanitizeInput($_POST['customer_phone'] ?? '');
                $customer_id = $_POST['customer_id'] ?? null;
            } else {
                // Pour un visiteur non connecté
                $customer_name = Security::sanitizeInput($_POST['customer_name'] ?? '');
                $customer_email = Security::sanitizeInput($_POST['customer_email'] ?? '');
                $customer_phone = Security::sanitizeInput($_POST['customer_phone'] ?? '');
                $customer_id = null;
            }
            
            $reservation_date = $_POST['reservation_date'] ?? '';
            $reservation_time = $_POST['reservation_time'] ?? '';
            $party_size = (int)($_POST['party_size'] ?? 1);
            $table_number = Security::sanitizeInput($_POST['table_number'] ?? '');
            $special_requests = Security::sanitizeInput($_POST['special_requests'] ?? '');

            // Validation des champs obligatoires
            if (empty($customer_name) || empty($customer_email) || empty($reservation_date) || empty($reservation_time)) {
                throw new Exception('Les champs nom, email, date et heure sont obligatoires');
            }
            
            if ($party_size <= 0) {
                throw new Exception('Le nombre de personnes doit être supérieur à 0');
            }
            
            // Validation de l'email
            if (!filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Adresse email invalide');
            }

            // Vérifier si l'utilisateur a déjà une réservation à la même date/heure (seulement pour les utilisateurs connectés)
            if ($customer_id) {
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM reservations WHERE customer_id = ? AND reservation_date = ? AND reservation_time = ? AND status NOT IN ('cancelled', 'rejected')");
                $stmt->execute([$customer_id, $reservation_date, $reservation_time]);
                $existing = $stmt->fetch();
                
                if ($existing['count'] > 0) {
                    throw new Exception('Vous avez déjà une réservation à cette date et heure');
                }
            }

            // Insérer la réservation
            $stmt = $pdo->prepare("INSERT INTO reservations (customer_name, customer_email, customer_phone, customer_id, reservation_date, reservation_time, party_size, status, special_requests, table_number, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW())");
            $stmt->execute([$customer_name, $customer_email, $customer_phone, $customer_id, $reservation_date, $reservation_time, $party_size, $special_requests, $table_number ?: null]);
            
            // Récupérer l'ID de la nouvelle réservation
            $reservation_id = $pdo->lastInsertId();
            
            // Envoyer un email de confirmation
            try {
                $reservation_data = [
                    'reservation_id' => $reservation_id,
                    'customer_name' => $customer_name,
                    'reservation_date' => $reservation_date,
                    'reservation_time' => $reservation_time,
                    'party_size' => $party_size,
                    'table_number' => $table_number ?: 'À déterminer'
                ];
                
                // Email au client
                EmailService::sendReservationEmail(
                    $customer_email,
                    'Création de votre réservation - Le Gourmet',
                    'created',
                    $reservation_data
                );
                
                // Notification à l'admin (si non-connecté ou client normal)
                if (!Security::isAdmin()) {
                    EmailService::sendNewReservationNotification($reservation_id, $pdo);
                }
                
            } catch (Exception $e) {
                // Ne pas bloquer la création si l'email échoue, juste logger
                error_log("Erreur envoi email pour réservation #$reservation_id: " . $e->getMessage());
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'Réservation créée avec succès', 
                'reservation_id' => $reservation_id,
                'is_logged_in' => Security::isLoggedIn()
            ]);
            break;

        case 'update':
            // Vérifier que l'utilisateur est connecté
            if (!Security::isLoggedIn()) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Veuillez vous connecter pour modifier une réservation']);
                exit;
            }
            
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) { 
                throw new Exception('Token CSRF invalide'); 
            }
            
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { 
                throw new Exception('ID invalide'); 
            }
            
            // Récupérer l'ANCIENNE réservation avant mise à jour
            $stmt = $pdo->prepare("SELECT status, customer_email, customer_name, customer_id, reservation_date, reservation_time, party_size, table_number FROM reservations WHERE id = ?");
            $stmt->execute([$id]);
            $old_reservation = $stmt->fetch();
            
            if (!$old_reservation) {
                throw new Exception('Réservation non trouvée');
            }
            
            // Vérifier les permissions
            if (!Security::isAdmin()) {
                // Un client ne peut modifier que ses propres réservations
                if ($old_reservation['customer_id'] != $user_id) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Vous ne pouvez modifier que vos propres réservations']);
                    exit;
                }
                
                // Un client ne peut pas modifier certaines données
                $fields = ['reservation_date', 'reservation_time', 'party_size', 'special_requests'];
            } else {
                // L'admin peut tout modifier
                $fields = ['customer_name','customer_email','customer_phone','reservation_date','reservation_time','party_size','status','special_requests','table_number'];
            }
            
            $data = [];
            foreach ($fields as $f) { 
                $data[$f] = Security::sanitizeInput($_POST[$f] ?? ''); 
            }
            
            // Construire la requête dynamiquement
            $sql = "UPDATE reservations SET ";
            $setParts = [];
            $params = [];
            
            foreach ($fields as $field) {
                if ($field === 'party_size') {
                    $data[$field] = (int)($_POST[$field] ?? 1);
                }
                $setParts[] = "$field = ?";
                $params[] = $data[$field];
            }
            
            $sql .= implode(', ', $setParts) . ", updated_at = NOW() WHERE id = ?";
            $params[] = $id;
            
            // Exécuter la mise à jour
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // Vérifier si le statut a changé (pour l'admin seulement)
            if (Security::isAdmin() && isset($data['status']) && $data['status'] !== $old_reservation['status']) {
                // Préparer les données pour l'email
                $reservation_data = [
                    'reservation_id' => $id,
                    'customer_name' => $data['customer_name'] ?? $old_reservation['customer_name'],
                    'reservation_date' => $data['reservation_date'] ?? $old_reservation['reservation_date'],
                    'reservation_time' => $data['reservation_time'] ?? $old_reservation['reservation_time'],
                    'party_size' => $data['party_size'] ?? $old_reservation['party_size'],
                    'table_number' => $data['table_number'] ?? ($old_reservation['table_number'] ?: 'À déterminer')
                ];
                
                $customer_email = $data['customer_email'] ?? $old_reservation['customer_email'];
                
                // Envoyer un email selon le nouveau statut
                switch ($data['status']) {
                    case 'confirmed':
                        // Email de confirmation (commande en cours)
                        EmailService::sendReservationEmail(
                            $customer_email,
                            'Confirmation de votre réservation - Le Gourmet',
                            'confirmation',
                            $reservation_data
                        );
                        break;
                        
                    case 'completed':
                        // Email de remerciement (réservation terminée)
                        EmailService::sendReservationEmail(
                            $customer_email,
                            'Merci pour votre visite - Le Gourmet',
                            'completed',
                            $reservation_data
                        );
                        break;
                        
                    case 'cancelled':
                        // Email d'annulation
                        EmailService::sendReservationEmail(
                            $customer_email,
                            'Annulation de votre réservation - Le Gourmet',
                            'cancelled',
                            $reservation_data
                        );
                        break;
                }
            }
            
            echo json_encode(['success' => true, 'message' => 'Réservation mise à jour']);
            break;

        case 'delete':
            // Vérifier que l'utilisateur est connecté
            if (!Security::isLoggedIn()) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Veuillez vous connecter pour supprimer une réservation']);
                exit;
            }
            
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) { 
                throw new Exception('Token CSRF invalide'); 
            }
            
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { 
                throw new Exception('ID invalide'); 
            }
            
            // Récupérer les infos de la réservation avant suppression pour l'email
            $stmt = $pdo->prepare("SELECT customer_email, customer_name, customer_id, status FROM reservations WHERE id = ?");
            $stmt->execute([$id]);
            $reservation = $stmt->fetch();
            
            if (!$reservation) {
                throw new Exception('Réservation non trouvée');
            }
            
            // Vérifier les permissions
            if (!Security::isAdmin()) {
                // Un client ne peut supprimer que ses propres réservations
                if ($reservation['customer_id'] != $user_id) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Vous ne pouvez supprimer que vos propres réservations']);
                    exit;
                }
                
                // Envoyer un email de confirmation de suppression au client
                if ($reservation['status'] !== 'cancelled') {
                    EmailService::sendReservationEmail(
                        $reservation['customer_email'],
                        'Annulation de votre réservation - Le Gourmet',
                        'cancelled',
                        ['customer_name' => $reservation['customer_name'], 'reservation_id' => $id]
                    );
                }
            } else {
                // Si l'admin supprime, envoyer un email d'annulation
                if ($reservation['status'] !== 'cancelled') {
                    EmailService::sendReservationEmail(
                        $reservation['customer_email'],
                        'Annulation de votre réservation par l\'administrateur - Le Gourmet',
                        'cancelled_admin',
                        ['customer_name' => $reservation['customer_name'], 'reservation_id' => $id]
                    );
                }
            }
            
            // Supprimer la réservation (ou la marquer comme annulée - à vous de choisir)
            // Option 1: Suppression physique
            // $stmt = $pdo->prepare('DELETE FROM reservations WHERE id = ?');
            // $stmt->execute([$id]);
            
            // Option 2: Marquer comme annulée (recommandé)
            $stmt = $pdo->prepare('UPDATE reservations SET status = "cancelled", updated_at = NOW() WHERE id = ?');
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true, 'message' => 'Réservation annulée']);
            break;

        case 'get':
            // Vérifier que l'utilisateur est connecté
            if (!Security::isLoggedIn()) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Veuillez vous connecter pour voir les détails d\'une réservation']);
                exit;
            }
            
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) { 
                throw new Exception('ID invalide'); 
            }
            
            if (!Security::isAdmin()) {
                // Un client ne peut voir que ses propres réservations
                $stmt = $pdo->prepare("SELECT r.* FROM reservations r WHERE r.id = ? AND r.customer_id = ?");
                $stmt->execute([$id, $user_id]);
            } else {
                // L'admin peut voir toutes les réservations
                $stmt = $pdo->prepare("SELECT r.*, u.first_name, u.last_name, u.email, u.phone FROM reservations r LEFT JOIN users u ON u.id = r.customer_id WHERE r.id = ?");
                $stmt->execute([$id]);
            }
            
            $reservation = $stmt->fetch();
            
            if (!$reservation) {
                throw new Exception('Réservation non trouvée');
            }
            
            echo json_encode(['success' => true, 'data' => $reservation]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Action inconnue']);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>