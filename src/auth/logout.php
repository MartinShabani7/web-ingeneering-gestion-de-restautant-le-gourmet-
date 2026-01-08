<?php
session_start();
require_once '../config/database.php';
require_once '../config/security.php';

// Vérifier si l'utilisateur est connecté
if (!Security::isLoggedIn()) {
    Security::redirect('../index.php');
}

try {
    // Log de la déconnexion
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, ip_address, user_agent, created_at) 
        VALUES (?, 'user_logout', ?, ?, NOW())
    ");
    $stmt->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
} catch (Exception $e) {
    error_log("Erreur lors du log de déconnexion: " . $e->getMessage());
}

// Destruction de la session
session_destroy();

// Suppression du cookie "Se souvenir de moi" si il existe
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
}

// Redirection vers la page d'accueil
Security::redirect('../index.php');
?>

