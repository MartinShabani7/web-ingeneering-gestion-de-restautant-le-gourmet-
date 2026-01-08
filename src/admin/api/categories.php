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
        error_log("Sortie API categories détectée: " . bin2hex($output));
        throw new Exception("Erreur interne du serveur");
    }
    
    header('Content-Type: application/json; charset=utf-8');
    
    if (!Security::isLoggedIn() || !Security::isAdmin()) {
        throw new Exception('Accès refusé', 403);
    }
    
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
        throw new Exception('Requête invalide', 400);
    }
    
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    switch ($action) {
        case 'list':
            $search = trim($_GET['search'] ?? '');
            $onlyActive = ($_GET['only_active'] ?? '');

            $sql = "SELECT * FROM categories WHERE 1";
            $params = [];

            if ($search !== '') {
                $sql .= " AND (name LIKE ? OR description LIKE ?)";
                $like = "%$search%";
                $params[] = $like;
                $params[] = $like;
            }
            if ($onlyActive !== '') {
                $sql .= " AND is_active = 1";
            }

            $sql .= " ORDER BY sort_order ASC, name ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $rows]);
            break;

        case 'create':
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Token CSRF invalide');
            }

            $name = Security::sanitizeInput($_POST['name'] ?? '');
            $description = Security::sanitizeInput($_POST['description'] ?? '');
            $sortOrder = (int)($_POST['sort_order'] ?? 0);
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($name === '') {
                throw new Exception('Le nom est requis');
            }

            $imagePath = null;
            if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadErrors = Security::validateFileUpload($_FILES['image']);
                if (!empty($uploadErrors)) {
                    throw new Exception(implode(' | ', $uploadErrors));
                }
                $dir = '../../uploads/categories/';
                if (!is_dir($dir)) { mkdir($dir, 0755, true); }
                $fileName = Security::generateSecureFileName($_FILES['image']['name']);
                if (!move_uploaded_file($_FILES['image']['tmp_name'], $dir . $fileName)) {
                    throw new Exception("Échec de l'upload de l'image");
                }
                $imagePath = 'uploads/categories/' . $fileName;
            }

            $stmt = $pdo->prepare("INSERT INTO categories (name, description, image, sort_order, is_active, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$name, $description, $imagePath, $sortOrder, $isActive]);

            echo json_encode(['success' => true, 'message' => 'Catégorie ajoutée']);
            break;

        case 'update':
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Token CSRF invalide');
            }
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { throw new Exception('ID invalide'); }

            $name = Security::sanitizeInput($_POST['name'] ?? '');
            $description = Security::sanitizeInput($_POST['description'] ?? '');
            $sortOrder = (int)($_POST['sort_order'] ?? 0);
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($name === '') {
                throw new Exception('Le nom est requis');
            }

            $imageClause = '';
            $params = [$name, $description, $sortOrder, $isActive, $id];

            if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadErrors = Security::validateFileUpload($_FILES['image']);
                if (!empty($uploadErrors)) {
                    throw new Exception(implode(' | ', $uploadErrors));
                }
                $dir = '../../uploads/categories/';
                if (!is_dir($dir)) { mkdir($dir, 0755, true); }
                $fileName = Security::generateSecureFileName($_FILES['image']['name']);
                if (!move_uploaded_file($_FILES['image']['tmp_name'], $dir . $fileName)) {
                    throw new Exception("Échec de l'upload de l'image");
                }
                $newImage = 'uploads/categories/' . $fileName;

                $old = $pdo->prepare('SELECT image FROM categories WHERE id = ?');
                $old->execute([$id]);
                if ($row = $old->fetch()) {
                    if ($row['image'] && file_exists('../../' . $row['image'])) {
                        @unlink('../../' . $row['image']);
                    }
                }

                $imageClause = ', image = ?';
                array_splice($params, 3, 0, [$newImage]);
            }

            $sql = "UPDATE categories SET name = ?, description = ?, sort_order = ?" . $imageClause . ", is_active = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            echo json_encode(['success' => true, 'message' => 'Catégorie mise à jour']);
            break;

        case 'delete':
            if ($method !== 'POST') { throw new Exception('Méthode invalide'); }
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Token CSRF invalide');
            }
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { throw new Exception('ID invalide'); }

            $old = $pdo->prepare('SELECT image FROM categories WHERE id = ?');
            $old->execute([$id]);
            if ($row = $old->fetch()) {
                if ($row['image'] && file_exists('../../' . $row['image'])) {
                    @unlink('../../' . $row['image']);
                }
            }

            $stmt = $pdo->prepare('DELETE FROM categories WHERE id = ?');
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Catégorie supprimée']);
            break;

        default:
            throw new Exception('Action inconnue', 400);
    }
    
} catch (Exception $e) {
    // Nettoyer tout buffer restant
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    http_response_code(is_int($e->getCode()) && $e->getCode() >= 400 ? $e->getCode() : 400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}