<?php
// config/Database.php

require_once __DIR__ . '/helpers.php';

/**
 * Configuration de la base de données
 * Gestion sécurisée des connexions MySQL avec support Docker/XAMPP
 */
class Database {
    private $host;
    private $port;
    private $db_name;
    private $username;
    private $password;
    private $charset;
    private $pdo;
    private static $instance = null;
    
    /**
     * Constructeur privé (singleton)
     */
    private function __construct() {
        $this->loadConfig();
        $this->connect();
    }
    
    /**
     * Charge la configuration depuis .env
     */
    private function loadConfig() {
        $this->host = env('DB_HOST', 'localhost');
        $this->port = env('DB_PORT', 3306);
        $this->db_name = env('DB_NAME', 'restaurant_gourmet');
        $this->username = env('DB_USER', 'root');
        $this->password = env('DB_PASSWORD', '');
        $this->charset = env('DB_CHARSET', 'utf8mb4');
        
        // Debug (à désactiver en production)
        if (env('APP_DEBUG', false)) {
            error_log("Database config loaded:");
            error_log("Host: {$this->host}");
            error_log("Port: {$this->port}");
            error_log("DB: {$this->db_name}");
            error_log("User: {$this->username}");
        }
    }
    
    /**
     * Établit la connexion PDO
     */
    private function connect() {
        // Construction du DSN
        $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->db_name};charset={$this->charset}";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset}"
        ];
        
        try {
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            
            // Vérifier si la base existe, sinon la créer
            $this->ensureDatabaseExists();
            
        } catch (PDOException $e) {
            // Message d'erreur clair
            $errorMessage = "Erreur de connexion MySQL: " . $e->getMessage();
            error_log($errorMessage);
            
            if (env('APP_DEBUG', false)) {
                throw new Exception($errorMessage . 
                    "\nConfig: host={$this->host}, port={$this->port}, db={$this->db_name}, user={$this->username}");
            } else {
                throw new Exception("Impossible de se connecter à la base de données. Contactez l'administrateur.");
            }
        }
    }
    
    /**
     * Vérifie et crée la base de données si elle n'existe pas
     */
    private function ensureDatabaseExists() {
        try {
            // Tenter d'utiliser la base
            $this->pdo->exec("USE {$this->db_name}");
        } catch (PDOException $e) {
            // Base inexistante, on essaie de la créer
            if ($e->getCode() === '1049') {
                error_log("Database '{$this->db_name}' does not exist. Attempting to create...");
                
                // Se connecter sans base spécifique
                $dsn = "mysql:host={$this->host};port={$this->port};charset={$this->charset}";
                $tempPdo = new PDO($dsn, $this->username, $this->password);
                
                // Créer la base
                $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `{$this->db_name}` CHARACTER SET {$this->charset}");
                $tempPdo->exec("USE {$this->db_name}");
                
                error_log("Database '{$this->db_name}' created successfully.");
            } else {
                throw $e;
            }
        }
    }
    
    /**
     * Instance unique (singleton)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Récupère la connexion PDO
     */
    public function getConnection() {
        return $this->pdo;
    }
    
    /**
     * Méthodes pratiques
     */
    public function prepare($sql) {
        return $this->pdo->prepare($sql);
    }
    
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    public function commit() {
        return $this->pdo->commit();
    }
    
    public function rollback() {
        return $this->pdo->rollback();
    }
    
    /**
     * Test de connexion
     */
    public function testConnection() {
        try {
            $stmt = $this->pdo->query("SELECT 1 as test");
            return $stmt->fetch()['test'] === 1;
        } catch (PDOException $e) {
            return false;
        }
    }
}

// Instance globale (pour compatibilité avec ton code existant)
try {
    $database = Database::getInstance();
    $pdo = $database->getConnection();
} catch (Exception $e) {
    // Gestion élégante de l'erreur
    if (env('APP_DEBUG', false)) {
        die("Erreur de base de données: " . $e->getMessage());
    } else {
        die("Erreur de base de données. Veuillez réessayer plus tard.");
    }
}
?>