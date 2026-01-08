<?php
session_start();
require_once '../config/database.php';
require_once '../config/security.php';


// Configuration PHPMailer intégrée
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once '../vendor/autoload.php';

// require '../include/PHPMailer-master/src/Exception.php';
// require '../include/PHPMailer-master/src/PHPMailer.php';
// require '../include/PHPMailer-master/src/SMTP.php';

class Mailer {
    // CONFIGURATION SMTP
    private static $SMTP_HOST = 'smtp.gmail.com';
    private static $SMTP_USER = 'martinshabani7@gmail.com';
    private static $SMTP_PASS = 'oplx hrhb rdda wpob';
    private static $SMTP_PORT = 587;
    private static $MAIL_FROM = 'martinshabani7@gmail.com';
    private static $MAIL_FROM_NAME = 'Le Gourmet';
    public static $MAIL_DEV_MODE = false;

    public static function send($to, $subject, $htmlBody) {
        if (self::$MAIL_DEV_MODE) {
            return self::logEmail($to, $subject, $htmlBody);
        }
        return self::sendRealEmail($to, $subject, $htmlBody);
    }

    private static function sendRealEmail($to, $subject, $htmlBody) {
        $mail = new PHPMailer(true);
        
        try {
            $mail->isSMTP();
            $mail->Host       = self::$SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = self::$SMTP_USER;
            $mail->Password   = self::$SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = self::$SMTP_PORT;
            $mail->CharSet    = 'UTF-8';
            
            $mail->setFrom(self::$MAIL_FROM, self::$MAIL_FROM_NAME);
            $mail->addAddress($to);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = strip_tags($htmlBody);

            $mail->send();
            
            self::logEmail($to, $subject, $htmlBody, true);
            return true;
            
        } catch (Exception $e) {
            error_log("ERREUR MAILER - Destinataire: $to - Erreur: " . $mail->ErrorInfo);
            self::logEmail($to, $subject, "ÉCHEC: " . $mail->ErrorInfo . "\n\n" . $htmlBody, false);
            return false;
        }
    }

    private static function logEmail($to, $subject, $content, $success = true) {
        $dir = dirname(__DIR__) . '/logs';
        if (!is_dir($dir)) { 
            @mkdir($dir, 0755, true); 
        }
        
        $status = $success ? "SUCCÈS" : "ÉCHEC";
        $logFile = $dir . '/mail.log';
        $entry = "=== [$status] " . date('Y-m-d H:i:s') . " ===\n";
        $entry .= "À: $to\n";
        $entry .= "Sujet: $subject\n";
        $entry .= "Contenu:\n$content\n\n";
        
        @file_put_contents($logFile, $entry, FILE_APPEND);
        return true;
    }
}

// Redirection si déjà connecté
if (Security::isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}

$errors = [];
$success = false;

