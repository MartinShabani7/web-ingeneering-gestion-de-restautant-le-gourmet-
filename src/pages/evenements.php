<?php
include '../include/header.php';
include '../admin/statistique_visiteurs/tracker.php';
require_once '../config/database.php';
session_start();

$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 6;
$offset = ($page-1)*$perPage;

// Récupérer les événements
$where = ' WHERE is_active = 1 ';
$params = [];
if ($q !== '') { 
    $where .= ' AND (title LIKE ? OR description LIKE ? OR location LIKE ?) '; 
    $params[] = "%$q%"; 
    $params[] = "%$q%"; 
    $params[] = "%$q%"; 
}

// $cnt = $pdo->prepare("SELECT COUNT(*) c FROM events $where");
// $cnt->execute($params); 
// $total = (int)$cnt->fetch()['c'];

// $sql = "SELECT * FROM events 
//         $where 
//         ORDER BY event_date ASC, created_at DESC 
//         LIMIT $perPage OFFSET $offset";
// $stmt = $pdo->prepare($sql); 
// $stmt->execute($params); 
// $events = $stmt->fetchAll();

// $pages = (int)ceil($total / $perPage);
?>
<div class="container py-5 mt-4">
    <!-- En-tête avec titre et description -->
    <div class="text-center mb-5">
        <h1 class="h2 mb-3 text-primary"><i class="fas fa-calendar-alt me-2"></i>Nos Événements</h1>
        <p class="lead text-muted max-w-800 mx-auto">
            Découvrez nos événements exclusifs et expériences culinaires uniques. 
            Que ce soit pour un mariage, un anniversaire ou un séminaire d'entreprise, 
            nous créons des moments mémorables autour d'une gastronomie d'exception.
        </p>
    </div>

    <!-- Barre de recherche dynamique -->
    <div class="row justify-content-center mb-4">
        <div class="col-md-8">
            <form method="GET" id="searchForm">
                <div class="input-group">
                    <input type="text" 
                           class="form-control" 
                           name="q" 
                           id="searchInput"
                           value="<?= htmlspecialchars($q) ?>" 
                           placeholder="Rechercher un événement..."
                           oninput="debounceSearch()">
                    <span class="input-group-text">
                        <i class="fas fa-search text-muted"></i>
                    </span>
                </div>
            </form>
        </div>
    </div>

    <!-- Affichage des événements -->
    <div class="row g-4">
        <?php if (empty($events)): ?>
            <div class="col-12 text-center text-muted py-5">
                <i class="fas fa-calendar-times fa-3x mb-3"></i>
                <p>Aucun événement trouvé pour votre recherche.</p>
                <?php if ($q): ?>
                    <a href="evenements.php" class="btn btn-outline-primary mt-2">Voir tous les événements</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($events as $event): ?>
                <div class="col-lg-6 col-md-12">
                    <div class="card event-card h-100">
                        <div class="row g-0 h-100">
                            <!-- Image de l'événement -->
                            <div class="col-md-5">
                                <div class="event-image-wrapper">
                                    <?php if ($event['image']): ?>
                                        <img src="../<?= htmlspecialchars($event['image']) ?>" 
                                             class="event-image" 
                                             alt="<?= htmlspecialchars($event['title']) ?>">
                                    <?php else: ?>
                                        <div class="event-image-placeholder">
                                            <i class="fas fa-calendar-alt fa-3x"></i>
                                        </div>
                                    <?php endif; ?>
                                    <!-- Badge de date -->
                                    <div class="event-date-badge">
                                        <div class="event-day"><?= date('d', strtotime($event['event_date'])) ?></div>
                                        <div class="event-month"><?= date('M', strtotime($event['event_date'])) ?></div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Contenu de l'événement -->
                            <div class="col-md-7">
                                <div class="card-body d-flex flex-column h-100">
                                    <div class="mb-3">
                                        <h5 class="card-title text-primary mb-2"><?= htmlspecialchars($event['title']) ?></h5>
                                        
                                        <!-- Informations de l'événement -->
                                        <div class="event-info mb-3">
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas fa-map-marker-alt text-muted me-2"></i>
                                                <small class="text-muted"><?= htmlspecialchars($event['location'] ?? 'Lieu à préciser') ?></small>
                                            </div>
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas fa-clock text-muted me-2"></i>
                                                <small class="text-muted">
                                                    <?= date('H:i', strtotime($event['event_time'] ?? '18:00')) ?> - 
                                                    <?= $event['price'] ? number_format($event['price'], 2) . '€' : 'Sur devis' ?>
                                                </small>
                                            </div>
                                            <?php if ($event['capacity']): ?>
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-users text-muted me-2"></i>
                                                    <small class="text-muted">Jusqu'à <?= $event['capacity'] ?> personnes</small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <p class="card-text event-description">
                                            <?= htmlspecialchars(substr($event['description'] ?? '', 0, 120)) ?>
                                            <?= strlen($event['description'] ?? '') > 120 ? '...' : '' ?>
                                        </p>
                                    </div>
                                    
                                    <div class="mt-auto">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <?php if ($event['available_slots'] > 0): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check me-1"></i>
                                                    <?= $event['available_slots'] ?> places disponibles
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">
                                                    <i class="fas fa-times me-1"></i>
                                                    Complet
                                                </span>
                                            <?php endif; ?>
                                            
                                            <button type="button" 
                                                    class="btn btn-primary btn-sm"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#eventModal<?= $event['id'] ?>"
                                                    <?= $event['available_slots'] <= 0 ? 'disabled' : '' ?>>
                                                <i class="fas fa-ticket-alt me-1"></i>
                                                Commander
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal de réservation -->
                <div class="modal fade" id="eventModal<?= $event['id'] ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Réserver : <?= htmlspecialchars($event['title']) ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <form action="reservation_event.php" method="POST">
                                    <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Nombre de places *</label>
                                        <input type="number" 
                                               class="form-control" 
                                               name="places" 
                                               min="1" 
                                               max="<?= $event['available_slots'] ?>" 
                                               required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Email *</label>
                                        <input type="email" class="form-control" name="email" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Téléphone</label>
                                        <input type="tel" class="form-control" name="phone">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Message spécial</label>
                                        <textarea class="form-control" name="message" rows="3" placeholder="Allergies, demandes particulières..."></textarea>
                                    </div>
                                    
                                    <div class="text-end">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                        <button type="submit" class="btn btn-primary">Confirmer la réservation</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <nav class="mt-5">
        <ul class="pagination justify-content-center">
            <!-- Bouton Précédent -->
            <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?q=<?= urlencode($q) ?>&page=<?= $page-1 ?>">
                        &laquo; Précédent
                    </a>
                </li>
            <?php else: ?>
                <li class="page-item disabled">
                    <span class="page-link">&laquo; Précédent</span>
                </li>
            <?php endif; ?>

            <!-- Pages numérotées -->
            <?php for ($i = 1; $i <= $pages; $i++): ?>
                <li class="page-item <?= $i===$page ? 'active' : '' ?>">
                    <a class="page-link" href="?q=<?= urlencode($q) ?>&page=<?= $i ?>">
                        <?= $i ?>
                    </a>
                </li>
            <?php endfor; ?>

            <!-- Bouton Suivant -->
            <?php if ($page < $pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?q=<?= urlencode($q) ?>&page=<?= $page+1 ?>">
                        Suivant &raquo;
                    </a>
                </li>
            <?php else: ?>
                <li class="page-item disabled">
                    <span class="page-link">Suivant &raquo;</span>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<style>
.max-w-800 {
    max-width: 800px;
}

.event-card {
    transition: transform 0.3s ease;
    border: none;
    box-shadow: 0 3px 15px rgba(0,0,0,0.1);
    overflow: hidden;
}

.event-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 25px rgba(0,0,0,0.15);
}

