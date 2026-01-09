<?php
// session_start();
require_once '../config/database.php';
require_once '../config/security.php';
// include 'header_navbar.php'; 
include 'jenga.php';
// include 'navbar.php';
// include 'm.php';
// include 'egal_profil.php';

// // Vérification de l'authentification
// if (!Security::isLoggedIn()) {
//     Security::redirect('../auth/login.php');
// }

// // Récupération des informations utilisateur
// $userId = $_SESSION['user_id'];
// $stmt = $pdo->prepare("
//     SELECT u.*, 
//            COUNT(DISTINCT o.id) as total_orders,
//            COUNT(DISTINCT r.id) as total_reservations,
//            SUM(o.total_amount) as total_spent
//     FROM users u
//     LEFT JOIN orders o ON u.id = o.customer_id
//     LEFT JOIN reservations r ON u.id = r.customer_id
//     WHERE u.id = ?
//     GROUP BY u.id
// ");
// $stmt->execute([$userId]);
// $user = $stmt->fetch();

// Récupération des dernières commandes
$stmt = $pdo->prepare("
    SELECT o.*, 
           COUNT(oi.id) as item_count
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.customer_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 5
");
$stmt->execute([$userId]);
$recentOrders = $stmt->fetchAll();

// Récupération des prochaines réservations
$stmt = $pdo->prepare("
    SELECT * FROM reservations 
    WHERE customer_id = ? 
    AND reservation_date >= CURDATE()
    AND status IN ('pending', 'confirmed')
    ORDER BY reservation_date, reservation_time
    LIMIT 3
");
$stmt->execute([$userId]);
$upcomingReservations = $stmt->fetchAll();

// Statistiques du mois
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as orders_this_month,
        SUM(total_amount) as spent_this_month
    FROM orders 
    WHERE customer_id = ? 
    AND MONTH(created_at) = MONTH(CURDATE())
    AND YEAR(created_at) = YEAR(CURDATE())
");
$stmt->execute([$userId]);
$monthStats = $stmt->fetch();
?>

<style>
    #container{
        margin-left:265px;
        margin-top:40px;
    }
</style>

    <div id="container" class="container">
        <!-- <div class="row" style ="width:100%"> -->
            <!-- Contenu principal -->
            <div class="col-md-12 col-lg-12 dashboard-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Bienvenue <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>!!</h1>
                    <h5 class="mb-0">Membre depuis le  
                        <?php 
                        if ($user['created_at']) {
                            $date = new DateTime($user['created_at']);
                            echo htmlspecialchars($date->format('d/m/Y'));
                        } else {
                            echo 'N/A';
                        }
                        ?>
                    </h5>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-download me-1"></i>Exporter
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Statistiques -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card">
                            <div class="stats-icon primary">
                                <i class="fas fa-shopping-bag"></i>
                            </div>
                            <div class="stats-content">
                                <h3 class="stats-number"><?= $user['total_orders'] ?></h3>
                                <p class="stats-label">Commandes totales</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card">
                            <div class="stats-icon success">
                                <i class="fas fa-calendar"></i>
                            </div>
                            <div class="stats-content">
                                <h3 class="stats-number"><?= $user['total_reservations'] ?></h3>
                                <p class="stats-label">Réservations</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card">
                            <div class="stats-icon warning">
                                <i class="fas fa-euro-sign"></i>
                            </div>
                            <div class="stats-content">
                                <h3 class="stats-number"><?= number_format($user['total_spent'] ?? 0, 2) ?>$</h3>
                                <p class="stats-label">Total dépensé</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card">
                            <div class="stats-icon danger">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="stats-content">
                                <h3 class="stats-number"><?= $monthStats['orders_this_month'] ?></h3>
                                <p class="stats-label">Ce mois-ci</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Dernières commandes -->
                    <div class="col-lg-8 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-shopping-bag me-2"></i>Dernières commandes
                                </h5>
                                <a href="commandes.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentOrders)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-shopping-bag fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">Aucune commande pour le moment</p>
                                        <a href="../index.php#menu" class="btn btn-primary">Découvrir notre menu</a>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Commande</th>
                                                    <th>Date</th>
                                                    <th>Statut</th>
                                                    <th>Total</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recentOrders as $order): ?>
                                                    <tr>
                                                        <td>
                                                            <strong>#<?= htmlspecialchars($order['order_number']) ?></strong>
                                                            <br>
                                                            <small class="text-muted"><?= $order['item_count'] ?> article(s)</small>
                                                        </td>
                                                        <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                                                        <td>
                                                            <span class="badge bg-<?= $order['status'] === 'served' ? 'success' : ($order['status'] === 'cancelled' ? 'danger' : 'warning') ?>">
                                                                <?= ucfirst($order['status']) ?>
                                                            </span>
                                                        </td>
                                                        <td><?= number_format($order['total_amount'], 2) ?>$</td>
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
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Prochaines réservations -->
                    <div class="col-lg-4 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-calendar me-2"></i>Prochaines réservations
                                </h5>
                                <a href="reservations.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($upcomingReservations)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-calendar fa-2x text-muted mb-3"></i>
                                        <p class="text-muted">Aucune réservation</p>
                                        <a href="nouvelle_reservation.php" class="btn btn-primary btn-sm">Réserver</a>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($upcomingReservations as $reservation): ?>
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="flex-shrink-0">
                                                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                    <i class="fas fa-calendar text-white"></i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <h6 class="mb-1"><?= date('d/m/Y', strtotime($reservation['reservation_date'])) ?></h6>
                                                <p class="mb-1 text-muted"><?= date('H:i', strtotime($reservation['reservation_time'])) ?> - <?= $reservation['party_size'] ?> personne(s)</p>
                                                <span class="badge bg-<?= $reservation['status'] === 'confirmed' ? 'success' : 'warning' ?>">
                                                    <?= ucfirst($reservation['status']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions rapides -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-bolt me-2"></i>Actions rapides
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <a href="nouvelle_reservation.php" class="btn btn-outline-primary w-100">
                                            <i class="fas fa-calendar-plus fa-2x mb-2"></i>
                                            <br>Nouvelle réservation
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="../index.php#menu" class="btn btn-outline-success w-100">
                                            <i class="fas fa-utensils fa-2x mb-2"></i>
                                            <br>Voir le menu
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="profile.php" class="btn btn-outline-info w-100">
                                            <i class="fas fa-user-edit fa-2x mb-2"></i>
                                            <br>Modifier profil
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="temoignage.php" class="btn btn-outline-warning w-100">
                                            <i class="fas fa-star fa-2x mb-2"></i>
                                            <br>Laisser un avis
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <!-- </div> -->
    </div>

<?php include 'footer.php'?>

