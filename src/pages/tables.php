<?php
include '../include/header.php';
include '../admin/statistique_visiteurs/tracker.php';
require_once '../config/database.php';
require_once '../config/security.php';

$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 9; 
$offset = ($page-1)*$perPage;

$where = ' WHERE 1 ';
$params = [];
if ($q !== '') { 
    $where .= ' AND (table_name LIKE ? OR location LIKE ?) '; 
    $params[] = "%$q%"; 
    $params[] = "%$q%"; 
}

$cnt = $pdo->prepare("SELECT COUNT(*) c FROM tables $where");
$cnt->execute($params); 
$total = (int)$cnt->fetch()['c'];

$sql = "SELECT * FROM tables $where ORDER BY table_name ASC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql); 
$stmt->execute($params); 
$rows = $stmt->fetchAll();
?>
<div class="container py-4 mt-4">
    <!-- En-tête avec titre et description -->
    <div class="row mb-4">
        <div class="col-lg-8 mx-auto text-center">
            <h1 class="display-5 fw-bold text-primary mb-3">Nos Tables Exceptionnelles</h1>
            <p class="lead text-muted">
                Découvrez notre sélection de tables soigneusement aménagées pour vous offrir 
                une expérience culinaire unique. Chaque espace a été conçu pour allier confort, 
                élégance et intimité.
            </p>
        </div>
    </div>

    <!-- Barre de recherche dynamique -->
    <div class="row mb-4">
        <div class="col-lg-6 mx-auto">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0">
                    <i class="fas fa-search text-muted"></i>
                </span>
                <input type="text" 
                       class="form-control border-start-0" 
                       id="searchInput" 
                       placeholder="Rechercher une table par nom ou emplacement..." 
                       value="<?= htmlspecialchars($q) ?>">
            </div>
            <!-- <div class="form-text text-center">La recherche se fait automatiquement au fur et à mesure</div> -->
        </div>
    </div>

    <!-- Affichage des tables en cartes -->
    <div class="row g-4" id="tablesContainer">
        <?php if (empty($rows)): ?>
            <div class="col-12 text-center text-muted py-5">
                <i class="fas fa-search fa-3x mb-3"></i>
                <h4>Aucune table trouvée</h4>
                <p>Essayez de modifier vos critères de recherche</p>
            </div>
        <?php else: ?>
            <?php foreach ($rows as $t): ?>
                <div class="col-xl-4 col-lg-6 col-md-6">
                    <div class="card h-100 shadow-sm border-0 table-card">
                        <!-- Image de la table -->
                        <div class="card-img-top-container position-relative overflow-hidden" style="height: 200px;">
                            <?php if (!empty($t['image'])): ?>
                                <img src="../uploads/tables/<?= htmlspecialchars($t['image']) ?>" 
                                     class="card-img-top h-100 object-fit-cover" 
                                     alt="Table <?= htmlspecialchars($t['table_name']) ?>">
                            <?php else: ?>
                                <div class="card-img-top h-100 bg-light d-flex align-items-center justify-content-center">
                                    <i class="fas fa-chair fa-3x text-muted"></i>
                                </div>
                            <?php endif; ?>
                            <!-- Badge de disponibilité -->
                            <div class="position-absolute top-0 end-0 m-2">
                                <span class="badge <?= $t['is_available'] ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= $t['is_available'] ? 'Disponible' : 'Occupée' ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="card-body d-flex flex-column">
                            <!-- Nom de la table -->
                            <h5 class="card-title text-primary mb-2">
                                Table <?= htmlspecialchars($t['table_name']) ?>
                            </h5>
                            
                            <!-- Description et détails -->
                            <div class="card-text mb-3 flex-grow-1">
                                <p class="text-muted small mb-2">
                                    Table <?= htmlspecialchars($t['location'] ?? 'au restaurant') ?> 
                                    offrant une capacité de <?= (int)$t['capacity'] ?> personne<?= (int)$t['capacity'] > 1 ? 's' : '' ?>.
                                    Parfaite pour <?= (int)$t['capacity'] <= 2 ? 'un dîner romantique' : ((int)$t['capacity'] <= 4 ? 'un repas en famille' : 'les grandes occasions') ?>.
                                </p>
                                <div class="d-flex justify-content-between text-sm text-muted">
                                    <span><i class="fas fa-users me-1"></i> <?= (int)$t['capacity'] ?> pers.</span>
                                    <span><i class="fas fa-map-marker-alt me-1"></i> <?= htmlspecialchars($t['location'] ?? 'Salon principal') ?></span>
                                </div>
                            </div>
                            
                            <!-- Bouton de réservation -->
                            <div class="mt-auto">
                                <button class="btn btn-primary w-100" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#reservationModal" 
                                        onclick="prefillTable('<?= htmlspecialchars($t['table_name']) ?>')"
                                        <?= !$t['is_available'] ? 'disabled' : '' ?>>
                                    <i class="fas fa-calendar-plus me-2"></i>
                                    <?= $t['is_available'] ? 'Réserver maintenant' : 'Non disponible' ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php $pages = (int)ceil($total / $perPage); if ($pages > 1): ?>
    <nav class="mt-5">
        <ul class="pagination justify-content-center">
            <?php for ($i=1; $i <= $pages; $i++): ?>
                <li class="page-item <?= $i===$page ? 'active' : '' ?>">
                    <a class="page-link" href="?q=<?= urlencode($q) ?>&page=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<!-- Modal de réservation -->
