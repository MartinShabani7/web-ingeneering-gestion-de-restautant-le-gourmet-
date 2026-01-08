/**
 * Scripts JavaScript pour l'application de gestion de restaurant
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Initialisation des composants
    initSmoothScrolling();
    initFormValidation();
    initTooltips();
    initDataTables();
    initCharts();
    
    // Gestion des notifications
    initNotifications();
    
    // Gestion des modals
    initModals();
    
    // Gestion des uploads de fichiers
    initFileUploads();
});

/**
 * Navigation fluide
 */
function initSmoothScrolling() {
    const navLinks = document.querySelectorAll('a[href^="#"]');
    
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);
            
            if (targetElement) {
                const offsetTop = targetElement.offsetTop - 80; // Compenser la navbar fixe
                
                window.scrollTo({
                    top: offsetTop,
                    behavior: 'smooth'
                });
            }
        });
    });
}

/**
 * Validation des formulaires côté client
 */
function initFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            form.classList.add('was-validated');
        });
    });
    
    // Validation en temps réel
    const inputs = document.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            validateField(this);
        });
        
        input.addEventListener('input', function() {
            if (this.classList.contains('is-invalid')) {
                validateField(this);
            }
        });
    });
}

/**
 * Validation d'un champ individuel
 */
function validateField(field) {
    const value = field.value.trim();
    const type = field.type;
    const required = field.hasAttribute('required');
    
    let isValid = true;
    let message = '';
    
    // Validation des champs requis
    if (required && !value) {
        isValid = false;
        message = 'Ce champ est obligatoire';
    }
    
    // Validation spécifique par type
    if (value && type === 'email') {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            isValid = false;
            message = 'Format d\'email invalide';
        }
    }
    
    if (value && type === 'password') {
        if (value.length < 8) {
            isValid = false;
            message = 'Le mot de passe doit contenir au moins 8 caractères';
        }
    }
    
    if (value && field.name === 'phone') {
        const phoneRegex = /^[0-9+\-\s()]{10,}$/;
        if (!phoneRegex.test(value)) {
            isValid = false;
            message = 'Format de téléphone invalide';
        }
    }
    
    // Application du style de validation
    if (isValid) {
        field.classList.remove('is-invalid');
        field.classList.add('is-valid');
    } else {
        field.classList.remove('is-valid');
        field.classList.add('is-invalid');
    }
    
    // Mise à jour du message d'erreur
    const feedback = field.parentNode.querySelector('.invalid-feedback');
    if (feedback) {
        feedback.textContent = message;
    }
}

/**
 * Initialisation des tooltips Bootstrap
 */
function initTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Initialisation des DataTables
 */
function initDataTables() {
    const tables = document.querySelectorAll('.data-table');
    
    tables.forEach(table => {
        if (typeof DataTable !== 'undefined') {
            new DataTable(table, {
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/fr-FR.json'
                },
                responsive: true,
                pageLength: 25,
                order: [[0, 'desc']],
                columnDefs: [
                    { orderable: false, targets: -1 }
                ]
            });
        }
    });
}

/**
 * Initialisation des graphiques
 */
