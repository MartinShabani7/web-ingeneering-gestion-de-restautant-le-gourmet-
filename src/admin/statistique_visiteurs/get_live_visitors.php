<?php
session_start();
require_once '../../config/database.php';
require_once 'VisiteurManager.php';

header('Content-Type: application/json');

// Vérifier l'accès admin
if (!isset($_SESSION['admin'])) {
    echo json_encode(['error' => 'Accès non autorisé']);
    exit;
}

$db = new PDO('mysql:host=localhost;dbname=restaurant_gourmet', 'root', '');
$visiteurManager = new VisiteurManager($db);

$count = $visiteurManager->getVisiteursEnTempsReel();

echo json_encode(['count' => $count]);
?>