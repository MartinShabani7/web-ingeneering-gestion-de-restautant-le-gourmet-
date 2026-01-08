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
        error_log("Sortie API services détectée: " . bin2hex($output));
        throw new Exception("Erreur interne du serveur");
    }
    
    header('Content-Type: application/json; charset=utf-8');
    
    if (!Security::isLoggedIn() || !Security::isAdmin()) {
        throw new Exception('Accès refusé', 403);
    }
    
    // Récupérer l'action depuis GET ou POST
    $action = $_GET['action'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            if ($action === 'get' || empty($action)) {
                // Récupérer tous les services ou un service spécifique
                if (isset($_GET['id'])) {
                    $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
                    $stmt->execute([$_GET['id']]);
                    $service = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($service) {
                        echo json_encode($service);
                    } else {
                        throw new Exception('Service non trouvé', 404);
                    }
                } else {
                    $stmt = $pdo->query("SELECT * FROM services ORDER BY sort_order ASC");
                    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    echo json_encode($services);
                }
            } else {
                throw new Exception('Action GET inconnue', 400);
            }
            break;

        case 'POST':
            // Créer un nouveau service
            $data = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Données JSON invalides', 400);
            }
            
            $required = ['title', 'description', 'icon', 'button_text', 'button_link'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Le champ $field est requis", 400);
                }
            }

            $stmt = $pdo->prepare("
                INSERT INTO services (title, description, icon, button_text, button_link, button_color, background_color, sort_order, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['title'],
                $data['description'],
                $data['icon'],
                $data['button_text'],
                $data['button_link'],
                $data['button_color'] ?? 'primary',
                $data['background_color'] ?? 'primary',
                $data['sort_order'] ?? 0,
                $data['is_active'] ?? 1
            ]);

            $serviceId = $pdo->lastInsertId();
            echo json_encode(['success' => true, 'id' => $serviceId, 'message' => 'Service créé avec succès']);
            break;

        case 'PUT':
            // Modifier un service
            $data = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Données JSON invalides', 400);
            }
            
            if (empty($data['id'])) {
                throw new Exception('ID du service requis', 400);
            }

            $stmt = $pdo->prepare("
                UPDATE services SET 
                title = ?, description = ?, icon = ?, button_text = ?, button_link = ?, 
                button_color = ?, background_color = ?, sort_order = ?, is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['title'],
                $data['description'],
                $data['icon'],
                $data['button_text'],
                $data['button_link'],
                $data['button_color'] ?? 'primary',
                $data['background_color'] ?? 'primary',
                $data['sort_order'] ?? 0,
                $data['is_active'] ?? 1,
                $data['id']
            ]);

            echo json_encode(['success' => true, 'message' => 'Service modifié avec succès']);
            break;

        case 'DELETE':
            // Supprimer un service
            if (empty($_GET['id'])) {
                throw new Exception('ID du service requis', 400);
            }

            $stmt = $pdo->prepare("DELETE FROM services WHERE id = ?");
            $stmt->execute([$_GET['id']]);

            echo json_encode(['success' => true, 'message' => 'Service supprimé avec succès']);
            break;

        default:
            throw new Exception('Méthode non autorisée', 405);
    }
    
} catch (Exception $e) {
    // Nettoyer tout buffer restant
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    http_response_code(is_int($e->getCode()) && $e->getCode() >= 400 ? $e->getCode() : 400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}