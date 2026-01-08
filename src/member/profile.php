<?php
session_start();
require_once '../config/database.php';
require_once '../config/security.php';

// Vérification de l'authentification
if (!Security::isLoggedIn()) {
    Security::redirect('../auth/login.php');
}

$userId = $_SESSION['user_id'];
$errors = [];
$success = false;

// Récupération des informations utilisateur
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification du token CSRF
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Token de sécurité invalide";
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_profile') {
            // Mise à jour du profil
            $first_name = Security::sanitizeInput($_POST['first_name'] ?? '');
            $last_name = Security::sanitizeInput($_POST['last_name'] ?? '');
            $phone = Security::sanitizeInput($_POST['phone'] ?? '');
            $address = Security::sanitizeInput($_POST['address'] ?? '');
            
            // Validation
            if (empty($first_name)) {
                $errors[] = "Le prénom est obligatoire";
            }
            if (empty($last_name)) {
                $errors[] = "Le nom est obligatoire";
            }
            
            if (empty($errors)) {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET first_name = ?, last_name = ?, phone = ?, address = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$first_name, $last_name, $phone, $address, $userId]);
                    
                    // Log de l'activité
                    $stmt = $pdo->prepare("
                        INSERT INTO activity_logs (user_id, action, ip_address, user_agent, created_at) 
                        VALUES (?, 'profile_updated', ?, ?, NOW())
                    ");
                    $stmt->execute([$userId, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
                    
                    $success = "Profil mis à jour avec succès";
                    
                    // Mettre à jour les données de session
                    $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                    
                    // Recharger les données utilisateur
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch();
                    
                } catch (Exception $e) {
                    error_log("Erreur lors de la mise à jour du profil: " . $e->getMessage());
                    $errors[] = "Erreur lors de la mise à jour du profil";
                }
            }
        }
        
        elseif ($action === 'update_avatar') {
            // Mise à jour de l'avatar
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $uploadErrors = Security::validateFileUpload($_FILES['avatar']);
                if (!empty($uploadErrors)) {
                    $errors = array_merge($errors, $uploadErrors);
                } else {
                    try {
                        $uploadDir = '../uploads/avatars/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        
                        $fileName = Security::generateSecureFileName($_FILES['avatar']['name']);
                        $uploadPath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadPath)) {
                            // Supprimer l'ancien avatar s'il existe
                            if ($user['avatar'] && file_exists('../' . $user['avatar'])) {
                                unlink('../' . $user['avatar']);
                            }
                            
                            // Mettre à jour en base
                            $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                            $stmt->execute(['uploads/avatars/' . $fileName, $userId]);
                            
                            $success = "Photo de profil mise à jour avec succès";
                            
                            // Recharger les données utilisateur
                            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                            $stmt->execute([$userId]);
                            $user = $stmt->fetch();
                            
                        } else {
                            $errors[] = "Erreur lors de l'upload de la photo";
                        }
                    } catch (Exception $e) {
                        error_log("Erreur lors de la mise à jour de l'avatar: " . $e->getMessage());
                        $errors[] = "Erreur lors de la mise à jour de la photo";
                    }
                }
            } else {
                $errors[] = "Aucun fichier sélectionné";
            }
        }
        
        elseif ($action === 'change_password') {
            // Changement de mot de passe
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            // Validation
            if (empty($current_password)) {
                $errors[] = "Le mot de passe actuel est obligatoire";
            }
            if (empty($new_password)) {
                $errors[] = "Le nouveau mot de passe est obligatoire";
            } elseif (!Security::validatePassword($new_password)) {
                $errors[] = "Le nouveau mot de passe doit contenir au moins 8 caractères avec une majuscule, une minuscule et un chiffre";
            }
            if ($new_password !== $confirm_password) {
                $errors[] = "Les nouveaux mots de passe ne correspondent pas";
            }
            
            if (empty($errors)) {
                try {
                    // Vérifier le mot de passe actuel
                    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $userData = $stmt->fetch();
                    
                    if (!Security::verifyPassword($current_password, $userData['password_hash'])) {
                        $errors[] = "Le mot de passe actuel est incorrect";
                    } else {
                        // Mettre à jour le mot de passe
                        $newPasswordHash = Security::hashPassword($new_password);
                        $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$newPasswordHash, $userId]);
                        
                        // Log de l'activité
                        $stmt = $pdo->prepare("
                            INSERT INTO activity_logs (user_id, action, ip_address, user_agent, created_at) 
                            VALUES (?, 'password_changed', ?, ?, NOW())
                        ");
                        $stmt->execute([$userId, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
                        
                        $success = "Mot de passe modifié avec succès";
                    }
                } catch (Exception $e) {
                    error_log("Erreur lors du changement de mot de passe: " . $e->getMessage());
                    $errors[] = "Erreur lors du changement de mot de passe";
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
    <title>Mon profil - Restaurant Le Gourmet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-utensils me-2"></i>Le Gourmet
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Tableau de bord
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="profile.php">
                            <i class="fas fa-user me-1"></i>Mon profil
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="commandes.php">
                            <i class="fas fa-shopping-bag me-1"></i>Mes commandes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reservations.php">
                            <i class="fas fa-calendar me-1"></i>Mes réservations
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../auth/logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i>Déconnexion
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 dashboard-sidebar">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <?php if ($user['avatar']): ?>
                            <img src="../uploads/avatars/<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar" class="rounded-circle mb-2" width="80" height="80">
                        <?php else: ?>
                            <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 80px; height: 80px;">
                                <i class="fas fa-user fa-2x text-white"></i>
                            </div>
                        <?php endif; ?>
                        <h6 class="text-white"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h6>
                        <small class="text-muted"><?= htmlspecialchars($user['email']) ?></small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Tableau de bord
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="profile.php">
                                <i class="fas fa-user me-2"></i>Mon profil
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="commandes.php">
                                <i class="fas fa-shopping-bag me-2"></i>Mes commandes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reservations.php">
                                <i class="fas fa-calendar me-2"></i>Mes réservations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="favorites.php">
                                <i class="fas fa-heart me-2"></i>Mes favoris
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="temoignage.php">
                                <i class="fas fa-star me-2"></i>Mes avis
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Contenu principal -->
            <div class="col-md-9 col-lg-10 dashboard-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Mon profil</h1>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
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

                <div class="row">
                    <!-- Informations personnelles -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-user me-2"></i>Informations personnelles
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" class="needs-validation" novalidate>
                                    <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                                    <input type="hidden" name="action" value="update_profile">
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="first_name" class="form-label">Prénom *</label>
                                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                                   value="<?= htmlspecialchars($user['first_name']) ?>" required>
                                            <div class="invalid-feedback"></div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="last_name" class="form-label">Nom *</label>
                                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                                   value="<?= htmlspecialchars($user['last_name']) ?>" required>
                                            <div class="invalid-feedback"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                                        <div class="form-text">L'email ne peut pas être modifié</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Téléphone</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?= htmlspecialchars($user['phone']) ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Adresse</label>
                                        <textarea class="form-control" id="address" name="address" rows="3"><?= htmlspecialchars($user['address']) ?></textarea>
                                    </div>
                                    
                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Mettre à jour
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Photo de profil -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-camera me-2"></i>Photo de profil
                                </h5>
                            </div>
                            <div class="card-body text-center">
                                <?php if ($user['avatar']): ?>
                                    <img src="../uploads/avatars/<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar" class="rounded-circle mb-3" width="150" height="150">
                                <?php else: ?>
                                    <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 150px; height: 150px;">
                                        <i class="fas fa-user fa-4x text-white"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                                    <input type="hidden" name="action" value="update_avatar">
                                    
                                    <div class="mb-3">
                                        <input type="file" class="form-control" id="avatar" name="avatar" accept="image/*">
                                        <div class="form-text">Formats acceptés: JPG, PNG, GIF (max 2MB)</div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-outline-primary">
                                        <i class="fas fa-upload me-2"></i>Changer la photo
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Changement de mot de passe -->
                <div class="row mt-4">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-lock me-2"></i>Changer le mot de passe
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" class="needs-validation" novalidate>
                                    <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                                    <input type="hidden" name="action" value="change_password">
                                    
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Mot de passe actuel *</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">Nouveau mot de passe *</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="form-text">Minimum 8 caractères avec majuscule, minuscule et chiffre</div>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe *</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-warning">
                                            <i class="fas fa-key me-2"></i>Changer le mot de passe
                                        </button>
                                    </div>
                                </form>
                            </div>
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

