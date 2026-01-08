<?php
session_start();
require_once '../config/database.php';
require_once '../config/security.php';

if (!Security::isLoggedIn()) { Security::redirect('../auth/login.php'); }

$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM reservations WHERE customer_id = ? ORDER BY reservation_date DESC, reservation_time DESC");
$stmt->execute([$userId]);
$reservations = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes réservations - Le Gourmet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 mb-0"><i class="fas fa-calendar me-2 text-primary"></i>Mes réservations</h1>
            <div>
                <a class="btn btn-outline-secondary me-2" href="dashboard.php"><i class="fas fa-arrow-left me-1"></i>Retour</a>
                <a class="btn btn-primary" href="nouvelle_reservation.php"><i class="fas fa-plus me-1"></i>Réserver</a>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Heure</th>
                                <th>Personnes</th>
                                <th>Table</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($reservations)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">Aucune réservation</td></tr>
                            <?php else: foreach ($reservations as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['reservation_date']) ?></td>
                                    <td><?= htmlspecialchars($r['reservation_time']) ?></td>
                                    <td><?= (int)$r['party_size'] ?></td>
                                    <td><?= htmlspecialchars($r['table_name'] ?? '-') ?></td>
                                    <td>
                                        <span class="badge bg-<?= $r['status'] === 'completed' ? 'success' : ($r['status'] === 'cancelled' ? 'danger' : 'warning') ?>">
                                            <?= htmlspecialchars($r['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>


