<?php
// details_commande.php
session_start();
require_once '../config/database.php';
require_once '../config/security.php';

if (!Security::isLoggedIn()) {
    Security::redirect('../auth/login.php');
}

// Vérifier si un ID de commande est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = 'Commande introuvable';
    header('Location: mes_commandes.php');
    exit();
}

$order_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Récupérer les détails de la commande
try {
    // Requête pour obtenir les détails de la commande
    $stmt = $pdo->prepare("
        SELECT 
            o.*,
            CONCAT(u.first_name, ' ', u.last_name) as customer_full_name,
            u.email as customer_email,
            u.phone as customer_phone,
            COUNT(oi.id) as items_count,
            SUM(oi.quantity) as total_items
        FROM orders o
        LEFT JOIN users u ON o.customer_id = u.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.id = ? AND o.customer_id = ?
        GROUP BY o.id
    ");
    
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        $_SESSION['error_message'] = 'Commande non trouvée ou accès refusé';
        header('Location: mes_commandes.php');
        exit();
    }
    
    // Récupérer les articles de la commande
    $stmt_items = $pdo->prepare("
        SELECT 
            oi.*,
            p.name as product_name,
            p.description as product_description,
            p.image as product_image
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
        ORDER BY oi.created_at
    ");
    
    $stmt_items->execute([$order_id]);
    $order_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer l'historique des statuts
    $stmt_history = $pdo->prepare("
        SELECT * FROM order_status_history 
        WHERE order_id = ? 
        ORDER BY created_at DESC
    ");
    
    $stmt_history->execute([$order_id]);
    $status_history = $stmt_history->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Erreur lors de la récupération de la commande: " . $e->getMessage());
    $_SESSION['error_message'] = 'Erreur lors du chargement des détails de la commande';
    header('Location: mes_commandes.php');
    exit();
}

// Fonctions de traduction
function translateStatus($status) {
    $translations = [
        'pending' => 'En attente',
        'confirmed' => 'Confirmée',
        'preparing' => 'En préparation',
        'ready' => 'Prête',
        'served' => 'Servie',
        'cancelled' => 'Annulée'
    ];
    return $translations[$status] ?? $status;
}

function translatePaymentStatus($status) {
    $translations = [
        'pending' => 'En attente',
        'paid' => 'Payée',
        'refunded' => 'Remboursée'
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

function translatePaymentMethod($method) {
    $translations = [
        'cash' => 'Espèces',
        'card' => 'Carte bancaire',
        'online' => 'En ligne'
    ];
    return $translations[$method] ?? $method;
}

// Calculer le temps écoulé depuis la création
function timeSince($datetime) {
    $now = new DateTime();
    $created = new DateTime($datetime);
    $interval = $now->diff($created);
    
    if ($interval->y > 0) {
        return $interval->y . ' an' . ($interval->y > 1 ? 's' : '');
    } elseif ($interval->m > 0) {
        return $interval->m . ' mois';
    } elseif ($interval->d > 0) {
        return $interval->d . ' jour' . ($interval->d > 1 ? 's' : '');
    } elseif ($interval->h > 0) {
        return $interval->h . ' heure' . ($interval->h > 1 ? 's' : '');
    } elseif ($interval->i > 0) {
        return $interval->i . ' minute' . ($interval->i > 1 ? 's' : '');
    } else {
        return 'quelques secondes';
    }
}
// include 'header_navbar.php';
include 'jenga.php';
?>

    <style>
        #container{
            margin-top:35px;
            margin-left:260px;
        }
        .order-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
        }
        .status-timeline {
            position: relative;
            padding-left: 30px;
        }
        .status-timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        .timeline-step {
            position: relative;
            margin-bottom: 25px;
        }
        .timeline-step::before {
            content: '';
            position: absolute;
            left: -26px;
            top: 0;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #6c757d;
            border: 3px solid white;
            z-index: 1;
        }
        .timeline-step.current::before {
            background: #0d6efd;
            box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.2);
        }
        .timeline-step.completed::before {
            background: #198754;
        }
        .timeline-step.cancelled::before {
            background: #dc3545;
        }
        .product-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
            transition: transform 0.2s;
        }
        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .product-image {
            height: 120px;
            object-fit: cover;
            width: 100%;
        }
        .badge-status {
            padding: 8px 15px;
            font-size: 0.9rem;
            border-radius: 20px;
        }
        .info-card {
            border-left: 4px solid;
            padding-left: 15px;
            margin-bottom: 15px;
        }
        .info-card.delivery {
            border-left-color: #198754;
        }
        .info-card.payment {
            border-left-color: #0d6efd;
        }
        .info-card.contact {
            border-left-color: #ffc107;
        }
        .action-buttons .btn {
            min-width: 120px;
        }
        .print-only {
            display: none;
        }
        .order-number {
            letter-spacing: 2px;
            font-family: 'Courier New', monospace;
        }
        @media print {
            .no-print, .action-buttons {
                display: none !important;
            }
            .print-only {
                display: block;
            }
            .container {
                width: 100%;
                max-width: 100%;
            }
            .card {
                border: none !important;
                box-shadow: none !important;
            }
        }
        .qr-code {
            width: 150px;
            height: 150px;
            margin: 0 auto;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 10px;
        }
        .share-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .share-btn {
            flex: 1;
            min-width: 120px;
        }
    </style>

    <div class="container" id="container">
        <div class="col-md-12 col-lg-10 commande-content">
            <!-- En-tête de la commande -->
            <div class="order-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center mb-3">
                            <i class="fas fa-shopping-bag fa-3x me-3"></i>
                            <div>
                                <h1 class="h4 mb-0">Commande <span class="order-number">#<?= htmlspecialchars($order['order_number']) ?></span></h1>
                                <p class="mb-0 opacity-75">
                                    <i class="far fa-calendar me-1"></i>
                                    Passée <?= timeSince($order['created_at']) ?> 
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <div class="d-inline-block bg-white text-dark px-3 py-2 rounded-3">
                            <div class="small text-muted">Statut</div>
                            <div class="h5 mb-0 text-<?= 
                                $order['status'] === 'cancelled' ? 'danger' : 
                                ($order['status'] === 'served' || $order['status'] === 'ready' ? 'success' : 
                                ($order['status'] === 'preparing' ? 'warning' : 
                                ($order['status'] === 'confirmed' ? 'info' : 'secondary'))) ?>">
                                <?= translateStatus($order['status']) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Messages d'alerte -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= $_SESSION['success_message'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= $_SESSION['error_message'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <div class="row">
                <!-- Colonne principale -->
                <div class="col-lg-8">
                    <!-- Articles de la commande -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-boxes me-2"></i>Articles commandés</h5>
                            <span class="badge bg-primary rounded-pill"><?= $order['total_items'] ?> articles</span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th style="width: 60px;"></th>
                                            <th>Article</th>
                                            <th class="text-center">Quantité</th>
                                            <th class="text-end">Prix unitaire</th>
                                            <th class="text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($order_items as $item): ?>
                                            <tr>
                                                <td>
                                                    <?php if (!empty($item['product_image'])): ?>
                                                        <img src="../uploads/products/<?= htmlspecialchars($item['product_image']) ?>" 
                                                            alt="<?= htmlspecialchars($item['product_name']) ?>" 
                                                            class="img-thumbnail" style="width: 50px; height: 50px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                                                            style="width: 50px; height: 50px;">
                                                            <i class="fas fa-utensils text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="fw-bold"><?= htmlspecialchars($item['product_name']) ?></div>
                                                    <?php if (!empty($item['product_description'])): ?>
                                                        <small class="text-muted"><?= htmlspecialchars(substr($item['product_description'], 0, 100)) ?>...</small>
                                                    <?php endif; ?>
                                                    <?php if (!empty($item['notes'])): ?>
                                                        <div class="mt-1">
                                                            <small class="text-primary"><i class="fas fa-sticky-note me-1"></i><?= htmlspecialchars($item['notes']) ?></small>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center"><?= (int)$item['quantity'] ?></td>
                                                <td class="text-end"><?= number_format($item['unit_price'], 2, ',', ' ') ?> €</td>
                                                <td class="text-end fw-bold"><?= number_format($item['unit_price'] * $item['quantity'], 2, ',', ' ') ?> €</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Suivi de la commande -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-shipping-fast me-2"></i>Suivi de votre commande</h5>
                        </div>
                        <div class="card-body">
                            <div class="status-timeline">
                                <?php 
                                $status_steps = [
                                    'pending' => ['icon' => 'clock', 'title' => 'En attente', 'desc' => 'Votre commande est en attente de confirmation'],
                                    'confirmed' => ['icon' => 'check-circle', 'title' => 'Confirmée', 'desc' => 'Commande confirmée par le restaurant'],
                                    'preparing' => ['icon' => 'utensils', 'title' => 'En préparation', 'desc' => 'Votre commande est en cours de préparation'],
                                    'ready' => ['icon' => 'check', 'title' => 'Prête', 'desc' => 'Votre commande est prête'],
                                    'served' => ['icon' => 'concierge-bell', 'title' => 'Servie', 'desc' => 'Commande servie'],
                                    'cancelled' => ['icon' => 'times', 'title' => 'Annulée', 'desc' => 'Commande annulée']
                                ];
                                
                                $current_status = $order['status'];
                                $status_keys = array_keys($status_steps);
                                $current_index = array_search($current_status, $status_keys);
                                
                                foreach ($status_steps as $key => $step):
                                    $is_current = $key === $current_status;
                                    $is_completed = array_search($key, $status_keys) < $current_index;
                                    $is_cancelled = $current_status === 'cancelled' && $key === 'cancelled';
                                    
                                    $status_class = '';
                                    if ($is_current) $status_class = 'current';
                                    if ($is_completed) $status_class = 'completed';
                                    if ($is_cancelled) $status_class = 'cancelled';
                                ?>
                                    <div class="timeline-step <?= $status_class ?>">
                                        <div class="d-flex">
                                            <div class="flex-shrink-0 me-3">
                                                <div class="rounded-circle bg-<?= 
                                                    $is_cancelled ? 'danger' : 
                                                    ($is_completed ? 'success' : 
                                                    ($is_current ? 'primary' : 'secondary')) 
                                                ?> text-white d-flex align-items-center justify-content-center" 
                                                    style="width: 40px; height: 40px;">
                                                    <i class="fas fa-<?= $step['icon'] ?>"></i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1"><?= $step['title'] ?></h6>
                                                <p class="mb-0 text-muted"><?= $step['desc'] ?></p>
                                                <?php if ($is_current): ?>
                                                    <div class="mt-2">
                                                        <small class="text-primary">
                                                            <i class="fas fa-clock me-1"></i>
                                                            Statut actuel
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Colonne latérale -->
                <div class="col-lg-4">
                    <!-- Informations de commande -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informations</h5>
                        </div>
                        <div class="card-body">
                            <!-- Type de commande -->
                            <div class="info-card <?= $order['order_type'] ?> mb-3">
                                <div class="small text-muted">Type de commande</div>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-<?= $order['order_type'] === 'dine_in' ? 'utensils' : ($order['order_type'] === 'takeaway' ? 'shopping-bag' : 'truck') ?> me-2"></i>
                                    <strong><?= translateOrderType($order['order_type']) ?></strong>
                                </div>
                                
                                <?php if ($order['order_type'] === 'dine_in' && !empty($order['table_number'])): ?>
                                    <div class="mt-2">
                                        <i class="fas fa-chair me-1"></i>
                                        Table : <strong><?= htmlspecialchars($order['table_number']) ?></strong>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($order['order_type'] === 'delivery' && !empty($order['notes'])): ?>
                                    <div class="mt-2">
                                        <i class="fas fa-map-marker-alt me-1"></i>
                                        <small class="text-muted"><?= nl2br(htmlspecialchars($order['notes'])) ?></small>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Paiement -->
                            <div class="info-card payment mb-3">
                                <div class="small text-muted">Paiement</div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-credit-card me-2"></i>
                                        <span class="badge bg-<?= 
                                            $order['payment_status'] === 'paid' ? 'success' : 
                                            ($order['payment_status'] === 'refunded' ? 'info' : 'warning') ?>">
                                            <?= translatePaymentStatus($order['payment_status']) ?>
                                        </span>
                                    </div>
                                    <?php if ($order['payment_method']): ?>
                                        <span class="text-muted"><?= translatePaymentMethod($order['payment_method']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Contact -->
                            <div class="info-card contact">
                                <div class="small text-muted">Contact</div>
                                <div><i class="fas fa-user me-2"></i><?= htmlspecialchars($order['customer_full_name']) ?></div>
                                <div><i class="fas fa-envelope me-2"></i><?= htmlspecialchars($order['customer_email']) ?></div>
                                <?php if (!empty($order['customer_phone'])): ?>
                                    <div><i class="fas fa-phone me-2"></i><?= htmlspecialchars($order['customer_phone']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Résumé financier -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>Résumé</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Sous-total</span>
                                <span><?= number_format($order['subtotal'], 2, ',', ' ') ?> €</span>
                            </div>
                            
                            <?php if ($order['tax_amount'] > 0): ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>TVA</span>
                                    <span><?= number_format($order['tax_amount'], 2, ',', ' ') ?> €</span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($order['discount_amount'] > 0): ?>
                                <div class="d-flex justify-content-between mb-2 text-success">
                                    <span>Réduction</span>
                                    <span>-<?= number_format($order['discount_amount'], 2, ',', ' ') ?> €</span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($order['order_type'] === 'delivery'): ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Livraison</span>
                                    <span><?= number_format(4.99, 2, ',', ' ') ?> $</span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($order['order_type'] === 'dine_in'): ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Service</span>
                                    <span><?= number_format(2.50, 2, ',', ' ') ?> $</span>
                                </div>
                            <?php endif; ?>
                            
                            <hr>
                            <div class="d-flex justify-content-between fw-bold fs-5">
                                <span>Total</span>
                                <span class="text-primary"><?= number_format($order['total_amount'], 2, ',', ' ') ?> $</span>
                            </div>
                            
                            <div class="mt-3 small text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                TVA incluse le cas échéant
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="action-buttons d-grid gap-2">
                                <?php if ($order['status'] === 'pending' || $order['status'] === 'confirmed'): ?>
                                    <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal">
                                        <i class="fas fa-times me-2"></i>Annuler la commande
                                    </button>
                                <?php endif; ?>
                                
                                <a href="nouvelle_commande.php?duplicate=<?= $order['id'] ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-copy me-2"></i>Commander à nouveau
                                </a>
                                
                                <button class="btn btn-outline-secondary" onclick="window.print()">
                                    <i class="fas fa-print me-2"></i>Imprimer
                                </button>
                                
                                <?php if ($order['order_type'] === 'delivery' && $order['status'] === 'preparing'): ?>
                                    <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#trackingModal">
                                        <i class="fas fa-map-marked-alt me-2"></i>Suivre la livraison
                                    </button>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Boutons de partage -->
                            <div class="mt-3">
                                <label class="form-label small">Partager la commande</label>
                                <div class="share-buttons">
                                    <button class="btn btn-outline-primary share-btn" onclick="shareOrder('whatsapp')">
                                        <i class="fab fa-whatsapp me-1"></i>WhatsApp
                                    </button>
                                    <button class="btn btn-outline-info share-btn" onclick="shareOrder('email')">
                                        <i class="fas fa-envelope me-1"></i>Email
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- QR Code pour suivi -->
                    <div class="card mb-4">
                        <div class="card-body text-center">
                            <h6 class="mb-3">Code de suivi</h6>
                            <div class="qr-code d-flex align-items-center justify-content-center">
                                <!-- Placeholder pour QR code -->
                                <div class="text-center">
                                    <i class="fas fa-qrcode fa-4x text-muted"></i>
                                    <div class="mt-2 small"><?= $order['order_number'] ?></div>
                                </div>
                            </div>
                            <p class="small text-muted mt-3 mb-0">
                                Présentez ce code pour récupérer votre commande
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Notes supplémentaires -->
        <?php if (!empty($order['notes'])): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-sticky-note me-2"></i>Notes supplémentaires</h5>
                </div>
                <div class="card-body">
                    <p class="mb-0"><?= nl2br(htmlspecialchars($order['notes'])) ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Historique des statuts -->
        <?php if (!empty($status_history)): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Historique des statuts</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Statut</th>
                                    <th>Commentaire</th>
                                    <th>Par</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($status_history as $history): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($history['created_at'])) ?></td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $history['status'] === 'cancelled' ? 'danger' : 
                                                ($history['status'] === 'served' || $history['status'] === 'ready' ? 'success' : 
                                                ($history['status'] === 'preparing' ? 'warning' : 
                                                ($history['status'] === 'confirmed' ? 'info' : 'secondary'))) ?>">
                                                <?= translateStatus($history['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($history['notes'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($history['changed_by'] ?? 'Système') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal d'annulation -->
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Annuler la commande</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="cancelForm" action="../admin/api/client_commande_annuleeMMM.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Êtes-vous sûr de vouloir annuler cette commande ?
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Raison de l'annulation</label>
                            <select class="form-control" name="cancellation_reason" required>
                                <option value="">-- Choisissez une raison --</option>
                                <option value="changement_plans">Changement de plans</option>
                                <option value="double_commande">Double commande</option>
                                <option value="delai_trop_long">Délai trop long</option>
                                <option value="autre">Autre raison</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Commentaire (optionnel)</label>
                            <textarea class="form-control" name="cancellation_notes" rows="3" 
                                      placeholder="Précisez si nécessaire..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Retour</button>
                        <button type="submit" class="btn btn-danger">Confirmer l'annulation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de suivi de livraison -->
    <div class="modal fade" id="trackingModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Suivi de livraison</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center py-4">
                        <i class="fas fa-truck fa-4x text-primary mb-3"></i>
                        <h5>Votre commande est en route !</h5>
                        <p class="text-muted">
                            Le livreur devrait arriver dans environ 15-25 minutes.
                        </p>
                        
                        <div class="progress mb-3" style="height: 10px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                 style="width: 75%"></div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Vous pouvez suivre le livreur en temps réel sur l'application.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    <a href="https://maps.app.goo.gl/?link=track" target="_blank" class="btn btn-primary">
                        <i class="fas fa-external-link-alt me-2"></i>Ouvrir le suivi
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Gestion du formulaire d'annulation
            const cancelForm = document.getElementById('cancelForm');
            if (cancelForm) {
                cancelForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    if (!confirm('Êtes-vous certain de vouloir annuler cette commande ? Cette action est irréversible.')) {
                        return;
                    }
                    
                    const formData = new FormData(this);
                    
                    fetch(this.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const modal = bootstrap.Modal.getInstance(document.getElementById('cancelModal'));
                            modal.hide();
                            
                            // Afficher message de succès et recharger
                            alert(data.message);
                            window.location.reload();
                        } else {
                            alert('Erreur : ' + (data.message || 'Une erreur est survenue'));
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        alert('Erreur réseau. Veuillez réessayer.');
                    });
                });
            }
            
            // Fonction de partage
            window.shareOrder = function(platform) {
                const orderNumber = '<?= $order['order_number'] ?>';
                const orderLink = window.location.href;
                const message = `Ma commande #${orderNumber} au restaurant Le Gourmet\n${orderLink}`;
                
                if (platform === 'whatsapp') {
                    window.open(`https://wa.me/?text=${encodeURIComponent(message)}`, '_blank');
                } else if (platform === 'email') {
                    window.location.href = `mailto:?subject=Ma commande #${orderNumber}&body=${encodeURIComponent(message)}`;
                }
            };
            
            // Mettre à jour l'heure toutes les minutes
            function updateTimes() {
                const timeElements = document.querySelectorAll('[data-time]');
                timeElements.forEach(el => {
                    const time = new Date(el.dataset.time);
                    const now = new Date();
                    const diff = Math.floor((now - time) / 1000);
                    
                    if (diff < 60) {
                        el.textContent = 'il y a quelques secondes';
                    } else if (diff < 3600) {
                        const minutes = Math.floor(diff / 60);
                        el.textContent = `il y a ${minutes} minute${minutes > 1 ? 's' : ''}`;
                    } else if (diff < 86400) {
                        const hours = Math.floor(diff / 3600);
                        el.textContent = `il y a ${hours} heure${hours > 1 ? 's' : ''}`;
                    } else {
                        const days = Math.floor(diff / 86400);
                        el.textContent = `il y a ${days} jour${days > 1 ? 's' : ''}`;
                    }
                });
            }
            
            // Initialiser et mettre à jour les heures
            updateTimes();
            setInterval(updateTimes, 60000);
            
            // Animation pour la timeline
            const timelineSteps = document.querySelectorAll('.timeline-step');
            timelineSteps.forEach((step, index) => {
                setTimeout(() => {
                    step.style.opacity = '1';
                    step.style.transform = 'translateX(0)';
                }, index * 200);
            });
        });
    </script>
</body>
</html>