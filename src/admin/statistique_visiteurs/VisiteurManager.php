<?php
class VisiteurManager {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    // public function enregistrerVisite($page) {
    //     $session_id = session_id();
    //     $ip = $this->getIP();
    //     $user_agent = $_SERVER['HTTP_USER_AGENT'];
    //     $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        
    //     // Informations du navigateur et OS
    //     $browser = $this->getBrowser();
    //     $os = $this->getOS();
        
    //     // Insertion dans la table principale
    //     $sql = "INSERT INTO visiteurs (ip_address, user_agent, page_visited, referrer, date_visite, session_id, navigateur, systeme_exploitation) 
    //             VALUES (?, ?, ?, ?, NOW(), ?, ?, ?)";
    //     $stmt = $this->db->prepare($sql);
    //     $stmt->execute([$ip, $user_agent, $page, $referrer, $session_id, $browser, $os]);
        
    //     // Mise à jour du temps réel
    //     $this->mettreAJourTempsReel($session_id, $ip, $page);
    // }

    public function enregistrerVisite($page) {
        $session_id = session_id();
        $ip = $this->getIP();
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        
        // Récupérer la géolocalisation
        $geolocation = $this->getGeolocation($ip);
        
        // Informations du navigateur et OS
        $browser = $this->getBrowser();
        $os = $this->getOS();
        
        // Insertion dans la table principale
        $sql = "INSERT INTO visiteurs (ip_address, user_agent, page_visited, referrer, date_visite, session_id, navigateur, systeme_exploitation, pays, ville, region, fournisseur_internet, coordonnees) 
                VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        
        if (is_array($geolocation)) {
            $stmt->execute([
                $ip, $user_agent, $page, $referrer, $session_id, $browser, $os,
                $geolocation['pays'], $geolocation['ville'], $geolocation['region'], 
                $geolocation['fournisseur'], $geolocation['coordonnees']
            ]);
        } else {
            $stmt->execute([
                $ip, $user_agent, $page, $referrer, $session_id, $browser, $os,
                $geolocation, $geolocation, $geolocation, $geolocation, ''
            ]);
        }
        
        // Mise à jour du temps réel
        $this->mettreAJourTempsReel($session_id, $ip, $page);
    }
    
