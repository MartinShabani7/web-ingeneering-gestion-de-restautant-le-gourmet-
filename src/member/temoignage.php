<?php
session_start();
require_once '../config/database.php';
require_once '../config/security.php';

// Vérification de l'authentification
if (!Security::isLoggedIn()) {
    Security::redirect('../auth/login.php');
}

$userId = $_SESSION['user_id'];
$success = $error = '';

// Récupération des informations utilisateur
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Vérifier si l'utilisateur a déjà laissé un avis
$stmt = $pdo->prepare("SELECT * FROM testimonials WHERE user_id = ?");
$stmt->execute([$userId]);
$existingTestimonial = $stmt->fetch();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = $_POST['rating'] ?? 0;
    $comment = trim($_POST['comment'] ?? '');

    // Validation
    if ($rating < 1 || $rating > 5) {
        $error = "Veuillez sélectionner une note entre 1 et 5 étoiles.";
    } elseif (empty($comment) || strlen($comment) < 10) {
        $error = "Votre commentaire doit contenir au moins 10 caractères.";
    } else {
        try {
            if ($existingTestimonial) {
                // Mise à jour de l'avis existant
                $stmt = $pdo->prepare("
                    UPDATE testimonials 
                    SET rating = ?, comment = ?, status = 'pending', updated_at = NOW() 
                    WHERE user_id = ?
                ");
                $stmt->execute([$rating, $comment, $userId]);
                $success = "Votre avis a été mis à jour avec succès ! Il sera revu par notre équipe.";
            } else {
                // Nouvel avis
                $stmt = $pdo->prepare("
                    INSERT INTO testimonials (user_id, rating, comment, status) 
                    VALUES (?, ?, ?, 'pending')
                ");
                $stmt->execute([$userId, $rating, $comment]);
                $success = "Merci pour votre avis ! Il sera publié après validation par notre équipe.";
            }
            
            // Recharger l'avis existant
            $stmt = $pdo->prepare("SELECT * FROM testimonials WHERE user_id = ?");
            $stmt->execute([$userId]);
            $existingTestimonial = $stmt->fetch();
            
        } catch (PDOException $e) {
            $error = "Une erreur est survenue. Veuillez réessayer.";
        }
    }
}

include 'header_navbar.php';
?>

    <style>
        #container{
            margin-top:25px;
            margin-left:260px;
        }
        .rating-stars {
            font-size: 2rem;
            cursor: pointer;
            color: #ddd;
        }
        .rating-stars .star {
            transition: color 0.2s;
        }
        .rating-stars .star:hover,
        .rating-stars .star.active {
            color: #ffc107;
        }
        .testimonial-card {
            border-left: 4px solid #007bff;
        }
        .status-pending { color: #ffc107; }
        .status-approved { color: #28a745; }
        .status-rejected { color: #dc3545; }
    </style>

    <div class="container mt-4" id='container'>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- En-tête -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>
                        <i class="fas fa-star me-2"></i>Votre avis compte !
                    </h1>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Retour
                    </a>
                </div>

                <!-- Messages d'alerte -->
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Formulaire d'avis -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-edit me-2"></i>
                                    <?= $existingTestimonial ? 'Modifier votre avis' : 'Laisser un avis' ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="testimonialForm">
                                    <!-- Notation par étoiles -->
                                    <div class="mb-4">
                                        <label class="form-label fw-bold">Notez votre expérience :</label>
                                        <div class="rating-stars mb-2" id="ratingStars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <span class="star" data-rating="<?= $i ?>">
                                                    <i class="fas fa-star"></i>
                                                </span>
                                            <?php endfor; ?>
                                        </div>
                                        <input type="hidden" name="rating" id="ratingInput" 
                                               value="<?= $existingTestimonial ? $existingTestimonial['rating'] : 0 ?>">
                                        <small class="text-muted">
                                            Cliquez sur les étoiles pour noter de 1 à 5
                                        </small>
                                    </div>

                                    <!-- Commentaire -->
                                    <div class="mb-4">
                                        <label for="comment" class="form-label fw-bold">
                                            Votre commentaire :
                                        </label>
                                        <textarea class="form-control" id="comment" name="comment" 
                                                  rows="6" placeholder="Partagez votre expérience au restaurant Le Gourmet..."
                                                  required><?= $existingTestimonial ? htmlspecialchars($existingTestimonial['comment']) : '' ?></textarea>
                                        <div class="form-text">
                                            Minimum 10 caractères. Votre avis sera modéré avant publication.
                                        </div>
                                    </div>

                                    <!-- Bouton de soumission -->
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="reset" class="btn btn-outline-secondary me-md-2">
                                            <i class="fas fa-redo me-1"></i>Réinitialiser
                                        </button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-paper-plane me-1"></i>
                                            <?= $existingTestimonial ? 'Mettre à jour' : 'Publier mon avis' ?>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Informations et statut -->
                    <div class="col-md-4">
                        <!-- Statut de l'avis actuel -->
                        <?php if ($existingTestimonial): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-info-circle me-2"></i>Votre avis actuel
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <strong>Note :</strong><br>
                                    <div class="text-warning">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star<?= $i <= $existingTestimonial['rating'] ? '' : '-o' ?>"></i>
                                        <?php endfor; ?>
                                        <span class="ms-1">(<?= $existingTestimonial['rating'] ?>/5)</span>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <strong>Statut :</strong><br>
                                    <span class="status-<?= $existingTestimonial['status'] ?>">
                                        <i class="fas fa-<?= $existingTestimonial['status'] === 'approved' ? 'check' : ($existingTestimonial['status'] === 'rejected' ? 'times' : 'clock') ?> me-1"></i>
                                        <?= ucfirst($existingTestimonial['status']) ?>
                                    </span>
                                </div>
                                <div>
                                    <strong>Dernière modification :</strong><br>
                                    <?= date('d/m/Y H:i', strtotime($existingTestimonial['updated_at'])) ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Conseils -->
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">
                                    <i class="fas fa-lightbulb me-2"></i>Conseils pour votre avis
                                </h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled small">
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Soyez spécifique et détaillé
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Partagez votre expérience globale
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Mentionnez vos plats préférés
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Parlez de l'ambiance et du service
                                    </li>
                                    <li>
                                        <i class="fas fa-check text-success me-2"></i>
                                        Restez courtois et constructif
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Gestion du système d'étoiles
        document.addEventListener('DOMContentLoaded', function() {
            const stars = document.querySelectorAll('.star');
            const ratingInput = document.getElementById('ratingInput');
            const currentRating = <?= $existingTestimonial ? $existingTestimonial['rating'] : 0 ?>;

            // Initialiser les étoiles si un avis existe
            if (currentRating > 0) {
                highlightStars(currentRating);
            }

            stars.forEach(star => {
                star.addEventListener('click', function() {
                    const rating = parseInt(this.getAttribute('data-rating'));
                    ratingInput.value = rating;
                    highlightStars(rating);
                });

                star.addEventListener('mouseover', function() {
                    const rating = parseInt(this.getAttribute('data-rating'));
                    highlightStars(rating);
                });
            });

            // Réinitialiser au survol de la zone d'étoiles
            document.getElementById('ratingStars').addEventListener('mouseleave', function() {
                const currentRating = parseInt(ratingInput.value);
                highlightStars(currentRating);
            });

            function highlightStars(rating) {
                stars.forEach(star => {
                    const starRating = parseInt(star.getAttribute('data-rating'));
                    if (starRating <= rating) {
                        star.classList.add('active');
                        star.querySelector('i').classList.remove('fa-star-o');
                        star.querySelector('i').classList.add('fa-star');
                    } else {
                        star.classList.remove('active');
                        star.querySelector('i').classList.remove('fa-star');
                        star.querySelector('i').classList.add('fa-star-o');
                    }
                });
            }

            // Validation du formulaire
            document.getElementById('testimonialForm').addEventListener('submit', function(e) {
                const rating = parseInt(ratingInput.value);
                const comment = document.getElementById('comment').value.trim();

                if (rating < 1 || rating > 5) {
                    e.preventDefault();
                    alert('Veuillez sélectionner une note entre 1 et 5 étoiles.');
                    return;
                }

                if (comment.length < 10) {
                    e.preventDefault();
                    alert('Votre commentaire doit contenir au moins 10 caractères.');
                    return;
                }
            });
        });
    </script>
<?php include 'footer.php'; ?>