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
        throw new Exception("Sortie non attendue détectée");
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
                $search = trim($_GET['search'] ?? '');

                $sql = "
                    SELECT o.*, u.first_name, u.last_name, u.email,
                        (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
                    FROM orders o
                    LEFT JOIN users u ON o.customer_id = u.id
                    WHERE 1
                ";
                $params = [];

                if ($status !== '') {
                    $sql .= " AND o.status = ?";
                    $params[] = $status;
                }
                if ($search !== '') {
                    $sql .= " AND (o.order_number LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
                    $like = "%$search%";
                    array_push($params, $like, $like, $like, $like);
                }

                $sql .= " ORDER BY o.created_at DESC LIMIT 200";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
                break;

            case 'details':
                $id = (int)($_GET['id'] ?? 0);
                if ($id <= 0) { throw new Exception('ID invalide'); }

                $o = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
                $o->execute([$id]);
                $order = $o->fetch();
                if (!$order) { throw new Exception('Commande introuvable'); }

                $it = $pdo->prepare("SELECT oi.*, p.name FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?");
                $it->execute([$id]);
                $items = $it->fetchAll();

                echo json_encode(['success' => true, 'order' => $order, 'items' => $items]);
                break;

            case 'update_status':
                if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                    throw new Exception('Token CSRF invalide');
                }
                $id = (int)($_POST['id'] ?? 0);
                $status = $_POST['status'] ?? '';
                $allowed = ['pending','confirmed','preparing','ready','served','cancelled'];
                if ($id <= 0 || !in_array($status, $allowed, true)) {
                    throw new Exception('Paramètres invalides');
                }
                $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$status, $id]);
                echo json_encode(['success' => true, 'message' => 'Statut mis à jour']);
                break;

            case 'update_payment':
                if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                    throw new Exception('Token CSRF invalide');
                }
                $id = (int)($_POST['id'] ?? 0);
                $payment_status = $_POST['payment_status'] ?? '';
                $allowed = ['pending','paid','refunded'];
                if ($id <= 0 || !in_array($payment_status, $allowed, true)) {
                    throw new Exception('Paramètres invalides');
                }
                $stmt = $pdo->prepare("UPDATE orders SET payment_status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$payment_status, $id]);
                echo json_encode(['success' => true, 'message' => 'Paiement mis à jour']);
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


