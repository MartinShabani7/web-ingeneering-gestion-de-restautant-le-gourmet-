<?php
// ../admin/api/client_commande_new.php
session_start();
require_once '../../config/database.php';
require_once '../../config/security.php';

header('Content-Type: application/json');

// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log pour voir ce qui est reçu
error_log("=== NOUVELLE COMMANDE ===");
error_log("POST reçu: " . print_r($_POST, true));
error_log("SESSION: " . print_r($_SESSION, true));

// Vérifier la session
if (!Security::isLoggedIn()) {
    error_log("Utilisateur non connecté");
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Vérifier le token CSRF
$csrf_token = $_POST['csrf_token'] ?? '';
error_log("Token CSRF reçu: " . $csrf_token);
error_log("Token CSRF session: " . ($_SESSION['csrf_token'] ?? 'VIDE'));

if (!Security::verifyCSRFToken($csrf_token)) {
    error_log("Token CSRF invalide!");
    http_response_code(403);
    echo json_encode([
        'success' => false, 
        'message' => 'Token CSRF invalide',
        'debug' => [
            'received' => $csrf_token,
            'expected' => $_SESSION['csrf_token'] ?? null
        ]
    ]);
    exit();
}

// Vérifier la méthode
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Méthode non autorisée: " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

try {
    // Vérifier l'action
    $action = $_POST['action'] ?? '';
    error_log("Action demandée: " . $action);
    
    if ($action !== 'create_order') {
        throw new Exception('Action invalide: ' . $action);
    }
    
    // Récupérer les données avec validation
    $customer_id = (int)($_POST['customer_id'] ?? 0);
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_email = trim($_POST['customer_email'] ?? '');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $order_type = $_POST['order_type'] ?? 'dine_in';
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $notes = trim($_POST['order_notes'] ?? $_POST['notes'] ?? '');

    // Stocker les détails du paiement
    $payment_details = [];

    // Gestion du mobile money
    $payment_method_db = $payment_method;
    if ($payment_method === 'mobile_money') {
        $payment_method_db = 'online'; // ou 'cash' selon votre choix
        
        // Récupérer les détails du mobile money
        $mobile_network = $_POST['payment_mobile_network'] ?? '';
        $mobile_number = $_POST['payment_mobile_number'] ?? '';
        
        $payment_details = [
            'original_method' => 'mobile_money',
            'network' => $mobile_network,
            'number' => $mobile_number,
            'processed_as' => $payment_method_db
        ];
        
        error_log("Mobile Money - Réseau: $mobile_network, Numéro: $mobile_number");
        
    } elseif ($payment_method === 'card') {
        // Récupérer les détails de la carte
        $card_last4 = $_POST['payment_card_last4'] ?? '';
        $card_expiry = $_POST['payment_card_expiry'] ?? '';
        $card_name = $_POST['payment_card_name'] ?? '';
        
        $payment_details = [
            'original_method' => 'card',
            'card_last4' => $card_last4,
            'card_expiry' => $card_expiry,
            'card_name' => $card_name
        ];
        
        error_log("Carte bancaire - Derniers 4: $card_last4, Exp: $card_expiry");
        
    } elseif ($payment_method === 'online') {
        // Récupérer la passerelle
        $online_gateway = $_POST['payment_online_gateway'] ?? '';
        
        $payment_details = [
            'original_method' => 'online',
            'gateway' => $online_gateway
        ];
        
        error_log("Paiement en ligne - Passerelle: $online_gateway");
    }

    error_log("Méthode paiement: originale=$payment_method, DB=$payment_method_db");
    error_log("Détails paiement: " . json_encode($payment_details));
    error_log("Données client: ID=$customer_id, Nom=$customer_name, Email=$customer_email, Type=$order_type, Téléphone=$customer_phone");
    
    // Données spécifiques au type de commande
    $table_number = null;
    $pickup_time = null;
    $delivery_address = null;
    $delivery_zipcode = null;
    $delivery_city = null;
    $delivery_notes = null;
    
    if ($order_type === 'dine_in') {
        $table_number = $_POST['table_number'] ?? null;
        error_log("Table sélectionnée: " . ($table_number ?: 'Aucune'));
    } elseif ($order_type === 'takeaway') {
        $pickup_time = $_POST['pickup_time'] ?? null;
        error_log("Heure de récupération: " . ($pickup_time ?: 'Non spécifiée'));
    } elseif ($order_type === 'delivery') {
        $delivery_address = $_POST['delivery_address'] ?? null;
        $delivery_zipcode = $_POST['delivery_zipcode'] ?? null;
        $delivery_city = $_POST['delivery_city'] ?? null;
        $delivery_notes = $_POST['delivery_notes'] ?? null;
        
        error_log("Adresse livraison: $delivery_address, $delivery_zipcode $delivery_city");
        
        // Validation des informations de livraison
        if (empty($delivery_address) || empty($delivery_zipcode) || empty($delivery_city)) {
            throw new Exception('Adresse de livraison incomplète');
        }
    }
    
    // Récupérer les articles du panier
    $cart_json = $_POST['cart_items'] ?? '[]';
    $cart_items = json_decode($cart_json, true);
    
    error_log("Nombre d'articles dans le panier: " . count($cart_items));
    error_log("Panier JSON: " . substr($cart_json, 0, 200) . "...");
    
    if (empty($cart_items)) {
        throw new Exception('Le panier est vide');
    }
    
    // Valider les données
    if (empty($customer_id)) {
        throw new Exception('ID client invalide');
    }
    
    if (empty($customer_name) || empty($customer_email)) {
        throw new Exception('Informations client incomplètes');
    }
    
    // Calculer les totaux depuis le panier
    $subtotal = 0;
    foreach ($cart_items as $item) {
        if (!isset($item['price']) || !isset($item['quantity'])) {
            throw new Exception('Article du panier invalide');
        }
        $item_total = $item['price'] * $item['quantity'];
        $subtotal += $item_total;
    }
    
    // Calculer la TVA (exemple: 10%)
    $tax_rate = 0.10; // 10% de TVA
    $tax_amount = round($subtotal * $tax_rate, 2);
    
    // Calculer les frais supplémentaires
    $service_fee = ($order_type === 'dine_in') ? 2.50 : 0;
    $delivery_fee = ($order_type === 'delivery') ? 4.99 : 0;
    $discount_amount = 0;
    
    $total_amount = $subtotal + $tax_amount + $service_fee + $delivery_fee - $discount_amount;
    
    error_log("Calculs: Sous-total=$subtotal, TVA=$tax_amount, Service=$service_fee, Livraison=$delivery_fee, Total=$total_amount");
    
    // Vérifier les prix
    if ($subtotal <= 0 || $total_amount <= 0) {
        throw new Exception('Montant invalide: Sous-total=' . $subtotal . ', Total=' . $total_amount);
    }
    
    // Vérifier la disponibilité des produits
    $product_ids = array_column($cart_items, 'id');
    if (empty($product_ids)) {
        throw new Exception('Aucun produit valide dans le panier');
    }
    
    // Vérifier la disponibilité des produits
    $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT id, name, price, is_available 
        FROM products 
        WHERE id IN ($placeholders)
    ");
    $stmt->execute($product_ids);
    $available_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Produits disponibles trouvés: " . count($available_products));
    
    if (count($available_products) !== count($product_ids)) {
        $found_ids = array_column($available_products, 'id');
        $missing_ids = array_diff($product_ids, $found_ids);
        throw new Exception('Certains produits ne sont pas disponibles: ' . implode(', ', $missing_ids));
    }
    
    $products_by_id = [];
    foreach ($available_products as $product) {
        $products_by_id[$product['id']] = $product;
    }
    
    // Vérifier chaque article
    foreach ($cart_items as $item) {
        if (!isset($products_by_id[$item['id']])) {
            throw new Exception("Produit non trouvé: ID {$item['id']}");
        }
        
        $product = $products_by_id[$item['id']];
        if (!$product['is_available']) {
            throw new Exception("Produit non disponible: {$product['name']}");
        }
        
        // Vérifier le prix (tolérance de 0.01€)
        if (abs($product['price'] - $item['price']) > 0.01) {
            throw new Exception("Prix incohérent pour: {$product['name']}. Prix attendu: {$product['price']}, Prix reçu: {$item['price']}");
        }
    }
    
    // Vérifier la table si commande sur place
    if ($order_type === 'dine_in' && $table_number) {
        $stmt = $pdo->prepare("
            SELECT id, table_number, is_available 
            FROM tables 
            WHERE table_number = ? AND is_available = 1
        ");
        $stmt->execute([$table_number]);
        $table = $stmt->fetch();
        
        if (!$table) {
            error_log("Table $table_number non disponible, continuation sans table spécifique");
            $table_number = null;
        }
    }
    
    // Générer un numéro de commande unique
    $order_number = 'CMD' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Vérifier l'unicité du numéro de commande
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE order_number = ?");
    $stmt->execute([$order_number]);
    if ($stmt->fetchColumn() > 0) {
        $order_number = 'CMD' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
    
    error_log("Numéro de commande généré: $order_number");
    
    // Préparer les notes finales
    $final_notes = $notes;
    if ($order_type === 'delivery' && $delivery_notes) {
        $final_notes .= ($final_notes ? "\n\n" : "") . "Instructions livraison:\n" . $delivery_notes;
    }
    
    if ($order_type === 'takeaway' && $pickup_time) {
        $final_notes .= ($final_notes ? "\n\n" : "") . "Heure de récupération souhaitée: " . $pickup_time;
    }
    
    // Commencer la transaction
    $pdo->beginTransaction();
    
    try {
        // 1. Insérer la commande principale
        error_log("Insertion de la commande principale...");
        $stmt = $pdo->prepare("
            INSERT INTO orders (
                order_number,
                customer_id,
                table_number,
                order_type,
                status,
                subtotal,
                tax_amount,
                discount_amount,
                total_amount,
                payment_status,
                payment_method,
                payment_details,
                notes,
                created_at,
                updated_at
            ) VALUES (
                :order_number,
                :customer_id,
                :table_number,
                :order_type,
                :status,
                :subtotal,
                :tax_amount,
                :discount_amount,
                :total_amount,
                :payment_status,
                :payment_method,
                :payment_details,
                :notes,
                NOW(),
                NOW()
            )
        ");
        
        $stmt->execute([
            ':order_number' => $order_number,
            ':customer_id' => $customer_id,
            ':table_number' => $table_number,
            ':order_type' => $order_type,
            ':status' => 'pending',
            ':subtotal' => $subtotal,
            ':tax_amount' => $tax_amount,
            ':discount_amount' => $discount_amount,
            ':total_amount' => $total_amount,
            ':payment_status' => 'pending',
            ':payment_method' => $payment_method_db,
            ':payment_details' => !empty($payment_details) ? json_encode($payment_details) : null,
            ':notes' => $final_notes
        ]);
        
        $order_id = $pdo->lastInsertId();
        error_log("Commande créée avec ID: $order_id");
        
        // 2. Insérer les articles de la commande selon votre structure
        $order_items_sql = "
            INSERT INTO order_items (
                order_id,
                product_id,
                quantity,
                unit_price,
                total_price,
                special_instructions,
                created_at
            ) VALUES (:order_id, :product_id, :quantity, :unit_price, :total_price, :special_instructions, NOW())
        ";
        
        $stmt = $pdo->prepare($order_items_sql);
        
        foreach ($cart_items as $item) {
            $product = $products_by_id[$item['id']];
            $item_total = $item['price'] * $item['quantity'];
            
            error_log("Insertion article: {$product['name']} x{$item['quantity']} = $item_total €");
            
            $stmt->execute([
                ':order_id' => $order_id,
                ':product_id' => $item['id'],
                ':quantity' => $item['quantity'],
                ':unit_price' => $item['price'],
                ':total_price' => $item_total,
                ':special_instructions' => ''
            ]);
        }
        
        // 3. Enregistrer l'historique du statut (table order_status_history)
        try {
            $stmt = $pdo->prepare("
                INSERT INTO order_status_history (
                    order_id,
                    status,
                    notes,
                    changed_by,
                    created_at
                ) VALUES (?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $order_id,
                'pending',
                'Commande créée par le client',
                'customer_' . $customer_id
            ]);
            error_log("Historique de statut créé");
        } catch (Exception $e) {
            // La table order_status_history n'existe peut-être pas
            error_log("Note: order_status_history non disponible: " . $e->getMessage());
        }
        
        // 4. Mettre à jour la table si nécessaire
        if ($order_type === 'dine_in' && $table_number && isset($table)) {
            $stmt = $pdo->prepare("
                UPDATE tables 
                SET is_available = 0 
                WHERE table_number = ?
            ");
            $stmt->execute([$table_number]);
            
            // Enregistrer l'occupation de la table (si la table table_occupations existe)
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO table_occupations (
                        table_id,
                        table_number,
                        order_id,
                        customer_id,
                        start_time,
                        expected_end_time,
                        created_at
                    ) VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 2 HOUR), NOW())
                ");
                
                $stmt->execute([
                    $table['id'],
                    $table_number,
                    $order_id,
                    $customer_id
                ]);
                error_log("Occupation de table enregistrée");
            } catch (Exception $e) {
                // La table table_occupations n'existe peut-être pas
                error_log("Note: table_occupations non disponible: " . $e->getMessage());
            }
        }
        
        // 5. Enregistrer l'adresse de livraison si nécessaire
        if ($order_type === 'delivery') {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO delivery_addresses (
                        order_id,
                        address,
                        zipcode,
                        city,
                        notes,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $order_id,
                    $delivery_address,
                    $delivery_zipcode,
                    $delivery_city,
                    $delivery_notes
                ]);
                error_log("Adresse de livraison enregistrée");
            } catch (Exception $e) {
                // La table delivery_addresses n'existe peut-être pas
                error_log("Note: delivery_addresses non disponible: " . $e->getMessage());
                
                // Ajouter l'adresse dans les notes si la table n'existe pas
                if ($delivery_address) {
                    $address_note = "\n\nAdresse de livraison:\n" . 
                                   $delivery_address . "\n" . 
                                   $delivery_zipcode . " " . $delivery_city;
                    
                    $stmt = $pdo->prepare("
                        UPDATE orders 
                        SET notes = CONCAT(notes, ?) 
                        WHERE id = ?
                    ");
                    $stmt->execute([$address_note, $order_id]);
                    error_log("Adresse de livraison ajoutée aux notes");
                }
            }
        }
        
        // 6. Mettre à jour le stock si vous gérez les stocks
        // Décommentez cette section si vous avez une gestion de stock
        /*
        foreach ($cart_items as $item) {
            $stmt = $pdo->prepare("
                UPDATE products 
                SET stock = stock - ? 
                WHERE id = ? AND stock >= ?
            ");
            $stmt->execute([$item['quantity'], $item['id'], $item['quantity']]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception("Stock insuffisant pour: {$item['name']}");
            }
        }
        */
        
        // Valider la transaction
        $pdo->commit();
        error_log("Transaction validée avec succès!");
        
        // 7. Envoyer une notification par email (optionnel)
        sendOrderConfirmationEmail($customer_email, $customer_name, $order_number, $total_amount, $order_type);
        
        // 8. Notifier l'administration (optionnel)
        notifyAdminNewOrder($order_id, $order_number, $total_amount);
        
        // 9. Répondre avec succès
        $response = [
            'success' => true,
            'message' => 'Commande créée avec succès ! Votre numéro de commande est ' . $order_number,
            'order_id' => $order_id,
            'order_number' => $order_number,
            'total_amount' => $total_amount,
            'redirect_url' => 'details_commande.php?id=' . $order_id
        ];
        
        error_log("Réponse envoyée: " . json_encode($response));
        echo json_encode($response);
        
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        $pdo->rollBack();
        error_log("Erreur transaction, rollback: " . $e->getMessage());
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Erreur PDO: " . $e->getMessage() . " - Code: " . $e->getCode());
    error_log("Trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage(),
        'debug' => [
            'error_code' => $e->getCode(),
            'error_info' => $pdo->errorInfo()
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Erreur générale: " . $e->getMessage());
    error_log("Trace: " . $e->getTraceAsString());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'trace' => $e->getTraceAsString()
        ]
    ]);
}

/**
 * Envoyer un email de confirmation de commande
 */
function sendOrderConfirmationEmail($email, $name, $order_number, $total_amount, $order_type) {
    // Pour l'instant, on log juste l'email
    $order_type_text = $order_type === 'dine_in' ? 'sur place' : 
                      ($order_type === 'takeaway' ? 'à emporter' : 'en livraison');
    
    $log_message = "Email de confirmation à envoyer pour la commande $order_number\n";
    $log_message .= "À: $name <$email>\n";
    $log_message .= "Montant: " . number_format($total_amount, 2, ',', ' ') . " €\n";
    $log_message .= "Type: $order_type_text\n";
    
    error_log($log_message);
    
    // Implémentation réelle d'envoi d'email
    try {
        // Exemple avec la fonction mail() de PHP (basique)
        $subject = "Confirmation de votre commande #$order_number - Le Gourmet";
        $message = "
        <html>
        <head>
            <title>Confirmation de commande</title>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #f8f9fa; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .footer { background-color: #f8f9fa; padding: 10px; text-align: center; font-size: 12px; color: #6c757d; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Le Gourmet</h1>
                    <h2>Confirmation de commande</h2>
                </div>
                <div class='content'>
                    <p>Bonjour $name,</p>
                    <p>Votre commande <strong>#$order_number</strong> a bien été enregistrée.</p>
                    <p><strong>Type de commande:</strong> $order_type_text</p>
                    <p><strong>Montant total:</strong> " . number_format($total_amount, 2, ',', ' ') . " €</p>
                    <p>Vous pouvez suivre l'état de votre commande depuis votre espace client.</p>
                    <p>Merci pour votre confiance !</p>
                </div>
                <div class='footer'>
                    <p>Ceci est un email automatique, merci de ne pas y répondre.</p>
                    <p>© " . date('Y') . " Le Gourmet - Tous droits réservés</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Le Gourmet <noreply@legourmet.com>" . "\r\n";
        
        // mail($email, $subject, $message, $headers);
        
        return true;
    } catch (Exception $e) {
        error_log("Erreur envoi email: " . $e->getMessage());
        return false;
    }
}

/**
 * Notifier l'administration d'une nouvelle commande
 */
function notifyAdminNewOrder($order_id, $order_number, $total_amount) {
    // Log la notification
    $log_message = "Nouvelle commande #$order_number\n";
    $log_message .= "ID: $order_id\n";
    $log_message .= "Montant: " . number_format($total_amount, 2, ',', ' ') . " €\n";
    $log_message .= "Date: " . date('d/m/Y H:i:s');
    
    error_log($log_message);
    
    // Vous pourriez envoyer une notification :
    // - Email à l'administrateur
    // - Notification push
    // - SMS
    // - Webhook vers un système de gestion
    
    // Exemple d'email à l'administrateur :
    /*
    $admin_email = 'admin@legourmet.com';
    $subject = "Nouvelle commande #$order_number";
    $message = "Une nouvelle commande a été passée.\n\n";
    $message .= "Numéro: $order_number\n";
    $message .= "Montant: " . number_format($total_amount, 2, ',', ' ') . " €\n";
    $message .= "ID: $order_id\n";
    $message .= "Date: " . date('d/m/Y H:i:s');
    
    mail($admin_email, $subject, $message);
    */
    
    return true;
}

/**
 * Valider les données de livraison
 */
function validateDeliveryData($address, $zipcode, $city) {
    if (empty($address) || strlen($address) < 5) {
        throw new Exception('Adresse invalide (minimum 5 caractères)');
    }
    
    if (!preg_match('/^\d{5}$/', $zipcode)) {
        throw new Exception('Code postal invalide (5 chiffres requis)');
    }
    
    if (empty($city) || strlen($city) < 2) {
        throw new Exception('Ville invalide (minimum 2 caractères)');
    }
    
    return true;
}

/**
 * Valider l'heure de récupération pour les commandes à emporter
 */
function validatePickupTime($pickup_time) {
    if (empty($pickup_time)) {
        throw new Exception('Heure de récupération requise');
    }
    
    $min_time = date('H:i', strtotime('+30 minutes'));
    $max_time = date('H:i', strtotime('+3 hours'));
    
    if ($pickup_time < $min_time) {
        throw new Exception('L\'heure de récupération doit être au moins 30 minutes à l\'avance');
    }
    
    if ($pickup_time > '23:00') {
        throw new Exception('Les commandes après 23h ne sont pas acceptées');
    }
    
    return true;
}