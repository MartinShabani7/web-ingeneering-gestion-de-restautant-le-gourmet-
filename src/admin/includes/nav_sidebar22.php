<?php
session_start();

// Chemins absolus utilisant __DIR__
$root_dir = dirname(__DIR__, 2); // Remonte de 2 niveaux depuis admin/includes

require_once $root_dir . '/config/database.php';
require_once $root_dir . '/config/security.php';
require_once $root_dir . '/include/AuthService.php';

// Vérification de l'authentification et des droits admin
if (!Security::isLoggedIn() || !Security::isAdmin()) {
    header('Location: ' . $root_dir . '/auth/login.php');
    exit();
}

// Récupération des données utilisateur 
$user_avatar = $_SESSION['user_avatar'] ?? 'default_avatar.jpg';
$user_name = $_SESSION['user_name'] ?? 'Utilisateur';

// Extraction du prénom et nom depuis user_name
$names = explode(' ', $user_name, 2);
$user_firstname = $names[0] ?? '';
$user_lastname = $names[1] ?? '';
$user_fullname = $user_name;

// Si le nom complet est vide, on utilise une valeur par défaut
if (empty($user_fullname)) {
    $user_fullname = 'Utilisateur';
}

// Définir le chemin de base pour les assets (relatif au dossier admin)
$assets_base = '../..';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord Admin - Restaurant Le Gourmet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Styles pour le sidebar fixe */
        .dashboard-sidebar {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            padding-top: 76px; /* Hauteur de la navbar */
            z-index: 1000;
            overflow-y: auto;
            max-height: 100vh;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        /* Empêcher le scroll sur le sidebar */
        .dashboard-sidebar .position-sticky {
            position: relative !important;
        }

        /* Styles pour les liens du sidebar */
        .dashboard-sidebar .nav-link {
            color: #ffffff !important;
            padding: 12px 20px;
            margin: 4px 10px;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .dashboard-sidebar .nav-link:hover {
            background: rgba(255, 255, 255, 0.15) !important;
            transform: translateX(5px);
            color: #ffffff !important;
        }

        .dashboard-sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.2) !important;
            border-left: 4px solid #4dabf7;
        }

        .dashboard-sidebar .nav-link i {
            width: 20px;
            text-align: center;
            margin-right: 10px;
            color: #e9ecef;
        }

        /* Style pour l'avatar dans le sidebar */
        .dashboard-sidebar .text-center {
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 20px;
            margin-bottom: 20px;
        }

        .dashboard-sidebar .text-white {
            color: #ffffff !important;
            font-weight: 600;
        }

        .dashboard-sidebar .text-muted {
            color: #adb5bd !important;
        }

        /* Navbar fixe */
        .navbar {
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1030;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        /* Ajustement du contenu principal */
        .main-content {
            margin-left: 16.666667%; /* Largeur du sidebar */
            margin-top: 76px; /* Hauteur de la navbar */
            padding: 20px;
            min-height: calc(100vh - 76px);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .dashboard-sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
        }

        /* Scroll personnalisé pour le sidebar */
        .dashboard-sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .dashboard-sidebar::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
        }

        .dashboard-sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
        }

        .dashboard-sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.5);
        }

        /* Correction pour le dropdown de la navbar */
        .navbar-nav .dropdown-menu {
            position: absolute !important;
        }

        /* Animation de chargement */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9998;
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>  
        <!-- Overlay de chargement -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div> 
    
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-utensils me-2"></i>Le Gourmet - Admin
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <!-- Tableau de bord centré -->
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Tableau de bord
                        </a>
                    </li>
                </ul>
                
                <!-- Menu utilisateur à droite -->
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <!-- Avatar de l'utilisateur -->
                            <img src="../../uploads/avatars/<?php echo htmlspecialchars($user_avatar); ?>" 
                                alt="Photo de profil de <?php echo htmlspecialchars($user_fullname); ?>" 
                                class="rounded-circle me-2" width="42" height="42" 
                                onerror="this.src='../../assets/img/default_avatar.jpg'">
                            <span><?php echo htmlspecialchars($user_fullname); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php">
                                <i class="fas fa-user me-2"></i>Mon profil
                            </a></li>
                            <li><a class="dropdown-item" href="settings.php">
                                <i class="fas fa-cog me-2"></i>Paramètres
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../../auth/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <div class="col-md-3 col-lg-2 dashboard-sidebar">
        <div class="position-sticky pt-3">
            <div class="text-center mb-4">
                <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-2 overflow-hidden" style="width: 80px; height: 80px;">
                    <img src="../../uploads/avatars/<?php echo $user_avatar; ?>" 
                        alt="Photo de profil de <?php echo htmlspecialchars($user_fullname); ?>"
                        class="w-100 h-100"
                        style="object-fit: cover;"
                        onerror="this.src='../../assets/img/default_avatar.jpg'">
                </div>
                <h6 class="text-white"><?= htmlspecialchars($_SESSION['user_name']) ?></h6>
                <small class="text-muted">Administrateur</small>
            </div>
            
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="../dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Tableau de bord
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../user.php">
                        <i class="fas fa-users me-2"></i>Utilisateurs
                    </a>
                </li>
                <!-- <li class="nav-item">
                    <a class="nav-link" href="user.php">
                        <i class="fas fa-user-friends me-2"></i>Users
                    </a>
                </li> -->
                <li class="nav-item">
                    <a class="nav-link" href="../products.php">
                        <i class="fas fa-utensils me-2"></i>Produits
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../categories.php">
                        <i class="fas fa-tags me-2"></i>Catégories
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../orders.php">
                        <i class="fas fa-shopping-bag me-2"></i>Commandes
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../reservations.php">
                        <i class="fas fa-calendar me-2"></i>Réservations
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../tables.php">
                        <i class="fas fa-table me-2"></i>Tables
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../partenaires.php">
                        <i class="fas fa-handshake me-2"></i>Partenaires
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../inventory.php">
                        <i class="fas fa-boxes me-2"></i>Stock
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../services.php">
                        <i class="fas fa-concierge-bell me-2"></i>Services
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../temoignages.php">
                        <i class="fas fa-comment me-2"></i>Témoignages
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../rapports/index.php">
                        <i class="fas fa-file-alt me-2"></i>Rapports
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="statistique_visiteurs/dashboard.php">
                        <i class="fas fa-chart-line me-2"></i>Statistique visiteurs
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../settings.php">
                        <i class="fas fa-cog me-2"></i>Paramètres
                    </a>
                </li>
                <!-- Bouton de déconnexion dans le sidebar -->
                <li class="nav-item mt-4">
                    <a class="nav-link text-danger" href="../auth/logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <style>
        #container{
            margin-top: 75px;
            margin-left: 280px;
            position: fixed;
            height: calc(100vh - 70px); 
            
            overflow-y: auto; 
        }
    </style>