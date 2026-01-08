<?php
session_start();
require_once '../config/database.php';
require_once '../config/security.php';

$token = $_GET['token'] ?? '';
$message = '';
$success = false;

error_log("=== DÉBUT VÉRIFICATION EMAIL ===");
error_log("Token reçu: " . $token);

if ($token === '') {
    $message = "Token manquant.";
} else {
    try {
        // Vérifier si le token existe en CLAIR dans la base
        $stmt = $pdo->prepare("SELECT id, email, email_verified FROM users WHERE email_verification_token = ? AND email_verified = FALSE");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        error_log("Utilisateur trouvé avec token clair: " . ($user ? 'OUI - ' . $user['email'] : 'NON'));

        if (!$user) {
            // Si pas trouvé en clair, vérifier avec les tokens hashés
            $stmt2 = $pdo->prepare("SELECT id, email, email_verified, email_verification_token FROM users WHERE email_verified = FALSE");
            $stmt2->execute();
            $users = $stmt2->fetchAll();

            $userFound = null;
            foreach ($users as $u) {
                if (password_verify($token, $u['email_verification_token'])) {
                    $userFound = $u;
                    error_log("Utilisateur trouvé avec token hashé: " . $u['email']);
                    break;
                }
            }

            if ($userFound) {
                $user = $userFound;
            }
        }

        if (!$user) {
            // Vérifier si le compte est déjà vérifié
            $stmt3 = $pdo->prepare("SELECT id FROM users WHERE email_verification_token = ? AND email_verified = TRUE");
            $stmt3->execute([$token]);
            if ($stmt3->fetch()) {
                $message = "Votre email est déjà vérifié.";
            } else {
                $message = "Lien invalide ou expiré.";
            }
        } elseif ((bool)$user['email_verified'] === true) {
            $message = "Votre email est déjà vérifié.";
        } else {
            // Activer le compte et supprimer le token
            $upd = $pdo->prepare("UPDATE users SET email_verified = TRUE, email_verification_token = NULL, updated_at = NOW() WHERE id = ?");
            $upd->execute([$user['id']]);
            $success = true;
            $message = "Email vérifié avec succès. Vous pouvez maintenant vous connecter.";
            
            $_SESSION['success_message'] = "Votre email a été vérifié avec succès ! Vous pouvez maintenant vous connecter.";
            error_log("Compte vérifié avec succès pour l'utilisateur ID: " . $user['id']);
        }
    } catch (Exception $e) {
        error_log('Erreur vérification email: ' . $e->getMessage());
        $message = "Une erreur est survenue lors de la vérification.";
    }
}

error_log("=== FIN VÉRIFICATION EMAIL ===");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification de l'email - Le Gourmet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow border-0">
                    <div class="card-body p-4 text-center">
                        <i class="fas fa-envelope-circle-check fa-3x mb-3 text-<?= $success ? 'success' : 'warning' ?>"></i>
                        <h3 class="mb-3">Vérification de l'email</h3>
                        <div class="alert alert-<?= $success ? 'success' : 'warning' ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                        <?php if ($success): ?>
                            <a href="login.php" class="btn btn-success">
                                <i class="fas fa-sign-in-alt me-2"></i>Se connecter
                            </a>
                        <?php else: ?>
                            <div class="d-flex gap-2 justify-content-center">
                                <a href="login.php" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt me-2"></i>Page de connexion
                                </a>
                                <a href="inscription.php" class="btn btn-outline-primary">
                                    <i class="fas fa-user-plus me-2"></i>Nouvelle inscription
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>