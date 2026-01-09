<?php
session_start();
require_once '../config/database.php';
require_once '../config/security.php';

// Activer les erreurs pour débogage (à désactiver en production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Headers pour AJAX
header('Content-Type: text/html; charset=utf-8');

if (!Security::isLoggedIn()) { 
    echo '<div class="alert alert-danger">Session expirée. Veuillez vous reconnecter.</div>';
    exit;
}

$reservationId = (int)($_GET['id'] ?? 0);
$userId = $_SESSION['user_id'];

if ($reservationId <= 0) {
    echo '<div class="alert alert-danger">ID de réservation invalide.</div>';
    exit;
}

try {
    // REQUÊTE SIMPLIFIÉE ET SÉCURISÉE
    $sql = "
        SELECT 
            r.*,
            t.table_name,
            t.capacity as table_capacity,
            t.location as table_location
        FROM reservations r
        LEFT JOIN tables t ON r.table_id = t.id
        WHERE r.id = ? AND r.customer_id = ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$reservationId, $userId]);
    $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reservation) {
        echo '<div class="alert alert-danger">Réservation non trouvée.</div>';
        exit;
    }
    
    // Formater les dates
    $dateFr = date('l d F Y', strtotime($reservation['reservation_date']));
    $dateShort = date('d/m/Y', strtotime($reservation['reservation_date']));
    $time = date('H:i', strtotime($reservation['reservation_time']));
    $createdAt = date('d/m/Y à H:i', strtotime($reservation['created_at']));
    $updatedAt = $reservation['updated_at'] ? date('d/m/Y à H:i', strtotime($reservation['updated_at'])) : null;
    
    // Déterminer le statut
    $statusText = '';
    $statusClass = '';
    switch($reservation['status']) {
        case 'confirmed':
            $statusText = 'Confirmée';
            $statusClass = 'success';
            break;
        case 'cancelled':
            $statusText = 'Annulée';
            $statusClass = 'danger';
            break;
        case 'completed':
            $statusText = 'Terminée';
            $statusClass = 'secondary';
            break;
        case 'no_show':
            $statusText = 'Non présenté';
            $statusClass = 'dark';
            break;
        default:
            $statusText = 'En attente';
            $statusClass = 'warning';
    }
    
    // Vérifier si la réservation est aujourd'hui/demain
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $dateBadge = '';
    
    if ($reservation['reservation_date'] == $today) {
        $dateBadge = '<span class="badge bg-info ms-2">Aujourd\'hui</span>';
    } elseif ($reservation['reservation_date'] == $tomorrow) {
        $dateBadge = '<span class="badge bg-primary ms-2">Demain</span>';
    }
    
    // Vérifier si c'est une réservation passée
    $isPast = strtotime($reservation['reservation_date'] . ' ' . $reservation['reservation_time']) < time();
    
    ?>
    <style>
        .reservation-detail-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid #eee;
        }
        .reservation-detail-item:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: 600;
            color: #495057;
            min-width: 140px;
        }
        .detail-value {
            color: #212529;
        }
        .detail-icon {
            width: 24px;
            color: #6c757d;
        }
        .section-title {
            color: #495057;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e9ecef;
        }
        .info-badge {
            font-size: 0.85em;
            padding: 0.25em 0.6em;
        }
        .capacity-warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 0.5rem 0.75rem;
            border-radius: 0.25rem;
            margin-top: 0.5rem;
        }
    </style>
    
    <div class="reservation-details">
        <!-- En-tête avec statut -->
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <div>
                <h5 class="mb-1 text-primary">Réservation #<?= $reservationId ?></h5>
                <div class="text-muted small">
                    <i class="fas fa-calendar-alt me-1"></i>
                    <?= $dateFr ?> à <?= $time ?>
                    <?= $dateBadge ?>
                </div>
            </div>
            <div>
                <span class="badge bg-<?= $statusClass ?> px-3 py-2 fs-6">
                    <i class="fas fa-flag me-1"></i><?= $statusText ?>
                </span>
            </div>
        </div>
        
        <!-- Section Informations de réservation -->
        <div class="mb-4">
            <h6 class="section-title">
                <i class="fas fa-info-circle me-2"></i>Informations de réservation
            </h6>
            
            <div class="reservation-detail-item d-flex align-items-center">
                <div class="detail-icon text-center me-3">
                    <i class="fas fa-users"></i>
                </div>
                <div class="d-flex flex-column flex-md-row w-100">
                    <div class="detail-label me-3">Nombre de personnes</div>
                    <div class="detail-value fw-semibold">
                        <?= $reservation['party_size'] ?> 
                        <span class="text-muted small">personne(s)</span>
                    </div>
                </div>
            </div>
            
            <div class="reservation-detail-item d-flex align-items-center">
                <div class="detail-icon text-center me-3">
                    <i class="fas fa-calendar-plus"></i>
                </div>
                <div class="d-flex flex-column flex-md-row w-100">
                    <div class="detail-label me-3">Date de création</div>
                    <div class="detail-value"><?= $createdAt ?></div>
                </div>
            </div>
            
            <?php if ($updatedAt): ?>
            <div class="reservation-detail-item d-flex align-items-center">
                <div class="detail-icon text-center me-3">
                    <i class="fas fa-edit"></i>
                </div>
                <div class="d-flex flex-column flex-md-row w-100">
                    <div class="detail-label me-3">Dernière modification</div>
                    <div class="detail-value"><?= $updatedAt ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Section Table assignée -->
        <div class="mb-4">
            <h6 class="section-title">
                <i class="fas fa-chair me-2"></i>Table assignée
            </h6>
            
            <?php if ($reservation['table_name']): ?>
                <div class="reservation-detail-item d-flex align-items-center">
                    <div class="detail-icon text-center me-3">
                        <i class="fas fa-table"></i>
                    </div>
                    <div class="d-flex flex-column flex-md-row w-100">
                        <div class="detail-label me-3">Nom de la table</div>
                        <div class="detail-value fw-semibold"><?= htmlspecialchars($reservation['table_name']) ?></div>
                    </div>
                </div>
                
                <div class="reservation-detail-item d-flex align-items-center">
                    <div class="detail-icon text-center me-3">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="d-flex flex-column flex-md-row w-100">
                        <div class="detail-label me-3">Capacité maximale</div>
                        <div class="detail-value">
                            <?= $reservation['table_capacity'] ?> personne(s)
                            <?php if ($reservation['party_size'] > $reservation['table_capacity']): ?>
                                <span class="badge bg-warning text-dark info-badge ms-2">
                                    <i class="fas fa-exclamation-triangle me-1"></i>Surcapacité
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php if ($reservation['table_location']): ?>
                <div class="reservation-detail-item d-flex align-items-center">
                    <div class="detail-icon text-center me-3">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="d-flex flex-column flex-md-row w-100">
                        <div class="detail-label me-3">Emplacement</div>
                        <div class="detail-value"><?= htmlspecialchars($reservation['table_location']) ?></div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($reservation['party_size'] > $reservation['table_capacity']): ?>
                <div class="capacity-warning d-flex align-items-start mt-2">
                    <i class="fas fa-exclamation-triangle text-warning me-2 mt-1"></i>
                    <div>
                        <strong>Attention :</strong> Le nombre de personnes réservées 
                        (<?= $reservation['party_size'] ?>) dépasse la capacité de la table 
                        (<?= $reservation['table_capacity'] ?>). 
                        <span class="text-muted small d-block mt-1">
                            Une table supplémentaire pourrait être nécessaire.
                        </span>
                    </div>
                </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="text-center py-3 border rounded bg-light">
                    <i class="fas fa-question-circle fa-2x text-muted mb-3"></i>
                    <p class="text-muted mb-0">Aucune table spécifique assignée pour le moment</p>
                    <small class="text-muted">Une table vous sera attribuée avant votre arrivée</small>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Section Demandes spéciales -->
        <?php if (!empty($reservation['special_requests'])): ?>
        <div class="mb-4">
            <h6 class="section-title">
                <i class="fas fa-sticky-note me-2"></i>Demandes spéciales
            </h6>
            
            <div class="border rounded p-3 bg-light">
                <div class="d-flex">
                    <div class="detail-icon text-center me-3">
                        <i class="fas fa-comment-alt"></i>
                    </div>
                    <div class="detail-value">
                        <?= nl2br(htmlspecialchars($reservation['special_requests'])) ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Section Informations client -->
        <div class="mb-4">
            <h6 class="section-title">
                <i class="fas fa-user me-2"></i>Vos informations
            </h6>
            
            <div class="reservation-detail-item d-flex align-items-center">
                <div class="detail-icon text-center me-3">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="d-flex flex-column flex-md-row w-100">
                    <div class="detail-label me-3">Nom complet</div>
                    <div class="detail-value"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Non disponible') ?></div>
                </div>
            </div>
            
            <div class="reservation-detail-item d-flex align-items-center">
                <div class="detail-icon text-center me-3">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="d-flex flex-column flex-md-row w-100">
                    <div class="detail-label me-3">Adresse email</div>
                    <div class="detail-value"><?= htmlspecialchars($_SESSION['user_email'] ?? 'Non disponible') ?></div>
                </div>
            </div>
            
            <?php if ($_SESSION['user_phone'] ?? false): ?>
            <div class="reservation-detail-item d-flex align-items-center">
                <div class="detail-icon text-center me-3">
                    <i class="fas fa-phone"></i>
                </div>
                <div class="d-flex flex-column flex-md-row w-100">
                    <div class="detail-label me-3">Téléphone</div>
                    <div class="detail-value"><?= htmlspecialchars($_SESSION['user_phone']) ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Boutons d'action -->
        <?php
        $reservationDateTime = strtotime($reservation['reservation_date'] . ' ' . $reservation['reservation_time']);
        $canCancel = ($reservation['status'] === 'pending' || $reservation['status'] === 'confirmed') 
                     && $reservationDateTime > time();
        $canEdit = ($reservation['status'] === 'pending') 
                   && $reservationDateTime > (time() + 3600);
        ?>
        
        <?php if ($canCancel || $canEdit): ?>
        <div class="mt-4 pt-3 border-top">
            <div class="d-flex flex-column flex-sm-row gap-2">
                <?php if ($canEdit): ?>
                <a href="nouvelle_reservation.php?edit=<?= $reservationId ?>" 
                   class="btn btn-primary flex-fill">
                    <i class="fas fa-edit me-2"></i>Modifier cette réservation
                </a>
                <?php endif; ?>
                
                <?php if ($canCancel): ?>
                <a href="reservations.php?action=cancel&id=<?= $reservationId ?>" 
                   class="btn btn-outline-danger flex-fill"
                   onclick="return confirm('Êtes-vous sûr de vouloir annuler votre réservation du <?= $dateShort ?> à <?= $time ?> ? Cette action est irréversible.')">
                    <i class="fas fa-times me-2"></i>Annuler la réservation
                </a>
                <?php endif; ?>
            </div>
            
            <?php if ($canEdit || $canCancel): ?>
            <div class="text-muted small mt-2">
                <i class="fas fa-info-circle me-1"></i>
                <?php if ($canEdit && $canCancel): ?>
                Vous pouvez modifier votre réservation jusqu'à 1h avant, ou l'annuler jusqu'au moment du rendez-vous.
                <?php elseif ($canEdit): ?>
                Vous pouvez modifier votre réservation jusqu'à 1h avant l'heure prévue.
                <?php elseif ($canCancel): ?>
                Vous pouvez annuler votre réservation jusqu'au moment du rendez-vous.
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Note pour les réservations passées -->
        <?php if ($isPast): ?>
        <div class="mt-3 pt-3 border-top">
            <div class="alert alert-light border">
                <div class="d-flex">
                    <i class="fas fa-history text-muted me-3 mt-1"></i>
                    <div>
                        <strong>Réservation passée</strong>
                        <p class="mb-0 small text-muted">
                            Cette réservation a déjà eu lieu. Vous ne pouvez plus la modifier ou l'annuler.
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php
    
} catch (Exception $e) {
    // En production, utiliser une erreur générique
    // En développement, afficher l'erreur
    if (ini_get('display_errors')) {
        echo '<div class="alert alert-danger">';
        echo '<h6>Erreur :</h6>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '</div>';
    } else {
        echo '<div class="alert alert-danger">Une erreur technique est survenue. Veuillez réessayer.</div>';
    }
    
    error_log("Erreur reservation_details.php: " . $e->getMessage());
}
?>