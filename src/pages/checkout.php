<?php
session_start();
require_once '../config/database.php';
require_once '../config/security.php';

if (!Security::isLoggedIn()) { Security::redirect('../auth/login.php'); }

if (empty($_SESSION['cart'])) { header('Location: cart.php'); exit; }

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderType = $_POST['order_type'] ?? 'dine_in';
    $tableNumber = $_POST['table_number'] ?? null;
    $notes = Security::sanitizeInput($_POST['notes'] ?? '');

    try {
        $pdo->beginTransaction();
        // compute totals
        $ids = array_keys($_SESSION['cart']);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT id, price FROM products WHERE id IN ($in)");
        $stmt->execute($ids);
        $prices = [];
        foreach ($stmt->fetchAll() as $r) { $prices[$r['id']] = (float)$r['price']; }
        $subtotal = 0;
        foreach ($_SESSION['cart'] as $pid => $qty) { $subtotal += ($prices[$pid] ?? 0) * $qty; }
        $tax = 0; $discount = 0; $total = $subtotal + $tax - $discount;

        // create order
        $orderNo = 'ORD' . date('ymdHis') . rand(100,999);
        $ins = $pdo->prepare("INSERT INTO orders(order_number, customer_id, table_number, order_type, status, subtotal, tax_amount, discount_amount, total_amount, payment_status, notes, created_at) VALUES(?,?,?,?, 'pending', ?,?,?,?, 'pending', ?, NOW())");
        $ins->execute([$orderNo, $_SESSION['user_id'], $tableNumber ?: null, $orderType, $subtotal, $tax, $discount, $total, $notes]);
        $orderId = $pdo->lastInsertId();

        // items
        $insItem = $pdo->prepare("INSERT INTO order_items(order_id, product_id, quantity, unit_price, total_price, created_at) VALUES(?,?,?,?,?, NOW())");
        foreach ($_SESSION['cart'] as $pid => $qty) {
            $price = $prices[$pid] ?? 0; $line = $qty * $price;
            $insItem->execute([$orderId, $pid, $qty, $price, $line]);
        }

        $pdo->commit();
        $success = true; unset($_SESSION['cart']);
    } catch (Exception $e) {
        $pdo->rollBack();
        $errors[] = "Erreur lors de la commande";
        error_log('Checkout error: ' . $e->getMessage());
    }
}
?>
<?php include '../include/header.php'; ?>
<div class="container py-4">
    <h1 class="h4 mb-3"><i class="fas fa-credit-card me-2 text-primary"></i>Commande</h1>

    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check me-1"></i>Votre commande a été enregistrée. Merci !</div>
        <a href="../member/dashboard.php" class="btn btn-primary"><i class="fas fa-user me-1"></i>Mon espace</a>
    <?php else: ?>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger"><?php foreach ($errors as $e) { echo '<div>' . htmlspecialchars($e) . '</div>'; } ?></div>
        <?php endif; ?>

        <form method="POST" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Type de commande</label>
                <select name="order_type" class="form-select">
                    <option value="dine_in">Sur place</option>
                    <option value="takeaway">À emporter</option>
                    <option value="delivery">Livraison</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">N° table (si sur place)</label>
                <input class="form-control" name="table_number" placeholder="Ex: A1">
            </div>
            <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea class="form-control" name="notes" rows="3"></textarea>
            </div>
            <div class="col-12">
                <button class="btn btn-success" type="submit"><i class="fas fa-check me-1"></i>Valider la commande</button>
            </div>
        </form>
    <?php endif; ?>
</div>
<?php include '../include/footer.php'; ?>


