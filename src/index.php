<?php
ob_start();
session_start();
require_once 'config/database.php';
require_once 'config/security.php';
require_once 'config/mailer_adapeter_contact.php'; // Inclure le mailer
include 'admin/statistique_visiteurs/tracker.php';

// Redirection basée sur le statut de connexion
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] === 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: member/dashboard.php');
    }
    exit();
}

//  Récupérer les services depuis la base de données
try {
    $stmt = $pdo->prepare("SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order ASC LIMIT 3");
    $stmt->execute();
    $services = $stmt->fetchAll();
} catch (PDOException $e) {
    // En cas d'erreur (table non créée), on initialise un tableau vide
    $services = [];
}

// Récupérer les témoignages approuvés
$stmt = $pdo->prepare("
    SELECT t.*, 
           u.first_name, 
           u.last_name, 
           u.avatar
    FROM testimonials t
    INNER JOIN users u ON t.user_id = u.id
    WHERE t.status = 'approved'
    ORDER BY t.created_at DESC
");
$stmt->execute();
$testimonials = $stmt->fetchAll();

// Traitement du formulaire côté serveur
$messageSent = false;
$errorMessage = '';
$formData = []; // Pour stocker temporairement les données

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = htmlspecialchars(trim($_POST['name']));
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $message = htmlspecialchars(trim($_POST['message']));
    
    // Stocker les données pour pré-remplissage en cas d'erreur
    $formData = [
        'name' => $name,
        'email' => $email,
        'message' => $message
    ];
    
    // Validation
    if (empty($name) || empty($email) || empty($message)) {
        $errorMessage = "Veuillez remplir tous les champs obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Veuillez entrer une adresse email valide.";
    } else {
        // Envoyer l'email en utilisant la nouvelle méthode sendContact
        if (Mailer::sendContact($name, $email, $message)) {
            $messageSent = true;
            // Réinitialiser les données du formulaire après envoi réussi
            $formData = [
                'name' => '',
                'email' => '',
                'message' => ''
            ];
        } else {
            $errorMessage = "Une erreur s'est produite lors de l'envoi du message. Veuillez réessayer.";
        }
    }
}

?>
<!-- La barre de navigation -->
<?php include 'include/header.php'; ?>

