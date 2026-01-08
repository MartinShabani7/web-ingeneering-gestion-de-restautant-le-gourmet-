<?php
ob_start(); // Capture toute sortie
ini_set('display_errors', 0); // Désactiver l'affichage des erreurs
error_reporting(E_ALL); // Mais les capturer


session_start();
require_once '../../config/database.php';
require_once '../../config/security.php';

header('Content-Type: application/json; charset=utf-8');

// Nettoyer tout buffer potentiel
while (ob_get_level()) {
    ob_end_clean();
}

// Vérifier si des erreurs/warnings ont été capturés
$output = ob_get_contents();
if (!empty($output)) {
    // Enregistrer pour débogage
    error_log("Sortie non-voulue avant JSON: " . $output);
}
ob_clean();

// TESTS DE CONNEXION - ajoute ces lignes
try {
    if (!isset($pdo) || !$pdo) {
        throw new Exception("Connexion DB non initialisée");
    }
    // Test simple de la connexion
    $pdo->query("SELECT 1");
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur DB: ' . $e->getMessage(),
        'debug' => 'Connexion DB échouée'
    ]);
    exit;
}

// Vérifier la session
if (!Security::isLoggedIn() || !Security::isAdmin()) {
    echo json_encode([
        'success' => false, 
        'message' => 'Accès refusé',
        'debug' => 'Session: ' . ($_SESSION['user_id'] ?? 'non connecté')
    ]);
    exit;
}

// if (!Security::isLoggedIn() || !Security::isAdmin()) {
//     http_response_code(403);
//     echo json_encode(['success' => false, 'message' => 'Accès refusé']);
//     exit;
// }

if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Requête invalide']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// DEBUG: log l'action demandée
error_log("API Users - Action: " . $action);

