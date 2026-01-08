<?php
// require_once '../config/database.php';
require_once(__DIR__ . '/../config/database.php');
require_once(__DIR__ . '/../config/security.php');

class AuthService {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Tente de connecter un utilisateur.
     * @param string $email
     * @param string $password
     * @param bool $remember
     * @return array|null Retourne l'utilisateur en cas de succès, ou un tableau d'erreurs.
     */
    public function login($email, $password, $remember) {
        $errors = [];

        // 1. Vérification de la protection contre la force brute
        if (!Security::checkBruteForce($email)) {
            $errors[] = "Trop de tentatives de connexion. Veuillez réessayer dans 15 minutes.";
            return ['errors' => $errors];
        }

        try {
            // 2. Récupération de l'utilisateur (sans vérifier is_active ici)
            $stmt = $this->pdo->prepare("
                SELECT id, email, password_hash, first_name, last_name, role, is_active, email_verified, last_login 
                FROM users 
                WHERE email = ?
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // 3. Vérification du mot de passe
            if (!$user || !Security::verifyPassword($password, $user['password_hash'])) {
                // Enregistrement de la tentative échouée si l'utilisateur existe
                if ($user) {
                    Security::recordFailedAttempt($email);
                }
                $errors[] = "Email ou mot de passe incorrect";
                return ['errors' => $errors];
            }

            // 4. Vérification de l'état du compte
            if (!$user['is_active']) {
                $errors[] = "Votre compte est inactif. Veuillez contacter l'administrateur.";
                return ['errors' => $errors];
            }
            
            // 5. Vérification de l'email
            if (!$user['email_verified']) {
                $errors[] = "Veuillez vérifier votre email avant de vous connecter";
                return ['errors' => $errors];
            }

            // 6. Connexion réussie
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['login_time'] = time();
            
            // Mise à jour de la dernière connexion
            $stmt = $this->pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            // Log de l'activité
            $stmt = $this->pdo->prepare("
                INSERT INTO activity_logs (user_id, action, ip_address, user_agent, created_at) 
                VALUES (?, 'user_login', ?, ?, NOW())
            ");
            $stmt->execute([$user['id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
            
            // Nettoyer les tentatives de force brute (par IP)
            // Security::clearBruteForceAttempts($email); // Plus nécessaire avec l'approche par IP
            
            // Gestion du "Se souvenir de moi"
            if ($remember) {
                $token = Security::createRememberToken($user['id']);
                setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);
            }

            return ['user' => $user];

        } catch (Exception $e) {
            error_log("Erreur lors de la connexion: " . $e->getMessage());
            $errors[] = "Erreur lors de la connexion";
            return ['errors' => $errors];
        }
    }

    /**
     * Enregistre un nouvel utilisateur.
     * @param array $data
     * @return array|null Retourne l'ID de l'utilisateur en cas de succès, ou un tableau d'erreurs.
     */
    public function register($data) {
        $errors = [];
        
        // 1. Validation des données (simplifiée, la validation complète reste dans inscription.php)
        if (!Security::validateEmail($data['email'])) {
            $errors[] = "Format d'email invalide";
        }
        if (!Security::validatePassword($data['password'])) {
            $errors[] = "Mot de passe invalide";
        }
        if ($data['password'] !== $data['confirm_password']) {
            $errors[] = "Les mots de passe ne correspondent pas";
        }

        if (!empty($errors)) {
            return ['errors' => $errors];
        }

        try {
            $this->pdo->beginTransaction();
            
            // 2. Vérifier si l'email existe déjà
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                $errors[] = "Cet email est déjà utilisé";
                $this->pdo->rollback();
                return ['errors' => $errors];
            }

            // 3. Gestion de l'upload de photo (la validation complète est dans inscription.php)
            $avatar = null;
            if (isset($data['avatar']) && $data['avatar']['error'] === UPLOAD_ERR_OK) {
                $uploadErrors = Security::validateFileUpload($data['avatar']);
                if (!empty($uploadErrors)) {
                    $this->pdo->rollback();
                    return ['errors' => $uploadErrors];
                }
                
                $uploadDir = '../uploads/avatars/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileName = Security::generateSecureFileName($data['avatar']['name']);
                $uploadPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($data['avatar']['tmp_name'], $uploadPath)) {
                    $avatar = 'uploads/avatars/' . $fileName;
                } else {
                    $errors[] = "Erreur lors de l'upload de la photo";
                    $this->pdo->rollback();
                    return ['errors' => $errors];
                }
            }

            // 4. Hash du mot de passe et token de vérification
            $passwordHash = Security::hashPassword($data['password']);
            $emailToken = bin2hex(random_bytes(32));
            
            // 5. Insertion de l'utilisateur
            $stmt = $this->pdo->prepare("
                INSERT INTO users (email, password_hash, first_name, last_name, phone, avatar, email_verification_token, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $data['email'],
                $passwordHash,
                $data['first_name'],
                $data['last_name'],
                $data['phone'],
                $avatar,
                $emailToken
            ]);
            
            $userId = $this->pdo->lastInsertId();
            
            // 6. Log de l'activité
            $stmt = $this->pdo->prepare("
                INSERT INTO activity_logs (user_id, action, ip_address, user_agent, created_at) 
                VALUES (?, 'user_registered', ?, ?, NOW())
            ");
            $stmt->execute([$userId, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
            
            $this->pdo->commit();

            return ['user_id' => $userId, 'email_token' => $emailToken];

        } catch (Exception $e) {
            $this->pdo->rollback();
            error_log("Erreur lors de l'inscription: " . $e->getMessage());
            $errors[] = "Erreur lors de la création du compte";
            return ['errors' => $errors];
        }
    }
}

// Instance globale du service d'authentification
$authService = new AuthService($pdo);
