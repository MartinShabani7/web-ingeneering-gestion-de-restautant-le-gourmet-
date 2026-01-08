<?php
// test_docker_path.php
echo "<pre>";
echo "=== DÉBOGAGE DOCKER ===\n";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Current Dir: " . getcwd() . "\n";
echo "__DIR__: " . __DIR__ . "\n\n";

// Test des chemins possibles
$paths = [
    '/var/www/html/config/',
    '/app/config/',
    __DIR__ . '/../../../config/',
    __DIR__ . '/../../config/',
    dirname(__DIR__, 2) . '/config/',
    dirname(__DIR__, 3) . '/config/',
];

foreach ($paths as $path) {
    $db = file_exists($path . 'database.php') ? '✅' : '❌';
    $sec = file_exists($path . 'security.php') ? '✅' : '❌';
    echo "$db$db $path\n";
}
echo "</pre>";