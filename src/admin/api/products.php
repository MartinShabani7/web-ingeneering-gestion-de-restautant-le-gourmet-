<?php
// controle de toute sortie html ou espace indésirable pour échapper aux erreurs de header already send
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Nettoyer tous les buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Démarre une nouvelle session propre
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/database.php';
require_once '../../config/security.php';

// Fonction helper améliorée
function sendJsonResponse($success, $message = '', $data = null, $httpCode = 200) {
    // Nettoyer tout output
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Définir le header
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($httpCode);
    
    // Construire la réponse
    $response = [
        'success' => (bool)$success,
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    // Ajouter debug en dev
    if (isset($_GET['debug']) || isset($_POST['debug'])) {
        $response['debug'] = [
            'session_id' => session_id(),
            'user_id' => $_SESSION['user_id'] ?? null,
            'user_role' => $_SESSION['user_role'] ?? null,
            'action' => $_GET['action'] ?? $_POST['action'] ?? 'none'
        ];
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    // Terminer proprement
    exit;
}

// VÉRIFICATION SESSION - AVANT TOUT
if (!isset($_SESSION['user_id'])) {
    sendJsonResponse(false, 'Session non initialisée', null, 403);
}

if (!Security::isLoggedIn()) {
    sendJsonResponse(false, 'Utilisateur non connecté', null, 403);
}

if (!Security::isAdmin()) {
    sendJsonResponse(false, 'Accès réservé aux administrateurs', null, 403);
}

// Vérifier requête AJAX
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
    sendJsonResponse(false, 'Requête AJAX uniquement', null, 400);
}

// Vérifier l'action
$action = $_GET['action'] ?? $_POST['action'] ?? '';
if (empty($action)) {
    sendJsonResponse(false, 'Action non spécifiée', null, 400);
}

// Vérifier la connexion DB
try {
    if (!$pdo) {
        throw new Exception("Connexion DB non initialisée");
    }
    $pdo->query("SELECT 1");
} catch (Exception $e) {
    sendJsonResponse(false, 'Erreur base de données: ' . $e->getMessage(), null, 500);
}


$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Configuration pour l'upload d'images
$uploadDir = '../../uploads/products/';
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
        throw new Exception('L\'image est trop volumineuse (max 1MB)');
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('Type de fichier non autorisé. Formats acceptés: JPG, PNG, GIF, WEBP');
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('product_') . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Erreur lors de l\'enregistrement de l\'image');
    }
    
    return 'uploads/products/' . $filename;
}

function deleteImage($filename) {
    if (!empty($filename) && file_exists('../../' . $filename)) {
        unlink('../../' . $filename);
    }
}


