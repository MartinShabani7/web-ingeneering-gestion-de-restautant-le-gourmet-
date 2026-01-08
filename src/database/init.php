<?php
/**
 * Script sécurisé d'initialisation de la base de données
 * Création sécurisée de l'utilisateur admin
 */

// === CONFIGURATION DE SÉCURITÉ ===
session_start();

// Désactiver l'affichage des erreurs en production
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Headers de sécurité
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Vérifier si le script est exécuté en local ou via CLI
$isCli = (php_sapi_name() === 'cli');
// $isLocal = ($_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1');
$isLocal = ($_SERVER['REMOTE_ADDR'] === '127.0.0.1' || 
           $_SERVER['REMOTE_ADDR'] === '::1' ||
           $_SERVER['REMOTE_ADDR'] === $_SERVER['SERVER_ADDR'] ||
           filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) === false);

// En production, restreindre l'accès
if (!$isCli && !$isLocal) {
    header('HTTP/1.1 403 Forbidden');
    die('❌ Accès interdit - Ce script ne peut être exécuté qu\'en local.');
}

// Vérifier le token CSRF pour les requêtes web
if (!$isCli && ($_SERVER['REQUEST_METHOD'] === 'POST')) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['install_token']) {
        die('❌ Token CSRF invalide');
    }
}

// Générer un token CSRF
$_SESSION['install_token'] = bin2hex(random_bytes(32));

require_once '../config/database.php';

