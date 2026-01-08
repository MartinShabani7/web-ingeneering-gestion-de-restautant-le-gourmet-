<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/security.php';

header('Content-Type: application/json; charset=utf-8');

if (!Security::isLoggedIn() || !Security::isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès refusé']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Vérification seulement pour les actions qui nécessitent une requête AJAX
// $ajaxActions = ['list', 'create', 'update', 'delete', 'toggle_actif', 'toggle_en_avant', 'get'];
// if (in_array($action, $ajaxActions) && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
//     http_response_code(400);
//     echo json_encode(['success' => false, 'message' => 'Requête invalide']);
//     exit;
// }

// Configuration pour l'upload d'images
$uploadDir = '../../uploads/partenaires/';
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
        throw new Exception('L\'image est trop volumineuse (max 2MB)');
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('Type de fichier non autorisé. Formats acceptés: JPEG, PNG, GIF, WebP');
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('partenaire_') . '.' . $extension;
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

try {
    switch ($action) {
        case 'list':
            // Récupération des paramètres de pagination et recherche
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = max(1, (int)($_GET['limit'] ?? 10));
            $offset = ($page - 1) * $limit;
            
            $search = Security::sanitizeInput($_GET['search'] ?? '');
            $status = $_GET['status'] ?? '';
            $featured = $_GET['featured'] ?? '';
            
            // Construction de la requête avec filtres
            $sql = "SELECT * FROM partenaires WHERE 1=1";
            $params = [];
            
            if (!empty($search)) {
                $sql .= " AND (nom LIKE ? OR mail LIKE ? OR contact LIKE ?)";
                $searchTerm = "%$search%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            if ($status !== '') {
                $sql .= " AND est_actif = ?";
                $params[] = (int)$status;
            }
            
            if ($featured !== '') {
                $sql .= " AND est_en_avant = ?";
                $params[] = (int)$featured;
            }
            
            $sql .= " ORDER BY est_en_avant DESC, nom ASC";
            
            // Requête pour le nombre total
            $countSql = "SELECT COUNT(*) FROM partenaires WHERE 1=1";
            $countParams = [];
            
            // Application des mêmes filtres pour le count
            if (!empty($search)) {
                $countSql .= " AND (nom LIKE ? OR mail LIKE ? OR contact LIKE ?)";
                $countParams[] = "%$search%";
                $countParams[] = "%$search%";
                $countParams[] = "%$search%";
            }
            
            if ($status !== '') {
                $countSql .= " AND est_actif = ?";
                $countParams[] = (int)$status;
            }
            
            if ($featured !== '') {
                $countSql .= " AND est_en_avant = ?";
                $countParams[] = (int)$featured;
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

        case 'toggle_actif':
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) { 
                throw new Exception('Token CSRF invalide'); 
            }
            
            $id = (int)($_POST['id'] ?? 0);
            $est_actif = (int)($_POST['est_actif'] ?? 0);
            
            if ($id <= 0) { 
                throw new Exception('ID invalide'); 
            }
            
            $stmt = $pdo->prepare("UPDATE partenaires SET est_actif = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$est_actif, $id]);
            
            echo json_encode(['success' => true, 'message' => 'Statut activé/désactivé mis à jour']);
            break;

        case 'toggle_en_avant':
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) { 
                throw new Exception('Token CSRF invalide'); 
            }
            
            $id = (int)($_POST['id'] ?? 0);
            $est_en_avant = (int)($_POST['est_en_avant'] ?? 0);
            
            if ($id <= 0) { 
                throw new Exception('ID invalide'); 
            }
            
            $stmt = $pdo->prepare("UPDATE partenaires SET est_en_avant = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$est_en_avant, $id]);
            
            echo json_encode(['success' => true, 'message' => 'Statut "en avant" mis à jour']);
            break;

        case 'create':
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) { 
                throw new Exception('Token CSRF invalide'); 
            }
            
            $nom = Security::sanitizeInput($_POST['nom'] ?? '');
            $adresse = Security::sanitizeInput($_POST['adresse'] ?? '');
            $mail = Security::sanitizeInput($_POST['mail'] ?? '');
            $contact = Security::sanitizeInput($_POST['contact'] ?? '');
            $est_actif = isset($_POST['est_actif']) ? 1 : 0;
            $est_en_avant = isset($_POST['est_en_avant']) ? 1 : 0;
            
            if ($nom === '') { 
                throw new Exception('Le nom est requis'); 
            }
            
            // Validation email si fourni
            if (!empty($mail) && !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Format d\'email invalide');
            }
            
            // Gestion de l'image
            $photo = null;
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $photo = handleImageUpload($_FILES['photo'], $uploadDir, $allowedTypes, $maxSize);
            }
            
            $stmt = $pdo->prepare("INSERT INTO partenaires (nom, adresse, mail, contact, photo, est_actif, est_en_avant, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$nom, $adresse, $mail, $contact, $photo, $est_actif, $est_en_avant]);
            
            echo json_encode(['success' => true, 'message' => 'Partenaire ajouté avec succès']);
            break;

        case 'update':
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) { 
                throw new Exception('Token CSRF invalide'); 
            }
            
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { 
                throw new Exception('ID invalide'); 
            }
            
            $nom = Security::sanitizeInput($_POST['nom'] ?? '');
            $adresse = Security::sanitizeInput($_POST['adresse'] ?? '');
            $mail = Security::sanitizeInput($_POST['mail'] ?? '');
            $contact = Security::sanitizeInput($_POST['contact'] ?? '');
            $est_actif = isset($_POST['est_actif']) ? 1 : 0;
            $est_en_avant = isset($_POST['est_en_avant']) ? 1 : 0;
            $delete_photo = isset($_POST['delete_photo']) ? 1 : 0;
            
            if ($nom === '') { 
                throw new Exception('Le nom est requis'); 
            }
            
            // Validation email si fourni
            if (!empty($mail) && !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Format d\'email invalide');
            }
            
            // Récupérer la photo actuelle
            $stmt = $pdo->prepare("SELECT photo FROM partenaires WHERE id = ?");
            $stmt->execute([$id]);
            $currentPhoto = $stmt->fetchColumn();
            
            $photo = $currentPhoto;
            
            // Supprimer la photo si demandé
            if ($delete_photo && $currentPhoto) {
                deleteImage($currentPhoto, $uploadDir);
                $photo = null;
            }
            
            // Gestion de la nouvelle photo
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                // Supprimer l'ancienne photo si elle existe
                if ($currentPhoto) {
                    deleteImage($currentPhoto, $uploadDir);
                }
                $photo = handleImageUpload($_FILES['photo'], $uploadDir, $allowedTypes, $maxSize);
            }
            
            $stmt = $pdo->prepare("UPDATE partenaires SET nom=?, adresse=?, mail=?, contact=?, photo=?, est_actif=?, est_en_avant=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$nom, $adresse, $mail, $contact, $photo, $est_actif, $est_en_avant, $id]);
            
            echo json_encode(['success' => true, 'message' => 'Partenaire mis à jour avec succès']);
            break;

        case 'delete':
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) { 
                throw new Exception('Token CSRF invalide'); 
            }
            
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { 
                throw new Exception('ID invalide'); 
            }
            
            // Récupérer et supprimer la photo
            $stmt = $pdo->prepare("SELECT photo FROM partenaires WHERE id = ?");
            $stmt->execute([$id]);
            $photo = $stmt->fetchColumn();
            
            if ($photo) {
                deleteImage($photo, $uploadDir);
            }
            
            $stmt = $pdo->prepare('DELETE FROM partenaires WHERE id = ?');
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true, 'message' => 'Partenaire supprimé avec succès']);
            break;

        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) { 
                throw new Exception('ID invalide'); 
            }
            
            $stmt = $pdo->prepare("SELECT * FROM partenaires WHERE id = ?");
            $stmt->execute([$id]);
            $partenaire = $stmt->fetch();
            
            if (!$partenaire) {
                throw new Exception('Partenaire non trouvé');
            }
            
            echo json_encode(['success' => true, 'data' => $partenaire]);
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