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
        error_log("Sortie API tables détectée: " . bin2hex($output));
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

    // Configuration pour l'upload d'images
    $uploadDir = '../../uploads/tables/';
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 1 * 1024 * 1024; // 1MB

    // Créer le dossier s'il n'existe pas
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    function handleImageUpload($file, $uploadDir, $allowedTypes, $maxSize) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Erreur lors du téléchargement de l\'image');
        }
        
        if ($file['size'] > $maxSize) {
            throw new Exception('L\'image est trop volumineuse (max 5MB)');
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            throw new Exception('Type de fichier non autorisé');
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('table_') . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('Erreur lors de l\'enregistrement de l\'image');
        }
        
        return $filename;
    }

    function deleteImage($filename, $uploadDir) {
        if (!empty($filename) && file_exists($uploadDir . $filename)) {
            unlink($uploadDir . $filename);
        }
    }

        switch ($action) {
            case 'list':
                // Récupération des paramètres de pagination et recherche
                $page = max(1, (int)($_GET['page'] ?? 1));
                $limit = max(1, (int)($_GET['limit'] ?? 10));
                $offset = ($page - 1) * $limit;
                
                $search = Security::sanitizeInput($_GET['search'] ?? '');
                $availability = $_GET['availability'] ?? '';
                $capacity = $_GET['capacity'] ?? '';
                
                // Construction de la requête avec filtres
                $sql = "SELECT * FROM tables WHERE 1=1";
                $params = [];
                
                if (!empty($search)) {
                    $sql .= " AND (table_name LIKE ? OR location LIKE ?)";
                    $searchTerm = "%$search%";
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                }
                
                if ($availability !== '') {
                    $sql .= " AND is_available = ?";
                    $params[] = (int)$availability;
                }
                
                if (!empty($capacity)) {
                    switch ($capacity) {
                        case '1-2':
                            $sql .= " AND capacity BETWEEN 1 AND 2";
                            break;
                        case '3-4':
                            $sql .= " AND capacity BETWEEN 3 AND 4";
                            break;
                        case '5-6':
                            $sql .= " AND capacity BETWEEN 5 AND 6";
                            break;
                        case '7+':
                            $sql .= " AND capacity >= 7";
                            break;
                    }
                }
                
                $sql .= " ORDER BY table_name ASC";
                
                // Requête pour le nombre total
                $countSql = "SELECT COUNT(*) FROM tables WHERE 1=1";
                $countParams = [];
                
                // Application des mêmes filtres pour le count
                if (!empty($search)) {
                    $countSql .= " AND (table_name LIKE ? OR location LIKE ?)";
                    $countParams[] = "%$search%";
                    $countParams[] = "%$search%";
                }
                
                if ($availability !== '') {
                    $countSql .= " AND is_available = ?";
                    $countParams[] = (int)$availability;
                }
                
                if (!empty($capacity)) {
                    switch ($capacity) {
                        case '1-2':
                            $countSql .= " AND capacity BETWEEN 1 AND 2";
                            break;
                        case '3-4':
                            $countSql .= " AND capacity BETWEEN 3 AND 4";
                            break;
                        case '5-6':
                            $countSql .= " AND capacity BETWEEN 5 AND 6";
                            break;
                        case '7+':
                            $countSql .= " AND capacity >= 7";
                            break;
                    }
                }
                
                $stmt = $pdo->prepare($countSql);
                $stmt->execute($countParams);
                $totalCount = $stmt->fetchColumn();
                $totalPages = ceil($totalCount / $limit);
                
                // Requête pour les données paginées
                $sql .= " LIMIT ? OFFSET ?";
                $params[] = $limit;
                $params[] = $offset;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $data = $stmt->fetchAll();
                
                echo json_encode([
                    'success' => true, 
                    'data' => $data,
                    'pagination' => [
                        'currentPage' => $page,
                        'totalPages' => $totalPages,
                        'totalCount' => $totalCount,
                        'limit' => $limit
                    ]
                ]);
                break;

            // Pour activer ou désactiver la disponibilité d'une table
            case 'toggle_availability':
                if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) { 
                    throw new Exception('Token CSRF invalide'); 
                }
                
                $id = (int)($_POST['id'] ?? 0);
                $is_available = (int)($_POST['is_available'] ?? 0);
                
                if ($id <= 0) { 
                    throw new Exception('ID invalide'); 
                }
                
                $stmt = $pdo->prepare("UPDATE tables SET is_available = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$is_available, $id]);
                
                echo json_encode(['success' => true, 'message' => 'Disponibilité mise à jour']);
                break;

            case 'create':
                if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) { 
                    throw new Exception('Token CSRF invalide'); 
                }
                
                $table_name = Security::sanitizeInput($_POST['table_name'] ?? '');
                $capacity = (int)($_POST['capacity'] ?? 0);
                $location = Security::sanitizeInput($_POST['location'] ?? '');
                $is_available = isset($_POST['is_available']) ? 1 : 0;
                
                if ($table_name === '' || $capacity <= 0) { 
                    throw new Exception('Champs requis manquants'); 
                }
                
                // Gestion de l'image
                $image = null;
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $image = handleImageUpload($_FILES['image'], $uploadDir, $allowedTypes, $maxSize);
                }
                
                $stmt = $pdo->prepare("INSERT INTO tables (table_name, capacity, location, image, is_available, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$table_name, $capacity, $location, $image, $is_available]);
                
                echo json_encode(['success' => true, 'message' => 'Table ajoutée']);
                break;

            case 'update':
                if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) { 
                    throw new Exception('Token CSRF invalide'); 
                }
                
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) { 
                    throw new Exception('ID invalide'); 
                }
                
                $table_name = Security::sanitizeInput($_POST['table_name'] ?? '');
                $capacity = (int)($_POST['capacity'] ?? 0);
                $location = Security::sanitizeInput($_POST['location'] ?? '');
                $is_available = isset($_POST['is_available']) ? 1 : 0;
                $delete_image = isset($_POST['delete_image']) ? 1 : 0;
                
                if ($table_name === '' || $capacity <= 0) { 
                    throw new Exception('Champs requis manquants'); 
                }
                
                // Récupérer l'image actuelle
                $stmt = $pdo->prepare("SELECT image FROM tables WHERE id = ?");
                $stmt->execute([$id]);
                $currentImage = $stmt->fetchColumn();
                
                $image = $currentImage;
                
                // Supprimer l'image si demandé
                if ($delete_image && $currentImage) {
                    deleteImage($currentImage, $uploadDir);
                    $image = null;
                }
                
                // Gestion de la nouvelle image
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    // Supprimer l'ancienne image si elle existe
                    if ($currentImage) {
                        deleteImage($currentImage, $uploadDir);
                    }
                    $image = handleImageUpload($_FILES['image'], $uploadDir, $allowedTypes, $maxSize);
                }
                
                $stmt = $pdo->prepare("UPDATE tables SET table_name=?, capacity=?, location=?, image=?, is_available=?, updated_at = NOW() WHERE id=?");
                $stmt->execute([$table_name, $capacity, $location, $image, $is_available, $id]);
                
                echo json_encode(['success' => true, 'message' => 'Table mise à jour']);
                break;

            case 'delete':
                if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) { 
                    throw new Exception('Token CSRF invalide'); 
                }
                
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) { 
                    throw new Exception('ID invalide'); 
                }
                
                // Récupérer et supprimer l'image
                $stmt = $pdo->prepare("SELECT image FROM tables WHERE id = ?");
                $stmt->execute([$id]);
                $image = $stmt->fetchColumn();
                
                if ($image) {
                    deleteImage($image, $uploadDir);
                }
                
                $stmt = $pdo->prepare('DELETE FROM tables WHERE id = ?');
                $stmt->execute([$id]);
                
                echo json_encode(['success' => true, 'message' => 'Table supprimée']);
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