<?php
session_start();
require_once '../config/database.php';
require_once '../config/security.php';

// Vérification de l'authentification
if (!Security::isLoggedIn()) {
    Security::redirect('../auth/login.php');
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

// Récupération des informations utilisateur
$userId = $_SESSION['user_id'];
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

// Générer l'URL d'avatar nettoyée
$userAvatarUrl = getAvatarUrl($user['avatar'] ?? '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Restaurant Le Gourmet</title>
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
        
        /* Styles pour les images d'avatar */
        .avatar-img {
            object-fit: cover;
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .avatar-img:hover {
            opacity: 0.9;
            transition: opacity 0.2s;
        }
    </style>
</head>
<body>
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
                                <a class="dropdown-item" href="profile.php">
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
            <!-- Sidebar fixe -->
            <div class="col-md-3 col-lg-2 dashboard-sidebar px-0">
                <div class="pt-3">
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
                    
                    <ul class="nav flex-column px-3">
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Tableau de bord
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : '' ?>" href="profile.php">
                                <i class="fas fa-user me-2"></i>Mon profil
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'commandes.php' ? 'active' : '' ?>" href="commandes.php">
                                <i class="fas fa-shopping-bag me-2"></i>Mes commandes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'reservations.php' ? 'active' : '' ?>" href="reservations.php">
                                <i class="fas fa-calendar me-2"></i>Mes réservations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'favorites.php' ? 'active' : '' ?>" href="favorites.php">
                                <i class="fas fa-heart me-2"></i>Mes favoris
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'temoignage.php' ? 'active' : '' ?>" href="temoignage.php">
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
            <div class="col-md-9 col-lg-10 dashboard-main-content">
                <div class="container-fluid py-4">
                    <!-- Contenu du tableau de bord -->
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-3 mb-4 border-bottom">
                        <h1 class="h2">Tableau de bord</h1>
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <div class="btn-group me-2">
                                <a href="commandes.php" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-plus me-1"></i>Nouvelle commande
                                </a>
                                <a href="reservations.php" class="btn btn-sm btn-outline-success">
                                    <i class="fas fa-calendar-plus me-1"></i>Nouvelle réservation
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Cartes de statistiques -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="card-title mb-0">Commandes</h6>
                                            <h2 class="mb-0"><?= htmlspecialchars($user['total_orders'] ?? 0) ?></h2>
                                        </div>
                                        <i class="fas fa-shopping-bag fa-2x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="card-title mb-0">Réservations</h6>
                                            <h2 class="mb-0"><?= htmlspecialchars($user['total_reservations'] ?? 0) ?></h2>
                                        </div>
                                        <i class="fas fa-calendar fa-2x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card bg-warning text-dark">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="card-title mb-0">Dépenses totales</h6>
                                            <h2 class="mb-0"><?= htmlspecialchars(number_format($user['total_spent'] ?? 0, 2, ',', ' ')) ?> €</h2>
                                        </div>
                                        <i class="fas fa-euro-sign fa-2x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="card-title mb-0">Membre depuis</h6>
                                            <h5 class="mb-0">
                                                <?php 
                                                if ($user['created_at']) {
                                                    $date = new DateTime($user['created_at']);
                                                    echo htmlspecialchars($date->format('d/m/Y'));
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </h5>
                                        </div>
                                        <i class="fas fa-user-clock fa-2x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Dernières activités -->
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-history me-2"></i>Dernières activités
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php
                                    // Récupérer les dernières activités
                                    $stmt = $pdo->prepare("
                                        SELECT action, created_at 
                                        FROM activity_logs 
                                        WHERE user_id = ? 
                                        ORDER BY created_at DESC 
                                        LIMIT 5
                                    ");
                                    $stmt->execute([$userId]);
                                    $activities = $stmt->fetchAll();
                                    
                                    if (empty($activities)): ?>
                                        <div class="text-center py-4 text-muted">
                                            <i class="fas fa-info-circle fa-2x mb-3"></i>
                                            <p>Aucune activité récente</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="list-group list-group-flush">
                                            <?php foreach ($activities as $activity): ?>
                                                <div class="list-group-item border-0 px-0">
                                                    <div class="d-flex justify-content-between">
                                                        <div>
                                                            <i class="fas fa-circle text-success me-2" style="font-size: 0.5rem;"></i>
                                                            <span class="text-capitalize">
                                                                <?php 
                                                                $actionLabels = [
                                                                    'profile_updated' => 'Profil mis à jour',
                                                                    'password_changed' => 'Mot de passe modifié',
                                                                    'order_created' => 'Commande créée',
                                                                    'reservation_created' => 'Réservation créée'
                                                                ];
                                                                echo htmlspecialchars($actionLabels[$activity['action']] ?? $activity['action']);
                                                                ?>
                                                            </span>
                                                        </div>
                                                        <small class="text-muted">
                                                            <?php 
                                                            $date = new DateTime($activity['created_at']);
                                                            echo htmlspecialchars($date->format('d/m/Y H:i'));
                                                            ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-bolt me-2"></i>Actions rapides
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <a href="commandes.php?action=new" class="btn btn-primary btn-lg">
                                            <i class="fas fa-shopping-cart me-2"></i>Commander
                                        </a>
                                        <a href="reservations.php?action=new" class="btn btn-success btn-lg">
                                            <i class="fas fa-calendar-check me-2"></i>Réserver une table
                                        </a>
                                        <a href="profile.php" class="btn btn-outline-primary">
                                            <i class="fas fa-user-edit me-2"></i>Modifier mon profil
                                        </a>
                                        <a href="favorites.php" class="btn btn-outline-warning">
                                            <i class="fas fa-heart me-2"></i>Voir mes favoris
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Gestion d'erreur des images d'avatar
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.avatar-img').forEach(img => {
                img.onerror = function() {
                    console.log('Erreur de chargement de l\'avatar:', this.src);
                    this.src = '/assets/img/default_avatar.jpg';
                };
            });
        });
        
        // Gestion du responsive de la sidebar
        function updateSidebar() {
            const sidebar = document.querySelector('.dashboard-sidebar');
            const content = document.querySelector('.dashboard-main-content');
            
            if (window.innerWidth <= 768) {
                sidebar.style.position = 'static';
                sidebar.style.width = '100%';
                content.style.marginLeft = '0';
            } else {
                sidebar.style.position = 'fixed';
                sidebar.style.width = '16.666667%';
                content.style.marginLeft = '16.666667%';
            }
        }
        
        // Initialisation et écouteur de redimensionnement
        updateSidebar();
        window.addEventListener('resize', updateSidebar);
    </script>