// Fonction pour créer un utilisateur
function createUser($data) {
    global $pdo, $errors;
    
    try {
        // Vérifier si l'email existe déjà
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        
        if ($stmt->fetch()) {
            $errors[] = "Cet email est déjà utilisé.";
            return false;
        }

        // Générer un token de vérification - EN CLAIR
        $emailToken = bin2hex(random_bytes(32));
        
        // Stocker le token EN CLAIR dans la base (plus simple pour la vérification)
        $tokenInDatabase = $emailToken;

        // Hasher le mot de passe
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

        // Gérer l'avatar
        $avatarFileName = null;
        if (isset($data['avatar']) && $data['avatar']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/avatars/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileExtension = pathinfo($data['avatar']['name'], PATHINFO_EXTENSION);
            $fileName = uniqid() . '.' . $fileExtension;
            $avatarPath = $uploadDir . $fileName;
            
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $fileType = mime_content_type($data['avatar']['tmp_name']);
            
            if (!in_array($fileType, $allowedTypes)) {
                $errors[] = "Format de fichier non autorisé. Utilisez JPG, PNG ou GIF.";
                return false;
            }
            
            if ($data['avatar']['size'] > 2 * 1024 * 1024) {
                $errors[] = "L'image est trop volumineuse (max 2MB).";
                return false;
            }
            
            if (move_uploaded_file($data['avatar']['tmp_name'], $avatarPath)) {
                $avatarFileName = $fileName;
            }
        }

        // Insérer l'utilisateur avec le token EN CLAIR
        $stmt = $pdo->prepare("INSERT INTO users 
                              (email, password_hash, first_name, last_name, phone, avatar, role, email_verified, email_verification_token, created_at) 
                              VALUES (?, ?, ?, ?, ?, ?, 'customer', FALSE, ?, NOW())");
        
        $stmt->execute([
            $data['email'],
            $hashedPassword,
            $data['first_name'],
            $data['last_name'],
            $data['phone'],
            $avatarFileName,
            $tokenInDatabase  // ← Token EN CLAIR
        ]);

        $userId = $pdo->lastInsertId();
        
        error_log("Nouvel utilisateur créé - ID: $userId, Email: {$data['email']}, Token: $emailToken");
        
        return [
            'user_id' => $userId,
            'email_token' => $emailToken,
            'first_name' => $data['first_name']
        ];
        
    } catch (Exception $e) {
        error_log("Erreur création utilisateur: " . $e->getMessage());
        $errors[] = "Une erreur est survenue lors de la création du compte.";
        return false;
    }
}

// Fonction pour créer le template d'email
function createVerificationEmail($firstName, $verificationLink) {
    return "
    <!DOCTYPE html>
    <html lang='fr'>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f8f9fa; }
            .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
            .content { padding: 30px; }
            .button { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 14px 28px; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 20px 0; }
            .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #e9ecef; color: #6c757d; font-size: 14px; text-align: center; }
            .verification-link { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; padding: 15px; margin: 20px 0; word-break: break-all; font-family: monospace; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1><i class='fas fa-utensils'></i> Le Gourmet</h1>
                <p>Votre destination culinaire</p>
            </div>
            
            <div class='content'>
                <h2>Bonjour $firstName,</h2>
                <p>Merci de vous être inscrit sur <strong>Le Gourmet</strong> ! Pour activer votre compte et commencer à explorer nos délicieuses recettes, veuillez confirmer votre adresse email.</p>
                
                <div style='text-align: center;'>
                    <a href='$verificationLink' class='button'>
                        <i class='fas fa-check-circle'></i> Confirmer mon email
                    </a>
                </div>
                
                <p>Si le bouton ne fonctionne pas, copiez et collez le lien suivant dans votre navigateur :</p>
                <div class='verification-link'>$verificationLink</div>
                
                <div style='background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; padding: 15px; margin: 20px 0;'>
                    <strong><i class='fas fa-shield-alt'></i> Sécurité :</strong> 
                    Ce lien expirera dans 24 heures pour protéger votre compte.
                </div>
                
                <p>Si vous n'avez pas créé de compte sur Le Gourmet, vous pouvez ignorer cet email.</p>
                
                <div class='footer'>
                    <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
                    <p>© " . date('Y') . " Le Gourmet. Tous droits réservés.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Token de sécurité invalide";
    } else {
        $email = Security::sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $first_name = Security::sanitizeInput($_POST['first_name'] ?? '');
        $last_name = Security::sanitizeInput($_POST['last_name'] ?? '');
        $phone = Security::sanitizeInput($_POST['phone'] ?? '');
        
        // Validation des données
        if (empty($email)) $errors[] = "L'email est obligatoire";
        elseif (!Security::validateEmail($email)) $errors[] = "Format d'email invalide";
        
        if (empty($password)) $errors[] = "Le mot de passe est obligatoire";
        elseif (!Security::validatePassword($password)) $errors[] = "Le mot de passe doit contenir au moins 8 caractères avec une majuscule, une minuscule et un chiffre";
        
        if ($password !== $confirm_password) $errors[] = "Les mots de passe ne correspondent pas";
        if (empty($first_name)) $errors[] = "Le prénom est obligatoire";
        if (empty($last_name)) $errors[] = "Le nom est obligatoire";
        
        if (empty($errors)) {
            $userData = [
                'email' => $email,
                'password' => $password,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'phone' => $phone,
                'avatar' => $_FILES['avatar'] ?? null
            ];

            $result = createUser($userData);

            if ($result) {
                $userId = $result['user_id'];
                $emailToken = $result['email_token'];
                $userFirstName = $result['first_name'];

                // CORRECTION : Utiliser le bon nom de fichier
                $verificationLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/verification_mail.php?token=" . $emailToken;
                
                $subject = 'Vérifiez votre adresse email - Le Gourmet';
                $body = createVerificationEmail($userFirstName, $verificationLink);
                
                $emailSent = Mailer::send($email, $subject, $body);
                
                if ($emailSent) {
                    $success = true;
                    $_SESSION['success_message'] = "Compte créé avec succès ! Un email de confirmation a été envoyé à $email";
                } else {
                    $errors[] = "Compte créé mais l'email de confirmation n'a pas pu être envoyé. Contactez le support.";
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
    <title>Inscription - Restaurant Le Gourmet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center min-vh-100 align-items-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="fas fa-utensils fa-3x text-primary mb-3"></i>
                            <h2 class="fw-bold">Rejoindre Le Gourmet</h2>
                            <p class="text-muted">Créez votre compte pour profiter de nos services</p>
                        </div>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                Votre compte a été créé avec succès !<br>
                                <strong>Un email de confirmation a été envoyé à <?= htmlspecialchars($email) ?></strong><br>
                                <small class="text-muted">Vérifiez votre boîte de réception et vos spams.</small>
                                <div class="mt-2">
                                    <a href="login.php" class="btn btn-success btn-sm">Se connecter</a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
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
                        
                        <?php if (!$success): ?>
                        <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">Prénom *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required>
                                    <div class="invalid-feedback">Veuillez saisir votre prénom.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Nom *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required>
                                    <div class="invalid-feedback">Veuillez saisir votre nom.</div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                                </div>
                                <div class="invalid-feedback">Veuillez saisir un email valide.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="phone" class="form-label">Téléphone</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Mot de passe *</label>
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
                                <div class="invalid-feedback">Le mot de passe ne respecte pas les critères de sécurité.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirmer le mot de passe *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">Les mots de passe ne correspondent pas.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="avatar" class="form-label">Photo de profil</label>
                                <input type="file" class="form-control" id="avatar" name="avatar" accept="image/*">
                                <div class="form-text">Formats acceptés: JPG, PNG, GIF (max 2MB)</div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    J'accepte les <a href="#" class="text-primary">conditions d'utilisation</a> et la <a href="#" class="text-primary">politique de confidentialité</a>
                                </label>
                                <div class="invalid-feedback">Vous devez accepter les conditions</div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-user-plus me-2"></i>Créer mon compte
                                </button>
                            </div>
                        </form>
                        
                        <div class="text-center mt-4">
                            <p class="text-muted">
                                Déjà membre ? 
                                <a href="login.php" class="text-primary fw-bold">Connectez-vous</a>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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

        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }
                        form.classList.add('was-validated')
                    }, false)
                })
        })()
    </script>
</body>
</html>