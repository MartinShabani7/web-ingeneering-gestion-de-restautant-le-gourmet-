<?php
include 'includes/nav_sidebar.php';
require_once '../config/database.php';
require_once '../config/security.php';

if (!Security::isLoggedIn() || !Security::isAdmin()) {
    Security::redirect('../auth/login.php');
}
?>

<!-- Contenu prenicpal -->
<div id="container" class="container">
    <div class="col-md-12 col-lg-12 reservations-content">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h3 mb-0"><i class="fas fa-calendar me-2 text-primary"></i>Réservations</h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#reservationModal" onclick="openCreate()">
                <i class="fas fa-plus me-2"></i>Nouvelle réservation
            </button>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Date</label>
                        <input type="date" id="date" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Statut</label>
                        <select id="status" class="form-select">
                            <option value="">Tous</option>
                            <option value="pending">En attente</option>
                            <option value="confirmed">Confirmée</option>
                            <option value="cancelled">Annulée</option>
                            <option value="completed">Terminée</option>
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
                    <table class="table table-hover align-middle" id="reservationsTable">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Date</th>
                                <th>Heure</th>
                                <th>Personnes</th>
                                <th>Table</th>
                                <th>Statut</th>
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

<!-- Modal -->
    <div class="modal fade" id="reservationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nouvelle réservation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="reservationForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                        <input type="hidden" name="id" id="reservation_id">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Nom *</label><input class="form-control" name="customer_name" id="customer_name" required></div>
                            <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="customer_email" id="customer_email"></div>
                            <div class="col-md-6"><label class="form-label">Téléphone</label><input class="form-control" name="customer_phone" id="customer_phone"></div>
                            <div class="col-md-3"><label class="form-label">Date *</label><input type="date" class="form-control" name="reservation_date" id="reservation_date" required></div>
                            <div class="col-md-3"><label class="form-label">Heure *</label><input type="time" class="form-control" name="reservation_time" id="reservation_time" required></div>
                            <div class="col-md-3"><label class="form-label">Personnes *</label><input type="number" min="1" class="form-control" name="party_size" id="party_size" value="1" required></div>
                            <div class="col-md-3"><label class="form-label">Table</label><input class="form-control" name="table_number" id="table_number"></div>
                            <div class="col-12"><label class="form-label">Demandes spéciales</label><textarea class="form-control" name="special_requests" id="special_requests" rows="2"></textarea></div>
                            <div class="col-md-6">
                                <label class="form-label">Statut</label>
                                <select class="form-select" name="status" id="status_field">
                                    <option value="pending">En attente</option>
                                    <option value="confirmed">Confirmée</option>
                                    <option value="cancelled">Annulée</option>
                                    <option value="completed">Terminée</option>
                                </select>
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
        const tableBody = document.querySelector('#reservationsTable tbody');
        const dateInput = document.getElementById('date');
        const statusSelect = document.getElementById('status');
        const btnReset = document.getElementById('btnReset');
        const modalEl = document.getElementById('reservationModal');
        const modal = new bootstrap.Modal(modalEl);
        const form = document.getElementById('reservationForm');
        const modalTitle = document.getElementById('modalTitle');

        function fetchList() {
            const params = new URLSearchParams();
            if (dateInput.value) params.set('date', dateInput.value);
            if (statusSelect.value) params.set('status', statusSelect.value);

            fetch('api/reservations.php?action=list&' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
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
                tableBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Aucune réservation</td></tr>';
                return;
            }
            rows.forEach(row => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${escapeHtml(row.customer_name)}</strong><br><small class="text-muted">${escapeHtml(row.customer_email || '')}</small></td>
                    <td>${row.reservation_date}</td>
                    <td>${row.reservation_time}</td>
                    <td>${row.party_size}</td>
                    <td>${row.table_number || '-'}</td>
                    <td><span class="badge bg-${badgeForStatus(row.status)}">${row.status}</span></td>
                    <td>
                        <button class="btn btn-sm btn-outline-info me-1" onclick='openEdit(${JSON.stringify(row)})'><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm btn-outline-danger" onclick="removeReservation(${row.id})"><i class="fas fa-trash"></i></button>
                    </td>`;
                tableBody.appendChild(tr);
            });
        }

        function openCreate() {
            form.reset();
            form.actionMode = 'create';
            document.getElementById('reservation_id').value = '';
            modalTitle.textContent = 'Nouvelle réservation';
        }

        function openEdit(row) {
            modal.show();
            form.actionMode = 'update';
            modalTitle.textContent = 'Modifier la réservation';
            document.getElementById('reservation_id').value = row.id;
            document.getElementById('customer_name').value = row.customer_name || '';
            document.getElementById('customer_email').value = row.customer_email || '';
            document.getElementById('customer_phone').value = row.customer_phone || '';
            document.getElementById('reservation_date').value = row.reservation_date || '';
            document.getElementById('reservation_time').value = row.reservation_time || '';
            document.getElementById('party_size').value = row.party_size || 1;
            document.getElementById('table_number').value = row.table_number || '';
            document.getElementById('special_requests').value = row.special_requests || '';
            document.getElementById('status_field').value = row.status || 'pending';
        }

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const fd = new FormData(form);
            const action = form.actionMode === 'update' ? 'update' : 'create';
            fd.append('action', action);
            fetch('api/reservations.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            }).then(r => r.json()).then(res => {
                if (!res.success) throw new Error(res.message || 'Erreur');
                modal.hide();
                fetchList();
            }).catch(err => alert(err.message));
        });

        function removeReservation(id) {
            if (!confirm('Supprimer cette réservation ?')) return;
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);
            fd.append('csrf_token', document.querySelector('#reservationForm [name=csrf_token]').value);
            fetch('api/reservations.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            }).then(r => r.json()).then(res => {
                if (!res.success) throw new Error(res.message || 'Erreur');
                fetchList();
            }).catch(err => alert(err.message));
        }

        function escapeHtml(s) { return (s || '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c] || c)); }
        function badgeForStatus(s) { return s === 'completed' ? 'success' : (s === 'cancelled' ? 'danger' : 'warning'); }

        [dateInput, statusSelect].forEach(el => el.addEventListener('input', () => {
            clearTimeout(window._t);
            window._t = setTimeout(fetchList, 250);
        }));
        btnReset.addEventListener('click', () => { dateInput.value=''; statusSelect.value=''; fetchList(); });

        fetchList();
    </script>
</body>
</html>


