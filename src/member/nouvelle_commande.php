<?php
// nouvelle_commande.php
// session_start();
require_once '../config/database.php';
require_once '../config/security.php';

// if (!Security::isLoggedIn()) {
//     Security::redirect('../auth/login.php');
// }

// Récupérer les informations utilisateur
// $user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? '';
$user_email = $_SESSION['user_email'] ?? '';
$user_phone = $_SESSION['user_phone'] ?? '';

// Récupérer les produits disponibles
$products = [];
$categories = [];

try {
    // Récupérer les catégories
    $cat_stmt = $pdo->query("SELECT id, name, description FROM categories WHERE is_active = 1 ORDER BY sort_order");
    $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les produits par catégorie
    $product_stmt = $pdo->query("
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.is_available = 1 
        ORDER BY c.sort_order, p.sort_order, p.name
    ");
    $all_products = $product_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organiser les produits par catégorie
    foreach ($all_products as $product) {
        $cat_id = $product['category_id'] ?: 'uncategorized';
        if (!isset($products[$cat_id])) {
            $products[$cat_id] = [
                'category_name' => $product['category_name'] ?: 'Non catégorisé',
                'items' => []
            ];
        }
        $products[$cat_id]['items'][] = $product;
    }
    
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des produits: " . $e->getMessage());
}

// Récupérer les tables disponibles pour les commandes sur place
$tables = [];
try {
    $stmt = $pdo->query("
        SELECT id, table_name, capacity, location 
        FROM tables 
        WHERE is_available = 1
        ORDER BY table_name
    ");
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des tables: " . $e->getMessage());
}
?>
<?php include 'jenga.php'; ?>
    <style>
        .containere {
            margin-left: 265px;
            margin-top: 60px;
            overflow-x: hidden; /* Empêche le défilement horizontal */
            /* max-width: 100%; Assure que le contenu ne dépasse pas */
        }
        .product-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid #dee2e6;
            height: 100%;
        }
        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .product-image {
            height: 150px;
            object-fit: cover;
            width: 100%;
            border-bottom: 1px solid #dee2e6;
        }
        .product-price {
            font-size: 1.1rem;
            font-weight: bold;
            color: #198754;
        }
        .product-ingredients {
            font-size: 0.85rem;
            color: #6c757d;
        }
        .allergen-badge {
            font-size: 0.7rem;
            padding: 2px 6px;
            margin-right: 3px;
        }
        .cart-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s;
        }
        .cart-item:hover {
            background-color: #f8f9fa;
        }
        .cart-item:last-child {
            border-bottom: none;
        }
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .quantity-btn {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .cart-empty {
            padding: 40px 20px;
            text-align: center;
            color: #6c757d;
        }
        .order-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            position: sticky;
            top: 20px;
        }
        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .summary-total {
            font-size: 1.2rem;
            font-weight: bold;
            border-top: 2px solid #dee2e6;
            padding-top: 10px;
            margin-top: 10px;
        }
        .tab-content {
            padding: 20px 0;
        }
        .nav-tabs .nav-link {
            border: 1px solid #dee2e6;
            border-bottom: none;
            border-radius: 5px 5px 0 0;
            margin-right: 5px;
            padding: 10px 20px;
        }
        .nav-tabs .nav-link.active {
            background-color: #0d6efd;
            color: white;
            border-color: #0d6efd;
        }
        .product-category {
            margin-bottom: 30px;
        }
        .category-title {
            padding-bottom: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #0d6efd;
            color: #495057;
        }
        .badge-time {
            background-color: #6f42c1;
            color: white;
        }
        .table-option {
            padding: 10px;
            border: 2px solid #dee2e6;
            border-radius: 5px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .table-option:hover {
            border-color: #0d6efd;
            background-color: #f8f9fa;
        }
        .table-option.selected {
            border-color: #198754;
            background-color: #e7f1ff;
        }
        .table-info {
            font-size: 0.85rem;
            color: #6c757d;
        }
        .delivery-info {
            background: #e8f5e9;
            border-radius: 5px;
            padding: 15px;
            margin-top: 15px;
        }
        .accordion-button:not(.collapsed) {
            background-color: #e7f1ff;
            color: #0d6efd;
        }
        .accordion-button:focus {
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
    </style>


<div id="container" class="container containere overflow-x-hidden">
    <div class="row" style ="width:100%">
        <!-- Contenu principal -->
        <div class="col-md-11 col-lg-10 nouvelle_commande-content">
            <div class="container py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0"><i class="fas fa-shopping-bag me-2 text-primary"></i>Nouvelle commande</h1>
                    <div>
                        <a class="btn btn-outline-secondary me-2" href="dashboard.php"><i class="fas fa-arrow-left me-1"></i>Retour</a>
                    </div>
                </div>

                <!-- Cart Preview (mobile) -->
                <div class="card mb-4 d-md-none">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-shopping-cart me-2"></i>Panier</span>
                        <span class="badge bg-primary rounded-pill" id="mobileCartCount">0</span>
                    </div>
                    <div class="card-body p-0">
                        <div id="mobileCartPreview" class="cart-empty">
                            <i class="fas fa-shopping-cart fa-2x mb-3"></i>
                            <p class="mb-0">Votre panier est vide</p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Colonne produits -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-body">
                                <!-- Type de commande -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Type de commande</label>
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="order_type" id="dine_in" value="dine_in" checked>
                                        <label class="btn btn-outline-primary" for="dine_in">
                                            <i class="fas fa-utensils me-2"></i>Sur place
                                        </label>
                                        
                                        <input type="radio" class="btn-check" name="order_type" id="takeaway" value="takeaway">
                                        <label class="btn btn-outline-primary" for="takeaway">
                                            <i class="fas fa-shopping-bag me-2"></i>À emporter
                                        </label>
                                        
                                        <input type="radio" class="btn-check" name="order_type" id="delivery" value="delivery">
                                        <label class="btn btn-outline-primary" for="delivery">
                                            <i class="fas fa-truck me-2"></i>Livraison
                                        </label>
                                    </div>
                                </div>

                                <!-- Informations spécifiques -->
                                <div id="orderTypeInfo">
                                    <!-- Sur place - Sélection de table -->
                                    <div id="dineInInfo">
                                        <div class="mb-3">
                                            <label class="form-label">Table</label>
                                            <select class="form-control" name="table_number" id="tableSelect">
                                                <option value="">-- Sélectionnez une table --</option>
                                                <?php foreach ($tables as $table): ?>
                                                    <option value="<?= htmlspecialchars($table['table_name']) ?>"
                                                            data-id="<?= $table['id'] ?>"
                                                            data-capacity="<?= $table['capacity'] ?>">
                                                        <?= htmlspecialchars($table['table_name']) ?> 
                                                        (<?= $table['capacity'] ?> pers.)
                                                        <?php if (!empty($table['location'])): ?>
                                                            - <?= htmlspecialchars($table['location']) ?>
                                                        <?php endif; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="text-muted">La table sera attribuée automatiquement si non spécifiée</small>
                                        </div>
                                    </div>

                                    <!-- <div id="dineInInfo">
                                        <div class="mb-3">
                                            <label class="form-label">Table</label>
                                            <select class="form-control" name="table_number" id="tableSelect">
                                                <option value="">-- Sélectionnez une table --</option>
                                                <?php if (empty($tables)): ?>
                                                    <option value="" disabled>Aucune table disponible - Contactez le serveur</option>
                                                <?php else: ?>
                                                    <?php foreach ($tables as $table): ?>
                                                        <option value="<?= htmlspecialchars($table['table_name']) ?>"
                                                                data-id="<?= $table['id'] ?>"
                                                                data-capacity="<?= $table['capacity'] ?>">
                                                            <?= htmlspecialchars($table['table_name']) ?> 
                                                            (<?= $table['capacity'] ?> pers.)
                                                            <?php if (!empty($table['location'])): ?>
                                                                - <?= htmlspecialchars($table['location']) ?>
                                                            <?php endif; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </select>
                                            <small class="text-muted">La table sera attribuée automatiquement si non spécifiée</small>
                                        </div>
                                    </div> -->

                                    <!-- À emporter - Heure de récupération -->
                                    <div id="takeawayInfo" style="display: none;">
                                        <div class="mb-3">
                                            <label class="form-label">Heure de récupération souhaitée</label>
                                            <input type="time" class="form-control" name="pickup_time" 
                                                min="<?= date('H:i', strtotime('+30 minutes')) ?>"
                                                value="<?= date('H:i', strtotime('+45 minutes')) ?>">
                                            <small class="text-muted">Préparation minimum : 30 minutes</small>
                                        </div>
                                    </div>

                                    <!-- Livraison - Adresse -->
                                    <div id="deliveryInfo" style="display: none;">
                                        <div class="delivery-info">
                                            <h6 class="mb-3"><i class="fas fa-map-marker-alt me-2"></i>Adresse de livraison</h6>
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Adresse</label>
                                                    <input type="text" class="form-control" name="delivery_address" 
                                                        placeholder="Numéro et rue">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">Code postal</label>
                                                    <input type="text" class="form-control" name="delivery_zipcode" 
                                                        placeholder="75000">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">Ville</label>
                                                    <input type="text" class="form-control" name="delivery_city" 
                                                        placeholder="Paris">
                                                </div>
                                            </div>
                                            <div class="mt-3">
                                                <label class="form-label">Instructions de livraison</label>
                                                <textarea class="form-control" name="delivery_notes" rows="2" 
                                                        placeholder="Code d'entrée, étage, etc."></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Onglets des catégories -->
                                <ul class="nav nav-tabs" id="categoryTabs" role="tablist">
                                    <?php $first = true; ?>
                                    <?php foreach ($products as $cat_id => $category): ?>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link <?= $first ? 'active' : '' ?>" 
                                                    id="tab-<?= $cat_id ?>" 
                                                    data-bs-toggle="tab" 
                                                    data-bs-target="#cat-<?= $cat_id ?>" 
                                                    type="button">
                                                <?= htmlspecialchars($category['category_name']) ?>
                                            </button>
                                        </li>
                                        <?php $first = false; ?>
                                    <?php endforeach; ?>
                                </ul>

                                <!-- Contenu des catégories -->
                                <div class="tab-content" id="categoryTabContent">
                                    <?php $first = true; ?>
                                    <?php foreach ($products as $cat_id => $category): ?>
                                        <div class="tab-pane fade <?= $first ? 'show active' : '' ?>" 
                                            id="cat-<?= $cat_id ?>" 
                                            role="tabpanel">
                                            
                                            <h5 class="category-title"><?= htmlspecialchars($category['category_name']) ?></h5>
                                            
                                            <div class="row row-cols-1 row-cols-md-2 g-4">
                                                <?php foreach ($category['items'] as $product): ?>
                                                    <div class="col">
                                                        <div class="card product-card h-100">
                                                            <?php if (!empty($product['image'])): ?>

                                                                <!-- Ajout de la vérification de l'existence de l'image
                                                                et utilisation de la fonction basename pour obtenir le nom de l'image -->
                                                                <img src="<?= '../uploads/products/' . htmlspecialchars(basename($product['image'])) ?>" 
                                                                    class="product-image" 
                                                                    alt="<?= htmlspecialchars($product['name']) ?>">
                                                            <?php else: ?>
                                                                <div class="product-image bg-light d-flex align-items-center justify-content-center">
                                                                    <i class="fas fa-utensils fa-3x text-muted"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                            
                                                            <div class="card-body">
                                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                                    <h6 class="card-title mb-0"><?= htmlspecialchars($product['name']) ?></h6>
                                                                    <span class="product-price"><?= number_format($product['price'], 2, ',', ' ') ?> $</span>
                                                                </div>
                                                                
                                                                <?php if (!empty($product['description'])): ?>
                                                                    <p class="card-text small text-muted mb-2">
                                                                        <?= htmlspecialchars($product['description']) ?>
                                                                    </p>
                                                                <?php endif; ?>
                                                                
                                                                <?php if (!empty($product['ingredients'])): ?>
                                                                    <p class="product-ingredients mb-2">
                                                                        <small><?= htmlspecialchars($product['ingredients']) ?></small>
                                                                    </p>
                                                                <?php endif; ?>
                                                                
                                                                <?php if (!empty($product['allergens'])): ?>
                                                                    <div class="mb-2">
                                                                        <?php 
                                                                        $allergens = explode(',', $product['allergens']);
                                                                        foreach ($allergens as $allergen):
                                                                            if (trim($allergen)): ?>
                                                                                <span class="badge allergen-badge bg-warning"><?= trim($allergen) ?></span>
                                                                            <?php endif;
                                                                        endforeach; ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                                
                                                                <?php if ($product['preparation_time']): ?>
                                                                    <div class="mb-3">
                                                                        <span class="badge badge-time">
                                                                            <i class="fas fa-clock me-1"></i>
                                                                            <?= $product['preparation_time'] ?> min
                                                                        </span>
                                                                    </div>
                                                                <?php endif; ?>
                                                                
                                                                <div class="d-flex justify-content-between align-items-center">
                                                                    <button class="btn btn-sm btn-outline-primary add-to-cart"
                                                                            data-product-id="<?= $product['id'] ?>"
                                                                            data-product-name="<?= htmlspecialchars($product['name']) ?>"
                                                                            data-product-price="<?= $product['price'] ?>"
                                                                            data-product-image="<?= htmlspecialchars($product['image'] ?? '') ?>">
                                                                        <i class="fas fa-plus me-1"></i>Ajouter
                                                                    </button>
                                                                    
                                                                    <div class="quantity-control d-none">
                                                                        <button class="btn btn-sm btn-outline-secondary quantity-btn minus">-</button>
                                                                        <span class="quantity">1</span>
                                                                        <button class="btn btn-sm btn-outline-secondary quantity-btn plus">+</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php $first = false; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Colonne panier et résumé -->
                    <div class="col-lg-4">
                        <div class="order-summary">
                            <h5 class="mb-4"><i class="fas fa-shopping-cart me-2"></i>Votre commande</h5>
                            
                            <!-- Liste des articles -->
                            <div id="cartItems" class="mb-4">
                                <div class="cart-empty">
                                    <i class="fas fa-shopping-cart fa-2x mb-3"></i>
                                    <p class="mb-0">Votre panier est vide</p>
                                    <small class="text-muted">Ajoutez des produits pour commencer</small>
                                </div>
                            </div>
                            
                            <!-- Résumé -->
                            <div id="orderSummary" style="display: none;">
                                <div class="summary-item">
                                    <span>Sous-total</span>
                                    <span id="subtotal">0,00 $</span>
                                </div>
                                <div class="summary-item">
                                    <span>Service</span>
                                    <span id="serviceFee">0,00 $</span>
                                </div>
                                <div class="summary-item">
                                    <span>Livraison</span>
                                    <span id="deliveryFee">0,00 $</span>
                                </div>
                                <div class="summary-item summary-total">
                                    <span>Total</span>
                                    <span id="totalAmount">0,00 $</span>
                                </div>
                                
                                <!-- Notes -->
                                <div class="mt-4">
                                    <label class="form-label">Notes pour la commande</label>
                                    <textarea class="form-control" name="order_notes" rows="3" 
                                            placeholder="Allergies, préférences, etc."></textarea>
                                </div>
                                
                                <!-- Accordéon pour les options de paiement -->
                                <!-- <div class="accordion mt-4" id="paymentAccordion">
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" 
                                                    data-bs-toggle="collapse" data-bs-target="#paymentCollapse">
                                                <i class="fas fa-credit-card me-2"></i>Options de paiement
                                            </button>
                                        </h2>
                                        <div id="paymentCollapse" class="accordion-collapse collapse" 
                                            data-bs-parent="#paymentAccordion">
                                            <div class="accordion-body">
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="radio" 
                                                        name="payment_method" value="cash" id="cash" checked>
                                                    <label class="form-check-label" for="cash">
                                                        Paiement à la réception
                                                    </label>
                                                </div>
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="radio" 
                                                        name="payment_method" value="card" id="card">
                                                    <label class="form-check-label" for="card">
                                                        Carte bancaire
                                                    </label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" 
                                                        name="payment_method" value="online" id="online">
                                                    <label class="form-check-label" for="online">
                                                        Paiement en ligne
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div> -->
                                <!-- Accordéon pour les options de paiement -->
                                <div class="accordion mt-4" id="paymentAccordion">
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" 
                                                    data-bs-toggle="collapse" data-bs-target="#paymentCollapse">
                                                <i class="fas fa-credit-card me-2"></i>Options de paiement
                                            </button>
                                        </h2>
                                        <div id="paymentCollapse" class="accordion-collapse collapse" 
                                            data-bs-parent="#paymentAccordion">
                                            <div class="accordion-body">
                                                <!-- Paiement à la réception -->
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input payment-method" type="radio" 
                                                        name="payment_method" value="cash" id="cash" checked>
                                                    <label class="form-check-label" for="cash">
                                                        <i class="fas fa-money-bill-wave me-2"></i>Paiement à la réception
                                                    </label>
                                                </div>
                                                
                                                <!-- Carte bancaire -->
                                                <div class="form-check mb-3">
                                                    <input class="form-check-input payment-method" type="radio" 
                                                        name="payment_method" value="card" id="card">
                                                    <label class="form-check-label" for="card">
                                                        <i class="fas fa-credit-card me-2"></i>Carte bancaire
                                                    </label>
                                                </div>
                                                
                                                <!-- Formulaire carte bancaire (caché par défaut) -->
                                                <div id="cardPaymentForm" class="border rounded p-3 mb-3" style="display: none;">
                                                    <h6><i class="fas fa-credit-card me-2"></i>Informations de la carte</h6>
                                                    <div class="mb-3">
                                                        <label class="form-label">Numéro de carte</label>
                                                        <input type="text" class="form-control" 
                                                            placeholder="1234 5678 9012 3456" 
                                                            maxlength="19"
                                                            oninput="formatCardNumber(this)">
                                                        <div class="form-text">Accepté: Visa, MasterCard, American Express</div>
                                                    </div>
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Date d'expiration</label>
                                                            <input type="text" class="form-control" 
                                                                placeholder="MM/AA"
                                                                maxlength="5"
                                                                oninput="formatExpiryDate(this)">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">CVV</label>
                                                            <input type="password" class="form-control" 
                                                                placeholder="123"
                                                                maxlength="4"
                                                                oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                                        </div>
                                                    </div>
                                                    <div class="mt-3">
                                                        <label class="form-label">Nom sur la carte</label>
                                                        <input type="text" class="form-control" 
                                                            placeholder="JEAN DUPONT">
                                                    </div>
                                                    <div class="form-check mt-3">
                                                        <input type="checkbox" class="form-check-input" id="saveCard">
                                                        <label class="form-check-label" for="saveCard">
                                                            Sauvegarder cette carte pour de futurs achats
                                                        </label>
                                                    </div>
                                                </div>
                                                
                                                <!-- Paiement en ligne -->
                                                <div class="form-check mb-3">
                                                    <input class="form-check-input payment-method" type="radio" 
                                                        name="payment_method" value="online" id="online">
                                                    <label class="form-check-label" for="online">
                                                        <i class="fas fa-globe me-2"></i>Paiement en ligne
                                                    </label>
                                                </div>
                                                
                                                <!-- Options paiement en ligne (caché par défaut) -->
                                                <div id="onlinePaymentForm" class="border rounded p-3 mb-3" style="display: none;">
                                                    <h6><i class="fas fa-globe me-2"></i>Choisissez votre passerelle de paiement</h6>
                                                    <div class="row g-2">
                                                        <div class="col-6">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="radio" 
                                                                    name="online_gateway" value="paypal" id="paypal">
                                                                <label class="form-check-label" for="paypal">
                                                                    <img src="https://www.paypalobjects.com/webstatic/mktg/logo/pp_cc_mark_37x23.jpg" 
                                                                        alt="PayPal" height="20" class="me-2">
                                                                    PayPal
                                                                </label>
                                                            </div>
                                                        </div>
                                                        <div class="col-6">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="radio" 
                                                                    name="online_gateway" value="stripe" id="stripe">
                                                                <label class="form-check-label" for="stripe">
                                                                    <i class="fab fa-stripe text-primary me-2"></i>Stripe
                                                                </label>
                                                            </div>
                                                        </div>
                                                        <div class="col-6">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="radio" 
                                                                    name="online_gateway" value="paydunya" id="paydunya">
                                                                <label class="form-check-label" for="paydunya">
                                                                    PayDunya
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="alert alert-info mt-3 small">
                                                        <i class="fas fa-info-circle me-2"></i>
                                                        Vous serez redirigé vers la plateforme de paiement sécurisée pour finaliser votre transaction.
                                                    </div>
                                                </div>
                                                
                                                <!-- Mobile Money -->
                                                <div class="form-check mb-3">
                                                    <input class="form-check-input payment-method" type="radio" 
                                                        name="payment_method" value="mobile_money" id="mobile_money">
                                                    <label class="form-check-label" for="mobile_money">
                                                        <i class="fas fa-mobile-alt me-2"></i>Mobile Money
                                                    </label>
                                                </div>
                                                
                                                <!-- Formulaire Mobile Money (caché par défaut) -->
                                                <div id="mobileMoneyForm" class="border rounded p-3" style="display: none;">
                                                    <h6><i class="fas fa-mobile-alt me-2"></i>Paiement par Mobile Money</h6>
                                                    
                                                    <!-- Sélection du réseau -->
                                                    <div class="mb-3">
                                                        <label class="form-label">Réseau Mobile Money</label>
                                                        <select class="form-control" name="mobile_network" id="mobileNetwork">
                                                            <option value="">-- Sélectionnez votre réseau --</option>
                                                            <option value="mpesa">M-Pesa</option>
                                                            <option value="airtel">Airtel Money</option>
                                                            <option value="orange">Orange Money</option>
                                                            <option value="afrimoney">Afrimoney</option>
                                                            <option value="mtn">MTN Mobile Money</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <!-- Numéro de téléphone -->
                                                    <div class="mb-3">
                                                        <label class="form-label">Numéro de téléphone</label>
                                                        <input type="tel" class="form-control" 
                                                            name="mobile_number"
                                                            placeholder="Ex: +243 973 900 115"
                                                            oninput="formatPhoneNumber(this)">
                                                    </div>
                                                    
                                                    <!-- Processus étape par étape -->
                                                    <div class="alert alert-warning small">
                                                        <h6><i class="fas fa-list-ol me-2"></i>Processus de paiement:</h6>
                                                        <ol class="mb-0">
                                                            <li>Entrez votre numéro de téléphone Mobile Money</li>
                                                            <li>Cliquez sur "Confirmer la commande"</li>
                                                            <li>Vous recevrez une demande de paiement sur votre téléphone</li>
                                                            <li>Validez le paiement sur votre application Mobile Money</li>
                                                            <li>Votre commande sera confirmée automatiquement</li>
                                                        </ol>
                                                    </div>
                                                    
                                                    <!-- Frais de transaction -->
                                                    <div class="alert alert-info small mt-2">
                                                        <i class="fas fa-info-circle me-2"></i>
                                                        Des frais de transaction de 1% peuvent s'appliquer selon votre opérateur.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Bouton de validation -->
                                <div class="mt-4">
                                    <button class="btn btn-primary w-100 py-3" id="submitOrder">
                                        <i class="fas fa-check-circle me-2"></i>
                                        Valider la commande
                                    </button>
                                    <small class="text-muted d-block text-center mt-2">
                                        En validant, vous acceptez nos conditions générales
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- Modal de confirmation -->
    <div class="modal fade" id="confirmationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmer la commande</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="confirmationDetails"></div>
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle me-2"></i>
                        Votre commande sera préparée dès confirmation.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Modifier</button>
                    <button type="button" class="btn btn-primary" id="confirmOrderBtn">Confirmer</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Panier
            let cart = JSON.parse(localStorage.getItem('gourmet_cart')) || [];
            const serviceFee = 2.50; // Frais de service fixes
            const deliveryFee = 4.99; // Frais de livraison
            
            // Mettre à jour l'affichage du panier
            function updateCartDisplay() {
                const cartItems = document.getElementById('cartItems');
                const orderSummary = document.getElementById('orderSummary');
                const mobileCartPreview = document.getElementById('mobileCartPreview');
                const mobileCartCount = document.getElementById('mobileCartCount');
                
                // Mettre à jour le compteur mobile
                mobileCartCount.textContent = cart.reduce((sum, item) => sum + item.quantity, 0);
                
                if (cart.length === 0) {
                    // Panier vide
                    cartItems.innerHTML = `
                        <div class="cart-empty">
                            <i class="fas fa-shopping-cart fa-2x mb-3"></i>
                            <p class="mb-0">Votre panier est vide</p>
                            <small class="text-muted">Ajoutez des produits pour commencer</small>
                        </div>
                    `;
                    orderSummary.style.display = 'none';
                    
                    // Mettre à jour l'aperçu mobile
                    mobileCartPreview.innerHTML = `
                        <i class="fas fa-shopping-cart fa-2x mb-3"></i>
                        <p class="mb-0">Votre panier est vide</p>
                    `;
                } else {
                    // Afficher les articles du panier
                    let itemsHtml = '';
                    let subtotal = 0;
                    
                    cart.forEach((item, index) => {
                        const itemTotal = item.price * item.quantity;
                        subtotal += itemTotal;
                        
                        itemsHtml += `
                            <div class="cart-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1">${item.name}</h6>
                                        <small class="text-muted">${item.price.toFixed(2).replace('.', ',')} $</small>
                                    </div>
                                    <div class="quantity-control">
                                        <button class="btn btn-sm btn-outline-secondary quantity-btn minus" 
                                                data-index="${index}">-</button>
                                        <span class="quantity mx-2">${item.quantity}</span>
                                        <button class="btn btn-sm btn-outline-secondary quantity-btn plus" 
                                                data-index="${index}">+</button>
                                    </div>
                                    <div class="ms-3 text-end">
                                        <div class="fw-bold">${itemTotal.toFixed(2).replace('.', ',')} $</div>
                                        <button class="btn btn-sm btn-link text-danger p-0 remove-item" 
                                                data-index="${index}">
                                            <small><i class="fas fa-trash"></i></small>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    cartItems.innerHTML = itemsHtml;
                    orderSummary.style.display = 'block';
                    
                    // Mettre à jour les totaux
                    const orderType = document.querySelector('input[name="order_type"]:checked').value;
                    const isDelivery = orderType === 'delivery';
                    const isTakeaway = orderType === 'takeaway';
                    
                    const deliveryTotal = isDelivery ? deliveryFee : 0;
                    const serviceTotal = (!isTakeaway && !isDelivery) ? serviceFee : 0;
                    const total = subtotal + serviceTotal + deliveryTotal;
                    
                    document.getElementById('subtotal').textContent = subtotal.toFixed(2).replace('.', ',') + ' $';
                    document.getElementById('serviceFee').textContent = serviceTotal.toFixed(2).replace('.', ',') + ' $';
                    document.getElementById('deliveryFee').textContent = deliveryTotal.toFixed(2).replace('.', ',') + ' $';
                    document.getElementById('totalAmount').textContent = total.toFixed(2).replace('.', ',') + ' $';
                    
                    // Mettre à jour l'aperçu mobile
                    mobileCartPreview.innerHTML = `
                        <div class="p-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>${cart.reduce((sum, item) => sum + item.quantity, 0)} article(s)</span>
                                <span class="fw-bold">${total.toFixed(2).replace('.', ',')} €</span>
                            </div>
                            <div class="text-end">
                                <button class="btn btn-sm btn-outline-primary" onclick="scrollToCart()">
                                    Voir le panier
                                </button>
                            </div>
                        </div>
                    `;
                    
                    // Ajouter les événements aux boutons
                    document.querySelectorAll('.remove-item').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const index = parseInt(this.dataset.index);
                            removeFromCart(index);
                        });
                    });
                    
                    document.querySelectorAll('.quantity-btn.minus').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const index = parseInt(this.dataset.index);
                            updateQuantity(index, cart[index].quantity - 1);
                        });
                    });
                    
                    document.querySelectorAll('.quantity-btn.plus').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const index = parseInt(this.dataset.index);
                            updateQuantity(index, cart[index].quantity + 1);
                        });
                    });
                }
                
                // Sauvegarder le panier
                localStorage.setItem('gourmet_cart', JSON.stringify(cart));
            }
            
            // Fonction pour défiler vers le panier (mobile)
            window.scrollToCart = function() {
                document.querySelector('.order-summary').scrollIntoView({ behavior: 'smooth' });
            };
            
            // Ajouter au panier
            document.querySelectorAll('.add-to-cart').forEach(button => {
                button.addEventListener('click', function() {
                    const productId = parseInt(this.dataset.productId);
                    const productName = this.dataset.productName;
                    const productPrice = parseFloat(this.dataset.productPrice);
                    const productImage = this.dataset.productImage;
                    
                    // Vérifier si le produit est déjà dans le panier
                    const existingIndex = cart.findIndex(item => item.id === productId);
                    
                    if (existingIndex > -1) {
                        // Augmenter la quantité
                        cart[existingIndex].quantity += 1;
                    } else {
                        // Ajouter un nouvel article
                        cart.push({
                            id: productId,
                            name: productName,
                            price: productPrice,
                            image: productImage,
                            quantity: 1
                        });
                    }
                    
                    // Mettre à jour l'affichage
                    updateCartDisplay();
                    
                    // Animation de feedback
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-check me-1"></i>Ajouté';
                    this.classList.remove('btn-outline-primary');
                    this.classList.add('btn-success');
                    
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.classList.remove('btn-success');
                        this.classList.add('btn-outline-primary');
                    }, 1000);
                });
            });
            
            // Mettre à jour la quantité
            function updateQuantity(index, newQuantity) {
                if (newQuantity < 1) {
                    removeFromCart(index);
                    return;
                }
                
                if (newQuantity > 20) {
                    alert('Quantité maximale : 20 par article');
                    return;
                }
                
                cart[index].quantity = newQuantity;
                updateCartDisplay();
            }
            
            // Supprimer du panier
            function removeFromCart(index) {
                if (confirm('Supprimer cet article du panier ?')) {
                    cart.splice(index, 1);
                    updateCartDisplay();
                }
            }
            
            // Gestion du type de commande
            const orderTypeRadios = document.querySelectorAll('input[name="order_type"]');
            orderTypeRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    // Masquer toutes les sections
                    document.getElementById('dineInInfo').style.display = 'none';
                    document.getElementById('takeawayInfo').style.display = 'none';
                    document.getElementById('deliveryInfo').style.display = 'none';
                    
                    // Afficher la section appropriée
                    if (this.value === 'dine_in') {
                        document.getElementById('dineInInfo').style.display = 'block';
                    } else if (this.value === 'takeaway') {
                        document.getElementById('takeawayInfo').style.display = 'block';
                    } else if (this.value === 'delivery') {
                        document.getElementById('deliveryInfo').style.display = 'block';
                    }
                    
                    // Mettre à jour les frais dans le panier
                    updateCartDisplay();
                });
            });
            
            // Gestion de la soumission
            document.getElementById('submitOrder').addEventListener('click', function() {
                if (cart.length === 0) {
                    alert('Votre panier est vide. Ajoutez des articles avant de commander.');
                    return;
                }
                
                // Vérifications selon le type de commande
                const orderType = document.querySelector('input[name="order_type"]:checked').value;
                
                if (orderType === 'dine_in') {
                    const tableSelect = document.getElementById('tableSelect');
                    if (!tableSelect.value) {
                        if (!confirm('Aucune table sélectionnée. Voulez-vous continuer ? La table sera attribuée automatiquement.')) {
                            return;
                        }
                    }
                }
                
                if (orderType === 'delivery') {
                    const address = document.querySelector('input[name="delivery_address"]').value;
                    const zipcode = document.querySelector('input[name="delivery_zipcode"]').value;
                    const city = document.querySelector('input[name="delivery_city"]').value;
                    
                    if (!address || !zipcode || !city) {
                        alert('Veuillez compléter l\'adresse de livraison.');
                        return;
                    }
                }
                
                // Afficher la modal de confirmation
                showConfirmationModal();
            });
            
            // Afficher la modal de confirmation
            function showConfirmationModal() {
                const orderType = document.querySelector('input[name="order_type"]:checked').value;
                const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
                const notes = document.querySelector('textarea[name="order_notes"]').value;
                
                let details = `
                    <h6>Récapitulatif</h6>
                    <div class="mb-3">
                        <strong>Type:</strong> ${orderType === 'dine_in' ? 'Sur place' : orderType === 'takeaway' ? 'À emporter' : 'Livraison'}<br>
                        <strong>Articles:</strong> ${cart.reduce((sum, item) => sum + item.quantity, 0)}<br>
                        <strong>Paiement:</strong> ${paymentMethod === 'cash' ? 'À la réception' : paymentMethod === 'card' ? 'Carte bancaire' : 'En ligne'}
                    </div>
                    
                    <div class="border-top pt-3">
                        <h6>Articles</h6>
                `;
                
                cart.forEach(item => {
                    details += `
                        <div class="d-flex justify-content-between small">
                            <span>${item.name} x ${item.quantity}</span>
                            <span>${(item.price * item.quantity).toFixed(2).replace('.', ',')} €</span>
                        </div>
                    `;
                });
                
                // Calculer le total
                const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                const isDelivery = orderType === 'delivery';
                const isTakeaway = orderType === 'takeaway';
                const deliveryTotal = isDelivery ? deliveryFee : 0;
                const serviceTotal = (!isTakeaway && !isDelivery) ? serviceFee : 0;
                const total = subtotal + serviceTotal + deliveryTotal;
                
                details += `
                    </div>
                    <div class="border-top pt-3">
                        <div class="d-flex justify-content-between">
                            <strong>Total:</strong>
                            <strong>${total.toFixed(2).replace('.', ',')} $</strong>
                        </div>
                    </div>
                `;
                
                if (notes) {
                    details += `
                        <div class="mt-3">
                            <strong>Notes:</strong><br>
                            <small>${notes}</small>
                        </div>
                    `;
                }
                
                document.getElementById('confirmationDetails').innerHTML = details;
                const modal = new bootstrap.Modal(document.getElementById('confirmationModal'));
                modal.show();
            }
            
            // Confirmer la commande
            document.getElementById('confirmOrderBtn').addEventListener('click', function() {
                const submitBtn = document.getElementById('submitOrder');
                const confirmBtn = this;
                
                // Désactiver les boutons
                submitBtn.disabled = true;
                confirmBtn.disabled = true;
                confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Envoi...';
                
                // Préparer les données
                const formData = new FormData();
                // AJOUTEZ LE TOKEN CSRF ICI
                const csrfToken = '<?= Security::generateCSRFToken() ?>';
                formData.append('csrf_token', csrfToken);
                formData.append('action', 'create_order');
                formData.append('customer_id', <?= $user_id ?>);
                formData.append('customer_name', '<?= addslashes($user_name) ?>');
                formData.append('customer_email', '<?= addslashes($user_email) ?>');
                formData.append('customer_phone', '<?= addslashes($user_phone) ?>');
                
                // Type de commande et informations
                const orderType = document.querySelector('input[name="order_type"]:checked').value;
                formData.append('order_type', orderType);
                
                if (orderType === 'dine_in') {
                    const tableSelect = document.getElementById('tableSelect');
                    if (tableSelect.value) {
                        formData.append('table_number', tableSelect.value);
                    }
                } else if (orderType === 'takeaway') {
                    const pickupTime = document.querySelector('input[name="pickup_time"]').value;
                    formData.append('pickup_time', pickupTime);
                } else if (orderType === 'delivery') {
                    formData.append('delivery_address', document.querySelector('input[name="delivery_address"]').value);
                    formData.append('delivery_zipcode', document.querySelector('input[name="delivery_zipcode"]').value);
                    formData.append('delivery_city', document.querySelector('input[name="delivery_city"]').value);
                    formData.append('delivery_notes', document.querySelector('textarea[name="delivery_notes"]').value);
                }
                
                // Paiement
                const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
                formData.append('payment_method', paymentMethod);
                
                // Notes
                const notes = document.querySelector('textarea[name="order_notes"]').value;
                if (notes) {
                    formData.append('order_notes', notes);
                }
                
                // Articles du panier
                formData.append('cart_items', JSON.stringify(cart));
                
                // Calcul des totaux
                const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                const isDelivery = orderType === 'delivery';
                const isTakeaway = orderType === 'takeaway';
                const deliveryTotal = isDelivery ? deliveryFee : 0;
                const serviceTotal = (!isTakeaway && !isDelivery) ? serviceFee : 0;
                const total = subtotal + serviceTotal + deliveryTotal;
                
                formData.append('subtotal', subtotal);
                formData.append('tax_amount', 0); // À calculer selon votre région
                formData.append('total_amount', total);
                
                // Envoyer la requête
                fetch('../admin/api/client_commandes_newMMM.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Fermer la modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('confirmationModal'));
                        modal.hide();
                        
                        // Afficher le message de succès
                        const alert = document.createElement('div');
                        alert.className = 'alert alert-success alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3';
                        alert.style.zIndex = '1060';
                        alert.innerHTML = `
                            <i class="fas fa-check-circle me-2"></i>
                            ${data.message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        `;
                        document.body.appendChild(alert);
                        
                        // Vider le panier
                        cart = [];
                        localStorage.removeItem('gourmet_cart');
                        updateCartDisplay();
                        
                        // Rediriger après 3 secondes
                        setTimeout(() => {
                            window.location.href = 'commandes.php';
                        }, 3000);
                    } else {
                        alert('Erreur : ' + (data.message || 'Une erreur est survenue'));
                        confirmBtn.disabled = false;
                        confirmBtn.innerHTML = 'Confirmer';
                        submitBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Erreur réseau. Veuillez réessayer.');
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = 'Confirmer';
                    submitBtn.disabled = false;
                });
            });
            
            // Initialiser l'affichage du panier
            updateCartDisplay();
            
            // Gestion des dates/heures
            const pickupTime = document.querySelector('input[name="pickup_time"]');
            if (pickupTime) {
                const now = new Date();
                now.setMinutes(now.getMinutes() + 30);
                pickupTime.min = now.toTimeString().substring(0, 5);
                
                now.setMinutes(now.getMinutes() + 15);
                pickupTime.value = now.toTimeString().substring(0, 5);
            }
        });
    </script> -->

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Panier
            let cart = JSON.parse(localStorage.getItem('gourmet_cart')) || [];
            const serviceFee = 2.50; // Frais de service fixes
            const deliveryFee = 4.99; // Frais de livraison
            
            // ================ FONCTIONS EXISTANTES (ESSENTIELLES) ================
            
            // Mettre à jour l'affichage du panier
            function updateCartDisplay() {
                const cartItems = document.getElementById('cartItems');
                const orderSummary = document.getElementById('orderSummary');
                const mobileCartPreview = document.getElementById('mobileCartPreview');
                const mobileCartCount = document.getElementById('mobileCartCount');
                
                // Mettre à jour le compteur mobile
                mobileCartCount.textContent = cart.reduce((sum, item) => sum + item.quantity, 0);
                
                if (cart.length === 0) {
                    // Panier vide
                    cartItems.innerHTML = `
                        <div class="cart-empty">
                            <i class="fas fa-shopping-cart fa-2x mb-3"></i>
                            <p class="mb-0">Votre panier est vide</p>
                            <small class="text-muted">Ajoutez des produits pour commencer</small>
                        </div>
                    `;
                    orderSummary.style.display = 'none';
                    
                    // Mettre à jour l'aperçu mobile
                    mobileCartPreview.innerHTML = `
                        <i class="fas fa-shopping-cart fa-2x mb-3"></i>
                        <p class="mb-0">Votre panier est vide</p>
                    `;
                } else {
                    // Afficher les articles du panier
                    let itemsHtml = '';
                    let subtotal = 0;
                    
                    cart.forEach((item, index) => {
                        const itemTotal = item.price * item.quantity;
                        subtotal += itemTotal;
                        
                        itemsHtml += `
                            <div class="cart-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1">${item.name}</h6>
                                        <small class="text-muted">${item.price.toFixed(2).replace('.', ',')} $</small>
                                    </div>
                                    <div class="quantity-control">
                                        <button class="btn btn-sm btn-outline-secondary quantity-btn minus" 
                                                data-index="${index}">-</button>
                                        <span class="quantity mx-2">${item.quantity}</span>
                                        <button class="btn btn-sm btn-outline-secondary quantity-btn plus" 
                                                data-index="${index}">+</button>
                                    </div>
                                    <div class="ms-3 text-end">
                                        <div class="fw-bold">${itemTotal.toFixed(2).replace('.', ',')} $</div>
                                        <button class="btn btn-sm btn-link text-danger p-0 remove-item" 
                                                data-index="${index}">
                                            <small><i class="fas fa-trash"></i></small>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    cartItems.innerHTML = itemsHtml;
                    orderSummary.style.display = 'block';
                    
                    // Mettre à jour les totaux
                    const orderType = document.querySelector('input[name="order_type"]:checked').value;
                    const isDelivery = orderType === 'delivery';
                    const isTakeaway = orderType === 'takeaway';
                    
                    const deliveryTotal = isDelivery ? deliveryFee : 0;
                    const serviceTotal = (!isTakeaway && !isDelivery) ? serviceFee : 0;
                    const total = subtotal + serviceTotal + deliveryTotal;
                    
                    document.getElementById('subtotal').textContent = subtotal.toFixed(2).replace('.', ',') + ' $';
                    document.getElementById('serviceFee').textContent = serviceTotal.toFixed(2).replace('.', ',') + ' $';
                    document.getElementById('deliveryFee').textContent = deliveryTotal.toFixed(2).replace('.', ',') + ' $';
                    document.getElementById('totalAmount').textContent = total.toFixed(2).replace('.', ',') + ' $';
                    
                    // Mettre à jour l'aperçu mobile
                    mobileCartPreview.innerHTML = `
                        <div class="p-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>${cart.reduce((sum, item) => sum + item.quantity, 0)} article(s)</span>
                                <span class="fw-bold">${total.toFixed(2).replace('.', ',')} €</span>
                            </div>
                            <div class="text-end">
                                <button class="btn btn-sm btn-outline-primary" onclick="scrollToCart()">
                                    Voir le panier
                                </button>
                            </div>
                        </div>
                    `;
                    
                    // Ajouter les événements aux boutons
                    document.querySelectorAll('.remove-item').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const index = parseInt(this.dataset.index);
                            removeFromCart(index);
                        });
                    });
                    
                    document.querySelectorAll('.quantity-btn.minus').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const index = parseInt(this.dataset.index);
                            updateQuantity(index, cart[index].quantity - 1);
                        });
                    });
                    
                    document.querySelectorAll('.quantity-btn.plus').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const index = parseInt(this.dataset.index);
                            updateQuantity(index, cart[index].quantity + 1);
                        });
                    });
                }
                
                // Sauvegarder le panier
                localStorage.setItem('gourmet_cart', JSON.stringify(cart));
            }
            
            // Fonction pour défiler vers le panier (mobile)
            window.scrollToCart = function() {
                document.querySelector('.order-summary').scrollIntoView({ behavior: 'smooth' });
            };
            
            // Ajouter au panier
            document.querySelectorAll('.add-to-cart').forEach(button => {
                button.addEventListener('click', function() {
                    const productId = parseInt(this.dataset.productId);
                    const productName = this.dataset.productName;
                    const productPrice = parseFloat(this.dataset.productPrice);
                    const productImage = this.dataset.productImage;
                    
                    // Vérifier si le produit est déjà dans le panier
                    const existingIndex = cart.findIndex(item => item.id === productId);
                    
                    if (existingIndex > -1) {
                        // Augmenter la quantité
                        cart[existingIndex].quantity += 1;
                    } else {
                        // Ajouter un nouvel article
                        cart.push({
                            id: productId,
                            name: productName,
                            price: productPrice,
                            image: productImage,
                            quantity: 1
                        });
                    }
                    
                    // Mettre à jour l'affichage
                    updateCartDisplay();
                    
                    // Animation de feedback
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-check me-1"></i>Ajouté';
                    this.classList.remove('btn-outline-primary');
                    this.classList.add('btn-success');
                    
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.classList.remove('btn-success');
                        this.classList.add('btn-outline-primary');
                    }, 1000);
                });
            });
            
            // Mettre à jour la quantité
            function updateQuantity(index, newQuantity) {
                if (newQuantity < 1) {
                    removeFromCart(index);
                    return;
                }
                
                if (newQuantity > 20) {
                    alert('Quantité maximale : 20 par article');
                    return;
                }
                
                cart[index].quantity = newQuantity;
                updateCartDisplay();
            }
            
            // Supprimer du panier
            function removeFromCart(index) {
                if (confirm('Supprimer cet article du panier ?')) {
                    cart.splice(index, 1);
                    updateCartDisplay();
                }
            }
            
            // Gestion du type de commande
            const orderTypeRadios = document.querySelectorAll('input[name="order_type"]');
            orderTypeRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    // Masquer toutes les sections
                    document.getElementById('dineInInfo').style.display = 'none';
                    document.getElementById('takeawayInfo').style.display = 'none';
                    document.getElementById('deliveryInfo').style.display = 'none';
                    
                    // Afficher la section appropriée
                    if (this.value === 'dine_in') {
                        document.getElementById('dineInInfo').style.display = 'block';
                    } else if (this.value === 'takeaway') {
                        document.getElementById('takeawayInfo').style.display = 'block';
                    } else if (this.value === 'delivery') {
                        document.getElementById('deliveryInfo').style.display = 'block';
                    }
                    
                    // Mettre à jour les frais dans le panier
                    updateCartDisplay();
                });
            });
            
            // Gestion de la soumission
            document.getElementById('submitOrder').addEventListener('click', function() {
                if (cart.length === 0) {
                    alert('Votre panier est vide. Ajoutez des articles avant de commander.');
                    return;
                }
                
                // Vérifications selon le type de commande
                const orderType = document.querySelector('input[name="order_type"]:checked').value;
                
                if (orderType === 'dine_in') {
                    const tableSelect = document.getElementById('tableSelect');
                    if (!tableSelect.value) {
                        if (!confirm('Aucune table sélectionnée. Voulez-vous continuer ? La table sera attribuée automatiquement.')) {
                            return;
                        }
                    }
                }
                
                if (orderType === 'delivery') {
                    const address = document.querySelector('input[name="delivery_address"]').value;
                    const zipcode = document.querySelector('input[name="delivery_zipcode"]').value;
                    const city = document.querySelector('input[name="delivery_city"]').value;
                    
                    if (!address || !zipcode || !city) {
                        alert('Veuillez compléter l\'adresse de livraison.');
                        return;
                    }
                }
                
                // Afficher la modal de confirmation
                showConfirmationModal();
            });
            
            // Afficher la modal de confirmation
            function showConfirmationModal() {
                const orderType = document.querySelector('input[name="order_type"]:checked').value;
                const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
                const notes = document.querySelector('textarea[name="order_notes"]').value;
                
                let details = `
                    <h6>Récapitulatif</h6>
                    <div class="mb-3">
                        <strong>Type:</strong> ${orderType === 'dine_in' ? 'Sur place' : orderType === 'takeaway' ? 'À emporter' : 'Livraison'}<br>
                        <strong>Articles:</strong> ${cart.reduce((sum, item) => sum + item.quantity, 0)}<br>
                        <strong>Paiement:</strong> ${paymentMethod === 'cash' ? 'À la réception' : paymentMethod === 'card' ? 'Carte bancaire' : 'En ligne'}
                    </div>
                    
                    <div class="border-top pt-3">
                        <h6>Articles</h6>
                `;
                
                cart.forEach(item => {
                    details += `
                        <div class="d-flex justify-content-between small">
                            <span>${item.name} x ${item.quantity}</span>
                            <span>${(item.price * item.quantity).toFixed(2).replace('.', ',')} €</span>
                        </div>
                    `;
                });
                
                // Calculer le total
                const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                const isDelivery = orderType === 'delivery';
                const isTakeaway = orderType === 'takeaway';
                const deliveryTotal = isDelivery ? deliveryFee : 0;
                const serviceTotal = (!isTakeaway && !isDelivery) ? serviceFee : 0;
                const total = subtotal + serviceTotal + deliveryTotal;
                
                details += `
                    </div>
                    <div class="border-top pt-3">
                        <div class="d-flex justify-content-between">
                            <strong>Total:</strong>
                            <strong>${total.toFixed(2).replace('.', ',')} $</strong>
                        </div>
                    </div>
                `;
                
                if (notes) {
                    details += `
                        <div class="mt-3">
                            <strong>Notes:</strong><br>
                            <small>${notes}</small>
                        </div>
                    `;
                }
                
                document.getElementById('confirmationDetails').innerHTML = details;
                const modal = new bootstrap.Modal(document.getElementById('confirmationModal'));
                modal.show();
            }
            
            // ================ FONCTIONS DE FORMATAGE ================
            
            // Fonctions de formatage
            function formatCardNumber(input) {
                let value = input.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
                let formatted = '';
                
                for (let i = 0; i < value.length; i++) {
                    if (i > 0 && i % 4 === 0) {
                        formatted += ' ';
                    }
                    formatted += value[i];
                }
                
                input.value = formatted.substring(0, 19); // 16 chiffres + 3 espaces
            }

            function formatExpiryDate(input) {
                let value = input.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
                
                if (value.length >= 2) {
                    let month = value.substring(0, 2);
                    let year = value.substring(2, 4);
                    
                    if (parseInt(month) > 12) {
                        month = '12';
                    }
                    
                    input.value = month + (year ? '/' + year : '');
                }
            }

            function formatPhoneNumber(input) {
                let value = input.value.replace(/\s+/g, '').replace(/[^0-9+]/gi, '');
                
                // Ajouter le préfixe + si nécessaire
                if (!value.startsWith('+')) {
                    if (value.startsWith('0')) {
                        value = '+243' + value.substring(1);
                    } else if (!value.startsWith('243')) {
                        value = '+243' + value;
                    } else {
                        value = '+' + value;
                    }
                }
                
                // Formater avec des espaces
                let formatted = '';
                if (value.startsWith('+243')) {
                    formatted = '+243 ' + value.substring(4, 6) + ' ' + value.substring(6, 9) + ' ' + value.substring(9, 12);
                } else {
                    formatted = value;
                }
                
                input.value = formatted.trim();
            }
            
            // Gestion de l'affichage des formulaires de paiement
            document.querySelectorAll('.payment-method').forEach(radio => {
                radio.addEventListener('change', function() {
                    // Cacher tous les formulaires
                    const cardForm = document.getElementById('cardPaymentForm');
                    const onlineForm = document.getElementById('onlinePaymentForm');
                    const mobileForm = document.getElementById('mobileMoneyForm');
                    
                    if (cardForm) cardForm.style.display = 'none';
                    if (onlineForm) onlineForm.style.display = 'none';
                    if (mobileForm) mobileForm.style.display = 'none';
                    
                    // Afficher le formulaire correspondant
                    if (this.value === 'card' && cardForm) {
                        cardForm.style.display = 'block';
                    } else if (this.value === 'online' && onlineForm) {
                        onlineForm.style.display = 'block';
                    } else if (this.value === 'mobile_money' && mobileForm) {
                        mobileForm.style.display = 'block';
                    }
                });
            });
            
            // ================ CONFIRMATION DE COMMANDE ================
            
            // Confirmer la commande - VERSION CORRIGÉE
            document.getElementById('confirmOrderBtn').addEventListener('click', function() {
                const submitBtn = document.getElementById('submitOrder');
                const confirmBtn = this;
                
                // =================== VALIDATION DES PAIEMENTS ===================
                // Déplacer la validation ICI, avant l'envoi
                const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
                
                if (paymentMethod === 'card') {
                    const cardNumber = document.querySelector('#cardPaymentForm input[type="text"]')?.value;
                    const expiryDate = document.querySelector('#cardPaymentForm input[placeholder="MM/AA"]')?.value;
                    const cvv = document.querySelector('#cardPaymentForm input[type="password"]')?.value;
                    
                    if (!cardNumber || cardNumber.replace(/\s/g, '').length < 16) {
                        alert('Veuillez entrer un numéro de carte valide');
                        return;
                    }
                    
                    if (!expiryDate || expiryDate.length !== 5) {
                        alert('Veuillez entrer une date d\'expiration valide (MM/AA)');
                        return;
                    }
                    
                    if (!cvv || cvv.length < 3) {
                        alert('Veuillez entrer le code CVV');
                        return;
                    }
                }
                
                if (paymentMethod === 'mobile_money') {
                    const mobileNetwork = document.querySelector('#mobileNetwork')?.value;
                    const mobileNumber = document.querySelector('input[name="mobile_number"]')?.value;
                    
                    if (!mobileNetwork) {
                        alert('Veuillez sélectionner votre réseau Mobile Money');
                        return;
                    }
                    
                    if (!mobileNumber || mobileNumber.replace(/\s/g, '').length < 10) {
                        alert('Veuillez entrer un numéro de téléphone valide');
                        return;
                    }
                }
                
                if (paymentMethod === 'online') {
                    const onlineGateway = document.querySelector('input[name="online_gateway"]:checked')?.value;
                    if (!onlineGateway) {
                        alert('Veuillez sélectionner une passerelle de paiement en ligne');
                        return;
                    }
                }
                // =================== FIN VALIDATION ===================
                
                // Désactiver les boutons
                submitBtn.disabled = true;
                confirmBtn.disabled = true;
                confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Envoi...';
                
                // Préparer les données
                const formData = new FormData();
                const csrfToken = '<?= Security::generateCSRFToken() ?>';
                formData.append('csrf_token', csrfToken);
                formData.append('action', 'create_order');
                formData.append('customer_id', <?= $user_id ?>);
                formData.append('customer_name', '<?= addslashes($user_name) ?>');
                formData.append('customer_email', '<?= addslashes($user_email) ?>');
                formData.append('customer_phone', '<?= addslashes($user_phone) ?>');
                
                // Type de commande et informations
                const orderType = document.querySelector('input[name="order_type"]:checked').value;
                formData.append('order_type', orderType);
                
                if (orderType === 'dine_in') {
                    const tableSelect = document.getElementById('tableSelect');
                    if (tableSelect.value) {
                        formData.append('table_number', tableSelect.value);
                    }
                } else if (orderType === 'takeaway') {
                    const pickupTime = document.querySelector('input[name="pickup_time"]').value;
                    formData.append('pickup_time', pickupTime);
                } else if (orderType === 'delivery') {
                    formData.append('delivery_address', document.querySelector('input[name="delivery_address"]').value);
                    formData.append('delivery_zipcode', document.querySelector('input[name="delivery_zipcode"]').value);
                    formData.append('delivery_city', document.querySelector('input[name="delivery_city"]').value);
                    formData.append('delivery_notes', document.querySelector('textarea[name="delivery_notes"]').value);
                }
                
                // Paiement - Ajouter les détails spécifiques
                formData.append('payment_method', paymentMethod);
                
                // Ajouter les détails spécifiques au mode de paiement
                if (paymentMethod === 'card') {
                    const cardNumber = document.querySelector('#cardPaymentForm input[type="text"]')?.value;
                    const expiryDate = document.querySelector('#cardPaymentForm input[placeholder="MM/AA"]')?.value;
                    const cardName = document.querySelector('#cardPaymentForm input[placeholder="JEAN DUPONT"]')?.value;
                    
                    // Ne jamais envoyer les données sensibles en clair dans un environnement réel!
                    // Utiliser un tokenisation ou un service sécurisé
                    formData.append('payment_card_last4', cardNumber ? cardNumber.slice(-4) : '');
                    formData.append('payment_card_expiry', expiryDate || '');
                    if (cardName) formData.append('payment_card_name', cardName);
                }
                
                if (paymentMethod === 'mobile_money') {
                    const mobileNetwork = document.querySelector('#mobileNetwork')?.value;
                    const mobileNumber = document.querySelector('input[name="mobile_number"]')?.value;
                    
                    formData.append('payment_mobile_network', mobileNetwork || '');
                    formData.append('payment_mobile_number', mobileNumber || '');
                }
                
                if (paymentMethod === 'online') {
                    const onlineGateway = document.querySelector('input[name="online_gateway"]:checked')?.value;
                    formData.append('payment_online_gateway', onlineGateway || '');
                }
                
                // Notes
                const notes = document.querySelector('textarea[name="order_notes"]').value;
                if (notes) {
                    formData.append('order_notes', notes);
                }
                
                // Articles du panier
                formData.append('cart_items', JSON.stringify(cart));
                
                // Calcul des totaux
                const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                const isDelivery = orderType === 'delivery';
                const isTakeaway = orderType === 'takeaway';
                const deliveryTotal = isDelivery ? deliveryFee : 0;
                const serviceTotal = (!isTakeaway && !isDelivery) ? serviceFee : 0;
                const total = subtotal + serviceTotal + deliveryTotal;
                
                formData.append('subtotal', subtotal);
                formData.append('tax_amount', 0);
                formData.append('total_amount', total);
                
                // Envoyer la requête
                fetch('../admin/api/client_commandes_newMMM.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Fermer la modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('confirmationModal'));
                        modal.hide();
                        
                        // Afficher le message de succès
                        const alert = document.createElement('div');
                        alert.className = 'alert alert-success alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3';
                        alert.style.zIndex = '1060';
                        alert.innerHTML = `
                            <i class="fas fa-check-circle me-2"></i>
                            ${data.message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        `;
                        document.body.appendChild(alert);
                        
                        // Vider le panier
                        cart = [];
                        localStorage.removeItem('gourmet_cart');
                        updateCartDisplay();
                        
                        // Rediriger après 3 secondes
                        setTimeout(() => {
                            window.location.href = 'commandes.php';
                        }, 3000);
                    } else {
                        alert('Erreur : ' + (data.message || 'Une erreur est survenue'));
                        confirmBtn.disabled = false;
                        confirmBtn.innerHTML = 'Confirmer';
                        submitBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Erreur réseau. Veuillez réessayer.');
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = 'Confirmer';
                    submitBtn.disabled = false;
                });
            });
            
            // ================ INITIALISATION ================
            
            // Initialiser l'affichage du panier
            updateCartDisplay();
            
            // Gestion des dates/heures
            const pickupTime = document.querySelector('input[name="pickup_time"]');
            if (pickupTime) {
                const now = new Date();
                now.setMinutes(now.getMinutes() + 30);
                pickupTime.min = now.toTimeString().substring(0, 5);
                
                now.setMinutes(now.getMinutes() + 15);
                pickupTime.value = now.toTimeString().substring(0, 5);
            }
            
            // Ajouter les événements de formatage aux champs
            const cardNumberInput = document.querySelector('#cardPaymentForm input[type="text"]');
            const expiryDateInput = document.querySelector('#cardPaymentForm input[placeholder="MM/AA"]');
            const phoneInput = document.querySelector('input[name="mobile_number"]');
            
            if (cardNumberInput) {
                cardNumberInput.addEventListener('input', function() {
                    formatCardNumber(this);
                });
            }
            
            if (expiryDateInput) {
                expiryDateInput.addEventListener('input', function() {
                    formatExpiryDate(this);
                });
            }
            
            if (phoneInput) {
                phoneInput.addEventListener('input', function() {
                    formatPhoneNumber(this);
                });
            }
        });
    </script>
