<?php
// Sécurité et connexion
include '../includes/nav_sidebar22.php';
require_once '../../config/database.php';
require_once '../../config/security.php';

// session_start();
// Vérifier si l'utilisateur est connecté
if (!Security::isAdmin() && !Security::isManager()) {
    header('Location: ../../login.php');
    exit;
}

require_once 'VisiteurManager.php';

// Récupérer les paramètres de pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 50; // Nombre de visiteurs par page
$offset = ($page - 1) * $limit;
// Connexion à la base de données
$db = $pdo;
// $db = new PDO('mysql:host=localhost;dbname=restaurant_gourmet', 'root', '');
$visiteurManager = new VisiteurManager($db);

// Récupérer les statistiques
// $stats = $visiteurManager->getStatistiquesVisiteurs();
// $visiteurs_temps_reel = $visiteurManager->getVisiteursEnTempsReel();
// $pages_populaires = $visiteurManager->getPagesPopulaires();
// $navigateurs = $visiteurManager->getNavigateursStats();
// $heures_affluence = $visiteurManager->getHeuresAffluence();

// Récupérer les données
$stats = $visiteurManager->getStatistiquesVisiteurs();
$visiteurs_temps_reel = $visiteurManager->getVisiteursEnTempsReel();
$pages_populaires = $visiteurManager->getPagesPopulaires();
$navigateurs = $visiteurManager->getNavigateursStats();
$heures_affluence = $visiteurManager->getHeuresAffluence();
$tous_les_visiteurs = $visiteurManager->getAllVisiteurs($limit, $offset);
$total_visiteurs = $visiteurManager->getTotalVisiteurs();
$total_pages = ceil($total_visiteurs / $limit);
?>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            color: #333;
        }
        #container{
            margin-top: 75px;
            margin-left: 240px;
            position: fixed;
            height: calc(100vh - 70px); 
            overflow-y: auto; 
        }
        .header {
            background: white;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .breadcrumb {
            color: #7f8c8d;
            font-size: 0.9em;
        }
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            padding: 0 20px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #3498db;
        }
        .stat-card.live {
            border-left-color: #e74c3c;
        }
        .stat-card.today {
            border-left-color: #2ecc71;
        }
        .stat-card.week {
            border-left-color: #f39c12;
        }
        .stat-card.month {
            border-left-color: #9b59b6;
        }
        .stat-number {
            font-size: 2.2em;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9em;
            font-weight: 500;
        }
        .charts-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            padding: 0 10px;
            margin-bottom: 30px;
        }
        .chart-container {
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .chart-container.full {
            grid-column: 1 / -1;
        }
        .chart-title {
            margin-bottom: 15px;
            color: #2c3e50;
            font-size: 1.2em;
            font-weight: 600;
        }
        .table-container {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin: 0 20px 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: #f8f9fa;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #e9ecef;
        }
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
        }
        tr:hover {
            background: #f8f9fa;
        }
        @media (max-width: 768px) {
            .charts-container {
                grid-template-columns: 1fr;
            }
            .stats-container {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
        }
    </style>
<div id="container" class="container">
    <div class="col-md-11 col-lg-12 dashboard-content">
        <div class="header">
            <h1>📊 Tableau de Bord des Statistiques</h1>
            <div class="breadcrumb">
                Admin > Statistiques > Vue d'ensemble
            </div>
        </div>
        
        <div class="stats-container">
            <div class="stat-card live">
                <div class="stat-number" id="visiteurs-temps-reel"><?= $visiteurs_temps_reel ?></div>
                <div class="stat-label">En ligne maintenant</div>
            </div>
            
            <div class="stat-card today">
                <div class="stat-number"><?= $stats['aujourdhui'] ?></div>
                <div class="stat-label">Visites aujourd'hui</div>
            </div>
            
            <div class="stat-card week">
                <div class="stat-number"><?= $stats['cette_semaine'] ?></div>
                <div class="stat-label">Cette semaine</div>
            </div>
            
            <div class="stat-card month">
                <div class="stat-number"><?= $stats['ce_mois'] ?></div>
                <div class="stat-label">Ce mois</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total'] ?></div>
                <div class="stat-label">Total visites</div>
            </div>
        </div>

        <div class="charts-container">
            <div class="chart-container">
                <h3 class="chart-title">🌍 Navigateurs utilisés</h3>
                <canvas id="browserChart" width="200" height="100"></canvas>
            </div>

            <div class="chart-container">
                <h3 class="chart-title">📈 Affluence par heure</h3>
                <canvas id="hoursChart" width="200" height="100"></canvas>
            </div>
        </div>

        <div class="table-container">
            <h3 class="chart-title">📄 Pages les plus populaires (7 derniers jours)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Page</th>
                        <th style="text-align: right;">Visites</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($pages_populaires as $page_data): ?>
                    <tr>
                        <td><?= htmlspecialchars($page_data['page_visited']) ?></td>
                        <td style="text-align: right;"><?= $page_data['visites'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Tableau complet des visiteurs -->
        <div class="table-container">
            <h3 class="chart-title">👥 Liste de tous les visiteurs (<?= $total_visiteurs ?> au total)</h3>
            
            <!-- Pagination -->
            <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    Page <?= $page ?> sur <?= $total_pages ?> 
                    (<?= $total_visiteurs ?> visiteurs au total)
                </div>
                <div>
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>" style="padding: 8px 15px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin-right: 10px;">← Précédent</a>
                    <?php endif; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>" style="padding: 8px 15px; background: #3498db; color: white; text-decoration: none; border-radius: 5px;">Suivant →</a>
                    <?php endif; ?>
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>IP</th>
                            <th>Pays</th>
                            <th>Ville</th>
                            <th>Coordonnées</th>
                            <th>Fournisseur</th>
                            <th>Page visitée</th>
                            <th>Referer</th>
                            <th>Navigateur</th>
                            <th>Système</th>
                            <th>Date/Heure</th>
                            <th>Session</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tous_les_visiteurs)): ?>
                            <tr>
                                <td colspan="12" style="text-align: center; padding: 20px; color: #7f8c8d;">
                                    Aucun visiteur enregistré pour le moment.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($tous_les_visiteurs as $visiteur): ?>
                            <tr>
                                <td><?= htmlspecialchars($visiteur['id']) ?></td>
                                
                                <td style="font-family: monospace;">
                                    <?= htmlspecialchars($visiteur['ip_address']) ?>
                                </td>
                                
                                <td>
                                    <span style="display: inline-block; padding: 2px 8px; background: #e8f5e8; border-radius: 12px; font-size: 0.8em;">
                                        <?= htmlspecialchars($visiteur['pays'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                
                                <td>
                                    <?= htmlspecialchars($visiteur['ville'] ?? 'N/A') ?>
                                </td>
                                
                                <td style="font-family: monospace; font-size: 0.8em;">
                                    <?= htmlspecialchars($visiteur['coordonnees'] ?? 'N/A') ?>
                                </td>
                                
                                <td title="<?= htmlspecialchars($visiteur['fournisseur_internet'] ?? '') ?>">
                                    <?= strlen($visiteur['fournisseur_internet'] ?? '') > 20 ? 
                                        substr($visiteur['fournisseur_internet'], 0, 20) . '...' : 
                                        ($visiteur['fournisseur_internet'] ?? 'N/A') ?>
                                </td>
                                
                                <td title="<?= htmlspecialchars($visiteur['page_visited']) ?>">
                                    <?= strlen($visiteur['page_visited']) > 30 ? 
                                        htmlspecialchars(substr($visiteur['page_visited'], 0, 30)) . '...' : 
                                        htmlspecialchars($visiteur['page_visited']) ?>
                                </td>
                                
                                <td title="<?= htmlspecialchars($visiteur['referrer']) ?>">
                                    <?= $visiteur['referrer'] ? 
                                        (strlen($visiteur['referrer']) > 20 ? 
                                            htmlspecialchars(substr($visiteur['referrer'], 0, 20)) . '...' : 
                                            htmlspecialchars($visiteur['referrer'])) 
                                        : '<span style="color: #7f8c8d;">Direct</span>' ?>
                                </td>
                                
                                <td>
                                    <span style="display: inline-block; padding: 2px 8px; background: #e3f2fd; border-radius: 12px; font-size: 0.8em;">
                                        <?= htmlspecialchars($visiteur['navigateur']) ?>
                                    </span>
                                </td>
                                
                                <td>
                                    <span style="display: inline-block; padding: 2px 8px; background: #f3e5f5; border-radius: 12px; font-size: 0.8em;">
                                        <?= htmlspecialchars($visiteur['systeme_exploitation']) ?>
                                    </span>
                                </td>
                                
                                <td style="white-space: nowrap;">
                                    <?= date('d/m/Y H:i', strtotime($visiteur['date_visite'])) ?>
                                </td>
                                
                                <td style="font-family: monospace; font-size: 0.7em;">
                                    <?= substr($visiteur['session_id'], 0, 8) ?>...
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination en bas -->
            <?php if ($total_pages > 1): ?>
            <div style="margin-top: 20px; text-align: center;">
                <?php for ($i = 1; $i <= min($total_pages, 10); $i++): ?>
                    <?php if ($i == $page): ?>
                        <strong style="display: inline-block; padding: 5px 10px; background: #3498db; color: white; border-radius: 3px; margin: 0 2px;"><?= $i ?></strong>
                    <?php else: ?>
                        <a href="?page=<?= $i ?>" style="display: inline-block; padding: 5px 10px; background: #ecf0f1; color: #34495e; text-decoration: none; border-radius: 3px; margin: 0 2px;"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($total_pages > 10): ?>
                    <span style="margin: 0 5px;">...</span>
                    <a href="?page=<?= $total_pages ?>" style="display: inline-block; padding: 5px 10px; background: #ecf0f1; color: #34495e; text-decoration: none; border-radius: 3px; margin: 0 2px;">Dernière (<?= $total_pages ?>)</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
    <script>
        // Graphique des navigateurs
        const browserCtx = document.getElementById('browserChart').getContext('2d');
        const browserChart = new Chart(browserCtx, {
            type: 'doughnut',
            data: {
                labels: [<?php foreach($navigateurs as $nav) { echo "'" . $nav['navigateur'] . "',"; } ?>],
                datasets: [{
                    data: [<?php foreach($navigateurs as $nav) { echo $nav['count'] . ","; } ?>],
                    backgroundColor: [
                        '#3498db', '#2ecc71', '#e74c3c', '#f39c12', '#9b59b6',
                        '#1abc9c', '#34495e', '#d35400', '#c0392b', '#16a085'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                }
            }
        });

        // Graphique des heures d'affluence
        const hoursCtx = document.getElementById('hoursChart').getContext('2d');
        
        // Préparer les données pour les heures
        const hoursData = Array(24).fill(0);
        <?php foreach($heures_affluence as $h): ?>
        hoursData[<?= $h['heure'] ?>] = <?= $h['visites'] ?>;
        <?php endforeach; ?>

        const hoursChart = new Chart(hoursCtx, {
            type: 'line',
            data: {
                labels: Array.from({length: 24}, (_, i) => i + 'h'),
                datasets: [{
                    label: 'Visites',
                    data: hoursData,
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#3498db',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Mise à jour en temps réel des visiteurs
        function updateLiveVisitors() {
            fetch('get_live_visitors.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('visiteurs-temps-reel').textContent = data.count;
                })
                .catch(error => console.error('Erreur:', error));
        }

        // Mettre à jour toutes les 30 secondes
        setInterval(updateLiveVisitors, 30000);
    </script>
</body>
</html>