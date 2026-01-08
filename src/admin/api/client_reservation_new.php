<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/security.php';

header('Content-Type: application/json; charset=utf-8');

// Vérifier si c'est une requête AJAX
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Requête invalide']);
    exit;
}

// Vérifier si l'utilisateur est connecté
if (!Security::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

// Récupérer l'ID de l'utilisateur connecté depuis la session
$current_user_id = $_SESSION['user_id'] ?? 0;

// Récupérer les informations utilisateur depuis la session
$user_name = $_SESSION['user_name'] ?? '';
$user_email = $_SESSION['user_email'] ?? '';

// Extraire prénom et nom
$name_parts = explode(' ', $user_name);
$user_first_name = $name_parts[0] ?? '';
$user_last_name = $name_parts[1] ?? '';

try {
    // DÉTERMINER L'ACTION SELON LA MÉTHODE HTTP
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? '';
    } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
        exit;
    }
    
    switch ($action) {
        case 'create':
            // Créer une nouvelle réservation (POST uniquement)
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Méthode non autorisée pour cette action');
            }
            
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Token CSRF invalide');
            }
            
            // Récupérer et valider les données du formulaire
            $customer_name = Security::sanitizeInput($_POST['customer_name'] ?? '');
            $customer_email = Security::sanitizeInput($_POST['customer_email'] ?? $user_email);
            $customer_phone = Security::sanitizeInput($_POST['customer_phone'] ?? '');
            $reservation_date = $_POST['reservation_date'] ?? '';
            $reservation_time = $_POST['reservation_time'] ?? '';
            $party_size = isset($_POST['party_size']) ? (int)$_POST['party_size'] : 1;
            $table_number = Security::sanitizeInput($_POST['table_name'] ?? '');
            $special_requests = Security::sanitizeInput($_POST['special_requests'] ?? '');
            
            // Validation des champs obligatoires
            if (empty($customer_name)) {
                throw new Exception('Le nom complet est requis');
            }
            if (empty($reservation_date)) {
                throw new Exception('La date est requise');
            }
            if (empty($reservation_time)) {
                throw new Exception('L\'heure est requise');
            }
            if ($party_size <= 0) {
                throw new Exception('Le nombre de personnes doit être supérieur à 0');
            }
            
            // Valider l'email s'il est fourni
            if (!empty($customer_email) && !Security::validateEmail($customer_email)) {
                throw new Exception('L\'email n\'est pas valide');
            }
            
            // Vérifier que la date n'est pas passée
            $reservation_datetime = $reservation_date . ' ' . $reservation_time;
            if (strtotime($reservation_datetime) < time()) {
                throw new Exception('Impossible de réserver pour une date/heure passée');
            }
            
            // Délai minimum pour réservation (2 heures)
            if (strtotime($reservation_datetime) < (time() + 7200)) {
                throw new Exception('La réservation doit être faite au moins 2 heures à l\'avance');
            }
            
            // Vérifier la disponibilité
            if (!checkAvailability($pdo, $reservation_date, $reservation_time)) {
                throw new Exception('Ce créneau horaire n\'est plus disponible');
            }
            
            // Vérifier la taille du groupe
            if ($party_size < 1 || $party_size > 12) {
                throw new Exception('La taille du groupe doit être entre 1 et 12 personnes');
            }
            
            // Récupérer les infos utilisateur complètes depuis la base
            $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, phone FROM users WHERE id = ?");
            $stmt->execute([$current_user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                throw new Exception('Utilisateur non trouvé');
            }
            
            // Utiliser l'email de l'utilisateur si non fourni
            if (empty($customer_email)) {
                $customer_email = $user['email'];
            }
            
            // Insérer la réservation
            $stmt = $pdo->prepare("
                INSERT INTO reservations 
                (customer_id, customer_name, customer_email, customer_phone, 
                 reservation_date, reservation_time, party_size, status, 
                 special_requests, table_number, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW())
            ");
            
            $stmt->execute([
                $current_user_id,
                $customer_name,
                $customer_email,
                $customer_phone,
                $reservation_date,
                $reservation_time,
                $party_size,
                $special_requests,
                $table_number ?: null
            ]);
            
            $reservation_id = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Réservation créée avec succès',
                'reservation_id' => $reservation_id
            ]);
            break;
            
        case 'list':
            // Lister les réservations du client (GET uniquement)
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                throw new Exception('Méthode non autorisée pour cette action');
            }
            
            $status = $_GET['status'] ?? '';
            $date_from = $_GET['date_from'] ?? '';
            $date_to = $_GET['date_to'] ?? '';
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            
            $sql = "SELECT 
                    r.*,
                    CASE 
                        WHEN r.reservation_date < CURDATE() THEN 'passée'
                        WHEN r.reservation_date = CURDATE() AND r.reservation_time < CURTIME() THEN 'passée'
                        ELSE 'à venir'
                    END as time_status
                    FROM reservations r 
                    WHERE r.customer_id = ?";
            
            $params = [$current_user_id];
            
            if (!empty($status)) {
                $sql .= " AND r.status = ?";
                $params[] = $status;
            }
            if (!empty($date_from)) {
                $sql .= " AND r.reservation_date >= ?";
                $params[] = $date_from;
            }
            if (!empty($date_to)) {
                $sql .= " AND r.reservation_date <= ?";
                $params[] = $date_to;
            }
            
            $sql .= " ORDER BY r.reservation_date DESC, r.reservation_time DESC";
            
            if ($limit > 0) {
                $sql .= " LIMIT ?";
                $params[] = $limit;
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Formater les dates pour l'affichage
            foreach ($reservations as &$res) {
                $res['formatted_date'] = date('d/m/Y', strtotime($res['reservation_date']));
                $res['formatted_time'] = date('H:i', strtotime($res['reservation_time']));
                
                // Déterminer la couleur selon le statut
                switch ($res['status']) {
                    case 'confirmed':
                        $res['status_color'] = 'success';
                        $res['status_text'] = 'Confirmée';
                        break;
                    case 'pending':
                        $res['status_color'] = 'warning';
                        $res['status_text'] = 'En attente';
                        break;
                    case 'cancelled':
                        $res['status_color'] = 'secondary';
                        $res['status_text'] = 'Annulée';
                        break;
                    case 'completed':
                        $res['status_color'] = 'info';
                        $res['status_text'] = 'Terminée';
                        break;
                    default:
                        $res['status_color'] = 'light';
                        $res['status_text'] = 'Inconnu';
                }
            }
            
            echo json_encode([
                'success' => true, 
                'data' => $reservations,
                'count' => count($reservations)
            ]);
            break;
            
        case 'update':
            // Mettre à jour une réservation (POST uniquement)
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Méthode non autorisée pour cette action');
            }
            
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Token CSRF invalide');
            }
            
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            if ($id <= 0) {
                throw new Exception('ID invalide');
            }
            
            // Vérifier que la réservation appartient à l'utilisateur
            $reservation = getReservation($pdo, $id, $current_user_id);
            if (!$reservation) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Réservation non trouvée']);
                exit;
            }
            
            // Vérifier si la réservation peut être modifiée
            $status = $reservation['status'];
            $reservation_datetime = $reservation['reservation_date'] . ' ' . $reservation['reservation_time'];
            
            if ($status === 'cancelled' || $status === 'completed') {
                throw new Exception('Impossible de modifier une réservation ' . $status);
            }
            
            if (strtotime($reservation_datetime) < (time() + 3600)) {
                throw new Exception('Impossible de modifier une réservation à moins d\'une heure du rendez-vous');
            }
            
            // Préparer les mises à jour
            $fields = [
                'customer_name' => 'string',
                'customer_email' => 'string',
                'customer_phone' => 'string',
                'reservation_date' => 'string',
                'reservation_time' => 'string',
                'party_size' => 'int',
                'special_requests' => 'string',
                'table_number' => 'string'
            ];
            
            $updates = [];
            $params = [];
            
            foreach ($fields as $field => $type) {
                if (isset($_POST[$field])) {
                    if ($type === 'string') {
                        $value = Security::sanitizeInput($_POST[$field]);
                    } else {
                        $value = (int)$_POST[$field];
                    }
                    
                    // Validation spécifique
                    if ($field === 'party_size' && ($value < 1 || $value > 12)) {
                        throw new Exception('La taille du groupe doit être entre 1 et 12 personnes');
                    }
                    
                    if ($field === 'customer_email' && !empty($value) && !Security::validateEmail($value)) {
                        throw new Exception('L\'email n\'est pas valide');
                    }
                    
                    $updates[] = "$field = ?";
                    $params[] = $value;
                }
            }
            
            if (empty($updates)) {
                throw new Exception('Aucune modification fournie');
            }
            
            // Si la date ou l'heure change, vérifier la disponibilité
            if (isset($_POST['reservation_date']) || isset($_POST['reservation_time'])) {
                $new_date = $_POST['reservation_date'] ?? $reservation['reservation_date'];
                $new_time = $_POST['reservation_time'] ?? $reservation['reservation_time'];
                
                if (!checkAvailability($pdo, $new_date, $new_time, $id)) {
                    throw new Exception('Le nouveau créneau horaire n\'est plus disponible');
                }
            }
            
            // Ajouter l'ID et l'ID utilisateur pour la clause WHERE
            $params[] = $id;
            $params[] = $current_user_id;
            
            // Exécuter la mise à jour
            $sql = "UPDATE reservations SET " . implode(', ', $updates) . ", updated_at = NOW() 
                    WHERE id = ? AND customer_id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Réservation mise à jour avec succès'
            ]);
            break;
            
        case 'cancel':
            // Annuler une réservation (POST uniquement)
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Méthode non autorisée pour cette action');
            }
            
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Token CSRF invalide');
            }
            
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            if ($id <= 0) {
                throw new Exception('ID invalide');
            }
            
            // Vérifier que la réservation appartient à l'utilisateur
            $reservation = getReservation($pdo, $id, $current_user_id);
            if (!$reservation) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Réservation non trouvée']);
                exit;
            }
            
            // Vérifier si la réservation peut être annulée
            $status = $reservation['status'];
            $reservation_datetime = $reservation['reservation_date'] . ' ' . $reservation['reservation_time'];
            
            if ($status === 'cancelled') {
                throw new Exception('Cette réservation est déjà annulée');
            }
            
            if ($status === 'completed') {
                throw new Exception('Impossible d\'annuler une réservation terminée');
            }
            
            if (strtotime($reservation_datetime) < time()) {
                throw new Exception('Impossible d\'annuler une réservation passée');
            }
            
            // Annuler la réservation
            $stmt = $pdo->prepare("
                UPDATE reservations 
                SET status = 'cancelled', updated_at = NOW() 
                WHERE id = ? AND customer_id = ?
            ");
            $stmt->execute([$id, $current_user_id]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Réservation annulée avec succès'
            ]);
            break;
            
        case 'availability':
            // Vérifier la disponibilité (GET uniquement)
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                throw new Exception('Méthode non autorisée pour cette action');
            }
            
            $date = $_GET['date'] ?? '';
            $time = $_GET['time'] ?? '';
            $party_size = isset($_GET['party_size']) ? (int)$_GET['party_size'] : 2;
            
            if (empty($date)) {
                throw new Exception('Date requise');
            }
            
            // Vérifier que la date n'est pas passée
            if (strtotime($date) < strtotime(date('Y-m-d'))) {
                echo json_encode([
                    'success' => true, 
                    'available_slots' => [],
                    'message' => 'Date passée'
                ]);
                exit;
            }
            
            if (!empty($time)) {
                // Vérifier un créneau spécifique
                $available = checkAvailability($pdo, $date, $time);
                echo json_encode([
                    'success' => true, 
                    'available' => $available,
                    'date' => $date,
                    'time' => $time
                ]);
            } else {
                // Lister tous les créneaux disponibles pour cette date
                $available_slots = getAvailableTimeSlots($pdo, $date, $party_size);
                echo json_encode([
                    'success' => true, 
                    'available_slots' => $available_slots,
                    'date' => $date,
                    'party_size' => $party_size
                ]);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Action inconnue: ' . $action]);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Fonctions utilitaires
function getReservation($pdo, $reservation_id, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ? AND customer_id = ?");
    $stmt->execute([$reservation_id, $user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function checkAvailability($pdo, $date, $time, $exclude_id = null) {
    // Capacité maximale du restaurant par créneau
    $max_capacity = 20; // À ajuster selon votre restaurant
    
    $sql = "SELECT SUM(party_size) as total_guests 
            FROM reservations 
            WHERE reservation_date = ? 
            AND reservation_time = ? 
            AND status IN ('confirmed', 'pending')";
    
    $params = [$date, $time];
    
    if ($exclude_id) {
        $sql .= " AND id != ?";
        $params[] = $exclude_id;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return ($result['total_guests'] ?? 0) < $max_capacity;
}

function getAvailableTimeSlots($pdo, $date, $party_size, $exclude_id = null) {
    $available_slots = [];
    
    // Heures d'ouverture du restaurant (à ajuster selon votre restaurant)
    $opening_time = '12:00';
    $closing_time = '23:59';
    
    // Générer tous les créneaux possibles (toutes les 30 minutes)
    $current = strtotime($date . ' ' . $opening_time);
    $end = strtotime($date . ' ' . $closing_time);
    
    while ($current <= $end) {
        $slot = date('H:i', $current);
        
        // Vérifier la disponibilité pour ce créneau
        if (checkAvailability($pdo, $date, $slot, $exclude_id)) {
            $available_slots[] = $slot;
        }
        
        $current = strtotime('+30 minutes', $current);
    }
    
    return $available_slots;
}
?>