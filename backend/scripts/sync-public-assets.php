<?php
/**
 * Sync static assets from frontend/public to root public/ (docroot copy).
 * Run from project root: php backend/scripts/sync-public-assets.php
 * Use when DocumentRoot is the project root so /public/assets/* and /public/js/* resolve.
 * No webpack/vite — file copy only.
 */
$projectRoot = dirname(__DIR__, 2);
$frontendPublic = $projectRoot . '/frontend/public';
$rootPublic = $projectRoot . '/public';

$pairs = [
    // Ozon legacy paths (browser requests these URLs)
    $frontendPublic . '/assets/ozon-wizard.js' => $rootPublic . '/assets/ozon-wizard.js',
    $frontendPublic . '/assets/ozon-wizard.css' => $rootPublic . '/assets/ozon-wizard.css',
    // eMall
    $frontendPublic . '/assets/emall/emall-wizard.js' => $rootPublic . '/assets/emall/emall-wizard.js',
    $frontendPublic . '/assets/emall/emall-wizard.css' => $rootPublic . '/assets/emall/emall-wizard.css',
    $frontendPublic . '/assets/emall/steps/step1.js' => $rootPublic . '/assets/emall/steps/step1.js',
    $frontendPublic . '/assets/emall/steps/step7.js' => $rootPublic . '/assets/emall/steps/step7.js',
    $frontendPublic . '/assets/emall/steps/step6.js' => $rootPublic . '/assets/emall/steps/step6.js',
    $frontendPublic . '/assets/emall/steps/step5.js' => $rootPublic . '/assets/emall/steps/step5.js',
    $frontendPublic . '/assets/emall/steps/step4.js' => $rootPublic . '/assets/emall/steps/step4.js',
    $frontendPublic . '/assets/emall/steps/step3.js' => $rootPublic . '/assets/emall/steps/step3.js',
    $frontendPublic . '/assets/emall/steps/step2.js' => $rootPublic . '/assets/emall/steps/step2.js',
    // Shared JS
    $frontendPublic . '/js/api.js' => $rootPublic . '/js/api.js',
];

foreach ($pairs as $src => $dst) {
    if (!is_file($src)) {
        fprintf(STDERR, "Skip (missing): %s\n", $src);
        continue;
    }
    $dir = dirname($dst);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    copy($src, $dst);
    echo "Copied: " . basename($src) . " -> public/\n";
}

echo "Done.\n";
