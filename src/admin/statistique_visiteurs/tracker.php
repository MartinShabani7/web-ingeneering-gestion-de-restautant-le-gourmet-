<?php
// 1. Démarrer la session si pas déjà active
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Inclure la configuration de la base de données - CHEMIN CORRECT
require_once __DIR__ . '/../../config/database.php';

// 3. Inclure le manager
require_once 'VisiteurManager.php';

try {
    // 4. Utiliser la connexion $pdo qui est déjà créée dans database.php
    $visiteurManager = new VisiteurManager($pdo);
    
    // 5. Enregistrer la visite
    $page_actuelle = $_SERVER['REQUEST_URI'];
    $visiteurManager->enregistrerVisite($page_actuelle);
    
} catch (Exception $e) {
    // Logger l'erreur sans casser le site
    error_log("Erreur dans le tracker de visites: " . $e->getMessage());
}
?>