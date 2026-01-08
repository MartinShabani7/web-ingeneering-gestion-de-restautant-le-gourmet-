<?php
// admin/rapports/download.php

require_once '../../config/database.php';
require_once '../../config/Security.php';

session_start();
if (!Security::isAdmin() && !Security::isManager()) {
    header('Location: ../../login.php');
    exit;
}

$filename = $_GET['file'] ?? '';
if (!$filename) {
    die("Fichier non spécifié");
}

// Sécuriser le nom de fichier
$filename = basename($filename);
$filepath = __DIR__ . '/exports/' . $filename;

if (!file_exists($filepath)) {
    die("Fichier non trouvé: " . htmlspecialchars($filename));
}

// Déterminer le type MIME
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

// Headers spécifiques selon le type
if ($extension === 'pdf') {
    // Pour PDF: afficher dans le navigateur si possible
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachement; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: public, must-revalidate, max-age=0');
    header('Pragma: public');
    header('Expires: 0');
} elseif ($extension === 'html') {
    // Pour HTML: téléchargement forcé
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
} elseif ($extension === 'csv') {
    // Pour CSV: téléchargement Excel
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
} else {
    // Type inconnu: téléchargement générique
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
}

// Nettoyer le buffer de sortie
if (ob_get_length()) {
    ob_end_clean();
}

// Lire le fichier
readfile($filepath);
exit;
?>