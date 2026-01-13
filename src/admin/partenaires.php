<?php include 'includes/nav_sidebar.php'; ?>

    <style>
        #container{
            margin-top:70px;
            margin-left: 280px;
            position: fixed;
            scrol
        }

 
        .partenaire-photo {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        .photo-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
        }
        .status-badge {
            font-size: 0.75em;
        }
        .table-actions {
            white-space: nowrap;
        }
        .search-box {
            max-width: 300px;
        }
        .filters-card {
            background-color: #f8f9fa;
        }
    </style>

<div id="container" class="container containere overflow-x-hidden">
    <div class="row" style ="width:100%">
        <div class="col-md-12 col-lg-12 partenaires-content">
            <!-- En-tête -->
            <div class="row mb-4">
                <div class="col">
                    <h1 class="h3 mb-0">
                        <i class="fas fa-handshake me-2 text-primary"></i>
                        Gestion des Partenaires
                    </h1>
                    <p class="text-muted">Gérez vos partenaires commerciaux</p>
                </div>
            </div>

            <!-- Barre d'outils et filtres -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card filters-card">
                        <div class="card-body py-3">
                            <div class="row g-3 align-items-center">
                                <div class="col-md-4">
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="searchInput" placeholder="Rechercher...">
                                        <button class="btn btn-outline-secondary" type="button" id="searchBtn">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select" id="statusFilter">
                                        <option value="">Tous les statuts</option>
                                        <option value="1">Actifs</option>
                                        <option value="0">Inactifs</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select" id="featuredFilter">
                                        <option value="">Tous</option>
                                        <option value="1">En avant</option>
                                        <option value="0">Normaux</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button class="btn btn-outline-secondary w-100" id="resetFilters">
                                        <i class="fas fa-refresh"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <button class="btn btn-primary" id="addPartenaireBtn">
                        <i class="fas fa-plus me-2"></i>Nouveau Partenaire
                    </button>
                </div>
            </div>

            <!-- Tableau des partenaires -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-hover" id="partenairesTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Photo</th>
                                    <th>Nom</th>
                                    <th>Adresse</th>
                                    <th>Email</th>
                                    <th>Contact</th>
                                    <th>Statut</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="partenairesBody">
                                <!-- Les données seront chargées ici -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <nav aria-label="Pagination" id="paginationNav">
                        <ul class="pagination justify-content-center" id="pagination">
                            <!-- La pagination sera générée ici -->
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>
    <!-- Modal pour ajouter/modifier -->
    <div class="modal fade" id="partenaireModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Ajouter un Partenaire</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="partenaireForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" id="partenaireId" name="id">
                        <input type="hidden" id="csrf_token" name="csrf_token" value="<?= Security::generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="nom" class="form-label">Nom *</label>
                                    <input type="text" class="form-control" id="nom" name="nom" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="adresse" class="form-label">Adresse</label>
                                    <textarea class="form-control" id="adresse" name="adresse" rows="3"></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="mail" class="form-label">Email</label>
                                            <input type="email" class="form-control" id="mail" name="mail">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="contact" class="form-label">Contact</label>
                                            <input type="text" class="form-control" id="contact" name="contact">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="est_actif" name="est_actif" checked>
                                            <label class="form-check-label" for="est_actif">Partenaire actif</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="est_en_avant" name="est_en_avant">
                                            <label class="form-check-label" for="est_en_avant">Mettre en avant</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="photo" class="form-label">Photo</label>
                                    <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                                    <div class="form-text">Formats: JPG, PNG, GIF, WebP (max 2MB)</div>
                                </div>
                                
                                <div class="mb-3" id="currentPhotoContainer" style="display: none;">
                                    <label class="form-label">Photo actuelle</label>
                                    <div>
                                        <img id="currentPhoto" class="photo-preview img-thumbnail mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="delete_photo" name="delete_photo">
                                            <label class="form-check-label" for="delete_photo">Supprimer la photo</label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div id="photoPreview" class="mb-3" style="display: none;">
                                    <label class="form-label">Aperçu</label>
                                    <div>
                                        <img id="previewImage" class="photo-preview img-thumbnail">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary" id="saveBtn">
                            <i class="fas fa-save me-2"></i>Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap & jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="partenaires.js"></script>
    <script>
        class GestionPartenaires {
            constructor() {
                this.apiUrl = 'api/partenaires.php';
                this.currentPage = 1;
                this.limit = 10;
                this.searchTerm = '';
                this.statusFilter = '';
                this.featuredFilter = '';
                this.init();
            }

            init() {
                this.loadPartenaires();
                this.setupEventListeners();
            }

            setupEventListeners() {
                // Recherche
                $('#searchInput').on('input', debounce(() => {
                    this.searchTerm = $('#searchInput').val();
                    this.currentPage = 1;
                    this.loadPartenaires();
                }, 500));

                $('#searchBtn').on('click', () => {
                    this.searchTerm = $('#searchInput').val();
                    this.currentPage = 1;
                    this.loadPartenaires();
                });

                // Filtres
                $('#statusFilter, #featuredFilter').on('change', () => {
                    this.statusFilter = $('#statusFilter').val();
                    this.featuredFilter = $('#featuredFilter').val();
                    this.currentPage = 1;
                    this.loadPartenaires();
                });

                $('#resetFilters').on('click', () => {
                    $('#searchInput').val('');
                    $('#statusFilter').val('');
                    $('#featuredFilter').val('');
                    this.searchTerm = '';
                    this.statusFilter = '';
                    this.featuredFilter = '';
                    this.currentPage = 1;
                    this.loadPartenaires();
                });

                // Bouton d'ajout
                $('#addPartenaireBtn').on('click', () => {
                    this.openModal();
                });

                // Gestion de la photo
                $('#photo').on('change', (e) => {
                    this.previewImage(e.target.files[0]);
                });

                // Formulaire
                $('#partenaireForm').on('submit', (e) => {
                    e.preventDefault();
                    this.savePartenaire();
                });
            }

            async loadPartenaires() {
                try {
                    const params = new URLSearchParams({
                        action: 'list',
                        page: this.currentPage,
                        limit: this.limit,
                        search: this.searchTerm,
                        status: this.statusFilter,
                        featured: this.featuredFilter
                    });

                    const response = await fetch(`${this.apiUrl}?${params}`);
                    const data = await response.json();
                    
                    if (data.success) {
                        this.displayPartenaires(data.data);
                        this.displayPagination(data.pagination);
                    } else {
                        this.showAlert(data.message, 'danger');
                    }
                } catch (error) {
                    console.error('Erreur:', error);
                    this.showAlert('Erreur lors du chargement des partenaires', 'danger');
                }
            }

            displayPartenaires(partenaires) {
                const tbody = $('#partenairesBody');
                
                if (partenaires.length === 0) {
                    tbody.html(`
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Aucun partenaire trouvé</p>
                            </td>
                        </tr>
                    `);
                    return;
                }

                tbody.html(partenaires.map(partenaire => `
                    <tr>
                        <td>
                            ${partenaire.photo ? 
                                `<img src="../uploads/partenaires/${partenaire.photo}" alt="${partenaire.nom}" class="partenaire-photo" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjYwIiBoZWlnaHQ9IjYwIiBmaWxsPSIjZGRkIi8+CjxwYXRoIGQ9Ik0zMCAzNUMzMy44NjYgMzUgMzcgMzEuODY2IDM3IDI4QzM3IDI0LjEzNCAzMy44NjYgMjEgMzAgMjFDMjYuMTM0IDIxIDIzIDI0LjEzNCAyMyAyOEMyMyAzMS44NjYgMjYuMTM0IDM1IDMwIDM1Wk0zMCAzN0MyNS4wMjkgMzcgMTkgNDAgMTkgNDVWNDdINDFWNDVDNDEgNDAgMzQuOTcxIDM3IDMwIDM3WiIgZmlsbD0id2hpdGUiLz4KPC9zdmc+'">` : 
                                `<div class="partenaire-photo bg-light d-flex align-items-center justify-content-center">
                                    <i class="fas fa-building text-muted"></i>
                                </div>`
                            }
                        </td>
                        <td>
                            <strong>${partenaire.nom}</strong>
                            ${partenaire.est_en_avant ? '<i class="fas fa-star text-warning ms-1" title="Mis en avant"></i>' : ''}
                        </td>
                        <td>${partenaire.adresse || '<span class="text-muted">-</span>'}</td>
                        <td>${partenaire.mail || '<span class="text-muted">-</span>'}</td>
                        <td>${partenaire.contact || '<span class="text-muted">-</span>'}</td>
                        <td>
                            <span class="badge ${partenaire.est_actif ? 'bg-success' : 'bg-secondary'} status-badge">
                                ${partenaire.est_actif ? 'Actif' : 'Inactif'}
                            </span>
                        </td>
                        <td class="text-end table-actions">
                            <button class="btn btn-sm btn-outline-primary" onclick="gestionPartenaires.editPartenaire(${partenaire.id})" title="Modifier">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm ${partenaire.est_actif ? 'btn-outline-warning' : 'btn-outline-success'}" 
                                    onclick="gestionPartenaires.toggleActif(${partenaire.id}, ${partenaire.est_actif})" 
                                    title="${partenaire.est_actif ? 'Désactiver' : 'Activer'}">
                                <i class="fas ${partenaire.est_actif ? 'fa-pause' : 'fa-play'}"></i>
                            </button>
                            <button class="btn btn-sm ${partenaire.est_en_avant ? 'btn-warning' : 'btn-outline-warning'}" 
                                    onclick="gestionPartenaires.toggleEnAvant(${partenaire.id}, ${partenaire.est_en_avant})" 
                                    title="${partenaire.est_en_avant ? 'Retirer des partenaires en avant' : 'Mettre en avant'}">
                                <i class="fas fa-star"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="gestionPartenaires.deletePartenaire(${partenaire.id})" title="Supprimer">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `).join(''));
            }

            displayPagination(pagination) {
                const paginationEl = $('#pagination');
                const { currentPage, totalPages } = pagination;

                let html = '';

                // Bouton précédent
                html += `
                    <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                        <a class="page-link" href="#" onclick="gestionPartenaires.changePage(${currentPage - 1})">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                `;

                // Pages
                for (let i = 1; i <= totalPages; i++) {
                    if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                        html += `
                            <li class="page-item ${i === currentPage ? 'active' : ''}">
                                <a class="page-link" href="#" onclick="gestionPartenaires.changePage(${i})">${i}</a>
                            </li>
                        `;
                    } else if (i === currentPage - 2 || i === currentPage + 2) {
                        html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                }

                // Bouton suivant
                html += `
                    <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                        <a class="page-link" href="#" onclick="gestionPartenaires.changePage(${currentPage + 1})">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                `;

                paginationEl.html(html);
            }

            changePage(page) {
                this.currentPage = page;
                this.loadPartenaires();
            }

            openModal(partenaire = null) {
                const modal = $('#partenaireModal');
                const title = $('#modalTitle');
                const form = $('#partenaireForm')[0];

                // Reset du formulaire
                form.reset();
                $('#currentPhotoContainer').hide();
                $('#photoPreview').hide();
                $('#delete_photo').prop('checked', false);

                if (partenaire) {
                    title.text('Modifier le Partenaire');
                    $('#partenaireId').val(partenaire.id);
                    $('#nom').val(partenaire.nom);
                    $('#adresse').val(partenaire.adresse || '');
                    $('#mail').val(partenaire.mail || '');
                    $('#contact').val(partenaire.contact || '');
                    $('#est_actif').prop('checked', partenaire.est_actif);
                    $('#est_en_avant').prop('checked', partenaire.est_en_avant);

                    // Gestion de la photo actuelle
                    if (partenaire.photo) {
                        $('#currentPhoto').attr('src', `../../uploads/partenaires/${partenaire.photo}`);
                        $('#currentPhotoContainer').show();
                    }
                } else {
                    title.text('Ajouter un Partenaire');
                    $('#partenaireId').val('');
                }

                modal.modal('show');
            }

            previewImage(file) {
                const preview = $('#photoPreview');
                const previewImage = $('#previewImage');

                if (file) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        previewImage.attr('src', e.target.result);
                        preview.show();
                    };
                    reader.readAsDataURL(file);
                } else {
                    preview.hide();
                }
            }

            async savePartenaire() {
                const formData = new FormData($('#partenaireForm')[0]);
                const id = $('#partenaireId').val();
                const action = id ? 'update' : 'create';

                // Afficher le loader
                $('#saveBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Enregistrement...');

                try {
                    const response = await fetch(`${this.apiUrl}?action=${action}`, {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        this.showAlert(result.message, 'success');
                        $('#partenaireModal').modal('hide');
                        this.loadPartenaires();
                    } else {
                        throw new Error(result.message);
                    }
                } catch (error) {
                    console.error('Erreur:', error);
                    this.showAlert(error.message, 'danger');
                } finally {
                    $('#saveBtn').prop('disabled', false).html('<i class="fas fa-save me-2"></i>Enregistrer');
                }
            }

            async editPartenaire(id) {
                try {
                    const response = await fetch(`${this.apiUrl}?action=get&id=${id}`);
                    const result = await response.json();

                    if (result.success) {
                        this.openModal(result.data);
                    } else {
                        throw new Error(result.message);
                    }
                } catch (error) {
                    console.error('Erreur:', error);
                    this.showAlert('Erreur lors du chargement du partenaire', 'danger');
                }
            }

            async toggleActif(id, currentState) {
                if (!confirm(`Voulez-vous vraiment ${currentState ? 'désactiver' : 'activer'} ce partenaire ?`)) {
                    return;
                }

                try {
                    const response = await fetch(this.apiUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'toggle_actif',
                            id: id,
                            est_actif: currentState ? 0 : 1,
                            csrf_token: $('#csrf_token').val()
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        this.showAlert(result.message, 'success');
                        this.loadPartenaires();
                    } else {
                        throw new Error(result.message);
                    }
                } catch (error) {
                    console.error('Erreur:', error);
                    this.showAlert(error.message, 'danger');
                }
            }

            async toggleEnAvant(id, currentState) {
                if (!confirm(`Voulez-vous vraiment ${currentState ? 'retirer ce partenaire des' : 'mettre ce partenaire en'} avant ?`)) {
                    return;
                }

                try {
                    const response = await fetch(this.apiUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'toggle_en_avant',
                            id: id,
                            est_en_avant: currentState ? 0 : 1,
                            csrf_token: $('#csrf_token').val()
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        this.showAlert(result.message, 'success');
                        this.loadPartenaires();
                    } else {
                        throw new Error(result.message);
                    }
                } catch (error) {
                    console.error('Erreur:', error);
                    this.showAlert(error.message, 'danger');
                }
            }

            async deletePartenaire(id) {
                if (!confirm('Voulez-vous vraiment supprimer ce partenaire ? Cette action est irréversible.')) {
                    return;
                }

                try {
                    const response = await fetch(this.apiUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'delete',
                            id: id,
                            csrf_token: $('#csrf_token').val()
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        this.showAlert(result.message, 'success');
                        this.loadPartenaires();
                    } else {
                        throw new Error(result.message);
                    }
                } catch (error) {
                    console.error('Erreur:', error);
                    this.showAlert(error.message, 'danger');
                }
            }

            showAlert(message, type) {
                const alert = $(`
                    <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `);
                
                $('.container-fluid').prepend(alert);
                
                setTimeout(() => {
                    alert.alert('close');
                }, 5000);
            }
        }

        // Fonction debounce pour la recherche
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Initialisation
        const gestionPartenaires = new GestionPartenaires();
    </script>
<?php include 'includes/footer.php'; ?>