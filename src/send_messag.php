<?php
// La barre de navigation
include 'include/header.php';
require_once 'config/mailer_adapeter_contact.php'; // Inclure le mailer

// Traitement du formulaire côté serveur
$messageSent = false;
$errorMessage = '';
$formData = []; // Pour stocker temporairement les données

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = htmlspecialchars(trim($_POST['name']));
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $message = htmlspecialchars(trim($_POST['message']));
    
    // Stocker les données pour pré-remplissage en cas d'erreur
    $formData = [
        'name' => $name,
        'email' => $email,
        'message' => $message
    ];
    
    // Validation
    if (empty($name) || empty($email) || empty($message)) {
        $errorMessage = "Veuillez remplir tous les champs obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Veuillez entrer une adresse email valide.";
    } else {
        // Envoyer l'email en utilisant la nouvelle méthode sendContact
        if (Mailer::sendContact($name, $email, $message)) {
            $messageSent = true;
            // Réinitialiser les données du formulaire après envoi réussi
            $formData = [
                'name' => '',
                'email' => '',
                'message' => ''
            ];
        } else {
            $errorMessage = "Une erreur s'est produite lors de l'envoi du message. Veuillez réessayer.";
        }
    }
}
?>

<!-- Styles supplémentaires pour améliorer l'apparence -->
<style>
    /* Styles pour les liens */
    a[href^="mailto:"] {
        color: #0d6efd;
        transition: all 0.2s;
    }
    
    a[href^="mailto:"]:hover {
        color: #0a58ca;
        text-decoration: underline;
        transform: translateY(-1px);
    }
    
    /* Animation pour le message de feedback */
    .fade-in {
        animation: fadeIn 0.5s ease-in-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    /* Amélioration de l'apparence des icônes réseaux sociaux */
    .fa-2x {
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .fa-2x:hover {
        transform: translateY(-3px);
    }
    
    /* Style pour le bouton d'envoi */
    .btn-primary {
        padding: 12px 0;
        font-weight: 600;
        transition: all 0.3s;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3);
    }
    
    /* Style pour les champs du formulaire */
    .form-control {
        border: 1px solid #dee2e6;
        padding: 12px 15px;
        transition: all 0.3s;
    }
    
    .form-control:focus {
        border-color: #86b7fe;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        transform: translateY(-1px);
    }
    
    /* Style pour l'alerte de destination */
    .alert-light.border {
        border-color: #0d6efd !important;
    }
    
    /* Animation de réinitialisation */
    @keyframes resetForm {
        0% { opacity: 1; }
        50% { opacity: 0.5; }
        100% { opacity: 1; }
    }
    
    .form-reset-animation {
        animation: resetForm 0.5s ease-in-out;
    }
    
    /* Amélioration responsive */
    @media (max-width: 768px) {
        .card-body {
            padding: 2rem !important;
        }
        
        .d-flex.gap-3 {
            justify-content: center;
            flex-wrap: wrap;
        }
    }
</style>

<section id="contact" class="py-5">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto text-center mb-5">
                <h2 class="display-5 fw-bold">Contactez-nous</h2>
                <p class="lead">Nous sommes là pour vous accueillir</p>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <!-- Messages de feedback -->
                <?php if ($messageSent): ?>
                    <div class="alert alert-success fade-in text-center" role="alert" id="successMessage">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Message envoyé avec succès!</strong> Vous aurez une réponse dans le plus bref délais!! Merci.
                        <p class="mb-0 mt-2 small">
                            <i class="fas fa-info-circle me-1"></i>
                            Une copie de confirmation vous a été envoyée à <strong><?php echo htmlspecialchars($email); ?></strong>
                        </p>
                    </div>
                    
                    <script>
                        // Masquer le message après 5 secondes
                        setTimeout(function() {
                            const successMessage = document.getElementById('successMessage');
                            if (successMessage) {
                                successMessage.style.transition = 'opacity 0.5s';
                                successMessage.style.opacity = '0';
                                setTimeout(() => successMessage.style.display = 'none', 500);
                            }
                        }, 5000);
                    </script>
                <?php elseif ($errorMessage): ?>
                    <div class="alert alert-danger fade-in text-center" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $errorMessage; ?>
                    </div>
                <?php endif; ?>
                
                <div class="card border-0 shadow">
                    <div class="card-body p-5">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Informations</h5>
                                <p><i class="fas fa-map-marker-alt text-primary me-2"></i>C/Les Volcans, 75001 Goma</p>
                                <p><i class="fas fa-phone text-primary me-2"></i>+243 973 900 115</p>
                                <p><i class="fas fa-envelope text-primary me-2"></i><a href="mailto:contact@legourmet.fr" class="text-decoration-none fw-bold">contact@legourmet.fr</a></p>
                                <p><i class="fas fa-clock text-primary me-2"></i>Lun-Dim: 12h-14h30 / 19h-23h</p>
                                
                                <!-- Destination clairement indiquée -->
                                <div class="alert alert-light border mt-4">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-paper-plane text-primary fa-lg me-3"></i>
                                        <div>
                                            <small class="text-muted d-block">Vos messages seront envoyés à:</small>
                                            <strong>contact@legourmet.fr</strong>
                                            <small class="text-muted d-block mt-1">et une copie vous sera envoyée</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Réseaux sociaux ajoutés -->
                                <div class="mt-4">
                                    <h6 class="mb-3">Suivez-nous sur les réseaux</h6>
                                    <div class="d-flex gap-3">
                                        <a href="https://web.facebook.com/shabani.wakengela" class="text-decoration-none" title="Facebook" target="_blank">
                                            <i class="fab fa-facebook fa-2x" style="color: #1877F2;"></i>
                                        </a>
                                        <a href="https://www.instagram.com/martin_shabani1/" class="text-decoration-none" title="Instagram" target="_blank">
                                            <i class="fab fa-instagram fa-2x" style="color: #E4405F;"></i>
                                        </a>
                                        <a href="https://x.com/MartinShabani7" class="text-decoration-none" title="Twitter" target="_blank">
                                            <i class="fab fa-twitter fa-2x" style="color: #1DA1F2;"></i>
                                        </a>
                                        <a href="https://www.linkedin.com/in/martin-shabani-187185239/?skipRedirect=true" class="text-decoration-none" title="LinkedIn" target="_blank">
                                            <i class="fab fa-linkedin fa-2x" style="color: #0A66C2;"></i>
                                        </a>
                                        <a href="https://www.tiktok.com/@martin.shabani" class="text-decoration-none" title="TikTok" target="_blank">
                                            <i class="fab fa-tiktok fa-2x" style="color: #000000;"></i>
                                        </a>
                                        <a href="https://www.youtube.com/@martinshabani6812" class="text-decoration-none" title="YouTube" target="_blank">
                                            <i class="fab fa-youtube fa-2x" style="color: #FF0000;"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h5>Envoyez votre message</h5>
                                <form method="POST" action="" id="contactForm">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Nom complet *</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               placeholder="Votre nom" required 
                                               value="<?php echo htmlspecialchars($formData['name'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email *</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               placeholder="votre@email.com" required
                                               value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="message" class="form-label">Message *</label>
                                        <textarea class="form-control" id="message" name="message" rows="4" 
                                                  placeholder="Votre message..." required><?php echo htmlspecialchars($formData['message'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                            <i class="fas fa-paper-plane me-2"></i>Envoyer le message
                                        </button>
                                    </div>
                                    <p class="text-muted small mt-2">
                                        <i class="fas fa-shield-alt me-1"></i>
                                        Vos données sont traitées en toute confidentialité.
                                    </p>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Inclusion de FontAwesome pour les icônes -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- Script pour les effets interactifs -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const contactForm = document.getElementById('contactForm');
        const submitBtn = document.getElementById('submitBtn');
        
        <?php if ($messageSent): ?>
        // Animation de réinitialisation après envoi réussi
        const formInputs = contactForm.querySelectorAll('input, textarea');
        formInputs.forEach(input => {
            input.classList.add('form-reset-animation');
            setTimeout(() => {
                input.classList.remove('form-reset-animation');
            }, 500);
        });
        <?php endif; ?>
        
        // Ajout de couleurs aux icônes des réseaux sociaux au survol
        const socialIcons = document.querySelectorAll('.fa-2x');
        socialIcons.forEach(icon => {
            const originalColor = icon.style.color;
            
            icon.addEventListener('mouseenter', function() {
                if (this.classList.contains('fa-facebook')) {
                    this.style.color = '#1877F2';
                } else if (this.classList.contains('fa-instagram')) {
                    this.style.color = '#E4405F';
                } else if (this.classList.contains('fa-twitter')) {
                    this.style.color = '#1DA1F2';
                } else if (this.classList.contains('fa-linkedin')) {
                    this.style.color = '#0A66C2';
                } else if (this.classList.contains('fa-tiktok')) {
                    this.style.color = '#000000';
                } else if (this.classList.contains('fa-youtube')) {
                    this.style.color = '#FF0000';
                }
            });
            
            icon.addEventListener('mouseleave', function() {
                // Retour à la couleur d'origine
                this.style.color = originalColor || '#212529';
            });
        });
        
        // Optionnel : Confirmation visuelle avant envoi
        contactForm.addEventListener('submit', function(e) {
            // Vous pouvez ajouter une validation JS supplémentaire ici
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Envoi en cours...';
            submitBtn.disabled = true;
            
            // Le formulaire continuera à s'envoyer normalement
            // La réinitialisation sera gérée par PHP
        });
    });
</script>