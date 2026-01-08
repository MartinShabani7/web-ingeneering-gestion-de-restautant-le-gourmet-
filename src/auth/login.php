<?php
session_start();
require_once '../config/database.php';
require_once '../config/security.php';
require_once '../include/AuthService.php';

// Tenter la connexion via le cookie "Se souvenir de moi" si la session n'est pas démarrée
if (!Security::isLoggedIn() && isset($_COOKIE['remember_token'])) {
    Security::attemptLoginFromCookie();
}

// Redirection si déjà connecté
if (Security::isLoggedIn()) {
    if ($_SESSION['user_role'] === 'admin') {
        Security::redirect('../admin/dashboard.php');
    } else {
        Security::redirect('../member/dashboard.php');
    }
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification du token CSRF
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Token de sécurité invalide";
    } else {
        $email = Security::sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);
        
        // Validation des données
        if (empty($email)) {
            $errors[] = "L'email est obligatoire";
        } elseif (!Security::validateEmail($email)) {
            $errors[] = "Format d'email invalide";
        }
        
        if (empty($password)) {
            $errors[] = "Le mot de passe est obligatoire";
        }
        
        // Tentative de connexion
        if (empty($errors)) {
            $result = $authService->login($email, $password, $remember);

            if (isset($result['errors'])) {
                $errors = array_merge($errors, $result['errors']);
            } else {
                // Connexion réussie, redirection selon le rôle
                $user = $result['user'];
                if ($user['role'] === 'admin') {
                    Security::redirect('../admin/dashboard.php');
                } else {
                    Security::redirect('../member/dashboard.php');
                }
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
    <title>Connexion - Restaurant Le Gourmet</title>
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
                            <i class="fas fa-utensils fa-3x text-primary mb-3"></i>
                            <h2 class="fw-bold">Connexion</h2>
                            <p class="text-muted">Accédez à votre espace personnel</p>
                        </div>
                        
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
                        
                        <form method="POST" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($email) ?>" required>
                                </div>
                                <div class="invalid-feedback"></div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Mot de passe</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback"></div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                <label class="form-check-label" for="remember">
                                    Se souvenir de moi
                                </label>
                            </div>
                            
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i>Se connecter
                                </button>
                            </div>
                            
                            <div class="text-center">
                                <a href="forgot_password.php" class="text-primary text-decoration-none">
                                    <i class="fas fa-key me-1"></i>Mot de passe oublié ?
                                </a>
                            </div>
                        </form>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <p class="text-muted mb-0">
                                Pas encore de compte ? 
                                <a href="inscription.php" class="text-primary fw-bold">Inscrivez-vous</a>
                            </p>
                        </div>
                        
                        <div class="text-center mt-3">
                            <a href="../index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Retour à l'accueil
                            </a>
                        </div>
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

