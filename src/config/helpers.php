<?php

/**
 * Charge les variables d'environnement depuis le fichier .env
 */
function loadEnv() {
    static $loaded = false;
    
    if (!$loaded) {
        $envFile = __DIR__ . '/../.env';
        
        if (!file_exists($envFile)) {
            throw new Exception("Fichier .env manquant. Créez-le à partir de .env.example");
        }
        
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Ignorer les commentaires
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Séparer le nom et la valeur
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                
                // Définir la variable d'environnement si pas déjà définie
                if (!array_key_exists($name, $_ENV)) {
                    $_ENV[$name] = $value;
                    putenv("$name=$value");
                }
            }
        }
        
        $loaded = true;
    }
}

/**
 * Récupère une variable d'environnement
 */
function env($key, $default = null) {
    // Charger .env si pas déjà fait
    loadEnv();
    
    // Chercher dans l'ordre : $_ENV -> getenv() -> $default
    $value = $_ENV[$key] ?? getenv($key) ?? $default;
    
    // Convertir 'true'/'false' en booléens
    if ($value === 'true') return true;
    if ($value === 'false') return false;
    if ($value === 'null') return null;
    
    return $value;
}