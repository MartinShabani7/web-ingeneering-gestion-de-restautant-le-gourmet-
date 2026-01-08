<?php
include 'jenga.php';

// Réinitialiser les variables pour ce fichier spécifique
$errors = [];
$success = false;

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
                    
                    // Mettre à jour l'URL d'avatar après rechargement
                    $userAvatarUrl = getAvatarUrl($user['avatar'] ?? '');
                    
                } catch (Exception $e) {
                    error_log("Erreur lors de la mise à jour du profil: " . $e->getMessage());
                    $errors[] = "Erreur lors de la mise à jour du profil";
                }
            }
        }
        
        elseif ($action === 'update_avatar') {
            // Mise à jour de l'avatar
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                // Validation
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $maxFileSize = 2 * 1024 * 1024; // 2MB
                
                if (!in_array($_FILES['avatar']['type'], $allowedTypes)) {
                    $errors[] = 'Type de fichier non autorisé. Formats acceptés: JPEG, PNG, GIF, WebP';
                } elseif ($_FILES['avatar']['size'] > $maxFileSize) {
                    $errors[] = 'Le fichier est trop volumineux. Taille maximum: 2MB';
                } else {
                    try {
                        // Chemin d'upload - IMPORTANT: même chemin que dans l'API
                        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/avatars/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        
                        // Générer un nom de fichier unique
                        $fileExtension = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
                        if ($fileExtension === 'jpeg') $fileExtension = 'jpg';
                        
                        $fileName = 'avatar_' . $userId . '_' . time() . '_' . uniqid() . '.' . $fileExtension;
                        $uploadPath = $uploadDir . $fileName;
                        
                        // Déplacer le fichier
                        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadPath)) {
                            // Redimensionner l'image si nécessaire
                            list($width, $height) = getimagesize($uploadPath);
                            if ($width > 500 || $height > 500) {
                                resizeImage($uploadPath, 500, 500);
                            }
                            
                            // Supprimer l'ancien avatar s'il existe
                            if ($user['avatar']) {
                                $oldPath = getAvatarPath($user['avatar']);
                                if ($oldPath && file_exists($oldPath)) {
                                    unlink($oldPath);
                                }
                            }
                            
                            // IMPORTANT: Stocker SEULEMENT le nom du fichier, pas le chemin complet
                            $avatarToStore = $fileName;
                            
                            // Mettre à jour la base de données
                            $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                            $stmt->execute([$avatarToStore, $userId]);
                            
                            $success = "Photo de profil mise à jour avec succès";
                            
                            // Mettre à jour la variable $user immédiatement pour l'affichage
                            $user['avatar'] = $avatarToStore;
                            $userAvatarUrl = getAvatarUrl($avatarToStore);
                            
                        } else {
                            $errors[] = "Erreur lors de l'enregistrement de la photo";
                        }
                    } catch (Exception $e) {
                        error_log("Erreur avatar: " . $e->getMessage());
                        $errors[] = "Une erreur est survenue lors de la mise à jour de la photo";
                    }
                }
            } else {
                $errorCode = $_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE;
                if ($errorCode === UPLOAD_ERR_INI_SIZE || $errorCode === UPLOAD_ERR_FORM_SIZE) {
                    $errors[] = "Le fichier est trop volumineux";
                } else {
                    $errors[] = "Veuillez sélectionner une photo";
                }
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

<style>
    #container{
        margin-left:265px;
        margin-top:40px;
    }
</style>
        <div class="container" id='container'>
            <!-- Contenu principal -->
            <div class="col-md-12 col-lg-10 dashboard-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Mon profil</h1>
                </div>

                <?php if (isset($success) && $success): ?>
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
                                <?php 
                                $avatarUrl = getAvatarUrl($user['avatar'] ?? '');
                                if ($user['avatar']): ?>
                                    <img src="<?= htmlspecialchars($avatarUrl) ?>" 
                                         alt="Avatar" 
                                         class="rounded-circle mb-3" 
                                         width="150" 
                                         height="150" 
                                         style="object-fit: cover;"
                                         onerror="this.onerror=null; this.src='/assets/img/default_avatar.jpg';">
                                <?php else: ?>
                                    <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 150px; height: 150px;">
                                        <i class="fas fa-user fa-4x text-white"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <form method="POST" enctype="multipart/form-data" id="avatarForm">
                                    <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                                    <input type="hidden" name="action" value="update_avatar">
                                    
                                    <div class="mb-3">
                                        <input type="file" class="form-control" id="avatar" name="avatar" accept="image/*">
                                        <div class="form-text">Formats acceptés: JPG, PNG, GIF, WebP (max 2MB)</div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-outline-primary">
                                        <i class="fas fa-upload me-2"></i>Changer la photo
                                    </button>
                                </form>
                                
                                <!-- Debug: Afficher l'URL réelle pour vérification -->
                                <div class="mt-3 small text-muted">
                                    <strong>Debug:</strong><br>
                                    Avatar en base: <?= htmlspecialchars($user['avatar'] ?? 'NULL') ?><br>
                                    URL générée: <?= htmlspecialchars($avatarUrl) ?>
                                </div>
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
        
        // Prévisualisation de l'avatar avant upload (si vous avez ce script dans jenga.php)
        <?php if (isset($user['avatar'])): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const avatarInput = document.getElementById('avatar');
            if (avatarInput) {
                avatarInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        // Vérifier la taille
                        if (file.size > 2 * 1024 * 1024) {
                            alert('Le fichier est trop volumineux (max 2MB)');
                            this.value = '';
                            return;
                        }
                        
                        // Vérifier le type
                        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                        if (!allowedTypes.includes(file.type)) {
                            alert('Type de fichier non autorisé');
                            this.value = '';
                            return;
                        }
                        
                        // Afficher la prévisualisation
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            document.querySelectorAll('img[alt="Avatar"]').forEach(img => {
                                img.src = e.target.result;
                            });
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>