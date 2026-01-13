<?php

include 'includes/nav_sidebar.php';
require_once '../config/database.php';
require_once '../config/security.php';

if (!Security::isLoggedIn() || !Security::isAdmin()) {
    Security::redirect('../auth/login.php');
}
?>

<style>
    #container{
                margin-top:80px;
                margin-left: 270px;
                position: fixed;
                scrol
            }   
</style>
    
<div id="container" class="container containere overflow-x-hidden">
    <div class="row" style ="width:100%">
        <div class="col-md-12 col-lg-12 user-content">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h3 mb-0"><i class="fas fa-users me-2 text-primary"></i>Utilisateurs</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="openCreate()">
                    <i class="fas fa-user-plus me-2"></i>Nouvel utilisateur
                </button>
            </div>

            <!-- Filtres -->
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">Recherche</label>
                            <input type="text" id="search" class="form-control" placeholder="Nom, prénom, email">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Rôle</label>
                            <select id="role" class="form-select">
                                <option value="">Tous</option>
                                <option value="admin">Admin</option>
                                <option value="manager">Manager</option>
                                <option value="staff">Staff</option>
                                <option value="customer">Client</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Statut</label>
                            <select id="status" class="form-select">
                                <option value="">Tous</option>
                                <option value="active">Actif</option>
                                <option value="inactive">Inactif</option>
                            </select>
                        </div>
                        <div class="col-md-2 text-end">
                            <button class="btn btn-outline-secondary" id="btnReset"><i class="fas fa-rotate-left me-1"></i>Réinitialiser</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tableau des utilisateurs -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-hover align-middle" id="usersTable">
                            <thead>
                                <tr>
                                    <th>Avatar</th>
                                    <th>Nom</th>
                                    <th>Email</th>
                                    <th>Téléphone</th>
                                    <th>Rôle</th>
                                    <th>Statut</th>
                                    <th>Dernière connexion</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <nav aria-label="Pagination" id="paginationContainer">
                        <ul class="pagination justify-content-center mb-0" id="pagination"></ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

        <!-- Modal pour créer/modifier un utilisateur -->
        <div class="modal fade" id="userModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Nouvel utilisateur</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="userForm" enctype="multipart/form-data">
                        <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                            <input type="hidden" name="id" id="user_id">
                            <input type="hidden" name="action" id="formAction" value="create">
                            
                            <div class="row g-3">
                                <!-- Avatar -->
                                <div class="col-md-12">
                                    <label class="form-label">Photo de profil</label>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-preview" id="avatarPreview" style="width: 80px; height: 80px; border-radius: 50%; overflow: hidden; background: #f8f9fa; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-user fa-2x text-muted" id="avatarIcon"></i>
                                            <img src="" alt="Preview" id="avatarImage" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                                        </div>
                                        <div class="flex-grow-1">
                                            <input type="file" class="form-control" name="avatar" id="avatar" accept="image/*">
                                            <div class="form-text">Formats acceptés: JPG, PNG, GIF, WebP. Max: 1MB</div>
                                            <div id="avatarActions" style="display: none;">
                                                <button type="button" class="btn btn-sm btn-outline-danger mt-1" onclick="deleteAvatar()">
                                                    <i class="fas fa-trash me-1"></i>Supprimer l'avatar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Prénom *</label>
                                    <input class="form-control" name="first_name" id="first_name" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Nom *</label>
                                    <input class="form-control" name="last_name" id="last_name" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="email" id="email" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Téléphone</label>
                                    <input type="tel" class="form-control" name="phone" id="phone">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Rôle</label>
                                    <select class="form-select" name="role" id="role_field">
                                        <option value="customer">Client</option>
                                        <option value="staff">Staff</option>
                                        <option value="manager">Manager</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Mot de passe <span id="passwordRequired">*</span></label>
                                    <input type="password" class="form-control" name="password" id="password" required>
                                    <div class="form-text" id="passwordHelp">Minimum 8 caractères avec majuscule, minuscule et chiffre</div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" checked>
                                        <label class="form-check-label" for="is_active">Utilisateur actif</label>
                                    </div>
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

        <!-- Modal pour voir les détails -->
        <div class="modal fade" id="viewModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Détails de l'utilisateur</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="userDetails"></div>
                </div>
            </div>
        </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentPage = 1;
        let totalPages = 1;
        let currentEditingUserId = null;
        const tableBody = document.querySelector('#usersTable tbody');
        const searchInput = document.getElementById('search');
        const roleSelect = document.getElementById('role');
        const statusSelect = document.getElementById('status');
        const btnReset = document.getElementById('btnReset');
        const modalEl = document.getElementById('userModal');
        const modal = new bootstrap.Modal(modalEl);
        const viewModal = new bootstrap.Modal(document.getElementById('viewModal'));
        const form = document.getElementById('userForm');
        const modalTitle = document.getElementById('modalTitle');
        const avatarInput = document.getElementById('avatar');
        const avatarPreview = document.getElementById('avatarPreview');
        const avatarImage = document.getElementById('avatarImage');
        const avatarIcon = document.getElementById('avatarIcon');
        const avatarActions = document.getElementById('avatarActions');
        const passwordField = document.getElementById('password');
        const passwordRequired = document.getElementById('passwordRequired');
        const passwordHelp = document.getElementById('passwordHelp');
        const formAction = document.getElementById('formAction');

        // Gestion de l'avatar
        avatarInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                if (file.size > 2 * 1024 * 1024) {
                    alert('Fichier trop volumineux (max 2MB)');
                    this.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    avatarImage.src = e.target.result;
                    avatarImage.style.display = 'block';
                    avatarIcon.style.display = 'none';
                };
                reader.readAsDataURL(file);
            }
        });
        
        // function ensureAbsoluteUrl(url) {
        //     if (!url) {
        //         return window.location.origin + '/marie/web-ingeneering/assets/img/default_avatar.jpg';
        //     }
            
        //     // Si c'est déjà une URL complète
        //     if (url.startsWith('http://') || url.startsWith('https://')) {
        //         return url;
        //     }
            
        //     // Si c'est un chemin relatif (commence par /)
        //     if (url.startsWith('/')) {
        //         return window.location.origin + url;
        //     }
            
        //     // Si c'est juste un nom de fichier
        //     return window.location.origin + '/web-ingeneering/uploads/avatars/' + url;
        // }

        // function fetchList(page = 1) {
        //     currentPage = page;
        //     const params = new URLSearchParams();
        //     if (searchInput.value.trim()) params.set('search', searchInput.value.trim());
        //     if (roleSelect.value) params.set('role', roleSelect.value);
        //     if (statusSelect.value) params.set('is_active', statusSelect.value === 'active' ? '1' : '0');
        //     params.set('page', page);
        //     params.set('limit', 10);
            
        //     fetch(`api/users.php?action=list&${params.toString()}`, { 
        //         headers: { 'X-Requested-With': 'XMLHttpRequest' } 
        //     })
        //     .then(r => r.json())
        //     .then(res => {
        //         if (!res.success) throw new Error(res.message || 'Erreur');
                
        //         // Convertir les URLs relatives en URLs absolues
        //         const users = res.data || [];
        //         users.forEach(user => {
        //             if (user.avatar_url) {
        //                 // Si c'est un chemin relatif, le convertir en URL absolue
        //                 if (user.avatar_url.startsWith('/')) {
        //                     user.avatar_url = window.location.origin + user.avatar_url;
        //                 }
        //             }
        //         });
                
        //         renderRows(users);
        //         renderPagination(res.pagination);
        //     })
        //     .catch(err => {
        //         console.error('Erreur:', err);
        //         alert('Erreur: ' + err.message);
        //     });
        // }

        function fetchList(page = 1) {
            currentPage = page;
            const params = new URLSearchParams();
            if (searchInput.value.trim()) params.set('search', searchInput.value.trim());
            if (roleSelect.value) params.set('role', roleSelect.value);
            if (statusSelect.value) params.set('is_active', statusSelect.value === 'active' ? '1' : '0');
            params.set('page', page);
            params.set('limit', 10);
            
            console.log('🔄 Requête vers API users.php avec params:', params.toString());
            
            fetch(`api/users.php?action=list&${params.toString()}`, { 
                headers: { 'X-Requested-With': 'XMLHttpRequest' } 
            })
            .then(r => {
                console.log('📡 Réponse HTTP status:', r.status);
                return r.json();
            })
            .then(res => {
                if (!res.success) throw new Error(res.message || 'Erreur');
                
                console.log('✅ Réponse API réussie:', res);
                console.log('📊 Données reçues:', res.data);
                
                // DEBUG: Vérifier un utilisateur spécifique
                if (res.data && res.data.length > 0) {
                    const firstUser = res.data[0];
                    console.log('👤 Premier utilisateur:', {
                        id: firstUser.id,
                        nom: firstUser.first_name + ' ' + firstUser.last_name,
                        avatar_field: firstUser.avatar,
                        avatar_url: firstUser.avatar_url,
                        hasAvatar: !!firstUser.avatar || !!firstUser.avatar_url
                    });
                    
                    // Tester le chargement de l'image
                    if (firstUser.avatar_url) {
                        const testImg = new Image();
                        const testUrl = firstUser.avatar_url.startsWith('/') ? 
                            window.location.origin + firstUser.avatar_url : 
                            firstUser.avatar_url;
                        testImg.src = testUrl;
                        testImg.onload = () => console.log('✅ Image test chargée:', testUrl);
                        testImg.onerror = () => console.log('❌ Erreur chargement image test:', testUrl);
                    }
                }
                
                renderRows(res.data);
                renderPagination(res.pagination);
            })
            .catch(err => {
                console.error('❌ Erreur:', err);
                alert('Erreur: ' + err.message);
            });
        }



        // function fetchList(page = 1) {
        //     currentPage = page;
        //     const params = new URLSearchParams();
        //     if (searchInput.value.trim()) params.set('search', searchInput.value.trim());
        //     if (roleSelect.value) params.set('role', roleSelect.value);
        //     if (statusSelect.value) params.set('is_active', statusSelect.value === 'active' ? '1' : '0');
        //     params.set('page', page);
        //     params.set('limit', 10);
            
        //     console.log('=== REQUÊTE API ===');
        //     console.log('URL:', `api/users.php?action=list&${params.toString()}`);
            
        //     fetch(`api/users.php?action=list&${params.toString()}`, { 
        //         headers: { 'X-Requested-With': 'XMLHttpRequest' } 
        //     })
        //     .then(r => {
        //         console.log('=== RÉPONSE HTTP ===');
        //         console.log('Status:', r.status);
        //         console.log('Headers:', r.headers);
        //         return r.json();
        //     })
        //     .then(res => {
        //         if (!res.success) throw new Error(res.message || 'Erreur');
                
        //         console.log('=== DONNÉES API ===');
        //         console.log('Réponse complète:', res);
        //         console.log('Debug serveur:', res.debug);
                
        //         // DEBUG DÉTAILLÉ DES AVATARS
        //         console.log('=== DEBUG AVATARS CLIENT ===');
        //         const users = res.data || [];
        //         users.forEach((user, index) => {
        //             console.log(`Utilisateur ${index + 1}:`, {
        //                 id: user.id,
        //                 nom: `${user.first_name} ${user.last_name}`,
        //                 avatar_fichier: user.avatar,
        //                 avatar_url: user.avatar_url,
        //                 url_complete: window.location.origin + user.avatar_url
        //             });
                    
        //             // Test de chargement d'image
        //             if (user.avatar_url) {
        //                 const testImg = new Image();
        //                 testImg.onload = function() {
        //                     console.log(`✅ Image chargée: ${user.avatar_url}`);
        //                 };
        //                 testImg.onerror = function() {
        //                     console.log(`❌ Erreur chargement image: ${user.avatar_url}`);
        //                 };
        //                 testImg.src = window.location.origin + user.avatar_url;
        //             }
        //         });
                
        //         // Conversion URLs relatives en absolues
        //         users.forEach(user => {
        //             if (user.avatar_url && user.avatar_url.startsWith('/')) {
        //                 user.avatar_url = window.location.origin + user.avatar_url;
        //             }
        //         });
                
        //         renderRows(users);
        //         renderPagination(res.pagination);
        //     })
        //     .catch(err => {
        //         console.error('=== ERREUR ===', err);
        //         alert('Erreur: ' + err.message);
        //     });
        // }

        // function renderRows(rows) {
        //     tableBody.innerHTML = '';
        //     if (!rows.length) {
        //         tableBody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Aucun utilisateur trouvé</td></tr>';
        //         return;
        //     }

        //     rows.forEach(row => {
        //         const tr = document.createElement('tr');
                
        //         // Créer l'avatar séparément pour mieux gérer les images
        //         const avatarHtml = row.avatar_url ? 
        //             `<img src="${escapeHtml(row.avatar_url)}" alt="Avatar" class="w-100 h-100" style="object-fit: cover;">` :
        //             `<i class="fas fa-user text-muted" style="line-height: 40px;"></i>`;
                
        //         tr.innerHTML = `
        //             <td>
        //                 <div class="avatar-circle" style="width: 40px; height: 40px; border-radius: 50%; overflow: hidden; background: #f8f9fa; display: flex; align-items: center; justify-content: center;">
        //                     ${avatarHtml}
        //                 </div>
        //             </td>
        //             <td>
        //                 <strong>${escapeHtml((row.first_name||'') + ' ' + (row.last_name||''))}</strong>
        //             </td>
        //             <td>${escapeHtml(row.email)}</td>
        //             <td>${escapeHtml(row.phone || '-')}</td>
        //             <td><span class="badge bg-${badgeForRole(row.role)}">${getRoleLabel(row.role)}</span></td>
        //             <td>
        //                 <div class="form-check form-switch">
        //                     <input class="form-check-input" type="checkbox" ${row.is_active ? 'checked' : ''} 
        //                         onchange="toggleActive(${row.id}, this.checked)">
        //                 </div>
        //             </td>
        //             <td>${row.last_login ? new Date(row.last_login.replace(' ','T')).toLocaleString('fr-FR') : 'Jamais'}</td>
        //             <td>
        //                 <div class="btn-group btn-group-sm">
        //                     <button class="btn btn-outline-primary" onclick="viewUser(${row.id})" title="Voir">
        //                         <i class="fas fa-eye"></i>
        //                     </button>
        //                     <button class="btn btn-outline-warning" onclick="editUser(${row.id})" title="Modifier">
        //                         <i class="fas fa-edit"></i>
        //                     </button>
        //                     <button class="btn btn-outline-secondary" onclick="resetPassword(${row.id})" title="Réinitialiser mot de passe">
        //                         <i class="fas fa-key"></i>
        //                     </button>
        //                     <button class="btn btn-outline-danger" onclick="deleteUser(${row.id})" title="Supprimer">
        //                         <i class="fas fa-trash"></i>
        //                     </button>
        //                 </div>
        //             </td>`;
                
        //         // Gestion d'erreur des images après création
        //         const img = tr.querySelector('img');
        //         if (img) {
        //             img.onerror = function() {
        //                 console.log('Erreur chargement avatar, remplacement par icône:', this.src);
        //                 this.style.display = 'none';
        //                 const icon = document.createElement('i');
        //                 icon.className = 'fas fa-user text-muted';
        //                 icon.style.cssText = 'line-height: 40px;';
        //                 this.parentNode.appendChild(icon);
        //             };
        //         }
                
        //         tableBody.appendChild(tr);
        //     });
        // }

        function renderRows(rows) {
            tableBody.innerHTML = '';
            if (!rows.length) {
                tableBody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Aucun utilisateur trouvé</td></tr>';
                return;
            }

            // Fonction pour garantir une URL absolue
            function ensureAbsoluteUrl(url) {
                if (!url) {
                    // Avatar par défaut
                    return window.location.origin + '/web-ingeneering/assets/img/default_avatar.jpg';
                }
                
                // Si c'est déjà une URL complète
                if (url.startsWith('http://') || url.startsWith('https://')) {
                    return url;
                }
                
                // Si c'est un chemin relatif (commence par /)
                if (url.startsWith('/')) {
                    return window.location.origin + url;
                }
                
                // Si c'est juste un nom de fichier
                return window.location.origin + '/web-ingeneering/uploads/avatars/' + url;
            }

            rows.forEach(row => {
                const tr = document.createElement('tr');
                
                // Générer l'URL d'avatar absolue
                const avatarUrl = ensureAbsoluteUrl(row.avatar_url || row.avatar);
                
                // Créer l'HTML de l'avatar avec gestion d'erreur intégrée
                const avatarHtml = avatarUrl ? 
                    `<img src="${escapeHtml(avatarUrl)}" alt="Avatar" class="w-100 h-100" style="object-fit: cover;" 
                        onerror="this.onerror=null; this.style.display='none'; 
                                const icon=document.createElement('i'); 
                                icon.className='fas fa-user text-muted';
                                icon.style.cssText='line-height: 40px;';
                                this.parentNode.appendChild(icon);">` :
                    `<i class="fas fa-user text-muted" style="line-height: 40px;"></i>`;
                
                tr.innerHTML = `
                    <td>
                        <div class="avatar-circle" style="width: 40px; height: 40px; border-radius: 50%; overflow: hidden; background: #f8f9fa; display: flex; align-items: center; justify-content: center;">
                            ${avatarHtml}
                        </div>
                    </td>
                    <td>
                        <strong>${escapeHtml((row.first_name||'') + ' ' + (row.last_name||''))}</strong>
                    </td>
                    <td>${escapeHtml(row.email)}</td>
                    <td>${escapeHtml(row.phone || '-')}</td>
                    <td><span class="badge bg-${badgeForRole(row.role)}">${getRoleLabel(row.role)}</span></td>
                    <td>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" ${row.is_active ? 'checked' : ''} 
                                onchange="toggleActive(${row.id}, this.checked)">
                        </div>
                    </td>
                    <td>${row.last_login ? new Date(row.last_login.replace(' ','T')).toLocaleString('fr-FR') : 'Jamais'}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="viewUser(${row.id})" title="Voir">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-outline-warning" onclick="editUser(${row.id})" title="Modifier">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-outline-secondary" onclick="resetPassword(${row.id})" title="Réinitialiser mot de passe">
                                <i class="fas fa-key"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="deleteUser(${row.id})" title="Supprimer">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>`;
                
                // Supprimer l'ancienne gestion d'erreur puisque maintenant elle est intégrée dans l'HTML
                // La gestion d'erreur onerror dans l'attribut img s'occupe de tout
                
                tableBody.appendChild(tr);
                
                // Optionnel : Afficher un log de débogage
                console.log(`Avatar pour ${row.first_name} ${row.last_name}:`, {
                    avatar_db: row.avatar,
                    avatar_url_api: row.avatar_url,
                    computed_url: avatarUrl
                });
            });
        }

        function renderPagination(pagination) {
            const paginationContainer = document.getElementById('pagination');
            paginationContainer.innerHTML = '';
            
            if (!pagination || pagination.total_pages <= 1) return;
            
            const { current_page, total_pages, has_previous, has_next, total_users } = pagination;
            
            // Bouton Précédent
            const prevLi = document.createElement('li');
            prevLi.className = `page-item ${!has_previous ? 'disabled' : ''}`;
            prevLi.innerHTML = `<a class="page-link" href="#" onclick="fetchList(${current_page - 1})">Précédent</a>`;
            paginationContainer.appendChild(prevLi);
            
            // Pages
            for (let i = 1; i <= total_pages; i++) {
                const pageLi = document.createElement('li');
                pageLi.className = `page-item ${current_page === i ? 'active' : ''}`;
                pageLi.innerHTML = `<a class="page-link" href="#" onclick="fetchList(${i})">${i}</a>`;
                paginationContainer.appendChild(pageLi);
            }
            
            // Bouton Suivant
            const nextLi = document.createElement('li');
            nextLi.className = `page-item ${!has_next ? 'disabled' : ''}`;
            nextLi.innerHTML = `<a class="page-link" href="#" onclick="fetchList(${current_page + 1})">Suivant</a>`;
            paginationContainer.appendChild(nextLi);
            
            // Afficher le nombre de résultats
            const existingCounter = document.getElementById('resultsCounter');
            if (existingCounter) {
                existingCounter.remove();
            }
            
            const counter = document.createElement('div');
            counter.id = 'resultsCounter';
            counter.className = 'text-center mt-2 text-muted';
            counter.innerHTML = `Page ${current_page} sur ${total_pages} - ${total_users} utilisateur(s) au total`;
            paginationContainer.appendChild(counter);
        }

        function openCreate() {
            form.reset();
            formAction.value = 'create';
            modalTitle.textContent = 'Nouvel utilisateur';
            passwordField.required = true;
            passwordRequired.style.display = 'inline';
            passwordHelp.textContent = 'Minimum 8 caractères avec majuscule, minuscule et chiffre';
            resetAvatarPreview();
            avatarActions.style.display = 'none';
            currentEditingUserId = null;
        }

        function editUser(id) {
            const fd = new FormData();
            fd.append('action', 'get');
            fd.append('id', id);
            fd.append('csrf_token', document.querySelector('#userForm [name=csrf_token]').value);
            
            fetch('api/users.php', { 
                method: 'POST', 
                headers: { 'X-Requested-With': 'XMLHttpRequest' }, 
                body: fd 
            })
            .then(r => r.json())
            .then(res => {
                if (!res.success) throw new Error(res.message || 'Erreur');
                const user = res.data;
                
                // Remplir le formulaire
                document.getElementById('user_id').value = user.id;
                document.getElementById('first_name').value = user.first_name || '';
                document.getElementById('last_name').value = user.last_name || '';
                document.getElementById('email').value = user.email || '';
                document.getElementById('phone').value = user.phone || '';
                document.getElementById('role_field').value = user.role || 'customer';
                document.getElementById('is_active').checked = user.is_active == 1;
                
                //  Gestion avatar avec URL absolue
                if (user.avatar_url) {
                    // Convertir en URL absolue si nécessaire
                    let avatarUrl = user.avatar_url;
                    if (avatarUrl.startsWith('/')) {
                        avatarUrl = window.location.origin + avatarUrl;
                    }
                    avatarImage.src = avatarUrl;
                    avatarImage.style.display = 'block';
                    avatarIcon.style.display = 'none';
                    avatarActions.style.display = 'block';
                } else {
                    resetAvatarPreview();
                    avatarActions.style.display = 'none';
                }
                
                formAction.value = 'update';
                modalTitle.textContent = 'Modifier l\'utilisateur';
                passwordField.required = false;
                passwordRequired.style.display = 'none';
                passwordHelp.textContent = 'Laisser vide pour ne pas modifier le mot de passe';
                currentEditingUserId = id;
                modal.show();
            })
            .catch(err => {
                console.error('Erreur:', err);
                alert('Erreur lors du chargement: ' + err.message);
            });
        }

        function deleteAvatar() {
            if (!currentEditingUserId) return;
            
            if (!confirm('Supprimer l\'avatar de cet utilisateur ?')) return;
            
            const fd = new FormData();
            fd.append('action', 'delete_avatar');
            fd.append('id', currentEditingUserId);
            fd.append('csrf_token', document.querySelector('#userForm [name=csrf_token]').value);
            
            fetch('api/users.php', { 
                method: 'POST', 
                headers: { 'X-Requested-With': 'XMLHttpRequest' }, 
                body: fd 
            })
            .then(r => r.json())
            .then(res => { 
                if (!res.success) throw new Error(res.message || 'Erreur'); 
                resetAvatarPreview();
                avatarActions.style.display = 'none';
                alert('Avatar supprimé avec succès');
                fetchList(currentPage);
            })
            .catch(err => alert(err.message));
        }

        function viewUser(id) {
            const fd = new FormData();
            fd.append('action', 'get');
            fd.append('id', id);
            fd.append('csrf_token', document.querySelector('#userForm [name=csrf_token]').value);
            
            fetch('api/users.php', { 
                method: 'POST', 
                headers: { 'X-Requested-With': 'XMLHttpRequest' }, 
                body: fd 
            })
            .then(r => r.json())
            .then(res => {
                if (!res.success) throw new Error(res.message || 'Erreur');
                const user = res.data;
                
                let avatarUrl = user.avatar_url || '';
                if (avatarUrl && avatarUrl.startsWith('/')) {
                    avatarUrl = window.location.origin + avatarUrl;
                }
                
                const details = document.getElementById('userDetails');
                details.innerHTML = `
                    <div class="row">
                        <div class="col-md-3 text-center">
                            <div style="width: 100px; height: 100px; border-radius: 50%; overflow: hidden; background: #f8f9fa; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                ${avatarUrl ? 
                                    `<img src="${escapeHtml(avatarUrl)}" alt="Avatar" class="w-100 h-100" style="object-fit: cover;">` :
                                    `<i class="fas fa-user text-muted fa-3x"></i>`
                                }
                            </div>
                        </div>
                        <div class="col-md-9">
                            <h4>${escapeHtml((user.first_name||'') + ' ' + (user.last_name||''))}</h4>
                            <div class="row mt-3">
                                <div class="col-6"><strong>Email:</strong><br>${escapeHtml(user.email)}</div>
                                <div class="col-6"><strong>Téléphone:</strong><br>${escapeHtml(user.phone || '-')}</div>
                                <div class="col-6 mt-2"><strong>Rôle:</strong><br><span class="badge bg-${badgeForRole(user.role)}">${getRoleLabel(user.role)}</span></div>
                                <div class="col-6 mt-2"><strong>Statut:</strong><br>${user.is_active == 1 ? '<span class="badge bg-success">Actif</span>' : '<span class="badge bg-danger">Inactif</span>'}</div>
                                <div class="col-6 mt-2"><strong>Créé le:</strong><br>${user.created_at ? new Date(user.created_at.replace(' ','T')).toLocaleDateString('fr-FR') : '-'}</div>
                                <div class="col-6 mt-2"><strong>Dernière connexion:</strong><br>${user.last_login ? new Date(user.last_login.replace(' ','T')).toLocaleString('fr-FR') : 'Jamais'}</div>
                            </div>
                        </div>
                    </div>
                `;
                viewModal.show();
            })
            .catch(err => {
                console.error('Erreur:', err);
                alert('Erreur lors du chargement: ' + err.message);
            });
        }

        function resetAvatarPreview() {
            avatarImage.style.display = 'none';
            avatarIcon.style.display = 'block';
            avatarInput.value = '';
        }

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const fd = new FormData(form);
            
            const action = formAction.value;
            fd.append('action', action);
            
            fetch('api/users.php', { 
                method: 'POST', 
                headers: { 'X-Requested-With': 'XMLHttpRequest' }, 
                body: fd 
            })
            .then(r => r.json())
            .then(res => { 
                if (!res.success) throw new Error(res.message || 'Erreur'); 
                modal.hide(); 
                fetchList(currentPage);
                alert(action === 'create' ? 'Utilisateur créé avec succès' : 'Utilisateur modifié avec succès');
            })
            .catch(err => alert(err.message));
        });

        function handleUserUpdate(fd) {
            const updateFd = new FormData();
            updateFd.append('action', 'update');
            updateFd.append('id', fd.get('id'));
            updateFd.append('email', document.getElementById('email').value);
            updateFd.append('first_name', document.getElementById('first_name').value);
            updateFd.append('last_name', document.getElementById('last_name').value);
            updateFd.append('phone', document.getElementById('phone').value);
            updateFd.append('role', document.getElementById('role_field').value);
            updateFd.append('is_active', document.getElementById('is_active').checked ? '1' : '0');
            updateFd.append('csrf_token', fd.get('csrf_token'));
            
            const password = document.getElementById('password').value;
            if (password) {
                updateFd.append('password', password);
            }
            
            fetch('api/users.php', { 
                method: 'POST', 
                headers: { 'X-Requested-With': 'XMLHttpRequest' }, 
                body: updateFd 
            })
            .then(r => r.json())
            .then(res => {
                if (!res.success) throw new Error(res.message || 'Erreur');
                
                const avatarFile = avatarInput.files[0];
                if (avatarFile) {
                    return uploadAvatar(fd.get('id'), avatarFile, fd.get('csrf_token'));
                }
                return Promise.resolve();
            })
            .then(() => {
                modal.hide();
                fetchList(currentPage);
                alert('Utilisateur modifié avec succès');
            })
            .catch(err => alert(err.message));
        }

        function uploadAvatar(userId, file, csrfToken) {
            const fd = new FormData();
            fd.append('action', 'upload_avatar');
            fd.append('id', userId);
            fd.append('avatar', file);
            fd.append('csrf_token', csrfToken);
            
            return fetch('api/users.php', { 
                method: 'POST', 
                headers: { 'X-Requested-With': 'XMLHttpRequest' }, 
                body: fd 
            }).then(r => r.json());
        }

        function toggleActive(id, checked) {
            if (!confirm(`Voulez-vous vraiment ${checked ? 'activer' : 'désactiver'} cet utilisateur ?`)) {
                fetchList(currentPage);
                return;
            }

            const fd = new FormData();
            fd.append('action', 'toggle_active');
            fd.append('id', id);
            fd.append('is_active', checked ? '1' : '0');
            fd.append('csrf_token', document.querySelector('#userForm [name=csrf_token]').value);
            
            fetch('api/users.php', { 
                method: 'POST', 
                headers: { 'X-Requested-With': 'XMLHttpRequest' }, 
                body: fd 
            })
            .then(r => r.json())
            .then(res => { 
                if (!res.success) throw new Error(res.message || 'Erreur'); 
                alert(`Utilisateur ${checked ? 'activé' : 'désactivé'} avec succès`);
            })
            .catch(err => { 
                alert(err.message); 
                fetchList(currentPage); 
            });
        }

        function resetPassword(id) {
            const pwd = prompt('Nouveau mot de passe (min 8 caractères, majuscule, minuscule, chiffre)');
            if (!pwd) return;
            
            const fd = new FormData();
            fd.append('action', 'reset_password');
            fd.append('id', id);
            fd.append('password', pwd);
            fd.append('csrf_token', document.querySelector('#userForm [name=csrf_token]').value);
            
            fetch('api/users.php', { 
                method: 'POST', 
                headers: { 'X-Requested-With': 'XMLHttpRequest' }, 
                body: fd 
            })
            .then(r => r.json())
            .then(res => { 
                if (!res.success) throw new Error(res.message || 'Erreur'); 
                alert('Mot de passe mis à jour avec succès'); 
            })
            .catch(err => alert(err.message));
        }

        function deleteUser(id) {
            if (!confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ? Cette action est irréversible.')) {
                return;
            }

            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);
            fd.append('csrf_token', document.querySelector('#userForm [name=csrf_token]').value);
            
            fetch('api/users.php', { 
                method: 'POST', 
                headers: { 'X-Requested-With': 'XMLHttpRequest' }, 
                body: fd 
            })
            .then(r => r.json())
            .then(res => { 
                if (!res.success) throw new Error(res.message || 'Erreur'); 
                alert('Utilisateur supprimé avec succès');
                fetchList(currentPage);
            })
            .catch(err => alert(err.message));
        }

        function getRoleFromBadge(badgeText) {
            const roles = {
                'Administrateur': 'admin',
                'Manager': 'manager', 
                'Staff': 'staff',
                'Client': 'customer'
            };
            return roles[badgeText] || 'customer';
        }

        function badgeForRole(r) { 
            return r === 'admin' ? 'danger' : 
                   r === 'manager' ? 'primary' : 
                   r === 'staff' ? 'info' : 'secondary'; 
        }
        
        function getRoleLabel(r) {
            const labels = {
                'admin': 'Administrateur',
                'manager': 'Manager', 
                'staff': 'Staff',
                'customer': 'Client'
            };
            return labels[r] || r;
        }
        
        function escapeHtml(s) { 
            return (s || '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c] || c)); 
        }

        // Événements de filtrage
        [searchInput, roleSelect, statusSelect].forEach(el => el.addEventListener('input', () => {
            clearTimeout(window._t);
            window._t = setTimeout(() => fetchList(1), 300);
        }));
        
        btnReset.addEventListener('click', () => { 
            searchInput.value = ''; 
            roleSelect.value = ''; 
            statusSelect.value = '';
            fetchList(1); 
        });

        // Initialisation
        fetchList();
    </script>
</body>
</html>