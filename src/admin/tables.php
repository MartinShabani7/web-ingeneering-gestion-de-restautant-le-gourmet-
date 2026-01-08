<?php
include 'includes/nav_sidebar.php';
require_once '../config/database.php';
require_once '../config/security.php';

if (!Security::isLoggedIn() || !Security::isAdmin()) {
    Security::redirect('../auth/login.php');
}
?>
    <style>
        .table-img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #dee2e6;
        }
        .table-img-placeholder {
            width: 60px;
            height: 60px;
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
        }
        .search-box {
            max-width: 300px;
        }
        .pagination {
            margin-bottom: 0;
        }
        
        /* Styles pour les notifications */
        .notification-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
        }
        
        .notification {
            background: white;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            border-left: 4px solid #28a745;
            transform: translateX(400px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .notification.show {
            transform: translateX(0);
            opacity: 1;
        }
        
        .notification.hide {
            transform: translateX(400px);
            opacity: 0;
        }
        
        .notification.success {
            border-left-color: #28a745;
        }
        
        .notification.error {
            border-left-color: #dc3545;
        }
        
        .notification.warning {
            border-left-color: #ffc107;
        }
        
        .notification.info {
            border-left-color: #17a2b8;
        }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }
        
        .notification.success .notification-icon {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }
        
        .notification.error .notification-icon {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-title {
            font-weight: 600;
            margin-bottom: 4px;
            font-size: 14px;
        }
        
        .notification-message {
            color: #6c757d;
            font-size: 13px;
            margin: 0;
        }
        
        .notification-close {
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: color 0.2s;
        }
        
        .notification-close:hover {
            color: #495057;
        }
        
        /* Animation de chargement */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9998;
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Styles pour le toggle de disponibilité (taille réduite) */
        .availability-toggle {
            width: 45px;
            height: 22px;
            background: #dc3545;
            border-radius: 11px;
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            outline: none;
        }
        
        .availability-toggle.available {
            background: #28a745;
        }
        
        .availability-toggle::before {
            content: '';
            position: absolute;
            width: 18px;
            height: 18px;
            background: white;
            border-radius: 50%;
            top: 2px;
            left: 2px;
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        
        .availability-toggle.available::before {
            left: 25px;
        }
        
        .availability-toggle:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        /* Animation pour les boutons */
        .btn-success {
            transition: all 0.3s ease;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }
        
        .btn-danger {
            transition: all 0.3s ease;
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }
        
        /* Animation pour les lignes du tableau */
        .table tbody tr {
            transition: all 0.3s ease;
        }
        
        .table tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.05);
        }
        
        /* Style pour les badges */
        .badge {
            font-size: 0.75em;
            padding: 0.5em 0.75em;
        }
        
        /* Prévisualisation d'image */
        .image-preview-container {
            display: none;
            margin-top: 10px;
            text-align: center;
        }
        
        .image-preview {
            max-width: 200px;
            max-height: 150px;
            border-radius: 8px;
            border: 2px solid #dee2e6;
            object-fit: cover;
        }
        
        .image-preview-info {
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .file-input-wrapper {
            position: relative;
        }
        
        .file-size-warning {
            color: #dc3545;
            font-size: 12px;
            display: none;
        }
    </style>

    <!-- Container pour les notifications -->
    <div class="notification-container" id="notificationContainer"></div>
    
    <!-- Overlay de chargement -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>


<div id="container" class="container">
    <div class="col-md-12 col-lg-12 tables-content">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h3 mb-0"><i class="fas fa-table me-2 text-primary"></i>Tables</h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tableModal" onclick="openCreate()">
                <i class="fas fa-plus me-2"></i>Nouvelle table
            </button>
        </div>

        <!-- Barre de recherche et filtres -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-center">
                    <div class="col-md-6">
                        <div class="input-group search-box">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="searchInput" placeholder="Rechercher une table..." oninput="handleSearch()">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="availabilityFilter" onchange="fetchList()">
                            <option value="">Toutes les disponibilités</option>
                            <option value="1">Disponible</option>
                            <option value="0">Non disponible</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="capacityFilter" onchange="fetchList()">
                            <option value="">Toutes les capacités</option>
                            <option value="1-2">1-2 personnes</option>
                            <option value="3-4">3-4 personnes</option>
                            <option value="5-6">5-6 personnes</option>
                            <option value="7+">7+ personnes</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div id="listContainer" class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-hover align-middle" id="tablesTable">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Nom</th>
                                <th>Capacité</th>
                                <th>Emplacement</th>
                                <th>Disponible</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <nav aria-label="Pagination">
                    <ul class="pagination justify-content-center" id="pagination">
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

    <!-- Modal pour créer/modifier une table -->
    <div class="modal fade" id="tableModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nouvelle table</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="tableForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                        <input type="hidden" name="id" id="table_id">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nom *</label>
                                <input class="form-control" name="table_name" id="table_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Capacité *</label>
                                <input type="number" min="1" class="form-control" name="capacity" id="capacity" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Emplacement</label>
                                <input class="form-control" name="location" id="location">
                            </div>
                            
                            <!-- Upload d'image avec prévisualisation -->
                            <div class="col-12">
                                <label class="form-label">Image de la table</label>
                                <div class="file-input-wrapper">
                                    <input type="file" class="form-control" name="image" id="image" accept="image/*" onchange="previewImage(this)">
                                    <div class="file-size-warning" id="fileSizeWarning">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        L'image est trop volumineuse (max 1MB)
                                    </div>
                                </div>
                                <div class="form-text">Formats acceptés: JPG, PNG, GIF, WEBP (max 1MB)</div>
                                
                                <!-- Prévisualisation pour nouvelle image -->
                                <div class="image-preview-container" id="newImagePreview">
                                    <img class="image-preview" id="newImagePreviewImg">
                                    <div class="image-preview-info" id="newImageInfo"></div>
                                </div>
                                
                                <!-- Aperçu de l'image actuelle pour l'édition -->
                                <div class="image-preview-container" id="currentImageContainer">
                                    <label class="form-label">Image actuelle</label>
                                    <img id="currentImage" src="" class="image-preview">
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" name="delete_image" id="delete_image">
                                        <label class="form-check-label text-danger" for="delete_image">
                                            <i class="fas fa-trash me-1"></i>Supprimer l'image
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-12 d-flex align-items-center gap-2">
                                <input class="form-check-input" type="checkbox" id="is_available" name="is_available" checked>
                                <label class="form-check-label" for="is_available">Disponible</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Variables globales
        let currentPage = 1;
        let totalPages = 1;
        let searchTerm = '';
        let availabilityFilter = '';
        let capacityFilter = '';
        let searchTimeout;

        const tableBody = document.querySelector('#tablesTable tbody');
        const modalEl = document.getElementById('tableModal');
        const modal = new bootstrap.Modal(modalEl);
        const form = document.getElementById('tableForm');
        const modalTitle = document.getElementById('modalTitle');
        const paginationEl = document.getElementById('pagination');
        const loadingOverlay = document.getElementById('loadingOverlay');

        // Fonction de prévisualisation d'image
        function previewImage(input) {
            const newImagePreview = document.getElementById('newImagePreview');
            const newImagePreviewImg = document.getElementById('newImagePreviewImg');
            const newImageInfo = document.getElementById('newImageInfo');
            const fileSizeWarning = document.getElementById('fileSizeWarning');
            const currentImageContainer = document.getElementById('currentImageContainer');
            
            // Masquer l'image actuelle lors de l'upload d'une nouvelle
            if (currentImageContainer) {
                currentImageContainer.style.display = 'none';
            }
            
            // Masquer l'avertissement de taille
            fileSizeWarning.style.display = 'none';
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const maxSize = 1 * 1024 * 1024; // 1MB
                
                // Vérifier la taille du fichier
                if (file.size > maxSize) {
                    fileSizeWarning.style.display = 'block';
                    input.value = ''; // Effacer le fichier sélectionné
                    newImagePreview.style.display = 'none';
                    return;
                }
                
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    newImagePreviewImg.src = e.target.result;
                    newImagePreview.style.display = 'block';
                    
                    // Afficher les informations du fichier
                    const fileSize = (file.size / 1024).toFixed(2);
                    newImageInfo.textContent = `${file.name} (${fileSize} KB)`;
                }
                
                reader.readAsDataURL(file);
            } else {
                newImagePreview.style.display = 'none';
            }
        }

        // Système de notifications
        function showNotification(type, title, message, duration = 5000) {
            const container = document.getElementById('notificationContainer');
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            
            const icons = {
                success: 'fas fa-check-circle',
                error: 'fas fa-exclamation-circle',
                warning: 'fas fa-exclamation-triangle',
                info: 'fas fa-info-circle'
            };
            
            notification.innerHTML = `
                <div class="notification-icon">
                    <i class="${icons[type]}"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-title">${title}</div>
                    <p class="notification-message">${message}</p>
                </div>
                <button class="notification-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            container.appendChild(notification);
            
            // Animation d'entrée
            setTimeout(() => {
                notification.classList.add('show');
            }, 10);
            
            // Auto-suppression après la durée spécifiée
            if (duration > 0) {
                setTimeout(() => {
                    hideNotification(notification);
                }, duration);
            }
            
            return notification;
        }

        function hideNotification(notification) {
            notification.classList.remove('show');
            notification.classList.add('hide');
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 400);
        }

        // Gestion du chargement
        function showLoading() {
            loadingOverlay.style.display = 'flex';
        }

        function hideLoading() {
            loadingOverlay.style.display = 'none';
        }

        // function fetchList(page = 1) {
        //     showLoading();
        //     currentPage = page;
            
        //     const params = new URLSearchParams({
        //         action: 'list',
        //         page: page,
        //         limit: 10
        //     });
            
        //     if (searchTerm) params.append('search', searchTerm);
        //     if (availabilityFilter) params.append('availability', availabilityFilter);
        //     if (capacityFilter) params.append('capacity', capacityFilter);

        //     fetch(`api/tables.php?${params}`, { 
        //         headers: { 'X-Requested-With': 'XMLHttpRequest' } 
        //     })
        //     .then(r => r.json())
        //     .then(res => {
        //         if (!res.success) throw new Error(res.message || 'Erreur');
        //         renderRows(res.data || []);
        //         renderPagination(res.pagination || {});
        //         hideLoading();
        //     })
        //     .catch(err => {
        //         hideLoading();
        //         showNotification('error', 'Erreur', err.message);
        //     });
        // }
        function fetchList(page = 1) {
            showLoading();
            currentPage = page;
            
            const params = new URLSearchParams({
                action: 'list',
                page: page,
                limit: 10
            });
            
            if (searchTerm) params.append('search', searchTerm);
            if (availabilityFilter) params.append('availability', availabilityFilter);
            if (capacityFilter) params.append('capacity', capacityFilter);

            fetch(`api/tables.php?${params}`, { 
                headers: { 'X-Requested-With': 'XMLHttpRequest' } 
            })
            .then(r => {
                // Lire d'abord comme texte pour debug
                return r.text().then(text => {
                    console.log("Réponse brute:", text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error("Erreur parsing JSON. Texte reçu:", text);
                        throw new Error("Réponse invalide du serveur");
                    }
                });
            })
            .then(res => {
                if (!res.success) throw new Error(res.message || 'Erreur');
                renderRows(res.data || []);
                renderPagination(res.pagination || {});
                hideLoading();
            })
            .catch(err => {
                hideLoading();
                showNotification('error', 'Erreur', err.message);
                console.error("Erreur détaillée:", err);
            });
        }

        function renderRows(rows) {
            tableBody.innerHTML = '';
            if (!rows.length) {
                tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Aucune table trouvée</td></tr>';
                return;
            }
            
            rows.forEach(row => {
                const tr = document.createElement('tr');
                
                // Gestion de l'image
                let imageHtml;
                if (row.image) {
                    imageHtml = `<img src="../uploads/tables/${escapeHtml(row.image)}" class="table-img" alt="Table ${escapeHtml(row.table_name)}">`;
                } else {
                    imageHtml = `<div class="table-img-placeholder"><i class="fas fa-table fa-lg"></i></div>`;
                }
                
                // Bouton toggle pour la disponibilité (taille réduite)
                const isAvailable = !!Number(row.is_available);
                const toggleClass = isAvailable ? 'available' : '';
                const toggleTitle = isAvailable ? 'Désactiver la disponibilité' : 'Activer la disponibilité';
                
                tr.innerHTML = `
                    <td>${imageHtml}</td>
                    <td>
                        <strong>${escapeHtml(row.table_name)}</strong>
                    </td>
                    <td>
                        <span class="badge bg-primary rounded-pill">${row.capacity} pers.</span>
                    </td>
                    <td>${escapeHtml(row.location || 'Non spécifié')}</td>
                    <td>
                        <div class="d-flex justify-content-center">
                            <button type="button" 
                                    class="availability-toggle ${toggleClass}" 
                                    onclick="toggleAvailability(${row.id}, ${isAvailable ? 0 : 1})"
                                    title="${toggleTitle}">
                            </button>
                        </div>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-info me-1" onclick='openEdit(${JSON.stringify(row)})' title="Modifier">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="removeTable(${row.id})" title="Supprimer">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>`;
                tableBody.appendChild(tr);
            });
        }

        // Fonction pour basculer la disponibilité
        function toggleAvailability(tableId, newStatus) {
            showLoading();
            
            const fd = new FormData();
            fd.append('action', 'toggle_availability');
            fd.append('id', tableId);
            fd.append('is_available', newStatus);
            fd.append('csrf_token', document.querySelector('#tableForm [name=csrf_token]').value);
            
            fetch('api/tables.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            })
            .then(r => r.json())
            .then(res => {
                hideLoading();
                if (!res.success) throw new Error(res.message || 'Erreur');
                
                // Mettre à jour l'affichage sans recharger toute la page
                fetchList(currentPage);
                
                const message = newStatus ? 
                    'Table marquée comme disponible' : 
                    'Table marquée comme non disponible';
                showNotification('success', 'Succès', message, 3000);
            })
            .catch(err => {
                hideLoading();
                showNotification('error', 'Erreur', err.message);
            });
        }

        function renderPagination(pagination) {
            paginationEl.innerHTML = '';
            
            if (!pagination || pagination.totalPages <= 1) return;
            
            totalPages = pagination.totalPages;
            
            // Bouton précédent
            const prevLi = document.createElement('li');
            prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
            prevLi.innerHTML = `<a class="page-link" href="#" onclick="fetchList(${currentPage - 1}); return false;">
                <i class="fas fa-chevron-left"></i>
            </a>`;
            paginationEl.appendChild(prevLi);
            
            // Pages
            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(totalPages, currentPage + 2);
            
            for (let i = startPage; i <= endPage; i++) {
                const pageLi = document.createElement('li');
                pageLi.className = `page-item ${i === currentPage ? 'active' : ''}`;
                pageLi.innerHTML = `<a class="page-link" href="#" onclick="fetchList(${i}); return false;">${i}</a>`;
                paginationEl.appendChild(pageLi);
            }
            
            // Bouton suivant
            const nextLi = document.createElement('li');
            nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
            nextLi.innerHTML = `<a class="page-link" href="#" onclick="fetchList(${currentPage + 1}); return false;">
                <i class="fas fa-chevron-right"></i>
            </a>`;
            paginationEl.appendChild(nextLi);
        }

        function handleSearch() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                searchTerm = document.getElementById('searchInput').value.trim();
                currentPage = 1;
                fetchList();
            }, 500);
        }

        function openCreate() {
            form.reset();
            form.actionMode = 'create';
            document.getElementById('table_id').value = '';
            modalTitle.textContent = 'Nouvelle table';
            document.getElementById('currentImageContainer').style.display = 'none';
            document.getElementById('newImagePreview').style.display = 'none';
            document.getElementById('delete_image').checked = false;
            document.getElementById('fileSizeWarning').style.display = 'none';
        }

        function openEdit(row) {
            modal.show();
            form.actionMode = 'update';
            modalTitle.textContent = 'Modifier la table';
            document.getElementById('table_id').value = row.id;
            document.getElementById('table_name').value = row.table_name || '';
            document.getElementById('capacity').value = row.capacity || 1;
            document.getElementById('location').value = row.location || '';
            document.getElementById('is_available').checked = !!Number(row.is_available);
            
            // Gestion de l'image actuelle
            const currentImageContainer = document.getElementById('currentImageContainer');
            const currentImage = document.getElementById('currentImage');
            const deleteImageCheckbox = document.getElementById('delete_image');
            const newImagePreview = document.getElementById('newImagePreview');
            
            // Masquer la prévisualisation de nouvelle image
            newImagePreview.style.display = 'none';
            
            if (row.image) {
                currentImage.src = `../uploads/tables/${row.image}`;
                currentImageContainer.style.display = 'block';
                deleteImageCheckbox.checked = false;
            } else {
                currentImageContainer.style.display = 'none';
            }
        }

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            
            // Vérifier la taille du fichier avant envoi
            const imageInput = document.getElementById('image');
            if (imageInput.files[0]) {
                const maxSize = 1 * 1024 * 1024; // 1MB
                if (imageInput.files[0].size > maxSize) {
                    showNotification('error', 'Erreur', 'L\'image est trop volumineuse (max 1MB)');
                    return;
                }
            }
            
            showLoading();
            
            const fd = new FormData(form);
            const action = form.actionMode === 'update' ? 'update' : 'create';
            fd.append('action', action);
            
            fetch('api/tables.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            })
            .then(r => r.json())
            .then(res => {
                hideLoading();
                if (!res.success) throw new Error(res.message || 'Erreur');
                
                modal.hide();
                fetchList(currentPage);
                
                // Message de succès
                const message = action === 'create' ? 
                    'Table créée avec succès' : 
                    'Table modifiée avec succès';
                showNotification('success', 'Succès', message, 3000);
            })
            .catch(err => {
                hideLoading();
                showNotification('error', 'Erreur', err.message);
            });
        });

        function removeTable(id) {
            if (!confirm('Êtes-vous sûr de vouloir supprimer cette table ? Cette action est irréversible.')) return;
            
            showLoading();
            
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);
            fd.append('csrf_token', document.querySelector('#tableForm [name=csrf_token]').value);
            
            fetch('api/tables.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            })
            .then(r => r.json())
            .then(res => {
                hideLoading();
                if (!res.success) throw new Error(res.message || 'Erreur');
                
                fetchList(currentPage);
                showNotification('success', 'Succès', 'Table supprimée avec succès', 3000);
            })
            .catch(err => {
                hideLoading();
                showNotification('error', 'Erreur', err.message);
            });
        }

        function escapeHtml(s) { 
            return (s || '').replace(/[&<>"']/g, c => 
                ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c] || c)
            ); 
        }

        // Initialisation des filtres
        document.getElementById('availabilityFilter').addEventListener('change', function() {
            availabilityFilter = this.value;
            currentPage = 1;
            fetchList();
        });

        document.getElementById('capacityFilter').addEventListener('change', function() {
            capacityFilter = this.value;
            currentPage = 1;
            fetchList();
        });

        // Chargement initial
        fetchList();
    </script>
</body>
</html>