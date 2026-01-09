<?php
// new_reservation.php (version améliorée avec sélection de tables)
session_start();
require_once '../config/database.php';
require_once '../config/security.php';

if (!Security::isLoggedIn()) {
    Security::redirect('../auth/login.php');
}

$userId = $_SESSION['user_id'] ?? 0;

// Vérifier si on est en mode édition
$editMode = false;
$reservationToEdit = null;

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT r.*, t.table_name 
            FROM reservations r 
            LEFT JOIN tables t ON r.table_id = t.id
            WHERE r.id = ? AND r.customer_id = ? AND r.status = 'pending'
        ");
        $stmt->execute([$editId, $userId]);
        $reservationToEdit = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($reservationToEdit) {
            $editMode = true;
            
            // Vérifier qu'on peut encore modifier (au moins 1 heure avant)
            $reservationDateTime = strtotime($reservationToEdit['reservation_date'] . ' ' . $reservationToEdit['reservation_time']);
            if ($reservationDateTime < (time() + 3600)) {
                $_SESSION['error_message'] = "Impossible de modifier une réservation à moins d'une heure du rendez-vous";
                header("Location: reservations.php");
                exit;
            }
        }
    } catch (Exception $e) {
        error_log("Erreur récupération réservation: " . $e->getMessage());
    }
}

// Récupérer les informations utilisateur pour pré-remplir
if ($editMode && $reservationToEdit) {
    $user_name = $reservationToEdit['customer_name'] ?? '';
    $user_email = $reservationToEdit['customer_email'] ?? '';
    $default_date = $reservationToEdit['reservation_date'] ?? date('Y-m-d');
    $default_party_size = $reservationToEdit['party_size'] ?? 2;
    $table_id = $reservationToEdit['table_id'] ?? 0;
    $customer_phone = $reservationToEdit['customer_phone'] ?? '';
    $special_requests = $reservationToEdit['special_requests'] ?? '';
    $reservation_time = $reservationToEdit['reservation_time'] ?? '';
} else {
    $user_name = $_SESSION['user_name'] ?? '';
    $user_email = $_SESSION['user_email'] ?? '';
    $default_date = date('Y-m-d');
    $default_party_size = 2;
    $table_id = 0;
    $customer_phone = '';
    $special_requests = '';
    $reservation_time = '';
}

