<?php
/**
 * Configuration de sécurité
 * Protection contre XSS, CSRF, SQL Injection
 */

class Security {
    
    /**
     * Génère un token CSRF
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Vérifie le token CSRF
     */
    public static function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Nettoie les données d'entrée (protection XSS)
     */
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Valide l'email
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Valide le mot de passe (minimum 8 caractères, 1 majuscule, 1 minuscule, 1 chiffre)
     */
    public static function validatePassword($password) {
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/', $password);
    }
    
    /**
     * Hash le mot de passe
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID);
    }
    
    /**
     * Vérifie le mot de passe
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Génère un token de réinitialisation
     */
    public static function generateResetToken() {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Génère un token "Se souvenir de moi" et le stocke en base
     */
    public static function createRememberToken($userId) {
        global $pdo;
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 jours
        
        $stmt = $pdo->prepare("
            INSERT INTO remember_tokens (user_id, token_hash, expires_at) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$userId, $tokenHash, $expires]);
        
        // Le token brut est retourné pour être mis dans le cookie
        return $token;
    }
    
    /**
     * Vérifie le token "Se souvenir de moi"
     */
    public static function verifyRememberToken($token) {
        global $pdo;
        $tokenHash = hash('sha256', $token);
        
        $stmt = $pdo->prepare("
            SELECT u.*, t.id as token_id
            FROM remember_tokens t
            JOIN users u ON t.user_id = u.id
            WHERE t.token_hash = ? AND t.expires_at > NOW()
        ");
        $stmt->execute([$tokenHash]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Supprimer l'ancien token et en créer un nouveau pour rotation
            $pdo->prepare("DELETE FROM remember_tokens WHERE id = ?")->execute([$user['token_id']]);
            
            // Créer un nouveau token pour la prochaine connexion
            $newToken = self::createRememberToken($user['id']);
            
            // Retourner l'utilisateur et le nouveau token
            return ['user' => $user, 'token' => $newToken];
        }
        
        return false;
    }
    
    /**
     * Supprime le token "Se souvenir de moi"
     */
    public static function deleteRememberToken($token) {
        global $pdo;
        $tokenHash = hash('sha256', $token);
        $pdo->prepare("DELETE FROM remember_tokens WHERE token_hash = ?")->execute([$tokenHash]);
    }
    
    /**
     * Tente de se connecter via le cookie "Se souvenir de moi"
     */
    public static function attemptLoginFromCookie() {
        if (isset($_COOKIE['remember_token'])) {
            $result = self::verifyRememberToken($_COOKIE['remember_token']);
            
            if ($result) {
                $user = $result['user'];
                $newToken = $result['token'];
                
                // Mettre à jour le cookie
                setcookie('remember_token', $newToken, time() + (30 * 24 * 60 * 60), '/', '', false, true);
                
                // Créer la session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_avatar'] = $user['avatar'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['login_time'] = time();
                
                return true;
            }
            
            // Si le token est invalide ou expiré, supprimer le cookie
            setcookie('remember_token', '', time() - 3600, '/', '', false, true);
        }
        return false;
    }

    // // La focntion pour les infos de l'utilisateur 
    // public static function getUserInfo() {
    //     if (self::isLoggedIn()) {
    //         return [
    //             'id' => $_SESSION['user_id'] ?? null,
    //             'avatar' => $_SESSION['user_avatar'] ?? 'default_avatar.jpg',
    //             'email' => $_SESSION['user_email'] ?? '',
    //             'first_name' => explode(' ', $_SESSION['user_name'] ?? '')[0] ?? '',
    //             'last_name' => explode(' ', $_SESSION['user_name'] ?? '')[1] ?? '',
    //             'full_name' => $_SESSION['user_name'] ?? '',
    //             'role' => $_SESSION['user_role'] ?? ''
    //         ];
    //     }
    //     return null;
    // }
    
    /**
     * Vérifie si l'utilisateur est connecté
     */
    public static function isLoggedIn() {
        if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
            return true;
        }
        
        // Tenter la connexion via le cookie si la session n'existe pas
        return self::attemptLoginFromCookie();
    }
    
    /**
     * Vérifie si l'utilisateur est admin
     */
    public static function isAdmin() {
        return self::isLoggedIn() && $_SESSION['user_role'] === 'admin';
    }
    
    /**
     * Redirection sécurisée
     */
    public static function redirect($url) {
        header("Location: $url");
        exit();
    }
    
    /**
     * Protection contre les attaques par force brute
     * Cette fonction vérifie si une adresse IP et l'adresse mail du compte ont dépassé 
     * le nombre autorisé de tentatives de connexion dans une fenêtre temporelle donnée. 
     * Elle nettoie d'abord les anciennes tentatives, puis compte celles restantes pour l'IP courante.
     */

    public static function checkBruteForce($email, $maxAttemptsPerEmail = 3, $maxAttemptsPerIp = 3, $timeWindow = 900) {
        global $pdo;
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        
        // 1. Supprimer les anciennes tentatives
        $timeLimit = date('Y-m-d H:i:s', time() - $timeWindow);
        $pdo->prepare("DELETE FROM brute_force_attempts WHERE attempt_time < ?")->execute([$timeLimit]);
        
        // 2. Compter les tentatives pour cette IP
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM brute_force_attempts WHERE ip_address = ?");
        $stmt->execute([$ipAddress]);
        $attemptsByIp = $stmt->fetchColumn();
        
        // 3. Compter les tentatives pour cet email
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM brute_force_attempts WHERE email = ?");
        $stmt->execute([$email]);
        $attemptsByEmail = $stmt->fetchColumn();
        
        // Bloquer si dépassement sur l'IP OU sur l'email
        return ($attemptsByIp < $maxAttemptsPerIp) && ($attemptsByEmail < $maxAttemptsPerEmail);
    }
    
    /**
     * Enregistrer une tentative de connexion échouée
     */
    public static function recordFailedAttempt($email) {
        global $pdo;
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        
        $stmt = $pdo->prepare("INSERT INTO brute_force_attempts (ip_address, email, attempt_time) VALUES (?, ?, NOW())");
        $stmt->execute([$ipAddress, $email]);
    }
    
    /**
     * Nettoyer les tentatives de force brute (inutile avec l'approche par IP, mais on garde la fonction pour la compatibilité)
     */
    public static function clearBruteForceAttempts($email) {
        // Avec l'approche par IP, on ne nettoie pas par email, mais on peut nettoyer l'IP si on veut
        // Pour l'instant, on ne fait rien, car la suppression est gérée par le temps dans checkBruteForce
    }
    
    /**
     * Valide les données de fichier uploadé
     */
    public static function validateFileUpload($file, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'], $maxSize = 2097152) {
        $errors = [];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Erreur lors de l'upload du fichier";
            return $errors;
        }
        
        // 1. Vérification de l'extension
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $allowedTypes)) {
            $errors[] = "Type de fichier non autorisé (extension)";
        }
        
        // 2. Vérification du type MIME réel (plus sécurisé)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
        ];
        
        if (!in_array($mimeType, $allowedMimeTypes)) {
            $errors[] = "Type de fichier non autorisé (MIME réel)";
        }
        
        // 3. Vérification de la taille
        if ($file['size'] > $maxSize) {
            $errors[] = "Fichier trop volumineux (max 2MB)";
        }
        
        return $errors;
    }
    
    /**
     * Génère un nom de fichier sécurisé
     */
    public static function generateSecureFileName($originalName) {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        return uniqid() . '_' . time() . '.' . $extension;
    }
}
?>