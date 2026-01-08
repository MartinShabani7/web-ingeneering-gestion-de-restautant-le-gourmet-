<?php
// DÉSACTIVER TOUTES LES ERREURS
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(0);

// Buffer pour capturer les sorties accidentelles
if (ob_get_length()) ob_end_clean();
ob_start();

// Headers JSON
header('Content-Type: application/json; charset=utf-8');

// Inclusions SIMPLES
require_once '../../config/database.php';
require_once '../../config/security.php';
require_once '../../config/mailer.php';

// Vérifier accès IMMÉDIATEMENT
if (!Security::isLoggedIn() || !Security::isAdmin()) {
    $output = ob_get_clean();
    echo json_encode(['success' => false, 'message' => 'Accès refusé']);
    exit;
}

// Ensure settings table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS settings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  setting_key VARCHAR(100) UNIQUE NOT NULL,
  setting_value TEXT NULL,
  description TEXT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

function get_settings($pdo) {
    $stmt = $pdo->query('SELECT setting_key, setting_value FROM settings');
    $rows = $stmt->fetchAll();
    $out = [];
    foreach ($rows as $r) { $out[$r['setting_key']] = $r['setting_value']; }
    return $out;
}

function set_settings($pdo, $data) {
    $stmt = $pdo->prepare('INSERT INTO settings(setting_key, setting_value) VALUES(?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    foreach ($data as $k => $v) { $stmt->execute([$k, $v]); }
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get':
            $data = get_settings($pdo);
            $output = ob_get_clean();
            echo json_encode(['success' => true, 'data' => $data]);
            exit;
            
        case 'save':
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) { 
                throw new Exception('Token CSRF invalide'); 
            }
            $keys = [
                'business_name','business_email','business_phone','business_address','business_hours',
                'smtp_provider','smtp_host','smtp_port','smtp_secure','smtp_user','smtp_pass',
                'smtp_from_email','smtp_from_name','smtp_api_key','smtp_api_domain','smtp_enabled'
            ];
            $data = [];
            foreach ($keys as $k) { $data[$k] = $_POST[$k] ?? ''; }
            set_settings($pdo, $data);
            $output = ob_get_clean();
            echo json_encode(['success' => true, 'message' => 'Paramètres sauvegardés']);
            exit;
            
        case 'test_email':
            if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) { 
                throw new Exception('Token CSRF invalide'); 
            }
            $to = $_POST['to'] ?? '';
            if (!Security::validateEmail($to)) { 
                throw new Exception('Email de test invalide'); 
            }
            // Temporarily override Mailer settings via globals in Mailer
            $settings = get_settings($pdo);
            
            // CAPTURER la sortie du mailer pour éviter qu'il casse le JSON
            ob_start();
            require_once '../../include/mailer_runtime.php';
            $ok = send_mail_runtime($pdo, $to, 'Test Email - Le Gourmet', '<p>Ceci est un email de test.</p>');
            $mailerOutput = ob_get_clean();
            
            // Si le mailer a généré du HTML, le logger
            if (!empty(trim($mailerOutput))) {
                error_log("Mailer output: " . substr($mailerOutput, 0, 200));
            }
            
            $output = ob_get_clean();
            echo json_encode([
                'success' => $ok, 
                'message' => $ok ? 'Email de test envoyé (ou loggé en DEV)' : "Échec d'envoi"
            ]);
            exit;
            
        default:
            $output = ob_get_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Action inconnue']);
            exit;
    }
} catch (Exception $e) {
    $output = ob_get_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
?>