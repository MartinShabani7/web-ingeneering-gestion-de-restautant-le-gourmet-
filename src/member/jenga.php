<?php
session_start();
require_once '../config/database.php';
require_once '../config/security.php';

// Vérification de l'authentification
if (!Security::isLoggedIn()) {
    Security::redirect('../auth/login.php');
}

// Récupération des informations utilisateur
$userId = $_SESSION['user_id'];

// Initialisation des variables pour éviter les erreurs "Undefined variable"
$errors = isset($errors) ? $errors : [];
$success = isset($success) ? $success : false;

$stmt = $pdo->prepare("
    SELECT u.*, 
           COUNT(DISTINCT o.id) as total_orders,
           COUNT(DISTINCT r.id) as total_reservations,
           SUM(o.total_amount) as total_spent
    FROM users u
    LEFT JOIN orders o ON u.id = o.customer_id
    LEFT JOIN reservations r ON u.id = r.customer_id
    WHERE u.id = ?
    GROUP BY u.id
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Vérification supplémentaire de sécurité
if (!$user) {
    // Si l'utilisateur n'existe pas dans la base
    session_destroy();
    header('Location: ../auth/login.php');
    exit();
}

// Fonctions utilitaires pour les avatars
function getAvatarUrl($avatarData) {
    if (!$avatarData) {
        return '/assets/img/default_avatar.jpg';
    }
    
    // Nettoyer le chemin : supprimer le préfixe en double si présent
    $cleanAvatar = $avatarData;
    
    // Si le chemin commence déjà par uploads/avatars/, l'utiliser tel quel
    if (strpos($cleanAvatar, 'uploads/avatars/') === 0) {
        return '/' . $cleanAvatar;
    }
    
    // Supprimer tout préfixe uploads/avatars/ supplémentaire
    $cleanAvatar = preg_replace('/^uploads\/avatars\//', '', $cleanAvatar);
    $cleanAvatar = preg_replace('/^\/?uploads\/avatars\//', '', $cleanAvatar);
    
    // Retourner le chemin nettoyé
    return '/uploads/avatars/' . $cleanAvatar;
}

function getAvatarPath($avatarData) {
    if (!$avatarData) {
        return null;
    }
    
    // Nettoyer le chemin comme dans getAvatarUrl
    $cleanAvatar = $avatarData;
    
    // Si le chemin commence déjà par uploads/avatars/, l'utiliser tel quel
    if (strpos($cleanAvatar, 'uploads/avatars/') === 0) {
        return $_SERVER['DOCUMENT_ROOT'] . '/' . $cleanAvatar;
    }
    
    // Supprimer tout préfixe uploads/avatars/ supplémentaire
    $cleanAvatar = preg_replace('/^uploads\/avatars\//', '', $cleanAvatar);
    $cleanAvatar = preg_replace('/^\/?uploads\/avatars\//', '', $cleanAvatar);
    
    // Retourner le chemin physique nettoyé
    return $_SERVER['DOCUMENT_ROOT'] . '/uploads/avatars/' . $cleanAvatar;
}

// function resizeImage($filepath, $maxWidth, $maxHeight) {
//     $imageInfo = getimagesize($filepath);
//     if (!$imageInfo) return false;
    
//     list($width, $height, $type) = $imageInfo;
    
//     // Calculer les nouvelles dimensions
//     $ratio = $width / $height;
//     if ($maxWidth / $maxHeight > $ratio) {
//         $newWidth = $maxHeight * $ratio;
//         $newHeight = $maxHeight;
//     } else {
//         $newWidth = $maxWidth;
//         $newHeight = $maxWidth / $ratio;
//     }
    
//     // Créer l'image source selon le type
//     switch ($type) {
//         case IMAGETYPE_JPEG:
//             $source = imagecreatefromjpeg($filepath);
//             break;
//         case IMAGETYPE_PNG:
//             $source = imagecreatefrompng($filepath);
//             break;
//         case IMAGETYPE_GIF:
//             $source = imagecreatefromgif($filepath);
//             break;
//         case IMAGETYPE_WEBP:
//             $source = imagecreatefromwebp($filepath);
//             break;
//         default:
//             return false;
//     }
    
//     // Créer l'image de destination
//     $destination = imagecreatetruecolor($newWidth, $newHeight);
    
//     // Gérer la transparence pour PNG et GIF
//     if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
//         imagecolortransparent($destination, imagecolorallocatealpha($destination, 0, 0, 0, 127));
//         imagealphablending($destination, false);
//         imagesavealpha($destination, true);
//     }
    
//     // Redimensionner
//     imagecopyresampled($destination, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
//     // Sauvegarder selon le type
//     switch ($type) {
//         case IMAGETYPE_JPEG:
//             imagejpeg($destination, $filepath, 90);
//             break;
//         case IMAGETYPE_PNG:
//             imagepng($destination, $filepath, 9);
//             break;
//         case IMAGETYPE_GIF:
//             imagegif($destination, $filepath);
//             break;
//         case IMAGETYPE_WEBP:
//             imagewebp($destination, $filepath, 90);
//             break;
//     }
    
//     // Libérer la mémoire
//     imagedestroy($source);
//     imagedestroy($destination);
    
//     return true;
// }

function resizeImage($filepath, $maxWidth, $maxHeight) {
    $imageInfo = getimagesize($filepath);
    if (!$imageInfo) return false;
    
    list($width, $height, $type) = $imageInfo;
    
    // Calculer les nouvelles dimensions avec conversion explicite
    $ratio = $width / $height;
    if ($maxWidth / $maxHeight > $ratio) {
        $newWidth = (int) round($maxHeight * $ratio);
        $newHeight = (int) $maxHeight;
    } else {
        $newWidth = (int) $maxWidth;
        $newHeight = (int) round($maxWidth / $ratio);
    }
    
    // Créer l'image source selon le type
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
    
    // Créer l'image de destination avec les dimensions converties en int
    $destination = imagecreatetruecolor($newWidth, $newHeight);
    
    // Gérer la transparence pour PNG et GIF
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagecolortransparent($destination, imagecolorallocatealpha($destination, 0, 0, 0, 127));
        imagealphablending($destination, false);
        imagesavealpha($destination, true);
    }
    
    // Redimensionner avec les dimensions entières
    imagecopyresampled($destination, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    // Sauvegarder selon le type
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
    
    // Libérer la mémoire
    imagedestroy($source);
    imagedestroy($destination);
    
    return true;
}

// DEBUG: Générer l'URL d'avatar pour le debug
$userAvatarUrl = getAvatarUrl($user['avatar'] ?? '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon profil - Restaurant Le Gourmet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .dashboard-sidebar {
            background-color: #2c3e50;
            min-height: calc(100vh - 56px); /* Hauteur de la navbar */
            height: 100%;
            position: fixed;
            top: 56px; /* Hauteur de la navbar */
            left: 0;
            overflow-y: auto;
            z-index: 1000;
        }
        
        .dashboard-main-content {
            margin-left: 16.666667%; /* Largeur de la sidebar col-md-3 */
            padding-top: 20px;
            min-height: calc(100vh - 56px);
        }
        
        @media (max-width: 767.98px) {
            .dashboard-sidebar {
                position: static;
                min-height: auto;
                width: 100%;
            }
            
            .dashboard-main-content {
                margin-left: 0;
            }
        }
        
        .nav-link {
            color: #ecf0f1;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 5px;
        }
        
        .nav-link:hover, .nav-link.active {
            background-color: #34495e;
            color: white;
        }
        
        .nav-link.active {
            background-color: #3498db;
        }
        
        .nav-link.text-danger:hover {
            background-color: rgba(231, 76, 60, 0.2);
        }
        
        .avatar-img {
            object-fit: cover;
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="overflow-x-hidden">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-utensils me-2"></i>Le Gourmet
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <!-- Dropdown utilisateur -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php if ($user['avatar']): ?>
                                <img src="<?= htmlspecialchars($userAvatarUrl) ?>" 
                                     alt="Avatar" 
                                     class="rounded-circle avatar-img me-2" 
                                     width="32" 
                                     height="32"
                                     onerror="this.onerror=null; this.src='/assets/img/default_avatar.jpg';">
                            <?php else: ?>
                                <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                    <i class="fas fa-user text-white" style="font-size: 0.8rem;"></i>
                                </div>
                            <?php endif; ?>
                            <span class="d-none d-md-inline">
                                <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li>
                                <a class="dropdown-item" href="dashboard.php">
                                    <i class="fas fa-tachometer-alt me-2"></i>Tableau de bord
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="perfil.php">
                                    <i class="fas fa-user me-2"></i>Mon profil
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="commandes.php">
                                    <i class="fas fa-shopping-bag me-2"></i>Mes commandes
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="reservations.php">
                                    <i class="fas fa-calendar me-2"></i>Mes réservations
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="../auth/logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 dashboard-sidebar">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <?php if ($user['avatar']): ?>
                            <img src="<?= htmlspecialchars($userAvatarUrl) ?>" 
                                 alt="Avatar" 
                                 class="rounded-circle avatar-img mb-2" 
                                 width="80" 
                                 height="80"
                                 onerror="this.onerror=null; this.src='/assets/img/default_avatar.jpg';">
                        <?php else: ?>
                            <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 80px; height: 80px;">
                                <i class="fas fa-user fa-2x text-white"></i>
                            </div>
                        <?php endif; ?>
                        <h6 class="text-white"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h6>
                        <small class="text-muted"><?= htmlspecialchars($user['email']) ?></small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Tableau de bord
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="perfil.php">
                                <i class="fas fa-user me-2"></i>Mon profil
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="commandes.php">
                                <i class="fas fa-shopping-bag me-2"></i>Mes commandes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reservations.php">
                                <i class="fas fa-calendar me-2"></i>Mes réservations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="favorites.php">
                                <i class="fas fa-heart me-2"></i>Mes favoris
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="temoignage.php">
                                <i class="fas fa-star me-2"></i>Mes avis
                            </a>
                        </li>
                        <li class="nav-item mt-4">
                            <a class="nav-link text-danger" href="../auth/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Contenu principal -->
            <!-- <div class="col-md-9 col-lg-10 dashboard-main-content"> -->
                <!-- Ici sera inclus le contenu de perfil.php -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- <script src="../assets/js/main.js"></script> -->
    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.parentNode.querySelector('button');
            const icon = button.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Prévisualisation de l'avatar avant upload
        document.getElementById('avatar').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Vérifier la taille
                if (file.size > 2 * 1024 * 1024) {
                    alert('Le fichier est trop volumineux (max 2MB)');
                    this.value = '';
                    return;
                }
                
                // Vérifier le type
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Type de fichier non autorisé');
                    this.value = '';
                    return;
                }
                
                // Afficher la prévisualisation
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Mettre à jour toutes les images d'avatar sur la page
                    document.querySelectorAll('img[alt="Avatar"]').forEach(img => {
                        img.src = e.target.result;
                    });
                };
                reader.readAsDataURL(file);
            }
        });

        // Validation du formulaire d'avatar
        document.getElementById('avatarForm').addEventListener('submit', function(e) {
            const fileInput = document.getElementById('avatar');
            const file = fileInput.files[0];
            
            if (!file) {
                e.preventDefault();
                alert('Veuillez sélectionner une photo');
                return;
            }
            
            // Vérifier la taille
            if (file.size > 2 * 1024 * 1024) {
                e.preventDefault();
                alert('Le fichier est trop volumineux (max 2MB)');
                fileInput.value = '';
                return;
            }
            
            // Vérifier le type
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                e.preventDefault();
                alert('Type de fichier non autorisé. Formats acceptés: JPEG, PNG, GIF, WebP');
                fileInput.value = '';
                return;
            }
        });
        
        // Gestion d'erreur des images
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('img[alt="Avatar"]').forEach(img => {
                img.onerror = function() {
                    console.log('Erreur de chargement de l\'avatar:', this.src);
                    this.src = '/assets/img/default_avatar.jpg';
                };
            });
        });
    </script>