<div class="modal fade" id="reservationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Réserver une table</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="resForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Nom *</label><input class="form-control" name="customer_name" required></div>
                        <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="customer_email"></div>
                        <div class="col-md-6"><label class="form-label">Téléphone</label><input class="form-control" name="customer_phone"></div>
                        <div class="col-md-3"><label class="form-label">Date *</label><input type="date" class="form-control" name="reservation_date" required></div>
                        <div class="col-md-3"><label class="form-label">Heure *</label><input type="time" class="form-control" name="reservation_time" required></div>
                        <div class="col-md-3"><label class="form-label">Personnes *</label><input type="number" class="form-control" name="party_size" value="2" min="1" required></div>
                        <div class="col-md-3"><label class="form-label">Table</label><input class="form-control" name="table_name" id="table_name" readonly></div>
                        <div class="col-12"><label class="form-label">Message</label><textarea class="form-control" name="special_requests" rows="2"></textarea></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Annuler</button>
                    <button class="btn btn-primary" type="submit"><i class="fas fa-paper-plane me-1"></i>Envoyer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Styles CSS additionnels -->
<style>
.table-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.table-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
}
.object-fit-cover {
    object-fit: cover;
}
.card-img-top-container {
    border-radius: 8px 8px 0 0;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Recherche dynamique
const searchInput = document.getElementById('searchInput');
let searchTimeout;

searchInput.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const searchTerm = this.value.trim();
        const url = new URL(window.location);
        url.searchParams.set('q', searchTerm);
        url.searchParams.set('page', '1'); // Retour à la première page
        window.location.href = url.toString();
    }, 500); // Délai de 500ms
});

// Fonction pour pré-remplir le formulaire
function prefillTable(tableName) {
    document.getElementById('table_name').value = tableName;
}

// Gestion du formulaire de réservation
const form = document.getElementById('resForm');
form.addEventListener('submit', (e) => {
    e.preventDefault();
    const fd = new FormData(form); 
    fd.append('action', 'create');
    
    fetch('../admin/api/reservations.php', { 
        method: 'POST', 
        headers: { 'X-Requested-With': 'XMLHttpRequest' }, 
        body: fd 
    })
    .then(r => r.json())
    .then(res => { 
        if (!res.success) throw new Error(res.message || 'Erreur'); 
        alert('Réservation envoyée avec succès!'); 
        location.reload(); 
    })
    .catch(err => alert('Erreur: ' + err.message));
});
</script>

<?php include '../include/footer.php'; ?>