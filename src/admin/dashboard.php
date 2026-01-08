<?php
include 'includes/nav_sidebar.php';
require_once '../config/database.php';
require_once '../config/security.php';
require_once '../include/AuthService.php';

// Vérification de l'authentification et des droits admin
if (!Security::isLoggedIn() || !Security::isAdmin()) {
    Security::redirect('../auth/login.php');
}


// Récupération des données utilisateur 
$user_avatar = $_SESSION['user_avatar'] ?? 'default_avatar.jpg';
$user_name = $_SESSION['user_name'] ?? 'Utilisateur';

// Extraction du prénom et nom depuis user_name
$names = explode(' ', $user_name, 2);
$user_firstname = $names[0] ?? '';
$user_lastname = $names[1] ?? '';
$user_fullname = $user_name;

// Si le nom complet est vide, on utilise une valeur par défaut
if (empty($user_fullname)) {
    $user_fullname = 'Utilisateur';
}

// Statistiques générales
$stats = [];

// Fonction pour exécuter une requête et retourner le résultat (pour simplifier le dashboard)
function fetchStat($sql, $fetchType = 'fetchColumn') {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    if ($fetchType === 'fetchColumn') {
        return $stmt->fetchColumn();
    }
    return $stmt->fetchAll();
}

// Nombre total d'utilisateurs
$stats['users'] = fetchStat("SELECT COUNT(*) FROM users WHERE is_active = 1");

// Nombre total de commandes
$stats['orders'] = fetchStat("SELECT COUNT(*) FROM orders");

// Chiffre d'affaires total
$stats['revenue'] = fetchStat("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'paid'");

// Nombre de réservations
$stats['reservations'] = fetchStat("SELECT COUNT(*) FROM reservations WHERE status IN ('pending', 'confirmed')");