function initCharts() {
    // Graphique des ventes
    const salesChart = document.getElementById('salesChart');
    if (salesChart && typeof Chart !== 'undefined') {
        new Chart(salesChart, {
            type: 'line',
            data: {
                labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun'],
                datasets: [{
                    label: 'Ventes',
                    data: [12000, 19000, 15000, 25000, 22000, 30000],
                    borderColor: '#d4af37',
                    backgroundColor: 'rgba(212, 175, 55, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
    
    // Graphique des catégories
    const categoryChart = document.getElementById('categoryChart');
    if (categoryChart && typeof Chart !== 'undefined') {
        new Chart(categoryChart, {
            type: 'doughnut',
            data: {
                labels: ['Entrées', 'Plats', 'Desserts', 'Boissons'],
                datasets: [{
                    data: [30, 40, 20, 10],
                    backgroundColor: [
                        '#d4af37',
                        '#2c3e50',
                        '#e74c3c',
                        '#3498db'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
}

/**
 * Gestion des notifications
 */
function initNotifications() {
    // Auto-hide des alertes après 5 secondes
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(alert => {
        setTimeout(() => {
            if (alert.parentNode) {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.remove();
                }, 500);
            }
        }, 5000);
    });
}

/**
 * Affichage d'une notification
 */
function showNotification(message, type = 'info', duration = 5000) {
    const alertContainer = document.getElementById('alert-container') || createAlertContainer();
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    alertContainer.appendChild(alert);
    
    if (duration > 0) {
        setTimeout(() => {
            if (alert.parentNode) {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.remove();
                }, 500);
            }
        }, duration);
    }
}

/**
 * Création du conteneur d'alertes
 */
function createAlertContainer() {
    const container = document.createElement('div');
    container.id = 'alert-container';
    container.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        max-width: 400px;
    `;
    document.body.appendChild(container);
    return container;
}

/**
 * Initialisation des modals
 */
function initModals() {
    const modals = document.querySelectorAll('.modal');
    
    modals.forEach(modal => {
        modal.addEventListener('show.bs.modal', function() {
            // Reset du formulaire lors de l'ouverture
            const form = this.querySelector('form');
            if (form) {
                form.reset();
                form.classList.remove('was-validated');
            }
        });
    });
}

/**
 * Initialisation des uploads de fichiers
 */
function initFileUploads() {
    const fileInputs = document.querySelectorAll('input[type="file"]');
    
    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                // Validation de la taille
                const maxSize = 2 * 1024 * 1024; // 2MB
                if (file.size > maxSize) {
                    showNotification('Le fichier est trop volumineux (max 2MB)', 'danger');
                    this.value = '';
                    return;
                }
                
                // Validation du type
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    showNotification('Type de fichier non autorisé', 'danger');
                    this.value = '';
                    return;
                }
                
                // Aperçu de l'image
                if (file.type.startsWith('image/')) {
                    showImagePreview(file, this);
                }
            }
        });
    });
}

/**
 * Affichage de l'aperçu d'image
 */
function showImagePreview(file, input) {
    const reader = new FileReader();
    reader.onload = function(e) {
        let preview = input.parentNode.querySelector('.image-preview');
        if (!preview) {
            preview = document.createElement('div');
            preview.className = 'image-preview mt-2';
            input.parentNode.appendChild(preview);
        }
        
        preview.innerHTML = `
            <img src="${e.target.result}" class="img-thumbnail" style="max-width: 200px; max-height: 200px;">
        `;
    };
    reader.readAsDataURL(file);
}

/**
 * Confirmation de suppression
 */
function confirmDelete(message = 'Êtes-vous sûr de vouloir supprimer cet élément ?') {
    return confirm(message);
}

/**
 * Chargement AJAX
 */
function showLoading(element) {
    if (typeof element === 'string') {
        element = document.querySelector(element);
    }
    
    if (element) {
        element.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Chargement...</span></div></div>';
    }
}

/**
 * Requête AJAX générique
 */
function ajaxRequest(url, data, method = 'POST', callback = null) {
    const xhr = new XMLHttpRequest();
    xhr.open(method, url, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (callback) callback(response);
                } catch (e) {
                    if (callback) callback({ success: false, message: 'Erreur de parsing JSON' });
                }
            } else {
                if (callback) callback({ success: false, message: 'Erreur de connexion' });
            }
        }
    };
    
    if (method === 'POST' && data) {
        xhr.send(data);
    } else {
        xhr.send();
    }
}

/**
 * Formatage des montants
 */
function formatCurrency(amount) {
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'EUR'
    }).format(amount);
}

/**
 * Formatage des dates
 */
function formatDate(date, locale = 'fr-FR') {
    return new Intl.DateTimeFormat(locale, {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    }).format(new Date(date));
}

/**
 * Export de données
 */
function exportData(format, data, filename) {
    let content, mimeType, extension;
    
    switch (format) {
        case 'csv':
            content = convertToCSV(data);
            mimeType = 'text/csv';
            extension = 'csv';
            break;
        case 'json':
            content = JSON.stringify(data, null, 2);
            mimeType = 'application/json';
            extension = 'json';
            break;
        default:
            return;
    }
    
    const blob = new Blob([content], { type: mimeType });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${filename}.${extension}`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

/**
 * Conversion en CSV
 */
function convertToCSV(data) {
    if (!data || data.length === 0) return '';
    
    const headers = Object.keys(data[0]);
    const csvContent = [
        headers.join(','),
        ...data.map(row => headers.map(header => `"${row[header] || ''}"`).join(','))
    ].join('\n');
    
    return csvContent;
}

/**
 * Recherche en temps réel
 */
function initLiveSearch(inputSelector, targetSelector) {
    const input = document.querySelector(inputSelector);
    const target = document.querySelector(targetSelector);
    
    if (!input || !target) return;
    
    input.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const items = target.querySelectorAll('[data-search]');
        
        items.forEach(item => {
            const searchableText = item.getAttribute('data-search').toLowerCase();
            if (searchableText.includes(searchTerm)) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    });
}

/**
 * Tri des colonnes
 */
function sortTable(columnIndex, tableId) {
    const table = document.getElementById(tableId);
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    const isAscending = table.getAttribute('data-sort-direction') !== 'asc';
    
    rows.sort((a, b) => {
        const aVal = a.cells[columnIndex].textContent.trim();
        const bVal = b.cells[columnIndex].textContent.trim();
        
        if (isAscending) {
            return aVal.localeCompare(bVal);
        } else {
            return bVal.localeCompare(aVal);
        }
    });
    
    rows.forEach(row => tbody.appendChild(row));
    table.setAttribute('data-sort-direction', isAscending ? 'asc' : 'desc');
}

//section hero
// document.addEventListener('DOMContentLoaded', function() {
//     const carousel = document.getElementById('heroCarousel');
    
//     // Animation d'entrée pour chaque slide
//     carousel.addEventListener('slide.bs.carousel', function(e) {
//         const nextSlide = e.relatedTarget;
//         const content = nextSlide.querySelector('.hero-content');
        
//         // Réinitialiser les animations
//         const title = content.querySelector('.hero-title');
//         const description = content.querySelector('.hero-description');
//         const buttons = content.querySelector('.hero-buttons');
        
//         title.style.animation = 'none';
//         description.style.animation = 'none';
//         buttons.style.animation = 'none';
        
//         // Forcer le recalcul pour réappliquer l'animation
//         setTimeout(() => {
//             title.style.animation = 'fadeInDown 0.8s ease-out';
//             description.style.animation = 'fadeInUp 0.8s ease-out 0.2s both';
//             buttons.style.animation = 'fadeInUp 0.8s ease-out 0.4s both';
//         }, 10);
//     });

//     // Optimisation pour les images de fond sur les grands écrans
//     function optimizeBackgrounds() {
//         const carouselItems = document.querySelectorAll('.carousel-item');
//         const isLargeScreen = window.innerWidth > 768;
        
//         carouselItems.forEach(item => {
//             if (isLargeScreen) {
//                 item.style.backgroundSize = 'cover';
//                 item.style.backgroundPosition = 'center center';
//             } else {
//                 item.style.backgroundSize = 'cover';
//                 item.style.backgroundPosition = 'center center';
//             }
//         });
//     }

//     // Appeler la fonction d'optimisation au chargement et au redimensionnement
//     optimizeBackgrounds();
//     window.addEventListener('resize', optimizeBackgrounds);
// });

    // Script pour améliorer le carrousel
    document.addEventListener('DOMContentLoaded', function() {
        const carousel = document.getElementById('heroCarousel');
        
        // Animation d'entrée pour chaque slide
        carousel.addEventListener('slide.bs.carousel', function(e) {
            const nextSlide = e.relatedTarget;
            const content = nextSlide.querySelector('.hero-content');
            
            // Réinitialiser les animations
            const title = content.querySelector('.hero-title');
            const description = content.querySelector('.hero-description');
            const buttons = content.querySelector('.hero-buttons');
            
            title.style.animation = 'none';
            description.style.animation = 'none';
            buttons.style.animation = 'none';
            
            // Forcer le recalcul pour réappliquer l'animation
            setTimeout(() => {
                title.style.animation = 'fadeInDown 0.8s ease-out';
                description.style.animation = 'fadeInUp 0.8s ease-out 0.2s both';
                buttons.style.animation = 'fadeInUp 0.8s ease-out 0.4s both';
            }, 10);
        });

        // Optimisation intelligente des images de fond
        function optimizeBackgrounds() {
            const carouselItems = document.querySelectorAll('.carousel-item');
            const screenWidth = window.innerWidth;
            const screenHeight = window.innerHeight;
            const screenRatio = screenWidth / screenHeight;
            
            carouselItems.forEach(item => {
                // Pour mobile (écrans < 768px)
                if (screenWidth < 768) {
                    item.style.backgroundSize = 'cover'; // ou 'contain'
                    item.style.backgroundPosition = 'center center';
                }
                // Pour les écrans très larges (ratio > 1.8)
                else if (screenRatio > 1.8) {
                    item.style.backgroundSize = 'cover';
                    item.style.backgroundPosition = 'center center';
                } 
                // Pour les écrans normaux
                else {
                    item.style.backgroundSize = 'contain';
                    item.style.backgroundPosition = 'center center';
                }
            });
        }

        // Appeler la fonction d'optimisation au chargement et au redimensionnement
        optimizeBackgrounds();
        window.addEventListener('resize', optimizeBackgrounds);
    });


// section partenaires
function afficherPartenaires(partenaires) {
    const container = document.getElementById('partnersContainer');
    const loadingState = document.getElementById('loadingState');
    const errorState = document.getElementById('errorState');
    const emptyState = document.getElementById('emptyState');
    
    // Cacher tous les états
    loadingState.style.display = 'none';
    errorState.style.display = 'none';
    emptyState.style.display = 'none';
    
    // Vider le conteneur
    container.innerHTML = '';
    
    // Vérifier s'il y a des partenaires
    if (partenaires.length === 0) {
        emptyState.style.display = 'block';
        return;
    }
    
    // Dupliquer les partenaires pour créer un effet de défilement infini
    const partenairesDupliques = [...partenaires, ...partenaires];
    
    partenairesDupliques.forEach(partenaire => {
        const partnerItem = document.createElement('div');
        partnerItem.className = 'partner-item';
        
        // Ajouter une classe spéciale pour les partenaires en avant
        if (partenaire.est_en_avant) {
            partnerItem.classList.add('featured-partner');
        }
        
        const img = document.createElement('img');
        img.src = partenaire.logo_url;
        img.alt = partenaire.nom || 'Logo partenaire';
        img.loading = 'lazy';
        
        // Essayer différentes méthodes pour un meilleur affichage
        setTimeout(() => {
            // Si l'image est trop petite, appliquer une classe alternative
            if (img.naturalWidth < 150 || img.naturalHeight < 80) {
                img.classList.add('alternative');
            }
        }, 100);
        
        // Gestion des images cassées
        img.onerror = function() {
            console.error('Image non chargée:', partenaire.logo_url);
            this.src = 'https://via.placeholder.com/190x110/007bff/ffffff?text=' + encodeURIComponent(partenaire.nom || 'Logo');
            this.alt = 'Logo non disponible';
        };
        
        // Debug: log pour vérifier les URLs
        console.log('Chargement image:', partenaire.nom, partenaire.logo_url);
        
        partnerItem.appendChild(img);
        container.appendChild(partnerItem);
    });
}

// section témoignage
document.addEventListener('DOMContentLoaded', function() {
    const track = document.getElementById('testimonialTrack');
    const indicators = document.querySelectorAll('.carousel-indicator');
    const items = document.querySelectorAll('.testimonial-item');
    
    if (items.length === 0) return;
    
    const itemsPerSlide = window.innerWidth < 768 ? 1 : window.innerWidth < 992 ? 2 : 3;
    const totalSlides = Math.ceil(items.length / itemsPerSlide);
    let currentSlide = 0;
    let autoSlideInterval;

    function updateIndicators() {
        indicators.forEach((indicator, index) => {
            indicator.classList.toggle('active', index === currentSlide);
        });
    }

    function goToSlide(slideIndex) {
        currentSlide = slideIndex;
        const translateX = -currentSlide * 100;
        track.style.transform = `translateX(${translateX}%)`;
        updateIndicators();
    }

    function nextSlide() {
        currentSlide = (currentSlide + 1) % totalSlides;
        goToSlide(currentSlide);
    }

    // Initialisation des indicateurs
    function initIndicators() {
        const indicatorsContainer = document.getElementById('carouselIndicators');
        indicatorsContainer.innerHTML = '';
        
        for (let i = 0; i < totalSlides; i++) {
            const indicator = document.createElement('div');
            indicator.className = `carousel-indicator ${i === 0 ? 'active' : ''}`;
            indicator.setAttribute('data-slide', i);
            indicator.addEventListener('click', () => {
                goToSlide(i);
                resetAutoSlide();
            });
            indicatorsContainer.appendChild(indicator);
        }
    }

    function resetAutoSlide() {
        clearInterval(autoSlideInterval);
        startAutoSlide();
    }

    function startAutoSlide() {
        autoSlideInterval = setInterval(nextSlide, 5000); // 5 secondes
    }

    // Gestion du redimensionnement
    function handleResize() {
        const newItemsPerSlide = window.innerWidth < 768 ? 1 : window.innerWidth < 992 ? 2 : 3;
        const newTotalSlides = Math.ceil(items.length / newItemsPerSlide);
        
        if (newTotalSlides !== totalSlides) {
            currentSlide = 0;
            goToSlide(0);
            initIndicators();
        }
    }

    // Initialisation
    initIndicators();
    startAutoSlide();

    // Événements
    window.addEventListener('resize', handleResize);

    // Pause au survol
    track.addEventListener('mouseenter', () => {
        clearInterval(autoSlideInterval);
    });

    track.addEventListener('mouseleave', () => {
        startAutoSlide();
    });
});

// Fonction pour gérer les erreurs
function gererErreur(error) {
    console.error('Erreur lors du chargement des partenaires:', error);
    
    const loadingState = document.getElementById('loadingState');
    const errorState = document.getElementById('errorState');
    const container = document.getElementById('partnersContainer');
    
    loadingState.style.display = 'none';
    container.innerHTML = '';
    errorState.style.display = 'block';
}

// Charger les partenaires depuis l'API publique
function chargerPartenaires() {
    const loadingState = document.getElementById('loadingState');
    const errorState = document.getElementById('errorState');
    
    // Afficher l'état de chargement
    loadingState.style.display = 'block';
    errorState.style.display = 'none';
    
    fetch('admin/api/partenaires-public.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur réseau: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                console.log('Partenaires chargés depuis l\'API:', data.data);
                afficherPartenaires(data.data);
            } else {
                throw new Error(data.message || 'Erreur lors du chargement des données');
            }
        })
        .catch(error => {
            gererErreur(error);
        });
}

// Appeler la fonction au chargement de la page
document.addEventListener('DOMContentLoaded', chargerPartenaires);

