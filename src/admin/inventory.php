<?php
include 'includes/nav_sidebar.php';
require_once '../config/database.php';
require_once '../config/security.php';

if (!Security::isLoggedIn() || !Security::isAdmin()) {
    Security::redirect('../auth/login.php');
}

// Load products for linking
$products = $pdo->query("SELECT id, name FROM products ORDER BY name ASC")->fetchAll();
?>

    <!-- <link href="../assets/css/style.css" rel="stylesheet"> -->
<div id="container" class="container">
    <div class="col-md-12 col-lg-12 inventory-content">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h3 mb-0"><i class="fas fa-boxes me-2 text-primary"></i>Inventaire</h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#inventoryModal" onclick="openCreate()">
                <i class="fas fa-plus me-2"></i>Nouvel élément
            </button>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label">Recherche</label>
                        <input type="text" id="search" class="form-control" placeholder="Ingrédient, produit, fournisseur">
                    </div>
                    <div class="col-md-6 text-md-end">
                        <button class="btn btn-outline-secondary" id="btnReset"><i class="fas fa-rotate-left me-1"></i>Réinitialiser</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div id="listContainer" class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-hover align-middle" id="inventoryTable">
                        <thead>
                            <tr>
                                <th>Ingrédient</th>
                                <th>Produit lié</th>
                                <th>Stock</th>
                                <th>Unité</th>
                                <th>Seuil min</th>
                                <th>Fournisseur</th>
                                <th>Péremption</th>
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

    <div class="modal fade" id="inventoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nouvel élément</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="inventoryForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                        <input type="hidden" name="id" id="inventory_id">
                        <div class="mb-3">
                            <label class="form-label">Ingrédient *</label>
                            <input class="form-control" name="ingredient_name" id="ingredient_name" required>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Produit lié</label>
                                <select class="form-select" name="product_id" id="product_id">
                                    <option value="">—</option>
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3"><label class="form-label">Stock</label><input type="number" step="0.01" class="form-control" name="current_stock" id="current_stock" value="0"></div>
                            <div class="col-md-3"><label class="form-label">Unité *</label><input class="form-control" name="unit" id="unit" required placeholder="kg, L, pcs..."></div>
                            <div class="col-md-4"><label class="form-label">Seuil min</label><input type="number" step="0.01" class="form-control" name="min_stock_level" id="min_stock_level" value="0"></div>
                            <div class="col-md-4"><label class="form-label">Coût/unité (€)</label><input type="number" step="0.01" class="form-control" name="cost_per_unit" id="cost_per_unit" value="0"></div>
                            <div class="col-md-4"><label class="form-label">Péremption</label><input type="date" class="form-control" name="expiry_date" id="expiry_date"></div>
                        </div>
                        <div class="mt-3"><label class="form-label">Fournisseur</label><input class="form-control" name="supplier" id="supplier"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="adjustModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ajuster le stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="adjustForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                        <input type="hidden" name="id" id="adjust_inventory_id">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Type</label>
                                <select name="movement_type" id="movement_type" class="form-select">
                                    <option value="in">Entrée</option>
                                    <option value="out">Sortie</option>
                                    <option value="adjustment">Ajustement</option>
                                </select>
                            </div>
                            <div class="col-md-6"><label class="form-label">Quantité</label><input type="number" step="0.01" class="form-control" name="quantity" id="quantity" required></div>
                            <div class="col-12"><label class="form-label">Raison</label><input class="form-control" name="reason" id="reason"></div>
                            <div class="col-12"><label class="form-label">Référence</label><input class="form-control" name="reference" id="reference"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Valider</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const tableBody = document.querySelector('#inventoryTable tbody');
        const searchInput = document.getElementById('search');
        const btnReset = document.getElementById('btnReset');
        const invModal = new bootstrap.Modal(document.getElementById('inventoryModal'));
        const adjustModal = new bootstrap.Modal(document.getElementById('adjustModal'));
        const invForm = document.getElementById('inventoryForm');
        const adjustForm = document.getElementById('adjustForm');
        const modalTitle = document.getElementById('modalTitle');

        // function fetchList() {
        //     const params = new URLSearchParams();
        //     if (searchInput.value.trim()) params.set('search', searchInput.value.trim());
        //     fetch('api/inventory.php?action=list&' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        //         .then(r => r.json())
        //         .then(res => { if (!res.success) throw new Error(res.message || 'Erreur'); renderRows(res.data || []); })
        //         .catch(err => alert(err.message));
        // }
        function fetchList() {
            const params = new URLSearchParams();
            if (searchInput.value.trim()) params.set('search', searchInput.value.trim());
            
            fetch('api/inventory.php?action=list&' + params.toString(), { 
                headers: { 'X-Requested-With': 'XMLHttpRequest' } 
            })
            .then(r => {
                // Lire d'abord comme texte pour debug
                return r.text().then(text => {
                    console.log("Réponse brute inventory:", text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error("Erreur parsing JSON. Texte reçu:", text);
                        throw new Error("Réponse invalide du serveur: " + text.substring(0, 100));
                    }
                });
            })
            .then(res => { 
                if (!res.success) throw new Error(res.message || 'Erreur'); 
                renderRows(res.data || []); 
            })
            .catch(err => {
                console.error("Erreur fetch inventory:", err);
                alert("Erreur: " + err.message);
            });
        }

        function renderRows(rows) {
            tableBody.innerHTML = '';
            if (!rows.length) { tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Aucun élément</td></tr>'; return; }
            rows.forEach(row => {
                const low = Number(row.current_stock) <= Number(row.min_stock_level);
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${escapeHtml(row.ingredient_name)}</strong></td>
                    <td>${escapeHtml(row.product_name || '')}</td>
                    <td>${Number(row.current_stock).toFixed(2)}</td>
                    <td>${escapeHtml(row.unit)}</td>
                    <td class="${low ? 'text-danger' : ''}">${Number(row.min_stock_level).toFixed(2)}</td>
                    <td>${escapeHtml(row.supplier || '')}</td>
                    <td>${row.expiry_date || '-'}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-info me-1" onclick='openEdit(${JSON.stringify(row)})'><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm btn-outline-success me-1" onclick='openAdjust(${row.id})'><i class="fas fa-balance-scale"></i></button>
                        <button class="btn btn-sm btn-outline-danger" onclick='removeItem(${row.id})'><i class="fas fa-trash"></i></button>
                    </td>`;
                tableBody.appendChild(tr);
            });
        }

        function openCreate() { invForm.reset(); invForm.actionMode = 'create'; document.getElementById('inventory_id').value = ''; modalTitle.textContent = 'Nouvel élément'; }
        function openEdit(row) {
            invModal.show(); invForm.actionMode = 'update'; modalTitle.textContent = 'Modifier l\'élément';
            document.getElementById('inventory_id').value = row.id;
            document.getElementById('ingredient_name').value = row.ingredient_name || '';
            document.getElementById('product_id').value = row.product_id || '';
            document.getElementById('current_stock').value = row.current_stock || 0;
            document.getElementById('unit').value = row.unit || '';
            document.getElementById('min_stock_level').value = row.min_stock_level || 0;
            document.getElementById('cost_per_unit').value = row.cost_per_unit || 0;
            document.getElementById('expiry_date').value = row.expiry_date || '';
            document.getElementById('supplier').value = row.supplier || '';
        }
        function openAdjust(id) { adjustModal.show(); document.getElementById('adjust_inventory_id').value = id; }

        invForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const fd = new FormData(invForm);
            const action = invForm.actionMode === 'update' ? 'update' : 'create';
            fd.append('action', action);
            fetch('api/inventory.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                .then(r => r.json()).then(res => { if (!res.success) throw new Error(res.message || 'Erreur'); invModal.hide(); fetchList(); })
                .catch(err => alert(err.message));
        });

        adjustForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const fd = new FormData(adjustForm);
            fd.append('action', 'adjust');
            fetch('api/inventory.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                .then(r => r.json()).then(res => { if (!res.success) throw new Error(res.message || 'Erreur'); adjustModal.hide(); fetchList(); })
                .catch(err => alert(err.message));
        });

        function removeItem(id) {
            if (!confirm('Supprimer cet élément ?')) return;
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);
            fd.append('csrf_token', document.querySelector('#inventoryForm [name=csrf_token]').value);
            fetch('api/inventory.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                .then(r => r.json()).then(res => { if (!res.success) throw new Error(res.message || 'Erreur'); fetchList(); })
                .catch(err => alert(err.message));
        }

        function escapeHtml(s) { return (s || '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c] || c)); }

        searchInput.addEventListener('input', () => { clearTimeout(window._t); window._t = setTimeout(fetchList, 250); });
        btnReset.addEventListener('click', () => { searchInput.value=''; fetchList(); });

        fetchList();
    </script>
</body>
</html>


