<?php
include '../include/header.php';
include '../admin/statistique_visiteurs/tracker.php';
require_once '../config/database.php';

// Récupérer tous les services actifs
$stmt = $pdo->prepare("SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order ASC");
$stmt->execute();
$services = $stmt->fetchAll();
?>
<div class="container py-5 mt-4">
    <!-- En-tête -->
    <div class="text-center mb-5">
        <h1 class="h2 mb-3 text-primary"><i class="fas fa-concierge-bell me-2"></i>Nos Services Premium</h1>
        <p class="lead text-muted max-w-800 mx-auto">
            Découvrez l'ensemble de nos services conçus pour transformer chaque instant en une expérience 
            culinaire exceptionnelle. De la gastronomie raffinée aux événements sur mesure, nous mettons 
            tout en œuvre pour satisfaire vos attentes les plus exigeantes.
        </p>
    </div>

    <!-- Barre de recherche dynamique -->
    <div class="row justify-content-center mb-5">
        <div class="col-md-8">
            <div class="input-group">
                <input type="text" 
                       class="form-control" 
                       id="serviceSearch"
                       placeholder="Rechercher un service...">
                <span class="input-group-text bg-primary text-white">
                    <i class="fas fa-search"></i>
                </span>
            </div>
        </div>
    </div>

    <!-- Affichage des services -->
    <div class="row g-4" id="servicesContainer">
        <?php if (empty($services)): ?>
            <div class="col-12 text-center text-muted py-5">
                <i class="fas fa-concierge-bell fa-3x mb-3"></i>
                <p>Aucun service disponible pour le moment.</p>
            </div>
        <?php else: ?>
            <?php foreach ($services as $service): ?>
                <div class="col-xl-4 col-lg-6 service-item">
                    <div class="card service-card h-100 border-0 shadow-lg">
                        <div class="card-body text-center p-4">
                            <div class="service-icon mb-4">
                                <div class="icon-container bg-<?= $service['background_color'] ?> rounded-circle mx-auto d-flex align-items-center justify-content-center">
                                    <i class="<?= htmlspecialchars($service['icon']) ?> fa-2x text-white"></i>
                                </div>
                            </div>
                            <h5 class="card-title fw-bold text-dark mb-3"><?= htmlspecialchars($service['title']) ?></h5>
                            <p class="card-text text-muted mb-4">
                                <?= htmlspecialchars($service['description']) ?>
                            </p>
                            <a href="<?= htmlspecialchars($service['button_link']) ?>" 
                               class="btn btn-<?= $service['button_color'] ?> btn-hover <?= in_array($service['button_color'], ['warning', 'info', 'purple', 'dark']) ? 'text-white' : '' ?>">
                                <i class="<?= strpos($service['icon'], 'fa-') !== false ? $service['icon'] : 'fas fa-arrow-right' ?> me-2"></i>
                                <?= htmlspecialchars($service['button_text']) ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Call to Action -->
    <div class="text-center mt-5">
        <div class="card bg-gradient-primary text-white border-0 shadow">
            <div class="card-body py-5">
                <h3 class="h2 mb-3">Prêt à vivre l'expérience ?</h3>
                <p class="lead mb-4">Contactez-nous pour personnaliser votre service selon vos besoins</p>
                <a href="contact.php" class="btn btn-light btn-lg">
                    <i class="fas fa-phone me-2"></i>Nous Contacter
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.max-w-800 {
    max-width: 800px;
}

.service-card {
    transition: all 0.3s ease;
    border-radius: 15px;
    overflow: hidden;
}

.service-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.15) !important;
}

.icon-container {
    width: 80px;
    height: 80px;
    transition: transform 0.3s ease;
}

.service-card:hover .icon-container {
    transform: scale(1.1);
}

.btn-hover {
    transition: all 0.3s ease;
    border-radius: 25px;
    padding: 10px 25px;
    font-weight: 600;
}

.btn-hover:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.bg-purple {
    background: linear-gradient(135deg, #6f42c1, #e83e8c) !important;
}

.btn-purple {
    background: linear-gradient(135deg, #6f42c1, #e83e8c) !important;
    border: none;
}

.bg-gradient-primary {
    background: linear-gradient(135deg, #007bff, #0056b3) !important;
}

/* Animation d'apparition */
.service-item {
    animation: fadeInUp 0.6s ease forwards;
    opacity: 0;
    transform: translateY(30px);
}

@keyframes fadeInUp {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive */
@media (max-width: 768px) {
    .service-card {
        margin-bottom: 1.5rem;
    }
    
    .icon-container {
        width: 70px;
        height: 70px;
    }
}
</style>

<script>
// Recherche dynamique des services
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('serviceSearch');
    const serviceItems = document.querySelectorAll('.service-item');
    
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        
        serviceItems.forEach(item => {
            const title = item.querySelector('.card-title').textContent.toLowerCase();
            const description = item.querySelector('.card-text').textContent.toLowerCase();
            
            if (title.includes(searchTerm) || description.includes(searchTerm)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    });
    
    // Focus sur la barre de recherche
    searchInput.focus();
});
</script>

<?php include '../include/footer.php'; ?>