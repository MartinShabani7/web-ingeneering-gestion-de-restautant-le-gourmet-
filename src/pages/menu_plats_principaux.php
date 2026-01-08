<?php
include '../include/header.php';
include '../admin/statistique_visiteurs/tracker.php';
require_once '../config/database.php';
session_start();
if (!isset($_SESSION['likes'])) { $_SESSION['likes'] = []; }

$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 9; $offset = ($page-1)*$perPage;

$catStmt = $pdo->prepare("SELECT id FROM categories WHERE name LIKE ? AND is_active = 1 ORDER BY sort_order LIMIT 1");
$catStmt->execute(['%plat%']);
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
<div class="container py-4">
    <h1 class="h4 mb-3"><i class="fas fa-drumstick-bite me-2 text-danger"></i>Plats principaux</h1>
    <form class="row g-2 mb-3" method="GET">
        <div class="col-md-8"><input class="form-control" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Rechercher un plat..."></div>
        <div class="col-md-4"><button class="btn btn-primary w-100" type="submit"><i class="fas fa-search me-1"></i>Rechercher</button></div>
    </form>

    <div class="row g-3">
        <?php if (empty($rows)): ?>
            <div class="col-12 text-center text-muted py-5">Aucun plat trouvé</div>
        <?php else: foreach ($rows as $p): ?>
            <div class="col-md-4">
                <div class="card h-100">
                    <?php if ($p['image']): ?><img src="../<?= htmlspecialchars($p['image']) ?>" class="card-img-top" style="height:180px;object-fit:cover"><?php endif; ?>
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title mb-1"><?= htmlspecialchars($p['name']) ?></h5>
                        <div class="text-muted small mb-2"><?= htmlspecialchars(substr($p['description'] ?? '', 0, 90)) ?></div>
                        <div class="mt-auto d-flex justify-content-between align-items-center">
                            <strong><?= number_format($p['price'], 2) ?>€</strong>
                            <div class="d-flex gap-2">
                                <form action="cart.php?action=add" method="POST">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <input type="hidden" name="qty" value="1">
                                    <button class="btn btn-sm btn-outline-primary" type="submit"><i class="fas fa-cart-plus"></i></button>
                                </form>
                                <?php $liked = isset($_SESSION['likes'][$p['id']]); ?>
                                <a href="like.php?id=<?= $p['id'] ?>&back=menu_plats_principaux.php" class="btn btn-sm <?= $liked ? 'btn-success' : 'btn-outline-success' ?>"><i class="fas fa-thumbs-up"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>

    <?php $pages = (int)ceil($total / $perPage); if ($pages > 1): ?>
    <nav class="mt-4"><ul class="pagination justify-content-center">
        <?php for ($i=1; $i <= $pages; $i++): ?>
            <li class="page-item <?= $i===$page ? 'active' : '' ?>"><a class="page-link" href="?q=<?= urlencode($q) ?>&page=<?= $i ?>"><?= $i ?></a></li>
        <?php endfor; ?>
    </ul></nav>
    <?php endif; ?>

    <div class="text-end mt-3">
        <a class="btn btn-outline-dark" href="cart.php"><i class="fas fa-shopping-cart me-1"></i>Voir mon panier</a>
    </div>
</div>
<?php include '../include/footer.php'; ?>