.event-image-wrapper {
    position: relative;
    height: 100%;
    min-height: 200px;
    overflow: hidden;
    background: #f8f9fa;
}

.event-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.event-card:hover .event-image {
    transform: scale(1.05);
}

.event-image-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.event-date-badge {
    position: absolute;
    top: 15px;
    left: 15px;
    background: rgba(255,255,255,0.95);
    padding: 10px;
    border-radius: 8px;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.event-day {
    font-size: 1.5rem;
    font-weight: bold;
    color: #333;
    line-height: 1;
}

.event-month {
    font-size: 0.8rem;
    color: #666;
    text-transform: uppercase;
    font-weight: 600;
}

.event-info {
    border-left: 3px solid #007bff;
    padding-left: 12px;
}

.event-description {
    font-size: 0.9rem;
    line-height: 1.5;
    color: #666;
}

.card-body {
    padding: 1.5rem;
}

/* Responsive */
@media (max-width: 768px) {
    .event-image-wrapper {
        min-height: 180px;
        height: 180px;
    }
    
    .col-md-5, .col-md-7 {
        width: 100%;
    }
}
</style>

<script>
// Fonction pour la recherche dynamique avec debounce
let searchTimeout;
function debounceSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        document.getElementById('searchForm').submit();
    }, 500);
}

// Focus sur la barre de recherche au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.focus();
    }
});
</script>

<?php include '../include/footer.php'; ?>