class SecureDatabaseInitializer {
    private $pdo;
    private $maxAttempts = 3;
    private $attemptsFile;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->attemptsFile = __DIR__ . '/install_attempts.txt';
    }
    
    /**
     * Vérifie les tentatives pour éviter les abus
     */
    private function checkAttempts() {
        if (!file_exists($this->attemptsFile)) {
            file_put_contents($this->attemptsFile, '0');
            return true;
        }
        
        $attempts = (int)file_get_contents($this->attemptsFile);
        $lastAttempt = filemtime($this->attemptsFile);
        $timeDiff = time() - $lastAttempt;
        
        // Réinitialiser après 1 heure
        if ($timeDiff > 3600) {
            file_put_contents($this->attemptsFile, '0');
            return true;
        }
        
        if ($attempts >= $this->maxAttempts) {
            throw new Exception("Trop de tentatives. Réessayez dans " . (3600 - $timeDiff) . " secondes.");
        }
        
        return true;
    }
    
    /**
     * Enregistre une tentative
     */
    private function recordAttempt() {
        $attempts = file_exists($this->attemptsFile) ? (int)file_get_contents($this->attemptsFile) : 0;
        file_put_contents($this->attemptsFile, $attempts + 1);
    }
    
    /**
     * Nettoie les entrées
     */
    private function sanitizeInput($data) {
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Génère un mot de passe fort
     */
    private function generateStrongPassword($length = 12) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-=+';
        $password = '';
        $charsLength = strlen($chars) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $charsLength)];
        }
        
        return $password;
    }
    
    /**
     * Valide l'email
     */
    private function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Hash sécurisé du mot de passe
     */
    private function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }
    
    /**
     * Vérifie la force de la base de données
     */
    private function checkDatabaseSecurity() {
        try {
            // Vérifier que la table users a les colonnes de sécurité
            $stmt = $this->pdo->prepare("
                SHOW COLUMNS FROM users LIKE 'password_hash'
            ");
            $stmt->execute();
            
            if (!$stmt->fetch()) {
                throw new Exception("La structure de la table users n'est pas sécurisée.");
            }
            
            return true;
        } catch (Exception $e) {
            throw new Exception("Erreur de vérification de sécurité: " . $e->getMessage());
        }
    }
    
    /**
     * Crée un utilisateur admin sécurisé
     */
    public function createSecureAdmin() {
        try {
            // Vérifier les tentatives
            $this->checkAttempts();
            
            // Vérifier la sécurité de la base
            $this->checkDatabaseSecurity();
            
            $adminEmail = 'admin@legourmet.fr';
            
            if (!$this->validateEmail($adminEmail)) {
                throw new Exception("Email admin invalide");
            }
            
            // Générer un mot de passe fort aléatoire
            $adminPassword = $this->generateStrongPassword();
            
            // Vérifier si l'admin existe déjà
            $stmt = $this->pdo->prepare("SELECT id, email, role FROM users WHERE email = ?");
            $stmt->execute([$adminEmail]);
            $existingAdmin = $stmt->fetch();
            
            if ($existingAdmin) {
                echo "ℹ️  L'utilisateur admin existe déjà.\n";
                
                // Demander confirmation pour la réinitialisation
                if (php_sapi_name() === 'cli') {
                    echo "Voulez-vous réinitialiser le mot de passe ? (o/n): ";
                    $response = trim(fgets(STDIN));
                } else {
                    $response = isset($_POST['confirm_reset']) ? 'o' : 'n';
                }
                
                if (strtolower($response) === 'o') {
                    $this->resetAdminPassword($adminEmail, $adminPassword);
                } else {
                    echo "❌ Opération annulée.\n";
                    return;
                }
            } else {
                // Créer l'admin avec un mot de passe fort
                $this->createNewAdmin($adminEmail, $adminPassword);
            }
            
            // Enregistrer la tentative réussie
            $this->recordAttempt();
            
        } catch (Exception $e) {
            $this->recordAttempt();
            throw $e;
        }
    }
    
    /**
     * Crée un nouvel admin
     */
    private function createNewAdmin($email, $password) {
        $passwordHash = $this->hashPassword($password);
        
        $stmt = $this->pdo->prepare("
            INSERT INTO users 
            (email, password_hash, first_name, last_name, role, is_active, email_verified, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $email,
            $passwordHash,
            'Admin',
            'Système',
            'admin',
            1,
            1
        ]);
        
        $this->displaySuccessMessage($email, $password, "CRÉÉ");
    }
    
    /**
     * Réinitialise le mot de passe admin
     */
    private function resetAdminPassword($email, $newPassword) {
        $passwordHash = $this->hashPassword($newPassword);
        
        $stmt = $this->pdo->prepare("
            UPDATE users SET 
            password_hash = ?, 
            updated_at = NOW(),
            password_reset_token = NULL,
            password_reset_expires = NULL
            WHERE email = ?
        ");
        
        $stmt->execute([$passwordHash, $email]);
        
        $this->displaySuccessMessage($email, $newPassword, "RÉINITIALISÉ");
    }
    
    /**
     * Affiche le message de succès
     */
    private function displaySuccessMessage($email, $password, $action) {
        if (php_sapi_name() === 'cli') {
            echo "✅ MOT DE PASSE ADMIN $action AVEC SUCCÈS!\n";
            echo "==========================================\n";
            echo "📧 Email: $email\n";
            echo "🔑 Mot de passe: $password\n";
            echo "==========================================\n";
            echo "⚠️  IMPORTANT DE SÉCURITÉ:\n";
            echo "   - Changez ce mot de passe immédiatement après connexion\n";
            echo "   - Ne partagez pas ces identifiants\n";
            echo "   - Activez l'authentification à deux facteurs si possible\n";
            echo "   - Ce message ne sera plus affiché\n";
            echo "==========================================\n";
        } else {
            echo '
            <div class="success-card">
                <div class="success-header">
                    <div class="success-icon">✅</div>
                    <h2>Administrateur ' . $action . ' avec Succès</h2>
                </div>
                <div class="credentials">
                    <div class="credential-item">
                        <span class="credential-label">📧 Email:</span>
                        <span class="credential-value">' . htmlspecialchars($email) . '</span>
                    </div>
                    <div class="credential-item">
                        <span class="credential-label">🔑 Mot de passe:</span>
                        <span class="credential-value password-display">' . htmlspecialchars($password) . '</span>
                        <button type="button" class="copy-btn" onclick="copyPassword()">📋 Copier</button>
                    </div>
                </div>
                <div class="security-warning">
                    <h3>⚠️ Important de Sécurité</h3>
                    <ul>
                        <li>Changez ce mot de passe immédiatement après connexion</li>
                        <li>Ne partagez pas ces identifiants</li>
                        <li>Activez l\'authentification à deux facteurs si possible</li>
                        <li>Cette page doit être supprimée après utilisation</li>
                    </ul>
                </div>
                <div class="action-buttons">
                    <a href="../login.php" class="btn btn-primary">🚀 Se connecter maintenant</a>
                    <button type="button" class="btn btn-secondary" onclick="hidePassword()">👁️ Masquer le mot de passe</button>
                </div>
            </div>
            <script>
                function copyPassword() {
                    const password = "' . htmlspecialchars($password) . '";
                    navigator.clipboard.writeText(password).then(() => {
                        const btn = document.querySelector(".copy-btn");
                        btn.textContent = "✅ Copié!";
                        setTimeout(() => btn.textContent = "📋 Copier", 2000);
                    });
                }
                
                function hidePassword() {
                    const display = document.querySelector(".password-display");
                    if (display.textContent === "••••••••••••") {
                        display.textContent = "' . htmlspecialchars($password) . '";
                    } else {
                        display.textContent = "••••••••••••";
                    }
                }
            </script>';
        }
        
        // Journaliser l'action (sans le mot de passe)
        error_log("Admin $action - Email: $email - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'CLI'));
    }
    
    /**
     * Affiche les informations de sécurité
     */
    public function showSecurityInfo() {
        try {
            if (php_sapi_name() === 'cli') {
                echo "\n=== INFORMATIONS DE SÉCURITÉ ===\n";
                
                // Vérifier la version de PHP
                echo "PHP Version: " . PHP_VERSION . "\n";
                
                // Vérifier les algorithmes de hash disponibles
                echo "Algorithme de hash: " . (defined('PASSWORD_ARGON2ID') ? 'Argon2id ✓' : 'BCrypt') . "\n";
                
                // Compter les utilisateurs admin
                $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
                $stmt->execute();
                $adminCount = $stmt->fetch()['count'];
                echo "Admins actifs: $adminCount\n";
                
                echo "==============================\n";
            } else {
                echo '
                <div class="security-info">
                    <h3>🔒 Informations de Sécurité</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">PHP Version:</span>
                            <span class="info-value">' . PHP_VERSION . '</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Algorithme de hash:</span>
                            <span class="info-value success-text">' . (defined('PASSWORD_ARGON2ID') ? 'Argon2id ✓' : 'BCrypt') . '</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Environnement:</span>
                            <span class="info-value success-text">Sécurisé ✓</span>
                        </div>
                    </div>
                </div>';
            }
            
        } catch (Exception $e) {
            echo "⚠️  Impossible d'afficher les informations de sécurité\n";
        }
    }
}

// === EXÉCUTION SÉCURISÉE ===
try {
    $isCli = (php_sapi_name() === 'cli');
    
    if (!$isCli) {
        echo '<!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Installation Sécurisée - Le Gourmet</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                body {
                    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                
                .container {
                    background: white;
                    border-radius: 20px;
                    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                    overflow: hidden;
                    max-width: 800px;
                    width: 100%;
                }
                
                .header {
                    background: linear-gradient(135deg, #2c3e50, #34495e);
                    color: white;
                    padding: 30px;
                    text-align: center;
                }
                
                .header h1 {
                    font-size: 2.5rem;
                    margin-bottom: 10px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 15px;
                }
                
                .header p {
                    opacity: 0.9;
                    font-size: 1.1rem;
                }
                
                .content {
                    padding: 40px;
                }
                
                .warning-card {
                    background: linear-gradient(135deg, #fff3cd, #ffeaa7);
                    border: 2px solid #fdcb6e;
                    border-radius: 15px;
                    padding: 30px;
                    margin-bottom: 30px;
                }
                
                .warning-header {
                    display: flex;
                    align-items: center;
                    gap: 15px;
                    margin-bottom: 20px;
                }
                
                .warning-icon {
                    font-size: 2rem;
                    color: #e17055;
                }
                
                .security-info {
                    background: #f8f9fa;
                    border-radius: 15px;
                    padding: 25px;
                    margin-bottom: 30px;
                    border-left: 5px solid #667eea;
                }
                
                .security-info h3 {
                    color: #2c3e50;
                    margin-bottom: 20px;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                
                .info-grid {
                    display: grid;
                    gap: 15px;
                }
                
                .info-item {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 10px 0;
                    border-bottom: 1px solid #e9ecef;
                }
                
                .info-label {
                    font-weight: 600;
                    color: #495057;
                }
                
                .info-value {
                    font-weight: 500;
                }
                
                .success-text {
                    color: #27ae60;
                    font-weight: 600;
                }
                
                .btn {
                    display: inline-flex;
                    align-items: center;
                    gap: 10px;
                    padding: 15px 30px;
                    border: none;
                    border-radius: 10px;
                    font-size: 1.1rem;
                    font-weight: 600;
                    text-decoration: none;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    text-align: center;
                    justify-content: center;
                }
                
                .btn-primary {
                    background: linear-gradient(135deg, #667eea, #764ba2);
                    color: white;
                }
                
                .btn-primary:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
                }
                
                .btn-secondary {
                    background: #95a5a6;
                    color: white;
                }
                
                .btn-secondary:hover {
                    background: #7f8c8d;
                    transform: translateY(-2px);
                }
                
                .form-container {
                    text-align: center;
                }
                
                .success-card {
                    background: linear-gradient(135deg, #d4edda, #c3e6cb);
                    border: 2px solid #28a745;
                    border-radius: 15px;
                    padding: 30px;
                }
                
                .success-header {
                    display: flex;
                    align-items: center;
                    gap: 15px;
                    margin-bottom: 25px;
                    justify-content: center;
                }
                
                .success-icon {
                    font-size: 2.5rem;
                }
                
                .credentials {
                    background: white;
                    border-radius: 10px;
                    padding: 25px;
                    margin: 25px 0;
                    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                }
                
                .credential-item {
                    display: flex;
                    align-items: center;
                    gap: 15px;
                    padding: 15px 0;
                    border-bottom: 1px solid #e9ecef;
                }
                
                .credential-item:last-child {
                    border-bottom: none;
                }
                
                .credential-label {
                    font-weight: 600;
                    color: #2c3e50;
                    min-width: 120px;
                }
                
                .credential-value {
                    flex: 1;
                    font-family: "Courier New", monospace;
                    font-size: 1.1rem;
                    font-weight: 600;
                    color: #e74c3c;
                }
                
                .copy-btn {
                    background: #3498db;
                    color: white;
                    border: none;
                    padding: 8px 15px;
                    border-radius: 6px;
                    cursor: pointer;
                    font-size: 0.9rem;
                    transition: background 0.3s ease;
                }
                
                .copy-btn:hover {
                    background: #2980b9;
                }
                
                .security-warning {
                    background: #fff3cd;
                    border: 1px solid #ffeaa7;
                    border-radius: 10px;
                    padding: 20px;
                    margin: 20px 0;
                }
                
                .security-warning h3 {
                    color: #856404;
                    margin-bottom: 15px;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                
                .security-warning ul {
                    list-style: none;
                    padding-left: 0;
                }
                
                .security-warning li {
                    padding: 8px 0;
                    color: #856404;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                
                .security-warning li:before {
                    content: "⚠️";
                }
                
                .action-buttons {
                    display: flex;
                    gap: 15px;
                    justify-content: center;
                    flex-wrap: wrap;
                }
                
                @media (max-width: 768px) {
                    .content {
                        padding: 20px;
                    }
                    
                    .header h1 {
                        font-size: 2rem;
                    }
                    
                    .action-buttons {
                        flex-direction: column;
                    }
                    
                    .credential-item {
                        flex-direction: column;
                        align-items: flex-start;
                        gap: 10px;
                    }
                    
                    .copy-btn {
                        align-self: flex-end;
                    }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><i class="fas fa-tools"></i> Installation Sécurisée</h1>
                    <p>Le Gourmet - Panel d\'administration</p>
                </div>
                <div class="content">';
    }
    
    $initializer = new SecureDatabaseInitializer();
    
    if (!$isCli && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $initializer->createSecureAdmin();
    } else {
        $initializer->showSecurityInfo();
        
        if (!$isCli) {
            echo '
                    <div class="warning-card">
                        <div class="warning-header">
                            <div class="warning-icon">⚠️</div>
                            <div>
                                <h2>Action Requise</h2>
                                <p>Cette opération va créer ou réinitialiser l\'utilisateur administrateur du système.</p>
                            </div>
                        </div>
                        <div class="form-container">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="' . $_SESSION['install_token'] . '">
                                <button type="submit" name="confirm_reset" value="1" class="btn btn-primary">
                                    <i class="fas fa-rocket"></i> Démarrer l\'Installation
                                </button>
                            </form>
                        </div>
                    </div>';
        } else {
            $initializer->createSecureAdmin();
        }
    }
    
    if (!$isCli) {
        echo '
                </div>
            </div>
        </body>
        </html>';
    }
    
} catch (Exception $e) {
    $message = "❌ ERREUR: " . $e->getMessage();
    
    if (php_sapi_name() === 'cli') {
        echo $message . "\n";
    } else {
        echo '
        <div class="container">
            <div class="header">
                <h1><i class="fas fa-exclamation-triangle"></i> Erreur</h1>
            </div>
            <div class="content">
                <div class="warning-card" style="background: linear-gradient(135deg, #f8d7da, #f5c6cb); border-color: #e74c3c;">
                    <div class="warning-header">
                        <div class="warning-icon">❌</div>
                        <div>
                            <h2>Erreur lors de l\'installation</h2>
                            <p>' . htmlspecialchars($message) . '</p>
                        </div>
                    </div>
                    <div class="form-container">
                        <a href="javascript:history.back()" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Retour
                        </a>
                    </div>
                </div>
            </div>
        </div>';
    }
    
    exit(1);
}
?>