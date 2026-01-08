<?php
include 'includes/nav_sidebar.php';
require_once '../config/database.php';
require_once '../config/security.php';

if (!Security::isLoggedIn() || !Security::isAdmin()) {
    Security::redirect('../auth/login.php');
}

$pageTitle = "Gestion des Services";
?>

    <title><?php echo $pageTitle; ?></title>
    <style>
        .service-preview {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 10px;
            border-left: 4px solid #007bff;
        }

        .status-badge {
            font-size: 0.8rem;
        }

        .icon-preview {
            font-size: 1.5rem;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .btn-action {
            padding: 0.25rem 0.5rem;
            margin: 0 0.1rem;
        }
    </style>

    <!-- Vérifiez que vous avez bien une structure de base comme ça -->
    <!-- <div class="container-fluid py-4"> -->
<div id="container" class="container">
    <div class="col-md-12 col-lg-12 services-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 text-gray-800">
                <i class="fas fa-concierge-bell text-primary me-2"></i>Gestion des Services
            </h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#serviceModal" onclick="openModal()">
                <i class="fas fa-plus me-2"></i>Nouveau Service
            </button>
        </div>

        <!-- Alertes -->
        <div id="alertContainer"></div>

        <!-- Tableau des services -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Liste des Services</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="servicesTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Ordre</th>
                                <th>Icône</th>
                                <th>Titre</th>
                                <th>Bouton</th>
                                <th>Lien</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="servicesTableBody">
                            <!-- Les services seront chargés ici par JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
    <!-- Modal pour ajouter/modifier un service -->
    <div class="modal fade" id="serviceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nouveau Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="serviceForm">
                        <input type="hidden" id="serviceId">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Titre *</label>
                                <input type="text" class="form-control" id="title" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Ordre d'affichage</label>
                                <input type="number" class="form-control" id="sort_order" value="0">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description *</label>
                            <textarea class="form-control" id="description" rows="3" required></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Icône FontAwesome *</label>
                                <input type="text" class="form-control" id="icon" placeholder="fas fa-utensils" required>
                                <small class="text-muted">Ex: fas fa-utensils, fas fa-wine-glass-alt</small>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Texte du bouton *</label>
                                <input type="text" class="form-control" id="button_text" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Lien du bouton *</label>
                                <input type="text" class="form-control" id="button_link" placeholder="pages/menu.php" required>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Couleur bouton</label>
                                <select class="form-select" id="button_color">
                                    <option value="primary">Bleu (Primary)</option>
                                    <option value="success">Vert (Success)</option>
                                    <option value="warning">Orange (Warning)</option>
                                    <option value="danger">Rouge (Danger)</option>
                                    <option value="info">Cyan (Info)</option>
                                    <option value="dark">Noir (Dark)</option>
                                    <option value="purple">Violet (Purple)</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Couleur fond</label>
                                <select class="form-select" id="background_color">
                                    <option value="primary">Bleu (Primary)</option>
                                    <option value="success">Vert (Success)</option>
                                    <option value="warning">Orange (Warning)</option>
                                    <option value="danger">Rouge (Danger)</option>
                                    <option value="info">Cyan (Info)</option>
                                    <option value="dark">Noir (Dark)</option>
                                    <option value="purple">Violet (Purple)</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="is_active" checked>
                            <label class="form-check-label">Service actif</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-primary" onclick="saveService()">Enregistrer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de confirmation de suppression -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmer la suppression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Êtes-vous sûr de vouloir supprimer ce service ? Cette action est irréversible.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Supprimer</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let currentServiceId = null;
        let serviceModal = null;
        let deleteModal = null;

        // Initialiser les modals après le chargement du DOM
        document.addEventListener('DOMContentLoaded', function() {
            serviceModal = new bootstrap.Modal(document.getElementById('serviceModal'));
            deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            loadServices();
        });


        function loadServices() {
            fetch('api/services.php?action=get')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erreur réseau: ' + response.status);
                    }
                    return response.text().then(text => {
                        console.log("Réponse brute services:", text);
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error("Erreur parsing JSON:", e);
                            throw new Error("Réponse invalide: " + text.substring(0, 100));
                        }
                    });
                })
                .then(data => {
                    const tbody = document.getElementById('servicesTableBody');
                    tbody.innerHTML = '';
                    
                    // Vérifier si c'est une réponse d'erreur
                    if (data.success === false) {
                        showAlert('Erreur: ' + (data.error || data.message), 'danger');
                        return;
                    }
                    
                    // data peut être un tableau directement ou un objet avec data
                    const services = Array.isArray(data) ? data : (data.data || []);
                    
                    services.forEach(service => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${service.sort_order}</td>
                            <td>
                                <div class="icon-preview bg-${service.background_color} text-white">
                                    <i class="${service.icon}"></i>
                                </div>
                            </td>
                            <td>${escapeHtml(service.title)}</td>
                            <td>
                                <span class="badge bg-${service.button_color}">${escapeHtml(service.button_text)}</span>
                            </td>
                            <td><small>${escapeHtml(service.button_link)}</small></td>
                            <td>
                                <span class="badge ${service.is_active ? 'bg-success' : 'bg-secondary'} status-badge">
                                    ${service.is_active ? 'Actif' : 'Inactif'}
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary btn-action" onclick="editService(${service.id})" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger btn-action" onclick="confirmDelete(${service.id})" title="Supprimer">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-${service.is_active ? 'warning' : 'success'} btn-action" 
                                        onclick="toggleService(${service.id}, ${service.is_active})" 
                                        title="${service.is_active ? 'Désactiver' : 'Activer'}">
                                    <i class="fas fa-${service.is_active ? 'eye-slash' : 'eye'}"></i>
                                </button>
                            </td>
                        `;
                        tbody.appendChild(row);
                    });
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    showAlert('Erreur lors du chargement des services: ' + error.message, 'danger');
                });
        }

        function openModal(service = null) {
            const form = document.getElementById('serviceForm');
            
            if (service) {
                document.getElementById('modalTitle').textContent = 'Modifier le Service';
                document.getElementById('serviceId').value = service.id;
                document.getElementById('title').value = service.title;
                document.getElementById('description').value = service.description;
                document.getElementById('icon').value = service.icon;
                document.getElementById('button_text').value = service.button_text;
                document.getElementById('button_link').value = service.button_link;
                document.getElementById('button_color').value = service.button_color;
                document.getElementById('background_color').value = service.background_color;
                document.getElementById('sort_order').value = service.sort_order;
                document.getElementById('is_active').checked = service.is_active;
            } else {
                document.getElementById('modalTitle').textContent = 'Nouveau Service';
                form.reset();
                document.getElementById('serviceId').value = '';
                document.getElementById('sort_order').value = 0;
                document.getElementById('is_active').checked = true;
            }
            
            serviceModal.show();
        }

        function saveService() {
            const formData = {
                id: document.getElementById('serviceId').value || null,
                title: document.getElementById('title').value,
                description: document.getElementById('description').value,
                icon: document.getElementById('icon').value,
                button_text: document.getElementById('button_text').value,
                button_link: document.getElementById('button_link').value,
                button_color: document.getElementById('button_color').value,
                background_color: document.getElementById('background_color').value,
                sort_order: parseInt(document.getElementById('sort_order').value),
                is_active: document.getElementById('is_active').checked ? 1 : 0
            };

            // Validation basique
            if (!formData.title || !formData.description || !formData.icon || !formData.button_text || !formData.button_link) {
                showAlert('Veuillez remplir tous les champs obligatoires', 'warning');
                return;
            }

            const method = formData.id ? 'PUT' : 'POST';
            const url = 'api/services.php';

            fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.text().then(text => {
                console.log("Réponse saveService:", text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error("Erreur parsing JSON:", e);
                    throw new Error("Réponse invalide: " + text.substring(0, 100));
                }
            }))
            .then(data => {
                if (data.success === false) {
                    showAlert('Erreur: ' + (data.error || data.message), 'danger');
                } else {
                    showAlert(data.message || 'Service enregistré avec succès', 'success');
                    serviceModal.hide();
                    loadServices();
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showAlert('Erreur lors de l\'enregistrement: ' + error.message, 'danger');
            });
        }

        function toggleService(id, currentStatus) {
            if (!confirm(`Voulez-vous vraiment ${currentStatus ? 'désactiver' : 'activer'} ce service ?`)) {
                return;
            }
            
            const newStatus = !currentStatus;
            
            fetch(`api/services.php?id=${id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ is_active: newStatus ? 1 : 0 })
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Erreur: ' + data.error);
                } else {
                    alert('Service ' + (newStatus ? 'activé' : 'désactivé'));
                    loadServices(); // Recharge la liste
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur: ' + error.message);
            });
        }

        function editService(id) {
            fetch(`api/services.php?id=${id}&action=get`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erreur HTTP: ' + response.status);
                    }
                    return response.json();
                })
                .then(service => {
                    if (service.error) {
                        showAlert('Erreur: ' + service.error, 'danger');
                    } else {
                        openModal(service);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    showAlert('Erreur lors du chargement du service: ' + error.message, 'danger');
                });
        }

        function confirmDelete(id) {
            currentServiceId = id;
            deleteModal.show();
        }

        document.getElementById('confirmDelete').addEventListener('click', function() {
            if (currentServiceId) {
                fetch(`api/services.php?id=${currentServiceId}`, {
                    method: 'DELETE'
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erreur HTTP: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        showAlert('Erreur: ' + data.error, 'danger');
                    } else {
                        showAlert(data.message || 'Service supprimé avec succès', 'success');
                        deleteModal.hide();
                        loadServices();
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    showAlert('Erreur lors de la suppression: ' + error.message, 'danger');
                });
            }
        });

        function toggleService(id, currentStatus) {
            const newStatus = !currentStatus;
            
            fetch(`api/services.php?id=${id}&action=toggle`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ is_active: newStatus ? 1 : 0 })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erreur HTTP: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    showAlert('Erreur: ' + data.error, 'danger');
                } else {
                    showAlert(`Service ${newStatus ? 'activé' : 'désactivé'} avec succès`, 'success');
                    loadServices();
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showAlert('Erreur lors de la modification: ' + error.message, 'danger');
            });
        }


        function showAlert(message, type) {
            const alertContainer = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-dismissible fade show`;
            alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            alertContainer.appendChild(alert);
            
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 5000);
        }

        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    </script>
</body>
</html>