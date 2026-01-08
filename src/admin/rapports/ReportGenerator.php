<?php
// admin/rapports/ReportGenerator.php

class ReportGenerator {
    private $pdo;
    private $exportsDir;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->exportsDir = __DIR__ . '/exports/';
        
        // Créer le dossier exports s'il n'existe pas
        if (!is_dir($this->exportsDir)) {
            mkdir($this->exportsDir, 0755, true);
        }
    }
    
    /**
     * Génère un rapport
     */
    public function generateReport($templateId, $parameters = [], $format = 'csv') {
        // Récupérer le template
        $template = $this->getTemplate($templateId);
        if (!$template) {
            throw new Exception("Template non trouvé");
        }
        
        // Appliquer les paramètres
        $sql = $this->applyParameters($template['sql_query'], $parameters);
        
        // Exécuter la requête
        $data = $this->executeQuery($sql);
        
        // Générer le fichier
        return $this->generateFile($data, $template, $parameters, $format);
    }
    
    /**
     * Récupère un template
     */
    private function getTemplate($templateId) {
        $stmt = $this->pdo->prepare("SELECT * FROM report_templates WHERE id = ?");
        $stmt->execute([$templateId]);
        return $stmt->fetch();
    }
    
        /**
     * Gère les paramètres dynamiques pour les templates avec filtres spéciaux
     */
    private function handleSpecialParameters($sql, $parameters) {
        // Pour les templates avec LIKE ? (ex: rôle utilisateur)
        if (strpos($sql, 'role LIKE ?') !== false) {
            if (isset($parameters['role']) && $parameters['role']) {
                $sql = str_replace('role LIKE ?', "role LIKE '%{$parameters['role']}%'", $sql);
            } else {
                $sql = str_replace('role LIKE ?', "role LIKE '%'", $sql);
            }
        }
        
        // Pour les templates avec is_active = ?
        if (strpos($sql, 'is_active = ?') !== false) {
            if (isset($parameters['active_only']) && $parameters['active_only']) {
                $sql = str_replace('is_active = ?', 'is_active = 1', $sql);
            } else {
                $sql = str_replace('is_active = ?', 'is_active IS NOT NULL', $sql);
            }
        }
        
        return $sql;
    }
    /**
     * Applique les paramètres à la requête SQL
     */
    // private function applyParameters($sql, $parameters) {
    //     if (isset($parameters['start_date']) && $parameters['start_date']) {
    //         $sql = str_replace('?', "'{$parameters['start_date']}'", $sql);
    //     }
    //     if (isset($parameters['end_date']) && $parameters['end_date']) {
    //         $sql = str_replace('?', "'{$parameters['end_date']}'", $sql);
    //     }
    //     return $sql;
    // }

    private function applyParameters($sql, $parameters) {
        // Gérer les paramètres spéciaux
        if (strpos($sql, '?') !== false) {
            $sql = $this->handleSpecialParameters($sql, $parameters);
        }
        
        // Compter le nombre de ? dans la requête
        $questionMarkCount = substr_count($sql, '?');
        
        // Si pas de ?, retourner le SQL tel quel
        if ($questionMarkCount === 0) {
            return $sql;
        }
        
        // Remplacer CHAQUE ? par NULL (ou une valeur par défaut)
        for ($i = 0; $i < $questionMarkCount; $i++) {
            $value = "NULL"; // Valeur par défaut
            
            // Premier ? = start_date
            if ($i === 0 && isset($parameters['start_date']) && $parameters['start_date']) {
                $value = "'" . addslashes($parameters['start_date']) . "'";
            }
            // Deuxième ? = end_date  
            elseif ($i === 1 && isset($parameters['end_date']) && $parameters['end_date']) {
                $value = "'" . addslashes($parameters['end_date']) . "'";
            }
            
            // Remplacer le premier ? trouvé
            $pos = strpos($sql, '?');
            if ($pos !== false) {
                $sql = substr_replace($sql, $value, $pos, 1);
            }
        }
        
        return $sql;
    }
    
    /**
     * Exécute la requête SQL
     */
    // private function executeQuery($sql) {
    //     $stmt = $this->pdo->prepare($sql);
    //     $stmt->execute();
    //     return $stmt->fetchAll();
    // }

    private function executeQuery($sql) {
        return $this->pdo->query($sql)->fetchAll();
    }
    
    /**
     * Génère le fichier selon le format
     */
    private function generateFile($data, $template, $parameters, $format) {
        switch ($format) {
            case 'csv':
                return $this->generateCSV($data, $template);
            case 'html':
                return $this->generateHTML($data, $template, $parameters);
            case 'pdf':
                return $this->generatePDF($data, $template, $parameters);
            default:
                return $this->generateCSV($data, $template);
        }
    }
    
    /**
     * Génère un fichier CSV
     */
    private function generateCSV($data, $template) {
        $filename = 'rapport_' . $this->sanitizeFilename($template['name']) . '_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = $this->exportsDir . $filename;
        
        $headers = json_decode($template['columns_config'], true);
        
        // Ouvrir le fichier
        $file = fopen($filepath, 'w');
        fwrite($file, "\xEF\xBB\xBF"); // BOM pour Excel
        
        // En-têtes
        $headerRow = [];
        foreach ($headers as $columnName => $config) {
            $headerRow[] = $config['display_name'];
        }
        fputcsv($file, $headerRow, ';');
        
        // Données
        foreach ($data as $record) {
            $dataRow = [];
            foreach ($headers as $columnName => $config) {
                $dataRow[] = $record[$columnName] ?? '';
            }
            fputcsv($file, $dataRow, ';');
        }
        
        fclose($file);
        return $filename;
    }
    
    // /**
    //  * Génère un fichier HTML
    //  */

    // private function generateHTML($data, $template, $parameters) {
    //     $filename = 'rapport_' . $this->sanitizeFilename($template['name']) . '_' . date('Y-m-d_H-i-s') . '.html';
    //     $filepath = $this->exportsDir . $filename;
        
    //     $headers = json_decode($template['columns_config'], true);
        
    //     // Chemin du logo (ajustez selon votre structure)
    //     $logoPath = __DIR__ . '/../../assets/img/logo.jpg';
    //     $logoImg = '';
        
    //     if (file_exists($logoPath)) {
    //         // Logo amélioré avec bord arrondi
    //         $logoImg = '<img src="data:image/jpg;base64,' . base64_encode(file_get_contents($logoPath)) . '" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid #2c3e50; box-shadow: 0 4px 8px rgba(0,0,0,0.1);" alt="Le Gourmet">';
    //     } else {
    //         // Logo par défaut amélioré
    //         $logoImg = '<div style="width: 80px; height: 80px; background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; border-radius: 50%; font-size: 24px; border: 3px solid #2c3e50; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">LG</div>';
    //     }
        
    //     $html = '<!DOCTYPE html>
    //     <html>
    //     <head>
    //         <meta charset="UTF-8">
    //         <title>' . htmlspecialchars($template['name']) . ' - Le Gourmet</title>
    //         <style>
    //             body { font-family: Arial, sans-serif; margin: 20px; }
    //             .header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #333; }
    //             .logo { flex-shrink: 0; }
    //             .title-container { text-align: center; flex-grow: 1; }
    //             .restaurant-title { font-size: 28px; color: #2c3e50; margin: 0; }
    //             .report-title { font-size: 18px; color: #e74c3c; margin: 5px 0; }
    //             .report-info { font-size: 12px; color: #7f8c8d; }
    //             table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    //             th { background: #2c3e50; color: white; padding: 12px; text-align: left; }
    //             td { padding: 10px; border: 1px solid #ddd; }
    //             tr:nth-child(even) { background: #f9f9f9; }
    //             .footer { margin-top: 30px; text-align: center; color: #666; font-size: 12px; border-top: 1px solid #ddd; padding-top: 15px; }
    //         </style>
    //     </head>
    //     <body>
    //         <div class="header">
    //             <div class="logo">' . $logoImg . '</div>
    //             <div class="title-container">
    //                 <h1 class="restaurant-title">Le Gourmet</h1>
    //                 <h2 class="report-title">' . htmlspecialchars($template['name']) . '</h2>
    //                 <div class="report-info">
    //                     <p>Généré le: ' . date('d/m/Y à H:i:s');
        
    //     if (isset($parameters['start_date']) && $parameters['start_date']) {
    //         $html .= '<br>Période: ' . htmlspecialchars($parameters['start_date']) . ' au ' . htmlspecialchars($parameters['end_date']);
    //     }
        
    //     $html .= '<br>Utilisateur: ' . htmlspecialchars($_SESSION['user_name'] ?? 'Administrateur') . ' | Catégorie: ' . htmlspecialchars($template['category']) . '
    //                     </p>
    //                 </div>
    //             </div>
    //             <div class="logo">' . $logoImg . '</div>
    //         </div>
    //         <table>
    //             <thead><tr>';
        
    //     foreach ($headers as $columnName => $config) {
    //         $html .= '<th>' . htmlspecialchars($config['display_name']) . '</th>';
    //     }
        
    //     $html .= '</tr></thead><tbody>';
        
    //     foreach ($data as $row) {
    //         $html .= '<tr>';
    //         foreach ($headers as $columnName => $config) {
    //             $html .= '<td>' . htmlspecialchars($row[$columnName] ?? '') . '</td>';
    //         }
    //         $html .= '</tr>';
    //     }
        
    //     $html .= '</tbody></table>
    //         <div class="footer">
    //             <p>Système Le Gourmet © ' . date('Y') . ' - Généré par ' . ($_SESSION['user_name'] ?? 'Administrateur') . '</p>
    //         </div>
    //     </body>
    //     </html>';
        
    //     file_put_contents($filepath, $html);
    //     return $filename;
    // }

    private function generateHTML($data, $template, $parameters) {
        $filename = 'rapport_' . $this->sanitizeFilename($template['name']) . '_' . date('Y-m-d_H-i-s') . '.html';
        $filepath = $this->exportsDir . $filename;
        
        $headers = json_decode($template['columns_config'], true);
        
        // Chemin du logo (ajustez selon votre structure)
        $logoPath = __DIR__ . '/../../assets/img/logo.jpg';
        $logoImg = '';
        
        if (file_exists($logoPath)) {
            // SEULE MODIFICATION : Logo 80px avec border-radius 50%
            $logoImg = '<img src="data:image/jpg;base64,' . base64_encode(file_get_contents($logoPath)) . '" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover;" alt="Le Gourmet">';
        } else {
            // Logo par défaut
            $logoImg = '<div style="width: 80px; height: 80px; background: #e74c3c; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; border-radius: 50%;">LG</div>';
        }
        
        // GARDEZ VOTRE CODE HTML EXISTANT (sans emojis, badges, etc.)
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . htmlspecialchars($template['name']) . ' - Le Gourmet</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { 
                width: 100%;
                margin-bottom: 30px; 
                padding-bottom: 20px; 
                border-bottom: 2px solid #333;
                text-align: center;
                position: relative;
                }
                .logo-left {
                    position: absolute;
                    left: 0;
                    top: 0;
                }
                .logo-right {
                    position: absolute;
                    right: 0;
                    top: 0;
                }
                .title-container { 
                    display: inline-block;
                    text-align: center;
                }
                .restaurant-title { font-size: 28px; color: #2c3e50; margin: 0; }
                .report-title { font-size: 18px; color: #e74c3c; margin: 5px 0; }
                .report-info { font-size: 12px; color: #7f8c8d; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th { background: #2c3e50; color: white; padding: 12px; text-align: left; }
                td { padding: 10px; border: 1px solid #ddd; }
                tr:nth-child(even) { background: #f9f9f9; }
                .footer { margin-top: 30px; text-align: center; color: #666; font-size: 12px; border-top: 1px solid #ddd; padding-top: 15px; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="logo-left">' . $logoImg . '</div>
                <div class="title-container">
                    <h1 class="restaurant-title">Le Gourmet</h1>
                    <h2 class="report-title">' . htmlspecialchars($template['name']) . '</h2>
                    <div class="report-info">
                        <p>Généré le: ' . date('d/m/Y à H:i:s');
        
        if (isset($parameters['start_date']) && $parameters['start_date']) {
            $html .= '<br>Période: ' . htmlspecialchars($parameters['start_date']) . ' au ' . htmlspecialchars($parameters['end_date']);
        }
        
        $html .= '<br>Utilisateur: ' . htmlspecialchars($_SESSION['user_name'] ?? 'Administrateur') . ' | Catégorie: ' . htmlspecialchars($template['category']) . '
                        </p>
                    </div>
                </div>
                <div class="logo-right">' . $logoImg . '</div>
            </div>
            <table>
                <thead><tr>';
        
        foreach ($headers as $columnName => $config) {
            $html .= '<th>' . htmlspecialchars($config['display_name']) . '</th>';
        }
        
        $html .= '</tr></thead><tbody>';
        
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($headers as $columnName => $config) {
                $html .= '<td>' . htmlspecialchars($row[$columnName] ?? '') . '</td>';
            }
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>
            <div class="footer">
                <p>Système Le Gourmet © ' . date('Y') . ' - Généré par ' . ($_SESSION['user_name'] ?? 'Administrateur') . '</p>
            </div>
        </body>
        </html>';
        
        file_put_contents($filepath, $html);
        return $filename;
    }
    
    
    /**
     * Génère un vrai fichier PDF avec DomPDF
     */

    private function generatePDF($data, $template, $parameters) {
        $filename = 'rapport_' . $this->sanitizeFilename($template['name']) . '_' . date('Y-m-d_H-i-s') . '.pdf';
        $filepath = $this->exportsDir . $filename;
        
        // 1. Générer le HTML d'abord (gardez votre en-tête avec logos)
        $htmlFile = $this->generateHTML($data, $template, $parameters);
        $htmlContent = file_get_contents($this->exportsDir . $htmlFile);
        
        // 2. Convertir en vrai PDF avec DomPDF
        require_once __DIR__ . '/../../vendor/autoload.php';
        $dompdf = new Dompdf\Dompdf();
        
        $dompdf->loadHtml($htmlContent);
        // $dompdf->setPaper('A4', 'portrait'); // portrait par défaut
        $dompdf->setPaper('A4', 'landscape'); // landscape pour l'impression en paysage
        $dompdf->render();
        
        // 3. Sauvegarder le PDF
        file_put_contents($filepath, $dompdf->output());
        
        // 4. Optionnel : supprimer le fichier HTML temporaire
        // unlink($this->exportsDir . $htmlFile);
        
        return $filename;
    }
    
    /**
     * Nettoie le nom de fichier
     */
    private function sanitizeFilename($name) {
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
    }
}
?>