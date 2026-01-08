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
        error_log("Sortie API inventory détectée: " . bin2hex($output));
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
            $search = trim($_GET['search'] ?? '');
            $sql = "SELECT i.*, p.name AS product_name FROM inventory i LEFT JOIN products p ON p.id = i.product_id WHERE 1";
            $params = [];
            if ($search !== '') {
                $sql .= " AND (i.ingredient_name LIKE ? OR p.name LIKE ? OR i.supplier LIKE ?)";
                $like = "%$search%"; 
                array_push($params, $like, $like, $like);
            }
            $sql .= " ORDER BY i.ingredient_name ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        case 'create':
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) { 
                throw new Exception('Token CSRF invalide'); 
            }
            $product_id = (int)($_POST['product_id'] ?? 0);
            $ingredient_name = Security::sanitizeInput($_POST['ingredient_name'] ?? '');
            $current_stock = (float)($_POST['current_stock'] ?? 0);
            $unit = Security::sanitizeInput($_POST['unit'] ?? '');
            $min_stock_level = (float)($_POST['min_stock_level'] ?? 0);
            $cost_per_unit = (float)($_POST['cost_per_unit'] ?? 0);
            $supplier = Security::sanitizeInput($_POST['supplier'] ?? '');
            $expiry_date = $_POST['expiry_date'] ?? null;
            if ($ingredient_name === '' || $unit === '') { 
                throw new Exception('Champs requis manquants'); 
            }
            $stmt = $pdo->prepare("INSERT INTO inventory (product_id, ingredient_name, current_stock, unit, min_stock_level, cost_per_unit, supplier, expiry_date, last_updated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$product_id ?: null, $ingredient_name, $current_stock, $unit, $min_stock_level, $cost_per_unit, $supplier, $expiry_date ?: null]);
            echo json_encode(['success' => true, 'message' => 'Élément de stock ajouté']);
            break;

        case 'update':
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) { 
                throw new Exception('Token CSRF invalide'); 
            }
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { 
                throw new Exception('ID invalide'); 
            }
            $product_id = (int)($_POST['product_id'] ?? 0);
            $ingredient_name = Security::sanitizeInput($_POST['ingredient_name'] ?? '');
            $unit = Security::sanitizeInput($_POST['unit'] ?? '');
            $min_stock_level = (float)($_POST['min_stock_level'] ?? 0);
            $cost_per_unit = (float)($_POST['cost_per_unit'] ?? 0);
            $supplier = Security::sanitizeInput($_POST['supplier'] ?? '');
            $expiry_date = $_POST['expiry_date'] ?? null;
            if ($ingredient_name === '' || $unit === '') { 
                throw new Exception('Champs requis manquants'); 
            }
            $stmt = $pdo->prepare("UPDATE inventory SET product_id=?, ingredient_name=?, unit=?, min_stock_level=?, cost_per_unit=?, supplier=?, expiry_date=?, last_updated = NOW() WHERE id = ?");
            $stmt->execute([$product_id ?: null, $ingredient_name, $unit, $min_stock_level, $cost_per_unit, $supplier, $expiry_date ?: null, $id]);
            echo json_encode(['success' => true, 'message' => 'Élément de stock mis à jour']);
            break;

        case 'adjust':
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) { 
                throw new Exception('Token CSRF invalide'); 
            }
            $id = (int)($_POST['id'] ?? 0);
            $movement_type = $_POST['movement_type'] ?? '';
            $quantity = (float)($_POST['quantity'] ?? 0);
            $reason = Security::sanitizeInput($_POST['reason'] ?? '');
            $reference = Security::sanitizeInput($_POST['reference'] ?? '');
            if ($id <= 0 || !in_array($movement_type, ['in','out','adjustment'], true) || $quantity <= 0) { 
                throw new Exception('Paramètres invalides'); 
            }
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO stock_movements (inventory_id, movement_type, quantity, reason, reference, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
            $stmt->execute([$id, $movement_type, $quantity, $reason, $reference, $_SESSION['user_id'] ?? null]);
            $op = $movement_type === 'out' ? '-' : '+';
            $stmt2 = $pdo->prepare("UPDATE inventory SET current_stock = CASE WHEN ? = '-' THEN current_stock - ? ELSE current_stock + ? END, last_updated = NOW() WHERE id = ?");
            $stmt2->execute([$op, $quantity, $quantity, $id]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Stock ajusté']);
            break;

        case 'delete':
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) { 
                throw new Exception('Token CSRF invalide'); 
            }
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { 
                throw new Exception('ID invalide'); 
            }
            $stmt = $pdo->prepare('DELETE FROM inventory WHERE id = ?');
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Élément de stock supprimé']);
            break;

        default:
            throw new Exception('Action inconnue', 400);
    }
    
} catch (Exception $e) {
    // Nettoyer tout buffer restant
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Rollback si transaction en cours
    if (isset($pdo) && $pdo->inTransaction()) { 
        $pdo->rollBack(); 
    }
    
    http_response_code(is_int($e->getCode()) && $e->getCode() >= 400 ? $e->getCode() : 400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}