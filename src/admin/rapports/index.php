<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);


include '../includes/nav_sidebar22.php';
require_once '../../config/database.php';
require_once '../../config/security.php';

// session_start();
if (!Security::isAdmin() && !Security::isManager()) {
    header('Location: ../../login.php');
    exit;
}

// Inclure les classes
require_once 'ReportGenerator.php';
require_once 'ReportManager.php';

// Initialiser
$reportManager = new ReportManager($pdo);
$templates = $reportManager->getTemplates();
$history = $reportManager->getReportHistory($_SESSION['user_id']);

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
    $reportGenerator = new ReportGenerator($pdo);
    
    try {
        $templateId = $_POST['template_id'];
        $reportName = $_POST['report_name'];
        $format = $_POST['format'];
        
        $parameters = [
            'start_date' => $_POST['start_date'] ?? '',
            'end_date' => $_POST['end_date'] ?? ''
        ];
        
        // Générer le rapport
        $filename = $reportGenerator->generateReport($templateId, $parameters, $format);
        
        // Sauvegarder l'historique
        $reportManager->saveGeneratedReport([
            'template_id' => $templateId,
            'user_id' => $_SESSION['user_id'],
            'report_name' => $reportName,
            'parameters' => $parameters,
            'file_path' => $filename,
            'file_format' => $format
        ]);
        
        $success = "✅ Rapport généré avec succès! <a href='download.php?file=$filename'>Télécharger</a>";
        
    } catch (Exception $e) {
        $error = "❌ Erreur: " . $e->getMessage();
    }
}
?>


    <style>
        .report-card { transition: transform 0.2s; }
        .report-card:hover { transform: translateY(-2px); }
        .format-badge { font-size: 0.7rem; }
    </style>

<div id="container" class="container">
    <div class="col-md-12 col-lg-11 rapports-content">
        <!-- En-tête -->
        <div class="row mb-4">
            <div class="col">
                <h1 class="h3">📊 Rapports et Exportations</h1>
                <p class="text-muted">Générez et exportez vos données</p>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
            <!-- Filtres -->
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">🔍 Filtres</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" id="filterForm">
                            <div class="mb-3">
                                <label class="form-label">Période</label>
                                <div class="row g-2">
                                    <div class="col-12">
                                        <input type="date" class="form-control" name="start_date" 
                                               value="<?= $_GET['start_date'] ?? '' ?>">
                                    </div>
                                    <div class="col-12">
                                        <input type="date" class="form-control" name="end_date"
                                               value="<?= $_GET['end_date'] ?? '' ?>">
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-outline-primary w-100">
                                Appliquer Filtres
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Historique rapide -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="mb-0">🕐 Derniers rapports</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($history)): ?>
                            <p class="text-muted small">Aucun rapport</p>
                        <?php else: ?>
                            <?php foreach (array_slice($history, 0, 5) as $report): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <small class="d-block"><?= substr($report['report_name'], 0, 20) ?>...</small>
                                        <small class="text-muted"><?= date('d/m', strtotime($report['generated_at'])) ?></small>
                                    </div>
                                    <a href="download.php?file=<?= $report['file_path'] ?>" class="btn btn-sm btn-outline-success">
                                        📥
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Liste des rapports -->
            <div class="col-md-9">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">📋 Rapports disponibles</h5>
                        <span class="badge bg-primary"><?= count($templates) ?> modèles</span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($templates as $template): ?>
                            <div class="col-lg-6 mb-3">
                                <div class="card report-card h-100">
                                    <div class="card-body">
                                        <h6 class="card-title"><?= htmlspecialchars($template['name']) ?></h6>
                                        <p class="card-text small text-muted">
                                            <?= htmlspecialchars($template['description']) ?>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge bg-secondary"><?= $template['category'] ?></span>
                                            <button type="button" class="btn btn-primary btn-sm" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#generateModal"
                                                    data-template-id="<?= $template['id'] ?>"
                                                    data-template-name="<?= htmlspecialchars($template['name']) ?>">
                                                Générer
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
    <!-- Modal de génération -->
    <div class="modal fade" id="generateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Générer un rapport</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="template_id" id="modalTemplateId">
                        <input type="hidden" name="generate_report" value="1">
                        
                        <!-- Reprendre les filtres actuels -->
                        <input type="hidden" name="start_date" value="<?= $_GET['start_date'] ?? '' ?>">
                        <input type="hidden" name="end_date" value="<?= $_GET['end_date'] ?? '' ?>">

                        <div class="mb-3">
                            <label class="form-label">Nom du rapport *</label>
                            <input type="text" class="form-control" name="report_name" required 
                                   placeholder="Ex: Rapport des ventes">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Format d'export *</label>
                            <select class="form-select" name="format" required>
                                <option value="csv">CSV (Excel)</option>
                                <option value="html">HTML (Web/Impression)</option>
                                <option value="pdf">PDF</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">🚀 Générer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Configuration du modal
    var generateModal = document.getElementById('generateModal');
    generateModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        var templateId = button.getAttribute('data-template-id');
        var templateName = button.getAttribute('data-template-name');
        
        document.getElementById('modalTemplateId').value = templateId;
        document.querySelector('input[name="report_name"]').value = templateName + ' - ' + new Date().toLocaleDateString();
    });
    </script>
</body>
</html>