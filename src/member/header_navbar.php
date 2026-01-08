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
                                <img src="../uploads/avatars/<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar" class="rounded-circle me-2" width="32" height="32">
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
                            <img src="../uploads/avatars/<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar" class="rounded-circle mb-2" width="80" height="80">
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
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'profilee.php' ? 'active' : '' ?>" href="profilee.php">
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
        </div>
    </div>