    private function mettreAJourTempsReel($session_id, $ip, $page) {
        $sql = "INSERT INTO visites_en_temps_reel (session_id, ip_address, derniere_activite, page_actuelle) 
                VALUES (?, ?, NOW(), ?)
                ON DUPLICATE KEY UPDATE 
                derniere_activite = NOW(), page_actuelle = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$session_id, $ip, $page, $page]);
    }
    
    public function getVisiteursEnTempsReel() {
        // Supprimer les sessions inactives depuis plus de 5 minutes
        $this->nettoyerSessionsInactives();
        
        $sql = "SELECT COUNT(*) as total FROM visites_en_temps_reel 
                WHERE derniere_activite > DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
        $stmt = $this->db->query($sql);
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
    
    public function getStatistiquesVisiteurs() {
        // Visites aujourd'hui
        $sql = "SELECT COUNT(*) as aujourdhui FROM visiteurs 
                WHERE DATE(date_visite) = CURDATE()";
        $stmt = $this->db->query($sql);
        $aujourdhui = $stmt->fetch(PDO::FETCH_ASSOC)['aujourdhui'];
        
        // Visites cette semaine
        $sql = "SELECT COUNT(*) as cette_semaine FROM visiteurs 
                WHERE YEARWEEK(date_visite) = YEARWEEK(CURDATE())";
        $stmt = $this->db->query($sql);
        $cette_semaine = $stmt->fetch(PDO::FETCH_ASSOC)['cette_semaine'];
        
        // Visites ce mois
        $sql = "SELECT COUNT(*) as ce_mois FROM visiteurs 
                WHERE MONTH(date_visite) = MONTH(CURDATE()) 
                AND YEAR(date_visite) = YEAR(CURDATE())";
        $stmt = $this->db->query($sql);
        $ce_mois = $stmt->fetch(PDO::FETCH_ASSOC)['ce_mois'];
        
        // Total visites
        $sql = "SELECT COUNT(*) as total FROM visiteurs";
        $stmt = $this->db->query($sql);
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        return [
            'aujourdhui' => $aujourdhui,
            'cette_semaine' => $cette_semaine,
            'ce_mois' => $ce_mois,
            'total' => $total
        ];
    }
    
    public function getPagesPopulaires() {
        $sql = "SELECT page_visited, COUNT(*) as visites 
                FROM visiteurs 
                WHERE date_visite > DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY page_visited 
                ORDER BY visites DESC 
                LIMIT 10";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getNavigateursStats() {
        $sql = "SELECT navigateur, COUNT(*) as count 
                FROM visiteurs 
                WHERE date_visite > DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY navigateur 
                ORDER BY count DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getHeuresAffluence() {
        $sql = "SELECT HOUR(date_visite) as heure, COUNT(*) as visites 
                FROM visiteurs 
                WHERE date_visite > DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY HOUR(date_visite) 
                ORDER BY heure";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function nettoyerSessionsInactives() {
        $sql = "DELETE FROM visites_en_temps_reel 
                WHERE derniere_activite < DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
        $this->db->exec($sql);
    }
    
    private function getIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }
    
    private function getBrowser() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        if (strpos($user_agent, 'MSIE') !== FALSE) return 'Internet Explorer';
        elseif (strpos($user_agent, 'Firefox') !== FALSE) return 'Firefox';
        elseif (strpos($user_agent, 'Chrome') !== FALSE) return 'Chrome';
        elseif (strpos($user_agent, 'Safari') !== FALSE) return 'Safari';
        elseif (strpos($user_agent, 'Opera') !== FALSE) return 'Opera';
        else return 'Autre';
    }
    
    private function getOS() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        if (strpos($user_agent, 'Windows') !== FALSE) return 'Windows';
        elseif (strpos($user_agent, 'Mac') !== FALSE) return 'Mac';
        elseif (strpos($user_agent, 'Linux') !== FALSE) return 'Linux';
        elseif (strpos($user_agent, 'Android') !== FALSE) return 'Android';
        elseif (strpos($user_agent, 'iOS') !== FALSE) return 'iOS';
        else return 'Autre';
    }

    public function getAllVisiteurs($limit = 100, $offset = 0) {
        $sql = "SELECT * FROM visiteurs 
                ORDER BY date_visite DESC 
                LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getTotalVisiteurs() {
        $sql = "SELECT COUNT(*) as total FROM visiteurs";
        $stmt = $this->db->query($sql);
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    private function getGeolocation($ip) {
        // Si c'est une IP locale, pas de géolocalisation
        if ($ip == '127.0.0.1' || $ip == '::1' || strpos($ip, '192.168.') === 0) {
            return 'Local';
        }
        
        try {
            // Utilisation du service ipapi.com (gratuit jusqu'à 1000 requêtes/mois)
            $url = "http://ip-api.com/json/{$ip}?fields=status,message,country,countryCode,regionName,city,lat,lon,isp";
            $response = file_get_contents($url);
            $data = json_decode($response, true);
            
            if ($data && $data['status'] == 'success') {
                $location = [
                    'pays' => $data['country'] ?? 'Inconnu',
                    'ville' => $data['city'] ?? 'Inconnue',
                    'region' => $data['regionName'] ?? 'Inconnue',
                    'fournisseur' => $data['isp'] ?? 'Inconnu',
                    'coordonnees' => ($data['lat'] ?? '') . ',' . ($data['lon'] ?? '')
                ];
                return $location;
            }
        } catch (Exception $e) {
            error_log("Erreur géolocalisation: " . $e->getMessage());
        }
        
        return 'Inconnu';
    }

    // private function getGeolocation($ip) {
    //     // IPs locales
    //     if ($ip == '127.0.0.1' || $ip == '::1') {
    //         return 'Local';
    //     }
        
    //     try {
    //         // Service ipinfo.io (gratuit 1000 requêtes/jour)
    //         $token = ''; // Optionnel, sans token c'est limité mais fonctionne
    //         $url = "https://ipinfo.io/{$ip}/json" . ($token ? "?token={$token}" : "");
            
    //         $response = file_get_contents($url);
    //         $data = json_decode($response, true);
            
    //         if ($data && !isset($data['error'])) {
    //             $location = [
    //                 'pays' => $data['country'] ?? 'Inconnu',
    //                 'ville' => $data['city'] ?? 'Inconnue',
    //                 'region' => $data['region'] ?? 'Inconnue',
    //                 'fournisseur' => $data['org'] ?? 'Inconnu',
    //                 'coordonnees' => $data['loc'] ?? ''
    //             ];
    //             return $location;
    //         }
    //     } catch (Exception $e) {
    //         error_log("Erreur géolocalisation: " . $e->getMessage());
    //     }
        
    //     return 'Inconnu';
    // }
}
?>