try {
    switch ($action) {
        case 'list':
            handleList();
            break;
        case 'get':
            handleGet();
            break;
        case 'create':
            handleCreate();
            break;
        case 'update':
            handleUpdate();
            break;
        case 'toggle_active':
            handleToggleActive();
            break;
        case 'reset_password':
            handleResetPassword();
            break;
        case 'upload_avatar':
            handleUploadAvatar();
            break;
        case 'delete_avatar':
            handleDeleteAvatar();
            break;
        case 'delete':
            handleDelete();
            break;
            default:
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'message' => 'Action inconnue: ' . $action,
                'debug' => 'Actions disponibles: list, get, create, update, etc.'
            ]);
    }
} catch (Exception $e) {
    // Nettoyer toute sortie potentielle
    if (ob_get_level()) ob_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

function handleList() {
    global $pdo;
    
    $role = $_GET['role'] ?? '';
    $search = trim($_GET['search'] ?? '');
    $is_active = $_GET['is_active'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min(50, (int)($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;
    
    $sql = "SELECT id, email, first_name, last_name, phone, role, is_active, avatar, created_at, last_login 
            FROM users WHERE 1";
    $params = [];
    
    if ($role !== '') { 
        $sql .= " AND role = ?"; 
        $params[] = $role; 
    }
    if ($is_active !== '') { 
        $sql .= " AND is_active = ?"; 
        $params[] = $is_active; 
    }
    if ($search !== '') {
        $sql .= " AND (email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
        $like = "%$search%"; 
        array_push($params, $like, $like, $like);
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    array_push($params, $limit, $offset);
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    $countSql = "SELECT COUNT(*) FROM users WHERE 1";
    $countParams = [];
    
    if ($role !== '') { 
        $countSql .= " AND role = ?"; 
        $countParams[] = $role; 
    }
    if ($is_active !== '') { 
        $countSql .= " AND is_active = ?"; 
        $countParams[] = $is_active; 
    }
    if ($search !== '') {
        $countSql .= " AND (email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
        $like = "%$search%"; 
        array_push($countParams, $like, $like, $like);
    }
    
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $totalUsers = $countStmt->fetchColumn();
    $totalPages = ceil($totalUsers / $limit);

    $basePath = '';

    foreach ($users as &$user) {
        if ($user['avatar']) {
            $user['avatar_url'] = '/uploads/avatars/' . $user['avatar'];
        } else {
            $user['avatar_url'] = '/assets/img/default_avatar.jpg';
        }
    }
    
    echo json_encode([
        'success' => true, 
        'data' => $users,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_users' => $totalUsers,
            'users_per_page' => $limit,
            'has_previous' => $page > 1,
            'has_next' => $page < $totalPages
        ]
    ]);
}

function handleGet() {
    global $pdo;
    
    $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) throw new Exception('ID utilisateur invalide');
    
    $stmt = $pdo->prepare("SELECT id, email, first_name, last_name, phone, address, role, is_active, avatar, created_at, last_login FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    if (!$user) throw new Exception('Utilisateur non trouvé');

    $basePath = '';
    
    $user['avatar_url'] = $user['avatar'] ? 
         '/uploads/avatars/' . $user['avatar'] : 
         '/assets/img/default_avatar.jpg';
    
    echo json_encode(['success' => true, 'data' => $user]);
}

function handleCreate() {
    global $pdo;
    
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) { 
        throw new Exception('Token CSRF invalide'); 
    }
    
    $email = Security::sanitizeInput($_POST['email'] ?? '');
    $first_name = Security::sanitizeInput($_POST['first_name'] ?? '');
    $last_name = Security::sanitizeInput($_POST['last_name'] ?? '');
    $phone = Security::sanitizeInput($_POST['phone'] ?? '');
    $address = Security::sanitizeInput($_POST['address'] ?? '');
    $role = $_POST['role'] ?? 'customer';
    $password = $_POST['password'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Gestion de l'upload d'avatar
    $avatarPath = null;
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        // $uploadDir = '../../uploads/avatars/'; 
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/avatars/';

        
        // Créer le dossier s'il n'existe pas
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExtension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $fileName = uniqid() . '_' . time() . '.' . $fileExtension;
        $uploadFile = $uploadDir . $fileName;
        
        // Validation du fichier
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxFileSize = 1 * 1024 * 1024; // 1MB
        
        if (!in_array($_FILES['avatar']['type'], $allowedTypes)) {
            throw new Exception('Type de fichier non autorisé. Formats acceptés: JPEG, PNG, GIF, WebP');
        }
        
        if ($_FILES['avatar']['size'] > $maxFileSize) {
            throw new Exception('Le fichier est trop volumineux. Taille maximum: 1MB');
        }
        
        // Déplacer le fichier uploadé
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadFile)) {
            // $avatarPath = $uploadFile;
            $avatarPath = $fileName; // Stocker uniquement le nom de fichier
            
        } else {
            throw new Exception('Erreur lors de l\'upload du fichier');
        }
    }
    
    if (!$email || !$first_name || !$last_name || !$password) { 
        throw new Exception('Tous les champs obligatoires doivent être remplis'); 
    }
    if (!Security::validateEmail($email)) { 
        throw new Exception('Email invalide'); 
    }
    if (!Security::validatePassword($password)) { 
        throw new Exception('Le mot de passe doit contenir au moins 8 caractères avec une majuscule, une minuscule et un chiffre'); 
    }
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        throw new Exception('Cet email est déjà utilisé');
    }
    
    $hash = Security::hashPassword($password);
    
    // Requête SQL pour l'insertion de l'utilisateur dans la base de donnée
    $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, first_name, last_name, phone, address, avatar, role, is_active, email_verified, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");
    $stmt->execute([$email, $hash, $first_name, $last_name, $phone, $address, $avatarPath, $role, $is_active]);
    
    $userId = $pdo->lastInsertId();
    
    echo json_encode(['success' => true, 'message' => 'Utilisateur créé avec succès', 'user_id' => $userId]);
}


