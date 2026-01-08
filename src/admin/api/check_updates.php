<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';

header('Content-Type: application/json; charset=utf-8');

// Vérification de sécurité
if (!Security::isLoggedIn() || !Security::isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

$lastUpdate = $_GET['last_update'] ?? date('Y-m-d H:i:s', strtotime('-1 hour'));

try {
    // Vérifier les nouvelles commandes
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM orders 
        WHERE (created_at > ? OR updated_at > ?)
        AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
    ");
    $stmt->execute([$lastUpdate, $lastUpdate]);
    $newOrders = $stmt->fetchColumn();

    // Vérifier les nouvelles réservations
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM reservations 
        WHERE (created_at > ? OR updated_at > ?)
        AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
    ");
    $stmt->execute([$lastUpdate, $lastUpdate]);
    $newReservations = $stmt->fetchColumn();

    // Vérifier les nouveaux utilisateurs
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM users 
        WHERE created_at > ? 
        AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
    ");
    $stmt->execute([$lastUpdate]);
    $newUsers = $stmt->fetchColumn();

    $hasUpdates = ($newOrders + $newReservations + $newUsers) > 0;

    echo json_encode([
        'success' => true,
        'hasUpdates' => $hasUpdates,
        'currentTime' => date('Y-m-d H:i:s'),
        'details' => [
            'newOrders' => (int)$newOrders,
            'newReservations' => (int)$newReservations,
            'newUsers' => (int)$newUsers
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'hasUpdates' => false,
        'currentTime' => date('Y-m-d H:i:s'),
        'message' => 'Erreur de vérification: ' . $e->getMessage()
    ]);
}
?>