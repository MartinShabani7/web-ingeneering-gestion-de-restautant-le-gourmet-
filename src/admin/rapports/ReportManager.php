<?php
// admin/rapports/ReportManager.php

class ReportManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Récupère les templates de rapports
     */
    public function getTemplates($category = null) {
        $sql = "SELECT * FROM report_templates WHERE is_public = TRUE";
        $params = [];
        
        if ($category) {
            $sql .= " AND category = ?";
            $params[] = $category;
        }
        
        $sql .= " ORDER BY name ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Sauvegarde un rapport généré
     */
    public function saveGeneratedReport($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO generated_reports 
            (template_id, user_id, report_name, parameters, file_path, file_format) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $data['template_id'],
            $data['user_id'],
            $data['report_name'],
            json_encode($data['parameters']),
            $data['file_path'],
            $data['file_format']
        ]);
    }
    
    /**
     * Récupère l'historique des rapports
     */
    public function getReportHistory($userId, $limit = 10) {
        $stmt = $this->pdo->prepare("
            SELECT gr.*, rt.name as template_name 
            FROM generated_reports gr 
            JOIN report_templates rt ON gr.template_id = rt.id 
            WHERE gr.user_id = ? 
            ORDER BY gr.generated_at DESC 
            LIMIT ?
        ");
        
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }
}
?>