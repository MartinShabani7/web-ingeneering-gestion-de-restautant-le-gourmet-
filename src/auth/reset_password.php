<?php
session_start();
require_once '../config/database.php';
require_once '../config/security.php';

$errors = [];
$success = false;
$token = $_GET['token'] ?? '';

// Vérifier si le token est valide
if (empty($token)) {
    $errors[] = "Token de réinitialisation manquant";
} else {
    try {
        $stmt = $pdo->prepare("
            SELECT id, first_name, last_name 
            FROM users 
            WHERE password_reset_token = ? 
            AND password_reset_expires > NOW() 
            AND is_active = 1
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $errors[] = "Token de réinitialisation invalide ou expiré";
        }
    } catch (Exception $e) {
        error_log("Erreur lors de la vérification du token: " . $e->getMessage());
        $errors[] = "Erreur lors de la vérification du token";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    // Vérification du token CSRF
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Token de sécurité invalide";
    } else {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validation du mot de passe
        if (empty($password)) {
            $errors[] = "Le mot de passe est obligatoire";
        } elseif (!Security::validatePassword($password)) {
            $errors[] = "Le mot de passe doit contenir au moins 8 caractères avec une majuscule, une minuscule et un chiffre";
        }
        
        if ($password !== $confirm_password) {
            $errors[] = "Les mots de passe ne correspondent pas";
        }
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                // Hash du nouveau mot de passe
                $passwordHash = Security::hashPassword($password);
                
                // Mise à jour du mot de passe et suppression du token
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET password_hash = ?, password_reset_token = NULL, password_reset_expires = NULL 
                    WHERE password_reset_token = ?
                ");
                $stmt->execute([$passwordHash, $token]);
                
                // Log de l'activité
                $stmt = $pdo->prepare("
                    INSERT INTO activity_logs (user_id, action, ip_address, user_agent, created_at) 
                    VALUES (?, 'password_reset_completed', ?, ?, NOW())
                ");
                $stmt->execute([$user['id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
                
                $pdo->commit();
                $success = true;
                
            } catch (Exception $e) {
                $pdo->rollback();
                error_log("Erreur lors de la réinitialisation: " . $e->getMessage());
                $errors[] = "Erreur lors de la réinitialisation du mot de passe";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation du mot de passe - Restaurant Le Gourmet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center min-vh-100 align-items-center">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="fas fa-lock fa-3x text-primary mb-3"></i>
                            <h2 class="fw-bold">Nouveau mot de passe</h2>
                            <?php if (isset($user)): ?>
                                <p class="text-muted">Bonjour <?= htmlspecialchars($user['first_name']) ?>, créez votre nouveau mot de passe</p>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Mot de passe mis à jour !</strong><br>
                                Votre mot de passe a été réinitialisé avec succès.
                            </div>
                            
                            <div class="text-center">
                                <a href="login.php" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt me-2"></i>Se connecter
                                </a>
                            </div>
                        <?php else: ?>
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?= htmlspecialchars($error) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($user)): ?>
                                <form method="POST" class="needs-validation" novalidate>
                                    <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                                    
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Nouveau mot de passe</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" id="password" name="password" required>
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="form-text">
                                            Minimum 8 caractères avec majuscule, minuscule et chiffre
                                        </div>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-save me-2"></i>Réinitialiser le mot de passe
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <div class="text-center">
                                    <a href="forgot_password.php" class="btn btn-primary">
                                        <i class="fas fa-arrow-left me-2"></i>Demander un nouveau lien
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.parentNode.querySelector('button');
            const icon = button.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>