function handleUpdate() {
    global $pdo;
    
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) { 
        throw new Exception('Token CSRF invalide'); 
    }
    
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) throw new Exception('ID utilisateur invalide');
    
    $stmt = $pdo->prepare("SELECT id, avatar FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) {
        throw new Exception('Utilisateur non trouvé');
    }
    
    $email = Security::sanitizeInput($_POST['email'] ?? '');
    $first_name = Security::sanitizeInput($_POST['first_name'] ?? '');
    $last_name = Security::sanitizeInput($_POST['last_name'] ?? '');
    $phone = Security::sanitizeInput($_POST['phone'] ?? '');
    $address = Security::sanitizeInput($_POST['address'] ?? '');
    $role = $_POST['role'] ?? 'customer';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Gestion de l'upload d'avatar
    $avatarPath = $user['avatar']; // Conserver l'ancien avatar par défaut
    
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        // $uploadDir = '../../uploads/avatars/';
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/avatars/';

        
        // Créer le dossier s'il n'existe pas
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExtension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $fileName = uniqid() . '_' . time() . '.' . $fileExtension;
        $uploadFile = $uploadDir . $fileName;
        
        // Validation du fichier
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxFileSize = 1 * 1024 * 1024; // 1MB
        
        if (!in_array($_FILES['avatar']['type'], $allowedTypes)) {
            throw new Exception('Type de fichier non autorisé. Formats acceptés: JPEG, PNG, GIF, WebP');
        }
        
        if ($_FILES['avatar']['size'] > $maxFileSize) {
            throw new Exception('Le fichier est trop volumineux. Taille maximum: 5MB');
        }
        
        // Déplacer le fichier uploadé
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadFile)) {
            // Supprimer l'ancien avatar s'il existe
            // if (!empty($user['avatar']) && file_exists($user['avatar'])) {
            //     unlink($user['avatar']);
            // }
            if (!empty($user['avatar'])) {
                $oldAvatarPath = '../../uploads/avatars/' . $user['avatar'];
                if (file_exists($oldAvatarPath)) {
                    unlink($oldAvatarPath);
                }
            }
            // $avatarPath = $uploadFile;
            $avatarPath = $fileName; // Stocker uniquement le nom de fichier
        } else {
            throw new Exception('Erreur lors de l\'upload du fichier');
        }
    } elseif (isset($_POST['remove_avatar']) && $_POST['remove_avatar'] === '1') {
        // Supprimer l'avatar si demandé
        if (!empty($user['avatar']) && file_exists($user['avatar'])) {
            unlink($user['avatar']);
        }
        $avatarPath = null;
    }
    
    if (!$email || !$first_name || !$last_name) { 
        throw new Exception('Tous les champs obligatoires doivent être remplis'); 
    }
    if (!Security::validateEmail($email)) { 
        throw new Exception('Email invalide'); 
    }
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $id]);
    if ($stmt->fetch()) {
        throw new Exception('Cet email est déjà utilisé par un autre utilisateur');
    }
    
    $stmt = $pdo->prepare("UPDATE users SET email = ?, first_name = ?, last_name = ?, phone = ?, address = ?, avatar = ?, role = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$email, $first_name, $last_name, $phone, $address, $avatarPath, $role, $is_active, $id]);
    
    if (!empty($_POST['password'])) {
        $password = $_POST['password'];
        if (!Security::validatePassword($password)) { 
            throw new Exception('Le mot de passe doit contenir au moins 8 caractères avec une majuscule, une minuscule et un chiffre'); 
        }
        $hash = Security::hashPassword($password);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$hash, $id]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Utilisateur mis à jour avec succès']);
}

function handleToggleActive() {
    global $pdo;
    
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) { 
        throw new Exception('Token CSRF invalide'); 
    }
    
    $id = (int)($_POST['id'] ?? 0); 
    $active = (int)($_POST['is_active'] ?? 0);
    if ($id <= 0) { throw new Exception('ID invalide'); }
    
    $stmt = $pdo->prepare('UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$active ? 1 : 0, $id]);
    
    echo json_encode(['success' => true, 'message' => 'Statut mis à jour']);
}

function handleResetPassword() {
    global $pdo;
    
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) { 
        throw new Exception('Token CSRF invalide'); 
    }
    
    $id = (int)($_POST['id'] ?? 0);
    $password = $_POST['password'] ?? '';
    if ($id <= 0 || !$password) { throw new Exception('Paramètres invalides'); }
    
    if (!Security::validatePassword($password)) { 
        throw new Exception('Mot de passe faible'); 
    }
    
    $hash = Security::hashPassword($password);
    $stmt = $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$hash, $id]);
    
    echo json_encode(['success' => true, 'message' => 'Mot de passe réinitialisé']);
}

function handleUploadAvatar() {
    global $pdo;
    
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) { 
        throw new Exception('Token CSRF invalide'); 
    }
    
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { 
        throw new Exception('ID utilisateur invalide'); 
    }
    
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'Fichier trop volumineux',
            UPLOAD_ERR_FORM_SIZE => 'Fichier trop volumineux',
            UPLOAD_ERR_PARTIAL => 'Upload partiel',
            UPLOAD_ERR_NO_FILE => 'Aucun fichier',
            UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant',
            UPLOAD_ERR_CANT_WRITE => 'Erreur d\'écriture',
            UPLOAD_ERR_EXTENSION => 'Extension non autorisée'
        ];
        $error = $_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE;
        throw new Exception($errorMessages[$error] ?? 'Erreur lors du téléchargement');
    }
    
    if ($_FILES['avatar']['size'] === 0) {
        throw new Exception('Le fichier est vide');
    }
    
    $avatarFilename = handleAvatarUpload($_FILES['avatar'], $id);
    
    $stmt = $pdo->prepare('SELECT avatar FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $oldAvatar = $stmt->fetchColumn();
    
    if ($oldAvatar) {
        $oldPath = '../../uploads/avatars/' . $oldAvatar;
        if (file_exists($oldPath)) {
            unlink($oldPath);
        }
    }
    
    $stmt = $pdo->prepare('UPDATE users SET avatar = ?, updated_at = NOW() WHERE id = ?');
    $result = $stmt->execute([$avatarFilename, $id]);
    
    if (!$result) {
        throw new Exception('Erreur lors de la mise à jour en base de données');
    }
    
    $basePath = '';
    
    echo json_encode([
        'success' => true, 
        'message' => 'Avatar mis à jour',
        'avatar_url' => '/uploads/avatars/' . $avatarFilename
    ]);
}

