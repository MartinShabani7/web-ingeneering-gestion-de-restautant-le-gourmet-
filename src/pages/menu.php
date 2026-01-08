<?php
include '../include/header.php';
include '../admin/statistique_visiteurs/tracker.php';
require_once '../config/database.php';
// session_start();
if (!isset($_SESSION['likes'])) { $_SESSION['likes'] = []; }

$q = trim($_GET['q'] ?? '');
$category_filter = $_GET['category'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12; // Changé à 12 pour 4 cartes par ligne (3x4)
$offset = ($page-1)*$perPage;

// Récupérer toutes les catégories pour le filtre
$catStmt = $pdo->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY sort_order");
$categories = $catStmt->fetchAll();

$where = ' WHERE p.is_available = 1 ';
$params = [];
if ($q !== '') { 
    $where .= ' AND (p.name LIKE ? OR p.description LIKE ?) '; 
    $params[] = "%$q%"; 
    $params[] = "%$q%"; 
}
if ($category_filter !== '') { 
    $where .= ' AND p.category_id = ? '; 
    $params[] = $category_filter; 
}

$cnt = $pdo->prepare("SELECT COUNT(*) c FROM products p $where");
$cnt->execute($params); $total = (int)$cnt->fetch()['c'];

$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        $where 
        ORDER BY p.sort_order ASC, p.created_at DESC 
        LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll();
?>
<div class="container py-5 mt-4"> <!-- Ajout de mt-4 pour l'espace avec la navbar -->
    <!-- En-tête avec titre et description touchante -->
    <div class="text-center mb-5">
        <h1 class="h2 mb-3 text-primary"><i class="fas fa-utensils me-2"></i>Notre Menu Exquis</h1>
        <p class="lead text-muted max-w-800 mx-auto">
            Découvrez un voyage culinaire où chaque plat raconte une histoire. 
            De nos entrées raffinées à nos desserts envoûtants, chaque bouchée est une célébration 
            des saveurs authentiques et de la passion de nos chefs. Laissez-vous séduire par 
            l'art de la gastronomie, où la tradition rencontre l'innovation.
        </p>
    </div>

    <!-- Barre de recherche dynamique et filtre par catégorie -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <form method="GET" id="searchForm">
                <div class="input-group">
                    <input type="text" 
                           class="form-control" 
                           name="q" 
                           id="searchInput"
                           value="<?= htmlspecialchars($q) ?>" 
                           placeholder="Rechercher un plat..."
                           oninput="debounceSearch()">
                    <input type="hidden" name="category" value="<?= htmlspecialchars($category_filter) ?>">
                    <span class="input-group-text">
                        <i class="fas fa-search text-muted"></i>
                    </span>
                </div>
            </form>
        </div>
        <div class="col-md-6">
            <form method="GET" id="categoryForm">
                <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">
                <select class="form-select" name="category" onchange="this.form.submit()">
                    <option value="">Toutes les catégories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $category_filter == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>

    <!-- Affichage des plats -->
    <div class="row g-3">
        <?php if (empty($rows)): ?>
            <div class="col-12 text-center text-muted py-5">
                <i class="fas fa-search fa-3x mb-3"></i>
                <p>Aucun plat trouvé pour votre recherche.</p>
                <?php if ($q || $category_filter): ?>
                    <a href="menu.php" class="btn btn-outline-primary mt-2">Voir tout le menu</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($rows as $p): ?>
                <div class="col-xl-3 col-lg-4 col-md-6"> <!-- 4 cartes par ligne sur grand écran -->
                    <div class="card h-100 product-card">
                        <div class="card-img-container">
                            <?php if ($p['image']): ?>
                                <img src="../<?= htmlspecialchars($p['image']) ?>" 
                                     class="card-img-top" 
                                     alt="<?= htmlspecialchars($p['name']) ?>">
                            <?php else: ?>
                                <div class="card-img-top bg-light d-flex align-items-center justify-content-center">
                                    <i class="fas fa-utensils fa-2x text-muted"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-body d-flex flex-column p-3"> 
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="card-title mb-0 fw-bold"><?= htmlspecialchars($p['name']) ?></h6> 
                                <span class="badge bg-secondary small"><?= htmlspecialchars($p['category_name']) ?></span>
                            </div>
                            <p class="card-text text-muted small flex-grow-1 mb-2">
                                <?= htmlspecialchars(substr($p['description'] ?? '', 0, 60)) ?>
                                <?= strlen($p['description'] ?? '') > 60 ? '...' : '' ?>
                            </p>
                            <div class="d-flex justify-content-between align-items-center mt-auto">
                                <strong class="text-primary"><?= number_format($p['price'], 2) ?>€</strong>
                                <div class="d-flex gap-1"> <!-- Gap réduit -->
                                    <form action="cart.php?action=add" method="POST" class="d-inline">
                                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                        <input type="hidden" name="qty" value="1">
                                        <button class="btn btn-sm btn-outline-primary" type="submit" 
                                                title="Ajouter au panier">
                                            <i class="fas fa-cart-plus"></i>
                                        </button>
                                    </form>
                                    <?php $liked = isset($_SESSION['likes'][$p['id']]); ?>
                                    <a href="like.php?id=<?= $p['id'] ?>&back=menu.php<?= $q ? '&q=' . urlencode($q) : '' ?><?= $category_filter ? '&category=' . $category_filter : '' ?>" 
                                       class="btn btn-sm <?= $liked ? 'btn-success' : 'btn-outline-success' ?>" 
                                       title="<?= $liked ? 'Retirer des favoris' : 'Ajouter aux favoris' ?>">
                                        <i class="fas fa-thumbs-up"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php $pages = (int)ceil($total / $perPage); if ($pages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for ($i=1; $i <= $pages; $i++): ?>
                <li class="page-item <?= $i===$page ? 'active' : '' ?>">
                    <a class="page-link" 
                       href="?q=<?= urlencode($q) ?>&category=<?= $category_filter ?>&page=<?= $i ?>">
                        <?= $i ?>
                    </a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>

    <!-- Bouton panier -->
    <div class="text-end mt-4">
        <a class="btn btn-outline-dark" href="cart.php">
            <i class="fas fa-shopping-cart me-1"></i>Voir mon panier
        </a>
    </div>
</div>

<style>
.max-w-800 {
    max-width: 800px;
}
.product-card {
    transition: transform 0.2s ease-in-out;
    border: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    font-size: 0.9rem;
}
.product-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.12);
}
.card-img-container {
    height: 140px; 
    overflow: hidden;
}
.card-img-top {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}
.product-card:hover .card-img-top {
    transform: scale(1.05);
}
.card-body {
    padding: 0.75rem; 
}
.card-title {
    font-size: 0.95rem; 
    font-weight: 600;
    line-height: 1.3;
}
.card-text {
    font-size: 0.8rem; 
    line-height: 1.3;
}
.badge {
    font-size: 0.65rem; 
}
.btn-sm {
    padding: 0.25rem 0.5rem;
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
    }
});
</script>

<?php include '../include/footer.php'; ?>