<!-- styles pour certaines section, je les insere ici car lorsque je les ai inséré dans mon fichier style.css, ça n'a pas capté malgré les efforts -->
<style>
    .hero-section {
        position: relative;
        overflow: hidden;
        padding: 0;
    }
        
    .carousel-item {
        height: 100vh;
        background-size: contain; /* Changé de 'cover' à 'contain' pour éviter le zoom et les coupures */
        background-position: center center;
        background-repeat: no-repeat;
        background-color: #000; /* Fond noir pour les parties non couvertes par l'image */
            
    }
        
    /* Images de fond optimisées pour tous les écrans */
    .carousel-item:nth-child(1) {
        background-image: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('assets/img/hero1.jpg');
    }
        
    .carousel-item:nth-child(2) {
        background-image: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('assets/img/hero2.jpg');
    }
        
    .carousel-item:nth-child(3) {
        background-image: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('assets/img/hero3.jpg');
    }
        
    .carousel-item:nth-child(4) {
        background-image: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('assets/img/hero4.jpg');
    }
        
    .carousel-item:nth-child(5) {
        background-image: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('assets/img/hero5.jpg');
    }
        
    .carousel-item:nth-child(6) {
        background-image: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('assets/img/hero6.jpg');
    }
        
    .hero-content {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        text-align: center;
        width: 100%;
        max-width: 900px;
        padding: 0 20px;
    }
        
    .hero-title {
        font-size: 3.2rem;
        font-weight: 700;
        margin-bottom: 1.5rem;
        text-shadow: 2px 2px 8px rgba(0,0,0,0.7);
         animation: fadeInDown 0.8s ease-out;
    }
        
    .hero-description {
        font-size: 1.3rem;
        margin-bottom: 2.5rem;
        text-shadow: 1px 1px 4px rgba(0,0,0,0.7);
        animation: fadeInUp 0.8s ease-out 0.2s both;
    }
        
    .hero-buttons {
        animation: fadeInUp 0.8s ease-out 0.4s both;
    }
        
    .btn-hero {
        padding: 0.8rem 2rem;
        font-size: 1.2rem;
        border-radius: 50px;
        transition: all 0.3s ease;
    }
        
    .btn-hero:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.2);
    }
        
    .carousel-indicators {
        bottom: 20px;
    }
        
    .carousel-indicators button {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin: 0 5px;
        background-color: rgba(255,255,255,0.5);
        border: none;
    }
        
    .carousel-indicators button.active {
        background-color: #fff;
    }
        
    /* Animations plus rapides */
    @keyframes fadeInDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
        
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
        
    /* Optimisation pour les grands écrans */
    @media (min-width: 1200px) {
        .carousel-item {
            background-size: contain; /* Toujours 'contain' pour éviter les coupures */
            background-position: center center;
        }
            
        .hero-title {
            font-size: 3.5rem;
        }
            
        .hero-description {
            font-size: 1.4rem;
        }
    }
        
    /* Pour les écrans très larges, on peut utiliser une approche différente */
     @media (min-width: 1600px) {
        .carousel-item {
            background-size: cover; /* Sur très grands écrans, on peut utiliser cover */
            background-position: center top; /* Positionner en haut pour montrer la partie intéressante */
        }
    }
        
    /* Responsive pour tablettes */
     @media (max-width: 992px) {
        .carousel-item {
            height: 80vh;
            background-size: contain; /* Toujours 'contain' sur tablettes */
        }
            
        .hero-title {
            font-size: 2.6rem;
        }
            
        .hero-description {
            font-size: 1.1rem;
        }
            
        .btn-hero {
            padding: 0.7rem 1.5rem;
            font-size: 1.1rem;
        }
    }
        
    /* Responsive pour mobiles */
     @media (max-width: 768px) {
        .carousel-item {
            height: 75vh;
            background-size: contain; /* Toujours 'contain' sur mobiles */
            background-position: center center;
        }
            
        .hero-title {
             font-size: 2.2rem;
        }
            
        .hero-description {
            font-size: 1rem;
        }
            
        .btn-hero {
            display: block;
            width: 100%;
            margin-bottom: 1rem;
        }
            
        .hero-buttons .d-flex {
            flex-direction: column;
        }
    }
        
     @media (max-width: 576px) {
        .carousel-item {
            height: 70vh;
            background-size: contain; /* Toujours 'contain' sur petits mobiles */
        }
            
        .hero-title {
            font-size: 1.8rem;
        }
            
        .hero-description {
            font-size: 0.9rem;
        }
            
        .carousel-indicators {
            bottom: 15px;
        }
    }
        
    /* Pour les écrans avec des ratios très différents */
     @media (max-aspect-ratio: 3/4) {
        .carousel-item {
            background-size: contain;
        }
    }

     /* section temoignage */
    .testimonial-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border-radius: 15px;
        overflow: hidden;
    }

    .testimonial-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1) !important;
    }

    .testimonial-avatar {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border: 3px solid #f8f9fa;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }

    .testimonial-avatar-default {
        width: 80px;
        height: 80px;
        font-size: 2rem;
    }

    .rating-stars {
        font-size: 0.9rem;
    }

    .card-text {
        min-height: 80px;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
    }

    /* Styles pour le carrousel */
    .testimonial-carousel {
        position: relative;
        overflow: hidden;
    }

    .testimonial-track {
        display: flex;
        transition: transform 0.5s ease-in-out;
    }

    .testimonial-item {
        flex: 0 0 33.333%;
        padding: 0 15px;
        box-sizing: border-box;
    }

    /* Animation pour les cartes */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .testimonial-card {
        animation: fadeInUp 0.6s ease forwards;
    }

    /* Indicateurs de navigation */
    .carousel-indicators {
        display: flex;
        justify-content: center;
        margin-top: 30px;
        gap: 10px;
    }

    .carousel-indicator {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background-color: #dee2e6;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }

    .carousel-indicator.active {
        background-color: #0d6efd;
    }

    /* Responsive */
    @media (max-width: 992px) {
        .testimonial-item {
            flex: 0 0 50%;
        }
    }

    @media (max-width: 768px) {
        .testimonial-item {
            flex: 0 0 100%;
        }
    }
    
    /* style de l'image de la section réservation */
    .reservation-image-container {
        max-height: 400px;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    .reservation-image {
        max-height: 400px;
        width: auto;
        max-width: 100%;
        object-fit: contain;
     }
    .btn-primary {
         background-color: #8B4513;
        border-color: #8B4513;
    }
    .btn-primary:hover {
        background-color: #A0522D;
        border-color: #A0522D;
    }

    /* section contact */

</style>

    <!-- Hero Section -->
    <section id="accueil" class="hero-section">
        <div id="heroCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="4000" data-bs-wrap="true">
            <!-- Carousel Indicators - 6 indicateurs maintenant -->
            <div class="carousel-indicators">
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="3" aria-label="Slide 4"></button>
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="4" aria-label="Slide 5"></button>
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="5" aria-label="Slide 6"></button>
            </div>
            
            <!-- Carousel Items - 6 slides maintenant -->
            <div class="carousel-inner">
                <!-- Slide 1 -->
                <div class="carousel-item active">
                    <div class="hero-content">
                        <h1 class="hero-title text-white">
                            Bienvenue au Restaurant Le Gourmet
                        </h1>
                        <p class="hero-description text-white">
                            Découvrez une expérience culinaire exceptionnelle dans un cadre chaleureux et moderne. 
                            Notre équipe vous propose une cuisine raffinée avec des produits frais et locaux.
                        </p>
                        <div class="hero-buttons">
                            <div class="d-flex gap-3 justify-content-center flex-wrap">
                                <a href="#menu" class="btn btn-primary btn-hero">
                                    <i class="fas fa-book me-2"></i>Voir le Menu
                                </a>
                                <a href="auth/inscription.php" class="btn btn-outline-light btn-hero">
                                    <i class="fas fa-user-plus me-2"></i>Rejoindre
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Slide 2 -->
                <div class="carousel-item">
                    <div class="hero-content">
                        <h1 class="hero-title text-white">
                            Service Traiteur Élégant
                        </h1>
                        <p class="hero-description text-white">
                            Pour vos événements spéciaux, notre service traiteur vous propose des créations 
                            gastronomiques adaptées à toutes vos occasions. Faites de chaque moment un souvenir délicieux.
                        </p>
                        <div class="hero-buttons">
                            <div class="d-flex gap-3 justify-content-center flex-wrap">
                                <a href="#menu" class="btn btn-primary btn-hero">
                                    <i class="fas fa-book me-2"></i>Voir le Menu
                                </a>
                                <a href="auth/inscription.php" class="btn btn-outline-light btn-hero">
                                    <i class="fas fa-user-plus me-2"></i>Rejoindre
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Slide 3 -->
                <div class="carousel-item">
                    <div class="hero-content">
                        <h1 class="hero-title text-white">
                            Ateliers Culinaires
                        </h1>
                        <p class="hero-description text-white">
                            Apprenez les secrets de notre cuisine avec nos chefs étoilés. 
                            Nos ateliers culinaires vous offrent une expérience unique pour perfectionner vos talents en cuisine.
                        </p>
                        <div class="hero-buttons">
                            <div class="d-flex gap-3 justify-content-center flex-wrap">
                                <a href="#menu" class="btn btn-primary btn-hero">
                                    <i class="fas fa-book me-2"></i>Voir le Menu
                                </a>
                                <a href="auth/inscription.php" class="btn btn-outline-light btn-hero">
                                    <i class="fas fa-user-plus me-2"></i>Rejoindre
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Slide 4 -->
                <div class="carousel-item">
                    <div class="hero-content">
                        <h1 class="hero-title text-white">
                            Événements Privés
                        </h1>
                        <p class="hero-description text-white">
                            Célébrez vos moments spéciaux dans notre espace dédié. Anniversaires, mariages, 
                            réunions d'affaires... Nous créons l'ambiance parfaite pour chaque occasion.
                        </p>
                        <div class="hero-buttons">
                            <div class="d-flex gap-3 justify-content-center flex-wrap">
                                <a href="#menu" class="btn btn-primary btn-hero">
                                    <i class="fas fa-book me-2"></i>Voir le Menu
                                </a>
                                <a href="auth/inscription.php" class="btn btn-outline-light btn-hero">
                                    <i class="fas fa-user-plus me-2"></i>Rejoindre
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Slide 5 -->
                <div class="carousel-item">
                    <div class="hero-content">
                        <h1 class="hero-title text-white">
                            Caves Sélectionnées
                        </h1>
                        <p class="hero-description text-white">
                            Découvrez notre sélection exclusive de vins et spiritueux. Notre sommelier vous 
                            guide pour l'association parfaite entre mets et vins.
                        </p>
                        <div class="hero-buttons">
                            <div class="d-flex gap-3 justify-content-center flex-wrap">
                                <a href="#menu" class="btn btn-primary btn-hero">
                                    <i class="fas fa-book me-2"></i>Voir le Menu
                                </a>
                                <a href="auth/inscription.php" class="btn btn-outline-light btn-hero">
                                    <i class="fas fa-user-plus me-2"></i>Rejoindre
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Slide 6 -->
                <div class="carousel-item">
                    <div class="hero-content">
                        <h1 class="hero-title text-white">
                            Terrasse Jardin
                        </h1>
                        <p class="hero-description text-white">
                            Profitez de nos espaces extérieurs aménagés avec élégance. Une oasis de tranquillité 
                            au cœur de la ville pour déguster nos spécialités en plein air.
                        </p>
                        <div class="hero-buttons">
                            <div class="d-flex gap-3 justify-content-center flex-wrap">
                                <a href="#menu" class="btn btn-primary btn-hero">
                                    <i class="fas fa-book me-2"></i>Voir le Menu
                                </a>
                                <a href="auth/inscription.php" class="btn btn-outline-light btn-hero">
                                    <i class="fas fa-user-plus me-2"></i>Rejoindre
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section service -->
    <section id="services" class="py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center mb-5">
                    <h2 class="display-5 fw-bold text-dark">Nos Services Exclusifs</h2>
                    <p class="lead text-muted">Découvrez l'excellence culinaire à travers nos services sur mesure</p>
                </div>
            </div>
            <!-- Affichez seulement 3 services en avant-première -->
            <div class="row g-4">
                <?php 
                $previewServices = array_slice($services, 0, 3);
                foreach ($previewServices as $service): 
                ?>
                <div class="col-md-4">
                    <div class="card service-card h-100 border-0 shadow-lg">
                        <div class="card-body text-center p-4">
                            <div class="service-icon mb-4">
                                <div class="icon-container bg-<?= $service['background_color'] ?> rounded-circle mx-auto d-flex align-items-center justify-content-center">
                                    <i class="<?= htmlspecialchars($service['icon']) ?> fa-2x text-white"></i>
                                </div>
                            </div>
                            <h5 class="card-title fw-bold text-dark mb-3"><?= htmlspecialchars($service['title']) ?></h5>
                            <p class="card-text text-muted mb-4"><?= htmlspecialchars(substr($service['description'], 0, 100)) ?>...</p>
                            <a href="<?= htmlspecialchars($service['button_link']) ?>" class="btn btn-<?= $service['button_color'] ?> btn-hover">
                                <i class="<?= strpos($service['icon'], 'fa-') !== false ? $service['icon'] : 'fas fa-arrow-right' ?> me-2"></i>
                                <?= htmlspecialchars($service['button_text']) ?>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <!-- Bouton pour voir tous les services -->
            <div class="text-center mt-5">
                <a href="pages/services.php" class="btn btn-outline-primary btn-lg">
                    <i class="fas fa-eye me-2"></i>Voir Tous Nos Services
                </a>
            </div>
        </div>
    </section>

    <!-- Menu Section -->
    <section id="menu" class="py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center mb-5">
                    <h2 class="display-5 fw-bold">Notre Menu</h2>
                    <p class="lead">Découvrez nos spécialités culinaires</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <img src="assets/img/img10_entree2.jpg" class="card-img-top" alt="Entrées">
                        <div class="card-body">
                            <h5 class="card-title">Entrées</h5>
                            <p class="card-text">Foie gras, escargots, salades fraîches...</p>
                            <a href="pages/menu_entree.php" class="btn btn-outline-primary">Voir les entrées</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <img src="assets/img/img2_plat_principaux.jpg" class="card-img-top" alt="Plats">
                        <div class="card-body">
                            <h5 class="card-title">Plats Principaux</h5>
                            <p class="card-text">Viandes, poissons, plats végétariens...</p>
                            <a href="pages/menu_plats_principaux.php" class="btn btn-outline-primary">Voir les plats</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <img src="assets/img/img1_dessert.jpg" class="card-img-top" alt="Desserts">
                        <div class="card-body">
                            <h5 class="card-title">Desserts</h5>
                            <p class="card-text">Pâtisseries maison, glaces artisanales...</p>
                            <a href="pages/menu_dessert.php" class="btn btn-outline-primary">Voir les desserts</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section Reservation -->
    <section id="reservation" class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center mb-5">
                    <h2 class="display-5 fw-bold">Réservez votre table</h2>
                    <p class="lead">Réservez votre table dès maintenant pour une expérience culinaire exceptionnelle.</p>
                </div>
            </div>
            
            <div class="row align-items-center">
                <!-- Colonne pour l'image avec hauteur réduite mais entièrement visible -->
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <div class="image-container reservation-image-container">
                        <img src="https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1170&q=80" 
                             alt="Table de restaurant élégante" 
                             class="rounded shadow reservation-image">
                    </div>
                </div>
                
                <!-- Colonne pour le contenu texte et bouton -->
                <div class="col-lg-6">
                    <div class="reservation-content ps-lg-4">
                        <h3 class="h2 mb-4">Votre expérience gastronomique vous attend</h3>
                        <p class="mb-4">
                            Plongez dans une expérience culinaire unique où chaque détail a été pensé pour votre plaisir. 
                            Notre ambiance chaleureuse, notre service attentif et nos plats raffinés créent des souvenirs 
                            mémorables à chaque visite. Que ce soit pour un dîner romantique, une célébration familiale 
                            ou un repas d'affaires, nous nous engageons à rendre votre moment exceptionnel.
                        </p>
                        <p class="mb-4">
                            Nos tables sont limitées pour garantir la qualité de chaque expérience. Réservez dès maintenant 
                            pour vous assurer de vivre un moment inoubliable dans notre établissement.
                        </p>
                        <a href="pages/tables.php" class="btn btn-primary btn-lg px-4 py-2">
                            Réservez maintenant
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section Partenaire -->
    <section id="partenaire" class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center mb-5">
                    <h2 class="display-5 fw-bold">Nos Partenaires</h2>
                    <p class="lead">Découvrez nos partenaires qui nous accompagnent dans notre mission de servir la meilleure cuisine.</p>
                </div>
            </div>
            
            <div class="partners-wrapper">
                <div class="partners-container" id="partnersContainer">
                    <!-- Les logos des partenaires seront ajoutés ici par JavaScript -->
                </div>
            </div>
            
            <!-- État de chargement -->
            <div id="loadingState" class="loading-spinner">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Chargement...</span>
                </div>
                <p class="mt-2">Chargement des partenaires...</p>
            </div>
            
            <!-- État d'erreur -->
            <div id="errorState" class="error-message" style="display: none;">
                <p>Impossible de charger les partenaires. Veuillez réessayer plus tard.</p>
                <button class="btn btn-primary btn-sm" onclick="chargerPartenaires()">Réessayer</button>
            </div>

            <!-- État vide -->
            <div id="emptyState" class="no-partners" style="display: none;">
                <p>Aucun partenaire disponible pour le moment.</p>
            </div>
        </div>
    </section>

    <!-- Section avis/temoignages -->
    <section id="avis" class="py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center mb-5">
                    <h2 class="display-5 fw-bold">Témoignages</h2>
                    <p class="lead">Découvrez les témoignages de nos clients qui ont partagé leur expérience au Restaurant Le Gourmet.</p>
                </div>
            </div>

            <?php if (!empty($testimonials)): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="testimonial-carousel">
                            <div class="testimonial-track" id="testimonialTrack">
                                <?php foreach ($testimonials as $index => $testimonial): ?>
                                    <div class="testimonial-item" data-index="<?= $index ?>">
                                        <div class="card border-0 shadow-sm h-100">
                                            <div class="card-body text-center p-4">
                                                <!-- Photo de l'utilisateur -->
                                                <div class="mb-3">
                                                    <?php if ($testimonial['avatar']): ?>
                                                        <img src="uploads/avatars/<?= htmlspecialchars($testimonial['avatar']) ?>" 
                                                            alt="<?= htmlspecialchars($testimonial['first_name'] . ' ' . $testimonial['last_name']) ?>" 
                                                            class="testimonial-avatar rounded-circle">
                                                    <?php else: ?>
                                                        <div class="testimonial-avatar-default rounded-circle mx-auto bg-primary d-flex align-items-center justify-content-center">
                                                            <i class="fas fa-user text-white"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- Nom complet -->
                                                <h5 class="card-title mb-3 fw-bold">
                                                    <?= htmlspecialchars($testimonial['first_name'] . ' ' . $testimonial['last_name']) ?>
                                                </h5>

                                                <!-- Commentaire en italique et centré -->
                                                <div class="card-text mb-4">
                                                    <p class="fst-italic text-muted m-0">
                                                        "<?= htmlspecialchars($testimonial['comment']) ?>"
                                                    </p>
                                                </div>

                                                <!-- Footer avec note et date -->
                                                <div class="d-flex justify-content-between align-items-center mt-auto">
                                                    <!-- Date -->
                                                    <small class="text-muted">
                                                        <?= date('d/m/Y', strtotime($testimonial['created_at'])) ?>
                                                    </small>

                                                    <!-- Note (étoiles) -->
                                                    <div class="rating-stars">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star<?= $i <= $testimonial['rating'] ? ' text-warning' : ' text-muted' ?>"></i>
                                                        <?php endfor; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Indicateurs de navigation -->
                            <div class="carousel-indicators" id="carouselIndicators">
                                <?php for ($i = 0; $i < ceil(count($testimonials) / 3); $i++): ?>
                                    <div class="carousel-indicator <?= $i === 0 ? 'active' : '' ?>" data-slide="<?= $i ?>"></div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Message si aucun témoignage -->
                <div class="row">
                    <div class="col-12 text-center">
                        <div class="py-5">
                            <i class="fas fa-star fa-3x text-muted mb-3"></i>
                            <h4 class="text-muted">Aucun témoignage pour le moment</h4>
                            <p class="text-muted">Soyez le premier à partager votre expérience !</p>
                            <a href="auth/login.php" class="btn btn-primary">Laisser un avis</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center mb-5">
                    <h2 class="display-5 fw-bold">Contactez-nous</h2>
                    <p class="lead">Nous sommes là pour vous accueillir</p>
                </div>
            </div>
            
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <!-- Messages de feedback -->
                    <?php if ($messageSent): ?>
                        <div class="alert alert-success fade-in text-center" role="alert" id="successMessage">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Message envoyé avec succès!</strong> Vous aurez une réponse dans le plus bref délais!! Merci.
                            <p class="mb-0 mt-2 small">
                                <i class="fas fa-info-circle me-1"></i>
                                Une copie de confirmation vous a été envoyée à <strong><?php echo htmlspecialchars($email); ?></strong>
                            </p>
                        </div>
                        
                        <script>
                            // Masquer le message après 5 secondes
                            setTimeout(function() {
                                const successMessage = document.getElementById('successMessage');
                                if (successMessage) {
                                    successMessage.style.transition = 'opacity 0.5s';
                                    successMessage.style.opacity = '0';
                                    setTimeout(() => successMessage.style.display = 'none', 500);
                                }
                            }, 5000);
                        </script>
                    <?php elseif ($errorMessage): ?>
                        <div class="alert alert-danger fade-in text-center" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo $errorMessage; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="card border-0 shadow">
                        <div class="card-body p-5">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5>Informations</h5>
                                    <p><i class="fas fa-map-marker-alt text-primary me-2"></i>C/Les Volcans, 75001 Goma</p>
                                    <p><i class="fas fa-phone text-primary me-2"></i>+243 973 900 115</p>
                                    <p><i class="fas fa-envelope text-primary me-2"></i><a href="mailto:martinshabani7@gmail.com" class="text-decoration-none fw-bold">contact@legourmet.fr</a></p>
                                    <p><i class="fas fa-clock text-primary me-2"></i>Lun-Dim: 12h-14h30 / 19h-23h</p>
                                    
                                    <!-- Destination clairement indiquée -->
                                    <div class="alert alert-light border mt-4">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-paper-plane text-primary fa-lg me-3"></i>
                                            <div>
                                                <small class="text-muted d-block">Vos messages seront envoyés à:</small>
                                                <strong>contact@legourmet.fr</strong>
                                                <small class="text-muted d-block mt-1">et une copie vous sera envoyée</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Réseaux sociaux ajoutés -->
                                    <div class="mt-4">
                                        <h6 class="mb-3">Suivez-nous sur les réseaux</h6>
                                        <div class="d-flex gap-3">
                                            <a href="https://web.facebook.com/shabani.wakengela" class="text-decoration-none" title="Facebook" target="_blank">
                                                <i class="fab fa-facebook fa-2x" style="color: #1877F2;"></i>
                                            </a>
                                            <a href="https://www.instagram.com/martin_shabani1/" class="text-decoration-none" title="Instagram" target="_blank">
                                                <i class="fab fa-instagram fa-2x" style="color: #E4405F;"></i>
                                            </a>
                                            <a href="https://x.com/MartinShabani7" class="text-decoration-none" title="Twitter" target="_blank">
                                                <i class="fab fa-twitter fa-2x" style="color: #1DA1F2;"></i>
                                            </a>
                                            <a href="https://www.linkedin.com/in/martin-shabani-187185239/?skipRedirect=true" class="text-decoration-none" title="LinkedIn" target="_blank">
                                                <i class="fab fa-linkedin fa-2x" style="color: #0A66C2;"></i>
                                            </a>
                                            <a href="https://www.tiktok.com/@martin.shabani" class="text-decoration-none" title="TikTok" target="_blank">
                                                <i class="fab fa-tiktok fa-2x" style="color: #000000;"></i>
                                            </a>
                                            <a href="https://www.youtube.com/@martinshabani6812" class="text-decoration-none" title="YouTube" target="_blank">
                                                <i class="fab fa-youtube fa-2x" style="color: #FF0000;"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h5>Envoyez votre message</h5>
                                    <form method="POST" action="" id="contactForm">
                                        <div class="mb-3">
                                            <!-- <label for="name" class="form-label">Nom complet *</label> -->
                                            <input type="text" class="form-control" id="name" name="name" 
                                                placeholder="Votre nom" required 
                                                value="<?php echo htmlspecialchars($formData['name'] ?? ''); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <!-- <label for="email" class="form-label">Email *</label> -->
                                            <input type="email" class="form-control" id="email" name="email" 
                                                placeholder="votre@email.com" required
                                                value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <!-- <label for="message" class="form-label">Message *</label> -->
                                            <textarea class="form-control" id="message" name="message" rows="4" 
                                                    placeholder="Votre message..." required><?php echo htmlspecialchars($formData['message'] ?? ''); ?></textarea>
                                        </div>
                                        <div class="d-grid">
                                            <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                                <i class="fas fa-paper-plane me-2"></i>Envoyer le message
                                            </button>
                                        </div>
                                        <p class="text-muted small mt-2">
                                            <i class="fas fa-shield-alt me-1"></i>
                                            Vos données sont traitées en toute confidentialité.
                                        </p>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<!-- les animations pour les témoignages -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const track = document.getElementById('testimonialTrack');
        const indicators = document.querySelectorAll('.carousel-indicator');
        const items = document.querySelectorAll('.testimonial-item');
        
        if (items.length === 0) return;
        
        const itemsPerSlide = window.innerWidth < 768 ? 1 : window.innerWidth < 992 ? 2 : 3;
        const totalSlides = Math.ceil(items.length / itemsPerSlide);
        let currentSlide = 0;
        let autoSlideInterval;

        function updateIndicators() {
            indicators.forEach((indicator, index) => {
                indicator.classList.toggle('active', index === currentSlide);
            });
        }

        function goToSlide(slideIndex) {
            currentSlide = slideIndex;
            const translateX = -currentSlide * 100;
            track.style.transform = `translateX(${translateX}%)`;
            updateIndicators();
        }

        function nextSlide() {
            currentSlide = (currentSlide + 1) % totalSlides;
            goToSlide(currentSlide);
        }

        // Initialisation des indicateurs
        function initIndicators() {
            const indicatorsContainer = document.getElementById('carouselIndicators');
            indicatorsContainer.innerHTML = '';
            
            for (let i = 0; i < totalSlides; i++) {
                const indicator = document.createElement('div');
                indicator.className = `carousel-indicator ${i === 0 ? 'active' : ''}`;
                indicator.setAttribute('data-slide', i);
                indicator.addEventListener('click', () => {
                    goToSlide(i);
                    resetAutoSlide();
                });
                indicatorsContainer.appendChild(indicator);
            }
        }

        function resetAutoSlide() {
            clearInterval(autoSlideInterval);
            startAutoSlide();
        }

        function startAutoSlide() {
            autoSlideInterval = setInterval(nextSlide, 5000); // 5 secondes
        }

        // Gestion du redimensionnement
        function handleResize() {
            const newItemsPerSlide = window.innerWidth < 768 ? 1 : window.innerWidth < 992 ? 2 : 3;
            const newTotalSlides = Math.ceil(items.length / newItemsPerSlide);
            
            if (newTotalSlides !== totalSlides) {
                currentSlide = 0;
                goToSlide(0);
                initIndicators();
            }
        }

        // Initialisation
        initIndicators();
        startAutoSlide();

        // Événements
        window.addEventListener('resize', handleResize);

        // Pause au survol
        track.addEventListener('mouseenter', () => {
            clearInterval(autoSlideInterval);
        });

        track.addEventListener('mouseleave', () => {
            startAutoSlide();
        });
    });

    // envoi des sms par le formulaire de contact
    document.addEventListener('DOMContentLoaded', function() {
        const contactForm = document.getElementById('contactForm');
        const submitBtn = document.getElementById('submitBtn');
        
        <?php if ($messageSent): ?>
        // Animation de réinitialisation après envoi réussi
        const formInputs = contactForm.querySelectorAll('input, textarea');
        formInputs.forEach(input => {
            input.classList.add('form-reset-animation');
            setTimeout(() => {
                input.classList.remove('form-reset-animation');
            }, 500);
        });
        <?php endif; ?>
        
        // Ajout de couleurs aux icônes des réseaux sociaux au survol
        const socialIcons = document.querySelectorAll('.fa-2x');
        socialIcons.forEach(icon => {
            const originalColor = icon.style.color;
            
            icon.addEventListener('mouseenter', function() {
                if (this.classList.contains('fa-facebook')) {
                    this.style.color = '#1877F2';
                } else if (this.classList.contains('fa-instagram')) {
                    this.style.color = '#E4405F';
                } else if (this.classList.contains('fa-twitter')) {
                    this.style.color = '#1DA1F2';
                } else if (this.classList.contains('fa-linkedin')) {
                    this.style.color = '#0A66C2';
                } else if (this.classList.contains('fa-tiktok')) {
                    this.style.color = '#000000';
                } else if (this.classList.contains('fa-youtube')) {
                    this.style.color = '#FF0000';
                }
            });
            
            icon.addEventListener('mouseleave', function() {
                // Retour à la couleur d'origine
                this.style.color = originalColor || '#212529';
            });
        });
        
        // Optionnel : Confirmation visuelle avant envoi
        contactForm.addEventListener('submit', function(e) {
            // Vous pouvez ajouter une validation JS supplémentaire ici
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Envoi en cours...';
            submitBtn.disabled = true;
            
            // Le formulaire continuera à s'envoyer normalement
            // La réinitialisation sera gérée par PHP
        });
    });
</script>
<script src="assets/js/main.js"></script>

<!-- Footer section -->
<?php 
    include 'include/footer.php'; 
    ob_end_flush();
?>