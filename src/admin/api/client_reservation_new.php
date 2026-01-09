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
            $table_id = isset($_POST['table_id']) ? (int)$_POST['table_id'] : 0;
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
            
            // Vérifier la taille du groupe
            if ($party_size < 1 || $party_size > 12) {
                throw new Exception('La taille du groupe doit être entre 1 et 12 personnes');
            }
            
            // Si une table est sélectionnée, vérifier qu'elle existe et est disponible
            if ($table_id > 0) {
                $stmt = $pdo->prepare("SELECT id, table_name, capacity FROM tables WHERE id = ? AND is_available = 1");
                $stmt->execute([$table_id]);
                $table = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$table) {
                    throw new Exception('La table sélectionnée n\'existe pas ou n\'est pas disponible');
                }
                
                // Vérifier la capacité de la table
                if ($party_size > $table['capacity']) {
                    throw new Exception('La table "' . $table['table_name'] . '" a une capacité de ' . $table['capacity'] . ' personnes, mais votre groupe est de ' . $party_size . ' personnes.');
                }
                
                $table_value = $table_id;
            } else {
                $table_value = null;
            }
            
            // Vérifier la disponibilité du créneau horaire
            if (!checkAvailability($pdo, $reservation_date, $reservation_time, null, $table_id)) {
                throw new Exception('Ce créneau horaire n\'est plus disponible');
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
                 special_requests, table_id, created_at) 
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
                $table_value  // NULL ou l'ID de la table
            ]);
            
            $reservation_id = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Réservation créée avec succès',
                'reservation_id' => $reservation_id
            ]);
            break;
            
        case 'update':
            // Mettre à jour une réservation existante
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Méthode non autorisée');
            }
            
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Token CSRF invalide');
            }
            
            $reservation_id = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;
            if ($reservation_id <= 0) {
                throw new Exception('ID de réservation invalide');
            }
            
            // Vérifier que la réservation appartient à l'utilisateur
            $stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ? AND customer_id = ?");
            $stmt->execute([$reservation_id, $current_user_id]);
            $existing_reservation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$existing_reservation) {
                throw new Exception('Réservation non trouvée');
            }
            
            // Vérifier qu'on peut modifier (statut pending et au moins 1h avant)
            if ($existing_reservation['status'] !== 'pending') {
                throw new Exception('Seules les réservations en attente peuvent être modifiées');
            }
            
            $reservation_datetime = $existing_reservation['reservation_date'] . ' ' . $existing_reservation['reservation_time'];
            if (strtotime($reservation_datetime) < (time() + 3600)) {
                throw new Exception('Impossible de modifier une réservation à moins d\'une heure du rendez-vous');
            }
            
            // Récupérer les nouvelles données
            $customer_name = Security::sanitizeInput($_POST['customer_name'] ?? '');
            $customer_email = Security::sanitizeInput($_POST['customer_email'] ?? $user_email);
            $customer_phone = Security::sanitizeInput($_POST['customer_phone'] ?? '');
            $reservation_date = $_POST['reservation_date'] ?? '';
            $reservation_time = $_POST['reservation_time'] ?? '';
            $party_size = isset($_POST['party_size']) ? (int)$_POST['party_size'] : 1;
            $table_id = isset($_POST['table_id']) ? (int)$_POST['table_id'] : 0;
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
            $new_reservation_datetime = $reservation_date . ' ' . $reservation_time;
            if (strtotime($new_reservation_datetime) < time()) {
                throw new Exception('Impossible de réserver pour une date/heure passée');
            }
            
            // Délai minimum pour réservation (2 heures)
            if (strtotime($new_reservation_datetime) < (time() + 7200)) {
                throw new Exception('La réservation doit être faite au moins 2 heures à l\'avance');
            }
            
            // Vérifier la taille du groupe
            if ($party_size < 1 || $party_size > 12) {
                throw new Exception('La taille du groupe doit être entre 1 et 12 personnes');
            }
            
            // Si une table est sélectionnée, vérifier qu'elle existe et est disponible
            if ($table_id > 0) {
                $stmt = $pdo->prepare("SELECT id, table_name, capacity FROM tables WHERE id = ? AND is_available = 1");
                $stmt->execute([$table_id]);
                $table = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$table) {
                    throw new Exception('La table sélectionnée n\'existe pas ou n\'est pas disponible');
                }
                
                // Vérifier la capacité de la table
                if ($party_size > $table['capacity']) {
                    throw new Exception('La table "' . $table['table_name'] . '" a une capacité de ' . $table['capacity'] . ' personnes, mais votre groupe est de ' . $party_size . ' personnes.');
                }
                
                $table_value = $table_id;
            } else {
                $table_value = null;
            }
            
            // Vérifier la disponibilité du nouveau créneau (en excluant la réservation actuelle)
            if (!checkAvailability($pdo, $reservation_date, $reservation_time, $reservation_id, $table_id)) {
                throw new Exception('Le nouveau créneau horaire n\'est plus disponible');
            }
            
            // Mettre à jour
            $stmt = $pdo->prepare("
                UPDATE reservations SET
                customer_name = ?,
                customer_email = ?,
                customer_phone = ?,
                reservation_date = ?,
                reservation_time = ?,
                party_size = ?,
                special_requests = ?,
                table_id = ?,
                updated_at = NOW()
                WHERE id = ? AND customer_id = ?
            ");
            
            $table_value = ($table_id > 0) ? $table_id : null;
            
            $stmt->execute([
                $customer_name,
                $customer_email,
                $customer_phone,
                $reservation_date,
                $reservation_time,
                $party_size,
                $special_requests,
                $table_value,
                $reservation_id,
                $current_user_id
            ]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Réservation mise à jour avec succès'
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
                    t.table_name,
                    CASE 
                        WHEN r.reservation_date < CURDATE() THEN 'passée'
                        WHEN r.reservation_date = CURDATE() AND r.reservation_time < CURTIME() THEN 'passée'
                        ELSE 'à venir'
                    END as time_status
                    FROM reservations r 
                    LEFT JOIN tables t ON r.table_id = t.id
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
            $stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ? AND customer_id = ?");
            $stmt->execute([$id, $current_user_id]);
            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
            
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
            $table_id = isset($_GET['table_id']) ? (int)$_GET['table_id'] : 0;
            
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
                $available = checkAvailability($pdo, $date, $time, null, $table_id);
                echo json_encode([
                    'success' => true, 
                    'available' => $available,
                    'date' => $date,
                    'time' => $time
                ]);
            } else {
                // Lister tous les créneaux disponibles pour cette date
                $available_slots = getAvailableTimeSlots($pdo, $date, $party_size, null, $table_id);
                echo json_encode([
                    'success' => true, 
                    'available_slots' => $available_slots,
                    'date' => $date,
                    'party_size' => $party_size,
                    'table_id' => $table_id
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

function checkAvailability($pdo, $date, $time, $exclude_id = null, $table_id = 0) {
    // Si une table spécifique est demandée
    if ($table_id > 0) {
        $sql = "SELECT COUNT(*) as count 
                FROM reservations 
                WHERE reservation_date = ? 
                AND reservation_time = ? 
                AND table_id = ?
                AND status IN ('confirmed', 'pending')";
        
        $params = [$date, $time, $table_id];
        
        if ($exclude_id) {
            $sql .= " AND id != ?";
            $params[] = $exclude_id;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // La table est disponible si aucune réservation ne l'utilise à ce créneau
        return ($result['count'] ?? 0) == 0;
    } else {
        // Capacité maximale du restaurant par créneau
        $max_capacity = 20;
        
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
}

function getAvailableTimeSlots($pdo, $date, $party_size, $exclude_id = null, $table_id = 0) {
    $available_slots = [];
    
    // Heures d'ouverture du restaurant
    $opening_time = '12:00';
    $closing_time = '23:59';
    
    // Générer tous les créneaux possibles
    $current = strtotime($date . ' ' . $opening_time);
    $end = strtotime($date . ' ' . $closing_time);
    
    while ($current <= $end) {
        $slot = date('H:i', $current);
        
        // Vérifier la disponibilité pour ce créneau
        if (checkAvailability($pdo, $date, $slot, $exclude_id, $table_id)) {
            // Si une table est spécifiée, vérifier aussi sa capacité
            if ($table_id > 0) {
                $stmt = $pdo->prepare("SELECT capacity FROM tables WHERE id = ?");
                $stmt->execute([$table_id]);
                $table = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($table && $party_size <= $table['capacity']) {
                    $available_slots[] = $slot;
                }
            } else {
                $available_slots[] = $slot;
            }
        }
        
        $current = strtotime('+30 minutes', $current);
    }
    
    return $available_slots;
}
?>