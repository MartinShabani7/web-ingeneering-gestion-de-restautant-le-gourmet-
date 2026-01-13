<?php
include 'includes/nav_sidebar.php';
require_once '../config/database.php';
require_once '../config/security.php';

// Vérification de l'authentification et des droits admin
if (!Security::isAdmin()) {
    Security::redirect('../index.php');
}

$success = $error = '';

// Actions de modération
if (isset($_POST['action']) && isset($_POST['testimonial_id'])) {
    $testimonialId = (int)$_POST['testimonial_id'];
    $action = $_POST['action'];
    
    try {
        switch ($action) {
            case 'approve':
                $stmt = $pdo->prepare("UPDATE testimonials SET status = 'approved', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$testimonialId]);
                $success = "Témoignage approuvé avec succès.";
                break;
                
            case 'reject':
                $stmt = $pdo->prepare("UPDATE testimonials SET status = 'rejected', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$testimonialId]);
                $success = "Témoignage rejeté avec succès.";
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM testimonials WHERE id = ?");
                $stmt->execute([$testimonialId]);
                $success = "Témoignage supprimé avec succès.";
                break;
                
            case 'toggle':
                $stmt = $pdo->prepare("SELECT status FROM testimonials WHERE id = ?");
                $stmt->execute([$testimonialId]);
                $currentStatus = $stmt->fetchColumn();
                
                $newStatus = $currentStatus === 'approved' ? 'rejected' : 'approved';
                $stmt = $pdo->prepare("UPDATE testimonials SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$newStatus, $testimonialId]);
                $success = "Statut du témoignage modifié avec succès.";
                break;
        }
    } catch (PDOException $e) {
        $error = "Erreur lors de la modification du témoignage.";
    }
}

// Récupération des témoignages avec filtres
$statusFilter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

$query = "
    SELECT t.*, 
           u.first_name, 
           u.last_name, 
           u.email,
           u.phone,
           u.avatar
    FROM testimonials t
    INNER JOIN users u ON t.user_id = u.id
    WHERE 1=1
";

$params = [];

if ($statusFilter !== 'all') {
    $query .= " AND t.status = ?";
    $params[] = $statusFilter;
}

if (!empty($search)) {
    $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR t.comment LIKE ? OR u.email LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$query .= " ORDER BY t.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$testimonials = $stmt->fetchAll();

// Statistiques
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM testimonials
");
$stats = $stmt->fetch();
?>

    <style>
        #container{
            margin-top:80px;
            margin-left: 270px;
            position: fixed;
            scrol
        }
        .stats-card {
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            color: white;
            margin-bottom: 15px;
        }
        .stats-total { background: linear-gradient(45deg, #007bff, #6610f2); }
        .stats-pending { background: linear-gradient(45deg, #ffc107, #fd7e14); }
        .stats-approved { background: linear-gradient(45deg, #28a745, #20c997); }
        .stats-rejected { background: linear-gradient(45deg, #dc3545, #e83e8c); }
        
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .user-avatar-lg {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(0,0,0,0.02);
        }
        
        .comment-preview {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 0.85rem;
        }
        
        /* Toggle switch compact */
        .switch {
            position: relative;
            display: inline-block;
            width: 40px;
            height: 20px;
        }
        
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 20px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 2px;
            bottom: 2px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: #28a745;
        }
        
        input:checked + .slider:before {
            transform: translateX(20px);
        }
        
        /* Étoiles compactes */
        .rating-stars {
            color: #ffc107;
            font-size: 0.8rem;
            line-height: 1;
        }
        
        .rating-stars-lg {
            color: #ffc107;
            font-size: 1.2rem;
        }
        
        /* Badges */
        .badge-pending { 
            background-color: #ffc107; 
            color: #000; 
            font-size: 0.7rem;
        }
        .badge-approved { 
            background-color: #28a745; 
            font-size: 0.7rem;
        }
        .badge-rejected { 
            background-color: #dc3545; 
            font-size: 0.7rem;
        }
        
        /* Table compacte */
        .table-compact th,
        .table-compact td {
            padding: 0.5rem;
            vertical-align: middle;
            font-size: 0.85rem;
        }
        
        .table-compact th {
            font-weight: 600;
            background-color: #f8f9fa;
        }
        
        /* Boutons actions */
        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
        
        /* Textes réduits */
        .text-small {
            font-size: 0.8rem;
        }
        
        .text-xsmall {
            font-size: 0.7rem;
        }
        
        /* Modal fixes */
        .modal-content {
            border: none;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .modal-header {
            border-bottom: 1px solid #dee2e6;
            background-color: #f8f9fa;
            border-radius: 10px 10px 0 0;
        }
        
        /* Alertes avec animation de disparition */
        .alert-auto-dismiss {
            transition: opacity 0.5s ease-in-out;
        }
        
        .alert-fade-out {
            opacity: 0;
        }
    </style>

<div id="container" class="container containere overflow-x-hidden">
    <div class="row" style ="width:100%">
            <!-- Contenu principal -->
            <div class="col-md-11 col-lg-11 temoignages-content">
                <!-- En-tête -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h4">
                        <i class="fas fa-star me-2"></i>Gestion des Témoignages
                    </h1>
                    <div class="btn-group">
                        <a href="../index.php" class="btn btn-outline-secondary btn-sm" target="_blank">
                            <i class="fas fa-external-link-alt me-1"></i>Voir le site
                        </a>
                    </div>
                </div>

                <!-- Messages d'alerte -->
                <div id="alerts-container">
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show alert-auto-dismiss" role="alert" data-auto-dismiss="3000">
                            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show alert-auto-dismiss" role="alert" data-auto-dismiss="3000">
                            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Statistiques -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="stats-card stats-total">
                            <h5 class="mb-1"><?= $stats['total'] ?></h5>
                            <p class="mb-0 text-small">Total témoignages</p>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="stats-card stats-pending">
                            <h5 class="mb-1"><?= $stats['pending'] ?></h5>
                            <p class="mb-0 text-small">En attente</p>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="stats-card stats-approved">
                            <h5 class="mb-1"><?= $stats['approved'] ?></h5>
                            <p class="mb-0 text-small">Approuvés</p>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="stats-card stats-rejected">
                            <h5 class="mb-1"><?= $stats['rejected'] ?></h5>
                            <p class="mb-0 text-small">Rejetés</p>
                        </div>
                    </div>
                </div>

                <!-- Filtres et recherche -->
                <div class="card mb-4">
                    <div class="card-body py-3">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-5">
                                <label for="status" class="form-label text-small mb-1">Filtrer par statut :</label>
                                <select name="status" id="status" class="form-select form-select-sm">
                                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Tous les statuts</option>
                                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>En attente</option>
                                    <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approuvés</option>
                                    <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejetés</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label for="search" class="form-label text-small mb-1">Rechercher :</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" name="search" id="search" class="form-control" 
                                           placeholder="Nom, email ou contenu..." value="<?= htmlspecialchars($search) ?>">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i>
                                    </button>
                                    <a href="testimonials.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-redo"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="d-grid">
                                    <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                                        <i class="fas fa-print me-1"></i>Imprimer
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tableau des témoignages -->
                <div class="card">
                    <div class="card-header py-2">
                        <h6 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            Liste des témoignages (<?= count($testimonials) ?>)
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($testimonials)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-star fa-2x text-muted mb-3"></i>
                                <h6 class="text-muted">Aucun témoignage trouvé</h6>
                                <p class="text-muted text-small">Aucun témoignage ne correspond à vos critères de recherche.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-hover table-compact mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="40">Photo</th>
                                            <th width="120">Nom Complet</th>
                                            <th width="150">Email</th>
                                            <th width="100">Téléphone</th>
                                            <th width="80">Note</th>
                                            <th width="200">Commentaire</th>
                                            <th width="100">Statut</th>
                                            <th width="90">Date</th>
                                            <th width="120">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($testimonials as $testimonial): ?>
                                            <tr>
                                                <!-- Photo -->
                                                <td>
                                                    <?php if ($testimonial['avatar']): ?>
                                                        <img src="../uploads/avatars/<?= htmlspecialchars($testimonial['avatar']) ?>" 
                                                             alt="Avatar" class="user-avatar">
                                                    <?php else: ?>
                                                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center user-avatar">
                                                            <i class="fas fa-user text-white" style="font-size: 0.8rem;"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                
                                                <!-- Nom Complet -->
                                                <td>
                                                    <div class="text-small fw-medium">
                                                        <?= htmlspecialchars($testimonial['first_name'] . ' ' . $testimonial['last_name']) ?>
                                                    </div>
                                                </td>
                                                
                                                <!-- Email -->
                                                <td>
                                                    <a href="mailto:<?= htmlspecialchars($testimonial['email']) ?>" class="text-decoration-none text-small">
                                                        <?= htmlspecialchars($testimonial['email']) ?>
                                                    </a>
                                                </td>
                                                
                                                <!-- Téléphone -->
                                                <td>
                                                    <?php if ($testimonial['phone']): ?>
                                                        <a href="tel:<?= htmlspecialchars($testimonial['phone']) ?>" class="text-decoration-none text-small">
                                                            <?= htmlspecialchars($testimonial['phone']) ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted text-xsmall">Non renseigné</span>
                                                    <?php endif; ?>
                                                </td>
                                                
                                                <!-- Note -->
                                                <td>
                                                    <div class="rating-stars">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star<?= $i <= $testimonial['rating'] ? '' : '-o' ?>"></i>
                                                        <?php endfor; ?>
                                                    </div>
                                                    <div class="text-xsmall text-muted">(<?= $testimonial['rating'] ?>/5)</div>
                                                </td>
                                                
                                                <!-- Commentaire -->
                                                <td>
                                                    <div class="comment-preview" data-bs-toggle="tooltip" 
                                                         title="<?= htmlspecialchars($testimonial['comment']) ?>">
                                                        <?= htmlspecialchars(substr($testimonial['comment'], 0, 50)) ?>
                                                        <?= strlen($testimonial['comment']) > 50 ? '...' : '' ?>
                                                    </div>
                                                </td>
                                                
                                                <!-- Statut avec Toggle -->
                                                <td>
                                                    <form method="POST" class="d-inline-block mb-1">
                                                        <input type="hidden" name="testimonial_id" value="<?= $testimonial['id'] ?>">
                                                        <input type="hidden" name="action" value="toggle">
                                                        <label class="switch" title="<?= $testimonial['status'] === 'approved' ? 'Désactiver' : 'Activer' ?>">
                                                            <input type="checkbox" <?= $testimonial['status'] === 'approved' ? 'checked' : '' ?> 
                                                                   onchange="this.form.submit()">
                                                            <span class="slider"></span>
                                                        </label>
                                                    </form>
                                                    <div>
                                                        <span class="badge badge-<?= $testimonial['status'] ?>">
                                                            <?= ucfirst($testimonial['status']) ?>
                                                        </span>
                                                    </div>
                                                </td>
                                                
                                                <!-- Date -->
                                                <td>
                                                    <div class="text-xsmall">
                                                        <?= date('d/m/Y', strtotime($testimonial['created_at'])) ?>
                                                    </div>
                                                    <div class="text-xsmall text-muted">
                                                        <?= date('H:i', strtotime($testimonial['created_at'])) ?>
                                                    </div>
                                                </td>
                                                
                                                <!-- Actions -->
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <!-- Voir les détails -->
                                                        <button type="button" class="btn btn-outline-primary btn-action" 
                                                                data-bs-toggle="modal" data-bs-target="#detailModal<?= $testimonial['id'] ?>"
                                                                title="Voir détails">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        
                                                        <!-- Approuver -->
                                                        <?php if ($testimonial['status'] !== 'approved'): ?>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="testimonial_id" value="<?= $testimonial['id'] ?>">
                                                                <input type="hidden" name="action" value="approve">
                                                                <button type="submit" class="btn btn-outline-success btn-action" 
                                                                        title="Approuver">
                                                                    <i class="fas fa-check"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        
                                                        <!-- Supprimer -->
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="testimonial_id" value="<?= $testimonial['id'] ?>">
                                                            <input type="hidden" name="action" value="delete">
                                                            <button type="submit" class="btn btn-outline-danger btn-action" 
                                                                    onclick="return confirm('Supprimer définitivement ce témoignage ?')"
                                                                    title="Supprimer">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
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

    <!-- Modals en dehors de la boucle pour éviter la duplication -->
    <?php foreach ($testimonials as $testimonial): ?>
    <div class="modal fade" id="detailModal<?= $testimonial['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header py-3">
                    <div class="d-flex align-items-center">
                        <?php if ($testimonial['avatar']): ?>
                            <img src="../uploads/avatars/<?= htmlspecialchars($testimonial['avatar']) ?>" 
                                 alt="Avatar" class="user-avatar-lg me-3">
                        <?php else: ?>
                            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center user-avatar-lg me-3">
                                <i class="fas fa-user text-white fa-2x"></i>
                            </div>
                        <?php endif; ?>
                        <div>
                            <h6 class="modal-title mb-0">
                                <?= htmlspecialchars($testimonial['first_name'] . ' ' . $testimonial['last_name']) ?>
                            </h6>
                            <small class="text-muted"><?= htmlspecialchars($testimonial['email']) ?></small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <strong class="text-small d-block mb-1">Note :</strong>
                            <div class="rating-stars-lg">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star<?= $i <= $testimonial['rating'] ? '' : '-o' ?>"></i>
                                <?php endfor; ?>
                                <span class="ms-2 fw-bold">(<?= $testimonial['rating'] ?>/5)</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <strong class="text-small d-block mb-1">Statut :</strong>
                            <span class="badge badge-<?= $testimonial['status'] ?> fs-6">
                                <?= ucfirst($testimonial['status']) ?>
                            </span>
                        </div>
                        <div class="col-md-4">
                            <strong class="text-small d-block mb-1">Date :</strong>
                            <span class="text-small">
                                <?= date('d/m/Y à H:i', strtotime($testimonial['created_at'])) ?>
                            </span>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-4">
                        <strong class="text-small d-block mb-2">Commentaire complet :</strong>
                        <div class="p-3 bg-light rounded">
                            <p class="mb-0 text-small"><?= nl2br(htmlspecialchars($testimonial['comment'])) ?></p>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <strong class="text-small d-block mb-2">Coordonnées :</strong>
                            <div class="text-small">
                                <div class="mb-1">
                                    <i class="fas fa-envelope me-2 text-muted"></i>
                                    <a href="mailto:<?= htmlspecialchars($testimonial['email']) ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($testimonial['email']) ?>
                                    </a>
                                </div>
                                <div>
                                    <i class="fas fa-phone me-2 text-muted"></i>
                                    <?php if ($testimonial['phone']): ?>
                                        <a href="tel:<?= htmlspecialchars($testimonial['phone']) ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($testimonial['phone']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">Non renseigné</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-3">
                    <form method="POST" class="me-auto">
                        <input type="hidden" name="testimonial_id" value="<?= $testimonial['id'] ?>">
                        <input type="hidden" name="action" value="toggle">
                        <button type="submit" class="btn btn-<?= $testimonial['status'] === 'approved' ? 'warning' : 'success' ?> btn-sm">
                            <i class="fas fa-<?= $testimonial['status'] === 'approved' ? 'times' : 'check' ?> me-1"></i>
                            <?= $testimonial['status'] === 'approved' ? 'Désactiver' : 'Activer' ?>
                        </button>
                    </form>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-submit du filtre de statut
        document.getElementById('status').addEventListener('change', function() {
            this.form.submit();
        });

        // Initialiser les tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Confirmation pour la suppression
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const action = this.querySelector('input[name="action"]').value;
                if (action === 'delete') {
                    if (!confirm('Êtes-vous sûr de vouloir supprimer définitivement ce témoignage ?')) {
                        e.preventDefault();
                    }
                }
            });
        });

        // Auto-dismiss des alertes après 3 secondes
        document.addEventListener('DOMContentLoaded', function() {
            const autoDismissAlerts = document.querySelectorAll('.alert-auto-dismiss');
            
            autoDismissAlerts.forEach(alert => {
                const dismissTime = alert.getAttribute('data-auto-dismiss') || 3000;
                
                setTimeout(() => {
                    // Ajouter la classe de fade out
                    alert.classList.add('alert-fade-out');
                    
                    // Supprimer l'alerte après l'animation
                    setTimeout(() => {
                        if (alert.parentNode) {
                            alert.remove();
                        }
                    }, 500); // Temps de l'animation
                    
                }, parseInt(dismissTime));
            });
        });

        // Empêcher la fermeture manuelle de déclencher des erreurs
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('btn-close')) {
                const alert = e.target.closest('.alert-auto-dismiss');
                if (alert) {
                    setTimeout(() => {
                        if (alert.parentNode) {
                            alert.remove();
                        }
                    }, 300);
                }
            }
        });
    </script>
</body>
</html>