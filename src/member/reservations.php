<?php
session_start();
require_once '../config/database.php';
require_once '../config/security.php';

if (!Security::isLoggedIn()) { 
    Security::redirect('../auth/login.php'); 
}

$userId = $_SESSION['user_id'];

// Gérer les actions (annulation, modification)
$action = $_GET['action'] ?? '';
$reservationId = $_GET['id'] ?? 0;

if ($action === 'cancel' && $reservationId > 0) {
    try {
        $stmt = $pdo->prepare("UPDATE reservations SET status = 'cancelled' WHERE id = ? AND customer_id = ?");
        $stmt->execute([$reservationId, $userId]);
        $_SESSION['success_message'] = "Réservation annulée avec succès";
        header("Location: reservations.php");
        exit;
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erreur lors de l'annulation: " . $e->getMessage();
        header("Location: reservations.php");
        exit;
    }
}

// Récupérer les réservations de l'utilisateur
try {
    $stmt = $pdo->prepare("
        SELECT r.*, t.table_name, t.capacity as table_capacity
        FROM reservations r 
        LEFT JOIN tables t ON r.table_id = t.id
        WHERE r.customer_id = ? 
        ORDER BY r.reservation_date DESC, r.reservation_time DESC
    ");
    $stmt->execute([$userId]);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Erreur: " . $e->getMessage();
    error_log("Erreur réservations: " . $e->getMessage());
}
?>
<?php include 'jenga.php'; ?>

<style>
    .containere {
    margin-left: 265px;
    margin-top: 40px;
    overflow-x: hidden; /* Empêche le défilement horizontal */
    /* max-width: 100%; Assure que le contenu ne dépasse pas */
}
</style>



<div id="container" class="container containere overflow-x-hidden">
    <div class="row" style ="width:100%">
        <!-- Contenu principal -->
        <div class="col-md-11 col-lg-10 reservation-content">
            <div class="container py-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h1 class="h4 mb-0"><i class="fas fa-calendar me-2 text-primary"></i>Mes réservations</h1>
                    <div>
                        <a class="btn btn-outline-secondary me-2" href="dashboard.php"><i class="fas fa-arrow-left me-1"></i>Retour</a>
                        <a class="btn btn-primary" href="nouvelle_reservation.php"><i class="fas fa-plus me-1"></i>Réserver</a>
                    </div>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i>
                        <?= $_SESSION['success_message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= $_SESSION['error_message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger mb-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= $error_message ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <?php if (empty($reservations)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-calendar-times fa-4x mb-3 text-light"></i>
                                <h5 class="mb-2">Aucune réservation</h5>
                                <p class="mb-0">Vous n'avez pas encore fait de réservation.</p>
                                <a href="nouvelle_reservation.php" class="btn btn-primary mt-3">
                                    <i class="fas fa-plus me-1"></i>Faire une réservation
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Heure</th>
                                            <th>Personnes</th>
                                            <th>Table</th>
                                            <th>Statut</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reservations as $r): 
                                            $reservationDateTime = strtotime($r['reservation_date'] . ' ' . $r['reservation_time']);
                                            $canCancel = ($r['status'] === 'pending' || $r['status'] === 'confirmed') && $reservationDateTime > time();
                                            $canEdit = ($r['status'] === 'pending') && $reservationDateTime > (time() + 3600); // 1 heure avant
                                        ?>
                                            <tr>
                                                <td>
                                                    <?= date('d/m/Y', strtotime($r['reservation_date'])) ?>
                                                    <?php if (strtotime($r['reservation_date']) == strtotime('today')): ?>
                                                        <span class="badge bg-info">Aujourd'hui</span>
                                                    <?php elseif (strtotime($r['reservation_date']) == strtotime('tomorrow')): ?>
                                                        <span class="badge bg-primary">Demain</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= date('H:i', strtotime($r['reservation_time'])) ?></td>
                                                <td>
                                                    <?= (int)$r['party_size'] ?> pers.
                                                    <?php if ($r['table_capacity'] && $r['party_size'] > $r['table_capacity']): ?>
                                                        <br><small class="text-warning"><i class="fas fa-exclamation-triangle"></i> Capacité dépassée</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?= htmlspecialchars($r['table_name'] ?? '-') ?>
                                                    <?php if (!empty($r['table_capacity'])): ?>
                                                        <br><small class="text-muted">(<?= $r['table_capacity'] ?> pers.)</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= 
                                                        $r['status'] === 'confirmed' ? 'success' : 
                                                        ($r['status'] === 'cancelled' ? 'danger' : 
                                                        ($r['status'] === 'completed' ? 'secondary' : 'warning')) ?>">
                                                        <?= $r['status'] === 'confirmed' ? 'Confirmée' : 
                                                        ($r['status'] === 'cancelled' ? 'Annulée' : 
                                                        ($r['status'] === 'completed' ? 'Terminée' : 'En attente')) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <!-- Bouton Voir - sans AJAX d'abord -->
                                                        <!-- <button type="button" 
                                                                class="btn btn-outline-info view-details-btn" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#viewReservationModal"
                                                                data-id="<?= $r['id'] ?>"
                                                                data-date="<?= date('d/m/Y', strtotime($r['reservation_date'])) ?>"
                                                                data-time="<?= date('H:i', strtotime($r['reservation_time'])) ?>"
                                                                data-party="<?= $r['party_size'] ?>"
                                                                data-table="<?= htmlspecialchars($r['table_name'] ?? 'Non assignée') ?>"
                                                                data-capacity="<?= $r['table_capacity'] ?>"
                                                                data-status="<?= $r['status'] ?>"
                                                                data-requests="<?= htmlspecialchars($r['special_requests'] ?? '') ?>"
                                                                data-created="<?= date('d/m/Y H:i', strtotime($r['created_at'])) ?>"
                                                                title="Voir les détails">
                                                            <i class="fas fa-eye"></i>
                                                        </button> -->

                                                        <!-- Bouton Voir avec Ajax -->

                                                        <button type="button" 
                                                                class="btn btn-outline-info view-details-btn" 
                                                                data-id="<?= $r['id'] ?>"
                                                                title="Voir les détails">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        
                                                        <?php if ($canEdit): ?>
                                                            <a href="nouvelle_reservation.php?edit=<?= $r['id'] ?>" 
                                                            class="btn btn-outline-primary" 
                                                            title="Modifier">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($canCancel): ?>
                                                            <a href="reservations.php?action=cancel&id=<?= $r['id'] ?>" 
                                                            class="btn btn-outline-danger cancel-btn"
                                                            onclick="return confirmCancel('<?= date('d/m/Y H:i', $reservationDateTime) ?>')"
                                                            title="Annuler">
                                                                <i class="fas fa-times"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (!$canCancel && $r['status'] === 'cancelled'): ?>
                                                            <span class="text-muted small">Annulée</span>
                                                        <?php elseif (!$canCancel && $r['status'] === 'completed'): ?>
                                                            <span class="text-muted small">Terminée</span>
                                                        <?php endif; ?>
                                                    </div>
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
        </div>
    </div>
</div>

<!-- Modal pour voir les détails de la réservation sans ajax-->
<!-- <div class="modal fade" id="viewReservationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails de la réservation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="reservationDetails">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted">Informations générales</h6>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <strong><i class="fas fa-calendar me-2 text-primary"></i>Date :</strong>
                                    <span id="detailDate"></span>
                                    <span id="detailDateBadge"></span>
                                </li>
                                <li class="mb-2">
                                    <strong><i class="fas fa-clock me-2 text-primary"></i>Heure :</strong>
                                    <span id="detailTime"></span>
                                </li>
                                <li class="mb-2">
                                    <strong><i class="fas fa-users me-2 text-primary"></i>Nombre de personnes :</strong>
                                    <span id="detailParty"></span>
                                </li>
                                <li class="mb-2">
                                    <strong><i class="fas fa-flag me-2 text-primary"></i>Statut :</strong>
                                    <span id="detailStatus"></span>
                                </li>
                                <li class="mb-2">
                                    <strong><i class="fas fa-calendar-plus me-2 text-primary"></i>Créée le :</strong>
                                    <span id="detailCreated"></span>
                                </li>
                            </ul>
                        </div>
                        
                        <div class="col-md-6">
                            <h6 class="text-muted">Table assignée</h6>
                            <div id="detailTableInfo">
                                
                            </div>
                        </div>
                    </div>
                    
                    <div id="detailRequests" class="mt-3" style="display: none;">
                        <h6 class="text-muted">Demandes spéciales</h6>
                        <div class="alert alert-light border" id="detailRequestsText"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div> -->

<!-- Modal pour voir les détails de la réservation  avec ajax-->
<div class="modal fade" id="viewReservationModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails de la réservation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Le contenu sera chargé dynamiquement via AJAX -->
                <div id="reservationDetails"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Fonction pour confirmer l'annulation - SIMPLIFIÉE
    // function confirmCancel(dateTime) {
    //     return confirm(`Voulez-vous vraiment annuler votre réservation du ${dateTime} ?\n\nCette action est irréversible.`);
    // }
    
    // Gestion de la visualisation des détails sans ajax
    // document.addEventListener('DOMContentLoaded', function() {
    //     const viewModal = document.getElementById('viewReservationModal');
    //     const viewButtons = document.querySelectorAll('.view-details-btn');
        
    //     viewButtons.forEach(button => {
    //         button.addEventListener('click', function() {
    //             // Récupérer les données du bouton
    //             const reservationId = this.getAttribute('data-id');
    //             const date = this.getAttribute('data-date');
    //             const time = this.getAttribute('data-time');
    //             const partySize = this.getAttribute('data-party');
    //             const tableName = this.getAttribute('data-table');
    //             const tableCapacity = this.getAttribute('data-capacity');
    //             const status = this.getAttribute('data-status');
    //             const specialRequests = this.getAttribute('data-requests');
    //             const createdAt = this.getAttribute('data-created');
                
    //             // Déterminer le texte et la couleur du statut
    //             let statusText, statusClass;
    //             switch(status) {
    //                 case 'confirmed':
    //                     statusText = 'Confirmée';
    //                     statusClass = 'success';
    //                     break;
    //                 case 'cancelled':
    //                     statusText = 'Annulée';
    //                     statusClass = 'danger';
    //                     break;
    //                 case 'completed':
    //                     statusText = 'Terminée';
    //                     statusClass = 'secondary';
    //                     break;
    //                 default:
    //                     statusText = 'En attente';
    //                     statusClass = 'warning';
    //             }
                
    //             // Déterminer si c'est aujourd'hui ou demain
    //             const today = new Date();
    //             const todayStr = today.getDate().toString().padStart(2, '0') + '/' + 
    //                             (today.getMonth() + 1).toString().padStart(2, '0') + '/' + 
    //                             today.getFullYear();
                
    //             const tomorrow = new Date();
    //             tomorrow.setDate(tomorrow.getDate() + 1);
    //             const tomorrowStr = tomorrow.getDate().toString().padStart(2, '0') + '/' + 
    //                                (tomorrow.getMonth() + 1).toString().padStart(2, '0') + '/' + 
    //                                tomorrow.getFullYear();
                
    //             let dateBadge = '';
    //             if (date === todayStr) {
    //                 dateBadge = '<span class="badge bg-info ms-2">Aujourd\'hui</span>';
    //             } else if (date === tomorrowStr) {
    //                 dateBadge = '<span class="badge bg-primary ms-2">Demain</span>';
    //             }
                
    //             // Mettre à jour les informations dans le modal
    //             document.getElementById('detailDate').textContent = date;
    //             document.getElementById('detailDateBadge').innerHTML = dateBadge;
    //             document.getElementById('detailTime').textContent = time;
    //             document.getElementById('detailParty').textContent = partySize + ' personnes';
    //             document.getElementById('detailCreated').textContent = createdAt;
                
    //             // Mettre à jour le statut
    //             const statusSpan = document.getElementById('detailStatus');
    //             statusSpan.innerHTML = `<span class="badge bg-${statusClass}">${statusText}</span>`;
                
    //             // Mettre à jour les informations de table
    //             const tableInfoDiv = document.getElementById('detailTableInfo');
    //             if (tableName && tableName !== 'Non assignée') {
    //                 let tableHtml = `
    //                     <div class="card border-primary">
    //                         <div class="card-body">
    //                             <h5 class="card-title">${tableName}</h5>
    //                             <p class="card-text">
    //                                 <i class="fas fa-chair me-1"></i>
    //                                 Capacité : ${tableCapacity} personnes
    //                             </p>`;
                    
    //                 // Avertissement si le nombre de personnes dépasse la capacité
    //                 if (parseInt(tableCapacity) > 0 && parseInt(partySize) > parseInt(tableCapacity)) {
    //                     tableHtml += `
    //                         <div class="alert alert-warning mt-2 p-2">
    //                             <i class="fas fa-exclamation-triangle me-1"></i>
    //                             Le nombre de personnes (${partySize}) dépasse la capacité de la table (${tableCapacity})
    //                         </div>`;
    //                 }
                    
    //                 tableHtml += `</div></div>`;
    //                 tableInfoDiv.innerHTML = tableHtml;
    //             } else {
    //                 tableInfoDiv.innerHTML = `
    //                     <div class="alert alert-info">
    //                         <i class="fas fa-info-circle me-2"></i>
    //                         Aucune table spécifique assignée pour le moment
    //                     </div>`;
    //             }
                
    //             // Mettre à jour les demandes spéciales
    //             const requestsDiv = document.getElementById('detailRequests');
    //             const requestsText = document.getElementById('detailRequestsText');
                
    //             if (specialRequests && specialRequests.trim() !== '') {
    //                 requestsDiv.style.display = 'block';
    //                 requestsText.innerHTML = `<i class="fas fa-sticky-note me-2 text-muted"></i>${specialRequests.replace(/\n/g, '<br>')}`;
    //             } else {
    //                 requestsDiv.style.display = 'none';
    //             }
    //         });
    //     });
        
    //     // Afficher les réservations passées avec un style différent
    //     const rows = document.querySelectorAll('tbody tr');
    //     const now = new Date();
        
    //     rows.forEach(row => {
    //         const dateText = row.cells[0].textContent.trim();
    //         const timeText = row.cells[1].textContent.trim();
            
    //         // Convertir la date au format YYYY-MM-DD
    //         const dateParts = dateText.split('/');
    //         if (dateParts.length === 3) {
    //             const dateStr = `${dateParts[2]}-${dateParts[1]}-${dateParts[0]} ${timeText}`;
    //             const reservationDate = new Date(dateStr);
                
    //             if (reservationDate < now) {
    //                 row.style.opacity = '0.7';
    //                 row.style.backgroundColor = '#f8f9fa';
    //             }
    //         }
    //     });
    // });

    // Fonction pour confirmer l'annulation
    function confirmCancel(dateTime) {
        return confirm(`Voulez-vous vraiment annuler votre réservation du ${dateTime} ?\n\nCette action est irréversible.`);
    }

    // Gestion de la visualisation des détails AVEC AJAX
    document.addEventListener('DOMContentLoaded', function() {
        const viewModal = new bootstrap.Modal(document.getElementById('viewReservationModal'));
        const detailsContainer = document.getElementById('reservationDetails');
        
        // Gestion des boutons "Voir" avec AJAX
        document.querySelectorAll('.view-details-btn').forEach(button => {
            button.addEventListener('click', async function() {
                const reservationId = this.getAttribute('data-id');
                
                // Afficher le spinner de chargement
                detailsContainer.innerHTML = `
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                        <p class="mt-2">Chargement des détails...</p>
                    </div>
                `;
                
                // Afficher le modal
                viewModal.show();
                
                try {
                    // Charger les détails via AJAX
                    const response = await fetch(`reservation_details.php?id=${reservationId}`);
                    
                    
                    if (!response.ok) {
                        throw new Error(`Erreur HTTP: ${response.status}`);
                    }
                    
                    const html = await response.text();
                    detailsContainer.innerHTML = html;
                    
                } catch (error) {
                    detailsContainer.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Erreur lors du chargement des détails.<br>
                            <small>${error.message}</small>
                        </div>
                        <div class="text-center mt-3">
                            <button class="btn btn-primary" onclick="location.reload()">
                                <i class="fas fa-redo me-1"></i>Réessayer
                            </button>
                        </div>
                    `;
                }
            });
        });
        
        // Afficher les réservations passées avec un style différent
        const rows = document.querySelectorAll('tbody tr');
        const now = new Date();
        
        rows.forEach(row => {
            const dateText = row.cells[0].textContent.trim();
            const timeText = row.cells[1].textContent.trim();
            
            // Convertir la date au format YYYY-MM-DD
            const dateParts = dateText.split('/');
            if (dateParts.length === 3) {
                const dateStr = `${dateParts[2]}-${dateParts[1]}-${dateParts[0]} ${timeText}`;
                const reservationDate = new Date(dateStr);
                
                if (reservationDate < now) {
                    row.style.opacity = '0.7';
                    row.style.backgroundColor = '#f8f9fa';
                }
            }
        });
    });
</script>