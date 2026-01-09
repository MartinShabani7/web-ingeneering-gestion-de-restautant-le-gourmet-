<?php
session_start();
require_once '../config/database.php';
require_once '../config/security.php';

if (!Security::isLoggedIn()) {
    Security::redirect('../auth/login.php');
}

// Récupérer l'ID de l'utilisateur connecté
$user_id = $_SESSION['user_id'];

// Récupérer les commandes de l'utilisateur
$orders = [];

try {
    // Récupérer les commandes avec les détails
    $stmt = $pdo->prepare("
        SELECT 
            o.id,
            o.order_number,
            o.order_type,
            o.table_number,
            o.status,
            o.payment_status,
            o.total_amount,
            o.notes,
            o.created_at,
            COUNT(oi.id) as items_count
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.customer_id = ?
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");
    
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des commandes: " . $e->getMessage());
}

// Fonction pour traduire les statuts
function translateStatus($status) {
    $translations = [
        'pending' => 'En attente',
        'confirmed' => 'Confirmée',
        'preparing' => 'En préparation',
        'ready' => 'Prête',
        'served' => 'Servie',
        'cancelled' => 'Annulée',
        'completed' => 'Terminée'
    ];
    return $translations[$status] ?? $status;
}

function translatePaymentStatus($status) {
    $translations = [
        'pending' => 'En attente',
        'paid' => 'Payée',
        'refunded' => 'Remboursée',
        'partially_paid' => 'Partiellement payée'
    ];
    return $translations[$status] ?? $status;
}

function translateOrderType($type) {
    $translations = [
        'dine_in' => 'Sur place',
        'takeaway' => 'À emporter',
        'delivery' => 'Livraison'
    ];
    return $translations[$type] ?? $type;
}

// include 'header_navbar.php';
include 'jenga.php';
?>
    <style>
        #container{
            margin-left:265px;
            margin-top:60px;
        }
        .order-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border-left: 4px solid;
        }
        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .order-card.dine_in { border-left-color: #0d6efd; }
        .order-card.takeaway { border-left-color: #6c757d; }
        .order-card.delivery { border-left-color: #198754; }
        .status-badge { font-size: 0.85em; }
        .price-highlight { font-size: 1.1em; font-weight: 600; }
        .order-type-icon {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin-right: 8px;
        }
    </style>

<div id="container" class="container">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h4 mb-0"><i class="fas fa-shopping-bag me-2 text-primary"></i>Mes commandes</h1>
            <div>
                <a class="btn btn-outline-secondary me-2" href="dashboard.php"><i class="fas fa-arrow-left me-1"></i>Retour</a>
                <a class="btn btn-primary" href="nouvelle_commande.php"><i class="fas fa-plus me-1"></i>Nouvelle commande</a>
            </div>
        </div>

        <?php if (empty($orders)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-shopping-bag fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Aucune commande</h5>
                    <p class="text-muted mb-0">Vous n'avez pas encore passé de commande.</p>
                    <a href="nouvelle_commande.php" class="btn btn-primary mt-3">Passer ma première commande</a>
                </div>
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-2 g-4">
                <?php foreach ($orders as $order): ?>
                    <div class="col">
                        <div class="card order-card h-100 <?= htmlspecialchars($order['order_type']) ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="card-title mb-1">
                                            <span class="order-type-icon bg-<?= $order['order_type'] === 'dine_in' ? 'primary' : ($order['order_type'] === 'takeaway' ? 'secondary' : 'success') ?> text-white">
                                                <i class="fas fa-<?= $order['order_type'] === 'dine_in' ? 'utensils' : ($order['order_type'] === 'takeaway' ? 'shopping-bag' : 'truck') ?>"></i>
                                            </span>
                                            Commande #<?= htmlspecialchars($order['order_number']) ?>
                                        </h5>
                                        <small class="text-muted">
                                            <i class="far fa-calendar me-1"></i>
                                            <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?>
                                        </small>
                                    </div>
                                    <span class="badge bg-<?= 
                                        $order['status'] === 'cancelled' ? 'danger' : 
                                        ($order['status'] === 'served' || $order['status'] === 'ready' || $order['status'] === 'completed' ? 'success' : 
                                        ($order['status'] === 'preparing' ? 'warning' : 
                                        ($order['status'] === 'confirmed' ? 'info' : 'secondary'))) ?> status-badge">
                                        <?= translateStatus($order['status']) ?>
                                    </span>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Type</small>
                                        <span class="d-block">
                                            <?= translateOrderType($order['order_type']) ?>
                                        </span>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Articles</small>
                                        <span class="d-block">
                                            <i class="fas fa-boxes me-1"></i>
                                            <?= (int)$order['items_count'] ?> article(s)
                                        </span>
                                    </div>
                                </div>

                                <?php if ($order['order_type'] === 'dine_in' && !empty($order['table_number'])): ?>
                                    <div class="mb-3">
                                        <small class="text-muted d-block">Table</small>
                                        <span class="d-block">
                                            <i class="fas fa-chair me-1"></i>
                                            Table #<?= htmlspecialchars($order['table_number']) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                                    <div>
                                        <small class="text-muted d-block">Paiement</small>
                                        <span class="d-block badge bg-<?= 
                                            $order['payment_status'] === 'paid' ? 'success' : 
                                            ($order['payment_status'] === 'refunded' ? 'info' : 
                                            ($order['payment_status'] === 'partially_paid' ? 'warning' : 'secondary')) ?>">
                                            <?= translatePaymentStatus($order['payment_status']) ?>
                                        </span>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted d-block">Total</small>
                                        <span class="price-highlight text-primary">
                                            <?= number_format($order['total_amount'], 2, ',', ' ') ?> $
                                        </span>
                                    </div>
                                </div>

                                <?php if (!empty($order['notes'])): ?>
                                    <div class="mt-3 pt-3 border-top">
                                        <small class="text-muted d-block mb-1">Notes</small>
                                        <p class="mb-0 small"><?= nl2br(htmlspecialchars($order['notes'])) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer bg-transparent border-top-0 pt-0">
                                <div class="d-flex justify-content-between">
                                    <a href="details_commande.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye me-1"></i>Détails
                                    </a>
                                    <?php if ($order['status'] === 'pending' || $order['status'] === 'confirmed'): ?>
                                        <a href="annuler_commande.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Annuler cette commande ?')">
                                            <i class="fas fa-times me-1"></i>Annuler
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Vue tableau -->
            <div class="card mt-4 d-none d-lg-block">
                <div class="card-body">
                    <h6 class="card-subtitle mb-3 text-muted">Vue détaillée</h6>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>N° Commande</th>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Table</th>
                                    <th>Articles</th>
                                    <th>Statut</th>
                                    <th>Paiement</th>
                                    <th>Total</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td>
                                            <strong>#<?= htmlspecialchars($order['order_number']) ?></strong>
                                        </td>
                                        <td>
                                            <?= date('d/m/Y', strtotime($order['created_at'])) ?><br>
                                            <small class="text-muted"><?= date('H:i', strtotime($order['created_at'])) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $order['order_type'] === 'dine_in' ? 'primary' : ($order['order_type'] === 'takeaway' ? 'secondary' : 'success') ?>">
                                                <?= translateOrderType($order['order_type']) ?>
                                            </span>
                                        </td>
                                        <td><?= !empty($order['table_number']) ? htmlspecialchars($order['table_number']) : '-' ?></td>
                                        <td><?= (int)$order['items_count'] ?></td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $order['status'] === 'cancelled' ? 'danger' : 
                                                ($order['status'] === 'served' || $order['status'] === 'ready' || $order['status'] === 'completed' ? 'success' : 
                                                ($order['status'] === 'preparing' ? 'warning' : 
                                                ($order['status'] === 'confirmed' ? 'info' : 'secondary'))) ?>">
                                                <?= translateStatus($order['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $order['payment_status'] === 'paid' ? 'success' : 
                                                ($order['payment_status'] === 'refunded' ? 'info' : 
                                                ($order['payment_status'] === 'partially_paid' ? 'warning' : 'secondary')) ?>">
                                                <?= translatePaymentStatus($order['payment_status']) ?>
                                            </span>
                                        </td>
                                        <td class="fw-bold"><?= number_format($order['total_amount'], 2, ',', ' ') ?> $</td>
                                        <td>
                                            <a href="details_commande.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

    <script>
        // Option pour basculer entre les vues
        document.addEventListener('DOMContentLoaded', function() {
            const toggleViewBtn = document.createElement('button');
            toggleViewBtn.className = 'btn btn-sm btn-outline-secondary d-none d-lg-inline-block';
            toggleViewBtn.innerHTML = '<i class="fas fa-table"></i> Basculer vue';
            toggleViewBtn.style.position = 'fixed';
            toggleViewBtn.style.bottom = '20px';
            toggleViewBtn.style.right = '20px';
            toggleViewBtn.style.zIndex = '1000';
            
            toggleViewBtn.addEventListener('click', function() {
                const gridView = document.querySelector('.row-cols-1');
                const tableView = document.querySelector('.d-none.d-lg-block');
                
                if (gridView.style.display === 'none') {
                    gridView.style.display = 'flex';
                    tableView.classList.add('d-none');
                    this.innerHTML = '<i class="fas fa-table"></i> Vue tableau';
                } else {
                    gridView.style.display = 'none';
                    tableView.classList.remove('d-none');
                    this.innerHTML = '<i class="fas fa-th-large"></i> Vue grille';
                }
            });
            
            document.body.appendChild(toggleViewBtn);
        });
    </script>
<?php include 'footer.php'?>