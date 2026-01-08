<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';
// require_once __DIR__ . '/../../include/AuthService.php';

header('Content-Type: application/json; charset=utf-8');

// Vérification de sécurité
if (!Security::isLoggedIn() || !Security::isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

// if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
//     http_response_code(400);
//     echo json_encode(['success' => false, 'message' => 'Requête invalide']);
//     exit;
// }

try {
    // ===========================================
    // STATISTIQUES PRINCIPALES
    // ===========================================
    
    // Nombre total d'utilisateurs actifs
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE is_active = 1");
    $stmt->execute();
    $users = $stmt->fetchColumn();

    // Nombre total de commandes
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders");
    $stmt->execute();
    $orders = $stmt->fetchColumn();

    // Chiffre d'affaires total
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'paid'");
    $stmt->execute();
    $revenue = $stmt->fetchColumn();

    // Nombre de réservations actives
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE status IN ('pending', 'confirmed')");
    $stmt->execute();
    $reservations = $stmt->fetchColumn();

    // ===========================================
    // STATISTIQUES DU MOIS EN COURS
    // ===========================================
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as orders_this_month,
            COALESCE(SUM(total_amount), 0) as revenue_this_month
        FROM orders 
        WHERE MONTH(created_at) = MONTH(CURDATE()) 
        AND YEAR(created_at) = YEAR(CURDATE())
        AND payment_status = 'paid'
    ");
    $stmt->execute();
    $monthStats = $stmt->fetch();

    // ===========================================
    // COMMANDES RÉCENTES (10 dernières)
    // ===========================================
    
    $stmt = $pdo->prepare("
        SELECT 
            o.*, 
            u.first_name, 
            u.last_name, 
            u.email,
            COUNT(oi.id) as item_count
        FROM orders o
        LEFT JOIN users u ON o.customer_id = u.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recentOrders = $stmt->fetchAll();

    // ===========================================
    // PRODUITS LES PLUS VENDUS
    // ===========================================
    
    $stmt = $pdo->prepare("
        SELECT 
            p.name, 
            SUM(oi.quantity) as total_sold
        FROM products p
        JOIN order_items oi ON p.id = oi.product_id
        JOIN orders o ON oi.order_id = o.id
        WHERE o.payment_status = 'paid'
        GROUP BY p.id, p.name
        ORDER BY total_sold DESC
        LIMIT 5
    ");
    $stmt->execute();
    $topProducts = $stmt->fetchAll();

    // ===========================================
    // RÉSERVATIONS RÉCENTES
    // ===========================================
    
    $stmt = $pdo->prepare("
        SELECT 
            r.*, 
            u.first_name, 
            u.last_name, 
            u.email
        FROM reservations r
        LEFT JOIN users u ON r.customer_id = u.id
        ORDER BY r.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recentReservations = $stmt->fetchAll();

    // ===========================================
    // ACTIVITÉ RÉCENTE
    // ===========================================
    
    $stmt = $pdo->prepare("
        SELECT 
            al.*, 
            u.first_name, 
            u.last_name
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recentActivity = $stmt->fetchAll();

    // ===========================================
    // DONNÉES POUR LES GRAPHIQUES
    // ===========================================
    
    // Ventes des 6 derniers mois
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%b') as month,
            COALESCE(SUM(total_amount), 0) as revenue
        FROM orders 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        AND payment_status = 'paid'
        GROUP BY YEAR(created_at), MONTH(created_at), DATE_FORMAT(created_at, '%b')
        ORDER BY YEAR(created_at), MONTH(created_at)
        LIMIT 6
    ");
    $stmt->execute();
    $monthlyRevenue = $stmt->fetchAll();

    // Ventes par catégorie
    $stmt = $pdo->prepare("
        SELECT 
            c.name as category,
            COALESCE(SUM(oi.quantity * oi.unit_price), 0) as revenue
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id AND o.payment_status = 'paid'
        WHERE c.is_active = 1
        GROUP BY c.id, c.name
        ORDER BY revenue DESC
        LIMIT 6
    ");
    $stmt->execute();
    $revenueByCategory = $stmt->fetchAll();

    // ===========================================
    // PRÉPARATION DES DONNÉES POUR LE FRONTEND
    // ===========================================
    
    $response = [
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        
        // Statistiques principales
        'stats' => [
            'users' => (int)$users,
            'orders' => (int)$orders,
            'revenue' => (float)$revenue,
            'reservations' => (int)$reservations,
            'orders_this_month' => (int)($monthStats['orders_this_month'] ?? 0),
            'revenue_this_month' => (float)($monthStats['revenue_this_month'] ?? 0)
        ],
        
        // Données pour les tableaux
        'recentOrders' => $recentOrders,
        'topProducts' => $topProducts,
        'recentReservations' => $recentReservations,
        'recentActivity' => $recentActivity,
        
        // Données pour les graphiques
        'chartData' => [
            'monthlyRevenue' => [
                'labels' => array_column($monthlyRevenue, 'month'),
                'data' => array_column($monthlyRevenue, 'revenue')
            ],
            'revenueByCategory' => [
                'labels' => array_column($revenueByCategory, 'category'),
                'data' => array_column($revenueByCategory, 'revenue'),
                'backgroundColors' => [
                    '#d4af37', '#2c3e50', '#e74c3c', '#3498db', '#2ecc71', '#9b59b6'
                ]
            ]
        ]
    ];

    echo json_encode($response, JSON_NUMERIC_CHECK);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur serveur: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>