// POINT D'ENTRÉE PRINCIPAL DE L'API
try {
    switch ($action) {
        case 'list':
            $search = trim($_GET['search'] ?? '');
            $categoryId = $_GET['category_id'] ?? '';
            $onlyActive = ($_GET['only_active'] ?? '');
            
            // Paramètres de pagination
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = max(1, (int)($_GET['limit'] ?? 10));
            $offset = ($page - 1) * $limit;

            // DEBUG: Log les paramètres
            error_log("Liste produits - Page: $page, Search: $search, Cat: $categoryId");

            // Requête pour les données
            $sql = "SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE 1";
            $params = [];

            if ($search !== '') {
                $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
                $like = "%$search%";
                $params[] = $like;
                $params[] = $like;
            }
            if ($categoryId !== '') {
                $sql .= " AND p.category_id = ?";
                $params[] = (int)$categoryId;
            }
            if ($onlyActive !== '') {
                $sql .= " AND p.is_available = 1";
            }

            $sql .= " ORDER BY p.sort_order ASC, p.created_at DESC";
            
            // Requête pour le comptage total
            $countSql = "SELECT COUNT(*) FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE 1";
            $countParams = [];
            
            if ($search !== '') {
                $countSql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
                $countParams[] = $like;
                $countParams[] = $like;
            }
            if ($categoryId !== '') {
                $countSql .= " AND p.category_id = ?";
                $countParams[] = (int)$categoryId;
            }
            if ($onlyActive !== '') {
                $countSql .= " AND p.is_available = 1";
            }
            
            // Exécuter la requête de comptage
            $stmt = $pdo->prepare($countSql);
            $stmt->execute($countParams);
            $totalCount = $stmt->fetchColumn();
            $totalPages = ceil($totalCount / $limit);
            
            // Ajouter la pagination à la requête principale
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            
            sendJsonResponse(true, '', [
                'data' => $rows,
                'pagination' => [
                    'currentPage' => $page,
                    'totalPages' => $totalPages,
                    'totalCount' => $totalCount,
                    'limit' => $limit
                ]
            ]);
            break;

        case 'create':
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Token CSRF invalide');
            }

            $name = Security::sanitizeInput($_POST['name'] ?? '');
            $description = Security::sanitizeInput($_POST['description'] ?? '');
            $price = (float)($_POST['price'] ?? 0);
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $isAvailable = isset($_POST['is_available']) ? 1 : 0;
            $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
            $sortOrder = (int)($_POST['sort_order'] ?? 0);

            if ($name === '' || $price <= 0) {
                throw new Exception('Nom et prix sont requis');
            }

            $imagePath = null;
            if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $imagePath = handleImageUpload($_FILES['image'], $uploadDir, $allowedTypes, $maxSize);
            }

            $stmt = $pdo->prepare("INSERT INTO products (name, description, price, category_id, image, is_available, is_featured, sort_order, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$name, $description, $price, $categoryId ?: null, $imagePath, $isAvailable, $isFeatured, $sortOrder]);

            sendJsonResponse(true, 'Produit ajouté avec succès', ['id' => $pdo->lastInsertId()]);
            break;

        case 'update':
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Token CSRF invalide');
            }
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { throw new Exception('ID invalide'); }

            $name = Security::sanitizeInput($_POST['name'] ?? '');
            $description = Security::sanitizeInput($_POST['description'] ?? '');
            $price = (float)($_POST['price'] ?? 0);
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $isAvailable = isset($_POST['is_available']) ? 1 : 0;
            $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
            $sortOrder = (int)($_POST['sort_order'] ?? 0);

            if ($name === '' || $price <= 0) {
                throw new Exception('Nom et prix sont requis');
            }

            // Récupérer l'image actuelle
            $stmt = $pdo->prepare("SELECT image FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $currentImage = $stmt->fetchColumn();
            
            $imagePath = $currentImage;

            // Gestion de la nouvelle image
            if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                // Supprimer l'ancienne image si elle existe
                if ($currentImage) {
                    deleteImage($currentImage);
                }
                $imagePath = handleImageUpload($_FILES['image'], $uploadDir, $allowedTypes, $maxSize);
            }

            $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, price = ?, category_id = ?, image = ?, is_available = ?, is_featured = ?, sort_order = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$name, $description, $price, $categoryId ?: null, $imagePath, $isAvailable, $isFeatured, $sortOrder, $id]);

            sendJsonResponse(true, 'Produit modifié avec succès');
            break;

        case 'delete':
            if ($method !== 'POST') { throw new Exception('Méthode invalide'); }
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Token CSRF invalide');
            }
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { throw new Exception('ID invalide'); }

            // Récupérer et supprimer l'image
            $stmt = $pdo->prepare('SELECT image FROM products WHERE id = ?');
            $stmt->execute([$id]);
            $image = $stmt->fetchColumn();
            
            if ($image) {
                deleteImage($image);
            }

            $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
            $stmt->execute([$id]);
            sendJsonResponse(true, 'Produit supprimé avec succès');
            break;

        case 'toggle_availability':
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Token CSRF invalide');
            }
            $id = (int)($_POST['id'] ?? 0);
            $is_available = (int)($_POST['is_available'] ?? 0);
            
            if ($id <= 0) { 
                throw new Exception('ID invalide'); 
            }
            
            $stmt = $pdo->prepare("UPDATE products SET is_available = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$is_available, $id]);
            
            sendJsonResponse(true, 'Disponibilité mise à jour');
            break;

        case 'toggle_featured':
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Token CSRF invalide');
            }
            $id = (int)($_POST['id'] ?? 0);
            $is_featured = (int)($_POST['is_featured'] ?? 0);
            
            if ($id <= 0) { 
                throw new Exception('ID invalide'); 
            }
            
            $stmt = $pdo->prepare("UPDATE products SET is_featured = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$is_featured, $id]);
            
            sendJsonResponse(true, 'Statut mis en avant mis à jour');
            break;
            
        // Pour les exports
        case 'export':
            $format = $_GET['format'] ?? 'excel';
            $search = trim($_GET['search'] ?? '');
            $categoryId = $_GET['category_id'] ?? '';
            $onlyActive = ($_GET['only_active'] ?? '');

            // Récupérer les données avec les mêmes filtres
            $sql = "SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE 1";
            $params = [];

            if ($search !== '') {
                $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
                $like = "%$search%";
                $params[] = $like;
                $params[] = $like;
            }
            if ($categoryId !== '') {
                $sql .= " AND p.category_id = ?";
                $params[] = (int)$categoryId;
            }
            if ($onlyActive !== '') {
                $sql .= " AND p.is_available = 1";
            }

            $sql .= " ORDER BY p.sort_order ASC, p.created_at DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $products = $stmt->fetchAll();

            // // Appeler la fonction d'export appropriée
            // switch ($format) {
            //     case 'excel':
            //         exportToExcel($products);
            //         break;
            //         exit;
            //     case 'csv':
            //         exportToCSV($products);
            //         break;
            //         exit;
            //     case 'pdf':
            //         exportToPDF($products);
            //         break;
            //         exit;
            //     case 'word':
            //         exportToWord($products);
            //         break;
            //         exit;
            //     default:
            //         throw new Exception('Format non supporté');
            // }
            // break;

        default:
            sendJsonResponse(false, 'Action inconnue', null, 400);
    }
} catch (Exception $e) {
    // Nettoyer avant d'envoyer l'erreur
    if (ob_get_level()) {
        ob_clean();
    }
    sendJsonResponse(false, $e->getMessage(), null, 400);
}
// FIN - S'assurer qu'il n'y a rien après
if (ob_get_level()) {
    ob_end_clean();
}
exit;
?>