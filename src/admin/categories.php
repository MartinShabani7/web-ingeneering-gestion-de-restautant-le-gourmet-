<?php
include 'includes/nav_sidebar.php';
require_once '../config/database.php';
require_once '../config/security.php';

if (!Security::isLoggedIn() || !Security::isAdmin()) {
    Security::redirect('../auth/login.php');
}
?>

<div id="container" class="container">
    <div class="col-md-12 col-lg-12 categories-content">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h3 mb-0"><i class="fas fa-tags me-2 text-primary"></i>Gestion des catégories</h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal" onclick="openCreate()">
                <i class="fas fa-plus me-2"></i>Nouvelle catégorie
            </button>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label">Recherche</label>
                        <input type="text" id="search" class="form-control" placeholder="Nom ou description">
                    </div>
                    <div class="col-md-3">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" id="onlyActive">
                            <label class="form-check-label" for="onlyActive">Actives</label>
                        </div>
                    </div>
                    <div class="col-md-3 text-md-end">
                        <button class="btn btn-outline-secondary" id="btnReset"><i class="fas fa-rotate-left me-1"></i>Réinitialiser</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div id="listContainer" class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-hover align-middle" id="categoriesTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Image</th>
                                <th>Nom</th>
                                <th>Ordre</th>
                                <th>Active</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

    <div class="modal fade" id="categoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nouvelle catégorie</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="categoryForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                        <input type="hidden" name="id" id="category_id">
                        <div class="mb-3">
                            <label class="form-label">Nom *</label>
                            <input type="text" name="name" id="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Image</label>
                            <input type="file" name="image" id="image" class="form-control" accept="image/*">
                            <div class="form-text">JPG/PNG/GIF, max 2MB</div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Ordre</label>
                                <input type="number" name="sort_order" id="sort_order" class="form-control" value="0">
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                                    <label class="form-check-label" for="is_active">Active</label>
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
        const tableBody = document.querySelector('#categoriesTable tbody');
        const searchInput = document.getElementById('search');
        const onlyActive = document.getElementById('onlyActive');
        const btnReset = document.getElementById('btnReset');
        const modalEl = document.getElementById('categoryModal');
        const modal = new bootstrap.Modal(modalEl);
        const form = document.getElementById('categoryForm');
        const modalTitle = document.getElementById('modalTitle');

        // function fetchList() {
        //     const params = new URLSearchParams();
        //     if (searchInput.value.trim()) params.set('search', searchInput.value.trim());
        //     if (onlyActive.checked) params.set('only_active', '1');

        //     fetch('api/categories.php?action=list&' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        //         .then(r => r.json())
        //         .then(res => {
        //             if (!res.success) throw new Error(res.message || 'Erreur');
        //             renderRows(res.data || []);
        //         })
        //         .catch(err => alert(err.message));
        // }
        function fetchList() {
            const params = new URLSearchParams();
            if (searchInput.value.trim()) params.set('search', searchInput.value.trim());
            if (onlyActive.checked) params.set('only_active', '1');

            fetch('api/categories.php?action=list&' + params.toString(), { 
                headers: { 'X-Requested-With': 'XMLHttpRequest' } 
            })
            .then(r => {
                // Lire d'abord comme texte pour debug
                return r.text().then(text => {
                    console.log("Réponse brute categories:", text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error("Erreur parsing JSON. Texte reçu:", text);
                        throw new Error("Réponse invalide: " + text.substring(0, 100));
                    }
                });
            })
            .then(res => {
                if (!res.success) throw new Error(res.message || 'Erreur');
                renderRows(res.data || []);
            })
            .catch(err => {
                console.error("Erreur détaillée:", err);
                alert("Erreur: " + err.message);
            });
        }

        function renderRows(rows) {
            tableBody.innerHTML = '';
            if (!rows.length) {
                tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Aucune catégorie</td></tr>';
                return;
            }
            rows.forEach(row => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${row.id}</td>
                    <td>${row.image ? `<img src="../${row.image}" alt="" class="img-thumbnail" style="width:60px;height:60px;object-fit:cover;">` : '-'}</td>
                    <td><strong>${escapeHtml(row.name)}</strong><br><small class="text-muted">${escapeHtml(row.description || '')}</small></td>
                    <td>${row.sort_order ?? 0}</td>
                    <td>${row.is_active ? '<span class="badge bg-success">Oui</span>' : '<span class="badge bg-secondary">Non</span>'}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-info me-1" onclick='openEdit(${JSON.stringify(row)})'><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm btn-outline-danger" onclick="removeCategory(${row.id})"><i class="fas fa-trash"></i></button>
                    </td>`;
                tableBody.appendChild(tr);
            });
        }

        function openCreate() {
            form.reset();
            form.actionMode = 'create';
            document.getElementById('category_id').value = '';
            modalTitle.textContent = 'Nouvelle catégorie';
        }

        function openEdit(row) {
            modal.show();
            form.actionMode = 'update';
            modalTitle.textContent = 'Modifier la catégorie';
            document.getElementById('category_id').value = row.id;
            document.getElementById('name').value = row.name || '';
            document.getElementById('description').value = row.description || '';
            document.getElementById('sort_order').value = row.sort_order || 0;
            document.getElementById('is_active').checked = !!Number(row.is_active);
        }

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const fd = new FormData(form);
            const action = form.actionMode === 'update' ? 'update' : 'create';
            fd.append('action', action);
            fetch('api/categories.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            }).then(r => r.json()).then(res => {
                if (!res.success) throw new Error(res.message || 'Erreur');
                modal.hide();
                fetchList();
            }).catch(err => alert(err.message));
        });

        function removeCategory(id) {
            if (!confirm('Supprimer cette catégorie ?')) return;
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);
            fd.append('csrf_token', document.querySelector('#categoryForm [name=csrf_token]').value);
            fetch('api/categories.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            }).then(r => r.json()).then(res => {
                if (!res.success) throw new Error(res.message || 'Erreur');
                fetchList();
            }).catch(err => alert(err.message));
        }

        function escapeHtml(s) {
            return (s || '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c] || c));
        }

        [searchInput, onlyActive].forEach(el => el.addEventListener('input', () => {
            clearTimeout(window._t);
            window._t = setTimeout(fetchList, 250);
        }));
        btnReset.addEventListener('click', () => { searchInput.value=''; onlyActive.checked=false; fetchList(); });

        fetchList();
    </script>
</body>
</html>