// Statistiques du mois
$monthStats = fetchStat("
    SELECT 
        COUNT(*) as orders_this_month,
        COALESCE(SUM(total_amount), 0) as revenue_this_month
    FROM orders 
    WHERE MONTH(created_at) = MONTH(CURDATE()) 
    AND YEAR(created_at) = YEAR(CURDATE())
", 'fetchAll')[0];

// Commandes récentes
$recentOrders = fetchStat("
    SELECT o.*, u.first_name, u.last_name, u.email,
           COUNT(oi.id) as item_count
    FROM orders o
    LEFT JOIN users u ON o.customer_id = u.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 10
", 'fetchAll');

// Réservations récentes
$recentReservations = fetchStat("
    SELECT r.*, u.first_name, u.last_name, u.email
    FROM reservations r
    LEFT JOIN users u ON r.customer_id = u.id
    ORDER BY r.created_at DESC
    LIMIT 10
", 'fetchAll');

// Produits les plus vendus
$topProducts = fetchStat("
    SELECT p.name, SUM(oi.quantity) as total_sold
    FROM products p
    JOIN order_items oi ON p.id = oi.product_id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.payment_status = 'paid'
    GROUP BY p.id, p.name
    ORDER BY total_sold DESC
    LIMIT 5
", 'fetchAll');

// Activité récente (logs)
$recentActivity = fetchStat("
    SELECT al.*, u.first_name, u.last_name
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 10
", 'fetchAll');
?>

    <style>
        #container{
            margin-top: 75px;
            margin-left: 240px;
            position: fixed;
            height: calc(100vh - 70px); 
            overflow-y: auto; 
        }
    </style>
    
    <div id="container" class="container">
            <!-- Contenu principal -->
            <div class="col-md-12 col-lg-12 dashboard-content">
                <!-- =========================== -->
                <!-- EN-TÊTE PERSONNALISÉE -->
                <!-- =========================== -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div>
                        <div class="d-flex align-items-center mb-2">
                            <!-- Icône utilisateur avec fond coloré -->
                            <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                <i class="fas fa-user-shield text-primary fa-lg"></i>
                            </div>
                            <div>
                                <!-- Message de bienvenue personnalisé -->
                                <h1 class="h3 mb-1">Bienvenue <?= htmlspecialchars($_SESSION['user_name'] ?? 'Administrateur') ?> </h1>
                                <div class="d-flex align-items-center">
                                    <!-- Badge de rôle -->
                                    <span class="badge bg-primary me-2">Administrateur</span>
                                    <!-- Date et heure actuelles -->
                                    <small class="text-muted" id="liveTime">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?= date('d/m/Y - H:i') ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Bouton d'action -->
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <!-- Bouton d'export avec fonction JS -->
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="exportReport()">
                                <i class="fas fa-file-export me-1"></i>Exporter
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Statistiques principales -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card">
                            <div class="stats-icon primary">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stats-content">
                                <h3 class="stats-number"><?= $stats['users'] ?></h3>
                                <p class="stats-label">Utilisateurs actifs</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card">
                            <div class="stats-icon success">
                                <i class="fas fa-shopping-bag"></i>
                            </div>
                            <div class="stats-content">
                                <h3 class="stats-number"><?= $stats['orders'] ?></h3>
                                <p class="stats-label">Commandes totales</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card">
                            <div class="stats-icon warning">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="stats-content">
                                <h3 class="stats-number"><?= number_format($stats['revenue'], 2) ?>$</h3>
                                <p class="stats-label">Chiffre d'affaires</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card">
                            <div class="stats-icon danger">
                                <i class="fas fa-calendar"></i>
                            </div>
                            <div class="stats-content">
                                <h3 class="stats-number"><?= $stats['reservations'] ?></h3>
                                <p class="stats-label">Réservations actives</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Graphiques -->
                <div class="row mb-4">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-line me-2"></i>Évolution des ventes
                                </h5>
                            </div>
                            <div class="card-body">
                                <canvas id="salesChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-pie me-2"></i>Ventes par catégorie
                                </h5>
                            </div>
                            <div class="card-body">
                                <canvas id="categoryChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Commandes récentes -->
                    <div class="col-lg-8 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-shopping-bag me-2"></i>Commandes récentes
                                </h5>
                                <a href="orders.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Commande</th>
                                                <th>Client</th>
                                                <th>Date</th>
                                                <th>Statut</th>
                                                <th>Total</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentOrders as $order): ?>
                                                <tr>
                                                    <td>
                                                        <strong>#<?= htmlspecialchars($order['order_number']) ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?= $order['item_count'] ?> article(s)</small>
                                                    </td>
                                                    <td>
                                                        <?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?>
                                                        <br>
                                                        <small class="text-muted"><?= htmlspecialchars($order['email']) ?></small>
                                                    </td>
                                                    <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= $order['status'] === 'served' ? 'success' : ($order['status'] === 'cancelled' ? 'danger' : 'warning') ?>">
                                                            <?= ucfirst($order['status']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= number_format($order['total_amount'], 2) ?>€</td>
                                                    <td>
                                                        <a href="order_details.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Produits populaires -->
                    <div class="col-lg-4 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-star me-2"></i>Produits populaires
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($topProducts)): ?>
                                    <p class="text-muted text-center">Aucune donnée disponible</p>
                                <?php else: ?>
                                    <?php foreach ($topProducts as $product): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span><?= htmlspecialchars($product['name']) ?></span>
                                            <span class="badge bg-primary"><?= $product['total_sold'] ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Activité récente -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-history me-2"></i>Activité récente
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Utilisateur</th>
                                                <th>Action</th>
                                                <th>Table</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentActivity as $activity): ?>
                                                <tr>
                                                    <td>
                                                        <?= $activity['first_name'] ? htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']) : 'Système' ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info"><?= htmlspecialchars($activity['action']) ?></span>
                                                    </td>
                                                    <td><?= htmlspecialchars($activity['table_name'] ?? '-') ?></td>
                                                    <td><?= date('d/m/Y H:i', strtotime($activity['created_at'])) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="../assets/js/main.js"></script>
    
    <script>
        // ===========================================
        // FONCTIONS D'EXPORT COMPLÈTES
        // ===========================================

        /**
         * Fonction pour l'export PDF
         */
        function exportToPDF() {
            showToast('Génération du PDF en cours...', 'info');
            
            // Option 1: Si vous n'avez pas besoin de données supplémentaires
            generatePDFFromCurrentView();
            
            // Option 2: Si vous avez besoin de données du serveur, décommentez ceci
            // fetchDashboardDataAndGeneratePDF();
        }

        /**
         * Génère le PDF directement depuis la vue actuelle
         */
        function generatePDFFromCurrentView() {
            // Cible un élément spécifique ou utilise le body
            const element = document.querySelector('.dashboard-content') || document.body;
            
            // Options améliorées pour html2canvas
            const options = {
                scale: 2,
                useCORS: true,
                logging: true,
                backgroundColor: '#ffffff',
                allowTaint: true,
                removeContainer: true,
                width: element.scrollWidth,
                height: element.scrollHeight,
                scrollX: 0,
                scrollY: -window.scrollY,
                windowWidth: document.documentElement.scrollWidth,
                windowHeight: document.documentElement.scrollHeight
            };

            html2canvas(element, options)
                .then(canvas => {
                    generatePDFFromCanvas(canvas);
                })
                .catch(error => {
                    console.error('Erreur html2canvas:', error);
                    showToast('Erreur lors de la capture d\'écran: ' + error.message, 'danger');
                });
        }

        /**
         * Génère le PDF depuis un canvas
         */
        function generatePDFFromCanvas(canvas) {
            try {
                const imgData = canvas.toDataURL('image/png', 1.0);
                const pdf = new jspdf.jsPDF({
                    orientation: 'portrait',
                    unit: 'mm',
                    format: 'a4'
                });

                const imgWidth = 210; // A4 width in mm
                const pageHeight = 295; // A4 height in mm
                const imgHeight = (canvas.height * imgWidth) / canvas.width;

                // Calcul simple du nombre de pages
                const totalPages = Math.ceil(imgHeight / pageHeight);

                for (let i = 0; i < totalPages; i++) {
                    if (i > 0) {
                        pdf.addPage();
                    }
                    // Position Y négative pour "scroller" vers le bas de l'image
                    const yPosition = -(i * pageHeight);
                    pdf.addImage(imgData, 'PNG', 0, yPosition, imgWidth, imgHeight);
                }

                // const imgWidth = 210; // A4 width in mm
                // const pageHeight = 295; // A4 height in mm
                // const imgHeight = (canvas.height * imgWidth) / canvas.width;
                
                // let heightLeft = imgHeight;
                // let position = 0;
                // // let pageCount = 1;

                // // Première page
                // pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight, undefined, 'FAST');
                // heightLeft -= pageHeight;

                // // Pages supplémentaires si nécessaire
                // while (heightLeft > 0) {
                //     position = -heightLeft;
                //     // position = heightLeft - imgHeight + (pageHeight * pageCount);
                //     pdf.addPage();
                //     pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight, undefined, 'FAST');
                //     heightLeft -= pageHeight;
                //     // pageCount++;
                // }

                const fileName = `dashboard-le-gourmet-${new Date().toISOString().split('T')[0]}.pdf`;
                pdf.save(fileName);
                showToast('PDF exporté avec succès !', 'success');
                
            } catch (error) {
                console.error('Erreur génération PDF:', error);
                showToast('Erreur lors de la génération du PDF: ' + error.message, 'danger');
            }
        }

        /**
         * Alternative: Si vous avez besoin de données du serveur
         */
        function fetchDashboardDataAndGeneratePDF() {
            fetch('api/dashboard_data.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin' // Important pour les sessions PHP
            })
            .then(response => {
                if (!response.ok) {
                    // Essayez de lire le message d'erreur du serveur
                    return response.text().then(text => {
                        throw new Error(`HTTP ${response.status}: ${text || response.statusText}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    generatePDFFromCurrentView();
                } else {
                    throw new Error(data.message || 'Erreur inconnue du serveur');
                }
            })
            .catch(error => {
                console.error('Erreur fetch:', error);
                showToast('Erreur lors de la récupération des données: ' + error.message, 'danger');
            });
        }

        /**
         * Version alternative avec timeout et meilleure gestion d'erreurs
         */
        function exportToPDFWithRetry() {
            showToast('Préparation de l\'export PDF...', 'info');
            
            // Attendre que le DOM soit complètement chargé
            setTimeout(() => {
                try {
                    // Forcer le rendu des polaces et images
                    document.fonts.ready.then(() => {
                        generatePDFFromCurrentView();
                    });
                } catch (error) {
                    // Fallback si document.fonts n'est pas supporté
                    generatePDFFromCurrentView();
                }
            }, 500);
        }
        
        /**
         * Fonction  pour l'export Excel
         */
        function exportToExcel() {
            showToast('Préparation du fichier Excel...', 'info');
            
            fetch('api/dashboard_data.php')
                // method: 'GET',        
                // headers: {
                //         'X-Requested-With': 'XMLHttpRequest' 
                //     }
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erreur HTTP ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data.success) throw new Error(data.message || 'Erreur de données');
                    
                    const wb = XLSX.utils.book_new();
                    
                    // Données des statistiques
                    const statsData = [
                        ['STATISTIQUES DASHBOARD - LE GOURMET'],
                        ['Généré le', new Date().toLocaleDateString('fr-FR')],
                        ['Généré à', new Date().toLocaleTimeString('fr-FR')],
                        [],
                        ['Métrique', 'Valeur'],
                        ['Utilisateurs actifs', data.stats.users],
                        ['Commandes totales', data.stats.orders],
                        ['Chiffre d\'affaires', data.stats.revenue.toFixed(2) + ' €'],
                        ['Réservations actives', data.stats.reservations],
                        ['Commandes ce mois', data.stats.orders_this_month || 0],
                        ['Revenu ce mois', (data.stats.revenue_this_month || 0).toFixed(2) + ' €']
                    ];

                    // Données des commandes récentes
                    const ordersData = [
                        ['COMMANDES RÉCENTES'],
                        [],
                        ['N° Commande', 'Client', 'Email', 'Date', 'Statut', 'Total (€)', 'Articles']
                    ];

                    if (data.recentOrders && data.recentOrders.length > 0) {
                        data.recentOrders.forEach(order => {
                            ordersData.push([
                                '#' + (order.order_number || 'N/A'),
                                (order.first_name || '') + ' ' + (order.last_name || ''),
                                order.email || '',
                                new Date(order.created_at).toLocaleDateString('fr-FR'),
                                order.status || 'inconnu',
                                parseFloat(order.total_amount || 0).toFixed(2),
                                order.item_count || 0
                            ]);
                        });
                    } else {
                        ordersData.push(['Aucune commande récente']);
                    }

                    // Données des produits populaires
                    const productsData = [
                        ['PRODUITS POPULAIRES'],
                        [],
                        ['Produit', 'Quantité vendue']
                    ];

                    if (data.topProducts && data.topProducts.length > 0) {
                        data.topProducts.forEach(product => {
                            productsData.push([
                                product.name || 'Produit inconnu',
                                product.total_sold || 0
                            ]);
                        });
                    } else {
                        productsData.push(['Aucune donnée disponible']);
                    }

                    // Ajouter les feuilles au workbook
                    XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(statsData), 'Statistiques');
                    XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(ordersData), 'Commandes');
                    XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(productsData), 'Produits');

                    // Générer et télécharger le fichier
                    XLSX.writeFile(wb, `rapport-dashboard-${new Date().toISOString().split('T')[0]}.xlsx`);
                    showToast('Fichier Excel exporté avec succès !', 'success');
                })
                .catch(error => {
                    console.error('Erreur export Excel:', error);
                    showToast('Erreur lors de l\'export Excel: ' + error.message, 'danger');
                });
        }

        /**
         * Fonction pour l'export CSV
         */
        function exportToCSV() {
            showToast('Génération du CSV...', 'info');
            
            fetch('./api/dashboard_data.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erreur HTTP ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data.success) throw new Error(data.message || 'Erreur de données');

                    let csvContent = "DATA:DASHBOARD LE GOURMET\n";
                    csvContent += "Généré le:," + new Date().toLocaleDateString('fr-FR') + "\n";
                    csvContent += "Généré à:," + new Date().toLocaleTimeString('fr-FR') + "\n\n";
                    
                    // Section Statistiques
                    csvContent += "STATISTIQUES\n";
                    csvContent += "Métrique,Valeur\n";
                    csvContent += "Utilisateurs actifs," + data.stats.users + "\n";
                    csvContent += "Commandes totales," + data.stats.orders + "\n";
                    csvContent += "Chiffre d'affaires," + data.stats.revenue.toFixed(2) + "\n";
                    csvContent += "Réservations actives," + data.stats.reservations + "\n";
                    csvContent += "Commandes ce mois," + (data.stats.orders_this_month || 0) + "\n";
                    csvContent += "Revenu ce mois," + (data.stats.revenue_this_month || 0).toFixed(2) + "\n\n";
                    
                    // Section Commandes récentes
                    csvContent += "COMMANDES RÉCENTES\n";
                    csvContent += "N° Commande,Client,Email,Date,Statut,Total,Articles\n";
                    
                    if (data.recentOrders && data.recentOrders.length > 0) {
                        data.recentOrders.forEach(order => {
                            csvContent += `"#${order.order_number || 'N/A'}","${(order.first_name || '') + ' ' + (order.last_name || '')}","${order.email || ''}","${new Date(order.created_at).toLocaleDateString('fr-FR')}","${order.status || 'inconnu'}",${parseFloat(order.total_amount || 0).toFixed(2)},${order.item_count || 0}\n`;
                        });
                    } else {
                        csvContent += "Aucune commande récente\n";
                    }
                    
                    csvContent += "\n";
                    
                    // Section Produits populaires
                    csvContent += "PRODUITS POPULAIRES\n";
                    csvContent += "Produit,Quantité vendue\n";
                    
                    if (data.topProducts && data.topProducts.length > 0) {
                        data.topProducts.forEach(product => {
                            csvContent += `"${product.name || 'Produit inconnu'}",${product.total_sold || 0}\n`;
                        });
                    } else {
                        csvContent += "Aucune donnée disponible\n";
                    }

                    // Créer et télécharger le fichier CSV
                    const blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
                    const link = document.createElement('a');
                    const url = URL.createObjectURL(blob);
                    link.setAttribute('href', url);
                    link.setAttribute('download', `rapport-dashboard-${new Date().toISOString().split('T')[0]}.csv`);
                    link.style.visibility = 'hidden';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);
                    
                    showToast('Fichier CSV exporté avec succès !', 'success');
                })
                .catch(error => {
                    console.error('Erreur export CSV:', error);
                    showToast('Erreur lors de l\'export CSV: ' + error.message, 'danger');
                });
        }

        /**
         * Fonction pour les mises à jour
         */
        function checkForUpdates() {
            if (isUpdating) return;
            
            fetch('./api/check_updates.php?last_update=' + encodeURIComponent(lastUpdateTime))
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erreur HTTP ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success && data.hasUpdates) {
                        lastUpdateTime = data.currentTime;
                        
                        const detailMsg = [];
                        if (data.details.newOrders > 0) detailMsg.push(`📦 ${data.details.newOrders} commande(s)`);
                        if (data.details.newReservations > 0) detailMsg.push(`📅 ${data.details.newReservations} réservation(s)`);
                        if (data.details.newUsers > 0) detailMsg.push(`👤 ${data.details.newUsers} utilisateur(s)`);
                        
                        if (detailMsg.length > 0) {
                            showToast('Nouveautés: ' + detailMsg.join(', '), 'info');
                            refreshDashboardData();
                        }
                    }
                })
                .catch(error => console.error('Erreur vérification updates:', error));
        }

        /**
         * Fonction pour l'actualisation des données
         */
        function refreshDashboardData() {
            if (isUpdating) return;
            isUpdating = true;
            
            const exportBtn = document.querySelector('[onclick="exportReport()"]');
            const originalHtml = exportBtn.innerHTML;
            exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Mise à jour...';
            exportBtn.disabled = true;
            
            fetch('./api/dashboard_data.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erreur HTTP ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        lastUpdateTime = data.timestamp;
                        updateAllStatistics(data.stats);
                        updateRecentOrders(data.recentOrders);
                        updateTopProducts(data.topProducts);
                        updateCharts(data.chartData);
                        
                        const timeElement = document.getElementById('liveTime');
                        if (timeElement) {
                            timeElement.innerHTML = `
                                <i class="fas fa-calendar me-1"></i>
                                ${new Date().toLocaleDateString('fr-FR')} - ${new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}
                                <small class="ms-2 text-success">✓ Actualisé</small>
                            `;
                        }
                    }
                })
                .catch(error => {
                    console.error('Erreur actualisation:', error);
                    showToast('Erreur lors de l\'actualisation: ' + error.message, 'danger');
                })
                .finally(() => {
                    isUpdating = false;
                    exportBtn.innerHTML = originalHtml;
                    exportBtn.disabled = false;
                });
        }
        /**
         * Fonction principale d'export avec menu de sélection
         */
        function exportReport() {
            const modalHTML = `
                <div class="modal fade" id="exportModal" tabindex="-1">
                    <div class="modal-dialog modal-sm">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-download me-2"></i>Exporter le rapport
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body text-center">
                                <p class="text-muted mb-3">Choisissez le format d'export :</p>
                                <div class="d-grid gap-2">
                                    <button class="btn btn-danger" onclick="exportToPDF()">
                                        <i class="fas fa-file-pdf me-2"></i>PDF
                                    </button>
                                    <button class="btn btn-success" onclick="exportToExcel()">
                                        <i class="fas fa-file-excel me-2"></i>Excel
                                    </button>
                                    <button class="btn btn-primary" onclick="exportToCSV()">
                                        <i class="fas fa-file-csv me-2"></i>CSV
                                    </button>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            if (!document.getElementById('exportModal')) {
                document.body.insertAdjacentHTML('beforeend', modalHTML);
            }
            
            const exportModal = new bootstrap.Modal(document.getElementById('exportModal'));
            exportModal.show();
        }

        /**
         * Affiche une notification toast
         */
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
            toast.style.zIndex = '9999';
            toast.innerHTML = `
                <i class="fas fa-${getToastIcon(type)} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 5000);
        }

        /**
         * Retourne l'icône appropriée pour le type de toast
         */
        function getToastIcon(type) {
            const icons = {
                'success': 'check-circle',
                'danger': 'exclamation-circle',
                'warning': 'exclamation-triangle',
                'info': 'info-circle'
            };
            return icons[type] || 'info-circle';
        }

        // ===========================================
        // SYSTÈME DE MISE À JOUR EN TEMPS RÉEL 
        // ===========================================

        let lastUpdateTime = '<?= date('Y-m-d H:i:s') ?>';
        let isUpdating = false; // Éviter les doubles actualisations
        let salesChart, categoryChart;

        /**
         * Vérifie les mises à jour en temps réel
         */
        function checkForUpdates() {
            if (isUpdating) return; // Éviter les conflits
            
            fetch('api/check_updates.php?last_update=' + encodeURIComponent(lastUpdateTime))
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.hasUpdates) {
                        lastUpdateTime = data.currentTime;
                        
                        const detailMsg = [];
                        if (data.details.newOrders > 0) detailMsg.push(`📦 ${data.details.newOrders} commande(s)`);
                        if (data.details.newReservations > 0) detailMsg.push(`📅 ${data.details.newReservations} réservation(s)`);
                        if (data.details.newUsers > 0) detailMsg.push(`👤 ${data.details.newUsers} utilisateur(s)`);
                        
                        showToast('Nouveautés: ' + detailMsg.join(', '), 'info');
                        refreshDashboardData();
                    }
                })
                .catch(error => console.error('Erreur vérification updates:', error));
        }

        /**
         * Actualise les données du dashboard via AJAX
         */
        function refreshDashboardData() {
            if (isUpdating) return;
            isUpdating = true;
            
            // Afficher un indicateur discret
            const exportBtn = document.querySelector('[onclick="exportReport()"]');
            const originalHtml = exportBtn.innerHTML;
            exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Mise à jour...';
            exportBtn.disabled = true;
            
            fetch('api/dashboard_data.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        lastUpdateTime = data.timestamp;
                        updateAllStatistics(data.stats);
                        updateRecentOrders(data.recentOrders);
                        updateTopProducts(data.topProducts);
                        updateCharts(data.chartData);
                        
                        // Mettre à jour le timestamp dans l'interface
                        const timeElement = document.getElementById('liveTime');
                        if (timeElement) {
                            timeElement.innerHTML = `
                                <i class="fas fa-calendar me-1"></i>
                                ${new Date().toLocaleDateString('fr-FR')} - ${new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}
                                <small class="ms-2 text-success">✓ Actualisé</small>
                            `;
                        }
                    }
                })
                .catch(error => {
                    console.error('Erreur actualisation:', error);
                    showToast('Erreur lors de l\'actualisation', 'danger');
                })
                .finally(() => {
                    isUpdating = false;
                    // Restaurer le bouton export
                    exportBtn.innerHTML = originalHtml;
                    exportBtn.disabled = false;
                });
        }

        /**
         * Met à jour TOUTES les statistiques correctement
         */
        function updateAllStatistics(stats) {
            // Mettre à jour les 4 cartes principales
            const statCards = document.querySelectorAll('.stats-card');
            
            if (statCards.length >= 4) {
                // Carte 1: Utilisateurs
                statCards[0].querySelector('.stats-number').textContent = stats.users;
                
                // Carte 2: Commandes
                statCards[1].querySelector('.stats-number').textContent = stats.orders;
                
                // Carte 3: Chiffre d'affaires
                statCards[2].querySelector('.stats-number').textContent = stats.revenue.toFixed(2) + '$';
                
                // Carte 4: Réservations
                statCards[3].querySelector('.stats-number').textContent = stats.reservations;
            }
            
            // Mettre à jour aussi les stats du mois si elles sont affichées
            const monthStatsElements = document.querySelectorAll('[data-stat="month"]');
            monthStatsElements.forEach(element => {
                if (element.textContent.includes('Mois')) {
                    element.textContent = `Ce mois: ${stats.orders_this_month} commandes, ${stats.revenue_this_month.toFixed(2)}$`;
                }
            });
        }

        // ===========================================
        // INITIALISATION AU CHARGEMENT DE LA PAGE
        // ===========================================

        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
            setInterval(updateLiveTime, 1000); // Heure en temps réel
            setInterval(checkForUpdates, 10000); // Vérif updates toutes les 10s
            checkForUpdates(); // Première vérification immédiate
            
            // Vérifier aussi quand la page redevient visible
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    checkForUpdates();
                }
            });
        });

        /**
         * Met à jour le tableau des commandes récentes
         */
        function updateRecentOrders(orders) {
            const tbody = document.querySelector('.table tbody');
            if (!tbody) return;
            
            tbody.innerHTML = '';
            
            orders.forEach(order => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>
                        <strong>#${order.order_number}</strong>
                        <br>
                        <small class="text-muted">${order.item_count} article(s)</small>
                    </td>
                    <td>
                        ${order.first_name} ${order.last_name}
                        <br>
                        <small class="text-muted">${order.email}</small>
                    </td>
                    <td>${new Date(order.created_at).toLocaleDateString('fr-FR')} ${new Date(order.created_at).toLocaleTimeString('fr-FR')}</td>
                    <td>
                        <span class="badge bg-${order.status === 'served' ? 'success' : (order.status === 'cancelled' ? 'danger' : 'warning')}">
                            ${order.status.charAt(0).toUpperCase() + order.status.slice(1)}
                        </span>
                    </td>
                    <td>${parseFloat(order.total_amount).toFixed(2)}€</td>
                    <td>
                        <a href="order_details.php?id=${order.id}" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        /**
         * Met à jour la liste des produits populaires
         */
        function updateTopProducts(products) {
            const container = document.querySelector('.card .card-body');
            if (!container) return;
            
            if (products.length === 0) {
                container.innerHTML = '<p class="text-muted text-center">Aucune donnée disponible</p>';
                return;
            }
            
            let html = '';
            products.forEach(product => {
                html += `
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>${product.name}</span>
                        <span class="badge bg-primary">${product.total_sold}</span>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        /**
         * Met à jour les graphiques avec les nouvelles données
         */
        function updateCharts(chartData) {
            if (salesChart && chartData.monthlyRevenue) {
                salesChart.data.labels = chartData.monthlyRevenue.labels;
                salesChart.data.datasets[0].data = chartData.monthlyRevenue.data;
                salesChart.update('none');
            }
            
            if (categoryChart && chartData.revenueByCategory) {
                categoryChart.data.labels = chartData.revenueByCategory.labels;
                categoryChart.data.datasets[0].data = chartData.revenueByCategory.data;
                if (chartData.revenueByCategory.backgroundColors) {
                    categoryChart.data.datasets[0].backgroundColor = chartData.revenueByCategory.backgroundColors;
                }
                categoryChart.update('none');
            }
        }

        /**
         * Met à jour l'heure en temps réel dans l'en-tête
         */
        function updateLiveTime() {
        const timeElement = document.getElementById('liveTime');
        if (timeElement) {
            const now = new Date();
            timeElement.innerHTML = `
                <i class="fas fa-calendar me-1"></i>
                ${now.toLocaleDateString('fr-FR')} - ${now.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}
            `;
        }
    }

        // ===========================================
        // INITIALISATION DES GRAPHIQUES
        // ===========================================
        
        function initializeCharts() {
            const salesCtx = document.getElementById('salesChart').getContext('2d');
            salesChart = new Chart(salesCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun'],
                    datasets: [{
                        label: 'Ventes (€)',
                        data: [12000, 19000, 15000, 25000, 22000, 30000],
                        borderColor: '#d4af37',
                        backgroundColor: 'rgba(212, 175, 55, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true } }
                }
            });

            const categoryCtx = document.getElementById('categoryChart').getContext('2d');
            categoryChart = new Chart(categoryCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Entrées', 'Plats', 'Desserts', 'Boissons'],
                    datasets: [{
                        data: [30, 40, 20, 10],
                        backgroundColor: ['#d4af37', '#2c3e50', '#e74c3c', '#3498db']
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }

                /**
         * Met à jour TOUTES les statistiques correctement
         */
        function updateAllStatistics(stats) {
            // Mettre à jour les 4 cartes principales
            const statCards = document.querySelectorAll('.stats-card');
            
            if (statCards.length >= 4) {
                // Carte 1: Utilisateurs
                statCards[0].querySelector('.stats-number').textContent = stats.users;
                
                // Carte 2: Commandes
                statCards[1].querySelector('.stats-number').textContent = stats.orders;
                
                // Carte 3: Chiffre d'affaires
                statCards[2].querySelector('.stats-number').textContent = stats.revenue.toFixed(2) + '$';
                
                // Carte 4: Réservations
                statCards[3].querySelector('.stats-number').textContent = stats.reservations;
            }
            
            // Mettre à jour aussi les stats du mois si elles sont affichées
            const monthStatsElements = document.querySelectorAll('[data-stat="month"]');
            monthStatsElements.forEach(element => {
                if (element.textContent.includes('Mois')) {
                    element.textContent = `Ce mois: ${stats.orders_this_month} commandes, ${stats.revenue_this_month.toFixed(2)}$`;
                }
            });
        }

        // ===========================================
        // INITIALISATION AU CHARGEMENT DE LA PAGE
        // ===========================================

        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
            setInterval(updateLiveTime, 1000);
            setInterval(checkForUpdates, 10000); // Vérif toutes les 10 secondes
            checkForUpdates(); // Vérif immédiate
        });

    </script>
</body>
</html>