<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['cart'])) { $_SESSION['cart'] = []; }

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function add_to_cart($id, $qty) {
    if (!isset($_SESSION['cart'][$id])) { $_SESSION['cart'][$id] = 0; }
    $_SESSION['cart'][$id] += max(1, (int)$qty);
}
function remove_from_cart($id) { unset($_SESSION['cart'][$id]); }
function update_cart($id, $qty) { if ($qty <= 0) unset($_SESSION['cart'][$id]); else $_SESSION['cart'][$id] = (int)$qty; }

if ($action === 'add') {
    add_to_cart((int)($_POST['product_id'] ?? 0), (int)($_POST['qty'] ?? 1));
    header('Location: cart.php'); exit;
}
if ($action === 'remove') { remove_from_cart((int)($_GET['product_id'] ?? 0)); header('Location: cart.php'); exit; }
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (($_POST['qty'] ?? []) as $pid => $q) { update_cart((int)$pid, (int)$q); }
    header('Location: cart.php'); exit;
}

$ids = array_keys($_SESSION['cart']);
$items = [];
$total = 0;
if (!empty($ids)) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, name, price, image FROM products WHERE id IN ($in)");
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();
    foreach ($rows as $r) {
        $qty = $_SESSION['cart'][$r['id']];
        $line = [ 'id'=>$r['id'], 'name'=>$r['name'], 'price'=>$r['price'], 'image'=>$r['image'], 'qty'=>$qty, 'total'=>$qty * $r['price'] ];
        $items[] = $line; $total += $line['total'];
    }
}
?>
<?php include '../include/header.php'; ?>

<div class="container py-4">
    <h1 class="h4 mb-3"><i class="fas fa-shopping-cart me-2 text-primary"></i>Mon panier</h1>

    <form method="POST" action="cart.php?action=update">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Article</th><th>Prix</th><th>Qté</th><th class="text-end">Total</th><th></th></tr></thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">Votre panier est vide</td></tr>
                <?php else: foreach ($items as $it): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <?php if ($it['image']): ?><img src="../<?= htmlspecialchars($it['image']) ?>" style="width:60px;height:60px;object-fit:cover" class="me-2 rounded"><?php endif; ?>
                                <strong><?= htmlspecialchars($it['name']) ?></strong>
                            </div>
                        </td>
                        <td><?= number_format($it['price'], 2) ?>€</td>
                        <td style="max-width:110px"><input class="form-control" type="number" name="qty[<?= $it['id'] ?>]" value="<?= (int)$it['qty'] ?>" min="0"></td>
                        <td class="text-end"><?= number_format($it['total'], 2) ?>$</td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-danger" href="cart.php?action=remove&product_id=<?= $it['id'] ?>"><i class="fas fa-trash"></i></a></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
            <?php if (!empty($items)): ?>
            <tfoot>
                <tr><th colspan="3" class="text-end">Total</th><th class="text-end"><?= number_format($total, 2) ?>$</th><th></th></tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
    <div class="d-flex justify-content-between">
        <a href="../index.php#menu" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Continuer mes achats</a>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary" type="submit"><i class="fas fa-rotate me-1"></i>Mettre à jour</button>
            <?php if (!empty($items)): ?><a class="btn btn-success" href="checkout.php"><i class="fas fa-credit-card me-1"></i>Commander</a><?php endif; ?>
        </div>
    </div>
    </form>
</div>

<?php include '../include/footer.php'; ?>


