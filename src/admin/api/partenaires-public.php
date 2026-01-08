<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Inclure la configuration de la base de données
require_once '../../config/database.php';

try {

    global $pdo;
    
    // Vérifier si la connexion PDO est disponible
    if (!$pdo) {
        throw new Exception('Connexion à la base de données non disponible');
    }
    
    // Récupérer uniquement les partenaires actifs pour l'affichage public
    $stmt = $pdo->prepare("
        SELECT id, nom, photo as logo_url, est_en_avant
        FROM partenaires 
        WHERE est_actif = 1 
        ORDER BY est_en_avant DESC, nom ASC
    ");
    $stmt->execute();
    
    $partenaires = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($partenaires as &$partenaire) {
        if (!empty($partenaire['logo_url'])) {
            // Chemin depuis la racine du site
            $partenaire['logo_url'] = 'uploads/partenaires/' . $partenaire['logo_url'];
        } else {
            // Image par défaut si pas de logo
            $partenaire['logo_url'] = 'https://via.placeholder.com/200x120?text=' . urlencode($partenaire['nom']);
        }
    }
    echo json_encode([
        'success' => true, 
        'data' => $partenaires,
        'count' => count($partenaires)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur lors du chargement des partenaires: ' . $e->getMessage(),
        'data' => []
    ]);
}
?>