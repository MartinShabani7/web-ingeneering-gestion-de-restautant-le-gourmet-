<?php
include 'includes/nav_sidebar.php';
require_once '../config/database.php';
require_once '../config/security.php';

if (!Security::isLoggedIn() || !Security::isAdmin()) {
    Security::redirect('../auth/login.php');
}

// Charger catégories pour filtres et formulaires
$categories = $pdo->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY sort_order, name")->fetchAll();
?>


    <!-- Container pour les notifications -->
    <div class="notification-container" id="notificationContainer"></div>
    
    <!-- Overlay de chargement
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div> -->


    <style>
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
        
        /* Styles pour les toggles */
        .availability-toggle, .featured-toggle {
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
        
        .availability-toggle.available, .featured-toggle.featured {
            background: #28a745;
        }
        
        .featured-toggle.featured {
            background: #ffc107;
        }
        
        .availability-toggle::before, .featured-toggle::before {
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
        
        .availability-toggle.available::before, .featured-toggle.featured::before {
            left: 25px;
        }
        
        .availability-toggle:disabled, .featured-toggle:disabled {
            opacity: 0.6;
            cursor: not-allowed;
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
        
        /* Animation pour les lignes du tableau */
        .table tbody tr {
            transition: all 0.3s ease;
        }
        
        .table tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.05);
        }
        
        /* Style pour les images dans le tableau */
        .product-img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #dee2e6;
        }
        
        .product-img-placeholder {
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
        
        .pagination {
            margin-bottom: 0;
        }
    </style>
<div id="container" class="container containere overflow-x-hidden">
    <div class="row" style ="width:100%">
        <div class="col-md-12 col-lg-12 products-content">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h3 mb-0"><i class="fas fa-utensils me-2 text-primary"></i>Gestion des produits</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal" onclick="openCreate()">
                    <i class="fas fa-plus me-2"></i>Nouveau produit
                </button>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <!-- Formulaore de recherche -->
                            <!-- <label class="form-label"></label> -->
                            <input type="text" id="search" class="form-control" placeholder=" Recherche par Nom ou description">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Catégorie</label>
                            <select id="filterCategory" class="form-select">
                                <option value="">Toutes</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="onlyActive">
                                <label class="form-check-label" for="onlyActive">Disponibles uniquement</label>
                            </div>
                        </div>
                        <div class="col-md-5 text-end">
                            <div class="btn-group" role="group">
                                
                                <!-- Menu déroulant pour l'export -->
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-download me-1"></i>Exporter
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <a class="dropdown-item" href="#" onclick="exportData('excel')">
                                                <i class="fas fa-file-excel text-success me-2"></i>Excel (.xlsx)
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="#" onclick="exportData('csv')">
                                                <i class="fas fa-file-csv text-info me-2"></i>CSV (.csv)
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="#" onclick="exportData('pdf')">
                                                <i class="fas fa-file-pdf text-danger me-2"></i>PDF (.pdf)
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="#" onclick="exportData('word')">
                                                <i class="fas fa-file-word text-primary me-2"></i>Word (.docx)
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <!-- Bouton d'impression -->
                            <button class="btn btn-primary" onclick="printProducts()" title="Imprimer la liste">
                                <i class="fas fa-print me-1"></i>Imprimer
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div id="listContainer" class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-hover align-middle" id="productsTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Image</th>
                                    <th>Nom</th>
                                    <th>Catégorie</th>
                                    <th class="text-end">Prix</th>
                                    <th>Disponible</th>
                                    <th>Mis en avant</th>
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
    </div>
</div>

    <!-- Modal create/update -->
    <div class="modal fade" id="productModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nouveau produit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="productForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                        <input type="hidden" name="id" id="product_id">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nom *</label>
                                <input type="text" name="name" id="name" class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Prix ($) *</label>
                                <input type="number" step="0.01" min="0" name="price" id="price" class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Catégorie</label>
                                <select name="category_id" id="category_id" class="form-select">
                                    <option value="">—</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea name="description" id="description" class="form-control" rows="3"></textarea>
                            </div>
                            
                            <!-- Upload d'image avec prévisualisation -->
                            <div class="col-12">
                                <label class="form-label">Image</label>
                                <div class="file-input-wrapper">
                                    <input type="file" name="image" id="image" class="form-control" accept="image/*" onchange="previewImage(this)">
                                    <div class="file-size-warning" id="fileSizeWarning">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        L'image est trop volumineuse (max 1MB)
                                    </div>
                                </div>
                                <div class="form-text">JPG/PNG/GIF, max 1MB</div>
                                
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
                            
                            <div class="col-md-3">
                                <label class="form-label">Ordre</label>
                                <input type="number" name="sort_order" id="sort_order" class="form-control" value="0">
                            </div>
                            <div class="col-md-9 d-flex align-items-end gap-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_available" name="is_available" checked>
                                    <label class="form-check-label" for="is_available">Disponible</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured">
                                    <label class="form-check-label" for="is_featured">En avant</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-save me-1"></i>Enregistrer
                        </button>
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
        let categoryFilter = '';
        let onlyActiveFilter = false;
        let searchTimeout;

        const tableBody = document.querySelector('#productsTable tbody');
        const searchInput = document.getElementById('search');
        const filterCategory = document.getElementById('filterCategory');
        const onlyActive = document.getElementById('onlyActive');
        const modalEl = document.getElementById('productModal');
        const modal = new bootstrap.Modal(modalEl);
        const form = document.getElementById('productForm');
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
        //     if (categoryFilter) params.append('category_id', categoryFilter);
        //     if (onlyActiveFilter) params.append('only_active', '1');

        //     fetch('api/products.php?' + params.toString(), { 
        //         headers: { 'X-Requested-With': 'XMLHttpRequest' } 
        //     })
        //     .then(r => r.json())
        //     .then(res => {
        //         if (!res.success) throw new Error(res.message || 'Erreur');
        //         renderRows(res.data.data || []);
        //         renderPagination(res.data.pagination || {});
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
                limit: 10,
                debug: 1 // Ajoute debug pour voir les infos
            });
            
            if (searchTerm) params.append('search', searchTerm);
            if (categoryFilter) params.append('category_id', categoryFilter);
            if (onlyActiveFilter) params.append('only_active', '1');

            const url = 'api/products.php?' + params.toString();
            console.log('🔍 Requête:', url);
            
            fetch(url, { 
                headers: { 
                    'X-Requested-With': 'XMLHttpRequest',
                    'Cache-Control': 'no-cache'
                },
                credentials: 'include' // Important pour les cookies de session
            })
            .then(r => {
                console.log('📊 Réponse HTTP:', {
                    status: r.status,
                    statusText: r.statusText,
                    ok: r.ok
                });
                
                if (!r.ok) {
                    // Si le statut n'est pas 200-299
                    return r.text().then(text => {
                        throw new Error(`HTTP ${r.status}: ${text || r.statusText}`);
                    });
                }
                
                return r.text();
            })
            .then(text => {
                console.log('📄 Réponse texte (premiers 500 chars):', 
                    text.substring(0, 500) + (text.length > 500 ? '...' : ''));
                
                if (!text.trim()) {
                    throw new Error('Réponse vide du serveur');
                }
                
                let res;
                try {
                    res = JSON.parse(text);
                } catch (e) {
                    console.error('❌ JSON invalide. Texte complet:', text);
                    throw new Error('Réponse JSON invalide: ' + e.message);
                }
                
                console.log('✅ JSON parsé:', res);
                
                if (!res.success) {
                    throw new Error(res.message || 'Erreur du serveur');
                }
                
                renderRows(res.data?.data || []);
                renderPagination(res.data?.pagination || {});
                hideLoading();
            })
            .catch(err => {
                hideLoading();
                console.error('🚨 Erreur complète:', err);
                
                let errorMessage = err.message;
                
                // Messages plus conviviaux
                if (err.message.includes('403')) {
                    errorMessage = 'Accès refusé. Veuillez vous reconnecter.';
                    // Rediriger vers la page de login
                    setTimeout(() => {
                        window.location.href = '../auth/login.php?expired=1';
                    }, 2000);
                } else if (err.message.includes('JSON')) {
                    errorMessage = 'Erreur de communication avec le serveur';
                }
                
                showNotification('error', 'Erreur', errorMessage);
                
                // Afficher une ligne vide dans le tableau
                tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">' + 
                                    errorMessage + '</td></tr>';
            });
        }

        function renderRows(rows) {
            console.log('Rows data:', rows);
            tableBody.innerHTML = '';
            if (!rows.length) {
                tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Aucun produit trouvé</td></tr>';
                return;
            }
            
            rows.forEach(row => {
                const tr = document.createElement('tr');
                
                // Gestion de l'image
                let imageHtml;
                if (row.image) {
                    imageHtml = `<img src="../${row.image}" class="product-img" alt="${escapeHtml(row.name)}">`;
                } else {
                    imageHtml = `<div class="product-img-placeholder"><i class="fas fa-image fa-lg"></i></div>`;
                }
                
                // Boutons toggle pour disponibilité et mise en avant
                const isAvailable = !!Number(row.is_available);
                const isFeatured = !!Number(row.is_featured);
                const availabilityToggleClass = isAvailable ? 'available' : '';
                const featuredToggleClass = isFeatured ? 'featured' : '';
                
                tr.innerHTML = `
                    <td>${row.id}</td>
                    <td>${imageHtml}</td>
                    <td>
                        <strong>${escapeHtml(row.name)}</strong>
                        ${row.description ? `<br><small class="text-muted">${escapeHtml(row.description.substring(0, 50))}${row.description.length > 50 ? '...' : ''}</small>` : ''}
                    </td>
                    <td>${escapeHtml(row.category_name || '-')}</td>
                    <td class="text-end fw-bold">${Number(row.price).toFixed(2)}$</td>
                    <td>
                        <div class="d-flex justify-content-center">
                            <button type="button" 
                                    class="availability-toggle ${availabilityToggleClass}" 
                                    onclick="toggleAvailability(${row.id}, ${isAvailable ? 0 : 1})"
                                    title="${isAvailable ? 'Désactiver' : 'Activer'}">
                            </button>
                        </div>
                    </td>
                    <td>
                        <div class="d-flex justify-content-center">
                            <button type="button" 
                                    class="featured-toggle ${featuredToggleClass}" 
                                    onclick="toggleFeatured(${row.id}, ${isFeatured ? 0 : 1})"
                                    title="${isFeatured ? 'Retirer des favoris' : 'Mettre en avant'}">
                            </button>
                        </div>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-info me-1" onclick='openEdit(${JSON.stringify(row)})' title="Modifier">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="removeProduct(${row.id})" title="Supprimer">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>`;
                tableBody.appendChild(tr);
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

        // Fonctions pour basculer les états
        function toggleAvailability(productId, newStatus) {
            showLoading();
            
            const fd = new FormData();
            fd.append('action', 'toggle_availability');
            fd.append('id', productId);
            fd.append('is_available', newStatus);
            fd.append('csrf_token', document.querySelector('#productForm [name=csrf_token]').value);
            
            fetch('api/products.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            })
            .then(r => r.json())
            .then(res => {
                hideLoading();
                if (!res.success) throw new Error(res.message || 'Erreur');
                
                fetchList(currentPage);
                const message = newStatus ? 
                    'Produit marqué comme disponible' : 
                    'Produit marqué comme non disponible';
                showNotification('success', 'Succès', message, 3000);
            })
            .catch(err => {
                hideLoading();
                showNotification('error', 'Erreur', err.message);
            });
        }

        function toggleFeatured(productId, newStatus) {
            showLoading();
            
            const fd = new FormData();
            fd.append('action', 'toggle_featured');
            fd.append('id', productId);
            fd.append('is_featured', newStatus);
            fd.append('csrf_token', document.querySelector('#productForm [name=csrf_token]').value);
            
            fetch('api/products.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            })
            .then(r => r.json())
            .then(res => {
                hideLoading();
                if (!res.success) throw new Error(res.message || 'Erreur');
                
                fetchList(currentPage);
                const message = newStatus ? 
                    'Produit mis en avant' : 
                    'Produit retiré des favoris';
                showNotification('success', 'Succès', message, 3000);
            })
            .catch(err => {
                hideLoading();
                showNotification('error', 'Erreur', err.message);
            });
        }

        function openCreate() {
            form.reset();
            form.actionMode = 'create';
            document.getElementById('product_id').value = '';
            modalTitle.textContent = 'Nouveau produit';
            document.getElementById('currentImageContainer').style.display = 'none';
            document.getElementById('newImagePreview').style.display = 'none';
            document.getElementById('delete_image').checked = false;
            document.getElementById('fileSizeWarning').style.display = 'none';
        }

        function openEdit(row) {
            modal.show();
            form.actionMode = 'update';
            modalTitle.textContent = 'Modifier le produit';
            document.getElementById('product_id').value = row.id;
            document.getElementById('name').value = row.name || '';
            document.getElementById('price').value = row.price || '';
            document.getElementById('category_id').value = row.category_id || '';
            document.getElementById('description').value = row.description || '';
            document.getElementById('sort_order').value = row.sort_order || 0;
            document.getElementById('is_available').checked = !!Number(row.is_available);
            document.getElementById('is_featured').checked = !!Number(row.is_featured);
            
            // Gestion de l'image actuelle
            const currentImageContainer = document.getElementById('currentImageContainer');
            const currentImage = document.getElementById('currentImage');
            const deleteImageCheckbox = document.getElementById('delete_image');
            const newImagePreview = document.getElementById('newImagePreview');
            
            // Masquer la prévisualisation de nouvelle image
            newImagePreview.style.display = 'none';
            
            if (row.image) {
                currentImage.src = `../${row.image}`;
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
            
            fetch('api/products.php', {
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
                    'Produit créé avec succès' : 
                    'Produit modifié avec succès';
                showNotification('success', 'Succès', message, 3000);
            })
            .catch(err => {
                hideLoading();
                showNotification('error', 'Erreur', err.message);
            });
        });

        function removeProduct(id) {
            if (!confirm('Êtes-vous sûr de vouloir supprimer ce produit ? Cette action est irréversible.')) return;
            
            showLoading();
            
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);
            fd.append('csrf_token', document.querySelector('#productForm [name=csrf_token]').value);
            
            fetch('api/products.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            })
            .then(r => r.json())
            .then(res => {
                hideLoading();
                if (!res.success) throw new Error(res.message || 'Erreur');
                
                fetchList(currentPage);
                showNotification('success', 'Succès', 'Produit supprimé avec succès', 3000);
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

        // Fonctions d'export
        function exportData(format) {
            showLoading();
            
            // Récupérer les paramètres de filtrage actuels
            const params = new URLSearchParams({
                action: 'export',
                format: format
            });
            
            if (searchTerm) params.append('search', searchTerm);
            if (categoryFilter) params.append('category_id', categoryFilter);
            if (onlyActiveFilter) params.append('only_active', '1');
            
            // Utiliser fetch pour gérer le téléchargement
            fetch(`api/export_handler.php?${params.toString()}`)
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(err => { throw new Error(err.message); });
                    }
                    return response.blob();
                })
                .then(blob => {
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `produits_${new Date().toISOString().split('T')[0]}.${getFileExtension(format)}`;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    
                    hideLoading();
                    showNotification('success', 'Export réussi', `Fichier ${format.toUpperCase()} généré avec succès`, 3000);
                })
                .catch(err => {
                    hideLoading();
                    showNotification('error', 'Erreur d\'export', err.message);
                });
        }

        // function exportData(format) {
        //     // Récupérer les paramètres de filtrage actuels
        //     const params = new URLSearchParams({
        //         format: format
        //     });
            
        //     if (searchTerm) params.append('search', searchTerm);
        //     if (categoryFilter) params.append('category_id', categoryFilter);
        //     if (onlyActiveFilter) params.append('only_active', '1');
            
        //     // Ouvrir directement le fichier d'export
        //     window.open(`api/export_handler.php?${params.toString()}`, '_blank');
            
        //     showNotification('success', 'Export lancé', 'Téléchargement en cours...', 3000);
        // }


        function getFileExtension(format) {
            const extensions = {
                'excel': 'xlsx',
                'csv': 'csv',
                'pdf': 'pdf',
                'word': 'docx'
            };
            return extensions[format] || 'txt';
        }

        // Fonction d'impression
        function printProducts() {
            showLoading();
            
            // Récupérer les données actuelles
            const params = new URLSearchParams({
                action: 'list',
                limit: 1000 // Récupérer tous les produits pour l'impression
            });
            
            if (searchTerm) params.append('search', searchTerm);
            if (categoryFilter) params.append('category_id', categoryFilter);
            if (onlyActiveFilter) params.append('only_active', '1');
            
            fetch(`api/products.php?${params.toString()}`, { 
                headers: { 'X-Requested-With': 'XMLHttpRequest' } 
            })
            .then(r => r.json())
            .then(res => {
                hideLoading();
                if (!res.success) throw new Error(res.message || 'Erreur');
                
                generatePrintView(res.data || []);
            })
            .catch(err => {
                hideLoading();
                showNotification('error', 'Erreur', err.message);
            });
        }

        function generatePrintView(products) {
            // Créer une nouvelle fenêtre pour l'impression
            const printWindow = window.open('', '_blank');
            const printDate = new Date().toLocaleDateString('fr-FR');
            
            let html = `
                <!DOCTYPE html>
                <html lang="fr">
                <head>
                    <meta charset="UTF-8">
                    <title>Liste des Produits - Le Gourmet</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
                        .header h1 { color: #2c3e50; margin: 0; }
                        .header .subtitle { color: #7f8c8d; font-size: 14px; }
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        th { background-color: #f8f9fa; border: 1px solid #dee2e6; padding: 12px; text-align: left; font-weight: bold; }
                        td { border: 1px solid #dee2e6; padding: 10px; }
                        .text-center { text-align: center; }
                        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; }
                        .badge-success { background-color: #d4edda; color: #155724; }
                        .badge-secondary { background-color: #e2e3e5; color: #383d41; }
                        .badge-warning { background-color: #fff3cd; color: #856404; }
                        .summary { margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 5px; }
                        .no-image { color: #6c757d; font-style: italic; }
                        @media print {
                            body { margin: 0; }
                            .no-print { display: none; }
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>Liste des Produits - Le Gourmet</h1>
                        <div class="subtitle">Généré le ${printDate}</div>
                    </div>
                    
                    <div class="summary">
                        <strong>Récapitulatif :</strong> ${products.length} produit(s) trouvé(s)
                        ${searchTerm ? `• Recherche : "${searchTerm}"` : ''}
                        ${categoryFilter ? `• Catégorie filtrée` : ''}
                        ${onlyActiveFilter ? `• Disponibles uniquement` : ''}
                    </div>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nom</th>
                                <th>Catégorie</th>
                                <th class="text-center">Prix</th>
                                <th class="text-center">Disponible</th>
                                <th class="text-center">En avant</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            products.forEach((product, index) => {
                html += `
                    <tr>
                        <td>${index + 1}</td>
                        <td><strong>${escapeHtml(product.name)}</strong></td>
                        <td>${escapeHtml(product.category_name || '-')}</td>
                        <td class="text-center">${Number(product.price).toFixed(2)}$</td>
                        <td class="text-center">
                            <span class="badge ${product.is_available ? 'badge-success' : 'badge-secondary'}">
                                ${product.is_available ? 'Oui' : 'Non'}
                            </span>
                        </td>
                        <td class="text-center">
                            ${product.is_featured ? '<span class="badge badge-warning">Oui</span>' : '-'}
                        </td>
                        <td>${escapeHtml(product.description || 'Aucune description')}</td>
                    </tr>
                `;
            });
            
            html += `
                        </tbody>
                    </table>
                    
                    <div style="margin-top: 30px; text-align: center; color: #6c757d; font-size: 12px;">
                        Document généré automatiquement par Le Gourmet - Back Office
                    </div>
                    
                    <script>
                        window.onload = function() {
                            window.print();
                            setTimeout(() => {
                                window.close();
                            }, 500);
                        };
                    <\/script>
                </body>
                </html>
            `;
            
            printWindow.document.write(html);
            printWindow.document.close();
        }

        // Gestion des filtres
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                searchTerm = searchInput.value.trim();
                currentPage = 1;
                fetchList();
            }, 500);
        });

        filterCategory.addEventListener('change', () => {
            categoryFilter = filterCategory.value;
            currentPage = 1;
            fetchList();
        });

        onlyActive.addEventListener('change', () => {
            onlyActiveFilter = onlyActive.checked;
            currentPage = 1;
            fetchList();
        });

        // Chargement initial
        fetchList();
    </script>
<?php include 'includes/footer.php'; ?>