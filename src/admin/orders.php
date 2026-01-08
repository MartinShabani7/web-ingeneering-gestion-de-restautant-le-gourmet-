<?php
include 'includes/nav_sidebar.php';
require_once '../config/database.php';
require_once '../config/security.php';

if (!Security::isLoggedIn() || !Security::isAdmin()) {
    Security::redirect('../auth/login.php');
}
?>

    <div id="container" class="container">
        <div class="col-md-12 col-lg-12 orders-content">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h3 mb-0"><i class="fas fa-shopping-bag me-2 text-primary"></i>Commandes</h1>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">Recherche</label>
                            <input type="text" id="search" class="form-control" placeholder="#commande, client, email">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Statut</label>
                            <select id="status" class="form-select">
                                <option value="">Tous</option>
                                <option value="pending">En attente</option>
                                <option value="confirmed">Confirmée</option>
                                <option value="preparing">En préparation</option>
                                <option value="ready">Prête</option>
                                <option value="served">Servie</option>
                                <option value="cancelled">Annulée</option>
                            </select>
                        </div>
                        <div class="col-md-5 text-md-end">
                            <button class="btn btn-outline-secondary" id="btnReset"><i class="fas fa-rotate-left me-1"></i>Réinitialiser</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div id="listContainer" class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-hover align-middle" id="ordersTable">
                            <thead>
                                <tr>
                                    <th>Commande</th>
                                    <th>Client</th>
                                    <th>Type</th>
                                    <th>Date</th>
                                    <th>Statut</th>
                                    <th>Paiement</th>
                                    <th>Total</th>
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

        <div class="modal fade" id="orderModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="orderTitle">Détails commande</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div id="orderDetails"></div>
                    </div>
                    <div class="modal-footer">
                        <form id="statusForm" class="d-flex gap-2">
                            <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                            <input type="hidden" name="id" id="order_id">
                            <select name="status" id="order_status" class="form-select">
                                <option value="pending">En attente</option>
                                <option value="confirmed">Confirmée</option>
                                <option value="preparing">En préparation</option>
                                <option value="ready">Prête</option>
                                <option value="served">Servie</option>
                                <option value="cancelled">Annulée</option>
                            </select>
                            <button class="btn btn-primary" type="submit"><i class="fas fa-save me-1"></i>Mettre à jour</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const tableBody = document.querySelector('#ordersTable tbody');
        const searchInput = document.getElementById('search');
        const statusSelect = document.getElementById('status');
        const btnReset = document.getElementById('btnReset');
        const orderModalEl = document.getElementById('orderModal');
        const orderModal = new bootstrap.Modal(orderModalEl);
        const statusForm = document.getElementById('statusForm');
        const orderIdInput = document.getElementById('order_id');
        const orderStatusInput = document.getElementById('order_status');
        const orderDetails = document.getElementById('orderDetails');

        function fetchList() {
            const params = new URLSearchParams();
            if (searchInput.value.trim()) params.set('search', searchInput.value.trim());
            if (statusSelect.value) params.set('status', statusSelect.value);

            fetch('api/orders.php?action=list&' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(res => {
                    if (!res.success) throw new Error(res.message || 'Erreur');
                    renderRows(res.data || []);
                })
                .catch(err => alert(err.message));
        }

        function renderRows(rows) {
            tableBody.innerHTML = '';
            if (!rows.length) {
                tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Aucune commande</td></tr>';
                return;
            }
            rows.forEach(row => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>#${row.order_number}</strong><br><small class="text-muted">${row.item_count} article(s)</small></td>
                    <td>${escapeHtml((row.first_name || '') + ' ' + (row.last_name || ''))}<br><small class="text-muted">${escapeHtml(row.email || '')}</small></td>
                    <td>${row.order_type || '-'}</td>
                    <td>${formatDate(row.created_at)}</td>
                    <td><span class="badge bg-${badgeForStatus(row.status)}">${row.status}</span></td>
                    <td>${paymentLabel(row.payment_status)}</td>
                    <td>${Number(row.total_amount).toFixed(2)}€</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary me-1" onclick="showDetails(${row.id})"><i class="fas fa-eye"></i></button>
                    </td>`;
                tableBody.appendChild(tr);
            });
        }

        function showDetails(id) {
            fetch('api/orders.php?action=details&id=' + id, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(res => {
                    if (!res.success) throw new Error(res.message || 'Erreur');
                    const { order, items } = res;
                    orderIdInput.value = order.id;
                    orderStatusInput.value = order.status;
                    orderDetails.innerHTML = `
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div><strong>Commande:</strong> #${order.order_number}</div>
                                <div><strong>Date:</strong> ${formatDate(order.created_at)}</div>
                                <div><strong>Type:</strong> ${order.order_type || '-'}</div>
                                <div><strong>Table:</strong> ${order.table_number || '-'}</div>
                            </div>
                            <div class="col-md-6">
                                <div><strong>Total:</strong> ${Number(order.total_amount).toFixed(2)}€</div>
                                <div><strong>Paiement:</strong> ${paymentLabel(order.payment_status)}</div>
                                <div><strong>Notes:</strong> ${escapeHtml(order.notes || '')}</div>
                            </div>
                        </div>
                        <hr>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead><tr><th>Article</th><th>Qté</th><th class="text-end">Prix</th><th class="text-end">Total</th></tr></thead>
                                <tbody>
                                    ${items.map(i => `<tr><td>${escapeHtml(i.name)}</td><td>${i.quantity}</td><td class="text-end">${Number(i.unit_price).toFixed(2)}€</td><td class="text-end">${Number(i.total_price).toFixed(2)}€</td></tr>`).join('')}
                                </tbody>
                            </table>
                        </div>`;
                    orderModal.show();
                })
                .catch(err => alert(err.message));
        }

        statusForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const fd = new FormData(statusForm);
            fd.append('action', 'update_status');
            fetch('api/orders.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            }).then(r => r.json()).then(res => {
                if (!res.success) throw new Error(res.message || 'Erreur');
                orderModal.hide();
                fetchList();
            }).catch(err => alert(err.message));
        });

        function paymentLabel(s) {
            const map = { paid: '<span class="badge bg-success">Payé</span>', refunded: '<span class="badge bg-info text-dark">Remboursé</span>', pending: '<span class="badge bg-secondary">En attente</span>' };
            return map[s] || '-';
        }
        function badgeForStatus(s) {
            return s === 'served' ? 'success' : (s === 'cancelled' ? 'danger' : (s === 'ready' ? 'primary' : 'warning'));
        }
        function escapeHtml(s) {
            return (s || '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c] || c));
        }
        function formatDate(str) {
            try { const d = new Date(str.replace(' ', 'T')); return d.toLocaleString('fr-FR'); } catch { return str; }
        }

        [searchInput, statusSelect].forEach(el => el.addEventListener('input', () => {
            clearTimeout(window._t);
            window._t = setTimeout(fetchList, 250);
        }));
        btnReset.addEventListener('click', () => { searchInput.value=''; statusSelect.value=''; fetchList(); });

        fetchList();
    </script>
</body>
</html>