function handleAvatarUpload($file, $userId) {
    
    // $uploadDir = '../../uploads/avatars/';
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/web-ingeneering/uploads/avatars/';

        // Activer l'affichage des erreurs temporairement
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
    
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Impossible de créer le dossier de destination');
        }
    }
    
    if (!is_writable($uploadDir)) {
        throw new Exception('Le dossier de destination n\'est pas accessible en écriture');
    }
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 2 * 1024 * 1024;
    
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Type de fichier non autorisé. Formats acceptés: JPEG, PNG, GIF, WebP');
    }
    
    if ($file['size'] > $maxSize) {
        throw new Exception('Fichier trop volumineux. Taille maximum: 2MB');
    }
    
    $imageInfo = getimagesize($file['tmp_name']);
    if (!$imageInfo) {
        throw new Exception('Le fichier n\'est pas une image valide');
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    $validExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($extension, $validExtensions)) {
        throw new Exception('Extension de fichier non autorisée');
    }
    
    if ($extension === 'jpeg') $extension = 'jpg';
    
    $filename = 'avatar_' . $userId . '_' . uniqid() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Erreur lors de l\'enregistrement du fichier');
    }
    
    if (!file_exists($filepath)) {
        throw new Exception('Le fichier n\'a pas été enregistré correctement');
    }
    
    resizeImage($filepath, 200, 200);
    
    return $filename;
}

function handleDeleteAvatar() {
    global $pdo;
    
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) { 
        throw new Exception('Token CSRF invalide'); 
    }
    
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { throw new Exception('ID utilisateur invalide'); }
    
    $stmt = $pdo->prepare('SELECT avatar FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $avatar = $stmt->fetchColumn();
    
    if ($avatar) {
        $avatarPath = '../../uploads/avatars/' . $avatar;
        if (file_exists($avatarPath)) {
            unlink($avatarPath);
        }
    }
    
    $stmt = $pdo->prepare('UPDATE users SET avatar = NULL, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true, 'message' => 'Avatar supprimé']);
}

function handleDelete() {
    global $pdo;
    
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) { 
        throw new Exception('Token CSRF invalide'); 
    }
    
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) throw new Exception('ID utilisateur invalide');
    
    if ($id == $_SESSION['user_id']) {
        throw new Exception('Vous ne pouvez pas supprimer votre propre compte');
    }
    
    $stmt = $pdo->prepare('SELECT avatar FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $avatar = $stmt->fetchColumn();
    
    if ($avatar) {
        $avatarPath = '../../uploads/avatars/' . $avatar;
        if (file_exists($avatarPath)) {
            unlink($avatarPath);
        }
    }
    
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Utilisateur non trouvé');
    }
    
    echo json_encode(['success' => true, 'message' => 'Utilisateur supprimé avec succès']);
}

function resizeImage($filepath, $maxWidth, $maxHeight) {
    $imageInfo = getimagesize($filepath);
    if (!$imageInfo) return false;
    
    list($width, $height, $type) = $imageInfo;
    
    $ratio = $width / $height;
    if ($maxWidth / $maxHeight > $ratio) {
        $newWidth = $maxHeight * $ratio;
        $newHeight = $maxHeight;
    } else {
        $newWidth = $maxWidth;
        $newHeight = $maxWidth / $ratio;
    }
    
    switch ($type) {
        case IMAGETYPE_JPEG:
            $source = imagecreatefromjpeg($filepath);
            break;
        case IMAGETYPE_PNG:
            $source = imagecreatefrompng($filepath);
            break;
        case IMAGETYPE_GIF:
            $source = imagecreatefromgif($filepath);
            break;
        case IMAGETYPE_WEBP:
            $source = imagecreatefromwebp($filepath);
            break;
        default:
            return false;
    }
    
    $destination = imagecreatetruecolor($newWidth, $newHeight);
    
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagecolortransparent($destination, imagecolorallocatealpha($destination, 0, 0, 0, 127));
        imagealphablending($destination, false);
        imagesavealpha($destination, true);
    }
    
    imagecopyresampled($destination, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    switch ($type) {
        case IMAGETYPE_JPEG:
            imagejpeg($destination, $filepath, 90);
            break;
        case IMAGETYPE_PNG:
            imagepng($destination, $filepath, 9);
            break;
        case IMAGETYPE_GIF:
            imagegif($destination, $filepath);
            break;
        case IMAGETYPE_WEBP:
            imagewebp($destination, $filepath, 90);
            break;
    }
    
    imagedestroy($source);
    imagedestroy($destination);
    
    return true;
}
?>