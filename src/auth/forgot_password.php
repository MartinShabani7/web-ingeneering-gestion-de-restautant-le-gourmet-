<?php
session_start();
require_once '../config/database.php';
require_once '../config/security.php';
require_once '../config/mailer.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification du token CSRF
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Token de sécurité invalide";
    } else {
        $email = Security::sanitizeInput($_POST['email'] ?? '');
        
        // Validation de l'email
        if (empty($email)) {
            $errors[] = "L'email est obligatoire";
        } elseif (!Security::validateEmail($email)) {
            $errors[] = "Format d'email invalide";
        }
        
        if (empty($errors)) {
            try {
                // Vérifier si l'utilisateur existe
                $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE email = ? AND is_active = 1");
                $stmt->execute([$email]);
                $user = $user = $stmt->fetch();
                
                if ($user) {
                    // Générer un token de réinitialisation
                    $resetToken = Security::generateResetToken();
                    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    // Sauvegarder le token en base
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET password_reset_token = ?, password_reset_expires = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$resetToken, $expiresAt, $user['id']]);
                    
                    // Log de l'activité
                    $stmt = $pdo->prepare("
                        INSERT INTO activity_logs (user_id, action, ip_address, user_agent, created_at) 
                        VALUES (?, 'password_reset_requested', ?, ?, NOW())
                    ");
                    $stmt->execute([$user['id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
                    
                    // Envoi de l'email de réinitialisation
                    $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $resetToken;
                    $subject = 'Réinitialisez votre mot de passe - Le Gourmet';
                    $body = '<p>Bonjour ' . htmlspecialchars($user['first_name']) . ',</p>' .
                            '<p>Vous avez demandé à réinitialiser votre mot de passe. Cliquez sur le lien ci-dessous pour continuer (valide 1 heure):</p>' .
                            '<p><a href="' . htmlspecialchars($resetLink) . '">Réinitialiser mon mot de passe</a></p>' .
                            '<p>Si vous n\'êtes pas à l\'origine de cette demande, vous pouvez ignorer cet email.</p>' .
                            '<p>— L\'équipe Le Gourmet</p>';
                    Mailer::send($email, $subject, $body);
                    
                    $success = true;
                    if (Mailer::$MAIL_DEV_MODE) { $resetLinkDisplay = $resetLink; }
                } else {
                    // Pour des raisons de sécurité, on ne révèle pas si l'email existe
                    $success = true;
                }
                
            } catch (Exception $e) {
                error_log("Erreur lors de la demande de réinitialisation: " . $e->getMessage());
                $errors[] = "Erreur lors du traitement de votre demande";
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
    <title>Mot de passe oublié - Restaurant Le Gourmet</title>
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
                            <i class="fas fa-key fa-3x text-primary mb-3"></i>
                            <h2 class="fw-bold">Mot de passe oublié</h2>
                            <p class="text-muted">Entrez votre email pour recevoir un lien de réinitialisation</p>
                        </div>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Email envoyé !</strong><br>
                                Si cet email existe dans notre système, vous recevrez un lien de réinitialisation.
                                
                                <?php if (isset($resetLinkDisplay)): ?>
                                    <hr>
                                    <small class="text-muted">
                                        <strong>Mode démo :</strong> Voici le lien de réinitialisation :<br>
                                        <a href="<?= htmlspecialchars($resetLinkDisplay) ?>" class="text-break">
                                            <?= htmlspecialchars($resetLinkDisplay) ?>
                                        </a>
                                    </small>
                                <?php endif; ?>
                            </div>
                            
                            <div class="text-center">
                                <a href="login.php" class="btn btn-primary">
                                    <i class="fas fa-arrow-left me-2"></i>Retour à la connexion
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
                            
                            <form method="POST" class="needs-validation" novalidate>
                                <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                                
                                <div class="mb-4">
                                    <label for="email" class="form-label">Adresse email</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                                    </div>
                                    <div class="form-text">
                                        Nous vous enverrons un lien pour réinitialiser votre mot de passe
                                    </div>
                                    <div class="invalid-feedback"></div>
                                </div>
                                
                                <div class="d-grid mb-3">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-paper-plane me-2"></i>Envoyer le lien
                                    </button>
                                </div>
                            </form>
                            
                            <div class="text-center">
                                <a href="login.php" class="text-primary text-decoration-none">
                                    <i class="fas fa-arrow-left me-1"></i>Retour à la connexion
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>