// Récupérer les tables disponibles depuis la base de données
$tables = [];
try {
    $stmt = $pdo->query("SELECT id, table_name, capacity, location, image, is_available FROM tables WHERE is_available = 1 ORDER BY capacity, table_name");
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // En cas d'erreur, on continue sans les tables
    error_log("Erreur lors de la récupération des tables: " . $e->getMessage());
}
?>
<?php include 'jenga.php'; ?>
    <style>
        .available-slot { cursor: pointer; }
        .available-slot:hover { background-color: #e8f5e9 !important; }
        .slot-selected { background-color: #4caf50 !important; color: white; }
        .slot-unavailable { opacity: 0.5; cursor: not-allowed; }
        #loading { display: none; }
        .table-option { padding: 8px 12px; }
        .table-info { font-size: 0.85rem; color: #6c757d; margin-left: 8px; }
        .table-capacity { 
            display: inline-block; 
            padding: 2px 8px; 
            background: #e9ecef; 
            border-radius: 12px; 
            font-size: 0.75rem; 
            margin-right: 5px;
        }
        .table-location { 
            display: inline-block; 
            padding: 2px 8px; 
            background: #d1ecf1; 
            border-radius: 12px; 
            font-size: 0.75rem; 
            margin-left: 5px; 
        }
        .table-available { 
            display: inline-block; 
            padding: 2px 8px; 
            background: #d4edda; 
            border-radius: 12px; 
            font-size: 0.75rem; 
            color: #155724;
            margin-left: 5px;
        }
        .table-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
            margin-right: 10px;
            border: 2px solid #dee2e6;
        }
        .table-item {
            display: flex;
            align-items: center;
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        .table-details {
            flex-grow: 1;
        }
        #tablePreview {
            margin-top: 10px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            display: none;
        }
        .table-preview-image {
            width: 100%;
            max-height: 200px;
            object-fit: cover;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .compatible-table {
            cursor: pointer;
            transition: all 0.3s;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 5px;
            border: 1px solid #dee2e6;
        }
        .compatible-table:hover {
            background-color: #f8f9fa;
            border-color: #0d6efd;
        }
        .compatible-table.selected {
            background-color: #e7f1ff;
            border-color: #0d6efd;
        }
        .table-icon {
            font-size: 1.5rem;
            margin-right: 10px;
            color: #6c757d;
        }
    </style>

<div id="container" class="bg-light">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <!-- MODIFIER LE TITRE -->
            <h1 class="h4 mb-0">
                <i class="fas fa-<?= $editMode ? 'edit' : 'calendar-plus' ?> me-2 text-primary"></i>
                <?= $editMode ? 'Modifier la réservation' : 'Nouvelle réservation' ?>
            </h1>
            <a class="btn btn-outline-secondary" href="reservations.php">
                <i class="fas fa-arrow-left me-1"></i>Retour
            </a>
        </div>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-3">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?= $_SESSION['error_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form id="reservationForm" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                    
                    <!-- AJOUTER LE CHAMP HIDDEN POUR L'ÉDITION -->
                    <?php if ($editMode && $reservationToEdit): ?>
                        <input type="hidden" name="reservation_id" value="<?= $reservationToEdit['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="col-md-6">
                        <label class="form-label">Nom complet *</label>
                        <input class="form-control" name="customer_name" value="<?= htmlspecialchars($user_name) ?>" required>
                        <small class="text-muted">Ce champ est pré-rempli avec votre nom</small>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control" name="customer_email" value="<?= htmlspecialchars($user_email) ?>" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Téléphone</label>
                        <input class="form-control" name="customer_phone" 
                               placeholder="Votre numéro de téléphone"
                               value="<?= htmlspecialchars($customer_phone) ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Date *</label>
                        <!-- MODIFIER LA VALEUR PAR DÉFAUT -->
                        <input type="date" class="form-control" name="reservation_date" 
                               min="<?= date('Y-m-d') ?>" 
                               value="<?= $default_date ?>" 
                               required id="datePicker">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Nombre de personnes *</label>
                        <!-- MODIFIER POUR PRÉ-SÉLECTIONNER -->
                        <select class="form-control" name="party_size" id="partySize" required>
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>" <?= ($i == $default_party_size) ? 'selected' : '' ?>>
                                    <?= $i ?> personne<?= $i > 1 ? 's' : '' ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label">Heure *</label>
                        <div id="timeSlots" class="d-flex flex-wrap gap-2 mb-3">
                            <div class="text-muted">Sélectionnez d'abord une date et le nombre de personnes</div>
                        </div>
                        <input type="hidden" name="reservation_time" id="selectedTime" required 
                               value="<?= htmlspecialchars($reservation_time) ?>">
                        <div id="loading" class="spinner-border spinner-border-sm text-primary" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Table préférée</label>
                        <select class="form-control" name="table_id" id="tableSelect">
                            <option value="">-- Aucune préférence --</option>
                            <?php foreach ($tables as $table): ?>
                                <option value="<?= $table['id'] ?>" 
                                        <?= ($editMode && $table['id'] == $table_id) ? 'selected' : '' ?>
                                        data-name="<?= htmlspecialchars($table['table_name']) ?>"
                                        data-capacity="<?= $table['capacity'] ?>"
                                        data-location="<?= htmlspecialchars($table['location']) ?>"
                                        data-image="<?= htmlspecialchars($table['image']) ?>">
                                    <?= htmlspecialchars($table['table_name']) ?> 
                                    <span class="table-info">
                                        <span class="table-capacity"><?= $table['capacity'] ?> pers.</span>
                                        <?php if (!empty($table['location'])): ?>
                                            <span class="table-location"><?= htmlspecialchars($table['location']) ?></span>
                                        <?php endif; ?>
                                        <span class="table-available">Disponible</span>
                                    </span>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="tablePreview">
                            <?php if (!empty($tables[0]['image'])): ?>
                                <img src="../uploads/tables/<?= htmlspecialchars($tables[0]['image']) ?>" 
                                     alt="Table <?= htmlspecialchars($tables[0]['table_name']) ?>" 
                                     class="table-preview-image">
                            <?php endif; ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <div><strong>Capacité:</strong> <span id="tableCapacity"></span> personnes</div>
                                    <div><strong>Emplacement:</strong> <span id="tableLocation"></span></div>
                                </div>
                                <div class="col-md-6">
                                    <div><strong>Statut:</strong> <span class="text-success">Disponible</span></div>
                                    <div><strong>ID:</strong> <span id="tableId"></span></div>
                                </div>
                            </div>
                        </div>
                        <small class="text-muted">Choisissez une table ou laissez "Aucune préférence"</small>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Tables compatibles avec votre groupe</label>
                        <div id="compatibleTables" class="alert alert-light">
                            <div class="small">
                                <?php foreach ($tables as $table): ?>
                                    <?php if ($table['capacity'] >= $default_party_size): ?>
                                        <div class="compatible-table" 
                                             data-table-name="<?= htmlspecialchars($table['table_name']) ?>"
                                             data-table-id="<?= $table['id'] ?>"
                                             data-capacity="<?= $table['capacity'] ?>"
                                             data-location="<?= htmlspecialchars($table['location']) ?>">
                                            <div class="table-item">
                                                <?php if (!empty($table['image'])): ?>
                                                    <img src="../uploads/tables/<?= htmlspecialchars($table['image']) ?>" 
                                                         alt="Table <?= htmlspecialchars($table['table_name']) ?>" 
                                                         class="table-image">
                                                <?php else: ?>
                                                    <i class="fas fa-table table-icon"></i>
                                                <?php endif; ?>
                                                <div class="table-details">
                                                    <strong><?= htmlspecialchars($table['table_name']) ?></strong>
                                                    <div>
                                                        <span class="table-capacity"><?= $table['capacity'] ?> pers.</span>
                                                        <?php if (!empty($table['location'])): ?>
                                                            <span class="table-location"><?= htmlspecialchars($table['location']) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label">Demandes spéciales</label>
                        <textarea class="form-control" name="special_requests" rows="3" 
                                  placeholder="Allergies, anniversaire, etc."><?= htmlspecialchars($special_requests) ?></textarea>
                    </div>
                    
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Les réservations doivent être faites au moins 2 heures à l'avance.
                        </div>
                    </div>
                    
                    <div class="col-12 d-flex justify-content-end gap-2">
                        <a href="reservations.php" class="btn btn-outline-secondary">Annuler</a>
                        <button class="btn btn-primary" type="submit" id="submitBtn">
                            <i class="fas fa-save me-1"></i>
                            <?= $editMode ? 'Mettre à jour' : 'Réserver' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('reservationForm');
            const datePicker = document.getElementById('datePicker');
            const partySize = document.getElementById('partySize');
            const timeSlots = document.getElementById('timeSlots');
            const selectedTime = document.getElementById('selectedTime');
            const loading = document.getElementById('loading');
            const submitBtn = document.getElementById('submitBtn');
            const tableSelect = document.getElementById('tableSelect');
            const tablePreview = document.getElementById('tablePreview');
            const compatibleTablesDiv = document.getElementById('compatibleTables');
            
            // Afficher les détails de la table sélectionnée
            tableSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                
                if (selectedOption.value) {
                    tablePreview.style.display = 'block';
                    document.getElementById('tableId').textContent = selectedOption.value || 'N/A';
                    document.getElementById('tableCapacity').textContent = selectedOption.dataset.capacity || 'Non spécifiée';
                    document.getElementById('tableLocation').textContent = selectedOption.dataset.location || 'Non spécifié';
                    
                    // Mettre à jour l'image de prévisualisation
                    const previewImage = tablePreview.querySelector('.table-preview-image');
                    if (selectedOption.dataset.image) {
                        previewImage.src = '../uploads/tables/' + selectedOption.dataset.image;
                        previewImage.style.display = 'block';
                    } else {
                        previewImage.style.display = 'none';
                    }
                } else {
                    tablePreview.style.display = 'none';
                }
                
                // Désélectionner toutes les tables compatibles
                document.querySelectorAll('.compatible-table').forEach(table => {
                    table.classList.remove('selected');
                });
            });
            
            // Permettre de sélectionner une table en cliquant sur la liste des tables compatibles
            document.querySelectorAll('.compatible-table').forEach(table => {
                table.addEventListener('click', function() {
                    const tableId = this.dataset.tableId;
                    const tableName = this.dataset.tableName;
                    const capacity = this.dataset.capacity;
                    const location = this.dataset.location;
                    
                    // Sélectionner l'option correspondante dans le select
                    tableSelect.value = tableId;
                    
                    // Déclencher le changement manuellement
                    tableSelect.dispatchEvent(new Event('change'));
                    
                    // Mettre en surbrillance la table sélectionnée
                    document.querySelectorAll('.compatible-table').forEach(t => {
                        t.classList.remove('selected');
                    });
                    this.classList.add('selected');
                    
                    // Vérifier si la capacité est suffisante
                    const partySizeValue = parseInt(partySize.value);
                    if (parseInt(capacity) < partySizeValue) {
                        showCapacityWarning(tableName, capacity, partySizeValue);
                    }
                });
            });
            
            function showCapacityWarning(tableName, tableCapacity, partySize) {
                const modal = `
                    <div class="modal fade" id="capacityWarningModal" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Attention : Capacité insuffisante</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        La table <strong>${tableName}</strong> a une capacité de <strong>${tableCapacity} personnes</strong>.
                                        Votre groupe est de <strong>${partySize} personnes</strong>.
                                    </div>
                                    <p>Souhaitez-vous continuer malgré tout ?</p>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Nous pouvons éventuellement ajouter une chaise supplémentaire.
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Changer de table</button>
                                    <button type="button" class="btn btn-primary" id="confirmReservation">Confirmer la réservation</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                // Ajouter le modal au DOM
                const modalDiv = document.createElement('div');
                modalDiv.innerHTML = modal;
                document.body.appendChild(modalDiv);
                
                const modalInstance = new bootstrap.Modal(document.getElementById('capacityWarningModal'));
                modalInstance.show();
                
                // Gérer la confirmation
                document.getElementById('confirmReservation').addEventListener('click', function() {
                    modalInstance.hide();
                    // Ajouter une note aux demandes spéciales
                    const specialRequests = form.querySelector('[name="special_requests"]');
                    const currentText = specialRequests.value;
                    specialRequests.value = currentText + (currentText ? '\n' : '') + 
                        `[NOTE] Table ${tableName} (capacité: ${tableCapacity}) pour ${partySize} personnes. Merci d'ajuster si possible.`;
                    
                    // Continuer avec le formulaire
                    submitForm();
                });
                
                // Supprimer le modal du DOM après fermeture
                document.getElementById('capacityWarningModal').addEventListener('hidden.bs.modal', function() {
                    this.remove();
                });
            }
            
            // Mettre à jour les tables compatibles quand le nombre de personnes change
            partySize.addEventListener('change', updateCompatibleTables);
            
            function updateCompatibleTables() {
                const partySizeValue = parseInt(partySize.value);
                const tables = document.querySelectorAll('.compatible-table');
                let hasCompatibleTables = false;
                
                tables.forEach(table => {
                    const capacity = parseInt(table.dataset.capacity);
                    if (capacity >= partySizeValue) {
                        table.style.display = 'block';
                        hasCompatibleTables = true;
                    } else {
                        table.style.display = 'none';
                    }
                });
                
                if (hasCompatibleTables) {
                    compatibleTablesDiv.className = 'alert alert-success';
                } else {
                    compatibleTablesDiv.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Aucune table disponible pour ${partySizeValue} personnes. 
                            Veuillez contacter le restaurant au <strong>01 23 45 67 89</strong>.
                        </div>
                    `;
                }
            }
            
            // Initialiser l'affichage des tables compatibles
            updateCompatibleTables();
            
            // Fonction pour charger les créneaux disponibles
            // function loadTimeSlots() {
            //     const date = datePicker.value;
            //     const size = partySize.value;
            //     const tableId = tableSelect.value;
                
            //     if (!date) return;
                
            //     timeSlots.innerHTML = '';
            //     loading.style.display = 'inline-block';
                
            //     // Désactiver le bouton de soumission pendant le chargement
            //     submitBtn.disabled = true;
                
            //     let url = `../admin/api/client_reservation_new.php?action=availability&date=${date}&party_size=${size}`;
            //     if (tableId) {
            //         url += `&table_id=${tableId}`;
            //     }
                
            //     fetch(url, {
            //             headers: {
            //                 'X-Requested-With': 'XMLHttpRequest'
            //             }
            //         })
            //         .then(response => response.json())
            //         .then(data => {
            //             loading.style.display = 'none';
            //             submitBtn.disabled = false;
                        
            //             if (data.success) {
            //                 if (data.available_slots && data.available_slots.length > 0) {
            //                     timeSlots.innerHTML = '';
            //                     data.available_slots.forEach(slot => {
            //                         const button = document.createElement('button');
            //                         button.type = 'button';
            //                         button.className = 'btn btn-outline-primary available-slot';
            //                         button.textContent = slot;
            //                         button.dataset.time = slot;
                                    
            //                         button.addEventListener('click', function() {
            //                             // Désélectionner tous les boutons
            //                             document.querySelectorAll('.available-slot').forEach(btn => {
            //                                 btn.classList.remove('btn-primary', 'slot-selected');
            //                                 btn.classList.add('btn-outline-primary');
            //                             });
                                        
            //                             // Sélectionner ce bouton
            //                             this.classList.remove('btn-outline-primary');
            //                             this.classList.add('btn-primary', 'slot-selected');
            //                             selectedTime.value = this.dataset.time;
                                        
            //                             // Valider le formulaire
            //                             form.querySelector('[name="reservation_time"]').setCustomValidity('');
            //                         });
                                    
            //                         timeSlots.appendChild(button);
            //                     });
                                
            //                     // Si on est en mode édition, sélectionner le créneau existant
            //                     <?php if ($editMode && !empty($reservation_time)): ?>
            //                         setTimeout(() => {
            //                             const existingSlot = document.querySelector(`[data-time="<?= $reservation_time ?>"]`);
            //                             if (existingSlot) {
            //                                 existingSlot.click();
            //                             }
            //                         }, 100);
            //                     <?php endif; ?>
            //                 } else {
            //                     timeSlots.innerHTML = '<div class="alert alert-warning w-100">Aucun créneau disponible pour cette date.</div>';
            //                     selectedTime.value = '';
            //                 }
            //             } else {
            //                 timeSlots.innerHTML = `<div class="alert alert-danger w-100">${data.message}</div>`;
            //             }
            //         })
            //         .catch(error => {
            //             loading.style.display = 'none';
            //             submitBtn.disabled = false;
            //             timeSlots.innerHTML = '<div class="alert alert-danger w-100">Erreur de chargement des créneaux</div>';
            //             console.error('Erreur:', error);
            //         });
            // }

            function loadTimeSlots() {
                const date = datePicker.value;
                const size = partySize.value;
                const tableId = tableSelect.value;
                
                console.log('loadTimeSlots appelée avec:', { date, size, tableId });
                
                if (!date) {
                    console.log('Date non spécifiée, arrêt');
                    return;
                }
                
                timeSlots.innerHTML = '';
                loading.style.display = 'inline-block';
                submitBtn.disabled = true;
                
                let url = `../admin/api/client_reservation_new.php?action=availability&date=${date}&party_size=${size}`;
                if (tableId) {
                    url += `&table_id=${tableId}`;
                }
                
                console.log('URL appelée:', url);
                
                fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    console.log('Statut HTTP:', response.status, response.statusText);
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.text();
                })
                .then(text => {
                    console.log('Réponse brute:', text);
                    try {
                        const data = JSON.parse(text);
                        console.log('Données JSON:', data);
                        
                        loading.style.display = 'none';
                        submitBtn.disabled = false;
                        
                        if (data.success) {
                            if (data.available_slots && data.available_slots.length > 0) {
                                console.log('Créneaux disponibles:', data.available_slots.length);
                                timeSlots.innerHTML = '';
                                data.available_slots.forEach(slot => {
                                    const button = document.createElement('button');
                                    button.type = 'button';
                                    button.className = 'btn btn-outline-primary available-slot';
                                    button.textContent = slot;
                                    button.dataset.time = slot;
                                    
                                    button.addEventListener('click', function() {
                                        document.querySelectorAll('.available-slot').forEach(btn => {
                                            btn.classList.remove('btn-primary', 'slot-selected');
                                            btn.classList.add('btn-outline-primary');
                                        });
                                        this.classList.remove('btn-outline-primary');
                                        this.classList.add('btn-primary', 'slot-selected');
                                        selectedTime.value = this.dataset.time;
                                        form.querySelector('[name="reservation_time"]').setCustomValidity('');
                                    });
                                    
                                    timeSlots.appendChild(button);
                                });
                                
                                <?php if ($editMode && !empty($reservation_time)): ?>
                                setTimeout(() => {
                                    const existingSlot = document.querySelector(`[data-time="<?= $reservation_time ?>"]`);
                                    if (existingSlot) {
                                        existingSlot.click();
                                    }
                                }, 100);
                                <?php endif; ?>
                            } else {
                                console.log('Aucun créneau disponible');
                                timeSlots.innerHTML = '<div class="alert alert-warning w-100">Aucun créneau disponible pour cette date.</div>';
                                selectedTime.value = '';
                            }
                        } else {
                            console.log('Erreur API:', data.message);
                            timeSlots.innerHTML = `<div class="alert alert-danger w-100">${data.message || 'Erreur inconnue'}</div>`;
                        }
                    } catch (e) {
                        console.error('Erreur parsing JSON:', e, 'Texte:', text);
                        throw new Error('Réponse non JSON: ' + text.substring(0, 100));
                    }
                })
                .catch(error => {
                    console.error('Erreur fetch:', error);
                    loading.style.display = 'none';
                    submitBtn.disabled = false;
                    timeSlots.innerHTML = '<div class="alert alert-danger w-100">Erreur de chargement: ' + error.message + '</div>';
                });
            }
            
            // Écouter les changements de date et de nombre de personnes
            datePicker.addEventListener('change', loadTimeSlots);
            partySize.addEventListener('change', loadTimeSlots);
            tableSelect.addEventListener('change', loadTimeSlots);
            
            // Charger les créneaux au chargement de la page
            loadTimeSlots();
            
            // Fonction pour soumettre le formulaire
            function submitForm() {
                // Préparer les données
                const formData = new FormData(form);
                
                // Déterminer l'action (create ou update)
                const action = <?= $editMode ? "'update'" : "'create'" ?>;
                formData.append('action', action);
                
                // Désactiver le bouton pendant l'envoi
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>En cours...';
                
                // Envoyer la requête
                const xhr = new XMLHttpRequest();
                xhr.open('POST', '../admin/api/client_reservation_new.php');
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                
                xhr.onload = function() {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-save me-1"></i>' + (<?= $editMode ? "'Mettre à jour'" : "'Réserver'" ?>);
                    
                    // Debug: afficher la réponse brute
                    console.log('Réponse brute:', xhr.responseText);
                    
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            
                            if (response.success) {
                                // Afficher un message de succès
                                const alert = document.createElement('div');
                                alert.className = 'alert alert-success alert-dismissible fade show';
                                alert.innerHTML = `
                                    <i class="fas fa-check-circle me-2"></i>
                                    ${response.message}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                `;
                                
                                // Insérer l'alerte avant le formulaire
                                form.parentNode.insertBefore(alert, form);
                                
                                // Rediriger après 2 secondes
                                setTimeout(() => {
                                    window.location.href = 'reservations.php';
                                }, 2000);
                            } else {
                                alert('Erreur : ' + (response.message || 'Une erreur est survenue'));
                            }
                        } catch (error) {
                            console.error('Erreur de parsing JSON:', error, 'Réponse:', xhr.responseText);
                            alert('Erreur lors du traitement de la réponse. Voir console pour détails.');
                        }
                    } else {
                        console.error('Erreur HTTP:', xhr.status, xhr.statusText, 'Réponse:', xhr.responseText);
                        alert('Erreur HTTP ' + xhr.status + ': ' + xhr.statusText);
                    }
                };
                
                xhr.onerror = function() {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-save me-1"></i>' + (<?= $editMode ? "'Mettre à jour'" : "'Réserver'" ?>);
                    console.error('Erreur réseau');
                    alert('Erreur réseau. Veuillez réessayer.');
                };
                
                xhr.send(formData);
            }
            
            // Validation du formulaire
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Vérifier qu'un créneau horaire est sélectionné
                if (!selectedTime.value) {
                    alert('Veuillez sélectionner un créneau horaire');
                    return;
                }
                
                // Vérifier si une table est sélectionnée et si sa capacité est suffisante
                if (tableSelect.value) {
                    const selectedOption = tableSelect.options[tableSelect.selectedIndex];
                    const tableCapacity = parseInt(selectedOption.dataset.capacity) || 0;
                    const partySizeValue = parseInt(partySize.value);
                    
                    if (tableCapacity > 0 && partySizeValue > tableCapacity) {
                        showCapacityWarning(
                            selectedOption.dataset.name, 
                            tableCapacity, 
                            partySizeValue
                        );
                        return; // La soumission sera gérée par la modal
                    }
                }
                
                // Vérifier la date/heure (au moins 2 heures à l'avance)
                const selectedDate = datePicker.value;
                const selectedDateTime = new Date(selectedDate + 'T' + selectedTime.value);
                const now = new Date();
                const twoHoursLater = new Date(now.getTime() + 2 * 60 * 60 * 1000);
                
                if (selectedDateTime < twoHoursLater) {
                    alert('La réservation doit être faite au moins 2 heures à l\'avance');
                    return;
                }
                
                // Soumettre le formulaire
                submitForm();
            });
            
            // Empêcher la sélection de dates passées
            const today = new Date().toISOString().split('T')[0];
            datePicker.min = today;
            
            // Si la date actuelle est après 23h, on ne peut plus réserver pour aujourd'hui
            const now = new Date();
            if (now.getHours() >= 23) {
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                datePicker.min = tomorrow.toISOString().split('T')[0];
                if (!<?= $editMode ? 'true' : 'false' ?>) {
                    datePicker.value = datePicker.min;
                }
            }
        });
    </script>