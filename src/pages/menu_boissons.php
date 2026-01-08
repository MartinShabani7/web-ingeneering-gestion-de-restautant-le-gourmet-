<?php
include '../include/header.php';
include '../admin/statistique_visiteurs/tracker.php';
require_once '../config/database.php';

session_start();
if (!isset($_SESSION['likes'])) { $_SESSION['likes'] = []; }

$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 9; $offset = ($page-1)*$perPage;

// Trouver l'ID de la catégorie "Boisson"
$catStmt = $pdo->prepare("SELECT id FROM categories WHERE name LIKE ? AND is_active = 1 ORDER BY sort_order LIMIT 1");
$catStmt->execute(['%bois%']);
$cat = $catStmt->fetch();
$catId = $cat['id'] ?? null;

$where = ' WHERE p.is_available = 1 ';
$params = [];
if ($catId) { $where .= ' AND p.category_id = ? '; $params[] = $catId; }
if ($q !== '') { $where .= ' AND (p.name LIKE ? OR p.description LIKE ?) '; $params[] = "%$q%"; $params[] = "%$q%"; }

$cnt = $pdo->prepare("SELECT COUNT(*) c FROM products p $where");
$cnt->execute($params); $total = (int)$cnt->fetch()['c'];

$sql = "SELECT p.* FROM products p $where ORDER BY p.sort_order ASC, p.created_at DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll();
?>
<div class="container py-5 mt-4">
    <!-- En-tête avec titre et description touchante -->
    <div class="text-center mb-5">
        <h1 class="h2 mb-3 text-info"><i class="fas fa-glass-cheers me-2"></i>Notre Carte des Boissons</h1>
        <p class="lead text-muted max-w-800 mx-auto">
            Laissez-vous séduire par notre sélection de boissons raffinées, où chaque gorgée est une invitation 
            au voyage. Des vins d'exception soigneusement choisis aux cocktails créatifs, en passant par 
            les softs élégants et les cafés d'origine, découvrez l'art de la dégustation dans toute sa splendeur. 
            L'accord parfait pour sublimer votre expérience culinaire.
        </p>
    </div>

    <!-- Barre de recherche totalement dynamique -->
    <div class="row justify-content-center mb-4">
        <div class="col-md-8">
            <form method="GET" id="searchForm">
                <div class="input-group">
                    <input type="text" 
                           class="form-control" 
                           name="q" 
                           id="searchInput"
                           value="<?= htmlspecialchars($q) ?>" 
                           placeholder="Rechercher une boisson, un vin, un cocktail..."
                           oninput="debounceSearch()">
                    <span class="input-group-text bg-info text-white">
                        <i class="fas fa-search"></i>
                    </span>
                </div>
            </form>
        </div>
    </div>

    <!-- Compteur de résultats -->
    <?php if ($q && !empty($rows)): ?>
    <div class="row mb-3">
        <div class="col-12">
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="fas fa-info-circle me-2"></i>
                <strong><?= $total ?></strong> boisson(s) trouvée(s) pour "<strong><?= htmlspecialchars($q) ?></strong>"
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Affichage des boissons -->
    <div class="row g-4">
        <?php if (empty($rows)): ?>
            <div class="col-12 text-center text-muted py-5">
                <i class="fas fa-glass-cheers fa-3x mb-3"></i>
                <p class="fs-5">Aucune boisson trouvée pour votre recherche.</p>
                <?php if ($q): ?>
                    <a href="menu_boissons.php" class="btn btn-outline-info mt-2">
                        <i class="fas fa-redo me-1"></i>Voir toutes les boissons
                    </a>
                <?php endif; ?>
            </div>
        <?php else: foreach ($rows as $p): ?>
            <div class="col-xl-4 col-lg-6">
                <div class="card h-100 drink-card">
                    <div class="drink-image-container">
                        <?php if ($p['image']): ?>
                            <img src="../<?= htmlspecialchars($p['image']) ?>" 
                                 class="drink-image" 
                                 alt="<?= htmlspecialchars($p['name']) ?>">
                        <?php else: ?>
                            <div class="drink-image-placeholder">
                                <i class="fas fa-glass-cheers fa-2x"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title text-dark mb-2"><?= htmlspecialchars($p['name']) ?></h5>
                        <p class="card-text text-muted small flex-grow-1 mb-3">
                            <?= htmlspecialchars(substr($p['description'] ?? '', 0, 100)) ?>
                            <?= strlen($p['description'] ?? '') > 100 ? '...' : '' ?>
                        </p>
                        <div class="mt-auto d-flex justify-content-between align-items-center">
                            <strong class="text-info fs-5"><?= number_format($p['price'], 2) ?>€</strong>
                            <div class="d-flex gap-2">
                                <form action="cart.php?action=add" method="POST" class="d-inline">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <input type="hidden" name="qty" value="1">
                                    <button class="btn btn-sm btn-outline-info" type="submit" title="Ajouter au panier">
                                        <i class="fas fa-cart-plus"></i>
                                    </button>
                                </form>
                                <?php $liked = isset($_SESSION['likes'][$p['id']]); ?>
                                <a href="like.php?id=<?= $p['id'] ?>&back=menu_boissons.php<?= $q ? '&q=' . urlencode($q) : '' ?>" 
                                   class="btn btn-sm <?= $liked ? 'btn-success' : 'btn-outline-success' ?>" 
                                   title="<?= $liked ? 'Retirer des favoris' : 'Ajouter aux favoris' ?>">
                                    <i class="fas fa-thumbs-up"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; endif; ?>
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

    <!-- Bouton panier -->
    <div class="text-center mt-4">
        <a class="btn btn-outline-info btn-lg" href="cart.php">
            <i class="fas fa-shopping-cart me-2"></i>Voir mon panier
        </a>
    </div>
</div>

<style>
.max-w-800 {
    max-width: 800px;
}

.drink-card {
    transition: transform 0.3s ease;
    border: none;
    box-shadow: 0 3px 15px rgba(0,0,0,0.1);
    border-radius: 15px;
    overflow: hidden;
}

.drink-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.drink-image-container {
    height: 200px;
    overflow: hidden;
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
}

.drink-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.drink-card:hover .drink-image {
    transform: scale(1.1);
}

.drink-image-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 3rem;
}

.card-body {
    padding: 1.5rem;
}

.card-title {
    font-weight: 600;
    font-size: 1.1rem;
}

/* Responsive */
@media (max-width: 768px) {
    .drink-image-container {
        height: 160px;
    }
    
    .card-body {
        padding: 1rem;
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
    }, 500); // 500ms de délai
}

// Focus sur la barre de recherche au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.focus();
        // Positionner le curseur à la fin du texte
        searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
    }
});
</script>

<?php include '../include/footer.php'; ?>