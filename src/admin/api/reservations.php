<?php
// Activer le buffering et supprimer toute sortie
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Désactiver l'affichage des erreurs pendant l'exécution
error_reporting(0);
ini_set('display_errors', 0);

try {
    session_start();
    require_once '../../config/database.php';
    require_once '../../config/security.php';
    
    // Capturer et supprimer toute sortie générée
    $output = ob_get_contents();
    ob_end_clean();
    
    // Si il y avait de la sortie, c'est une erreur
    if (!empty(trim($output))) {
        error_log("Sortie API réservations détectée: " . bin2hex($output));
        throw new Exception("Erreur interne du serveur");
    }
    
    header('Content-Type: application/json; charset=utf-8');
    
    if (!Security::isLoggedIn() || !Security::isAdmin()) {
        throw new Exception('Accès refusé', 403);
    }
    
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
        throw new Exception('Requête invalide', 400);
    }

    $action = $_GET['action'] ?? $_POST['action'] ?? '';


    switch ($action) {
        case 'list':
            $status = $_GET['status'] ?? '';
            $date = $_GET['date'] ?? '';

            $sql = "SELECT r.*, u.first_name, u.last_name, u.email FROM reservations r LEFT JOIN users u ON u.id = r.customer_id WHERE 1";
            $params = [];
            if ($status !== '') { $sql .= " AND r.status = ?"; $params[] = $status; }
            if ($date !== '') { $sql .= " AND r.reservation_date = ?"; $params[] = $date; }
            $sql .= " ORDER BY r.reservation_date DESC, r.reservation_time DESC LIMIT 200";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        case 'create':
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) { throw new Exception('Token CSRF invalide'); }
            $customer_name = Security::sanitizeInput($_POST['customer_name'] ?? '');
            $customer_email = Security::sanitizeInput($_POST['customer_email'] ?? '');
            $customer_phone = Security::sanitizeInput($_POST['customer_phone'] ?? '');
            $reservation_date = $_POST['reservation_date'] ?? '';
            $reservation_time = $_POST['reservation_time'] ?? '';
            $party_size = (int)($_POST['party_size'] ?? 1);
            $table_number = Security::sanitizeInput($_POST['table_number'] ?? '');
            $special_requests = Security::sanitizeInput($_POST['special_requests'] ?? '');

            if ($customer_name === '' || $reservation_date === '' || $reservation_time === '' || $party_size <= 0) {
                throw new Exception('Champs requis manquants');
            }

            $stmt = $pdo->prepare("INSERT INTO reservations (customer_name, customer_email, customer_phone, reservation_date, reservation_time, party_size, status, special_requests, table_number, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW())");
            $stmt->execute([$customer_name, $customer_email, $customer_phone, $reservation_date, $reservation_time, $party_size, $special_requests, $table_number ?: null]);
            echo json_encode(['success' => true, 'message' => 'Réservation ajoutée']);
            break;

        case 'update':
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) { throw new Exception('Token CSRF invalide'); }
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { throw new Exception('ID invalide'); }
            $fields = ['customer_name','customer_email','customer_phone','reservation_date','reservation_time','party_size','status','special_requests','table_number'];
            $data = [];
            foreach ($fields as $f) { $data[$f] = Security::sanitizeInput($_POST[$f] ?? ''); }
            $data['party_size'] = (int)($_POST['party_size'] ?? 1);
            $stmt = $pdo->prepare("UPDATE reservations SET customer_name=?, customer_email=?, customer_phone=?, reservation_date=?, reservation_time=?, party_size=?, status=?, special_requests=?, table_number=?, updated_at = NOW() WHERE id=?");
            $stmt->execute([$data['customer_name'],$data['customer_email'],$data['customer_phone'],$data['reservation_date'],$data['reservation_time'],$data['party_size'],$data['status'],$data['special_requests'],$data['table_number'] ?: null,$id]);
            echo json_encode(['success' => true, 'message' => 'Réservation mise à jour']);
            break;

        case 'delete':
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) { throw new Exception('Token CSRF invalide'); }
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { throw new Exception('ID invalide'); }
            $stmt = $pdo->prepare('DELETE FROM reservations WHERE id = ?');
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Réservation supprimée']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Action inconnue']);
    }
} catch (Exception $e) {
    // Nettoyer tout buffer restant
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>